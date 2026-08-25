<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single FCM registration token for one app install on one device.
 * See DeviceTokenRepository::upsertForUser for the reassignment
 * behavior when the same token shows up under a different user.
 */
class DeviceToken extends Model
{
    protected $fillable = ['user_id', 'token', 'platform', 'device_name', 'last_used_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
