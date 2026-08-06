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

    public function depositsAll(Request $request)
    {
        $setting = Setting::latest()->first();

        $query = Transaksi::with('user.bank')
            ->where('type', 1);

        if ($request->filled('status_id')) {
            $query->where('status_id', $request->status_id);
        }
        if ($request->filled('search')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('username', 'like', "%{$request->search}%");
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $data = $query->orderBy('created_at', 'DESC')->paginate(20);

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
            'data' => ['transactions' => $data],
        ]);
    }

    public function withdrawsAll(Request $request)
    {
        $query = Transaksi::with('user.bank')
            ->where('type', 2);

        if ($request->filled('status_id')) {
            $query->where('status_id', $request->status_id);
        }
        if ($request->filled('search')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('username', 'like', "%{$request->search}%");
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $data = $query->orderBy('created_at', 'DESC')->paginate(20);

        $data->getCollection()->transform(function ($item) {
            $item->accName = optional($item->user)->bank->nama_penerima ?? '-';
            $item->bankMember = optional($item->user)->bank->nama_bank ?? '-';
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => ['transactions' => $data],
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
