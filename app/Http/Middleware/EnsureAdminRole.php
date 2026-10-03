<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\App;

class EnsureAdminRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('admin')->check()) {          
            return redirect(route('admin.login', ['locale' => app()->getLocale()]));
        }

        // Make the admin guard the default so Gate::check / @can / permission middleware
        // work correctly throughout admin routes without specifying the guard explicitly.
        Auth::shouldUse('admin');

        return $next($request);
    }
}
