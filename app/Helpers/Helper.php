<?php
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

if (!function_exists('setting')) {

    function setting($key, $default = null)
    {
        $settings = Cache::rememberForever('settings', function () {
            return Setting::pluck('value', 'key')->toArray();
        });

        return $settings[$key] ?? $default;
    }

}

if (!function_exists('settingMedia')) {

    function settingMedia($key, $default = null)
    {
        $settings = Setting::where('key', $key)->first()->getFirstMediaUrl($key);
        return $settings ?? $default;
    }

}

if (!function_exists('isArabic')) {

    function isArabic(): bool
    {
        return app()->getLocale() === 'ar';
    }

}

if (!function_exists('localeUrl')) {

    function localeUrl(string $locale): string
    {
        $supported = config('app.supported_locales', ['en']);
        $segments  = explode('/', trim(request()->path(), '/'));

        if (!empty($segments) && in_array($segments[0], $supported)) {
            array_shift($segments);
        }

        $newPath = $locale . (count($segments) && $segments[0] !== '' ? '/' . implode('/', $segments) : '');

        return url($newPath);
    }

}