<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\WalletService;
use App\Models\Setting;

class TransferController extends Controller
{
    protected $wallet;

    public function __construct(WalletService $wallet)
    {
        $this->wallet = $wallet;
    }

    public function index()
    {
        $user = Auth::user();

        $setting = Setting::orderBy('created_at', 'DESC')->first();

        return view('transfer', [
            'setting'     => $setting,
            'mainBalance' => $this->wallet->getMainBalance($user),
            'slotBalance' => $this->wallet->getSlotBalance($user),
            'gameBalance' => $this->wallet->getGameBalance($user),
        ]);
    }

    public function transfer(Request $request)
    {
        $request->validate([
            'from'   => 'required',
            'to'     => 'required',
            'amount' => 'required|numeric|min:1000',
        ]);

        if ($request->from == $request->to) {
            return back()->with('error', 'Asal dan tujuan transfer tidak boleh sama.');
        }

        try {

            $user = Auth::user();

            switch ($request->from . '-' . $request->to) {

                case 'main-slot':
                    $this->wallet->transferToSlot($user, $request->amount);
                    break;

                case 'slot-main':
                    $this->wallet->transferFromSlot($user, $request->amount);
                    break;

                case 'main-game':
                    $this->wallet->transferToGame($user, $request->amount);
                    break;

                case 'game-main':
                    $this->wallet->transferFromGame($user, $request->amount);
                    break;

                case 'game-slot':
                    $this->wallet->transferFromGame($user, $request->amount);
                    $this->wallet->transferToSlot($user, $request->amount);
                    break;

                case 'slot-game':
                    $this->wallet->transferFromSlot($user, $request->amount);
                    $this->wallet->transferToGame($user, $request->amount);
                    break;

                default:
                    return back()->with('error', 'Transfer tidak didukung.');
            }

            return back()->with('success', 'Transfer berhasil.');

        } catch (\Exception $e) {

            return back()->with('error', $e->getMessage());

        }
    }
}