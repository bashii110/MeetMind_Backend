<?php

namespace App\Http\Requests\DeviceToken;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by auth:sanctum middleware at the route level
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['sometimes', 'nullable', 'in:android,ios,web'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
