<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class AssignTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by TaskPolicy::assign at the route level
    }

    public function rules(): array
    {
        return [
            // Nullable to support unassigning.
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
