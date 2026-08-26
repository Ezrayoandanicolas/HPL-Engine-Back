<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // QRIS auto-confirm now relies on Saweria/Bayar webhooks only.
        // $schedule->command('qris:check-pending')->everyMinute();

        // Auto sync games from X-API setiap hari jam 4 pagi
        $schedule->command('xapi:sync-games')->dailyAt('04:00');

        // Auto reject deposit & withdraw pending > 10 menit
        $schedule->command('transaksi:auto-reject')->everyMinute();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
