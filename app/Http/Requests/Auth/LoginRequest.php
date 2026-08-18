<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            // Optional human-readable device name so users can tell tokens
            // apart later (e.g. "Ali's Pixel 8"). Falls back to a default.
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
