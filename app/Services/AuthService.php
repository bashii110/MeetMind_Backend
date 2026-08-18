<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

/**
 * Encapsulates the full account lifecycle (FR-1.x). Controllers stay thin
 * and just translate HTTP <-> these methods, per ARCHITECTURE.md 3.2.
 *
 * Token strategy (ARCHITECTURE.md 3.3 — "JWT-style access + refresh
 * tokens" via Sanctum): Sanctum tokens aren't JWTs, but we get the same
 * short-lived-access / long-lived-refresh UX by issuing *two* personal
 * access tokens per login:
 *   - "access_token"  — short TTL (SANCTUM_TOKEN_EXPIRATION), ability ['*']
 *   - "refresh_token" — long TTL (30 days), ability ['issue-access-token']
 *                       only, so it can't be used to call ordinary
 *                       endpoints even if leaked to something that only
 *                       expects a normal bearer token.
 * Refreshing revokes the old refresh token and issues a new pair
 * (rotation), so a stolen refresh token has a single-use window.
 */
class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly WorkspaceService $workspaces,
    ) {}

    /**
     * @return array{user: User, access_token: string, refresh_token: string}
     */
    public function register(array $data): array
    {
        $user = $this->users->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'timezone' => $data['timezone'] ?? 'UTC',
            'role' => UserRole::RegularUser,
        ]);

        $this->workspaces->createPersonalWorkspace($user);

        event(new Registered($user));

        return $this->issueTokenPair($user, $data['device_name'] ?? 'default');
    }

    /**
     * @return array{user: User, access_token: string, refresh_token: string}
     *
     * @throws ValidationException
     */
    public function login(string $email, string $password, string $deviceName = 'default'): array
    {
        $user = $this->users->findByEmail($email);

        if (! $user || ! $user->password || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $this->guardAgainstDisabledAccount($user);

        return $this->issueTokenPair($user, $deviceName);
    }

    /**
     * FR-1.2: Google OAuth. The Flutter app signs in with Google natively
     * (google_sign_in package) and hands us the resulting access token;
     * we verify it server-side rather than doing a redirect-based OAuth
     * dance, which doesn't fit a mobile client well.
     *
     * @return array{user: User, access_token: string, refresh_token: string}
     */
    public function loginWithGoogle(string $googleAccessToken, string $deviceName = 'default'): array
    {
        $googleUser = Socialite::driver('google')->stateless()->userFromToken($googleAccessToken);

        $user = $this->users->findByProvider('google', $googleUser->getId())
            ?? $this->users->findByEmail($googleUser->getEmail());

        if ($user) {
            $this->guardAgainstDisabledAccount($user);

            if (! $user->provider) {
                // An email/password account signing in with Google for the
                // first time — link the accounts rather than duplicating.
                $user = $this->users->update($user, [
                    'provider' => 'google',
                    'provider_id' => $googleUser->getId(),
                ]);
            }
        } else {
            $user = $this->users->create([
                'name' => $googleUser->getName() ?? $googleUser->getNickname() ?? 'MeetMind User',
                'email' => $googleUser->getEmail(),
                'password' => null,
                'provider' => 'google',
                'provider_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'role' => UserRole::RegularUser,
                'email_verified_at' => now(), // Google has already verified this address
            ]);

            $this->workspaces->createPersonalWorkspace($user);

            event(new Registered($user));
        }

        return $this->issueTokenPair($user, $deviceName);
    }

    /**
     * Rotates the refresh token used to authenticate this request into a
     * brand new access/refresh pair. The route calling this must be
     * protected by `auth:sanctum` and the current token's ability checked
     * (see AuthController::refresh) so only refresh tokens can hit it.
     *
     * @return array{user: User, access_token: string, refresh_token: string}
     */
    public function refresh(User $user): array
    {
        $user->currentAccessToken()?->delete();

        return $this->issueTokenPair($user, 'default');
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    public function logoutAllDevices(User $user): void
    {
        $user->tokens()->delete();
    }

    public function sendPasswordResetLink(string $email): string
    {
        return Password::sendResetLink(['email' => $email]);
    }

    public function resetPassword(array $data): string
    {
        return Password::reset(
            $data,
            function (User $user, string $password) {
                $this->users->update($user, ['password' => Hash::make($password)]);
                $user->tokens()->delete(); // force re-login everywhere after a reset
            },
        );
    }

    private function guardAgainstDisabledAccount(User $user): void
    {
        if ($user->isDisabled()) {
            throw ValidationException::withMessages([
                'email' => ['This account has been disabled. Contact support.'],
            ]);
        }
    }

    /**
     * @return array{user: User, access_token: string, refresh_token: string}
     */
    private function issueTokenPair(User $user, string $deviceName): array
    {
        $accessTtl = now()->addMinutes((int) config('sanctum.expiration', 1440));
        $refreshTtl = now()->addDays(30);

        $accessToken = $user->createToken("access:{$deviceName}", ['*'], $accessTtl)->plainTextToken;
        $refreshToken = $user->createToken("refresh:{$deviceName}", ['issue-access-token'], $refreshTtl)->plainTextToken;

        return [
            'user' => $user,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ];
    }
}
