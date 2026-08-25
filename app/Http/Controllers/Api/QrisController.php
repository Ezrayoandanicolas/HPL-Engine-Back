<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\ActivityLog;
use App\Models\PaymentSetting;
use App\Models\QrisAccount;
use App\Models\Setting;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\BayarService;
use App\Services\SaweriaService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QrisController extends BaseApiController
{
    protected function resolveUser(Request $request)
    {
        $userId = $request->input('user_id');
        if ($userId) {
            return User::find($userId);
        }
        return $this->getAuthenticatedUser();
    }

    public function isEnabled(): bool
    {
        return PaymentSetting::get('qris_enabled', '0') === '1';
    }

    /**
     * Pilih akun QRIS berikutnya (round-robin) di antara akun enabled semua gateway.
     */
    protected function nextAccount(): ?QrisAccount
    {
        $accounts = QrisAccount::where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($accounts->isEmpty()) return null;

        $pick = $accounts->sortBy(function ($account) {
            return $account->use_count;
        })->first();

        $pick->increment('use_count');
        $pick->update(['last_used_at' => now()]);

        return $pick;
    }

    public function create(Request $request)
    {
        $user = $this->resolveUser($request);
        if (!$user) return $this->error('Unauthenticated', 401);

        if (!$this->isEnabled()) {
            return $this->error('QRIS belum dikonfigurasi oleh admin.', 400);
        }

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $nominal = (int) round($request->amount * 1000);

        $settings = Setting::orderBy('created_at', 'DESC')->first();
        $minDeposit = (int) ($settings->min_deposit ?? 25000);

        if ($nominal < $minDeposit) {
            return $this->error('Minimal deposit QRIS Rp' . number_format($minDeposit, 0, ',', '.'), 422);
        }

        $pending = Transaksi::where('user_id', $user->id)
            ->where('type', 1)
            ->where('payment_method', 'qris')
            ->where('status_id', 1)
            ->first();

        if ($pending) {
            return $this->error('Tidak Bisa Melakukan Deposit QRIS. Menunggu Deposit Sebelumnya Diterima!', 422);
        }

        $account = $this->nextAccount();
        if (!$account) {
            return $this->error('Tidak ada akun QRIS aktif. Periksa konfigurasi.', 400);
        }

        $gateway = $account->gateway;

        try {
            if ($gateway === 'saweria') {
                $service = SaweriaService::fromAccount($account->config ?: []);
                $result = $service->createPayment($nominal, $user);
                if (!$result) {
                    return $this->error('Gagal membuat QRIS Saweria. Periksa konfigurasi akun.', 422);
                }
                $qr = [
                    'gateway' => 'saweria',
                    'account' => $account->name,
                    'trx_id' => $result['trx_id'],
                    'qr_string' => $result['qr_string'] ?? '',
                    'qr_image_url' => $result['qr_string']
                        ? 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($result['qr_string'])
                        : '',
                    'invoice_url' => $result['invoice_url'] ?? '',
                    'amount' => $result['amount'] ?? $nominal,
                ];
            } else {
                $config = $account->config ?: [];
                $service = BayarService::fromAccount($config);
                $trxNumber = rand(100000, 999999);
                $descriptions = [
                    'Pembayaran invoice',
                    'Pembayaran layanan',
                    'Pembayaran tagihan',
                    'Top up saldo',
                    'Transfer dana',
                    'Pembayaran pesanan',
                    'Pembayaran jasa',
                    'Transaksi pembelian',
                ];
                $desc = $descriptions[array_rand($descriptions)];
                $payload = [
                    'amount' => (int) $nominal,
                    'description' => $desc . ' #' . $trxNumber,
                    'customer_name' => $user->name ?? $user->username,
                    'customer_email' => $user->email ?? '',
                    'customer_phone' => '',
                    'callback_url' => $config['callback_url'] ?? '',
                    'redirect_url' => '',
                    'payment_method' => 'qris',
                ];

                $useConverter = ($config['use_qris_converter'] ?? '1') == '1';
                $qrisString = $config['qris_string'] ?? '';

                if ($useConverter) {
                    $payload['use_qris_converter'] = true;
                    if ($qrisString) {
                        $payload['qris_string'] = $qrisString;
                    }
                }

                $payment = $service->createPayment($payload);

                $qr = [
                    'gateway' => 'bayar',
                    'account' => $account->name,
                    'trx_id' => $payment['invoice_id'] ?? 'INV-' . time() . '-' . $user->id,
                    'qr_string' => $payment['qris_converter']['converted_qris']
                        ?? $payment['qris_dynamic_string'] ?? '',
                    'qr_image_url' => $payment['qris_converter']['qr_image_url']
                        ?? $payment['qris_dynamic_image_url'] ?? '',
                    'invoice_url' => $payment['payment_url'] ?? '',
                    'amount' => $payment['amount'] ?? $nominal,
                ];
            }
        } catch (\Throwable $e) {
            Log::error('QRIS create failed', ['user' => $user->id, 'account' => $account->id, 'error' => $e->getMessage()]);
            return $this->error('Gagal membuat pembayaran QRIS: ' . $e->getMessage(), 422);
        }

        $transaksi = Transaksi::create([
            'user_id' => $user->id,
            'amount' => $nominal,
            'type' => 1,
            'status_id' => 1,
            'description' => 'Deposit QRIS (' . $gateway . ')',
            'notes' => 'unread',
            'payment_method' => 'qris',
            'payment_gateway' => $gateway,
            'qris_account_id' => $account->id,
            'qris_trx_id' => $qr['trx_id'],
            'qris_payload' => json_encode($qr),
        ]);

        // Telegram notification
        try {
            $tgMsgId = app(\App\Services\TelegramNotifService::class)->sendDepositPending($transaksi, $user);
            if ($tgMsgId) {
                $transaksi->update(['tg_message_id' => $tgMsgId]);
            }
        } catch (\Exception $e) {}

        return $this->success([
            'trx_id' => $qr['trx_id'],
            'qr_string' => $qr['qr_string'],
            'qr_image_url' => $qr['qr_image_url'],
            'invoice_url' => $qr['invoice_url'],
            'amount' => $qr['amount'],
            'deposit_id' => $transaksi->id,
        ], 'QRIS berhasil dibuat');
    }

    public function check(Request $request)
    {
        $user = $this->resolveUser($request);
        if (!$user) return $this->error('Unauthenticated', 401);

        $request->validate(['trx_id' => 'required|string']);

        $transaksi = Transaksi::where('user_id', $user->id)
            ->where('type', 1)
            ->where('payment_method', 'qris')
            ->where('qris_trx_id', $request->trx_id)
            ->first();

        if (!$transaksi) return $this->error('Transaksi tidak ditemukan', 404);

        if ((int) $transaksi->status_id === 2) {
            return $this->success(['payment_status' => 'paid']);
        }

        if ((int) $transaksi->status_id === 3) {
            return $this->success(['payment_status' => 'failed']);
        }

        $account = $transaksi->qris_account_id
            ? QrisAccount::find($transaksi->qris_account_id)
            : null;

        $gateway = $account->gateway ?? $transaksi->payment_gateway;
        if (!$gateway) return $this->success(['payment_status' => 'pending']);

        try {
            if ($gateway === 'saweria') {
                $service = $account
                    ? SaweriaService::fromAccount($account->config ?: [])
                    : app(SaweriaService::class);
                $status = $service->checkPaymentV2($request->trx_id);
            } else {
                $service = $account
                    ? BayarService::fromAccount($account->config ?: [])
                    : app(BayarService::class);
                $result = $service->checkPayment($request->trx_id);
                $status = strtolower((string) ($result['status'] ?? 'pending'));
                $status = $status === 'success' ? 'paid' : ($status === 'paid' ? 'paid' : 'pending');
            }
        } catch (\Throwable $e) {
            Log::warning('QRIS check failed', ['trx' => $request->trx_id, 'error' => $e->getMessage()]);
            return $this->success(['payment_status' => 'pending']);
        }

        if ($status === 'paid') {
            $this->autoApprove($transaksi);
            return $this->success(['payment_status' => 'paid']);
        }

        return $this->success(['payment_status' => 'pending']);
    }

    public function autoApprove(Transaksi $transaksi): void
    {
        if ((int) $transaksi->status_id !== 1) return;

        $user = User::find($transaksi->user_id);
        if (!$user) return;

        $amount = $transaksi->amount;

        $transaksi->update(['status_id' => 2, 'notes' => 'unread']);
        app(\App\Services\WalletService::class)->creditBalance($user, $amount);

        if ($amount >= 50000) {
            $user->increment('point_player', 2500);
        }

        try {
            ActivityLog::create([
                'admin_id' => null,
                'admin_name' => 'system',
                'action' => 'deposit_approve',
                'description' => "Auto-approve deposit QRIS Rp{$amount} untuk {$user->username}",
                'target_type' => 'deposit',
                'target_id' => $transaksi->id,
                'ip' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ActivityLog auto-approve failed', ['error' => $e->getMessage()]);
        }

        // Telegram: update deposit success
        try {
            if ($transaksi->tg_message_id) {
                app(\App\Services\TelegramNotifService::class)->updateDepositSuccess($transaksi->tg_message_id, $transaksi, $user);
            }
        } catch (\Exception $e) {}
    }

    public function webhookSaweria(Request $request, SaweriaService $service)
    {
        $payload = $request->all();
        Log::info('Saweria webhook received', $payload);

        $trxId = $payload['id'] ?? null;
        if (!$trxId) {
            return response()->json(['error' => 'No transaction ID'], 400);
        }

        $transaksi = Transaksi::where('qris_trx_id', $trxId)->first();
        if (!$transaksi) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        if ((int) $transaksi->status_id !== 1) {
            return response()->json(['status' => 'processed']);
        }

        $account = $transaksi->qris_account_id
            ? QrisAccount::find($transaksi->qris_account_id)
            : null;
        $svc = $account
            ? SaweriaService::fromAccount($account->config ?: [])
            : $service;

        $status = $svc->checkPaymentV2($trxId);

        if ($status !== 'paid') {
            return response()->json(['status' => 'pending', 'checked' => $status]);
        }

        $this->autoApprove($transaksi);

        Log::info("Deposit QRIS #{$transaksi->id} auto-approved via Saweria webhook");
        return response()->json(['status' => 'processed']);
    }

    public function webhookBayar(Request $request, BayarService $service)
    {
        $payload = $request->all();
        $signature = $request->header('X-Webhook-Signature');
        $timestamp = $request->header('X-Webhook-Timestamp');

        Log::info('Bayar webhook received', $payload);

        $invoiceId = $payload['invoice_id'] ?? null;
        if (!$invoiceId) {
            return response()->json(['error' => 'No invoice ID'], 400);
        }

        $transaksi = Transaksi::where('qris_trx_id', $invoiceId)->first();
        if (!$transaksi) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        $account = $transaksi->qris_account_id
            ? QrisAccount::find($transaksi->qris_account_id)
            : null;

        if ($account) {
            $svc = BayarService::fromAccount($account->config ?: []);
            if (!$svc->validateWebhookSignature($payload, $signature, $timestamp)) {
                Log::warning('Bayar invalid signature', $payload);
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        } else {
            if (!$service->validateWebhookSignature($payload, $signature, $timestamp)) {
                Log::warning('Bayar invalid signature', $payload);
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $status = strtolower((string) ($payload['status'] ?? 'pending'));

        if ($status === 'paid' || $status === 'success') {
            $this->autoApprove($transaksi);
            Log::info("Deposit QRIS #{$transaksi->id} auto-approved via Bayar webhook");
        }

        return response()->json(['success' => true]);
    }

    public function adminSettings(Request $request)
    {
        $accounts = QrisAccount::orderBy('sort_order')->orderBy('id')->get()->map(function ($acc) {
            return [
                'id' => $acc->id,
                'gateway' => $acc->gateway,
                'name' => $acc->name,
                'enabled' => (int) $acc->enabled,
                'sort_order' => $acc->sort_order,
                'use_count' => $acc->use_count,
                'last_used_at' => $acc->last_used_at ? $acc->last_used_at->toDateTimeString() : null,
                'config' => $acc->config ?: [],
            ];
        })->values();

        return $this->success([
            'qris_enabled' => PaymentSetting::get('qris_enabled', '0'),
            'accounts' => $accounts,
        ]);
    }

    public function adminSettingsSave(Request $request)
    {
        $data = $request->validate([
            'qris_enabled' => 'required|in:0,1',
            'accounts' => 'nullable|array',
            'accounts.*.id' => 'nullable|integer',
            'accounts.*.gateway' => 'required|in:saweria,bayar',
            'accounts.*.name' => 'nullable|string',
            'accounts.*.enabled' => 'nullable|in:0,1',
            'accounts.*.sort_order' => 'nullable|integer',
            'accounts.*.config' => 'nullable|array',
        ]);

        PaymentSetting::set('qris_enabled', $data['qris_enabled']);

        $submitted = $data['accounts'] ?? [];
        $submittedIds = [];

        foreach ($submitted as $i => $acc) {
            $attributes = [
                'gateway' => $acc['gateway'],
                'name' => $acc['name'] ?? (($acc['gateway'] === 'saweria' ? 'Saweria' : 'Bayar.gg') . ' #' . ($i + 1)),
                'enabled' => (($acc['enabled'] ?? '1') === '1'),
                'sort_order' => (int) ($acc['sort_order'] ?? $i),
                'config' => $acc['config'] ?? [],
            ];

            if (!empty($acc['id'])) {
                $account = QrisAccount::find($acc['id']);
                if ($account) {
                    $account->update($attributes);
                    $submittedIds[] = $account->id;
                    continue;
                }
            }

            $account = QrisAccount::create($attributes);
            $submittedIds[] = $account->id;
        }

        QrisAccount::whereNotIn('id', $submittedIds)->delete();

        return $this->success(null, 'Pengaturan QRIS berhasil disimpan');
    }
}
