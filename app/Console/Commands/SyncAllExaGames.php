<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\API\Exa;
use Illuminate\Support\Facades\DB;

class SyncAllExaGames extends Command
{
    protected $signature = 'sync:all-exa';
    protected $description = 'Sinkronisasi semua game Casino EXA';

    public function handle()
    {
        ini_set('memory_limit', '512M');

        $this->info('Memulai sinkronisasi game EXA...');

        $exa = new Exa();

        try {
            $providers = $exa->getProviders();
            $this->info('Provider ditemukan: ' . count($providers));

            DB::table('exa_providers')->truncate();
            foreach ($providers as $p) {
                DB::table('exa_providers')->insert([
                    'provider_code' => $p['provider_code'],
                    'provider_name' => $p['provider_name'],
                    'category'      => $p['category'],
                    'logo_url'      => $p['logo_url'] ?? null,
                    'status'        => $p['status'] ?? 'active',
                ]);
            }

            $categories = ['casino', 'sports'];
            $totalSync = 0;

            foreach ($categories as $category) {
                $catProviders = array_filter($providers, fn($p) => $p['category'] === $category);

                foreach ($catProviders as $provider) {
                    $code = $provider['provider_code'];

                    try {
                        $games = $exa->fetchGamesByProvider($code);
                    } catch (\Exception $e) {
                        $this->warn("  Skip provider {$code}: {$e->getMessage()}");
                        continue;
                    }

                    if (empty($games)) continue;

                    $bar = $this->output->createProgressBar(count($games));
                    $bar->start();

                    $data = [];
                    foreach ($games as $g) {
                        $data[] = [
                            'provider_code' => $code,
                            'game_uid'      => $g['game_uid'],
                            'game_name'     => $g['game_name'],
                            'game_type'     => $g['game_type'] ?? $category,
                            'logo_url'      => $g['logo_url'] ?? null,
                            'is_active'     => true,
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ];
                        $bar->advance();
                    }

                    DB::table('exagames')->upsert($data, ['game_uid'], [
                        'provider_code', 'game_name', 'game_type', 'logo_url', 'is_active', 'updated_at'
                    ]);

                    $bar->finish();
                    $this->newLine();

                    $totalSync += count($data);
                }
            }

            $this->syncToLegacyTables();

            $this->info("Sinkronisasi EXA selesai. Total: {$totalSync} game.");

        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    protected function syncToLegacyTables()
    {
        $this->info('Memperbarui tabel casino & sports...');

        DB::table('casino')->truncate();
        $casinoGames = DB::table('exagames')->where('game_type', 'casino')->get();
        foreach ($casinoGames as $g) {
            DB::table('casino')->insert([
                'game_uid'       => $g->game_uid,
                'provider_code'  => $g->provider_code,
                'provider_name'  => DB::table('exa_providers')->where('provider_code', $g->provider_code)->value('provider_name') ?? $g->provider_code,
                'game_name'      => $g->game_name,
                'game_type'      => 'casino',
                'status'         => 'active',
                'rtp'            => 96.00,
            ]);
        }

        DB::table('sports')->truncate();
        $sportsGames = DB::table('exagames')->where('game_type', 'sports')->get();
        foreach ($sportsGames as $g) {
            DB::table('sports')->insert([
                'game_uid'       => $g->game_uid,
                'provider_code'  => $g->provider_code,
                'provider_name'  => DB::table('exa_providers')->where('provider_code', $g->provider_code)->value('provider_name') ?? $g->provider_code,
                'game_name'      => $g->game_name,
                'game_type'      => 'sports',
                'status'         => 1,
                'rtp'            => 95.00,
            ]);
        }

        $this->info('Tabel legacy: ' . count($casinoGames) . ' casino, ' . count($sportsGames) . ' sports.');
    }
}
