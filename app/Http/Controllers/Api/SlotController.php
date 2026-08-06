<?php

namespace App\Http\Controllers\Api;

use App\Models\Setting;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SlotController extends BaseApiController
{
    public function index()
    {
        $setting = Setting::orderBy('created_at', 'DESC')->first();
        $providers = Provider::all();
        $user = $this->getAuthenticatedUser();
        $balance = $user ? $this->formatBalance($user->saldo) : null;

        return $this->success([
            'setting' => $setting,
            'providers' => $providers,
            'user' => $user,
            'balance' => $balance,
        ]);
    }

    public function providers()
    {
        $providers = Provider::all();
        return $this->success($providers);
    }

    public function gamesByProvider($provider)
    {
        $games = [];
        return $this->success($games);
    }
}
