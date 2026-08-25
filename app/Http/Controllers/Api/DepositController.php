<?php

namespace App\Http\Controllers\Api;

use App\Models\Bank;
use App\Models\Banner;
use App\Models\Network;
use App\Models\Setting;
use App\Models\Rekening;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DepositController extends BaseApiController
{
    public function index()
    {
        $user = $this->getAuthenticatedUser();
        if (!$user) return $this->error('Unauthenticated', 401);

        $balance = $this->formatBalance($user->saldo);
        $bank_deposite = Bank::all();
        $rekening = Rekening::orderBy('created_at', 'DESC')
            ->where('user_id', $user->id)->first();
        $banner = Banner::all();
        $setting = Setting::orderBy('created_at', 'DESC')->first();

        return $this->success([
            'balance' => $balance,
            'bank_deposite' => $bank_deposite,
            'rekening' => $rekening,
            'banner' => $banner,
            'setting' => $setting,
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            $userId = $request->input('user_id');
            if ($userId) {
                $user = \App\Models\User::find($userId);
            }
            if (!$user) return $this->error('Unauthenticated', 401);
        }

        $pending = Transaksi::where('user_id', $user->id)
            ->where('status_id', '1')->where('type', 1)->first();

        if ($pending) {
            return $this->error('Tidak Bisa Melakukan Deposit. Menunggu Deposit Sebelumnya Diterima!');
        }

        $validateData = $request->validate([
            'bankMember' => 'required|max:255',
            'amount' => 'required',
            'img' => 'nullable|image|file|mimes:jpeg,png,jpg|max:2024',
            'bank_penerima' => 'required',
            'nama_penerima' => 'required',
            'nomer_penerima' => 'required',
        ]);

        if ($request->hasFile('img')) {
            $validateData['img'] = $request->file('img')->store('post-images');
        } else {
            $validateData['img'] = $request->input('img') ?? NULL;
        }

        $validateData['amount'] = $request->amount * 1000;
        $validateData['user_id'] = $user->id;
        $validateData['status_id'] = 1;
        $validateData['type'] = $request->type ?? 1;
        $validateData['accName'] = $user->accName;
        $validateData['notes'] = 'unread';

        $network = Network::where('user_id', $user->id)->first();
        $validateData['ref'] = $network ? $network->ref : 'NULL';

        $deposit = Transaksi::create($validateData);

        // Telegram notification
        try {
            $tgMsgId = app(\App\Services\TelegramNotifService::class)->sendDepositPending($deposit, $user);
            if ($tgMsgId) {
                $deposit->update(['tg_message_id' => $tgMsgId]);
            }
        } catch (\Exception $e) {}

        return $this->success($deposit, 'Deposit berhasil');
    }
}
