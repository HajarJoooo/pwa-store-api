<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Traits\SanitizesInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->sanitizeNameFields(['first_name', 'last_name']);
        $this->sanitizeAuthCredentials(email: true, username: true);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100', 'regex:/^[\p{L}\p{M}\s\'.\-]+$/u'],
            'last_name' => ['required', 'string', 'max:100', 'regex:/^[\p{L}\p{M}\s\'.\-]+$/u'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'string', Rule::in(['user', 'developer'])],
        ];
    }
}
