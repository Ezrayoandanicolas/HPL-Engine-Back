<?php

namespace App\Http\Controllers;

use App\Http\API\fiver;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class PlaystarController extends Controller
{
    public function index()
    {
        $title = 'PLAYSTAR';

        // Kita tarik data langsung dari tabel 'games' menggunakan DB Facade
        $gamelist = DB::table('games')
                        ->where('game_provider', 'PS')
                        ->where('game_category', 'SL')
                        ->get();

        $setting = Setting::orderBy('created_at', 'DESC')->first();
        $balance = $this->getBalance();

        return view('gamelist', compact('gamelist', 'setting', 'title', 'balance'));
    }

    private function getBalance()
    {
        if (!Auth::check()) return '0,00';

        $SG = new fiver();
        // Pastikan Auth::user() ada sebelum memanggil extplayer
        $extPlayer = Auth::user()->extplayer;
        $act = json_decode($SG->userbalance($extPlayer));
        
        $hiddenBalance = $act->user->balance ?? 0;

        if ($hiddenBalance <= 0) return '0,00';

        $formattedBalance = number_format($hiddenBalance, 2, ',', '.');
        
        return ($hiddenBalance < 1000) 
            ? '0.' . substr_replace($formattedBalance, '', -4) 
            : substr_replace($formattedBalance, '', -4);
    }
}