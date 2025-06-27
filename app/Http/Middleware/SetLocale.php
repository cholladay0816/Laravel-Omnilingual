<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // Get the preferred language from the request
        $locale = $request->getPreferredLanguage(config('app.supported_locales', ['en']));
        // If the locale is not supported, fallback to the default locale
        if (!in_array($locale, config('app.supported_locales', ['en']))) {
            $locale = config('app.fallback_locale', 'en');
        }

        // Set the application locale
        App::setLocale($locale);

        return $next($request);
    }
}
