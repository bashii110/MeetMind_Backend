<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\MeetingParticipant */
class MeetingParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invite_status' => $this->invite_status?->value,
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
