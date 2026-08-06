<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncAllSlotGames extends Command
{
    protected $signature = 'sync:all-slot';
    protected $description = 'Sinkronisasi game Slot dari API Fiver';

    public function handle()
    {
        $this->warn('API FIVER tidak dapat diakses (403). Data slot menggunakan GameSeeder.');
        $this->info('Jalankan: php artisan db:seed --class=GameSeeder');
    }
}
