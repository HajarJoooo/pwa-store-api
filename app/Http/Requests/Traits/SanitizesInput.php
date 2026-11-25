<?php

namespace App\Http\Requests\Traits;

trait SanitizesInput
{
    /**
     * Sanitize free-text fields by stripping HTML tags and trimming whitespace.
     * If an optional field becomes empty, it is set to null.
     * If a required field becomes empty, it is set to empty string "" so validation fails with 422.
     *
     * @param  array<string, bool>  $fields  [field_name => is_required]
     */
    protected function sanitizeTextFields(array $fields): void
    {
        $sanitized = [];

        foreach ($fields as $field => $isRequired) {
            if ($this->has($field)) {
                $raw = $this->input($field);

                if (is_string($raw)) {
                    $clean = trim(strip_tags($raw));

                    if ($clean === '') {
                        $sanitized[$field] = $isRequired ? '' : null;
                    } else {
                        $sanitized[$field] = $clean;
                    }
                } elseif ($raw === null && ! $isRequired) {
                    $sanitized[$field] = null;
                }
            }
        }

        if (! empty($sanitized)) {
            $this->merge($sanitized);
        }
    }

    /**
     * Sanitize human name fields by trimming whitespace without stripping tags (tags will fail letter regex).
     *
     * @param  array<int, string>  $fields
     */
    protected function sanitizeNameFields(array $fields): void
    {
        $sanitized = [];

        foreach ($fields as $field) {
            if ($this->has($field)) {
                $raw = $this->input($field);
                if (is_string($raw)) {
                    $sanitized[$field] = trim($raw);
                }
            }
        }

        if (! empty($sanitized)) {
            $this->merge($sanitized);
        }
    }

    /**
     * Sanitize email and username by trimming whitespace and converting to lowercase.
     */
    protected function sanitizeAuthCredentials(bool $email = true, bool $username = true): void
    {
        $sanitized = [];

        if ($email && $this->has('email')) {
            $rawEmail = $this->input('email');
            if (is_string($rawEmail)) {
                $sanitized['email'] = strtolower(trim($rawEmail));
            }
        }

        if ($username && $this->has('username')) {
            $rawUsername = $this->input('username');
            if (is_string($rawUsername)) {
                $sanitized['username'] = strtolower(trim($rawUsername));
            }
        }

        if ($username && $this->has('login')) {
            $rawLogin = $this->input('login');
            if (is_string($rawLogin)) {
                $sanitized['login'] = strtolower(trim($rawLogin));
            }
        }

        if (! empty($sanitized)) {
            $this->merge($sanitized);
        }
    }
}
