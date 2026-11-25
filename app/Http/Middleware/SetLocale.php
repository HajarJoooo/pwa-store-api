<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Supported application locales.
     */
    public const SUPPORTED_LOCALES = ['ar', 'en'];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = self::determineLocale($request);

        App::setLocale($locale);

        $response = $next($request);

        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    public static function determineLocale(Request $request): string
    {
        $header = $request->header('Accept-Language');

        if (! empty($header)) {
            foreach (explode(',', $header) as $part) {
                $lang = strtolower(substr(trim(explode(';', $part)[0]), 0, 2));
                if (in_array($lang, self::SUPPORTED_LOCALES, true)) {
                    return $lang;
                }
            }
        }

        return config('app.locale', 'ar');
    }
}
