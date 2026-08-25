<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\TelegramNotifService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $update = $request->all();

        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
        }

        return response()->json(['ok' => true]);
    }

    protected function handleCallbackQuery(array $callbackQuery)
    {
        $data = $callbackQuery['data'] ?? '';
        $chatId = $callbackQuery['message']['chat']['id'] ?? null;
        $messageId = $callbackQuery['message']['message_id'] ?? null;
        $callbackId = $callbackQuery['id'] ?? null;

        $notif = app(TelegramNotifService::class);

        if (preg_match('/^withdraw_(acc|reject)_(\d+)$/', $data, $m)) {
            $action = $m[1];
            $transaksiId = $m[2];

            $transaksi = Transaksi::find($transaksiId);
            if (!$transaksi || $transaksi->status_id != 1 || $transaksi->type != 2) {
                $notif->answerCallback($callbackId, '⚠️ Withdraw sudah diproses');
                return;
            }

            $user = User::find($transaksi->user_id);

            if ($action === 'acc') {
                $transaksi->update(['status_id' => 2, 'notes' => 'unread']);
                \App\Models\ActivityLog::create([
                    'user_id' => $transaksi->user_id,
                    'activity' => 'withdraw_approve',
                    'description' => "Withdraw #{$transaksi->id} disetujui",
                ]);
                $notif->answerCallback($callbackId, '✅ Withdraw diterima');
                $notif->updateWithdrawResolved($messageId, 'acc', $transaksi, $user);
            } else {
                $transaksi->update(['status_id' => 3, 'notes' => 'unread']);
                if ($user) {
                    $user->increment('saldo', $transaksi->amount);
                }
                \App\Models\ActivityLog::create([
                    'user_id' => $transaksi->user_id,
                    'activity' => 'withdraw_reject',
                    'description' => "Withdraw #{$transaksi->id} ditolak",
                ]);
                $notif->answerCallback($callbackId, '❌ Withdraw ditolak');
                $notif->updateWithdrawResolved($messageId, 'reject', $transaksi, $user);
            }

            Log::info("TELEGRAM WITHDRAW {$action}", [
                'transaksi_id' => $transaksiId,
                'admin' => $callbackQuery['from']['username'] ?? 'unknown',
            ]);
        }
    }
}
