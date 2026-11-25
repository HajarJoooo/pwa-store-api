<?php

namespace App\Http\Requests\Developer;

use App\Http\Requests\Traits\SanitizesInput;
use App\Rules\SafePublicUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeveloperPwaStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('is_active', true),
            ],
            'short_description' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'manifest_url' => ['required', 'url', 'starts_with:https://', 'max:500', new SafePublicUrl],
            'start_url' => ['required', 'url', 'starts_with:https://', 'max:500', new SafePublicUrl],
            'icon_url' => ['nullable', 'url', 'starts_with:https://', 'max:500', new SafePublicUrl],
            'screenshots' => ['nullable', 'array', 'max:8'],
            'screenshots.*' => ['required', 'url', 'starts_with:https://', 'max:500', new SafePublicUrl],
            'version' => ['nullable', 'string', 'regex:/^\d{1,4}\.\d{1,4}\.\d{1,4}$/', 'max:30'],
            'pricing_type' => ['nullable', 'string', 'in:free,paid,freemium'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'privacy_policy_url' => ['nullable', 'url', 'starts_with:https://', 'max:500', new SafePublicUrl],
            'compatibility' => ['nullable', 'array'],
        ];
    }
}
