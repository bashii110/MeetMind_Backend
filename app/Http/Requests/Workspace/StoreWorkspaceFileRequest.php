<?php

namespace App\Http\Requests\Workspace;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkspaceFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by WorkspacePolicy::view at the route level
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:20480'], // 20MB, matches task attachments
        ];
    }
}
