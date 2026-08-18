<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TaskCandidate */
class TaskCandidateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'title' => $this->title,
            'description' => $this->description,
            'suggested_assignee_name' => $this->suggested_assignee_name,
            'suggested_assignee' => $this->suggestedAssignee ? new UserResource($this->suggestedAssignee) : null,
            'suggested_deadline' => $this->suggested_deadline?->toDateString(),
            'suggested_priority' => $this->suggested_priority,
            'status' => $this->status?->value,
        ];
    }
}
