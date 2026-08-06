<?php

namespace App\Http\Controllers;

use App\Http\API\fiver; 
use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function index()
    {
        $setting = Setting::orderBy('created_at', 'DESC')->first();
        return view('layout.mobile.login', compact('setting'));
    }

    public function auth(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required',
            'password' => 'required'
        ]);

        $User = User::where('username', $request->username)->first();
        
        if (!$User) {
            return back()->withErrors(['username' => 'User tidak ditemukan.']);
        }

        if (!Hash::check($request->password, $User->password)) {
            return back()->withErrors(['password' => 'Username dan Password tidak sesuai.']);
        }

        if (Auth::attempt($credentials)) {
            // ==========================================
            // BYPASS API FIVER UNTUK SEMENTARA
            // ==========================================
            // $SG = new fiver();
            // $act = json_decode($SG->userbalance($User->extplayer));

            // CATATAN PENTING UNTUK NANTI: 
            // Jika API diaktifkan lagi, gunakan 'isset()' agar tidak muncul layar merah saat API sedang gangguan
            // if (isset($act) && isset($act->msg) && $act->msg == 'SUCCESS') {
            
            // Untuk sekarang, kita langsung loloskan loginnya:
            if (true) {
                $request->session()->regenerate();
                return redirect('/');
            } else {
                // Saya ubah pesannya, karena kalau sampai di sini artinya password sudah benar,
                // tapi API-nya yang menolak/gangguan.
                return back()->with('info', 'Gagal terhubung ke server permainan. Silahkan coba lagi nanti.');
            }
        }
        
        return back()->with('info', 'Login gagal, silahkan periksa kembali data Anda.');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        // Perbaikan kecil: invalidate() tidak perlu diisi parameter session() di dalamnya
        $request->session()->invalidate(); 

        $request->session()->regenerateToken();

        return redirect('/');
    }
}