<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Meeting */
class MeetingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            // Phase 10: echoed back so the offline client can reconcile
            // its local draft with the server-confirmed record after sync.
            'client_ref' => $this->client_ref,
            'title' => $this->title,
            'description' => $this->description,
            'date' => $this->date?->toDateString(),
            'time' => $this->time,
            'location' => $this->location,
            'online_link' => $this->online_link,
            'priority' => $this->priority?->value,
            'category' => $this->category,
            'status' => $this->status?->value,
            'owner' => new UserResource($this->whenLoaded('owner')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'participants' => MeetingParticipantResource::collection($this->whenLoaded('participants')),
            'participant_count' => $this->whenCounted('participants'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
