<?php

namespace App\Http\Controllers;

use App\Http\API\Exa;
use App\Models\Sport;
use Illuminate\Support\Facades\Auth;
use App\Services\WalletService;

class SportsPlayController extends Controller
{
    protected $wallet;

    public function __construct(WalletService $wallet)
    {
        $this->wallet = $wallet;
    }

    public function play($game_uid)
    {
        $game = Sport::where('game_uid', $game_uid)->firstOrFail();
        $user = Auth::user();

        $gameBalance = $this->wallet->getGameBalance($user);
        if ($gameBalance <= 0) {
            return redirect()->route('transfer', [
                'from' => 'main', 'to' => 'game'
            ])->with('warning', 'Saldo Game Wallet Anda kosong. Silakan transfer saldo terlebih dahulu.');
        }

        $exa = new Exa();

        $playerId = $user->exa_player_id;
        if (!$playerId) {
            $playerId = $user->username . '_' . $user->id;
            try {
                $exa->createMember($user->username, $user->email, 'member123', $user->name ?? $user->username, $user->phone ?? '08123456789');
                $playerId = $user->username;
            } catch (\Exception $e) {
                // use fallback player ID
            }
            $user->update(['exa_player_id' => $playerId]);
        }

        $result = $exa->launchGame(
            $playerId,
            $game->game_uid,
            'https://gamexaglobal.com'
        );

        if (
            isset($result['status']) &&
            $result['status'] == 200 &&
            !empty($result['body']['success']) &&
            !empty($result['body']['game_launch_url'])
        ) {
            return redirect()->away($result['body']['game_launch_url']);
        }

        return back()->with('error', $result['body']['message'] ?? 'Launch game sports gagal.');
    }
}
