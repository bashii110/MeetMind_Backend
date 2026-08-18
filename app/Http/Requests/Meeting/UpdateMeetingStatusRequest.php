<?php

namespace App\Http\Requests\Meeting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by MeetingPolicy::update at the route level
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:scheduled,completed,cancelled'],
        ];
    }
}
