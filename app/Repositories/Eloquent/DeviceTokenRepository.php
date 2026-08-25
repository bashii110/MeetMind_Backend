<?php

namespace App\Repositories\Eloquent;

use App\Models\DeviceToken;
use App\Models\User;
use App\Repositories\Contracts\DeviceTokenRepositoryInterface;
use Illuminate\Support\Collection;

class DeviceTokenRepository extends BaseRepository implements DeviceTokenRepositoryInterface
{
    public function __construct(DeviceToken $model)
    {
        parent::__construct($model);
    }

    public function forUser(User $user): Collection
    {
        return $user->deviceTokens()->get();
    }

    public function upsertForUser(User $user, string $token, ?string $platform, ?string $deviceName): void
    {
        DeviceToken::query()->updateOrCreate(
            ['token' => $token],
            [
                'user_id' => $user->id,
                'platform' => $platform,
                'device_name' => $deviceName,
                'last_used_at' => now(),
            ],
        );
    }

    public function deleteByToken(User $user, string $token): void
    {
        DeviceToken::query()->where('user_id', $user->id)->where('token', $token)->delete();
    }

    public function forceDeleteByToken(string $token): void
    {
        DeviceToken::query()->where('token', $token)->delete();
    }
}
