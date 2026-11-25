<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Traits\SanitizesInput;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->sanitizeAuthCredentials(email: false, username: true);
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
