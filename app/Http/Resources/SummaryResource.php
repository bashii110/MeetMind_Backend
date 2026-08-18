<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Summary */
class SummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'executive_summary' => $this->executive_summary,
            'bullet_summary' => $this->bullet_summary,
            'decisions' => $this->decisions,
            'risks' => $this->risks,
            'next_steps' => $this->next_steps,
            'deadlines' => $this->deadlines,
            'mood' => $this->mood?->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
