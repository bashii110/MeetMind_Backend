<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Support\Collection;

interface DeviceTokenRepositoryInterface extends RepositoryInterface
{
    public function forUser(User $user): Collection;

    /**
     * Registers (or moves ownership of) a token. Tokens are unique per
     * app install, not per user, so re-registering an existing token
     * under a different user reassigns it rather than erroring or
     * duplicating — see the unique index on device_tokens.token.
     */
    public function upsertForUser(User $user, string $token, ?string $platform, ?string $deviceName): void;

    /** Scoped to the owning user so a guessed token can't be deleted by someone else. */
    public function deleteByToken(User $user, string $token): void;

    /** Used by FcmService when FCM itself reports a token as dead. */
    public function forceDeleteByToken(string $token): void;
}
