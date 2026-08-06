<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\QrisController;
use App\Models\QrisAccount;
use App\Models\Transaksi;
use Illuminate\Console\Command;

class CheckPendingQrisDeposits extends Command
{
    protected $signature = 'qris:check-pending';
    protected $description = 'Check pending QRIS deposits and auto-approve if paid';

    public function handle(): int
    {
        $pending = Transaksi::where('type', 1)
            ->where('payment_method', 'qris')
            ->where('status_id', 1)
            ->whereNotNull('qris_trx_id')
            ->get();

        $this->info("Found {$pending->count()} pending QRIS deposits");

        $approved = 0;
        foreach ($pending as $transaksi) {
            $gateway = $transaksi->payment_gateway;

            $account = $transaksi->qris_account_id
                ? QrisAccount::find($transaksi->qris_account_id)
                : null;

            try {
                if ($gateway === 'saweria') {
                    $service = $account
                        ? \App\Services\SaweriaService::fromAccount($account->config ?: [])
                        : app(\App\Services\SaweriaService::class);
                    $status = $service->checkPaymentV2($transaksi->qris_trx_id);
                } elseif ($gateway === 'bayar') {
                    $service = $account
                        ? \App\Services\BayarService::fromAccount($account->config ?: [])
                        : app(\App\Services\BayarService::class);
                    $result = $service->checkPayment($transaksi->qris_trx_id);
                    $status = strtolower((string) ($result['status'] ?? 'pending'));
                    $status = in_array($status, ['paid', 'success'], true) ? 'paid' : 'pending';
                } else {
                    continue;
                }
            } catch (\Throwable $e) {
                $this->warn("  - Deposit #{$transaksi->id}: check failed ({$e->getMessage()})");
                continue;
            }

            if ($status === 'paid') {
                app(QrisController::class)->autoApprove($transaksi);
                $this->info("  - Deposit #{$transaksi->id}: Rp " . number_format($transaksi->amount) . " approved");
                $approved++;
            }
        }

        $this->info("Done. Approved: {$approved}");
        return 0;
    }
}
