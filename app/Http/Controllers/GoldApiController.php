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

        $isFiver = $agentCode === $fiver->agen && $agentSecret === $fiver->secret;
        $isDc = $agentCode === $dc->agen && ($agentSecret === $dc->token || $agentToken === $dc->token);

        if (!$isFiver && !$isDc) {
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

        switch ($method) {
            case 'user_balance':
                return response()->json([
                    'status' => 1,
                    'user_balance' => (float) $user->saldo,
                ]);

            case 'transaction':
                return $this->processTransaction($user, $payload);

            default:
                return response()->json(['status' => 0, 'msg' => 'INVALID_METHOD']);
        }
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
