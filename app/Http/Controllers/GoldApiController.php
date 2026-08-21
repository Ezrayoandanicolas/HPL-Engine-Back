<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\SeamlessTransaction;
use App\Http\API\fiver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GoldApiController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->json()->all();

        Log::info('GOLD_API INCOMING', ['payload' => $payload]);

        $method = $payload['method'] ?? null;
        $agentCode = $payload['agent_code'] ?? null;
        $agentSecret = $payload['agent_secret'] ?? null;
        $agentToken = $payload['agent_token'] ?? null;

        $fiver = new fiver();
        $dc = new \App\Http\API\DigitalCreative();
        $xapi = new \App\Http\API\XApi();

        $isFiver = $agentCode === $fiver->agen && $agentSecret === $fiver->secret;
        $isDc = $agentCode === $dc->agen && ($agentSecret === $dc->token || $agentToken === $dc->token);
        $isXapi = $agentCode === $xapi->agen && ($agentSecret === $xapi->token || $agentToken === $xapi->token);

        // DC Agent Seamless: tanpa agent_secret/token, auth via sign md5(agent_code + request_time + method + secret)
        if (!$isDc && $agentCode === $dc->agen && ($payload['request_time'] ?? null) && ($payload['sign'] ?? null)) {
            $expectedSign = md5($agentCode . $payload['request_time'] . ($payload['method'] ?? '') . $dc->secret);
            if (hash_equals($expectedSign, $payload['sign'])) {
                $isDc = true;
            }
        }

        // X-API Agent Seamless: auth via sign md5(agent_code + request_time + method + secret_key)
        if (!$isXapi && $agentCode === $xapi->agen && ($payload['request_time'] ?? null) && ($payload['sign'] ?? null)) {
            $expectedSign = md5($agentCode . $payload['request_time'] . ($payload['method'] ?? '') . $xapi->secret_key);
            if (hash_equals($expectedSign, $payload['sign'])) {
                $isXapi = true;
            }
        }

        if (!$isFiver && !$isDc && !$isXapi) {
            return response()->json(['status' => 0, 'msg' => 'AUTH_FAILED']);
        }

        $userCode = $payload['user_code'] ?? null;
        if (!$userCode) {
            return response()->json(['status' => 0, 'msg' => 'INVALID_USER']);
        }

        $user = User::where('username', $userCode)->first();
        if (!$user) {
            return response()->json(['status' => 0, 'msg' => 'USER_NOT_FOUND']);
        }

        // DC Agent Seamless (apiType=0): DGC memanggil method balance/withdraw/deposit/pushbet
        if ($isDc && in_array($method, ['balance', 'withdraw', 'deposit', 'pushbet'], true)) {
            return $this->processDcSeamless($user, $payload, $dc->secret);
        }

        // X-API Agent Seamless: memanggil method balance/withdraw/deposit/pushbet
        if ($isXapi && in_array($method, ['balance', 'withdraw', 'deposit', 'pushbet'], true)) {
            return $this->processDcSeamless($user, $payload, $xapi->secret_key);
        }

        switch ($method) {
            case 'user_balance':
            case 'money_info':
                // Hanya saldo utama yang "tersedia" untuk DC. saldo_game = dana yang sudah
                // di-hold/ditarik DC saat launch, jangan dilaporkan ulang agar DC tidak
                // mengkredit upstream berulang kali (double credit).
                $reportBalance = (float) $user->saldo;
                if ($method === 'money_info') {
                    return response()->json([
                        'status' => 1,
                        'msg'    => 'SUCCESS',
                        'agent'  => [
                            'agent_code' => $agentCode,
                            'balance'    => $reportBalance,
                        ],
                        'user' => [
                            'user_code' => $user->username,
                            'balance'   => $reportBalance,
                            'api_type'  => 0,
                        ],
                    ]);
                }

                return response()->json([
                    'status' => 1,
                    'user_balance' => $reportBalance,
                ]);

            case 'transaction':
                return $this->processTransaction($user, $payload);

            case 'user_withdraw':
            case 'user_deposit':
                return $this->processDcUserTransfer($user, $method, $payload);

            default:
                return response()->json(['status' => 0, 'msg' => 'INVALID_METHOD']);
        }
    }

    private function processDcSeamless(User $user, array $payload, string $secret)
    {
        $requestTime = $payload['request_time'] ?? null;
        $method = $payload['method'] ?? null;
        $sign = $payload['sign'] ?? null;
        $agentCode = $payload['agent_code'] ?? null;

        if (!$requestTime || !$sign) {
            return response()->json(['status' => 0, 'msg' => 'INVALID_SIGN']);
        }

        // sign = md5(agent_code + request_time + method + secret)
        $expectedSign = md5($agentCode . $requestTime . $method . $secret);
        if (!hash_equals($expectedSign, $sign)) {
            return response()->json(['status' => 0, 'msg' => 'INVALID_SIGN']);
        }

        $amount = (float) ($payload['amount'] ?? 0);
        $txnId = $payload['txn_id'] ?? null;

        return DB::transaction(function () use ($user, $method, $amount, $txnId, $payload) {
            if ($method === 'balance') {
                return response()->json([
                    'status'  => 1,
                    'balance' => (float) $user->saldo,
                    'msg'     => 'SUCCESS',
                ]);
            }

            // withdraw/deposit/pushbet butuh txn_id sebagai idempotent key
            if (!$txnId) {
                return response()->json(['status' => 0, 'msg' => 'INVALID_TXN_ID']);
            }

            $existing = SeamlessTransaction::where('txn_id', $txnId)
                ->where('txn_type', $method)
                ->first();

            if ($existing) {
                return response()->json([
                    'status'  => 1,
                    'balance' => (float) $existing->balance_after,
                    'msg'     => 'SUCCESS',
                ]);
            }

            $locked = User::where('id', $user->id)->lockForUpdate()->first();
            if (!$locked) {
                return response()->json(['status' => 0, 'msg' => 'USER_NOT_FOUND']);
            }

            $before = (float) $locked->saldo + (float) $locked->saldo_game;

            if ($method === 'withdraw') {
                if ($before < $amount) {
                    return response()->json(['status' => 0, 'msg' => 'INSUFFICIENT_USER_FUNDS']);
                }
                $after = round($before - $amount, 2);
            } elseif ($method === 'deposit') {
                $after = round($before + $amount, 2);
            } elseif ($method === 'pushbet') {
                // amount negatif = bet, positif = win (net)
                if ($amount < 0 && $before < abs($amount)) {
                    return response()->json(['status' => 0, 'msg' => 'INSUFFICIENT_USER_FUNDS']);
                }
                $after = round($before + $amount, 2);
            } else {
                return response()->json(['status' => 0, 'msg' => 'INVALID_METHOD']);
            }

            // simpan delta di saldo_game (dana hold saat launch) supaya saldo utama tetap aman
            $delta = $after - $before;
            $locked->saldo_game = round((float) $locked->saldo_game + $delta, 2);
            $locked->exists = true;
            $locked->save();

            SeamlessTransaction::create([
                'txn_id'         => $txnId,
                'round_id'       => $payload['round_id'] ?? null,
                'user_id'        => $user->id,
                'user_code'      => $user->username,
                'game_type'      => $payload['game_type'] ?? null,
                'provider_code'  => $payload['provider_code'] ?? null,
                'game_code'      => $payload['game_code'] ?? null,
                'txn_type'       => $method,
                'bet_money'      => $method === 'withdraw' ? $amount : ($method === 'pushbet' && $amount < 0 ? abs($amount) : 0),
                'win_money'      => $method === 'deposit' ? $amount : ($method === 'pushbet' && $amount > 0 ? $amount : 0),
                'balance_before' => $before,
                'balance_after'  => $after,
                'payload'        => json_encode($payload),
            ]);

            Log::info('GOLD_API DC SEAMLESS APPLIED', [
                'user'   => $user->username,
                'txn_id' => $txnId,
                'method' => $method,
                'amount' => $amount,
                'before' => $before,
                'after'  => $after,
            ]);

            return response()->json([
                'status'  => 1,
                'balance' => $after,
                'msg'     => 'SUCCESS',
            ]);
        });
    }

    private function processDcUserTransfer(User $user, string $method, array $payload)
    {
        $amount = (float) ($payload['amount'] ?? 0);
        $txnId = $payload['txn_id'] ?? null;

        if (!$txnId) {
            return response()->json(['status' => 0, 'msg' => 'INVALID_TXN_ID']);
        }

        return DB::transaction(function () use ($user, $method, $amount, $txnId, $payload) {
            $existing = SeamlessTransaction::where('txn_id', $txnId)
                ->where('txn_type', $method)
                ->first();

            if ($existing) {
                return response()->json([
                    'status'      => 1,
                    'msg'         => 'SUCCESS',
                    'balance'     => (float) $user->saldo,
                    'user_balance' => (float) $user->saldo,
                ]);
            }

            $locked = User::where('id', $user->id)->lockForUpdate()->first();
            if (!$locked) {
                return response()->json(['status' => 0, 'msg' => 'USER_NOT_FOUND']);
            }

            $saldo = (float) $locked->saldo;
            $saldoGame = (float) $locked->saldo_game;
            $before = $saldo + $saldoGame;

            if ($method === 'user_withdraw') {
                // DC seamless menahan dana saat launch: pindahkan saldo -> saldo_game (hold).
                // Idempoten terhadap total: retry dengan txn_id baru tidak boleh menolak
                // jika dana sudah ter-hold seluruhnya.
                if ($before < $amount) {
                    return response()->json(['status' => 0, 'msg' => 'INSUFFICIENT_USER_FUNDS']);
                }
                $needed = $amount - $saldoGame;
                if ($needed > 0) {
                    $fromSaldo = min($needed, $saldo);
                    $locked->saldo = round($saldo - $fromSaldo, 2);
                    $locked->saldo_game = round($saldoGame + $fromSaldo, 2);
                }
            } else {
                // user_deposit: DC mengembalikan dana dari hold, kurangi saldo_game lalu tambah saldo
                $release = min($amount, $saldoGame);
                $locked->saldo = round($saldo + $amount, 2);
                $locked->saldo_game = round($saldoGame - $release, 2);
            }

            $after = (float) $locked->saldo + (float) $locked->saldo_game;
            $locked->exists = true;
            $locked->save();

            // Response ke DC: saldo TERSEDIA (saldo utama), bukan total hold,
            // agar DC tidak menganggap dana yang sudah di-hold masih tersedia
            // (mencegah double credit upstream).
            $reportAfter = (float) $locked->saldo;

            SeamlessTransaction::create([
                'txn_id'         => $txnId,
                'round_id'       => $payload['round_id'] ?? null,
                'user_id'        => $user->id,
                'user_code'      => $user->username,
                'game_type'      => $payload['game_type'] ?? null,
                'provider_code'  => $payload['provider_code'] ?? null,
                'game_code'      => $payload['game_code'] ?? null,
                'txn_type'       => $method,
                'bet_money'      => $method === 'user_withdraw' ? $amount : 0,
                'win_money'      => $method === 'user_deposit' ? $amount : 0,
                'balance_before' => $before,
                'balance_after'  => $after,
                'payload'        => json_encode($payload),
            ]);

            Log::info('GOLD_API DC USER TRANSFER APPLIED', [
                'user'   => $user->username,
                'txn_id' => $txnId,
                'method' => $method,
                'amount' => $amount,
                'before' => $before,
                'after'  => $after,
            ]);

            return response()->json([
                'status'       => 1,
                'msg'          => 'SUCCESS',
                'balance'      => $reportAfter,
                'user_balance' => $reportAfter,
            ]);
        });
    }

    private function processTransaction(User $user, array $payload)
    {
        $gameType = $payload['game_type'] ?? null;
        $game = $payload[$gameType] ?? null;

        if (!is_array($game)) {
            return response()->json(['status' => 0, 'msg' => 'INVALID_GAME']);
        }

        $txnId = $game['txn_id'] ?? null;
        $txnType = $game['txn_type'] ?? 'debit_credit';
        $bet = (float) ($game['bet_money'] ?? 0);
        $win = (float) ($game['win_money'] ?? 0);

        if (!$txnId) {
            return response()->json(['status' => 0, 'msg' => 'INVALID_TXN_ID']);
        }

        return DB::transaction(function () use ($user, $payload, $game, $gameType, $txnId, $txnType, $bet, $win) {
            // Idempotent key = (txn_id, txn_type). GGR dapat mengirim debit & credit
            // pada round yang sama dengan txn_id yang SAMA, sehingga txn_type wajib
            // ikut dalam pengecekan untuk membedakan bet dan win.
            $existing = SeamlessTransaction::where('txn_id', $txnId)
                ->where('txn_type', $txnType)
                ->first();

            // Idempotent: sudah diproses sebelumnya, kembalikan balance yang tersimpan
            if ($existing) {
                return response()->json([
                    'status' => 1,
                    'user_balance' => (float) $existing->balance_after,
                ]);
            }

            $locked = User::where('id', $user->id)->lockForUpdate()->first();
            if (!$locked) {
                return response()->json(['status' => 0, 'msg' => 'USER_NOT_FOUND']);
            }

            $before = (float) $locked->saldo;

            $delta = $win - $bet;
            if ($txnType === 'debit') {
                $delta = -$bet;
            } elseif ($txnType === 'credit') {
                $delta = $win;
            }

            if ($delta < 0 && $before < abs($delta)) {
                return response()->json(['status' => 0, 'msg' => 'INSUFFICIENT_USER_FUNDS']);
            }

            $after = round($before + $delta, 2);

            $locked->saldo = $after;
            $locked->exists = true;
            $locked->save();

            SeamlessTransaction::create([
                'txn_id'         => $txnId,
                'round_id'       => $game['round_id'] ?? null,
                'user_id'        => $user->id,
                'user_code'      => $user->username,
                'game_type'      => $gameType,
                'provider_code'  => $game['provider_code'] ?? null,
                'game_code'      => $game['game_code'] ?? null,
                'txn_type'       => $txnType,
                'bet_money'      => $bet,
                'win_money'      => $win,
                'balance_before' => $before,
                'balance_after'  => $after,
                'payload'        => json_encode($payload),
            ]);

            Log::info('GOLD_API TRANSACTION APPLIED', [
                'user' => $user->username,
                'txn_id' => $txnId,
                'txn_type' => $txnType,
                'bet' => $bet,
                'win' => $win,
                'before' => $before,
                'after' => $after,
            ]);

            return response()->json([
                'status' => 1,
                'user_balance' => $after,
            ]);
        });
    }
}
