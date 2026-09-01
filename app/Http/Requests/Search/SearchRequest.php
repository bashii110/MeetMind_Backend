<?php

namespace App\Http\Requests\Search;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by auth:sanctum middleware at the route level
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:255'],
        ];
    }
}
