<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\API\DigitalCreative;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    protected $wallet;

    public function __construct(WalletService $wallet)
    {
        $this->wallet = $wallet;
    }

    public function balance(Request $request)
    {
        $user = $request->input('user_id') ? User::find($request->user_id) : null;
        if (!$user) {
            return response()->json(['main' => 0, 'slot' => 0, 'game' => 0]);
        }

        $this->syncGameBalance($user);

        return response()->json([
            'main'  => (float) $user->saldo,
            'slot'  => (float) $user->saldo_slot,
            'game'  => (float) $user->saldo_game,
            'total' => (float) $user->saldo + (float) $user->saldo_slot + (float) $user->saldo_game,
        ]);
    }

    private function syncGameBalance(User $user): void
    {
        if ((float) $user->saldo_game <= 0) {
            return;
        }

        // Batasi sinkronisasi: maksimal 1x per 5 detik per user (samakan dengan polling frontend).
        $cacheKey = 'dc_balance_sync_' . $user->id;
        if (Cache::has($cacheKey)) {
            return;
        }
        Cache::put($cacheKey, true, 5);

        try {
            $dc = new DigitalCreative();
            $start = now()->startOfDay()->format('Y-m-d H:i:s');
            $end = now()->endOfDay()->format('Y-m-d H:i:s');
            $raw = $dc->historyPlay($user->username, 'slot', $start, $end, 0, 100);

            if (!$raw) {
                return;
            }

            $data = json_decode($raw, true);
            $logs = $data['slot'] ?? [];
            if (empty($logs)) {
                return;
            }

            $latest = end($logs);
            $endBalance = (float) ($latest['user_end_balance'] ?? 0);

            if ($endBalance > 0 && $endBalance != (float) $user->saldo_game) {
                $user->saldo_game = $endBalance;
                $user->save();

                Log::info('DC GAME BALANCE SYNCED', [
                    'user'   => $user->username,
                    'from'   => $user->getOriginal('saldo_game'),
                    'to'     => $endBalance,
                    'source' => 'get_game_log',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('DC GAME BALANCE SYNC FAILED', [
                'user'    => $user->username,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function transfer(Request $request)
    {
        $user = $request->input('user_id') ? User::find($request->user_id) : null;
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $data = $request->validate([
            'from'   => 'required|in:main,slot,game',
            'to'     => 'required|in:main,slot,game',
            'amount' => 'required|numeric|min:1000',
        ]);

        if ($data['from'] == $data['to']) {
            return response()->json(['success' => false, 'message' => 'Asal dan tujuan transfer tidak boleh sama.']);
        }

        $amount = (float) $data['amount'];

        try {
            match ("{$data['from']}_{$data['to']}") {
                'main_slot' => $this->wallet->transferToSlot($user, $amount),
                'slot_main' => $this->wallet->transferFromSlot($user, $amount),
                'main_game' => $this->wallet->transferToGame($user, $amount),
                'game_main' => $this->wallet->transferFromGame($user, $amount),
                default     => throw new \Exception('Kombinasi transfer tidak valid.'),
            };

            return response()->json([
                'success' => true,
                'message' => 'Transfer berhasil.',
                'balance' => [
                    'main' => (float) $user->saldo,
                    'slot' => (float) $user->saldo_slot,
                    'game' => (float) $user->saldo_game,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
