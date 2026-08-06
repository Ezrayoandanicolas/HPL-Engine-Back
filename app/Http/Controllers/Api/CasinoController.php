<?php

namespace App\Http\Controllers\Api;

use App\Models\Setting;
use Illuminate\Http\Request;

class CasinoController extends BaseApiController
{
    public function index()
    {
        $setting = Setting::orderBy('created_at', 'DESC')->first();
        $user = $this->getAuthenticatedUser();
        $balance = $user ? $this->formatBalance($user->saldo) : null;

        return $this->success([
            'setting' => $setting,
            'user' => $user,
            'balance' => $balance,
        ]);
    }

    public function provider($provider)
    {
        $setting = Setting::orderBy('created_at', 'DESC')->first();
        $user = $this->getAuthenticatedUser();
        $balance = $user ? $this->formatBalance($user->saldo) : null;

        return $this->success([
            'setting' => $setting,
            'provider' => $provider,
            'user' => $user,
            'balance' => $balance,
        ]);
    }
}
