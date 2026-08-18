<?php

namespace App\Http\Requests\AudioFile;

use Illuminate\Foundation\Http\FormRequest;

class StoreAudioChunkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by MeetingPolicy::view at the route level
    }

    public function rules(): array
    {
        return [
            'chunk_index' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file', 'max:10240'], // 10MB per chunk ceiling
        ];
    }
}
