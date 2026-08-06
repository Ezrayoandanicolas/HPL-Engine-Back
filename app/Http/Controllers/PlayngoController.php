<?php

namespace App\Http\Controllers;

use App\Http\API\fiver;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class PlayngoController extends Controller
{
    public function index()
    {
        $title = 'PLAYNGO';

        // 1. Ambil data dari tabel fiver_games
        // Sesuaikan 'PLAYNGO' dengan nilai yang ada di kolom 'game_provider' tabel lu
        $gamelist = DB::table('fiver_games')
                        ->where('game_provider', 'PLAYNGO') 
                        ->where('game_category', 'slot')
                        ->where('status', 1)
                        ->get();

        $setting = Setting::orderBy('created_at', 'DESC')->first();
        $balance = '0,00';

        // 2. Logic saldo (DRY - Lebih ringkas)
        if (Auth::check()) {
            $SG = new fiver();
            $act = json_decode($SG->userbalance(Auth::user()->extplayer));
            $hiddenBalance = $act->user->balance ?? 0;

            if ($hiddenBalance > 0) {
                $formattedBalance = number_format($hiddenBalance, 2, ',', '.');
                $balance = ($hiddenBalance < 1000) 
                    ? '0.' . substr_replace($formattedBalance, '', -4) 
                    : substr_replace($formattedBalance, '', -4);
            }
        }

        return view('gamelist', compact('gamelist', 'setting', 'title', 'balance'));
    }
}