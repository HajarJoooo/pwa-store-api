<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sort' => ['nullable', 'string', Rule::in(['newest', 'helpful'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
