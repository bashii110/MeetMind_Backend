<?php

namespace App\Http\Requests\Sync;

use Illuminate\Foundation\Http\FormRequest;

class SyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // workspace membership checked in the controller
    }

    public function rules(): array
    {
        return [
            'workspace_id' => ['sometimes', 'integer', 'exists:workspaces,id'],
            // Omit entirely for a first-time/full sync (e.g. a fresh
            // install) — SyncController treats a missing `since` as
            // "everything", per SyncService::forWorkspace().
            'since' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
