<?php

namespace App\Http\Controllers;


use App\Http\API\Exa;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CasinoController extends Controller
{
    // ==================== LOBBY CASINO ====================
    public function index()
{
    $setting = Setting::latest()->first();

   $providers = DB::table('casino')
    ->select(
        'provider_code',
        'provider_name',
        DB::raw('MIN(image_url) as image_url'),
        DB::raw('COUNT(*) as total_game')
    )
    ->where('game_type', 'casino')
    ->where('status', 1)
    ->groupBy('provider_code', 'provider_name')
    ->orderBy('provider_name')
    ->get();

    $balance = $this->getBalance();

    return view('casino', compact(
        'providers',
        'setting',
        'balance'
    ));
}

    // ==================== PROVIDER ====================
   public function provider($provider)
{
    $provider = strtolower($provider);

    $providerInfo = DB::table('casino')
        ->where('provider_code', $provider)
        ->first();

    if (!$providerInfo) {
        abort(404);
    }

    $title = $providerInfo->provider_name;

    $gamelist = DB::table('casino')
        ->where('provider_code', $provider)
        ->where('game_type', 'casino')
        ->where('status', 1)
        ->select(
            'id',
            'game_uid',
            'game_name',
            'provider_code',
            'image_url'
        )
        ->orderBy('game_name')
        ->get();

    $setting = Setting::latest()->first();

    $balance = $this->getBalance();

    return view('gamelist', compact(
        'gamelist',
        'setting',
        'title',
        'balance'
    ));
}

public function play($game_uid)
{
    dd('MASUK CASINO PLAY');

    $game = Casino::where('game_uid', $game_uid)->firstOrFail();

    
}

// ==================== HELPER SALDO ====================
private function getBalance()
{
    if (!Auth::check()) {
        return '0,00';
    }

    try {

        $exa = new Exa();

        $balance = $exa->playerBalance(
            Auth::user()->exa_player_id
        );

        return number_format($balance, 0, ',', '.');

    } catch (\Throwable $e) {

        return '0,00';

    }
}
}