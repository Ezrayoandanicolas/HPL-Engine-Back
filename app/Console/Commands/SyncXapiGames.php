<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncXapiGames extends Command
{
    protected $signature = 'xapi:sync-games';
    protected $description = 'Sync all games from X-API, update existing, add new, deactivate missing';

    public function handle()
    {
        $xapi = new \App\Http\API\XApi();

        $this->info('Fetching provider list from X-API...');
        $res = json_decode($xapi->providerlist(), true);

        if (!isset($res['status']) || $res['status'] != 1) {
            $this->error('X-API error: ' . ($res['msg'] ?? 'unknown'));
            return 1;
        }

        $providers = $res['providers'] ?? [];
        $totalSynced = 0;
        $totalCreated = 0;
        $totalSkipped = 0;
            $xapiGameCodes = [];
            $xapiPairs = [];

            foreach ($providers as $p) {
            $code = $p['code'];
            if (($p['status'] ?? 0) != 1) continue;

            $this->info("Syncing {$code}...");
            $gameRes = json_decode($xapi->gamelist($code), true);
            if (!isset($gameRes['status']) || $gameRes['status'] != 1) continue;

            $games = $gameRes['games'] ?? [];

            foreach ($games as $g) {
                $gameCode = $g['game_code'];
                $gameName = $g['game_name'];
                $xapiGameCodes[] = $gameCode;
                $xapiPairs[] = $code . '|' . $gameCode;

                $existing = \App\Models\Game::where('game_code', $gameCode)
                    ->where('game_provider', $code)
                    ->orWhere(function ($q) use ($gameName, $code) {
                        $q->where('game_name', $gameName)->where('game_provider', $code);
                    })->first();

                if ($existing) {
                    $existing->update([
                        'game_code'     => $gameCode,
                        'game_name'     => $g['game_name'],
                        'game_provider' => $code,
                        'provider'      => $p['name'],
                        'image'         => $g['banner'] ?? $g['image_url'] ?? $g['image'] ?? $existing->image,
                        'game_category' => strtolower($g['game_type'] ?? 'slot'),
                        'status'        => ($g['status'] ?? 1) == 1 ? 1 : 0,
                    ]);
                    $totalSynced++;
                } else {
                    \App\Models\Game::create([
                        'game_code'     => $gameCode,
                        'game_name'     => $g['game_name'],
                        'game_provider' => $code,
                        'provider'      => $p['name'],
                        'image'         => $g['banner'] ?? $g['image_url'] ?? $g['image'] ?? '',
                        'game_category' => strtolower($g['game_type'] ?? 'slot'),
                        'status'        => ($g['status'] ?? 1) == 1 ? 1 : 0,
                    ]);
                    $totalCreated++;
                }
            }
        }

        $this->info("X-API games synced: {$totalSynced} updated, {$totalCreated} created");

        // Deactivate games not in X-API
        $this->info('Deactivating games not found in X-API...');
        $deactivated = 0;
        foreach (\App\Models\Game::where('status', 1)->get(['id', 'game_code', 'game_provider']) as $dbGame) {
            $pair = $dbGame->game_provider . '|' . $dbGame->game_code;
            if (!in_array($pair, $xapiPairs)) {
                $dbGame->update(['status' => 0]);
                $deactivated++;
            }
        }

        $this->info("Deactivated {$deactivated} games not found in X-API");

        $total = \App\Models\Game::count();
        $active = \App\Models\Game::where('status', 1)->count();
        $this->info("Database total: {$total} games ({$active} active, " . ($total - $active) . " inactive)");

        return 0;
    }
}
