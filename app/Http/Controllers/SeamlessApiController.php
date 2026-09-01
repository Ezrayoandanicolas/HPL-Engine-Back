<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\SeamlessTransaction;
use App\Http\API\DigitalCreative;
use App\Http\API\XApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

        // Identify provider by operator_code
        $dc = new DigitalCreative();
        $xapi = new XApi();

        $provider = null;
        if ($operatorCode === $dc->agen) {
            $provider = 'dc';
            $secret = $dc->secret;
        } elseif ($operatorCode === $xapi->agen) {
            $provider = 'xapi';
            $secret = $xapi->secret_key;
        } else {
            return response()->json(['code' => 1003, 'message' => 'Invalid operator code']);
        }

        if (!$requestTime || !$sign) {
            return response()->json(['code' => 1004, 'message' => 'Invalid signature']);
        }

        // sign = md5(operator_code + request_time + action + secret_key)
        $expectedSign = md5($operatorCode . $requestTime . $action . $secret);
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
            case 'balance':
                return $this->getBalance($batch, $currency);
            case 'withdraw':
                return $this->withdraw($batch, $currency);
            case 'deposit':
                return $this->deposit($batch, $currency);
            case 'pushbetdata':
            case 'pushbet':
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

    // X-API Seamless Mode (siteEndPoint format)
    // POST /seamless/GetBalance
    public function getBalanceXapi(Request $request)
    {
        $userCode = $request->input('user_code');
        Log::info('XAPI_SEAMLESS GetBalance', ['user_code' => $userCode]);

        $user = User::where('username', $userCode)->orWhere('aas_user_code', $userCode)->first();

        if (!$user) {
            $user = User::create([
                'username'      => $userCode,
                'name'          => $userCode,
                'email'         => strtolower($userCode) . '@xapi.local',
                'password'      => Hash::make('password123'),
                'role'          => 'member',
                'phone'         => '0000000000',
                'whatsapp'      => '0000000000',
                'bank'          => 'BCA',
                'accNumber'     => '0000000000',
                'accName'       => $userCode,
                'country'       => 'ID',
                'informasi'     => 'Auto-created from X-API Seamless',
                'aas_user_code' => $userCode,
            ]);
            Log::info('XAPI_SEAMLESS auto-created user', ['user_code' => $userCode, 'id' => $user->id]);
        }

        $balance = (float) $user->saldo + (float) $user->saldo_game;
        return response()->json([
            'data' => ['user_balance' => round($balance, 2)],
            'error' => 0,
            'description' => 'OK',
        ]);
    }

    // POST /seamless/UTransaction
    public function uTransactionXapi(Request $request)
    {
        $userCode = $request->input('user_code');
        $amount = (float) $request->input('amount', 0);
        $txnType = (int) $request->input('transaction_type', 0);
        $transId = $request->input('trans_id', '');

        Log::info('XAPI_SEAMLESS UTransaction', [
            'user_code' => $userCode,
            'amount' => $amount,
            'type' => $txnType,
            'trans_id' => $transId,
        ]);

        $user = User::where('username', $userCode)->orWhere('aas_user_code', $userCode)->first();

        if (!$user) {
            $user = User::create([
                'username'      => $userCode,
                'name'          => $userCode,
                'email'         => strtolower($userCode) . '@xapi.local',
                'password'      => Hash::make('password123'),
                'role'          => 'member',
                'phone'         => '0000000000',
                'whatsapp'      => '0000000000',
                'bank'          => 'BCA',
                'accNumber'     => '0000000000',
                'accName'       => $userCode,
                'country'       => 'ID',
                'informasi'     => 'Auto-created from X-API Seamless',
                'aas_user_code' => $userCode,
            ]);
            Log::info('XAPI_SEAMLESS auto-created user (UTransaction)', ['user_code' => $userCode, 'id' => $user->id]);
        }

        $payload = $request->all();

        return DB::transaction(function () use ($user, $amount, $txnType, $transId, $payload) {
            if ($transId) {
                $existing = SeamlessTransaction::where('txn_id', $transId)->first();
                if ($existing) {
                    $balance = (float) $user->saldo + (float) $user->saldo_game;
                    return response()->json([
                        'data' => ['user_balance' => round($balance, 2)],
                        'error' => 0,
                        'description' => 'OK',
                    ]);
                }
            }

            $locked = User::where('id', $user->id)->lockForUpdate()->first();
            $total = (float) $locked->saldo + (float) $locked->saldo_game;

            switch ($txnType) {
                case 1: // Bet
                    if ($total < $amount) {
                        return response()->json([
                            'data' => ['user_balance' => round($total, 2)],
                            'error' => 2001,
                            'description' => 'PointNotEnough',
                        ]);
                    }
                    $fromGame = min((float) $locked->saldo_game, $amount);
                    $fromSaldo = $amount - $fromGame;
                    $locked->saldo = round((float) $locked->saldo - $fromSaldo, 2);
                    $locked->saldo_game = round((float) $locked->saldo_game - $fromGame, 2);
                    break;

                case 2: // Win
                    $locked->saldo = round((float) $locked->saldo + $amount, 2);
                    break;

                case 3: // Cancel
                    $locked->saldo = round((float) $locked->saldo + $amount, 2);
                    break;
            }

            $locked->exists = true;
            $locked->save();

            $newTotal = (float) $locked->saldo + (float) $locked->saldo_game;

            SeamlessTransaction::create([
                'txn_id' => $transId,
                'user_id' => $user->id,
                'user_code' => $user->username,
                'txn_type' => "xapi_seamless_{$txnType}",
                'bet_money' => $txnType == 1 ? $amount : 0,
                'win_money' => $txnType == 2 ? $amount : 0,
                'balance_before' => $total,
                'balance_after' => $newTotal,
                'payload' => json_encode($payload),
            ]);

            return response()->json([
                'data' => ['user_balance' => round($newTotal, 2)],
                'error' => 0,
                'description' => 'OK',
            ]);
        });
    }

    // X-API Agent Seamless (apiType=0) — format mirip DC /gold_api
    // POST /seamless dengan field method=balance|withdraw|deposit|pushbet
    public function handleAgentSeamless(Request $request)
    {
        $payload = $request->json()->all();

        Log::info('XAPI_AGENT_SEAMLESS INCOMING', ['payload' => $payload]);

        $method = $payload['method'] ?? null;
        $agentCode = $payload['agent_code'] ?? null;
        $userCode = $payload['user_code'] ?? null;
        $amount = (float) ($payload['amount'] ?? 0);
        $txnId = $payload['txn_id'] ?? null;
        $currency = $payload['currency'] ?? 'IDR';
        $requestTime = $payload['request_time'] ?? null;
        $sign = $payload['sign'] ?? null;

        $xapi = new \App\Http\API\XApi();

        if ($agentCode !== $xapi->agen) {
            return response()->json(['status' => 0, 'msg' => 'AUTH_FAILED']);
        }

        if (!$requestTime || !$sign) {
            return response()->json(['status' => 0, 'msg' => 'INVALID_SIGN']);
        }

        // sign = md5(agent_code + request_time + method + secret_key)
        $expectedSign = md5($agentCode . $requestTime . $method . $xapi->secret_key);
        if (!hash_equals($expectedSign, $sign)) {
            return response()->json(['status' => 0, 'msg' => 'INVALID_SIGN']);
        }

        if ($currency && !in_array($currency, ['IDR', 'IDR2', 'IDR3'], true)) {
            return response()->json(['status' => 0, 'msg' => 'INVALID_CURRENCY']);
        }

        if (!$userCode) {
            return response()->json(['status' => 0, 'msg' => 'INVALID_USER']);
        }

        $user = \App\Models\User::where('username', $userCode)->first();
        if (!$user) {
            return response()->json(['status' => 0, 'msg' => 'USER_NOT_FOUND']);
        }

        $ratio = $this->ratio($currency);
        $amount = $amount * $ratio;

        switch ($method) {
            case 'balance':
                $balance = ((float) $user->saldo + (float) $user->saldo_game) * $ratio;
                return response()->json([
                    'status' => 1,
                    'msg' => 'SUCCESS',
                    'balance' => round($balance, 2),
                ]);

            case 'withdraw':
            case 'deposit':
                if (!$txnId) {
                    return response()->json(['status' => 0, 'msg' => 'INVALID_TXN_ID']);
                }

                $txnType = $method === 'withdraw' ? 'xapi_agent_withdraw' : 'xapi_agent_deposit';

                return \Illuminate\Support\Facades\DB::transaction(function () use ($user, $method, $amount, $txnId, $txnType, $ratio) {
                    $existing = \App\Models\SeamlessTransaction::where('txn_id', $txnId)
                        ->where('txn_type', $txnType)
                        ->first();

                    if ($existing) {
                        return response()->json([
                            'status' => 1,
                            'msg' => 'SUCCESS',
                            'balance' => $existing->balance_after,
                        ]);
                    }

                    $locked = \App\Models\User::where('id', $user->id)->lockForUpdate()->first();

                    $total = (float) $locked->saldo + (float) $locked->saldo_game;

                    if ($method === 'withdraw') {
                        if ($total < $amount) {
                            return response()->json(['status' => 0, 'msg' => 'INSUFFICIENT_FUNDS']);
                        }
                        // debit dari saldo_game dulu, sisanya dari saldo
                        $fromGame = min((float) $locked->saldo_game, $amount);
                        $fromSaldo = $amount - $fromGame;
                        $locked->saldo = round((float) $locked->saldo - $fromSaldo, 2);
                        $locked->saldo_game = round((float) $locked->saldo_game - $fromGame, 2);
                    } else {
                        // deposit: credit ke saldo
                        $locked->saldo = round((float) $locked->saldo + $amount, 2);
                    }

                    $locked->exists = true;
                    $locked->save();

                    $newTotal = (float) $locked->saldo + (float) $locked->saldo_game;

                    \App\Models\SeamlessTransaction::create([
                        'txn_id' => $txnId,
                        'round_id' => $payload['round_id'] ?? null,
                        'user_id' => $user->id,
                        'user_code' => $user->username,
                        'game_type' => $payload['game_type'] ?? 'slot',
                        'provider_code' => $payload['provider_code'] ?? null,
                        'game_code' => $payload['game_code'] ?? null,
                        'txn_type' => $txnType,
                        'bet_money' => $method === 'withdraw' ? $amount : 0,
                        'win_money' => $method === 'deposit' ? $amount : 0,
                        'balance_before' => $total,
                        'balance_after' => $newTotal,
                        'payload' => json_encode(['agent_seamless' => true]),
                    ]);

                    return response()->json([
                        'status' => 1,
                        'msg' => 'SUCCESS',
                        'balance' => round($newTotal, 2),
                    ]);
                });

            case 'pushbet':
                // pushbet hanya logging, saldo sudah via withdraw/deposit
                return response()->json(['status' => 1, 'msg' => 'SUCCESS']);

            default:
                return response()->json(['status' => 0, 'msg' => 'INVALID_METHOD']);
        }
    }
}