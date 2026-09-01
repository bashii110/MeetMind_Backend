<?php

namespace App\Http\Requests\Assistant;

use Illuminate\Foundation\Http\FormRequest;

class AssistantQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by MeetingPolicy::view at the route level
    }

    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'max:2000'],
        ];
    }
}
