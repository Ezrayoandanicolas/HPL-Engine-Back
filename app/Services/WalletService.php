<?php

namespace App\Services;

use App\Models\User;
use App\Models\ProviderTransaction;
use App\Http\API\fiver;
use App\Http\API\Exa;
use Illuminate\Support\Facades\Log;

class WalletService
{
    protected $fiver;
    protected $exa;

    public function __construct()
    {
        $this->fiver = new fiver();
        $this->exa = new Exa();
    }

    public function getMainBalance(User $user)
    {
        return (float) $user->saldo;
    }

    public function getSlotBalance(User $user)
    {
        return (float) $user->saldo_slot;
    }

    public function getGameBalance(User $user)
    {
        return (float) $user->saldo_game;
    }

    public function transferToSlot(User $user, float $amount)
    {
        if ($user->saldo < $amount) {
            throw new \Exception('Saldo utama tidak mencukupi.');
        }

        $user->saldo -= $amount;
        $user->saldo_slot += $amount;
         \App\Models\User::withoutEvents(function () use ($user) {
             $user->exists = true;
             $user->save();
        });

        $agentSign = $this->generateAgentSign($user->username, 'user_deposit');
        $raw = $this->fiver->deposit($user->username, $amount, $agentSign);
        $result = $this->parseFiverResponse($raw);

        $this->logTransaction($user->username, $amount, 'user_deposit', $agentSign, $result, $raw);

        if (!$result['success']) {
            // GGR gagal, rollback lokal
            $user->saldo += $amount;
            $user->saldo_slot -= $amount;
            $user->exists = true;
            $user->save();
            throw new \Exception($result['message']);
        }

        return true;
    }

    public function transferFromSlot(User $user, float $amount)
    {
        if ($user->saldo_slot < $amount) {
            throw new \Exception('Saldo Slot tidak mencukupi.');
        }

        $user->saldo_slot -= $amount;
        $user->saldo += $amount;
            $user->exists = true;
            $user->save();

        $agentSign = $this->generateAgentSign($user->username, 'user_withdraw');
        $raw = $this->fiver->withdraw($user->username, $amount, $agentSign);
        $result = $this->parseFiverResponse($raw);

        $this->logTransaction($user->username, $amount, 'user_withdraw', $agentSign, $result, $raw);

        if (!$result['success']) {
            $user->saldo_slot += $amount;
            $user->saldo -= $amount;
            $user->exists = true;
            $user->save();
            throw new \Exception($result['message']);
        }

        return true;
    }

    public function transferToGame(User $user, float $amount)
    {
        if ($user->saldo < $amount) {
            throw new \Exception('Saldo utama tidak mencukupi.');
        }

        $user->saldo -= $amount;
        $user->saldo_game += $amount;
            $user->exists = true;
            $user->save();

        $agentSign = $this->generateAgentSign($user->username, 'user_deposit');
        $raw = $this->fiver->deposit($user->username, $amount, $agentSign);
        $result = $this->parseFiverResponse($raw);

        $this->logTransaction($user->username, $amount, 'user_deposit', $agentSign, $result, $raw);

        if (!$result['success']) {
            $user->saldo += $amount;
            $user->saldo_game -= $amount;
            $user->exists = true;
            $user->save();
            throw new \Exception($result['message']);
        }

        return true;
    }

    public function transferFromGame(User $user, float $amount)
    {
        if ($user->saldo_game < $amount) {
            throw new \Exception('Saldo Game tidak mencukupi.');
        }

        $user->saldo_game -= $amount;
        $user->saldo += $amount;
            $user->exists = true;
            $user->save();

        $agentSign = $this->generateAgentSign($user->username, 'user_withdraw');
        $raw = $this->fiver->withdraw($user->username, $amount, $agentSign);
        $result = $this->parseFiverResponse($raw);

        $this->logTransaction($user->username, $amount, 'user_withdraw', $agentSign, $result, $raw);

        if (!$result['success']) {
            $user->saldo_game += $amount;
            $user->saldo -= $amount;
            $user->exists = true;
            $user->save();
            throw new \Exception($result['message']);
        }

        return true;
    }

    private function generateAgentSign(string $username, string $method): string
    {
        return strtoupper(substr(md5(uniqid($username . '_' . $method . '_', true)), 0, 16));
    }

    private function logTransaction(string $username, float $amount, string $type, string $agentSign, array $result, $rawResponse): void
    {
        try {
            ProviderTransaction::create([
                'agent_sign'   => $agentSign,
                'username'     => $username,
                'amount'       => $amount,
                'type'         => $type,
                'status'       => $result['success'] ? 'success' : 'failed',
                'message'      => $result['message'] ?? null,
                'response_raw' => is_string($rawResponse) ? $rawResponse : json_encode($rawResponse),
            ]);
        } catch (\Exception $e) {
            Log::error('Gagal menyimpan log transaksi provider', [
                'error' => $e->getMessage(),
                'agent_sign' => $agentSign,
            ]);
        }
    }

    private function parseFiverResponse($rawResponse): array
    {
        if (!$rawResponse) {
            return [
                'success' => false,
                'message' => 'Gagal terhubung ke provider (koneksi terputus).',
            ];
        }

        $decoded = json_decode($rawResponse, true);

        if (!$decoded || !isset($decoded['status'])) {
            return [
                'success' => false,
                'message' => 'Respon provider tidak valid.',
            ];
        }

        if ((int) $decoded['status'] !== 1) {
            return [
                'success' => false,
                'message' => $decoded['msg'] ?? 'Transfer provider gagal.',
            ];
        }

        return [
            'success' => true,
            'message' => 'SUCCESS',
            'agent_balance' => $decoded['agent_balance'] ?? 0,
            'user_balance' => $decoded['user_balance'] ?? 0,
        ];
    }
}
