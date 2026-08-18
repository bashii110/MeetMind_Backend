<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'role' => $this->role?->value,
            'timezone' => $this->timezone,
            'avatar' => $this->avatar,
            'provider' => $this->provider,
            'bio' => $this->bio,
            'company' => $this->company,
            'position' => $this->position,
            'skills' => $this->skills ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
