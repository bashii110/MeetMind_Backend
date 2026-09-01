<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Workspace */
class WorkspaceResource extends JsonResource
{
    private ?string $myRole;

    public function __construct($resource, ?string $myRole = null)
    {
        parent::__construct($resource);

        $this->myRole = $myRole;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'owner_id' => $this->owner_id,
            'owner_name' => $this->owner?->name,

            'my_role' => $this->myRole ?? $this->pivot?->role,

            'member_count' => $this->members_count ?? 0,
            'department_count' => $this->departments_count ?? 0,

            'created_at' => $this->created_at?->toISOString(),

            'members' => WorkspaceMemberResource::collection(
                $this->whenLoaded('members')
            ),

            'departments' => DepartmentResource::collection(
                $this->whenLoaded('departments')
            ),
        ];
    }
}
