<?php

namespace App\Http\Controllers;

use App\Services\GameProviderService;
use App\Models\Game;
use App\Models\Banner;
use App\Models\Setting;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; // Tambahkan ini untuk mencatat error

class SlotController extends Controller
{
    public function index()
    {
        $banner = Banner::all();
        $setting = Setting::orderBy('created_at', 'DESC')->first();
        $balance = '0,00'; // Default

        if (Auth::check()) {
            $user = Auth::user();
            $hiddenBalance = (float) $user->saldo_slot;
            if ($hiddenBalance > 0) {
                $formattedBalance = number_format($hiddenBalance, 2, ',', '.');
                $balance = ($hiddenBalance < 1000)
                    ? '0.' . substr_replace($formattedBalance, '', -4)
                    : substr_replace($formattedBalance, '', -4);
            }
        }

        return view('slot', compact('setting', 'banner', 'balance'));
    }

    public function gameHandler($id)
    {
        $game = Game::find($id);
        if (!$game) {
            return redirect('/slots')->with('error', 'Game tidak ditemukan');
        }

        $SG = app(GameProviderService::class)->api();
        // Pastikan parameter ke opengame sesuai dengan dokumentasi API Anda
        // Periksa apakah provider_code diperlukan
        $response = $SG->opengame(Auth::user()->extplayer, $game->game_code, $game->game_provider);
        $act = json_decode($response);

        // Perbaikan: Gunakan pengecekan status yang dinamis sesuai respon API
        if (isset($act->msg) && $act->msg == 'SUCCESS') {
            $url = $act->launch_url ?? $act->gameUrl;
            return redirect()->to($url);
        } else {
            Log::error("Game Launch Error: " . $response);
            return redirect('/slots')->with('error', 'Maaf, gagal membuka game.');
        }
    }
}