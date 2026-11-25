<?php

namespace App\Http\Requests\Review;

use App\Http\Requests\Traits\SanitizesInput;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReviewUpdateRequest extends FormRequest
{
    use SanitizesInput;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->sanitizeTextFields([
            'title' => false,
            'content' => false,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rating' => ['sometimes', 'required', 'integer', 'between:1,5'],
            'title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'content' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
