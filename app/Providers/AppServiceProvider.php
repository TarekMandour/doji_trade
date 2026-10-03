<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (session()->has('locale')) {
            URL::defaults([
                'locale' => session('locale')
            ]);
        } else {
            URL::defaults([
                'locale' => config('app.locale')
            ]);
        }

    }
}
