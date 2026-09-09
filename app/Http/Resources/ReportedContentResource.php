<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ReportedContent */
class ReportedContentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            // class_basename so the API surfaces "Task"/"Meeting"/"TaskComment"
            // rather than leaking the fully-qualified class name stored
            // internally (same morph-storage convention as ActivityLog).
            'subject_type' => class_basename($this->subject_type),
            'subject_id' => $this->subject_id,
            'reason' => $this->reason,
            'details' => $this->details,
            'status' => $this->status?->value,
            'reported_by' => new UserResource($this->whenLoaded('reporter')),
            'resolved_by' => $this->resolved_by ? new UserResource($this->whenLoaded('resolver')) : null,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolution_notes' => $this->resolution_notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
