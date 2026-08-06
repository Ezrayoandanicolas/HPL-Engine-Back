<?php

namespace App\Http\Controllers\Api;

use App\Models\Bank;
use App\Models\Klaim;
use App\Models\Network;
use App\Models\Turnover;
use App\Models\Freechip;
use App\Models\Voucher;
use App\Models\Verifikasi;
use App\Models\Transaksi;
use App\Models\User;
use App\Models\AdminMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MemberApiController extends BaseApiController
{
    private function user(Request $request): ?User
    {
        if ($request->filled('user_id')) {
            return User::find($request->user_id);
        }
        return auth()->user();
    }

    // -------------------- Bonus --------------------

    public function claimBonus(Request $request)
    {
        $user = $this->user($request);
        if (!$user) return $this->error('Unauthenticated', 401);

        $promoId = $request->input('promo_id');
        if (!$promoId) return $this->error('promo_id wajib diisi');

        $exists = Klaim::where('user_id', $user->id)->where('promo_id', $promoId)->first();
        if ($exists) return $this->error('Bonus sudah diklaim');

        Klaim::create(['user_id' => $user->id, 'promo_id' => $promoId]);
        return $this->success(['klaim' => $user->id], 'Bonus diklaim');
    }

    public function claimedPromotionIds(Request $request)
    {
        $user = $this->user($request);
        if (!$user) return response()->json([]);
        return response()->json(Klaim::where('user_id', $user->id)->pluck('promo_id')->toArray());
    }

    public function historyKlaims(Request $request)
    {
        $user = $this->user($request);
        if (!$user) return response()->json(['promotion' => null]);

        $klaim = Klaim::where('user_id', $user->id)->latest()->with('promotion')->first();
        if (!$klaim || !$klaim->promotion) return response()->json(['promotion' => null]);

        $p = $klaim->promotion;
        return response()->json([
            'promotion' => [
                'title' => $p->title,
                'start_date' => $p->tanggal_mulai,
                'end_date' => $p->tanggal_akhir,
                'amount' => $p->bonus,
                'turnover' => $p->turnover,
                'status' => $p->status,
            ]
        ]);
    }

    // -------------------- Voucher / Loyalitas --------------------

    public function availableVouchers(Request $request)
    {
        $user = $this->user($request);
        $vouchers = Voucher::orderBy('created_at', 'DESC')->get()->map(function ($v) {
            return [
                'id' => $v->id,
                'title' => $v->title,
                'nominal' => $v->nominal,
                'exp' => $v->nominal,
            ];
        });
        return response()->json($vouchers->values()->toArray());
    }

    public function claimVoucher(Request $request)
    {
        $user = $this->user($request);
        if (!$user) return $this->error('Unauthenticated', 401);

        $voucherId = $request->input('voucher_id');
        $voucher = Voucher::find($voucherId);
        if (!$voucher) return $this->error('Voucher tidak ditemukan');

        $exists = Freechip::where('user_id', $user->id)->where('voucher_id', $voucherId)->first();
        if ($exists) return $this->error('Voucher sudah diklaim');

        Freechip::create([
            'user_id' => $user->id,
            'voucher_id' => $voucherId,
            'used' => 0,
            'nominal' => $voucher->nominal,
        ]);
        return $this->success(null, 'Voucher diklaim');
    }

    public function tarik(Request $request)
    {
        $user = $this->user($request);
        if (!$user) return $this->error('Unauthenticated', 401);
        return $this->success(null, 'Tarik berhasil');
    }

    // -------------------- Referral --------------------

    public function verifikasi(Request $request)
    {
        $user = $this->user($request);
        if (!$user) return response()->json(null);
        $verifikasi = Verifikasi::where('user_id', $user->id)->first();
        return response()->json($verifikasi ? $verifikasi->toArray() : null);
    }

    public function submitVerification(Request $request)
    {
        $user = $this->user($request);
        if (!$user) return $this->error('Unauthenticated', 401);

        $verifikasi = Verifikasi::firstOrNew(['user_id' => $user->id]);
        $verifikasi->status = 1;
        if ($request->has('fullName')) $verifikasi->full_name = $request->input('fullName');
        if ($request->hasFile('img')) $verifikasi->img = $request->file('img')->store('post-images');
        if ($request->hasFile('barcode')) $verifikasi->barcode = $request->file('barcode')->store('post-images');
        $verifikasi->save();

        return $this->success(['verifikasi' => $verifikasi], 'Verifikasi disimpan');
    }

    public function referralData(Request $request)
    {
        $user = $this->user($request);
        if (!$user) {
            return response()->json(['child_referrals' => 0, 'total_earnings' => 0, 'last_earning' => 0]);
        }

        $children = User::where('ref', $user->ref)->where('id', '!=', $user->id)->count();

        return response()->json([
            'child_referrals' => $children,
            'total_earnings' => 0,
            'last_earning' => 0,
        ]);
    }

    public function referralDetails(Request $request)
    {
        $user = $this->user($request);
        if (!$user) return response()->json([]);

        $children = User::where('ref', $user->ref)->where('id', '!=', $user->id)->get();

        return response()->json($children->map(function ($child) {
            return [
                'name' => $child->name,
                'parent_ref' => $child->ref,
                'total_deposit' => 0,
                'bonus' => 0,
                'join_date' => optional($child->created_at)->format('Y-m-d'),
            ];
        })->values()->toArray());
    }

    // -------------------- Turnover --------------------

    public function turnover24h(Request $request)
    {
        $user = $this->user($request);
        $turnover = $user
            ? Turnover::where('user_id', $user->id)->whereDate('created_at', today())->sum('turnover')
            : 0;

        return response()->json([
            'turnover' => (float) $turnover,
            'progressive_goal' => 0,
            'tanggal' => now()->format('Y-m-d'),
            'username' => $user->username ?? '',
            'total_spin' => 0,
            'bet' => 0,
        ]);
    }

    public function turnoverHistory(Request $request)
    {
        $user = $this->user($request);
        if (!$user) return response()->json([]);
        return response()->json(Turnover::where('user_id', $user->id)->orderBy('created_at', 'DESC')->get()->toArray());
    }

    // -------------------- Transactions --------------------

    public function transactionsToday(Request $request)
    {
        $user = $this->user($request);
        if (!$user) return response()->json([]);
        return response()->json(
            Transaksi::where('user_id', $user->id)->orderBy('created_at', 'DESC')->limit(50)->get()->toArray()
        );
    }

    public function markRead(Request $request)
    {
        $user = $this->user($request);
        if ($user) {
            Transaksi::where('user_id', $user->id)->update(['notes' => 'read']);
        }
        return $this->success(null, 'Semua transaksi ditandai sudah dibaca');
    }

    // -------------------- Profile --------------------

    public function profileUpdate(Request $request)
    {
        $user = $this->user($request);
        if (!$user) return $this->error('Unauthenticated', 401);

        if ($request->filled('FullName')) $user->name = $request->input('FullName');
        if ($request->filled('accName')) $user->accName = $request->input('accName');
        if ($request->filled('noHp')) $user->phone = $request->input('noHp');
        if ($request->filled('ContactNo')) $user->phone = $request->input('ContactNo');
        if ($request->filled('WhatsApp')) $user->whatsapp = $request->input('WhatsApp');
        if ($request->filled('Country')) $user->country = $request->input('Country');
        if ($request->filled('Email')) $user->email = $request->input('Email');

        if ($request->filled('Email') && $user->isDirty('email')) {
            $exists = User::where('email', $request->input('Email'))
                ->where('id', '!=', $user->id)
                ->exists();
            if ($exists) {
                return $this->error('Email sudah digunakan');
            }
        }

        $user->save();

        return $this->success(['user' => $user], 'Profile berhasil diubah');
    }

    public function changePassword(Request $request)
    {
        $user = $this->user($request);
        if (!$user) return $this->error('Unauthenticated', 401);

        if (!Hash::check($request->input('old_password'), $user->password)) {
            return $this->error('Password lama salah', 400);
        }

        if ($request->input('new_password') !== $request->input('new_password_confirmation')) {
            return $this->error('Konfirmasi password tidak cocok', 400);
        }

        $user->password = Hash::make($request->input('new_password'));
        $user->save();

        return $this->success(null, 'Password berhasil diubah');
    }

    // -------------------- Bank --------------------

    public function banks()
    {
        return response()->json(Bank::where('status', 1)->get()->toArray());
    }

    // -------------------- Admin Messages (Info) --------------------

    public function adminMessages(Request $request)
    {
        $user = $this->user($request);
        if (!$user) return $this->error('Unauthenticated', 401);

        $messages = AdminMessage::with('sender')
            ->where(function ($q) use ($user) {
                $q->where('type', 'broadcast')
                  ->orWhere('recipient_id', $user->id);
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($m) {
                return [
                    'id' => $m->id,
                    'title' => $m->title,
                    'body' => $m->body,
                    'type' => $m->type,
                    'is_read' => (bool) $m->is_read,
                    'created_at' => $m->created_at,
                ];
            });

        return response()->json($messages);
    }

    // -------------------- Claim Notifications (Promo) --------------------

    public function claimNotifications(Request $request)
    {
        $user = $this->user($request);
        if (!$user) return $this->error('Unauthenticated', 401);

        $claims = Klaim::with('promotion')
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(function ($k) {
                $p = $k->promotion;
                return [
                    'id' => $k->id,
                    'title' => $p ? $p->title : 'Promo',
                    'bonus' => $p ? $p->bonus : null,
                    'created_at' => $k->created_at,
                ];
            });

        return response()->json($claims);
    }
}
