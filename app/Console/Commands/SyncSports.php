<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\API\Exa;
use App\Models\Sport;

class SyncSports extends Command
{
    protected $signature = 'sync:sports';
    protected $description = 'Sync Sports dari EXA';

    public function handle()
    {
        $this->info('Mengambil data sports...');

        try {

            $exa = new Exa();

            $sports = $exa->getSports();

            foreach ($sports as $game) {

                Sport::updateOrCreate(
                    [
                        'game_uid' => $game['game_uid']
                    ],
                    [
                        'provider_code' => $game['provider_code'],
                        'provider_name' => trim($game['provider_name']),
                        'game_name'     => trim($game['game_name']),
                        'game_type'     => $game['game_type'],
                        'image_url'     => $game['image_url'],
                        'rtp'           => $game['rtp'],
                        'volatility'    => $game['volatility'],
                        'min_bet'       => $game['min_bet'],
                        'max_bet'       => $game['max_bet'],
                        'status' => $game['status'] === 'active' ? 1 : 0,
                    ]
                );
            }

            $this->info(count($sports) . ' sports berhasil disimpan.');

            return Command::SUCCESS;

        } catch (\Throwable $e) {

            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}