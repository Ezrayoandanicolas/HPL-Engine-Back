<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class SyncXapiUsers extends Command
{
    protected $signature = 'xapi:sync-users';
    protected $description = 'Sync all X-API users into local DB';

    public function handle()
    {
        $xapi = new \App\Http\API\XApi();

        $this->info('Fetching user list from X-API...');
        $res = json_decode($xapi->allUsersBalance(), true);

        if (!isset($res['status']) || $res['status'] != 1) {
            $this->error('X-API error: ' . ($res['msg'] ?? 'unknown'));
            return 1;
        }

        $users = $res['user_list'] ?? [];
        $this->info('X-API users found: ' . count($users));

        $existing = User::pluck('username')->toArray();
        $created = 0;
        $skipped = 0;

        foreach ($users as $u) {
            $code = $u['user_code'];
            if (in_array($code, $existing)) {
                $skipped++;
                continue;
            }

            User::create([
                'username'     => $code,
                'name'         => $code,
                'email'        => strtolower($code) . '@xapi.local',
                'password'     => Hash::make('password123'),
                'role'         => 'member',
                'phone'        => '0000000000',
                'whatsapp'     => '0000000000',
                'bank'         => 'BCA',
                'accNumber'    => '0000000000',
                'accName'      => $code,
                'country'      => 'ID',
                'informasi'    => 'Auto-synced from X-API',
                'aas_user_code' => $code,
                'saldo'        => (float) ($u['balance'] ?? 0),
            ]);

            $existing[] = $code;
            $created++;
            $this->line("  Created: {$code} (balance: {$u['balance']})");
        }

        $this->info("Done: {$created} created, {$skipped} skipped (already exist)");
        return 0;
    }
}
