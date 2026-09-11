<?php

namespace App\Http\Requests\Meeting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by MeetingPolicy::update at the route level
    }

    public function rules(): array
    {
        return [
            // Phase 10: the `updated_at` this client's local cache last
            // saw for this meeting. If it no longer matches the server's
            // current value, MeetingService::update() throws a
            // SyncConflictException instead of silently overwriting a
            // change made elsewhere in the meantime. Omit this field
            // entirely to skip the check (e.g. a normal always-online edit).
            'client_updated_at' => ['sometimes', 'nullable', 'date'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'date' => ['sometimes', 'date'],
            'time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'online_link' => ['sometimes', 'nullable', 'url', 'max:255'],
            'priority' => ['sometimes', 'in:low,medium,high'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }
}
