<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\SeamlessTransaction;
use App\Http\API\DigitalCreative;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SeamlessApiController extends Controller
{
    public function handle(Request $request, string $action)
    {
        $payload = $request->json()->all();

        Log::info('SEAMLESS_API INCOMING', [
            'action' => $action,
            'payload' => $payload,
        ]);

        $operatorCode = $payload['operator_code'] ?? null;
        $requestTime = $payload['request_time'] ?? null;
        $sign = $payload['sign'] ?? null;
        $currency = $payload['currency'] ?? null;
        $batch = $payload['batch_requests'] ?? null;

        $dc = new DigitalCreative();

        if ($operatorCode !== $dc->agen) {
            return response()->json(['code' => 1003, 'message' => 'Invalid operator code']);
        }

        if (!$requestTime || !$sign) {
            return response()->json(['code' => 1004, 'message' => 'Invalid signature']);
        }

        // sign = md5(operator_code + request_time + action + secret_key)
        $expectedSign = md5($operatorCode . $requestTime . $action . $dc->secret);
        if (!hash_equals($expectedSign, $sign)) {
            return response()->json(['code' => 1004, 'message' => 'Invalid signature']);
        }

        if ($currency && !in_array($currency, ['IDR', 'IDR2', 'IDR3'], true)) {
            return response()->json(['code' => 1006, 'message' => 'Invalid currency']);
        }

        if (!is_array($batch)) {
            return response()->json(['code' => 1000, 'message' => 'Invalid batch_requests']);
        }

        switch ($action) {
            case 'getbalance':
                return $this->getBalance($batch, $currency);
            case 'withdraw':
                return $this->withdraw($batch, $currency);
            case 'deposit':
                return $this->deposit($batch, $currency);
            case 'pushbetdata':
                return $this->pushBetData($batch);
            default:
                return response()->json(['code' => 1004, 'message' => 'Unknown action']);
        }
    }

    private function getBalance(array $batch, ?string $currency)
    {
        $ratio = $this->ratio($currency);
        $data = [];

        foreach ($batch as $req) {
            $user = User::where('username', $req['member_account'] ?? null)->first();
            if (!$user) {
                $data[] = [
                    'member_account' => $req['member_account'] ?? null,
                    'product_code'   => $req['product_code'] ?? null,
                    'balance'        => 0.0,
                    'code'           => 1000,
                    'message'        => 'Member not found',
                ];
                continue;
            }

            $balance = ((float) $user->saldo + (float) $user->saldo_game) * $ratio;
            $data[] = [
                'member_account' => $user->username,
                'product_code'   => $req['product_code'] ?? null,
                'balance'        => round($balance, 2),
                'code'           => 0,
                'message'        => '',
            ];
        }

        return response()->json(['code' => 0, 'message' => '', 'data' => $data]);
    }

    private function withdraw(array $batch, ?string $currency)
    {
        $ratio = $this->ratio($currency);
        $data = [];

        foreach ($batch as $req) {
            $user = User::where('username', $req['member_account'] ?? null)->first();
            if (!$user) {
                $data[] = $this->withdrawRow($req, null, null, 1000, 'Member not found');
                continue;
            }

            $amount = (float) ($req['amount'] ?? 0) * $ratio;
            $txnId = $req['txn_id'] ?? null;
            if (!$txnId) {
                $data[] = $this->withdrawRow($req, null, null, 1008, 'Invalid txn_id');
                continue;
            }

            $result = DB::transaction(function () use ($user, $amount, $txnId, $req) {
                $existing = SeamlessTransaction::where('txn_id', $txnId)
                    ->where('txn_type', 'seamless_withdraw')
                    ->first();

                if ($existing) {
                    return ['before' => $existing->balance_before, 'after' => $existing->balance_after, 'duplicate' => true];
                }

                $locked = User::where('id', $user->id)->lockForUpdate()->first();

                $total = (float) $locked->saldo + (float) $locked->saldo_game;
                if ($total < $amount) {
                    return ['error' => 1007, 'before' => $total];
                }

                // debit dari saldo_game (hold) dulu, sisanya dari saldo
                $fromGame = min((float) $locked->saldo_game, $amount);
                $fromSaldo = $amount - $fromGame;
                $afterSaldo = (float) $locked->saldo - $fromSaldo;
                $afterGame = (float) $locked->saldo_game - $fromGame;

                $locked->saldo = round($afterSaldo, 2);
                $locked->saldo_game = round($afterGame, 2);
                $locked->exists = true;
                $locked->save();

                SeamlessTransaction::create([
                    'txn_id'         => $txnId,
                    'round_id'       => $req['round_id'] ?? null,
                    'user_id'        => $user->id,
                    'user_code'      => $user->username,
                    'game_type'      => 'slot',
                    'provider_code'  => null,
                    'game_code'      => $req['game_code'] ?? null,
                    'txn_type'       => 'seamless_withdraw',
                    'bet_money'      => $amount,
                    'win_money'      => 0,
                    'balance_before' => $total,
                    'balance_after'  => $total - $amount,
                    'payload'        => json_encode(['seamless' => true]),
                ]);

                return ['before' => $total, 'after' => $total - $amount, 'duplicate' => false];
            });

            if (isset($result['error'])) {
                $data[] = $this->withdrawRow($req, $result['before'], $result['before'], $result['error'], 'Insufficient balance');
                continue;
            }

            $data[] = $this->withdrawRow($req, $result['before'], $result['after'], 0, '');
        }

        return response()->json(['code' => 0, 'message' => '', 'data' => $data]);
    }

    private function deposit(array $batch, ?string $currency)
    {
        $ratio = $this->ratio($currency);
        $data = [];

        foreach ($batch as $req) {
            $user = User::where('username', $req['member_account'] ?? null)->first();
            if (!$user) {
                $data[] = $this->withdrawRow($req, null, null, 1000, 'Member not found');
                continue;
            }

            $amount = (float) ($req['amount'] ?? 0) * $ratio;
            $txnId = $req['txn_id'] ?? null;
            if (!$txnId) {
                $data[] = $this->withdrawRow($req, null, null, 1008, 'Invalid txn_id');
                continue;
            }

            $result = DB::transaction(function () use ($user, $amount, $txnId, $req) {
                $existing = SeamlessTransaction::where('txn_id', $txnId)
                    ->where('txn_type', 'seamless_deposit')
                    ->first();

                if ($existing) {
                    return ['before' => $existing->balance_before, 'after' => $existing->balance_after, 'duplicate' => true];
                }

                $locked = User::where('id', $user->id)->lockForUpdate()->first();

                $total = (float) $locked->saldo + (float) $locked->saldo_game;
                $after = $total + $amount;

                $locked->saldo = round((float) $locked->saldo + $amount, 2);
                $locked->exists = true;
                $locked->save();

                SeamlessTransaction::create([
                    'txn_id'         => $txnId,
                    'round_id'       => $req['round_id'] ?? null,
                    'user_id'        => $user->id,
                    'user_code'      => $user->username,
                    'game_type'      => 'slot',
                    'provider_code'  => null,
                    'game_code'      => $req['game_code'] ?? null,
                    'txn_type'       => 'seamless_deposit',
                    'bet_money'      => 0,
                    'win_money'      => $amount,
                    'balance_before' => $total,
                    'balance_after'  => $after,
                    'payload'        => json_encode(['seamless' => true]),
                ]);

                return ['before' => $total, 'after' => $after, 'duplicate' => false];
            });

            $data[] = $this->withdrawRow($req, $result['before'], $result['after'], 0, '');
        }

        return response()->json(['code' => 0, 'message' => '', 'data' => $data]);
    }

    private function pushBetData(array $batch)
    {
        // pushbetdata hanya ringkasan/history — saldo sudah berubah via withdraw/deposit.
        return response()->json(['code' => 0, 'message' => 'Success']);
    }

    private function withdrawRow(array $req, ?float $before, ?float $after, int $code, string $message)
    {
        return [
            'member_account' => $req['member_account'] ?? null,
            'product_code'   => $req['product_code'] ?? null,
            'before_balance' => $before,
            'balance'        => $after,
            'code'           => $code,
            'message'        => $message,
        ];
    }

    private function ratio(?string $currency)
    {
        switch ($currency) {
            case 'IDR2':
                return 1000;
            case 'IDR3':
                return 100;
            default:
                return 1;
        }
    }
}
