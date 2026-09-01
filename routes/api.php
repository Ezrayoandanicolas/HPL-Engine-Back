<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\DepositController;
use App\Http\Controllers\Api\SlotController;
use App\Http\Controllers\Api\CasinoController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WalletController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/ping', [AuthController::class, 'ping']);
Route::post('/auth/logout', [AuthController::class, 'logout']);

// Public chat routes (no API key - session_token authenticates)
Route::post('/chat/create', [ChatController::class, 'createSession']);
Route::post('/chat/send', [ChatController::class, 'sendMessage']);
Route::get('/chat/messages/{token}', [ChatController::class, 'messages']);
Route::get('/chat/sse/{token}', [ChatController::class, 'sse']);
Route::post('/chat/typing', [ChatController::class, 'typing']);
Route::get('/chat/typing/status/{token}', [ChatController::class, 'typingStatus']);
Route::post('/chat/upload', [ChatController::class, 'upload']);
Route::post('/chat/rating', [ChatController::class, 'rating']);

// Public navigation menu
Route::get('/navigation-menu', function () {
    $menus = \App\Models\NavigationMenu::where('is_active', true)
        ->orderBy('sort_order')->get()->groupBy('category');
    return response()->json(['success' => true, 'data' => $menus]);
});

// Public QRIS payment webhooks (called by Saweria / Bayar.gg)
Route::post('/webhook/saweria', [App\Http\Controllers\Api\QrisController::class, 'webhookSaweria']);
Route::post('/webhook/bayar', [App\Http\Controllers\Api\QrisController::class, 'webhookBayar']);

// Telegram webhook for withdraw accept/reject buttons
Route::post('/webhook/telegram', [App\Http\Controllers\Api\TelegramWebhookController::class, 'handle']);

// Public game lists
    // Sports games (member list)
Route::get('/sports/games', function () {
    $games = \App\Models\Sport::where('status', 1)
        ->orderBy('provider_name')
        ->get()
        ->map(function ($g) {
            $g->image_url = trim($g->image_url ?? '');
            $g->img       = $g->image_url;
            $g->image     = $g->image_url;
            $g->images    = $g->image_url;
            $g->logo      = $g->image_url;
            $g->icon      = $g->image_url;
            $g->thumbnail = $g->image_url;
            $g->game_image = $g->image_url;
            $g->picture   = $g->image_url;
            return $g;
        });

    return response()->json($games);
});

Route::get('/public-games', function () {
    $provider = request('provider');
    $limit = request('limit', 20);
    $order = request('order', 'random');
    $query = \App\Models\Game::where('status', 1);
    if ($provider) {
        $query->where('game_provider', $provider);
    }
    if ($order === 'latest') {
        $query->orderByDesc('id');
    } else {
        $query->inRandomOrder();
    }
    $games = $query->limit($limit)->get();
    return response()->json(['success' => true, 'data' => $games]);
});

Route::get('/public-providers', function () {
    $providers = \App\Models\Game::where('status', 1)
        ->select('game_provider', 'provider')
        ->distinct()
        ->get()
        ->map(function($p) {
            $nav = \App\Models\NavigationMenu::where('title', $p->provider)->first();
            return [
                'code' => $p->game_provider,
                'name' => $p->provider,
                'image' => $nav ? $nav->image : '',
            ];
        });
    return response()->json(['success' => true, 'data' => $providers]);
});

