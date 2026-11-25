<?php

namespace App\Http\Requests\Pwa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PwaIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'min:2', 'max:100'],
            'category' => ['nullable', 'string', Rule::exists('categories', 'slug')],
            'sort' => ['nullable', 'string', Rule::in(['newest', 'popular', 'top_rated'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
