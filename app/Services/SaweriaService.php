<?php

namespace App\Services;

use App\Models\PaymentSetting;
use Illuminate\Support\Facades\Http;

class SaweriaService
{
    private string $backendUri = 'https://backend.saweria.co';
    private string $frontendUri = 'https://saweria.co';
    private ?string $jwt = null;
    private ?string $userId = null;
    private string $username;
    private string $email;
    private string $password;

    public function __construct(?string $username = null, string $email = '', string $password = '', ?string $jwt = null)
    {
        $this->username = $username ?: (string) PaymentSetting::get('saweria_username', '');
        $this->email = $email ?: (string) PaymentSetting::get('saweria_email', '');
        $this->password = $password ?: (string) PaymentSetting::get('saweria_password', '');
        $this->jwt = $jwt ?: (PaymentSetting::get('saweria_jwt') ?: null);
    }

    public static function fromAccount(array $config): self
    {
        return new self(
            $config['username'] ?? null,
            $config['email'] ?? '',
            $config['password'] ?? '',
            $config['jwt'] ?? null,
        );
    }

    public function isConfigured(): bool
    {
        return !empty($this->username);
    }

    public function login(): bool
    {
        if ($this->jwt) return true;

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Origin' => 'https://saweria.co',
            'Referer' => 'https://saweria.co/login',
        ])->post("{$this->backendUri}/auth/login", [
            'email' => $this->email,
            'password' => $this->password,
        ]);

        if ($response->status() === 403 && str_contains($response->body(), 'CAPTCHA')) {
            return false;
        }

        if (!$response->successful()) return false;

        $this->jwt = $response->header('Authorization');
        return (bool) $this->jwt;
    }

    public function setJwt(string $jwt): void
    {
        $this->jwt = $jwt;
    }

    public function getUserId(): ?string
    {
        $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'])
            ->get("{$this->frontendUri}/{$this->username}");

        if (!$response->successful()) return null;

        $html = $response->body();
        preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/', $html, $matches);

        if (!isset($matches[1])) return null;

        $data = json_decode($matches[1], true);
        $this->userId = $data['props']['pageProps']['data']['id'] ?? null;

        return $this->userId;
    }

    public function createPayment(int $amount, int $expiredMinutes = 30): ?array
    {
        if (!$this->userId) {
            if (!$this->getUserId()) return null;
        }

        $response = Http::post("{$this->backendUri}/donations/{$this->userId}", [
            'agree' => true,
            'notUnderage' => true,
            'message' => 'Deposit QRIS NexusEngine',
            'amount' => $amount,
            'payment_type' => 'qris',
            'vote' => '',
            'currency' => 'IDR',
            'customer_info' => [
                'first_name' => '',
                'email' => 'customer@email.com',
                'phone' => '',
            ],
        ]);

        if (!$response->successful()) return null;

        $data = $response->json()['data'] ?? null;
        if (!$data) return null;

        return [
            'trx_id' => $data['id'],
            'qr_string' => $data['qr_string'],
            'amount' => $data['amount_raw'] ?? $amount,
            'invoice_url' => "https://saweria.co/qris/{$data['id']}",
            'created_at' => $data['created_at'],
        ];
    }

    public function checkPaymentV2(string $trxId): string
    {
        $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'])
            ->get("{$this->frontendUri}/qris/{$trxId}");

        if (!$response->successful()) return 'unknown';

        $html = $response->body();

        if (str_contains($html, '"status":"paid"') ||
            str_contains($html, '"status":"PAID"') ||
            str_contains($html, '"status":"completed"') ||
            str_contains($html, 'LUNAS') ||
            str_contains($html, 'SUDAH DIBAYAR')) {
            return 'paid';
        }

        if (preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $html, $m)) {
            $data = json_decode($m[1], true);
            $pageData = $data['props']['pageProps']['data'] ?? null;

            if ($pageData && isset($pageData['qr_string']) && empty($pageData['qr_string'])) {
                return 'paid';
            }
        }

        return 'unknown';
    }

    public function checkPaymentV1(string $trxId): string
    {
        if (!$this->jwt) {
            if (!$this->login()) return 'unknown';
        }

        $response = Http::withHeaders(['Authorization' => $this->jwt])
            ->get("{$this->backendUri}/transactions", [
                'page' => 1,
                'page_size' => 15,
            ]);

        if (!$response->successful()) return 'unknown';

        $transactions = $response->json()['data']['transactions'] ?? [];
        $found = collect($transactions)->firstWhere('id', $trxId);

        return $found ? 'paid' : 'pending';
    }

    public function setWebhook(string $url): array
    {
        if (!$this->login()) return ['success' => false, 'message' => 'Login failed'];

        $response = Http::withHeaders(['Authorization' => $this->jwt])
            ->post("{$this->backendUri}/callbacks/webhook", [
                'active' => true,
                'endpoint' => $url,
            ]);

        if (!$response->successful()) {
            return ['success' => false, 'message' => 'Failed to set webhook: ' . $response->body()];
        }

        $data = $response->json();
        return ['success' => true, 'message' => 'Webhook set: ' . ($data['data']['type'] ?? 'unknown')];
    }

    public function getBalance(): ?float
    {
        if (!$this->jwt) {
            if (!$this->login()) return null;
        }

        $response = Http::withHeaders(['Authorization' => $this->jwt])
            ->get("{$this->backendUri}/donations/balance");

        if (!$response->successful()) return null;

        return $response->json()['data']['balance'] ?? null;
    }
}