// Page data routes (used by Frontend via API key)
Route::middleware(['api.key'])->group(function () {
    Route::get('/page/home', [PageController::class, 'home']);
    Route::get('/page/deposit', [PageController::class, 'deposit']);
    Route::get('/page/withdraw', [PageController::class, 'withdraw']);
    Route::get('/page/slots', [PageController::class, 'slots']);
    Route::get('/page/casino', [PageController::class, 'casino']);
    Route::get('/page/casino/{provider}', [PageController::class, 'game']);
    Route::get('/page/profile', [PageController::class, 'profile']);
    Route::get('/page/game/{provider}', [PageController::class, 'game']);

    // Legacy routes (for backward compatibility)
    Route::get('/home', [HomeController::class, 'index']);
    Route::get('/slots', [SlotController::class, 'index']);
    Route::get('/slots/providers', [SlotController::class, 'providers']);
    Route::get('/casino', [CasinoController::class, 'index']);
    Route::get('/casino/{provider}', [CasinoController::class, 'provider']);

    Route::get('/games/players', [AdminController::class, 'gamePlayers']);
    Route::get('/games', [AdminController::class, 'games']);

    Route::match(['GET', 'POST'], '/games/history', function (\Illuminate\Http\Request $r) {
        try {
            $extplayer = $r->input('extplayer', '');
            if (!$extplayer) {
                return response()->json(['status' => 'error', 'data' => [], 'msg' => 'Pilih user']);
            }
            $fiver = new \App\Http\API\fiver();

            $fiver = new \App\Http\API\fiver();
            $extplayer = $r->input('extplayer', '');
            $gameType = strtolower($r->input('game_type', 'slot'));
            $dateStart = $r->input('date_start', date('Y-m-d')) . ' 00:00:00';
            $dateEnd = $r->input('date_end', date('Y-m-d')) . ' 23:59:00';
            $page = (int) $r->input('page', 0);
            $perPage = (int) $r->input('per_page', 100);

            \Illuminate\Support\Facades\Log::info('GAMES HISTORY CALL', ['extplayer' => $extplayer, 'gameType' => $gameType, 'dateStart' => $dateStart, 'dateEnd' => $dateEnd]);

            $raw = $fiver->historyPlay($extplayer, $gameType, $dateStart, $dateEnd, $page, $perPage);
            $decoded = json_decode($raw, true);

            \Illuminate\Support\Facades\Log::info('GAMES HISTORY RESPONSE', ['raw' => $raw]);

            $data = $decoded['data'] ?? ($decoded[$gameType] ?? []);
            if (!is_array($data)) $data = [];

            return response()->json([
                'status' => 'success',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('GAMES HISTORY ERROR', ['msg' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'data' => [], 'msg' => $e->getMessage()]);
        }
    });

    Route::get('/games/providers', function () {
        $providers = \App\Models\Game::whereNotNull('game_provider')
            ->distinct()
            ->get(['game_provider'])
            ->pluck('game_provider')
            ->sort()
            ->values()
            ->map(function ($code) {
                return [
                    'provider_code' => $code,
                    'provider_name' => str_replace('_', ' ', ucwords(strtolower($code))),
                ];
            });
        return response()->json(['success' => true, 'data' => $providers]);
    });

    Route::post('/games/in-game-history', function (\Illuminate\Http\Request $r) {
        $fiver = new \App\Http\API\fiver();
        $raw = $fiver->inGameHistory($r->input('user_code'), $r->input('provider_code'), $r->input('game_code'));
        $decoded = json_decode($raw, true);
        return response()->json([
            'status' => $decoded['status'] ?? 0,
            'url' => $decoded['history_url'] ?? null,
            'msg' => $decoded['msg'] ?? ''
        ]);
    });

    Route::get('/games/call-list', function (\Illuminate\Http\Request $r) {
        $fiver = new \App\Http\API\fiver();
        $raw = $fiver->callList($r->input('provider', ''), $r->input('game_code', ''));
        $decoded = json_decode($raw, true);
        if (!$decoded) {
            return response()->json(['status' => 'error', 'msg' => 'Respon provider tidak valid.', 'data' => []]);
        }
        $success = isset($decoded['status']) && (int) $decoded['status'] === 1;
        return response()->json([
            'status' => $success ? 'success' : 'error',
            'msg' => $decoded['msg'] ?? ($success ? 'OK' : 'Gagal'),
            'data' => $decoded['data'] ?? $decoded,
        ]);
    });
    Route::post('/games/call-apply', function (\Illuminate\Http\Request $r) {
        $fiver = new \App\Http\API\fiver();
        $raw = $fiver->callApply(
            $r->input('provider', ''),
            $r->input('game_code', ''),
            $r->input('username', ''),
            $r->input('win_amount', 0),
            $r->input('call_type', 'normal')
        );
        $decoded = json_decode($raw, true);
        if (!$decoded) {
            return response()->json(['status' => 'error', 'msg' => 'Respon provider tidak valid.', 'data' => []]);
        }
        $success = isset($decoded['status']) && (int) $decoded['status'] === 1;
        return response()->json([
            'status' => $success ? 'success' : 'error',
            'msg' => $decoded['msg'] ?? ($success ? 'OK' : 'Gagal'),
            'data' => $decoded['data'] ?? $decoded,
        ]);
    });
    Route::get('/games/{id}', [AdminController::class, 'gameShow']);
    Route::get('/promotions', [AdminController::class, 'promotions']);

    Route::get('/deposits', [DepositController::class, 'index']);
    Route::post('/deposits', [DepositController::class, 'store']);

    Route::post('/qris/deposit', [App\Http\Controllers\Api\QrisController::class, 'create']);
    Route::post('/qris/check', [App\Http\Controllers\Api\QrisController::class, 'check']);

    Route::post('/withdraws', function (\Illuminate\Http\Request $request) {        $userId = $request->input('user_id');
        $user = $userId ? \App\Models\User::find($userId) : null;
        if (!$user) return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);

        $data = $request->validate([
            'bankMember' => 'required|max:255',
            'amount' => 'required|numeric|min:1',
        ]);

        $amount = $data['amount'] * 1000;

        $setting = \App\Models\Setting::first();
        $minWithdraw = $setting->min_withdraw ?? 50000;
        $maxWithdraw = $setting->max_withdraw ?? 5000000;

        if ($amount < $minWithdraw) {
            return response()->json(['success' => false, 'message' => 'Minimal withdraw Rp ' . number_format($minWithdraw, 0, ',', '.')]);
        }

        if ($amount > $maxWithdraw) {
            return response()->json(['success' => false, 'message' => 'Maksimal withdraw Rp ' . number_format($maxWithdraw, 0, ',', '.')]);
        }

        $pending = \App\Models\Transaksi::where('user_id', $user->id)
            ->where('status_id', 1)->where('type', 2)->first();
        if ($pending) {
            return response()->json(['success' => false, 'message' => 'Menunggu withdraw sebelumnya']);
        }

        if ($user->saldo < $amount) {
            return response()->json(['success' => false, 'message' => 'Saldo tidak mencukupi']);
        }

        $user->decrement('saldo', $amount);

        $transaksi = \App\Models\Transaksi::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'type' => 2,
            'status_id' => 1,
            'description' => $data['bankMember'],
            'notes' => 'unread',
        ]);

        // Telegram notification
        try {
            app(\App\Services\TelegramNotifService::class)->sendWithdrawPending($transaksi, $user);
        } catch (\Exception $e) {}

        return response()->json(['success' => true, 'data' => $transaksi, 'message' => 'Withdraw berhasil']);
    });

    Route::get('/wallet/balance', [WalletController::class, 'balance']);
    Route::post('/wallet/transfer', [WalletController::class, 'transfer']);

    Route::get('/transactions/deposits-completed', [TransactionController::class, 'depositsCompleted']);
    Route::get('/transactions/withdraws-completed', [TransactionController::class, 'withdrawsCompleted']);
    Route::get('/transactions/unread-count', [TransactionController::class, 'unreadCount']);

    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Member transaction endpoints
    Route::get('/deposit/history', function (\Illuminate\Http\Request $r) {
        $user = $r->input('user_id') ? \App\Models\User::find($r->user_id) : null;
        if (!$user) return response()->json([]);
        $data = \App\Models\Transaksi::where('user_id', $user->id)->where('type', 1)->latest()->get();
        return response()->json($data);
    });
    Route::get('/deposit/today', function (\Illuminate\Http\Request $r) {
        $user = $r->input('user_id') ? \App\Models\User::find($r->user_id) : null;
        if (!$user) return response()->json([]);
        $data = \App\Models\Transaksi::where('user_id', $user->id)->where('type', 1)->whereDate('created_at', today())->get();
        return response()->json($data);
    });
    Route::get('/withdraw/history', function (\Illuminate\Http\Request $r) {
        $user = $r->input('user_id') ? \App\Models\User::find($r->user_id) : null;
        if (!$user) return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        $data = \App\Models\Transaksi::where('user_id', $user->id)->where('type', 2)->latest()->get();
        return response()->json(['success' => true, 'data' => $data]);
    });
    Route::get('/withdraw/today', function (\Illuminate\Http\Request $r) {
        $user = $r->input('user_id') ? \App\Models\User::find($r->user_id) : null;
        if (!$user) return response()->json([]);
        $data = \App\Models\Transaksi::where('user_id', $user->id)->where('type', 2)->whereDate('created_at', today())->get();
        return response()->json($data);
    });
    Route::get('/transactions/today-with-status', function (\Illuminate\Http\Request $r) {
        $user = $r->input('user_id') ? \App\Models\User::find($r->user_id) : null;
        if (!$user) return response()->json(['transaksi' => [], 'damount' => 0, 'wamount' => 0]);
        $dep = \App\Models\Transaksi::where('user_id', $user->id)->where('type', 1)->latest()->first();
        $wd = \App\Models\Transaksi::where('user_id', $user->id)->where('type', 2)->latest()->first();
        $transaksi = \App\Models\Transaksi::where('user_id', $user->id)->orderByDesc('created_at')->get();
        return response()->json(['transaksi' => $transaksi, 'damount' => $dep->amount ?? 0, 'wamount' => $wd->amount ?? 0]);
    });
    Route::post('/transactions/mark-read-single', function (\Illuminate\Http\Request $r) {
        $id = $r->input('id');
        if ($id) \App\Models\Transaksi::where('id', $id)->update(['notes' => 'read']);
        return response()->json(['success' => true]);
    });
    Route::get('/transactions/show-with-summary', function (\Illuminate\Http\Request $r) {
        $id = $r->input('id');
        $user = $r->input('user_id') ? \App\Models\User::find($r->user_id) : null;
        $tx = $id ? \App\Models\Transaksi::find($id) : null;
        if (!$user || !$tx) return response()->json(['transaksi' => [], 'damount' => 0, 'wamount' => 0]);
        $dep = \App\Models\Transaksi::where('user_id', $user->id)->where('type', 1)->latest()->first();
        $wd = \App\Models\Transaksi::where('user_id', $user->id)->where('type', 2)->latest()->first();
        return response()->json(['transaksi' => $tx, 'damount' => $dep->amount ?? 0, 'wamount' => $wd->amount ?? 0]);
    });
    Route::get('/user/by-ref', function (\Illuminate\Http\Request $r) {
        $ref = $r->input('ref');
        $user = $ref ? \App\Models\User::where('ref', $ref)->first() : null;
        return response()->json($user ? ['user' => $user] : ['user' => null]);
    });
    Route::get('/rekening', function (\Illuminate\Http\Request $r) {
        $user = $r->input('user_id') ? \App\Models\User::find($r->user_id) : null;
        if (!$user) return response()->json(['success' => false, 'data' => ['rekening' => null]]);
        $rek = \App\Models\Rekening::where('user_id', $user->id)->latest()->first();
        return response()->json(['success' => true, 'data' => ['rekening' => $rek]]);
    });

    // Member API routes
    Route::get('/banks', [App\Http\Controllers\Api\MemberApiController::class, 'banks']);
    Route::post('/bonus/claim', [App\Http\Controllers\Api\MemberApiController::class, 'claimBonus']);
    Route::get('/bonus/claimed-ids', [App\Http\Controllers\Api\MemberApiController::class, 'claimedPromotionIds']);
    Route::get('/bonus/history-klaims', [App\Http\Controllers\Api\MemberApiController::class, 'historyKlaims']);
    Route::get('/vouchers/available', [App\Http\Controllers\Api\MemberApiController::class, 'availableVouchers']);
    Route::post('/loyalitas/claim-voucher', [App\Http\Controllers\Api\MemberApiController::class, 'claimVoucher']);
    Route::post('/loyalitas/tarik', [App\Http\Controllers\Api\MemberApiController::class, 'tarik']);
    Route::get('/referral/verifikasi', [App\Http\Controllers\Api\MemberApiController::class, 'verifikasi']);
    Route::post('/referral/submit-verification', [App\Http\Controllers\Api\MemberApiController::class, 'submitVerification']);
    Route::get('/referral/data', [App\Http\Controllers\Api\MemberApiController::class, 'referralData']);
    Route::get('/referral/details', [App\Http\Controllers\Api\MemberApiController::class, 'referralDetails']);
    Route::get('/turnover/24h', [App\Http\Controllers\Api\MemberApiController::class, 'turnover24h']);
    Route::get('/turnover/history', [App\Http\Controllers\Api\MemberApiController::class, 'turnoverHistory']);
    Route::get('/transactions/today', [App\Http\Controllers\Api\MemberApiController::class, 'transactionsToday']);
    Route::post('/transactions/mark-read', [App\Http\Controllers\Api\MemberApiController::class, 'markRead']);
    Route::get('/messages', [App\Http\Controllers\Api\MemberApiController::class, 'adminMessages']);
    Route::get('/claims/notifications', [App\Http\Controllers\Api\MemberApiController::class, 'claimNotifications']);
    Route::post('/profile/update', [App\Http\Controllers\Api\MemberApiController::class, 'profileUpdate']);
    Route::post('/profile/change-password', [App\Http\Controllers\Api\MemberApiController::class, 'changePassword']);
    Route::post('/home/update-exp', [App\Http\Controllers\Api\HomeController::class, 'updateExpPlayer']);

    // Admin routes
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/dashboard/users', [AdminController::class, 'dashboardUsers']);
        Route::get('/dashboard/today-deposits', [AdminController::class, 'dashboardTodayDeposits']);
        Route::get('/dashboard/today-withdraws', [AdminController::class, 'dashboardTodayWithdraws']);

        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users/find-by-username', [AdminController::class, 'userFindByUsername']);
        Route::get('/users/{id}', [AdminController::class, 'userShow']);
        Route::post('/users', [AdminController::class, 'userStore']);
        Route::put('/users/{id}', [AdminController::class, 'userUpdate']);
        Route::post('/users/{id}/inject-saldo', [AdminController::class, 'userInjectSaldo']);
        Route::delete('/users/{id}', [AdminController::class, 'userDestroy']);

        Route::get('/deposits', [AdminController::class, 'deposits']);
        Route::get('/deposits-new', [AdminController::class, 'depositsNew']);
        Route::get('/deposits-status-check', [AdminController::class, 'depositsStatusCheck']);
        Route::post('/deposits/{id}/update', [AdminController::class, 'depositUpdate']);
        Route::get('/withdraws', [AdminController::class, 'withdraws']);
        Route::get('/withdraws-new', [AdminController::class, 'withdrawsNew']);
        Route::post('/withdraws/{id}/update', [AdminController::class, 'withdrawUpdate']);

        Route::get('/deposites', [AdminController::class, 'deposites']);
        Route::get('/deposites/{id}', [AdminController::class, 'depositeShow']);
        Route::put('/deposites/{id}', [AdminController::class, 'depositeUpdate']);
        Route::delete('/deposites/{id}', [AdminController::class, 'depositeDestroy']);

        Route::get('/withdraws-old', [AdminController::class, 'withdrawsOld']);
        Route::get('/withdraws-old/{id}', [AdminController::class, 'withdrawOldShow']);
        Route::delete('/withdraws-old/{id}', [AdminController::class, 'withdrawOldDestroy']);

        Route::get('/transactions/deposits-all', [TransactionController::class, 'depositsAll']);
        Route::get('/transactions/withdraws-all', [TransactionController::class, 'withdrawsAll']);

        Route::get('/banners', [AdminController::class, 'banners']);
        Route::post('/banners', [AdminController::class, 'bannerStore']);
        Route::get('/banners/{id}', [AdminController::class, 'bannerShow']);
        Route::post('/banners/{id}', [AdminController::class, 'bannerUpdate']);
        Route::delete('/banners/{id}', [AdminController::class, 'bannerDestroy']);

        Route::get('/banks', [AdminController::class, 'banks']);
        Route::post('/banks', [AdminController::class, 'bankStore']);
        Route::post('/banks/{id}', [AdminController::class, 'bankUpdate']);
        Route::delete('/banks/{id}', [AdminController::class, 'bankDestroy']);

        Route::get('/qris-settings', [App\Http\Controllers\Api\QrisController::class, 'adminSettings']);
        Route::post('/qris-settings', [App\Http\Controllers\Api\QrisController::class, 'adminSettingsSave']);

        Route::get('/promotions', [AdminController::class, 'promotions']);
        Route::post('/promotions', [AdminController::class, 'promotionStore']);
        Route::get('/promotions/{id}', [AdminController::class, 'promotionShow']);
        Route::match(['PUT', 'POST'], '/promotions/{id}', [AdminController::class, 'promotionUpdate']);
        Route::delete('/promotions/{id}', [AdminController::class, 'promotionDestroy']);

        Route::get('/bonuses', [AdminController::class, 'bonuses']);
        Route::post('/bonuses', [AdminController::class, 'bonusStore']);
        Route::get('/bonuses/{id}', [AdminController::class, 'bonusShow']);
        Route::post('/bonuses/{id}', [AdminController::class, 'bonusUpdate']);
        Route::delete('/bonuses/{id}', [AdminController::class, 'bonusDestroy']);
        Route::post('/bonuses/{id}/toggle-status', [AdminController::class, 'bonusToggleStatus']);

        Route::get('/vouchers', [AdminController::class, 'vouchers']);
        Route::post('/vouchers', [AdminController::class, 'voucherStore']);
        Route::delete('/vouchers/{id}', [AdminController::class, 'voucherDestroy']);

        Route::get('/games', [AdminController::class, 'games']);
    Route::get('/games/{id}', [AdminController::class, 'gameShow']);

        Route::put('/games/{id}', [AdminController::class, 'gameUpdate']);
        Route::get('/games/search-by-provider', [AdminController::class, 'gameSearchByProvider']);
        Route::get('/providers', [AdminController::class, 'providers']);

        Route::get('/statuses', [AdminController::class, 'statuses']);
        Route::post('/statuses', [AdminController::class, 'statusStore']);
        Route::get('/statuses/{id}', [AdminController::class, 'statusShow']);
        Route::put('/statuses/{id}', [AdminController::class, 'statusUpdate']);
        Route::delete('/statuses/{id}', [AdminController::class, 'statusDestroy']);

        Route::get('/settings', [AdminController::class, 'settings']);
        Route::post('/settings', [AdminController::class, 'settingStore']);

        Route::get('/statistics', [AdminController::class, 'statistics']);

        Route::get('/verifications', [AdminController::class, 'verifications']);
        Route::get('/verifications-new', [AdminController::class, 'verificationsNew']);
        Route::post('/verifications/{id}/update', [AdminController::class, 'verificationUpdate']);

        Route::get('/networks', [AdminController::class, 'networks']);

        Route::get('/laporans', [AdminController::class, 'laporan']);
        Route::post('/laporans', [AdminController::class, 'laporanStore']);

        Route::get('/rekening', [AdminController::class, 'rekeningUser']);
        Route::post('/rekening', [AdminController::class, 'rekeningStore']);

        Route::get('/colors', [AdminController::class, 'colors']);
        Route::post('/colors', [AdminController::class, 'colorStore']);
        Route::delete('/colors/{id}', [AdminController::class, 'colorDestroy']);

        Route::get('/provider-balances', [AdminController::class, 'providerBalances']);
        Route::get('/ggr-users-balance', function () {
            $fiver = new \App\Http\API\fiver();
            $raw = $fiver->allUsersBalance();
            $decoded = json_decode($raw, true);
            $users = $decoded['user_list'] ?? [];
            usort($users, function ($a, $b) { return ($b['balance'] ?? 0) <=> ($a['balance'] ?? 0); });
            return response()->json(['success' => true, 'data' => $users]);
        });

        // Admin chat routes
        Route::get('/chat/sessions', [ChatController::class, 'sessions']);
        Route::get('/chat/messages/{id}', [ChatController::class, 'adminMessages']);
        Route::post('/chat/reply/{id}', [ChatController::class, 'reply']);
        Route::post('/chat/close/{id}', [ChatController::class, 'close']);
        Route::post('/chat/typing/{id}', [ChatController::class, 'adminTyping']);
        Route::get('/chat/typing/status/{id}', [ChatController::class, 'adminTypingStatus']);
        Route::post('/chat/assign', [ChatController::class, 'assign']);
        Route::post('/chat/upload/{id}', [ChatController::class, 'adminUpload']);
        Route::get('/chat/sse/{id}', [ChatController::class, 'adminSse']);
        Route::get('/chat/sessions-count-sse', [ChatController::class, 'sessionsCountSse']);
        Route::get('/chat/unread-count', [ChatController::class, 'unreadCount']);
        Route::get('/chat/open-count', [ChatController::class, 'openCount']);

        // Provider transactions (GGR wallet logs)
        Route::get('/provider-transactions', function (\Illuminate\Http\Request $r) {
            $query = \App\Models\ProviderTransaction::query();
            if ($r->filled('search')) {
                $q = $r->search;
                $query->where(function ($sub) use ($q) {
                    $sub->where('username', 'like', "%{$q}%")
                        ->orWhere('agent_sign', 'like', "%{$q}%");
                });
            }
            if ($r->filled('status')) {
                $query->where('status', $r->status);
            }
            $transactions = $query->latest()->paginate(20);
            return response()->json([
                'success' => true,
                'data' => $transactions->items(),
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                    'last_page' => $transactions->lastPage(),
                ],
                'counts' => [
                    'success' => \App\Models\ProviderTransaction::where('status', 'success')->count(),
                    'failed' => \App\Models\ProviderTransaction::where('status', 'failed')->count(),
                ],
            ]);
        });

        Route::get('/provider-transactions/{id}', function (\Illuminate\Http\Request $r, $id) {
            $tx = \App\Models\ProviderTransaction::find($id);
            if (!$tx) return response()->json(['success' => false, 'message' => 'Not found'], 404);
            return response()->json(['success' => true, 'data' => $tx]);
        });

        // Fiver admin actions (moved to backend so logging hits the shared DB only here)
        Route::post('/fiver/reset-user', function (\Illuminate\Http\Request $r) {
            $r->validate(['username' => 'required|string']);
            $fiver = new \App\Http\API\fiver();
            $raw = $fiver->resetUserBalance($r->username);
            $decoded = json_decode($raw, true);
            $success = $decoded && isset($decoded['status']) && (int) $decoded['status'] === 1;
            \App\Models\ProviderTransaction::create([
                'agent_sign' => strtoupper(substr(md5(uniqid($r->username . '_user_withdraw_reset_', true)), 0, 16)),
                'username' => $r->username,
                'amount' => 0,
                'type' => 'user_withdraw_reset',
                'status' => $success ? 'success' : 'failed',
                'message' => $decoded['msg'] ?? null,
                'response_raw' => is_string($raw) ? $raw : json_encode($raw),
            ]);
            return response()->json([
                'success' => $success,
                'message' => $decoded['msg'] ?? ($success ? 'SUCCESS' : 'Gagal'),
                'data' => $decoded,
            ]);
        });

        Route::post('/fiver/reset-balance', function () {
            $fiver = new \App\Http\API\fiver();
            $raw = $fiver->resetBalance();
            $decoded = json_decode($raw, true);
            $success = $decoded && isset($decoded['status']) && (int) $decoded['status'] === 1;
            \App\Models\ProviderTransaction::create([
                'agent_sign' => strtoupper(substr(md5(uniqid('ALL_USERS_user_withdraw_reset_', true)), 0, 16)),
                'username' => 'ALL_USERS',
                'amount' => 0,
                'type' => 'user_withdraw_reset',
                'status' => $success ? 'success' : 'failed',
                'message' => $decoded['msg'] ?? null,
                'response_raw' => is_string($raw) ? $raw : json_encode($raw),
            ]);
            return response()->json([
                'success' => $success,
                'message' => $decoded['msg'] ?? ($success ? 'SUCCESS' : 'Gagal'),
                'data' => $decoded,
            ]);
        });

        Route::post('/fiver/check-status', function (\Illuminate\Http\Request $r) {
            $r->validate(['username' => 'required|string', 'agent_sign' => 'required|string']);
            $fiver = new \App\Http\API\fiver();
            $raw = $fiver->transferStatus($r->username, $r->agent_sign);
            $decoded = json_decode($raw, true);
            $success = $decoded && isset($decoded['status']) && (int) $decoded['status'] === 1;
            return response()->json([
                'success' => $success,
                'message' => $decoded['msg'] ?? ($success ? 'SUCCESS' : 'Gagal'),
                'data' => $decoded,
            ]);
        });

        Route::get('/exa-providers', function () {
            try {
                $providers = \DB::table('exa_providers')
                    ->select('provider_code', 'provider_name')
                    ->orderBy('provider_name')
                    ->get();
                return response()->json(['success' => true, 'data' => $providers]);
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'data' => []]);
            }
        });

        // Navigation menu routes
        Route::get('/navigation-menus', [AdminController::class, 'navigationMenus']);
        Route::post('/navigation-menus', [AdminController::class, 'navigationMenuStore']);
        Route::put('/navigation-menus/{id}', [AdminController::class, 'navigationMenuUpdate']);
        Route::delete('/navigation-menus/{id}', [AdminController::class, 'navigationMenuDestroy']);
        Route::get('/navigation-menu-categories', [AdminController::class, 'navigationMenuCategories']);
        Route::post('/sync-ggr-providers', [AdminController::class, 'syncGGRProviders']);
        Route::post('/sync-ggr-games', [AdminController::class, 'syncGGRGames']);
        Route::post('/sync-all-ggr-games', [AdminController::class, 'syncAllGGRGames']);

        Route::get('/game-provider', [AdminController::class, 'getGameProvider']);
        Route::post('/game-provider', [AdminController::class, 'setGameProvider']);
        Route::post('/sync-dc-providers', [AdminController::class, 'syncDCProviders']);
        Route::post('/sync-dc-games', [AdminController::class, 'syncDCGames']);
        Route::post('/sync-all-dc-games', [AdminController::class, 'syncAllDCGames']);

        Route::post('/sync-xapi-games', [AdminController::class, 'syncXapiGames']);

        Route::get('/messages', [AdminController::class, 'adminMessages']);
        Route::post('/messages', [AdminController::class, 'adminMessageStore']);
        Route::post('/messages/{id}/read', [AdminController::class, 'adminMessageRead']);
        Route::delete('/messages/{id}', [AdminController::class, 'adminMessageDestroy']);

        Route::get('/activity-logs', function (\Illuminate\Http\Request $r) {
            $query = \App\Models\ActivityLog::latest();
            if ($r->filled('action')) $query->where('action', $r->action);
            if ($r->filled('admin_id')) $query->where('admin_id', $r->admin_id);
            if ($r->filled('date_from')) $query->whereDate('created_at', '>=', $r->date_from);
            if ($r->filled('date_to')) $query->whereDate('created_at', '<=', $r->date_to);
            return response()->json(['success' => true, 'data' => $query->paginate(50)]);
        });
    });

    // sync-balance tidak diperlukan di seamless mode
    // Balance dikelola oleh SeamlessApiController callback
});

// Authenticated routes (user token)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::post('/home/claim-daily-reward', [HomeController::class, 'claimDailyReward']);
    Route::post('/home/reset-reward', [HomeController::class, 'resetReward']);
    Route::post('/home/update-reward', [HomeController::class, 'updateReward']);
    Route::get('/home/player-progress', [HomeController::class, 'getPlayerProgress']);
    Route::post('/home/update-exp-player', [HomeController::class, 'updateExpPlayer']);
    Route::get('/home/user-badge', [HomeController::class, 'getUserBadge']);
});

// Public route - uses user_id from ApiService
Route::post('/user/update-aas-code', function (\Illuminate\Http\Request $request) {
    $userId = $request->input('user_id');
    $aasCode = $request->input('aas_user_code');
    if (!$userId || !$aasCode) {
        return response()->json(['success' => false, 'message' => 'Invalid request']);
    }
    $user = \App\Models\User::find($userId);
    if (!$user) {
        return response()->json(['success' => false, 'message' => 'User not found']);
    }
    $user->aas_user_code = $aasCode;
    $user->save();
    return response()->json(['success' => true]);
});
