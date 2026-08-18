<?php

namespace App\Models;

use App\Enums\ParticipantInviteStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingParticipant extends Model
{
    protected $fillable = ['meeting_id', 'user_id', 'invite_status'];

    protected function casts(): array
    {
        return ['invite_status' => ParticipantInviteStatus::class];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
