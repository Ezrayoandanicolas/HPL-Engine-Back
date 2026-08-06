<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\API\Exa;
use App\Models\Casino;

class SyncCasino extends Command
{
    protected $signature = 'sync:casino';
    protected $description = 'Sync Casino dari EXA';

    public function handle()
    {
        $this->info('Mengambil data casino...');

        try {

            $exa = new Exa();

            $games = $exa->getCasino();

            foreach ($games as $game) {

                Casino::updateOrCreate(
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
                        'status'        => $game['status'] === 'active' ? 1 : 0,
                    ]
                );
            }

            $this->info(count($games) . ' game casino berhasil disimpan.');

            return Command::SUCCESS;

        } catch (\Throwable $e) {

            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}