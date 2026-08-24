<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Http\API\XApi;

class SyncAasUserCode extends Command
{
    protected $signature = 'user:sync-aas-code';
    protected $description = 'Sync aas_user_code for all users missing it';

    public function handle()
    {
        $users = User::whereNull('aas_user_code')
            ->where('username', '!=', 'admin')
            ->where('username', '!=', '')
            ->get();

        $this->info("Found {$users->count()} users without aas_user_code");

        $xapi = new XApi();
        $success = 0;
        $failed = 0;

        foreach ($users as $user) {
            $this->line("Processing: {$user->username}...");

            try {
                $raw = $xapi->create($user->username);
                $result = json_decode($raw, true);
                $aasCode = $result['aas_user_code'] ?? null;

                if ($aasCode) {
                    $user->aas_user_code = $aasCode;
                    $user->save();
                    $this->info("  ✓ Saved aas_user_code: {$aasCode}");
                    $success++;
                } else {
                    $this->warn("  ✗ No aas_user_code returned");
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->error("  ✗ Error: {$e->getMessage()}");
                $failed++;
            }

            // Delay antar request supaya tidak rate limit
            usleep(500000); // 0.5 detik
        }

        $this->newLine();
        $this->info("Done! Success: {$success}, Failed: {$failed}");
    }
}
