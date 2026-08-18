<?php

namespace App\Http\Requests\AudioFile;

use Illuminate\Foundation\Http\FormRequest;

class InitAudioUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by MeetingPolicy::view at the route level
    }

    public function rules(): array
    {
        return [
            'mime_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'extension' => ['sometimes', 'nullable', 'string', 'in:m4a,aac,wav,mp3,mp4'],
            'total_size' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'total_chunks' => ['required', 'integer', 'min:1'],
            'duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
