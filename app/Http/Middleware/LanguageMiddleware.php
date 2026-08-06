<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class LanguageMiddleware
{
    public function handle($request, Closure $next)
    {
        $supportedLocales = config('app.supported_locales', ['id', 'en']);
        $defaultLocale = config('app.locale', 'id');

        if (Session::has('locale')) {
            $locale = Session::get('locale');

            // Validasi: pastikan locale yang disimpan valid
            if (in_array($locale, $supportedLocales)) {
                App::setLocale($locale);
            } else {
                // Kalau tidak valid, pakai default
                App::setLocale($defaultLocale);
                Session::put('locale', $defaultLocale);
            }
        } else {
            // Kalau belum ada session, pakai default
            App::setLocale($defaultLocale);
            Session::put('locale', $defaultLocale);
        }

        return $next($request);
    }
}