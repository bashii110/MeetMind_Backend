<?php

namespace App\Models;

use App\Enums\MeetingPriority;
use App\Enums\MeetingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meeting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'owner_id',
        // Phase 10: client-generated idempotency key for offline-created
        // meetings replayed from the Flutter app's outbox — see
        // MeetingService::create().
        'client_ref',
        'title',
        'description',
        'date',
        'time',
        'location',
        'online_link',
        'priority',
        'category',
        'status',
        'last_reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'priority' => MeetingPriority::class,
            'status' => MeetingStatus::class,
            'last_reminder_sent_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    /** Convenience accessor for the User records themselves, invite status via ->pivot. */
    public function participantUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_participants')
            ->withPivot(['invite_status'])
            ->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'meeting_tags');
    }

    public function audioFiles(): HasMany
    {
        return $this->hasMany(AudioFile::class);
    }

    public function transcripts(): HasMany
    {
        return $this->hasMany(Transcript::class);
    }

    public function summary(): HasOne
    {
        return $this->hasOne(Summary::class);
    }

    public function taskCandidates(): HasMany
    {
        return $this->hasMany(TaskCandidate::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
