<?php

namespace App\Http\API;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class Exa
{
    protected $baseUrl = 'https://api.httpsgamexaglobal.net/api';

    /*
    |--------------------------------------------------------------------------
    | TOKEN
    |--------------------------------------------------------------------------
    */

    public function getToken()
    {
        return Cache::remember('exa_token', now()->addMinutes(50), function () {

            $response = Http::asJson()->post($this->baseUrl . '/auth/login', [
                'agent_code' => env('EXA_AGENT_CODE'),
                'password'   => env('EXA_PASSWORD'),
            ]);

            if (!$response->successful()) {
                throw new \Exception($response->body());
            }

            return $response->json('token');
        });
    }

    /*
    |--------------------------------------------------------------------------
    | AGENT
    |--------------------------------------------------------------------------
    */

    public function getAgent()
    {
        $response = Http::withToken($this->getToken())
            ->get($this->baseUrl . '/auth/me');

        if (!$response->successful()) {
            throw new \Exception($response->body());
        }

        return $response->json('agent');
    }

    public function agentBalance()
    {
        $agent = $this->getAgent();

        return $agent['balance'] ?? 0;
    }

    /*
    |--------------------------------------------------------------------------
    | PLAYER
    |--------------------------------------------------------------------------
    */

    public function createMember($username, $email, $password, $fullName, $phone)
    {
        $response = Http::withToken($this->getToken())
            ->post($this->baseUrl . '/players', [
                'username'  => $username,
                'email'     => $email,
                'password'  => $password,
                'full_name' => $fullName,
                'phone'     => $phone,
            ]);

        if (!$response->successful()) {
            throw new \Exception($response->body());
        }

        return $response->json();
    }

    public function getPlayers()
    {
        $response = Http::withToken($this->getToken())
            ->get($this->baseUrl . '/players');

        if (!$response->successful()) {
            throw new \Exception($response->body());
        }

        return $response->json('players');
    }

    public function playerBalance($playerId)
    {
        $response = Http::withToken($this->getToken())
            ->get($this->baseUrl . "/players/{$playerId}/balance");

        if (!$response->successful()) {
            return 0;
        }

        return $response->json('balance');
    }

    /*
    |--------------------------------------------------------------------------
    | WALLET
    |--------------------------------------------------------------------------
    */

    public function deposit($playerId, $amount, $reference)
    {
        $response = Http::withToken($this->getToken())
            ->post($this->baseUrl . "/players/{$playerId}/deposit", [
                'amount'       => $amount,
                'reference_id' => $reference,
            ]);

        return $response->json();
    }

    public function withdraw($playerId, $amount, $reference)
    {
        $response = Http::withToken($this->getToken())
            ->post($this->baseUrl . "/players/{$playerId}/withdraw", [
                'amount'       => $amount,
                'reference_id' => $reference,
            ]);

        return $response->json();
    }

    /*
    |--------------------------------------------------------------------------
    | PROVIDER
    |--------------------------------------------------------------------------
    */

    public function getProviders()
    {
        $response = Http::withToken($this->getToken())
            ->get($this->baseUrl . '/games/providers');

        if (!$response->successful()) {
            throw new \Exception($response->body());
        }

        return $response->json('providers');
    }

    /*
    |--------------------------------------------------------------------------
    | SPORTS
    |--------------------------------------------------------------------------
    */

public function getSports()
{
    $providers = collect($this->getProviders())
        ->where('category', 'sports')
        ->values();

    $games = [];

    foreach ($providers as $provider) {

        $providerGames = $this->fetchGamesByProvider(
            $provider['provider_code']
        );

        if (!is_array($providerGames)) {
            continue;
        }

        foreach ($providerGames as $game) {

            // Ambil game sports saja
            if (($game['game_type'] ?? '') !== 'sports') {
                continue;
            }

            // Tambahkan info provider ke setiap game
            $game['provider_code'] = $provider['provider_code'];
            $game['provider_name'] = $provider['provider_name'];

            $games[] = $game;
        }
    }

    return $games;
}

    /*
    |--------------------------------------------------------------------------
    | GAME
    |--------------------------------------------------------------------------
    */

    public function fetchGamesByProvider($providerCode)
    {
        $response = Http::withToken($this->getToken())
            ->get($this->baseUrl . "/games/provider/{$providerCode}");

        if (!$response->successful()) {
            throw new \Exception($response->body());
        }

        return $response->json('games');
    }

    public function getGames($page = 1, $limit = 1000)
    {
        $response = Http::withToken($this->getToken())
            ->get($this->baseUrl . '/games', [
                'page'   => $page,
                'limit'  => $limit,
                'status' => 'active',
            ]);

        if (!$response->successful()) {
            throw new \Exception($response->body());
        }

        return $response->json('games');
    }

    /*
    |--------------------------------------------------------------------------
    | LAUNCH GAME
    |--------------------------------------------------------------------------
    */

public function launchGame($playerId, $gameUid, $lobbyUrl, $lang = 'en')
{
    $response = Http::withToken($this->getToken())
        ->post($this->baseUrl . '/games/launch', [
            'player_id' => $playerId,
            'game_uid'  => $gameUid,
            'lobby_url' => $lobbyUrl,
            'lang'      => $lang,
        ]);

    return [
        'status' => $response->status(),
        'body'   => $response->json(),
    ];
}

    /*
    |--------------------------------------------------------------------------
    | TRANSACTION
    |--------------------------------------------------------------------------
    */

    public function playerTransactions($playerId)
    {
        $response = Http::withToken($this->getToken())
            ->get($this->baseUrl . "/players/{$playerId}/transactions");

        return $response->json();
    }

    public function transactions()
    {
        $response = Http::withToken($this->getToken())
            ->get($this->baseUrl . '/transactions');

        return $response->json();
    }

    public function transactionStats()
    {
        $response = Http::withToken($this->getToken())
            ->get($this->baseUrl . '/transactions/stats');

        return $response->json();
    }

   public function getCasino()
{
    $providers = collect($this->getProviders())
        ->where('category', 'casino')
        ->values();

    $games = [];

    foreach ($providers as $provider) {

        $providerGames = $this->fetchGamesByProvider(
            $provider['provider_code']
        );

        if (!is_array($providerGames)) {
            continue;
        }

        foreach ($providerGames as $game) {

            if (($game['game_type'] ?? '') !== 'casino') {
                continue;
            }

            $game['provider_code'] = $provider['provider_code'];
            $game['provider_name'] = $provider['provider_name'];

            $games[] = $game;
        }
    }

    return $games;
}
    /*
    |--------------------------------------------------------------------------
    | CLEAR TOKEN
    |--------------------------------------------------------------------------
    */

    public function clearToken()
    {
        Cache::forget('exa_token');
    }

    public function getArcade()
{
    $providers = collect($this->getProviders())
        ->where('category', 'arcade')
        ->values();

    $games = [];

    foreach ($providers as $provider) {

        $providerGames = $this->fetchGamesByProvider(
            $provider['provider_code']
        );

        if (!is_array($providerGames)) {
            continue;
        }

        foreach ($providerGames as $game) {

            if (($game['game_type'] ?? '') !== 'arcade') {
                continue;
            }

            $game['provider_code'] = $provider['provider_code'];
            $game['provider_name'] = $provider['provider_name'];

            $games[] = $game;
        }
    }

    return $games;
}

}