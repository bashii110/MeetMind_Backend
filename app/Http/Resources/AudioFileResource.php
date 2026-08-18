<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AudioFile */
class AudioFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'status' => $this->status?->value,
            'total_chunks' => $this->total_chunks,
            'received_chunks' => $this->received_chunks ?? [],
            'total_size' => $this->total_size,
            'duration_seconds' => $this->duration_seconds,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
