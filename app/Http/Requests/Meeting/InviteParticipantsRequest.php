<?php

namespace App\Http\Requests\Meeting;

use Illuminate\Foundation\Http\FormRequest;

class InviteParticipantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by MeetingPolicy::manageParticipants at the route level
    }

    public function rules(): array
    {
        return [
            'emails' => ['required', 'array', 'min:1'],
            'emails.*' => ['email'],
        ];
    }
}
