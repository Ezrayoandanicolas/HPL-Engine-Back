<?php

namespace App\Http\Controllers;

use App\Http\API\fiver;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class SpadegamingController extends Controller
{
    public function index()
    {
        $title = 'SPADEGAMING';

        /**
         * Menggunakan tabel 'games' sesuai struktur database lu.
         * Filter 'status' dihapus karena kolomnya tidak ditemukan di tabel tersebut.
         */
        $gamelist = DB::table('fiver_games')
                        ->where('game_provider', 'SPADEGAMING') 
                        ->where('game_category', 'slot')
                        ->get();

        $setting = Setting::orderBy('created_at', 'DESC')->first();
        $balance = $this->getBalance();

        return view('gamelist', compact('gamelist', 'setting', 'title', 'balance'));
    }

    /**
     * Fungsi helper untuk mengambil saldo agar kode lebih bersih (DRY).
     */
    private function getBalance()
    {
        if (!Auth::check()) return '0,00';

        $SG = new fiver();
        $act = json_decode($SG->userbalance(Auth::user()->extplayer));
        $hiddenBalance = $act->user->balance ?? 0;

        if ($hiddenBalance <= 0) return '0,00';

        $formattedBalance = number_format($hiddenBalance, 2, ',', '.');
        
        return ($hiddenBalance < 1000) 
            ? '0.' . substr_replace($formattedBalance, '', -4) 
            : substr_replace($formattedBalance, '', -4);
    }
}