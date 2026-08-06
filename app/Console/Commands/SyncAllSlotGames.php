<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Game;

class SyncAllSlotGames extends Command
{
    protected $signature = 'sync:all-slot';
    protected $description = 'Sinkronisasi game Slot dari API Fiver';

    public function handle()
    {
        $this->info('Mengambil data provider dari API Fiver...');

        try {
            $providerRaw = $this->svCall('provider_list');
            $providers = json_decode($providerRaw, true);

            if (!is_array($providers) || ($providers['status'] ?? 0) !== 1) {
                $this->error('Gagal mengambil provider. Response: ' . substr($providerRaw, 0, 300));
                $this->info('Jika API tidak dapat diakses (403), jalankan: php artisan db:seed --class=GameSeeder');
                return Command::FAILURE;
            }

            $slotProviders = collect($providers['providers'] ?? [])
                ->filter(fn($p) => ($p['type'] ?? '') === 'slot')
                ->pluck('code')
                ->values();

            if ($slotProviders->isEmpty()) {
                $this->warn('Tidak ada provider slot ditemukan.');
                return Command::SUCCESS;
            }

            $this->info('Ditemukan ' . $slotProviders->count() . ' provider slot: ' . $slotProviders->implode(', '));

            $total = 0;

            foreach ($slotProviders as $provider) {
                $gameRaw = $this->svCall('game_list', ['provider_code' => $provider]);
                $games = json_decode($gameRaw, true);

                if (!is_array($games) || ($games['status'] ?? 0) !== 1 || empty($games['games'])) {
                    continue;
                }

                $count = 0;
                foreach ($games['games'] as $game) {
                    Game::updateOrCreate(
                        ['game_code' => $game['game_code'], 'game_provider' => $provider],
                        [
                            'game_name'      => $game['game_name'] ?? '',
                            'game_provider'  => $provider,
                            'provider'       => $provider,
                            'game_category'  => 'slot',
                            'image'          => $game['banner'] ?? null,
                            'status'         => ($game['status'] ?? 1) == 1,
                        ]
                    );
                    $count++;
                }

                $total += $count;
                $this->line("  - {$provider}: {$count} game");
            }

            $this->info("Selesai. Total {$total} game slot berhasil disinkronkan.");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function svCall(string $method, array $extra = []): string
    {
        $param = array_merge([
            'method' => $method,
            'agent_code' => 'tokengames',
            'agent_token' => 'af9395d2c665e2812e76e8a123edbffa',
        ], $extra);

        return $this->svConnect('https://api.nexusggr.com', $param);
    }

    public function svConnect(string $url, array $postArray): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postArray));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $res = curl_exec($ch);
        curl_close($ch);

        return $res ?: '';
    }
}