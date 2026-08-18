<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\GoogleLoginRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuthService $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->auth->register($request->validated());

        return $this->tokenResponse($result, 'Registration successful. Please verify your email.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->input('device_name', 'default'),
        );

        return $this->tokenResponse($result, 'Logged in successfully.');
    }

    public function loginWithGoogle(GoogleLoginRequest $request): JsonResponse
    {
        $result = $this->auth->loginWithGoogle(
            $request->string('access_token')->toString(),
            $request->input('device_name', 'default'),
        );

        return $this->tokenResponse($result, 'Logged in with Google successfully.');
    }

    /**
     * Rotates the refresh token used to authenticate this request.
     * Route is protected by `auth:sanctum`; we additionally require the
     * token used have the `issue-access-token` ability so a normal access
     * token can't be used to mint more tokens.
     */
    public function refresh(Request $request): JsonResponse
    {
        if (! $request->user()->tokenCan('issue-access-token')) {
            return $this->error('This token cannot be used to refresh a session.', 403);
        }

        $result = $this->auth->refresh($request->user());

        return $this->tokenResponse($result, 'Token refreshed.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return $this->success(null, 'Logged out successfully.');
    }

    public function logoutAllDevices(Request $request): JsonResponse
    {
        $this->auth->logoutAllDevices($request->user());

        return $this->success(null, 'Logged out of all devices.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()));
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = $this->auth->sendPasswordResetLink($request->string('email')->toString());

        return $status === Password::RESET_LINK_SENT
            ? $this->success(null, __($status))
            : $this->error(__($status), 422);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = $this->auth->resetPassword($request->validated());

        return $status === Password::PASSWORD_RESET
            ? $this->success(null, __($status))
            : $this->error(__($status), 422);
    }

    /**
     * GET /api/v1/auth/email/verify/{id}/{hash} — hit via the signed link
     * emailed by VerifyEmailNotification. Not behind auth:sanctum since the
     * user is verifying from an email client, not the app; the signature +
     * hash together authorize this request instead.
     */
    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse
    {
        if (! $request->hasValidSignature()) {
            return $this->error('This verification link is invalid or has expired.', 403);
        }

        $user = User::findOrFail($id);

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return $this->error('This verification link is invalid.', 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return $this->success(null, 'Email verified successfully.');
    }

    public function resendVerificationEmail(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->success(null, 'Email already verified.');
        }

        $request->user()->sendEmailVerificationNotification();

        return $this->success(null, 'Verification email resent.');
    }

    /**
     * @param array{user: User, access_token: string, refresh_token: string} $result
     */
    private function tokenResponse(array $result, string $message, int $status = 200): JsonResponse
    {
        return $this->success([
            'user' => new UserResource($result['user']),
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'token_type' => 'Bearer',
        ], $message, $status);
    }
}
