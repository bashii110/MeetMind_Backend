<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Deliberately named AppNotification, not Notification: Laravel's own
 * notification *classes* (VerifyEmailNotification, etc.) already use that
 * word, and Laravel ships a different built-in "database notifications"
 * Eloquent model/schema (uuid + notifiable polymorphic + data) which this
 * intentionally is NOT — it follows ARCHITECTURE.md's simpler
 * `notifications(user_id, type, payload, read_at)` schema instead.
 */
class AppNotification extends Model
{
    protected $table = 'notifications';

    protected $fillable = ['user_id', 'type', 'payload', 'read_at'];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'payload' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->update(['read_at' => now()]);
        }
    }
}
