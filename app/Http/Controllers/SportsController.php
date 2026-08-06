<?php

namespace App\Http\Controllers;

use App\Http\API\Exa;
use App\Models\Sport;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;

class SportsController extends Controller
{
    public function index()
    {
        $title = 'SPORTS';

        $gamelist = Sport::where('status', 1)
            ->orderBy('provider_name')
            ->get();

        foreach ($gamelist as $game) {

            $game->image_url = trim($game->image_url);

            // Alias supaya blade lama tetap jalan
            $game->img        = $game->image_url;
            $game->image      = $game->image_url;
            $game->images     = $game->image_url;
            $game->logo       = $game->image_url;
            $game->icon       = $game->image_url;
            $game->thumbnail  = $game->image_url;
            $game->game_image = $game->image_url;
            $game->picture    = $game->image_url;
        }

        $setting = Setting::latest()->first();

        $balance = '0,00';

        if (Auth::check()) {

            $exa = new Exa();

            if (!empty(Auth::user()->extplayer)) {

                $player = $exa->playerBalance(Auth::user()->extplayer);

                $hiddenBalance = is_array($player)
                    ? ($player['balance'] ?? 0)
                    : 0;

                if ($hiddenBalance > 0) {

                    $formattedBalance = number_format($hiddenBalance, 2, ',', '.');

                    if ($hiddenBalance < 1000) {
                        $balance = '0.' . substr_replace($formattedBalance, '', -4);
                    } else {
                        $balance = substr_replace($formattedBalance, '', -4);
                    }
                }
            }

            return view('gamelist', compact(
                'gamelist',
                'setting',
                'title',
                'balance'
            ));
        }

        return view('gamelist', compact(
            'gamelist',
            'setting',
            'title'
        ));
    }


    /**
     * SYNC SPORTS
     */
    public function syncSports()
    {
        $exa = new Exa();

        $providers = $exa->getProviders();

        $total = 0;

        foreach ($providers as $provider) {

            if (strtolower($provider['category']) != 'sports') {
                continue;
            }

            $games = $exa->fetchGamesByProvider($provider['provider_code']);

            if (empty($games)) {
                continue;
            }

            foreach ($games as $game) {

                Sport::updateOrCreate(
                    [
                        'game_uid' => $game['game_uid']
                    ],
                    [
                        'provider_code' => $provider['provider_code'],
                        'provider_name' => trim($provider['provider_name']),
                        'game_name'     => trim($game['game_name']),
                        'game_type'     => $game['game_type'],
                        'image_url'     => $game['image_url'],
                        'rtp'           => $game['rtp'],
                        'volatility'    => $game['volatility'],
                        'min_bet'       => $game['min_bet'],
                        'max_bet'       => $game['max_bet'],
                        'status'        => $game['status'] == 'active' ? 1 : 0,
                    ]
                );

                $total++;
            }
        }

        return "Sync selesai. Total Game Sports : {$total}";
    }
}