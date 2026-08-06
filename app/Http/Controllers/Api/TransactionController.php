<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaksi;
use App\Models\Setting;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function depositsCompleted()
    {
        $setting = Setting::latest()->first();

        $data = Transaksi::with('user.bank')
            ->where('type', 1)
            ->whereIn('status_id', [2, 3])
            ->orderBy('created_at', 'DESC')
            ->paginate(20);

        $data->getCollection()->transform(function ($item) use ($setting) {
            $item->accName = optional($item->user)->bank->nama_penerima ?? '-';
            $item->bankMember = optional($item->user)->bank->nama_bank ?? '-';
            $item->bank_penerima = $setting->bank_name ?? '-';
            $item->nama_penerima = $setting->bank_holder ?? '-';
            $item->nomer_penerima = $setting->bank_account ?? '-';
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $data,
            ],
        ]);
    }

    public function withdrawsCompleted()
    {
        $data = Transaksi::with('user.bank')
            ->where('type', 2)
            ->whereIn('status_id', [2, 3])
            ->orderBy('created_at', 'DESC')
            ->paginate(20);

        $data->getCollection()->transform(function ($item) {
            $item->accName = optional($item->user)->bank->nama_penerima ?? '-';
            $item->bankMember = optional($item->user)->bank->nama_bank ?? '-';
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $data,
            ],
        ]);
    }

    public function unreadCount(Request $request)
    {
        $userId = $request->input('user_id');
        if (!$userId) {
            return response()->json(['unreadCount' => 0]);
        }
        $count = Transaksi::where('user_id', $userId)
            ->where('notes', 'unread')
            ->count();
        return response()->json(['unreadCount' => $count]);
    }
}
