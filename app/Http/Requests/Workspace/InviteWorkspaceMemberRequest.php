<?php

namespace App\Http\Requests\Workspace;

use Illuminate\Foundation\Http\FormRequest;

class InviteWorkspaceMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by WorkspacePolicy::manageMembers at the route level
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'role' => ['sometimes', 'in:admin,member'], // owner is assigned only at workspace creation
        ];
    }
}
