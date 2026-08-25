<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramNotifService
{
    protected $token;
    protected $chatId;
    protected $depositTopic;
    protected $withdrawTopic;

    public function __construct()
    {
        $this->token = env('TG_BOT_TOKEN');
        $this->chatId = env('TG_CHAT_ID');
        $this->depositTopic = (int) env('TG_TOPIC_DEPOSIT', 29);
        $this->withdrawTopic = (int) env('TG_TOPIC_WITHDRAW', 35);
    }

    public function sendDepositPending($transaksi, $user): ?int
    {
        $amount = number_format($transaksi->amount, 0, ',', '.');
        $now = now('Asia/Jakarta')->format('d M Y H:i:s');
        $method = strtoupper($transaksi->payment_method ?? 'BANK');
        $username = $user->username ?? '-';
        $name = $user->name ?? '-';

        $text = "💰 <b>DEPOSIT BARU</b>\n\n";
        $text .= "👤 <b>{$name}</b> (@{$username})\n";
        $text .= "💵 <b>Rp {$amount}</b>\n";
        $text .= "🏦 Metode: {$method}\n";
        $text .= "🕐 {$now} WIB\n";
        $text .= "📋 ID: #{$transaksi->id}";

        return $this->send($text, $this->depositTopic);
    }

    public function updateDepositSuccess(int $messageId, $transaksi, $user): void
    {
        $amount = number_format($transaksi->amount, 0, ',', '.');
        $now = now('Asia/Jakarta')->format('d M Y H:i:s');
        $username = $user->username ?? '-';
        $name = $user->name ?? '-';

        $text = "✅ <b>DEPOSIT BERHASIL</b>\n\n";
        $text .= "👤 <b>{$name}</b> (@{$username})\n";
        $text .= "💵 <b>Rp {$amount}</b>\n";
        $text .= "🕐 {$now} WIB\n";
        $text .= "📋 ID: #{$transaksi->id}";

        $this->editMessage($messageId, $text, $this->depositTopic);
    }

    public function updateDepositRejected(int $messageId, $transaksi, $user): void
    {
        $amount = number_format($transaksi->amount, 0, ',', '.');
        $now = now('Asia/Jakarta')->format('d M Y H:i:s');
        $username = $user->username ?? '-';
        $name = $user->name ?? '-';

        $text = "❌ <b>DEPOSIT DITOLAK</b>\n\n";
        $text .= "👤 <b>{$name}</b> (@{$username})\n";
        $text .= "💵 <b>Rp {$amount}</b>\n";
        $text .= "🕐 {$now} WIB\n";
        $text .= "📋 ID: #{$transaksi->id}";

        $this->editMessage($messageId, $text, $this->depositTopic);
    }

    public function sendWithdrawPending($transaksi, $user): ?int
    {
        $amount = number_format($transaksi->amount, 0, ',', '.');
        $now = now('Asia/Jakarta')->format('d M Y H:i:s');
        $bank = $transaksi->description ?? '-';
        $username = $user->username ?? '-';
        $name = $user->name ?? '-';

        $text = "💸 <b>WITHDRAW BARU</b>\n\n";
        $text .= "👤 <b>{$name}</b> (@{$username})\n";
        $text .= "💵 <b>Rp {$amount}</b>\n";
        $text .= "🏦 Bank: {$bank}\n";
        $text .= "🕐 {$now} WIB\n";
        $text .= "📋 ID: #{$transaksi->id}";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Accept', 'callback_data' => "withdraw_acc_{$transaksi->id}"],
                    ['text' => '❌ Reject', 'callback_data' => "withdraw_reject_{$transaksi->id}"],
                ],
            ],
        ];

        $this->send($text, $this->withdrawTopic, $keyboard);
    }

    public function updateWithdrawResolved(int $messageId, string $action, $transaksi, $user): void
    {
        $amount = number_format($transaksi->amount, 0, ',', '.');
        $now = now('Asia/Jakarta')->format('d M Y H:i:s');
        $bank = $transaksi->description ?? '-';
        $username = $user->username ?? '-';
        $name = $user->name ?? '-';
        $emoji = $action === 'acc' ? '✅' : '❌';
        $status = $action === 'acc' ? 'DITERIMA' : 'DITOLAK';

        $text = "{$emoji} <b>WITHDRAW {$status}</b>\n\n";
        $text .= "👤 <b>{$name}</b> (@{$username})\n";
        $text .= "💵 <b>Rp {$amount}</b>\n";
        $text .= "🏦 Bank: {$bank}\n";
        $text .= "🕐 {$now} WIB\n";
        $text .= "📋 ID: #{$transaksi->id}";

        $this->editMessage($messageId, $text, $this->withdrawTopic);
    }

    public function send(string $text, int $threadId, ?array $keyboard = null): ?int
    {
        $payload = [
            'chat_id' => $this->chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'message_thread_id' => $threadId,
        ];

        if ($keyboard) {
            $payload = array_merge($payload, $keyboard);
        }

        try {
            $response = Http::timeout(10)->post(
                "https://api.telegram.org/bot{$this->token}/sendMessage",
                $payload
            );

            $data = $response->json();
            return $data['result']['message_id'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function editMessage(int $messageId, string $text, int $threadId): void
    {
        try {
            Http::timeout(10)->post("https://api.telegram.org/bot{$this->token}/editMessageText", [
                'chat_id' => $this->chatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Exception $e) {
            // silent fail
        }
    }

    public function answerCallback(string $callbackQueryId, string $text = ''): void
    {
        try {
            Http::timeout(5)->post("https://api.telegram.org/bot{$this->token}/answerCallbackQuery", [
                'callback_query_id' => $callbackQueryId,
                'text' => $text,
            ]);
        } catch (\Exception $e) {
            // silent fail
        }
    }
}
