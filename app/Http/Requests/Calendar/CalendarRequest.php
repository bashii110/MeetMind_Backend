<?php

namespace App\Http\Requests\Calendar;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class CalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by auth:sanctum middleware at the route level
    }

    public function rules(): array
    {
        return [
            'start' => ['required', 'date'],
            'end' => [
                'required',
                'date',
                'after_or_equal:start',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $start = Carbon::parse($this->input('start'));
                    $end = Carbon::parse($value);

                    // Month/week/day views (DESIGN.md 3.8) never need more
                    // than about a year in one request; this just guards
                    // against an accidentally (or maliciously) huge range.
                    if ($start->diffInDays($end) > 366) {
                        $fail('The date range may not exceed 366 days.');
                    }
                },
            ],
        ];
    }
}
