<?php

namespace App\Models;

use App\Enums\MeetingMood;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Summary extends Model
{
    protected $fillable = [
        'meeting_id',
        'transcript_id',
        'executive_summary',
        'bullet_summary',
        'decisions',
        'risks',
        'next_steps',
        'deadlines',
        'mood',
    ];

    protected function casts(): array
    {
        return [
            'bullet_summary' => 'array',
            'decisions' => 'array',
            'risks' => 'array',
            'next_steps' => 'array',
            'deadlines' => 'array',
            'mood' => MeetingMood::class,
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
}
