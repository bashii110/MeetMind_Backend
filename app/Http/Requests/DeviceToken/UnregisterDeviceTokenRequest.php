<?php

namespace App\Http\Requests\DeviceToken;

use Illuminate\Foundation\Http\FormRequest;

class UnregisterDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
        ];
    }
}
