<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\Request;

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

        return response()->json([
            'main' => (float) $user->saldo,
            'slot' => (float) $user->saldo_slot,
            'game' => (float) $user->saldo_game,
        ]);
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
