<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Setting;
use Illuminate\Http\Request;

class CheckMaintenance
{
    public function handle(Request $request, Closure $next)
    {
        $setting = Setting::orderBy('created_at', 'DESC')->first();
        if ($setting && $setting->maintenance) {
            if ($request->is('admin/*') || $request->is('login') || $request->is('api/*')) {
                return $next($request);
            }
            return response()->view('errors.maintenance');
        }
        return $next($request);
    }
}
