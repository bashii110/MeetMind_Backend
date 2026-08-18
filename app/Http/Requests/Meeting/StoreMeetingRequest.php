<?php

namespace App\Http\Requests\Meeting;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // workspace membership checked in the controller
    }

    public function rules(): array
    {
        return [
            'workspace_id' => ['sometimes', 'integer', 'exists:workspaces,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'date' => ['required', 'date'],
            'time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'online_link' => ['sometimes', 'nullable', 'url', 'max:255'],
            'priority' => ['sometimes', 'in:low,medium,high'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'in:draft,scheduled'], // creation only; completed/cancelled via the status endpoint
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],
            'participant_emails' => ['sometimes', 'array'],
            'participant_emails.*' => ['email'],
        ];
    }
}
