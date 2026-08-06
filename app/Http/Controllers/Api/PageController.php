<?php

namespace App\Http\Controllers\Api;

use App\Models\Bank;
use App\Models\User;
use App\Models\Banner;
use App\Models\Setting;
use App\Models\Rekening;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PageController extends BaseApiController
{
    private function getUser()
    {
        $userId = request()->input('user_id');
        if ($userId) {
            $user = User::find($userId);
            if ($user) {
                $this->syncSlotBalance($user);
            }
            return $user;
        }
        return $this->getAuthenticatedUser();
    }

    private function syncSlotBalance($user)
    {
        try {
            $api = new \App\Http\API\fiver();
            $raw = $api->userbalance($user->username);
            $res = json_decode($raw, true);
            $ggrBalance = (float) ($res['user']['balance'] ?? $res['balance'] ?? 0);
            if ($ggrBalance > 0 && abs($user->saldo_slot - $ggrBalance) > 1) {
                $user->saldo_slot = $ggrBalance;
                $user->exists = true;
                $user->save();
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('syncSlotBalance error: ' . $e->getMessage());
        }
    }

    public function home()
    {
        $banner = Banner::all();
        $setting = Setting::orderBy('created_at', 'DESC')->first();
        $user = $this->getUser();
        $balance = $user ? $this->formatBalance($user->saldo) : null;
        $totalDeposit = $user ? Transaksi::where('user_id', $user->id)->where('type', 1)->whereIn('status_id', [2])->sum('amount') : 0;
        $totalWithdraw = $user ? Transaksi::where('user_id', $user->id)->where('type', 2)->whereIn('status_id', [2])->sum('amount') : 0;

        return $this->success([
            'banner' => $banner,
            'setting' => $setting,
            'user' => $user ? $user->only(['id', 'username', 'saldo', 'saldo_slot', 'saldo_game', 'role', 'accName', 'point_player', 'exp_player', 'level', 'reward']) : null,
            'balance' => $balance,
            'total_deposit' => $totalDeposit,
            'total_withdraw' => $totalWithdraw,
        ]);
    }

    public function deposit()
    {
        $user = $this->getUser();
        if (!$user) return $this->error('Unauthenticated', 401);

        $balance = $this->formatBalance($user->saldo);
        $bank_deposite = Bank::all();
        $rekening = Rekening::orderBy('created_at', 'DESC')->where('user_id', $user->id)->first();
        $banner = Banner::all();
        $setting = Setting::orderBy('created_at', 'DESC')->first();

        return $this->success(compact('balance', 'bank_deposite', 'rekening', 'banner', 'setting'));
    }

    public function withdraw()
    {
        $user = $this->getUser();
        if (!$user) return $this->error('Unauthenticated', 401);

        $balance = $this->formatBalance($user->saldo);
        $banner = Banner::all();
        $setting = Setting::orderBy('created_at', 'DESC')->first();
        $rekening = Rekening::orderBy('created_at', 'DESC')->where('user_id', $user->id)->first();

        $saldo = (float) $user->saldo;

        return $this->success(compact('balance', 'banner', 'setting', 'rekening', 'saldo'));
    }

    public function slots()
    {
        $setting = Setting::orderBy('created_at', 'DESC')->first();
        $user = $this->getUser();
        $balance = $user ? $this->formatBalance($user->saldo) : null;

        return $this->success(compact('setting', 'balance'));
    }

    public function casino()
    {
        $setting = Setting::orderBy('created_at', 'DESC')->first();
        $user = $this->getUser();
        $balance = $user ? $this->formatBalance($user->saldo) : null;

        $providers = DB::table('casino')
            ->select(
                'provider_code',
                'provider_name',
                DB::raw('MIN(image_url) as image_url'),
                DB::raw('COUNT(*) as total_game')
            )
            ->where('game_type', 'casino')
            ->where('status', 'active')
            ->groupBy('provider_code', 'provider_name')
            ->orderBy('provider_name')
            ->get();

        return $this->success(compact('setting', 'balance', 'providers'));
    }

    public function profile()
    {
        $user = $this->getUser();
        if (!$user) return $this->error('Unauthenticated', 401);

        $balance = $this->formatBalance($user->saldo);
        $setting = Setting::orderBy('created_at', 'DESC')->first();
        $deposit = Transaksi::where('user_id', $user->id)->where('type', 1)->latest()->first();
        $withdraw = Transaksi::where('user_id', $user->id)->where('type', 2)->latest()->first();

        return $this->success(compact('setting', 'balance', 'deposit', 'withdraw'));
    }

    public function game(Request $request, $provider)
    {
        $setting = Setting::orderBy('created_at', 'DESC')->first();
        $user = $this->getUser();
        $balance = $user ? $this->formatBalance($user->saldo) : null;

        $gamelist = DB::table('casino')
            ->where('provider_code', strtoupper($provider))
            ->where('game_type', 'casino')
            ->where('status', 'active')
            ->orderBy('game_name')
            ->get();

        return $this->success(compact('setting', 'balance', 'provider', 'gamelist'));
    }
}
