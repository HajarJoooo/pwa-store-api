<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Defines permitted cross-origin access rules for the PWA Store API.
    | In production, wildcards (*) are forbidden and origins are strictly
    | resolved from the CORS_ALLOWED_ORIGINS environment variable.
    |
    */

    'paths' => ['api/*', 'up'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => (function (): array {
        $origins = env('CORS_ALLOWED_ORIGINS');
        if ($origins !== null && trim($origins) !== '') {
            $parsed = array_values(array_filter(array_map('trim', explode(',', $origins))));
            // In production, strictly reject '*'
            if (env('APP_ENV') === 'production') {
                return array_values(array_filter($parsed, fn ($origin) => $origin !== '*'));
            }

            return $parsed;
        }

        return env('APP_ENV') === 'production' ? [] : ['*'];
    })(),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Authorization',
        'Content-Type',
        'Accept',
        'Accept-Language',
        'X-Requested-With',
    ],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => false,

];
