<?php

namespace App\Models;

use App\Enums\AudioFileStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AudioFile extends Model
{
    protected $fillable = [
        'meeting_id',
        'uploaded_by',
        'status',
        'error_message',
        'mime_type',
        'extension',
        'total_size',
        'total_chunks',
        'received_chunks',
        'duration_seconds',
        'path',
    ];

    protected function casts(): array
    {
        return [
            'status' => AudioFileStatus::class,
            'received_chunks' => 'array',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function transcript(): HasOne
    {
        return $this->hasOne(Transcript::class);
    }

    public function hasReceivedChunk(int $index): bool
    {
        return in_array($index, $this->received_chunks ?? [], true);
    }
}
