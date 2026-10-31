<?php

namespace App\Http\Middleware;

use Closure;

class ApiLocale
{
    public function handle($request, Closure $next)
    {
        $supported = ['en', 'ar'];
        $locale = $request->header('Accept-Language');

        if ($locale) {
            $locale = substr($locale, 0, 2);
        }

        if (!in_array($locale, $supported)) {
            $locale = 'en';
        }

        app()->setLocale($locale);

        return $next($request);
    }

}