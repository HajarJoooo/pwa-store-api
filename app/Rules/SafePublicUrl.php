<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class SafePublicUrl implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(__('validation.safe_public_url', ['attribute' => $attribute]));

            return;
        }

        // 1. Reject control characters, newlines, and whitespace
        if (preg_match('/[\x00-\x1F\x7F\s]/', $value)) {
            $fail(__('validation.safe_public_url', ['attribute' => $attribute]));

            return;
        }

        // 2. Reject explicit userinfo in URL (user:pass@)
        if (str_contains($value, '@')) {
            $fail(__('validation.safe_public_url', ['attribute' => $attribute]));

            return;
        }

        // 3. Parse URL components
        $parts = parse_url($value);
        if (! $parts || empty($parts['scheme']) || empty($parts['host'])) {
            $fail(__('validation.safe_public_url', ['attribute' => $attribute]));

            return;
        }

        // 4. Scheme must be strictly https
        if (strtolower($parts['scheme']) !== 'https') {
            $fail(__('validation.safe_public_url', ['attribute' => $attribute]));

            return;
        }

        // 5. Reject userinfo if parsed
        if (! empty($parts['user']) || ! empty($parts['pass'])) {
            $fail(__('validation.safe_public_url', ['attribute' => $attribute]));

            return;
        }

        // 6. Port must be absent or strictly 443
        if (isset($parts['port']) && (int) $parts['port'] !== 443) {
            $fail(__('validation.safe_public_url', ['attribute' => $attribute]));

            return;
        }

        $host = strtolower($parts['host']);

        // 7. Reject IPv6 in brackets or any colon in host
        if (str_starts_with($host, '[') || str_ends_with($host, ']') || str_contains($host, ':')) {
            $fail(__('validation.safe_public_url', ['attribute' => $attribute]));

            return;
        }

        // 8. Reject integer / decimal IP (e.g. 2130706433)
        if (ctype_digit($host)) {
            $fail(__('validation.safe_public_url', ['attribute' => $attribute]));

            return;
        }

        // 9. Reject standard IPv4 and IPv6
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $fail(__('validation.safe_public_url', ['attribute' => $attribute]));

            return;
        }

        // 10. Must contain at least one dot (reject single-label hosts like localhost, intranet)
        if (! str_contains($host, '.')) {
            $fail(__('validation.safe_public_url', ['attribute' => $attribute]));

            return;
        }

        // 11. Reject localhost and private / internal domain suffixes
        if ($host === 'localhost') {
            $fail(__('validation.safe_public_url', ['attribute' => $attribute]));

            return;
        }

        $blockedSuffixes = [
            '.localhost',
            '.local',
            '.internal',
            '.lan',
            '.home.arpa',
        ];

        foreach ($blockedSuffixes as $suffix) {
            if (str_ends_with($host, $suffix)) {
                $fail(__('validation.safe_public_url', ['attribute' => $attribute]));

                return;
            }
        }

        // 12. Check labels for hex/octal IP formats and numeric TLDs
        $labels = explode('.', $host);

        // If the last label (TLD) is purely numeric, it's an IP representation (e.g. 127.0.0.1)
        if (ctype_digit(end($labels))) {
            $fail(__('validation.safe_public_url', ['attribute' => $attribute]));

            return;
        }

        // Check each label
        foreach ($labels as $label) {
            if ($label === '') {
                $fail(__('validation.safe_public_url', ['attribute' => $attribute]));

                return;
            }

            // Hex prefix (e.g. 0x7f)
            if (str_starts_with($label, '0x')) {
                $fail(__('validation.safe_public_url', ['attribute' => $attribute]));

                return;
            }

            // Octal prefix (e.g. 0177)
            if (strlen($label) > 1 && str_starts_with($label, '0') && ctype_digit($label)) {
                $fail(__('validation.safe_public_url', ['attribute' => $attribute]));

                return;
            }
        }
    }
}
