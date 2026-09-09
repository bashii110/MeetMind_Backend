<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ModerateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the 'system_admin' middleware at the route level
    }

    public function rules(): array
    {
        return [
            // 'pending' is deliberately excluded — moderating a report
            // always moves it out of the pending queue.
            'status' => ['required', 'in:reviewed,dismissed,actioned'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
