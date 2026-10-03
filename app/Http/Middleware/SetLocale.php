<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;

class SetLocale
{
    public function handle($request, Closure $next)
    {
        $supported = config('app.supported_locales', ['en']);
        $default   = config('app.locale', 'en');

        $segments = $request->segments();
        $first    = $segments[0] ?? null;

        if (count($supported) > 1) {

            if (in_array($first, $supported)) {
                session(['locale' => $first]);
                App::setLocale($first);
                URL::defaults(['locale' => $first]);

                $request->route()?->forgetParameter('locale');

                array_shift($segments);
                $request->server->set(
                    'REQUEST_URI',
                    '/' . implode('/', $segments)
                );

                return $next($request);
            }

            if ($first && strlen($first) === 2) {
                return redirect("/{$default}");
            }

            $locale = session('locale', $default);

            return redirect('/' . $locale . '/' . ltrim($request->path(), '/'));
        }

        App::setLocale($supported[0]);
        URL::defaults(['locale' => $supported[0]]);

        return $next($request);
    }

}