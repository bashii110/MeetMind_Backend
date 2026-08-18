<?php

namespace App\Models;

use App\Enums\TaskCandidateStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskCandidate extends Model
{
    protected $fillable = [
        'meeting_id',
        'transcript_id',
        'title',
        'description',
        'suggested_assignee_name',
        'suggested_assignee_user_id',
        'suggested_deadline',
        'suggested_priority',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'suggested_deadline' => 'date',
            'status' => TaskCandidateStatus::class,
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function transcript(): BelongsTo
    {
        return $this->belongsTo(Transcript::class);
    }

    public function suggestedAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suggested_assignee_user_id');
    }

    public function confirmedTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'confirmed_task_id');
    }
}
