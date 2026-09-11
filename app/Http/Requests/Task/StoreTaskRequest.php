<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // workspace membership checked in the controller
    }

    public function rules(): array
    {
        return [
            'workspace_id' => ['sometimes', 'integer', 'exists:workspaces,id'],
            // Phase 10: client-generated UUID for offline-created tasks,
            // so replaying this request from the outbox is idempotent —
            // see TaskService::create().
            'client_ref' => ['sometimes', 'nullable', 'uuid'],
            'meeting_id' => ['sometimes', 'nullable', 'integer', 'exists:meetings,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'priority' => ['sometimes', 'in:low,medium,high'],
            'status' => ['sometimes', 'in:pending,in_progress'], // completed/cancelled via the status endpoint
            'deadline' => ['sometimes', 'nullable', 'date'],
            'assigned_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
