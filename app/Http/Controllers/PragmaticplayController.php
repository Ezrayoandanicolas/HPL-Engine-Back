<?php

namespace App\Http\Controllers;

use App\Http\API\fiver;
use App\Models\Banner;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class PragmaticplayController extends Controller
{
    public function index()
    {
        $title = 'PRAGMATICPLAY';
        $setting = Setting::orderBy('created_at', 'DESC')->first();
        $gamelist = DB::table('fiver_games')
                    ->where('game_provider', 'PRAGMATIC')
                    ->where('game_category', 'slot')
                    ->get();
        
        $balance = '0,00';

        if (Auth::check()) {
            $SG = new fiver();
            $act = json_decode($SG->userbalance(Auth()->user()->extplayer));
            
            // Cek aman untuk saldo
            if (isset($act->user) && isset($act->user->balance)) {
                $hiddenBalance = (float)$act->user->balance;
                $formattedBalance = number_format($hiddenBalance, 2, ',', '.');
                $balance = ($hiddenBalance < 1000 && $hiddenBalance > 0) 
                    ? '0.' . substr_replace($formattedBalance, '', -4) 
                    : substr_replace($formattedBalance, '', -4);
            }
            
            return view('gamelist', compact('gamelist', 'setting', 'title', 'balance'));
        }

        return view('gamelist', compact('gamelist', 'setting', 'title'));
    }
}