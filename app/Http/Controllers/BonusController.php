<?php

namespace App\Http\Controllers;

use App\Http\API\fiver;
use App\Models\User;
use App\Models\Klaim;
use App\Models\Setting;
use App\Models\Promotion;
use Illuminate\Support\Carbon;
use App\Http\Controllers\Controller;

class BonusController extends Controller
{
    /**
     * Helper untuk mengambil balance user dari API dengan aman
     */
    private function getUserBalance($extplayer)
    {
        $SG = new fiver();
        $act = json_decode($SG->userbalance($extplayer));

        // Ambil balance dari berbagai kemungkinan struktur response
        return $act->balance 
            ?? $act->user->balance 
            ?? $act->data->balance 
            ?? 0;
    }

    public function index()
    {
        $user = auth()->user();
        $hiddenBalance = $this->getUserBalance($user->extplayer);

        // Format balance
        if ($hiddenBalance == 0) {
            $balance = '0,00';
        } else {
            $formattedBalance = number_format($hiddenBalance, 2, ',', '.');
            if ($hiddenBalance < 1000 && $hiddenBalance > 0) {
                $balance = '0.' . substr_replace($formattedBalance, '', -4);
            } else {
                $balance = substr_replace($formattedBalance, '', -4);
            }
        }

        $claimedPromotionIds = Klaim::where('user_id', $user->id)->pluck('promo_id')->toArray();
        $promotion = Promotion::all();
        $setting = Setting::orderBy('created_at', 'DESC')->first();

        return view('bonus', compact('setting', 'balance', 'promotion', 'claimedPromotionIds'));
    }

    public function update($id)
    {
        $promo = Promotion::findOrFail($id);
        $user = auth()->user();

        // Cek apakah user sudah pernah klaim promo ini
        $alreadyClaimed = Klaim::where('user_id', $user->id)
            ->where('promo_id', $promo->id)
            ->exists();

        if ($alreadyClaimed) {
            return back()->with('info', 'Promo ini sudah pernah kamu klaim.');
        }

        $hiddenBalance = $this->getUserBalance($user->extplayer);

        if ($hiddenBalance < $promo->min_deposite) {
            return back()->with('info', 'Saldo Anda tidak mencukupi untuk mengambil promo ini.');
        }

        $bonusAmount = ($hiddenBalance * $promo->bonus) / 100;

        // Proses deposit bonus ke akun game
        $SG = new fiver();
        $depositResponse = json_decode($SG->deposit($user->extplayer, $bonusAmount));

        if (isset($depositResponse->msg) && $depositResponse->msg === 'SUCCESS') {
            // Catat klaim di database
            Klaim::create([
                'user_id'   => $user->id,
                'nominal'   => $bonusAmount,
                'promo_id'  => $promo->id,
                'used_promo'=> 1
            ]);

            // Update saldo di tabel users (jika ada kolom saldo)
            $user->update(['saldo' => $bonusAmount]);

            return back()->with('success', 'Klaim Bonus Berhasil!');
        }

        return back()->with('info', 'Gagal mengklaim bonus. Silakan coba lagi.');
    }

    public function historyKlaim()
    {
        $user = auth()->user();
        $hiddenBalance = $this->getUserBalance($user->extplayer);

        // Format balance
        if ($hiddenBalance == 0) {
            $balance = '0,00';
        } else {
            $formattedBalance = number_format($hiddenBalance, 2, ',', '.');
            if ($hiddenBalance < 1000 && $hiddenBalance > 0) {
                $balance = '0.' . substr_replace($formattedBalance, '', -4);
            } else {
                $balance = substr_replace($formattedBalance, '', -4);
            }
        }

        $setting = Setting::orderBy('created_at', 'DESC')->first();

        return view('history_klaim', compact('setting', 'balance'));
    }

    public function historyKlaims()
    {
        $claimedPromotion = Klaim::where('user_id', auth()->id())
            ->with('promotion')
            ->first();

        if ($claimedPromotion && $claimedPromotion->promotion) {
            $promotion = $claimedPromotion->promotion;

            $createdAt = Carbon::parse($claimedPromotion->created_at);
            $endDate = $createdAt->copy()->addHours(24);

            $promotion->start_date = $createdAt->format('Y-m-d H:i:s');
            $promotion->end_date = $endDate->format('Y-m-d H:i:s');
            $promotion->amount = $claimedPromotion->nominal;

            return response()->json(['promotion' => $promotion]);
        }

        return response()->json(['promotion' => null]);
    }
}