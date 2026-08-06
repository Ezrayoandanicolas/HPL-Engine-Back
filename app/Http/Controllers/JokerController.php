<?php

namespace App\Http\Controllers;

use App\Http\API\fiver;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // Tambahin ini buat panggil tabel manual
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class JokerController extends Controller
{
    public function index()
    {
        $title = 'JOKER';

        // FIX: Tembak langsung ke tabel fiver_games sesuai database lu!
        $gamelist = DB::table('fiver_games')
                        ->where('game_provider', 'JOKERGAMING')
                        ->where('game_category', 'slot')
                        ->where('status', 1) // Sekarang aman karena kolomnya ada
                        ->get();

        $setting = Setting::orderBy('created_at', 'DESC')->first();
        $balance = '0,00'; 

        if (Auth::check()) {
            $SG = new fiver();
            $act = json_decode($SG->userbalance(Auth::user()->extplayer));
            
            $hiddenBalance = $act->user->balance ?? 0;

            if ($hiddenBalance > 0) {
                $formattedBalance = number_format($hiddenBalance, 2, ',', '.');
                if ($hiddenBalance < 1000) {
                    $balance = '0.' . substr_replace($formattedBalance, '', -4);
                } else {
                    $balance = substr_replace($formattedBalance, '', -4);
                }
            }
        }

        return view('gamelist', compact('gamelist', 'setting', 'title', 'balance'));
    }
}