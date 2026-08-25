<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Workspace */
class WorkspaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'owner_id' => $this->owner_id,
            'role' => $this->when(
                $this->pivot !== null,
                fn () => $this->pivot?->role,
            ),
            'member_count' => $this->whenCounted('members'),
            'members' => WorkspaceMemberResource::collection($this->whenLoaded('members')),
            'departments' => DepartmentResource::collection($this->whenLoaded('departments')),
        ];
    }
}
