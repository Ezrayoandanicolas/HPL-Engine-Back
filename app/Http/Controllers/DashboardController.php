<?php

namespace App\Http\Controllers;

use App\Http\API\fiver;
use App\Http\API\exa;
use App\Models\Game;
use App\Models\User;
use App\Models\Gamer;
use App\Models\Network;
use App\Models\Deposite;
use App\Models\Withdraw;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $User = User::where('username', 'like', '%' . $search . '%')->paginate(10);
        $Deposite = Transaksi::all();
        $totalDeposit = Transaksi::where('status_id', 2)
            ->where('type', 1)
            ->sum('amount');
        $totalWithdraw = Transaksi::where('status_id', 2)
            ->where('type', 2)
            ->sum('amount');
        $totalPendapatan = $totalDeposit - $totalWithdraw;
        $totalUser = User::count();

        $totalref = Network::where('ref', Auth()->User()->ref)->count();
        $totalrefDeposite = Transaksi::where('status_id', 2)
            ->where('type', 1)
            ->where('ref', Auth()->User()->ref)
            ->sum('amount');
        $totalpendingrefDeposite = Transaksi::where('status_id', 1)
            ->where('ref', Auth()->User()->ref)
            ->count();
        $userrefDeposite = Network::where('ref', Auth()->User()->ref)->get();

        $jumlahDepositPending = Transaksi::latest()
            ->where('type', 1)
            ->where('status_id', 1)
            ->count();
        $jumlahWithdrawPending = Transaksi::latest()
            ->where('type', 2)
            ->where('status_id', 1)
            ->count();

        $SG = new fiver();
$act = json_decode($SG->agentbalance());
$agentBalance = $act->agent->balance ?? 0;

// Balance Exa
$EXA = new exa();
$exaBalance = $EXA->agentBalance();
// Total Game
$Game = Game::where('game_category', 'SL')->count();

return view('backoffice.backoffice', [
    'User' => $User,
    'Deposite' => $Deposite,
    'totalDeposite' => $totalDeposit,
    'totalWithdraw' => $totalWithdraw,
    'totalUser' => $totalUser,
    'pendingDeposite' => $jumlahDepositPending,
    'pendingWithdraw' => $jumlahWithdrawPending,
    'totalPendapatan' => $totalPendapatan,
    'totalref' => $totalref,
    'totalpendingrefDeposite' => $totalpendingrefDeposite,
    'userrefDeposite' => $userrefDeposite,
    'totalrefDeposite' => $totalrefDeposite,

    // Balance Fiver
    'agentBalance' => $agentBalance,

    // Balance Exa
    'exaBalance' => $exaBalance,

    'Game' => $Game
]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $User = User::findOrFail($id);
        return view('Dashboard.edit', [
            'User' => $User,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $User = User::find($id);
        $rules = [
            'bank' => 'required',
            'accNumber' => 'required',
            'accName' => 'required',
        ];

        $validateData = $request->validate($rules);

        User::where('id', $User->id)
            ->update($validateData);

        return redirect('/Admin/Dashboard')->with('success', 'User Bank has been Updated!!');
    }

    /**
     * Memproses persetujuan Deposit (Approve) ke API Game
     */
    public function approveDeposit($id)
    {
        // 1. Ambil data transaksi deposit yang masih pending (status_id = 1, type = 1)
        $transaksi = Transaksi::where('id', $id)
            ->where('type', 1)
            ->where('status_id', 1)
            ->firstOrFail();

        // 2. Ambil data User terkait untuk mendapatkan username game-nya
        $user = User::findOrFail($transaksi->user_id);

        $SG = new fiver();
        
        // Buat kode unik agent_sign agar tidak terjadi double request (mencegah manipulasi saldo ganda)
        $agentSign = 'DEP_TX_' . $transaksi->id; 

        // Eksekusi fungsi deposit dari fiver.php
        $response = $SG->deposit($user->username, $transaksi->amount, $agentSign);
        $result = json_decode($response);

        // 3. Validasi respon sukses dari API NexusGGR
        if ($result && isset($result->status) && $result->status === 'success') {
            
            // Update status transaksi lokal di database menjadi Sukses (status_id = 2)
            $transaksi->update([
                'status_id' => 2 
            ]);

            return redirect()->back()->with('success', 'Deposit berhasil disetujui, saldo otomatis masuk ke game!');
        }

        $errorMessage = $result->message ?? 'Gagal menghubungi server API game';
        return redirect()->back()->with('error', 'Gagal memproses API: ' . $errorMessage);
    }

    /**
     * Memproses persetujuan Withdraw (Approve) ke API Game
     */
    public function approveWithdraw($id)
    {
        // 1. Ambil data transaksi withdraw yang masih pending (status_id = 1, type = 2)
        $transaksi = Transaksi::where('id', $id)
            ->where('type', 2)
            ->where('status_id', 1)
            ->firstOrFail();

        $user = User::findOrFail($transaksi->user_id);

        $SG = new fiver();
        $agentSign = 'WD_TX_' . $transaksi->id;

        // Eksekusi fungsi withdraw dari fiver.php
        $response = $SG->withdraw($user->username, $transaksi->amount, $agentSign);
        $result = json_decode($response);

        if ($result && isset($result->status) && $result->status === 'success') {
            
            // Update status transaksi lokal di database menjadi Sukses (status_id = 2)
            $transaksi->update([
                'status_id' => 2 
            ]);

            return redirect()->back()->with('success', 'Withdraw berhasil disetujui, saldo game otomatis dipotong!');
        }

        $errorMessage = $result->message ?? 'Gagal menghubungi server API game';
        return redirect()->back()->with('error', 'Gagal memproses API: ' . $errorMessage);
    }

    public function getDeposit()
    {
        $todayDeposits = Transaksi::whereDate('created_at', Carbon::today())
            ->where('type', 1)
            ->where('status_id', 1)
            ->get();

        return response()->json($todayDeposits);
    }
    
    public function getWithdawDashboard()
    {
        $todayWithdraw = Transaksi::whereDate('created_at', Carbon::today())
            ->where('type', 2)
            ->where('status_id', 1)
            ->get();

        return response()->json($todayWithdraw);
    }
}