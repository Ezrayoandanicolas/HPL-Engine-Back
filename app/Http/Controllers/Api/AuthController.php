<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseApiController
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required'
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->error('Username atau password salah', 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return $this->success([
            'user' => $user,
            'token' => $token,
        ], 'Login berhasil');
    }

    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|unique:users,username',
            'password' => 'required|min:6',
            'email' => 'required|email',
            'phone' => 'required',
            'accNumber' => 'required',
            'accName' => 'required',
            'bank' => 'required',
            'country' => 'required',
            'informasi' => 'required',
            'whatsapp' => 'required',
            'ref_code' => 'nullable',
            'ref_link' => 'nullable',
        ], [
            'username.required' => 'Username harus diisi',
            'password.required' => 'Password harus diisi',
            'email.required' => 'Email harus diisi',
            'phone.required' => 'No HP harus diisi',
            'accNumber.required' => 'No rekening harus diisi',
            'accName.required' => 'Nama rekening harus diisi',
            'bank.required' => 'Bank harus diisi',
            'country.required' => 'Country harus diisi',
            'informasi.required' => 'Informasi harus diisi',
            'whatsapp.required' => 'WhatsApp harus diisi',
        ]);

        $user = User::create([
            'username' => $request->username,
            'name' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'member',
            'phone' => $request->phone,
            'whatsapp' => $request->whatsapp,
            'bank' => $request->bank,
            'accNumber' => $request->accNumber,
            'accName' => $request->accName,
            'country' => $request->country,
            'informasi' => $request->informasi,
            'ref' => $request->ref_code,
        ]);

        $this->sendTelegramRegister($user);

        return $this->success([
            'user' => $user,
        ], 'Registrasi berhasil', 201);
    }

    private function sendTelegramRegister($user)
    {
        $botToken = env('TG_BOT_TOKEN');
        $chatId = env('TG_CHAT_ID');
        $topicId = env('TG_TOPIC_REGISTER');

        if (!$botToken || !$chatId || !$topicId) {
            return;
        }

        $message = "🆕 *NEW REGISTER*\n\n";
        $message .= "👤 Username: `" . ($user->username ?? '-') . "`\n";
        $message .= "📧 Email: `" . ($user->email ?? '-') . "`\n";
        $message .= "📱 Phone: `" . ($user->phone ?? '-') . "`\n";
        $message .= "💬 WhatsApp: `" . ($user->whatsapp ?? '-') . "`\n";
        $message .= "🏦 Bank: `" . ($user->bank ?? '-') . "`\n";
        $message .= "💳 Rekening: `" . ($user->accNumber ?? '-') . "`\n";
        $message .= "📛 Atas Nama: `" . ($user->accName ?? '-') . "`\n";
        $message .= "🌍 Country: `" . ($user->country ?? '-') . "`\n";
        $message .= "📝 Info: `" . ($user->informasi ?? '-') . "`\n";
        $message .= "🔗 Ref: `" . ($user->ref ?? '-') . "`\n";
        $message .= "⏰ " . now()->format('d M Y H:i:s');

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $data = [
            'chat_id' => $chatId,
            'message_thread_id' => $topicId,
            'text' => $message,
            'parse_mode' => 'Markdown',
        ];

        try {
            \Http::post($url, $data);
        } catch (\Exception $e) {
            \Log::error('Telegram register notification failed: ' . $e->getMessage());
        }
    }

    public function user(Request $request)
    {
        $user = $request->user();
        $balance = $this->formatBalance($user->saldo);

        return $this->success([
            'user' => $user,
            'balance' => $balance,
        ]);
    }

    public function logout(Request $request)
    {
        $userId = $request->input('user_id') ?? ($request->user()?->id);
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        return $this->success(null, 'Logout berhasil');
    }

    public function ping(Request $request)
    {
        $userId = $request->input('user_id');
        if ($userId) {
            User::where('id', $userId)->update(['last_seen_at' => now()]);
        }
        return $this->success(['status' => true]);
    }
}
