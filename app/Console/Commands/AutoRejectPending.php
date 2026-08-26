<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\TelegramNotifService;
use App\Services\WalletService;
use Illuminate\Console\Command;

class AutoRejectPending extends Command
{
    protected $signature = 'transaksi:auto-reject';
    protected $description = 'Auto reject deposits & withdrawals pending > 10 minutes';

    public function handle()
    {
        $setting = Setting::first();
        if (!$setting || !$setting->auto_reject) {
            return 0;
        }

        $tenMinutesAgo = now()->subMinutes(10);

        // Auto reject deposits
        $pendingDeposits = Transaksi::where('type', 1)
            ->where('status_id', 1)
            ->where('created_at', '<', $tenMinutesAgo)
            ->get();

        foreach ($pendingDeposits as $trx) {
            $user = User::find($trx->user_id);
            $trx->update(['status_id' => 3, 'notes' => 'unread']);

            \App\Models\ActivityLog::create([
                'admin_id' => null,
                'admin_name' => 'system',
                'action' => 'deposit_reject',
                'description' => "Auto-reject deposit (timeout 10 menit) Rp{$trx->amount} untuk {$trx->user_id}",
                'target_type' => 'deposit',
                'target_id' => $trx->id,
                'ip' => '127.0.0.1',
            ]);

            // Telegram update
            try {
                if ($trx->tg_message_id && $user) {
                    app(TelegramNotifService::class)->updateDepositRejected($trx->tg_message_id, $trx, $user);
                }
            } catch (\Exception $e) {}

            $this->info("Auto-rejected deposit #{$trx->id} (Rp{$trx->amount})");
        }

        // Auto reject withdrawals
        $pendingWithdraws = Transaksi::where('type', 2)
            ->where('status_id', 1)
            ->where('created_at', '<', $tenMinutesAgo)
            ->get();

        foreach ($pendingWithdraws as $trx) {
            $user = User::find($trx->user_id);
            $trx->update(['status_id' => 3, 'notes' => 'unread']);

            // Refund balance
            if ($user) {
                $user->increment('saldo', $trx->amount);
            }

            \App\Models\ActivityLog::create([
                'admin_id' => null,
                'admin_name' => 'system',
                'action' => 'withdraw_reject',
                'description' => "Auto-reject withdraw (timeout 10 menit) Rp{$trx->amount} untuk {$trx->user_id}",
                'target_type' => 'withdraw',
                'target_id' => $trx->id,
                'ip' => '127.0.0.1',
            ]);

            // Telegram update
            try {
                if ($trx->tg_message_id && $user) {
                    app(TelegramNotifService::class)->updateWithdrawResolved($trx->tg_message_id, 'reject', $trx, $user);
                }
            } catch (\Exception $e) {}

            $this->info("Auto-rejected withdraw #{$trx->id} (Rp{$trx->amount})");
        }

        $total = $pendingDeposits->count() + $pendingWithdraws->count();
        if ($total > 0) {
            $this->info("Total auto-rejected: {$total} transaksi");
        }

        return 0;
    }
}
