<?php

namespace App\Http\Requests\Developer;

use App\Http\Requests\Traits\SanitizesInput;
use App\Rules\SafePublicUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeveloperPwaUpdateRequest extends FormRequest
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
            'name' => true,
            'short_description' => true,
            'description' => false,
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
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'category_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('is_active', true),
            ],
            'short_description' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'manifest_url' => ['sometimes', 'required', 'url', 'starts_with:https://', 'max:500', new SafePublicUrl],
            'start_url' => ['sometimes', 'required', 'url', 'starts_with:https://', 'max:500', new SafePublicUrl],
            'icon_url' => ['sometimes', 'nullable', 'url', 'starts_with:https://', 'max:500', new SafePublicUrl],
            'screenshots' => ['sometimes', 'nullable', 'array', 'max:8'],
            'screenshots.*' => ['required', 'url', 'starts_with:https://', 'max:500', new SafePublicUrl],
            'version' => ['sometimes', 'nullable', 'string', 'regex:/^\d{1,4}\.\d{1,4}\.\d{1,4}$/', 'max:30'],
            'pricing_type' => ['sometimes', 'nullable', 'string', 'in:free,paid,freemium'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999.99'],
            'privacy_policy_url' => ['sometimes', 'nullable', 'url', 'starts_with:https://', 'max:500', new SafePublicUrl],
            'compatibility' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $updatableFields = [
                'name',
                'category_id',
                'short_description',
                'description',
                'manifest_url',
                'start_url',
                'icon_url',
                'screenshots',
                'version',
                'pricing_type',
                'price',
                'privacy_policy_url',
                'compatibility',
            ];

            $hasValidField = false;
            foreach ($updatableFields as $field) {
                if ($this->has($field) && $this->input($field) !== null) {
                    $hasValidField = true;
                    break;
                }
            }

            if (! $hasValidField) {
                $validator->errors()->add('payload', __('validation.at_least_one_field_required'));
            }
        });
    }
}
