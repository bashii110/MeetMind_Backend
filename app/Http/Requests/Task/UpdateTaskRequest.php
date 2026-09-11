<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by TaskPolicy::update at the route level
    }

    public function rules(): array
    {
        return [
            // Phase 10: see UpdateMeetingRequest's client_updated_at doc —
            // identical mechanism, applied here since ARCHITECTURE.md 2.3
            // calls out task edits by name for the "manual merge prompt".
            'client_updated_at' => ['sometimes', 'nullable', 'date'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'priority' => ['sometimes', 'in:low,medium,high'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'meeting_id' => ['sometimes', 'nullable', 'integer', 'exists:meetings,id'],
        ];
    }
}
