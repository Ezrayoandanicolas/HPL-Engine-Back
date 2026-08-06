<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;

class GameListController extends Controller
{
    public function getGames()
    {
        // Langsung ambil dari cache yang udah disiapin command tadi
        $games = Cache::get('master_games_exa');

        if (!$games) {
            return response()->json(['error' => 'Data belum disync, jalankan php artisan sync:all-exa'], 404);
        }

        return response()->json($games);
    }
}