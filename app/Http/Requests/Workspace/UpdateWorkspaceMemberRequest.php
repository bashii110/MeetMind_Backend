<?php

namespace App\Http\Requests\Workspace;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspaceMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by WorkspacePolicy::manageMembers at the route level
    }

    public function rules(): array
    {
        return [
            'role' => ['sometimes', 'in:admin,member'],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
        ];
    }
}
