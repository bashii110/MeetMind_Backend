<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // visibility into the reported subject is checked in ModerationService::report
    }

    public function rules(): array
    {
        return [
            // Whitelisted, human-friendly type strings rather than raw
            // class names — ModerationService maps these to the actual
            // model classes so a caller can never report against an
            // arbitrary Eloquent model.
            'subject_type' => ['required', 'in:task,meeting,task_comment'],
            'subject_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:255'],
            'details' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
