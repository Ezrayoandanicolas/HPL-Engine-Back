<?php

namespace App\Http\Controllers;

use App\Http\API\fiver;
use App\Http\API\Exa;
use App\Models\User;
use App\Models\Banner;
use App\Models\Setting;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Hash;

class RegisterasiController extends Controller
{
    public function index()
    {
        $banner = Banner::all();
        $setting = Setting::orderBy('created_at', 'DESC')->first();

        return view('layout.mobile.registerasi', compact('setting', 'banner'));
    }

    public function loadReferral(Request $request)
    {
        $setting = Setting::orderBy('created_at', 'DESC')->first();

        if (isset($request->ref)) {

            $referral = $request->ref;
            $user = User::where('ref', $referral)->first();

            if ($user) {
                return view('layout.mobile.registerasi', compact('referral', 'setting'));
            }

            return view('404');
        }

        return back()->with('info', 'Invalid referral code');
    }

    public function registerasi(Request $request)
    {
        $refferalcode = Str::random(6);
        $domain = URL::to('/');
        $Url = $domain . '/referral-register?ref=' . $refferalcode;

        $request->validate([
            'username'   => 'required|unique:users|regex:/^[0-9a-zA-Z]{3,12}$/',
            'password'   => 'required|min:8|max:20|regex:/^(?=.*[a-z])(?=.*[0-9]).*$/|confirmed',
            'email'      => 'required|email|unique:users',
            'phone'      => 'required',
            'accNumber'  => 'required',
            'accName'    => 'required|regex:/^[0-9a-zA-Z ]*$/',
            'bank'       => 'required',
            'country'    => 'required',
            'informasi'  => 'required',
            'whatsapp'   => 'required',
        ], [
            'username.regex' => 'Nama pengguna harus terdiri dari 3-12 karakter.',
            'password.regex' => 'Password harus mengandung huruf dan angka.',
        ]);

        $EXA = new Exa();

        $extplayer = $request->username . mt_rand(100, 999);

        /*
        |--------------------------------------------------------------------------
        | REGISTER EXA
        |--------------------------------------------------------------------------
        */

        $exaPlayerId = null;

        try {

            $exaResponse = $EXA->createMember(
                $request->username,
                $request->email,
                $request->password,
                $request->accName,
                $request->phone
            );

            if (isset($exaResponse['player']['id'])) {
                $exaPlayerId = $exaResponse['player']['id'];
            }

        } catch (\Exception $e) {

            // kalau EXA gagal tetap lanjut register website
            $exaPlayerId = null;
        }

        /*
        |--------------------------------------------------------------------------
        | SAVE USER
        |--------------------------------------------------------------------------
        */

        $user = new User();

        $user->username = $request->username;
        $user->password = Hash::make($request->password);

        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->whatsapp = $request->whatsapp;

        $user->bank = $request->bank;
        $user->accName = $request->accName;
        $user->accNumber = $request->accNumber;

        $user->informasi = $request->informasi;
        $user->pembayaran = $request->pembayaran ?? 'bank';
        $user->country = $request->country;

        $user->extplayer = $extplayer;

        // PLAYER EXA
        $user->exa_player_id = $exaPlayerId;

        $user->saldo = 0;
        $user->level = 'New Player';

        $user->captcha = null;

        $user->ref = $refferalcode;
        $user->ref_link = $Url;

        $user->save();

        /*
        |--------------------------------------------------------------------------
        | REFERRAL
        |--------------------------------------------------------------------------
        */

        if ($request->ref) {

            $referrer = User::where('ref', $request->ref)->first();

            if ($referrer) {

                DB::table('networks')->insert([

                    'ref'        => $request->ref,
                    'user_id'    => $user->id,
                    'parent_id'  => $referrer->id,
                    'username'   => $request->username,
                    'created_at' => now(),
                    'updated_at' => now(),

                ]);
            }
        }

        return redirect('/')
            ->with('success', 'Registrasi Berhasil. Silahkan Login');
    }
}