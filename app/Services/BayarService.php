<?php

namespace App\Services;

use App\Models\PaymentSetting;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BayarService
{
    protected string $baseUrl;
    protected ?string $apiKey;
    protected ?string $secretKey;
    protected string $callbackUrl;
    protected bool $useQrisConverter;
    protected string $qrisString;

    public function __construct(?array $config = null)
    {
        $config = $config ?: [];
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? PaymentSetting::get('bayar_base_url') ?: 'https://bayar.gg/api'), '/');
        $this->apiKey = $config['api_key'] ?? PaymentSetting::get('bayar_api_key');
        $this->secretKey = $config['secret_key'] ?? PaymentSetting::get('bayar_secret_key');
        $this->callbackUrl = (string) ($config['callback_url'] ?? PaymentSetting::get('bayar_callback_url') ?: '');
        $this->useQrisConverter = ($config['use_qris_converter'] ?? PaymentSetting::get('bayar_use_qris_converter', '1')) == '1';
        $this->qrisString = (string) ($config['qris_string'] ?? PaymentSetting::get('bayar_qris_string', ''));
    }

    public static function fromAccount(array $config): self
    {
        return new self($config);
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function createPayment(array $payload): array
    {
        $response = Http::acceptJson()
            ->withHeaders($this->headers())
            ->post($this->baseUrl . '/create-payment.php', $payload);

        $data = $response->json();

        if (!$response->successful() || !($data['success'] ?? false)) {
            Log::error('Bayar create payment failed', [
                'status' => $response->status(),
                'response' => $data,
            ]);
            throw new Exception($data['message'] ?? 'Gagal membuat pembayaran di Bayar.gg.');
        }

        return $data['data'] ?? [];
    }

    public function checkPayment(string $invoiceId): array
    {
        $response = Http::acceptJson()
            ->withHeaders($this->headers())
            ->get($this->baseUrl . '/check-payment.php', [
                'invoice' => $invoiceId,
            ]);

        $data = $response->json();

        if (!$response->successful()) {
            Log::warning('Bayar check payment failed', [
                'invoice_id' => $invoiceId,
                'status' => $response->status(),
                'response' => $data,
            ]);
            throw new Exception($data['message'] ?? 'Gagal memeriksa status pembayaran.');
        }

        return is_array($data) ? $data : [];
    }

    public function validateWebhookSignature(array $payload, ?string $signature, ?string $timestamp): bool
    {
        if (!$this->secretKey || !$signature || !$timestamp) {
            return false;
        }

        $signatureData = sprintf(
            '%s|%s|%s|%s',
            $payload['invoice_id'] ?? '',
            $payload['status'] ?? '',
            $payload['final_amount'] ?? '',
            $timestamp
        );

        $expected = hash_hmac('sha256', $signatureData, $this->secretKey);

        return hash_equals($expected, $signature);
    }

    protected function headers(): array
    {
        return [
            'X-API-Key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }
}
