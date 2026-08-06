<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class PlayController extends Controller
{
    public function show($id)
    {
        $game = DB::table('fiver_games')
            ->where('id', $id)
            ->orWhere('game_code', $id)
            ->first();

        if (!$game) {
            return back()->with('error', 'Game tidak ditemukan.');
        }

        // ==========================
        // CEK SALDO SLOT (LOKAL)
        // ==========================
        $slotBalance = (float) Auth::user()->saldo_slot;

        if ($slotBalance <= 0) {
            return redirect()->route('transfer', [
                'from' => 'main',
                'to'   => 'slot'
            ])->with(
                'warning',
                'Saldo Slot Anda kosong. Silakan transfer saldo ke Slot terlebih dahulu.'
            );
        }

        // ==========================
        // API FIVER TIDAK DAPAT DIAKSES
        // Tampilkan halaman maintenance
        // ==========================
        return view('game-maintenance', compact('game'));
    }
}
