<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use App\Services\WalletService;

class ShareUserBalance
{
    public function handle($request, Closure $next)
    {
        if (Auth::check()) {

            $wallet = app(WalletService::class);

            View::share('mainBalance', $wallet->getMainBalance(Auth::user()));
            View::share('slotBalance', $wallet->getSlotBalance(Auth::user()));
            View::share('gameBalance', $wallet->getGameBalance(Auth::user()));

            // Supaya template lama yang masih pakai $balance tetap jalan
            View::share('balance', $wallet->getMainBalance(Auth::user()));
        }

        return $next($request);
    }
}