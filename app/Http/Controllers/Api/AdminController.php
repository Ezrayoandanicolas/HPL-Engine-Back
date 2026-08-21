<?php

namespace App\Http\Controllers\Api;

use App\Models\Bank;
use App\Models\Banner;
use App\Models\Deposite;
use App\Models\Game;
use App\Models\Laporan;
use App\Models\Network;
use App\Models\Promotion;
use App\Models\Provider;
use App\Models\Rekening;
use App\Models\Setting;
use App\Models\Sport;
use App\Models\Casino;
use App\Models\Status;
use App\Models\Transaksi;
use App\Models\User;
use App\Models\Verifikasi;
use App\Models\Voucher;
use App\Models\Withdraw;
use App\Models\QrisAccount;
use App\Services\SaweriaService;
use App\Services\BayarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

class AdminController extends BaseApiController
{
    // ==================== DASHBOARD ====================
    public function dashboard()
    {
        $totalDeposit = Transaksi::where('status_id', 2)->where('type', 1)->sum('amount');
        $totalWithdraw = Transaksi::where('status_id', 2)->where('type', 2)->sum('amount');
        $totalUser = User::count();
        $pendingDeposit = Transaksi::where('type', 1)->where('status_id', 1)->count();
        $pendingWithdraw = Transaksi::where('type', 2)->where('status_id', 1)->count();
        $totalGame = Game::where('game_category', 'SL')->count();

        return $this->success(compact(
            'totalDeposit', 'totalWithdraw', 'totalUser',
            'pendingDeposit', 'pendingWithdraw', 'totalGame'
        ));
    }

    public function dashboardUsers(Request $request)
    {
        $search = $request->input('search');
        $query = User::query();
        if ($search) {
            $query->where('username', 'like', "%$search%");
        }
        return $this->success([
            'users' => $query->paginate(10),
        ]);
    }

    public function dashboardTodayDeposits()
    {
        $data = Transaksi::with('user')->whereDate('created_at', Carbon::today())
            ->where('type', 1)->where('status_id', 1)->get();
        return response()->json($data);
    }

    public function dashboardTodayWithdraws()
    {
        $data = Transaksi::with('user')->whereDate('created_at', Carbon::today())
            ->where('type', 2)->where('status_id', 1)->get();
        return response()->json($data);
    }

    // ==================== USERS ====================
    public function users(Request $request)
    {
        $search = $request->input('search');
        $role = $request->input('role');
        $query = User::query();
        if ($search) {
            $query->where('username', 'like', "%$search%");
        }
        if ($role) {
            $query->where('role', $role);
        }
        return $this->success([
            'users' => $query->paginate(20),
        ]);
    }

    public function userShow($id)
    {
        $user = User::findOrFail($id);
        return $this->success(['user' => $user]);
    }

    public function userFindByUsername(Request $request)
    {
        $user = User::where('username', $request->username)->first();
        if (!$user) {
            return $this->error('User not found', 404);
        }
        return $this->success(['user' => $user]);
    }

    public function userStore(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:users',
            'password' => 'required|string|min:8',
            'email' => 'required|email|max:255|unique:users',
            'phone' => 'required|string|max:20',
            'ref' => 'nullable|string|max:50',
            'accName' => 'required|string|max:255',
            'bank' => 'required|string|max:255',
            'accNumber' => 'required|string|max:50',
        ]);

        $user = User::create([
            'username' => $validated['username'],
            'name' => $validated['username'],
            'password' => Hash::make($validated['password']),
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'ref' => $validated['ref'] ?? null,
            'accName' => $validated['accName'],
            'bank' => $validated['bank'],
            'accNumber' => $validated['accNumber'],
            'role' => 'member',
        ]);

        return $this->success(['user' => $user], 'User created', 201);
    }

    public function userUpdate(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $validated = $request->validate([
            'username' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'ref' => 'nullable|string|max:50',
            'role' => 'nullable|string|in:member,admin,cashier',
            'accName' => 'required|string|max:255',
            'bank' => 'required|string|max:255',
            'accNumber' => 'required|string|max:50',
            'saldo' => 'nullable|numeric',
            'password' => 'nullable|string|min:8',
        ]);

        $user->fill($validated);
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        $user->save();

        return $this->success(['user' => $user], 'User updated');
    }

    public function userDestroy($id)
    {
        User::destroy($id);
        return $this->success(null, 'User deleted');
    }

    public function userInjectSaldo(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'saldo' => 'required|numeric|min:0',
            'action' => 'required|in:add,subtract',
        ]);

        $amount = intval($request->saldo);

        if ($request->action == 'add') {
            app(\App\Services\WalletService::class)->creditBalance($user, $amount);
        } else {
            if ($user->saldo < $amount) {
                return $this->error('Saldo user tidak mencukupi');
            }
            app(\App\Services\WalletService::class)->debitBalance($user, $amount);
        }

        return $this->success(['user' => $user->fresh()], 'Saldo berhasil diperbarui');
    }

    // ==================== TRANSACTIONS ====================
    private function transactionsQuery($type, $statusId = null)
    {
        $query = Transaksi::with('user')->where('type', $type);
        if ($statusId !== null) {
            $query->where('status_id', $statusId);
        }
        return $query->orderBy('created_at', 'DESC');
    }

    public function deposits(Request $request)
    {
        $statusId = $request->input('status_id', 1);
        $query = $this->transactionsQuery(1, $statusId);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('username', 'like', "%$search%");
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $data = $query->paginate(20);
        return $this->success(['transactions' => $data]);
    }

    public function depositsNew(Request $request)
    {
        $sinceId = $request->input('since_id', 0);
        $statusId = $request->input('status_id', 1);

        $query = Transaksi::with('user')->where('type', 1)->where('id', '>', $sinceId);
        if ($statusId !== null) {
            $query->where('status_id', $statusId);
        }
        $data = $query->orderBy('id')->get();

        return $this->success(['transactions' => $data]);
    }

    public function depositsStatusCheck(Request $request)
    {
        $ids = array_filter(explode(',', $request->input('ids', '')));
        if (empty($ids)) {
            return $this->success(['removed_ids' => []]);
        }

        $qrisPending = Transaksi::where('type', 1)
            ->where('status_id', 1)
            ->where('payment_method', 'qris')
            ->whereIn('id', $ids)
            ->get();

        foreach ($qrisPending as $trx) {
            try {
                $gateway = $trx->payment_gateway;
                if ($gateway === 'saweria') {
                    $account = $trx->qris_account_id ? QrisAccount::find($trx->qris_account_id) : null;
                    $svc = $account ? SaweriaService::fromAccount($account->config ?: []) : app(SaweriaService::class);
                    $status = $svc->checkPaymentV2($trx->qris_trx_id);
                } else {
                    $account = $trx->qris_account_id ? QrisAccount::find($trx->qris_account_id) : null;
                    $svc = $account ? BayarService::fromAccount($account->config ?: []) : app(BayarService::class);
                    $result = $svc->checkPayment($trx->qris_trx_id);
                    $status = strtolower((string) ($result['status'] ?? 'pending'));
                    $status = $status === 'success' ? 'paid' : ($status === 'paid' ? 'paid' : 'pending');
                }
                if ($status === 'paid') {
                    app(QrisController::class)->autoApprove($trx);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('depositsStatusCheck QRIS check failed', [
                    'trx_id' => $trx->id, 'error' => $e->getMessage()
                ]);
            }
        }

        $stillPending = Transaksi::where('type', 1)
            ->where('status_id', 1)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->toArray();

        $removedIds = array_values(array_diff($ids, $stillPending));

        return $this->success(['removed_ids' => $removedIds]);
    }

    public function depositUpdate(Request $request, $id)
    {
        \Illuminate\Support\Facades\Log::info('depositUpdate called', ['id' => $id, 'action' => $request->action]);
        $transaksi = Transaksi::findOrFail($id);
        $user = User::findOrFail($transaksi->user_id);
        $amount = $transaksi->amount;

        $request->validate(['action' => 'required|in:acc,tolak']);

        if ($request->action == 'acc') {
            $transaksi->update(['status_id' => 2, 'notes' => 'unread']);
            app(\App\Services\WalletService::class)->creditBalance($user, $amount);
            \App\Models\ActivityLog::create([
                'admin_id' => $request->user_id, 'admin_name' => $request->user_id,
                'action' => 'deposit_approve', 'description' => "Approve deposit Rp{$amount} untuk {$user->username}",
                'target_type' => 'deposit', 'target_id' => $id, 'ip' => request()->ip(),
            ]);
            if ($amount >= 50000) {
                $user->increment('point_player', 2500);
            }
        } else {
            $transaksi->update(['status_id' => 3, 'notes' => 'unread']);
            \App\Models\ActivityLog::create([
                'admin_id' => $request->user_id, 'action' => 'deposit_reject',
                'description' => "Tolak deposit Rp{$amount} untuk {$user->username}",
                'target_type' => 'deposit', 'target_id' => $id, 'ip' => request()->ip(),
            ]);
        }

        return $this->success(null, 'Deposit updated');
    }

    public function withdraws(Request $request)
    {
        $statusId = $request->input('status_id', 1);
        $data = $this->transactionsQuery(2, $statusId)->paginate(20);
        return $this->success(['transactions' => $data]);
    }

    public function withdrawUpdate(Request $request, $id)
    {
        $transaksi = Transaksi::findOrFail($id);
        $user = User::findOrFail($transaksi->user_id);
        $amount = $transaksi->amount;
        $request->validate(['action' => 'required|in:acc,tolak']);

        if ($request->action == 'tolak') {
            $transaksi->update(['status_id' => 3, 'notes' => 'unread']);
            $user->increment('saldo', $amount);
            \App\Models\ActivityLog::create([
                'admin_id' => $request->user_id, 'action' => 'withdraw_reject',
                'description' => "Tolak withdraw Rp{$amount} untuk {$user->username}",
                'target_type' => 'withdraw', 'target_id' => $id, 'ip' => request()->ip(),
            ]);
        } else {
            $transaksi->update(['status_id' => 2, 'notes' => 'unread']);
            \App\Models\ActivityLog::create([
                'admin_id' => $request->user_id, 'action' => 'withdraw_approve',
                'description' => "Approve withdraw Rp{$amount} untuk {$user->username}",
                'target_type' => 'withdraw', 'target_id' => $id, 'ip' => request()->ip(),
            ]);
        }

        return $this->success(null, 'Withdraw updated');
    }

    public function withdrawsNew(Request $request)
    {
        $sinceId = $request->input('since_id', 0);
        $statusId = $request->input('status_id');

        $query = Transaksi::with('user')->where('type', 2)->where('id', '>', $sinceId);
        if ($statusId !== null) {
            $query->where('status_id', $statusId);
        }
        $data = $query->orderBy('id')->get();

        return $this->success(['transactions' => $data]);
    }

    // ==================== DEPOSITE (old model) ====================
    public function deposites(Request $request)
    {
        $query = Deposite::with('user')->orderBy('created_at', 'DESC');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('username', 'like', "%$search%");
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $this->success(['deposites' => $query->get()]);
    }

    public function depositeShow($id)
    {
        return $this->success(['deposite' => Deposite::findOrFail($id)]);
    }

    public function depositeUpdate(Request $request, $id)
    {
        $data = Deposite::findOrFail($id);
        if ($request->has('status_id')) {
            $data->update(['status_id' => $request->status_id]);
        }
        return $this->success(['deposite' => $data]);
    }

    public function depositeDestroy($id)
    {
        $data = Deposite::find($id);
        if ($data && $data->img) {
            Storage::delete($data->img);
        }
        Deposite::destroy($id);
        return $this->success(null, 'Deleted');
    }

    // ==================== WITHDRAW (old model) ====================
    public function withdrawsOld()
    {
        return $this->success(['withdraws' => Withdraw::orderBy('created_at', 'DESC')->get()]);
    }

    public function withdrawOldShow($id)
    {
        return $this->success(['withdraw' => Withdraw::findOrFail($id)]);
    }

    public function withdrawOldDestroy($id)
    {
        $data = Withdraw::find($id);
        if ($data && $data->img) {
            Storage::delete($data->img);
        }
        Withdraw::destroy($id);
        return $this->success(null, 'Deleted');
    }

    // ==================== BANNERS ====================
    public function banners()
    {
        return $this->success(['banners' => Banner::all()]);
    }

    public function bannerStore(Request $request)
    {
        $data = $request->validate([
            'img' => 'image|file|mimes:jpeg,png,webp,jpg|max:4048',
            'Judul' => 'required',
        ]);
        if ($request->file('img')) {
            $data['img'] = $request->file('img')->store('post-images');
        }
        $data['status'] = 1;
        $banner = Banner::create($data);
        return $this->success(['banner' => $banner], 'Banner created', 201);
    }

    public function bannerShow($id)
    {
        return $this->success(['banner' => Banner::findOrFail($id)]);
    }

    public function bannerUpdate(Request $request, $id)
    {
        $banner = Banner::findOrFail($id);
        if ($request->has('status')) {
            $banner->status = $request->status;
        }
        if ($request->has('Judul')) {
            $banner->Judul = $request->Judul;
        }
        if ($request->hasFile('img')) {
            $banner->img = $request->file('img')->store('post-images');
        }
        $banner->save();
        return $this->success(['banner' => $banner], 'Banner updated');
    }

    public function bannerDestroy($id)
    {
        $banner = Banner::find($id);
        if ($banner && $banner->img) {
            Storage::delete($banner->img);
        }
        Banner::destroy($id);
        return $this->success(null, 'Banner deleted');
    }

    // ==================== BANKS ====================
    public function banks()
    {
        return $this->success(['banks' => Bank::all()]);
    }

    public function bankStore(Request $request)
    {
        $data = $request->validate([
            'nama_bank' => 'required|max:255',
            'no_rek' => 'required|max:255',
            'nama_penerima' => 'required|max:255',
            'image_qr' => 'image|file|max:2024',
        ]);
        if ($request->file('image_qr')) {
            $data['image_qr'] = $request->file('image_qr')->store('post-images');
        }
        $data['status'] = 1;
        $bank = Bank::create($data);
        return $this->success(['bank' => $bank], 'Bank created', 201);
    }

    public function bankUpdate(Request $request, $id)
    {
        $bank = Bank::findOrFail($id);
        if ($request->has('status')) {
            $bank->status = $request->status;
        }
        if ($request->has('nama_bank')) {
            $bank->nama_bank = $request->nama_bank;
            $bank->nama_penerima = $request->nama_penerima;
            $bank->no_rek = $request->no_rek;
        }
        if ($request->hasFile('image_qr')) {
            $bank->image_qr = $request->file('image_qr')->store('post-images');
        }
        $bank->save();
        return $this->success(['bank' => $bank], 'Bank updated');
    }

    public function bankDestroy($id)
    {
        Bank::destroy($id);
        return $this->success(null, 'Bank deleted');
    }

    // ==================== PROMOTIONS ====================
    public function promotions()
    {
        return $this->success(['promotions' => Promotion::latest()->get()]);
    }

    public function promotionStore(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|max:255',
            'keterangan' => 'required|max:255',
            'bonus' => 'required',
            'jenis_pemberian' => 'required|max:255',
            'jenis_promosi' => 'required|max:25',
            'min_deposite' => 'required',
            'max_deposite' => 'required',
            'tanggal_mulai' => 'required',
            'tanggal_akhir' => 'required',
            'turnover' => 'required',
            'img' => 'image|file|mimes:jpeg,png,webp,jpg|max:5050',
            'body' => 'required',
        ]);
        if ($request->file('img')) {
            $data['img'] = $request->file('img')->store('post-images');
        }
        $data['user_id'] = $request->user_id ?? 1;
        $data['status'] = 1;
        $promotion = Promotion::create($data);
        return $this->success(['promotion' => $promotion], 'Promotion created', 201);
    }

    public function promotionShow($id)
    {
        return $this->success(['promotion' => Promotion::findOrFail($id)]);
    }

    public function promotionUpdate(Request $request, $id)
    {
        $promotion = Promotion::findOrFail($id);
        $data = $request->except('_token', '_method');
        if ($request->hasFile('img')) {
            $data['img'] = $request->file('img')->store('post-images');
        }
        $promotion->update($data);
        return $this->success(['promotion' => $promotion], 'Promotion updated');
    }

    public function promotionDestroy($id)
    {
        $promotion = Promotion::find($id);
        if ($promotion && $promotion->img) {
            Storage::delete($promotion->img);
        }
        Promotion::destroy($id);
        return $this->success(null, 'Promotion deleted');
    }

    public function bonuses(Request $request)
    {
        $query = Promotion::orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%$s%")
                  ->orWhere('keterangan', 'like', "%$s%");
            });
        }

        $bonuses = $query->get();
        return $this->success($bonuses);
    }

    public function bonusShow($id)
    {
        $promotion = Promotion::findOrFail($id);
        return $this->success(['bonus' => $promotion]);
    }

    public function bonusStore(Request $request)
    {
        $data = $request->validate([
            'judul' => 'required|string|max:255',
            'keterangan' => 'required|string|max:255',
            'nominal' => 'required|numeric',
        ]);
        $promotion = Promotion::create([
            'title' => $data['judul'],
            'keterangan' => $data['keterangan'],
            'bonus' => $data['nominal'],
            'status' => 1,
        ]);
        return $this->success(['bonus' => $promotion], 'Bonus created');
    }

    public function bonusUpdate(Request $request, $id)
    {
        $promotion = Promotion::findOrFail($id);
        $data = $request->validate([
            'judul' => 'required|string|max:255',
            'keterangan' => 'required|string|max:255',
            'nominal' => 'required|numeric',
        ]);
        $promotion->update([
            'title' => $data['judul'],
            'keterangan' => $data['keterangan'],
            'bonus' => $data['nominal'],
        ]);
        if ($request->has('status')) {
            $promotion->update(['status' => $request->status]);
        }
        return $this->success(['bonus' => $promotion], 'Bonus updated');
    }

    public function bonusDestroy($id)
    {
        Promotion::destroy($id);
        return $this->success(null, 'Bonus deleted');
    }

    public function bonusToggleStatus($id)
    {
        $promotion = Promotion::findOrFail($id);
        $promotion->update(['status' => $promotion->status == 1 ? 2 : 1]);
        return $this->success(['status' => $promotion->status], 'Status updated');
    }

    // ==================== VOUCHERS ====================
    public function vouchers(Request $request)
    {
        $query = Voucher::query();

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $this->success(['vouchers' => $query->get()]);
    }

    public function voucherStore(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|min:8|max:100',
            'exp' => 'required|min:4|max:100',
            'nominal' => 'required|min:4|max:100',
        ]);
        $voucher = Voucher::create($data);
        return $this->success(['voucher' => $voucher], 'Voucher created', 201);
    }

    public function voucherDestroy($id)
    {
        Voucher::destroy($id);
        return $this->success(null, 'Voucher deleted');
    }

    // ==================== GGR SYNC ====================
    public function syncGGRProviders(Request $request = null)
    {
        $api = app(\App\Services\GameProviderService::class)->api();
        $res = json_decode($api->providerlist(), true);

        if (!isset($res['status']) || $res['status'] != 1) {
            return $this->error($res['message'] ?? $res['msg'] ?? 'GGR API error');
        }

        $providers = $res['providers'] ?? [];
        $count = 0;

        $catOrder = ['Hot Games', 'Slots', 'Live Casino', 'Sports', 'Arcade', 'Poker', 'Sabung Ayam'];
        $category = 'Slots'; // default

        foreach ($providers as $p) {
            $code = $p['code'];
            $name = $p['name'];
            $status = $p['status'] ?? 1;

            // Check if already exists in navigation_menu
            $existing = \App\Models\NavigationMenu::where('title', $name)->first();
            if ($existing) {
                $existing->update(['is_active' => $status == 1]);
                continue;
            }

            // Auto-categorize based on keywords
            $cat = $category;
            $upper = strtoupper($name);
            if (strpos($upper, 'LIVE') !== false || strpos($upper, 'CASINO') !== false || strpos($upper, 'EVOLUTION') !== false || strpos($upper, 'SEXY') !== false || strpos($upper, 'PRETTY') !== false || strpos($upper, 'EBET') !== false || strpos($upper, 'SA GAMING') !== false || strpos($upper, 'ALLBET') !== false || strpos($upper, 'ORIENTAL') !== false || strpos($upper, '568WIN') !== false || strpos($upper, 'OPUS') !== false) {
                $cat = 'Live Casino';
            } elseif (strpos($upper, 'SPORTS') !== false || strpos($upper, 'SABA') !== false || strpos($upper, 'PINNACLE') !== false || strpos($upper, 'UMBET') !== false || strpos($upper, 'WBET') !== false) {
                $cat = 'Sports';
            } elseif (strpos($upper, 'POKER') !== false || strpos($upper, 'BALAK') !== false || strpos($upper, '9GAMING') !== false) {
                $cat = 'Poker';
            } elseif (strpos($upper, 'COCK') !== false || strpos($upper, 'SABUNG') !== false || strpos($upper, 'SV388') !== false || strpos($upper, 'WS168') !== false) {
                $cat = 'Sabung Ayam';
            } elseif (strpos($upper, 'ARCADE') !== false || strpos($upper, 'SPRIBE') !== false || strpos($upper, 'LIVE22') !== false || strpos($upper, 'ARCADIA') !== false || strpos($upper, 'FUNKY') !== false || strpos($upper, 'MM TANGKAS') !== false) {
                $cat = 'Arcade';
            } elseif (strpos($upper, 'HOT') !== false) {
                $cat = 'Hot Games';
            }

            $maxOrder = \App\Models\NavigationMenu::where('category', $cat)->max('sort_order') ?? 0;

            \App\Models\NavigationMenu::create([
                'title' => $name,
                'url' => '/' . strtolower(str_replace(' ', '-', $name)),
                'image' => '',
                'category' => $cat,
                'sort_order' => $maxOrder + 1,
                'is_active' => $status == 1,
            ]);

            $count++;
        }

        return $this->success(['synced' => $count, 'total' => count($providers)], 'Providers synced');
    }

    public function syncGGRGames(Request $request)
    {
        $providerCode = $request->provider_code;
        if (!$providerCode) {
            return $this->error('provider_code required');
        }

        $api = app(\App\Services\GameProviderService::class)->api();
        $res = json_decode($api->gamelist($providerCode), true);

        if (!isset($res['status']) || $res['status'] != 1) {
            return $this->error($res['message'] ?? $res['msg'] ?? 'GGR API error');
        }

        $games = $res['games'] ?? [];
        $count = 0;

        // Map provider code to name
        $providerName = $providerCode;

        foreach ($games as $g) {
            $gameCode = $g['game_code'];
            $gameName = $g['game_name'];
            $banner = $g['banner'] ?? '';
            $status = $g['status'] ?? 1;

            \App\Models\Game::updateOrCreate(
                ['game_code' => $gameCode, 'game_provider' => $providerCode],
                [
                    'game_name' => $gameName,
                    'game_provider' => $providerCode,
                    'provider' => $providerName,
                    'image' => $banner,
                    'game_category' => 'slot',
                    'status' => $status == 1 ? 1 : 0,
                ]
            );
            $count++;
        }

        return $this->success(['synced' => $count, 'total' => count($games)], 'Games synced for ' . $providerCode);
    }

    public function syncAllGGRGames()
    {
        $api = app(\App\Services\GameProviderService::class)->api();
        $res = json_decode($api->providerlist(), true);

        if (!isset($res['status']) || $res['status'] != 1) {
            return $this->error($res['message'] ?? $res['msg'] ?? 'GGR API error');
        }

        $providers = $res['providers'] ?? [];
        $totalGames = 0;
        $syncedProviders = 0;

        foreach ($providers as $p) {
            $code = $p['code'];
            if (($p['status'] ?? 0) != 1) continue;

            $gameRes = json_decode($api->gamelist($code), true);
            if (!isset($gameRes['status']) || $gameRes['status'] != 1) continue;

            $games = $gameRes['games'] ?? [];
            $count = 0;

            foreach ($games as $g) {
                \App\Models\Game::updateOrCreate(
                    ['game_code' => $g['game_code'], 'game_provider' => $code],
                    [
                        'game_name' => $g['game_name'],
                        'game_provider' => $code,
                        'provider' => $p['name'],
                        'image' => $g['banner'] ?? '',
                        'game_category' => strtolower($p['type'] ?? 'slot'),
                        'status' => ($g['status'] ?? 1) == 1 ? 1 : 0,
                    ]
                );
                $count++;
            }
            $totalGames += $count;
            $syncedProviders++;
        }

        return $this->success([
            'providers_synced' => $syncedProviders,
            'total_games' => $totalGames,
        ], 'All games synced successfully');
    }

    // ==================== GAMES ====================
    public function games()
    {
        $provider = request('provider');
        $category = request('category');
        $query = \App\Models\Game::query();
        if ($provider) {
            $query->where('game_provider', $provider);
        }
        if ($category) {
            $query->where('game_category', $category);
        }
        $games = $query->get()->map(function ($g) {
            return (object) [
                'id' => $g->id,
                'game_uid' => $g->game_code,
                'game_code' => $g->game_code,
                'game_name' => $g->game_name,
                'game_image' => $g->image,
                'image_url' => $g->image,
                'game_provider' => $g->game_provider,
                'provider' => $g->provider,
                'status' => $g->status,
            ];
        });
        return $this->success($games->toArray());
    }

    public function gameShow($id)
    {
        return $this->success(['game' => Game::findOrFail($id)]);
    }

    public function gamePlayers()
    {
        try {
            $fiver = new \App\Http\API\fiver();
            $raw = $fiver->callPlayer();
            $decoded = json_decode($raw, true);
            $players = $decoded['data'] ?? $decoded['players'] ?? [];
            if (!is_array($players)) $players = [];
            return $this->success($players);
        } catch (\Exception $e) {
            return $this->success([]);
        }
    }

    public function gameUpdate(Request $request, $id)
    {
        $game = Game::findOrFail($id);
        if ($request->has('type_id')) {
            $game->type_id = $request->type_id;
        }
        if ($request->hasFile('img')) {
            $game->game_image = $request->file('img')->store('post-images');
        }
        $game->save();
        return $this->success(['game' => $game], 'Game updated');
    }

    public function gameSearchByProvider(Request $request)
    {
        $games = Game::where('game_provider', $request->provider_id)->get();
        return response()->json($games);
    }

    public function providers()
    {
        $codes = \App\Models\Game::whereNotNull('game_provider')
            ->distinct()
            ->pluck('game_provider')
            ->sort()
            ->values();

        $providers = $codes->map(function ($code) {
            $name = str_replace('_', ' ', ucwords(strtolower($code)));
            return ['provider_code' => $code, 'provider_name' => $name];
        });

        return $this->success(['providers' => $providers]);
    }

    // ==================== STATUSES ====================
    public function statuses()
    {
        return $this->success(['statuses' => Status::all()]);
    }

    public function statusStore(Request $request)
    {
        $data = $request->validate(['status' => 'required']);
        $data['user_id'] = $request->user_id ?? 1;
        $status = Status::create($data);
        return $this->success(['status' => $status], 'Status created', 201);
    }

    public function statusShow($id)
    {
        return $this->success(['status' => Status::findOrFail($id)]);
    }

    public function statusUpdate(Request $request, $id)
    {
        $data = $request->validate(['status' => 'required']);
        Status::where('id', $id)->update($data);
        return $this->success(null, 'Status updated');
    }

    public function statusDestroy($id)
    {
        Status::destroy($id);
        return $this->success(null, 'Status deleted');
    }

    // ==================== SETTINGS ====================
    public function settings()
    {
        return $this->success(['setting' => Setting::orderBy('created_at', 'DESC')->first()]);
    }

    public function settingStore(Request $request)
    {
        $data = $request->except('_token', '_method');

        if ($request->file('icon')) {
            $data['icon'] = $request->file('icon')->store('post-images', 'public');
        }
        if ($request->file('logo')) {
            $data['logo'] = $request->file('logo')->store('post-images', 'public');
        }

        $links = [];
        $labels = $request->input('sosmed_label', []);
        $urls = $request->input('sosmed_url', []);
        $images = $request->input('sosmed_image', []);
        foreach ($labels as $i => $label) {
            if (trim((string) $label) === '' && trim((string) ($urls[$i] ?? '')) === '') {
                continue;
            }
            $links[] = [
                'label' => trim((string) $label),
                'url'   => trim((string) ($urls[$i] ?? '')),
                'image' => trim((string) ($images[$i] ?? '')),
            ];
        }
        $data['sosmed_links'] = $links;

        $data['user_id'] = $request->user_id ?? 1;

        // Update setting aktif (row terbaru) jika sudah ada, bukan buat row baru.
        // logo/icon lama otomatis dipertahankan saat tidak ada file baru diupload.
        $existing = Setting::orderBy('created_at', 'DESC')->first();

        if ($existing) {
            $existing->update($data);
            $setting = $existing->fresh();
        } else {
            $setting = Setting::create($data);
        }

        return $this->success(['setting' => $setting], 'Setting created', 201);
    }

    // ==================== VERIFICATIONS (KYC) ====================
    public function verifications(Request $request)
    {
        $query = Verifikasi::with('user');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'menunggu');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('username', 'like', "%$search%");
            });
        }

        return $this->success([
            'verifications' => $query->orderBy('created_at', 'DESC')->paginate(20),
        ]);
    }

    public function verificationsNew(Request $request)
    {
        $sinceId = $request->input('since_id', 0);
        $status = $request->input('status', 'menunggu');

        $data = Verifikasi::with('user')
            ->where('status', $status)
            ->where('id', '>', $sinceId)
            ->orderBy('id')
            ->get();

        return $this->success(['verifications' => $data]);
    }

    public function verificationUpdate(Request $request, $id)
    {
        $verifikasi = Verifikasi::findOrFail($id);
        $request->validate(['action' => 'required|in:acc,tolak']);
        $verifikasi->status = $request->action === 'acc' ? 'verifikasi' : 'ditolak';
        $verifikasi->save();
        return $this->success(null, 'KYC status updated');
    }

    // ==================== NETWORKS ====================
    public function networks()
    {
        return $this->success(['networks' => Network::all()]);
    }

    // ==================== LAPORAN ====================
    public function laporan()
    {
        return $this->success(['laporans' => Laporan::all()]);
    }

    public function laporanStore(Request $request)
    {
        $data = $request->validate([
            'feedback' => 'required',
            'pesan' => 'required',
        ]);
        $data['user_id'] = $request->user_id ?? 1;
        $laporan = Laporan::create($data);
        return $this->success(['laporan' => $laporan], 'Laporan created', 201);
    }

    // ==================== REKENING ====================
    public function rekeningUser(Request $request)
    {
        $rekening = Rekening::where('user_id', $request->user_id)->first();
        return $this->success(['rekening' => $rekening]);
    }

    public function rekeningStore(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required',
            'bank' => 'required|string',
            'accNumber' => 'required|string',
            'accName' => 'required|string',
        ]);
        $rekening = Rekening::create($data);
        return $this->success(['rekening' => $rekening], 'Rekening created', 201);
    }

    // ==================== COLORS/WARNA ====================
    public function colors()
    {
        $colors = \App\Models\Warna::latest()->get();
        return $this->success(['colors' => $colors]);
    }

    public function colorStore(Request $request)
    {
        $data = $request->validate(['style' => 'required|max:15']);
        $data['custom'] = $request->style;
        $data['global'] = $request->style;
        $color = \App\Models\Warna::create($data);
        return $this->success(['color' => $color], 'Color created', 201);
    }

    public function colorDestroy($id)
    {
        \App\Models\Warna::destroy($id);
        return $this->success(null, 'Color deleted');
    }

    // ==================== GAME PROVIDERS (Fiver/Exa balance) ====================
    public function providerBalances()
    {
        $SG = new \App\Http\API\fiver();
        $act = json_decode($SG->agentbalance());
        $agentBalance = $act->agent->balance ?? 0;

        $EXA = new \App\Http\API\Exa();
        $exaBalance = $EXA->agentBalance();

        $DC = new \App\Http\API\DigitalCreative();
        $dcRaw = json_decode($DC->agentbalance());
        $dcBalance = $dcRaw->agent->balance ?? 0;

        $XAPI = new \App\Http\API\XApi();
        $xapiRaw = json_decode($XAPI->agentbalance());
        $xapiBalance = $xapiRaw->agent->balance ?? 0;

        return $this->success(compact('agentBalance', 'exaBalance', 'dcBalance', 'xapiBalance'));
    }

    // ==================== GAME PROVIDER TOGGLE ====================
    public function getGameProvider()
    {
        $service = app(\App\Services\GameProviderService::class);

        return $this->success([
            'provider' => $service->current(),
            'label'    => $service->label($service->current()),
        ]);
    }

    public function setGameProvider(Request $request)
    {
        $request->validate(['provider' => 'required|in:fiver,dc,xapi']);

        $service = app(\App\Services\GameProviderService::class);
        $provider = $service->setProvider($request->provider);

        \App\Models\ActivityLog::create([
            'admin_id'     => $request->user_id ?? 1,
            'action'       => 'set_game_provider',
            'description'  => "Ganti provider game ke {$service->label($provider)}",
            'target_type'  => 'setting',
            'ip'           => request()->ip(),
        ]);

        return $this->success([
            'provider' => $provider,
            'label'    => $service->label($provider),
        ], "Provider game diganti ke {$service->label($provider)}");
    }

    // ==================== DC SYNC ====================
    public function syncDCProviders()
    {
        $api = app(\App\Services\GameProviderService::class)->api();
        $res = json_decode($api->providerlist(), true);

        if (!isset($res['status']) || $res['status'] != 1) {
            return $this->error($res['msg'] ?? 'DC API error');
        }

        $providers = $res['providers'] ?? [];
        $count = 0;

        foreach ($providers as $p) {
            $code = $p['code'];
            $name = $p['name'];
            $status = $p['status'] ?? 1;

            $existing = \App\Models\NavigationMenu::where('title', $name)->first();
            if ($existing) {
                $existing->update(['is_active' => $status == 1]);
                continue;
            }

            $cat = strtoupper($p['type'] ?? '') === 'LIVE' ? 'Live Casino'
                : (strtoupper($p['type'] ?? '') === 'SB' ? 'Sports' : 'Slots');

            $maxOrder = \App\Models\NavigationMenu::where('category', $cat)->max('sort_order') ?? 0;

            \App\Models\NavigationMenu::create([
                'title'      => $name,
                'url'        => '/' . strtolower(str_replace(' ', '-', $name)),
                'image'      => '',
                'category'   => $cat,
                'sort_order' => $maxOrder + 1,
                'is_active'  => $status == 1,
            ]);

            $count++;
        }

        return $this->success(['synced' => $count, 'total' => count($providers)], 'DC providers synced');
    }

    public function syncDCGames(Request $request)
    {
        $providerCode = $request->provider_code;
        if (!$providerCode) {
            return $this->error('provider_code required');
        }

        $api = app(\App\Services\GameProviderService::class)->api();
        $res = json_decode($api->gamelist($providerCode), true);

        if (!isset($res['status']) || $res['status'] != 1) {
            return $this->error($res['msg'] ?? 'DC API error');
        }

        $games = $res['games'] ?? [];
        $count = 0;

        foreach ($games as $g) {
            \App\Models\Game::updateOrCreate(
                ['game_code' => $g['game_code'], 'game_provider' => $providerCode],
                [
                    'game_name'     => $g['game_name'],
                    'game_provider' => $providerCode,
                    'provider'      => $providerCode,
                    'image'         => $g['banner'] ?? '',
                    'game_category' => 'slot',
                    'status'        => ($g['status'] ?? 1) == 1 ? 1 : 0,
                ]
            );
            $count++;
        }

        return $this->success(['synced' => $count, 'total' => count($games)], 'DC games synced for ' . $providerCode);
    }

    public function syncAllDCGames()
    {
        $api = app(\App\Services\GameProviderService::class)->api();
        $res = json_decode($api->providerlist(), true);

        if (!isset($res['status']) || $res['status'] != 1) {
            return $this->error($res['msg'] ?? 'DC API error');
        }

        $providers = $res['providers'] ?? [];
        $totalGames = 0;
        $syncedProviders = 0;

        foreach ($providers as $p) {
            $code = $p['code'];
            if (($p['status'] ?? 0) != 1) continue;

            $gameRes = json_decode($api->gamelist($code), true);
            if (!isset($gameRes['status']) || $gameRes['status'] != 1) continue;

            $games = $gameRes['games'] ?? [];
            $count = 0;

            foreach ($games as $g) {
                \App\Models\Game::updateOrCreate(
                    ['game_code' => $g['game_code'], 'game_provider' => $code],
                    [
                        'game_name'     => $g['game_name'],
                        'game_provider' => $code,
                        'provider'      => $p['name'],
                        'image'         => $g['banner'] ?? '',
                        'game_category' => strtolower($p['type'] ?? 'slot'),
                        'status'        => ($g['status'] ?? 1) == 1 ? 1 : 0,
                    ]
                );
                $count++;
            }
            $totalGames += $count;
            $syncedProviders++;
        }

        return $this->success([
            'providers_synced' => $syncedProviders,
            'total_games'      => $totalGames,
        ], 'All DC games synced successfully');
    }

    public function navigationMenus(Request $request)
    {
        $query = \App\Models\NavigationMenu::orderBy('sort_order');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                  ->orWhere('category', 'like', "%$search%")
                  ->orWhere('url', 'like', "%$search%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $menus = $query->get();
        return $this->success($menus->toArray());
    }

    public function navigationMenuStore(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:100',
            'url' => 'required|string|max:200',
            'image' => 'nullable|string|max:500',
            'category' => 'required|string|max:50',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if (!isset($data['sort_order'])) {
            $max = \App\Models\NavigationMenu::max('sort_order') ?? 0;
            $data['sort_order'] = $max + 1;
        }

        $menu = \App\Models\NavigationMenu::create($data);
        return $this->success($menu->toArray(), 'Menu created');
    }

    public function navigationMenuUpdate($id, Request $request)
    {
        $menu = \App\Models\NavigationMenu::findOrFail($id);
        $data = $request->validate([
            'title' => 'required|string|max:100',
            'url' => 'required|string|max:200',
            'image' => 'nullable|string|max:500',
            'category' => 'required|string|max:50',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $menu->update($data);
        return $this->success($menu->toArray(), 'Menu updated');
    }

    public function navigationMenuDestroy($id)
    {
        \App\Models\NavigationMenu::findOrFail($id)->delete();
        return $this->success(null, 'Menu deleted');
    }

    public function navigationMenuCategories()
    {
        $cats = \App\Models\NavigationMenu::select('category')
            ->distinct()->orderBy('category')->pluck('category');
        return $this->success($cats->toArray());
    }

    public function statistics()
    {
        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $monthStart = $now->copy()->startOfMonth();

        $totalUsers = \App\Models\User::count();
        $todayUsers = \App\Models\User::whereDate('created_at', $todayStart)->count();

        $totalDeposit = \App\Models\Transaksi::where('type', 1)->whereIn('status_id', [2])->sum('amount');
        $totalWithdraw = \App\Models\Transaksi::where('type', 2)->whereIn('status_id', [2])->sum('amount');
        $depositCount = \App\Models\Transaksi::where('type', 1)->whereIn('status_id', [2])->count();
        $withdrawCount = \App\Models\Transaksi::where('type', 2)->whereIn('status_id', [2])->count();

        $todayDeposit = \App\Models\Transaksi::where('type', 1)->whereIn('status_id', [2])->whereDate('created_at', $todayStart)->sum('amount');
        $todayWithdraw = \App\Models\Transaksi::where('type', 2)->whereIn('status_id', [2])->whereDate('created_at', $todayStart)->sum('amount');
        $monthDeposit = \App\Models\Transaksi::where('type', 1)->whereIn('status_id', [2])->whereDate('created_at', '>=', $monthStart)->sum('amount');
        $monthWithdraw = \App\Models\Transaksi::where('type', 2)->whereIn('status_id', [2])->whereDate('created_at', '>=', $monthStart)->sum('amount');

        $dailyStats = \App\Models\Transaksi::selectRaw("DATE(created_at) as date, type, SUM(amount) as total, COUNT(*) as count")
            ->whereIn('status_id', [2])
            ->whereDate('created_at', '>=', $now->copy()->subDays(30))
            ->groupBy('date', 'type')
            ->orderBy('date')
            ->get();

        return $this->success([
            'users' => ['total' => $totalUsers, 'today' => $todayUsers],
            'deposits' => ['total_amount' => (float) $totalDeposit, 'total_count' => $depositCount, 'today' => (float) $todayDeposit, 'month' => (float) $monthDeposit],
            'withdraws' => ['total_amount' => (float) $totalWithdraw, 'total_count' => $withdrawCount, 'today' => (float) $todayWithdraw, 'month' => (float) $monthWithdraw],
            'daily' => $dailyStats,
        ]);
    }

    public function adminMessages(Request $request)
    {
        $query = \App\Models\AdminMessage::with('sender')->orderBy('created_at', 'desc');

        if ($request->filled('recipient_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('type', 'broadcast')
                  ->orWhere('recipient_id', $request->recipient_id);
            });
        }

        $messages = $query->get();
        return $this->success($messages);
    }

    public function adminMessageStore(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:broadcast,private',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'recipient_id' => 'nullable|exists:users,id',
        ]);

        $data['sender_id'] = $request->user_id ?? 1;

        if ($data['type'] === 'broadcast') {
            $data['recipient_id'] = null;
        }

        $message = \App\Models\AdminMessage::create($data);
        \App\Models\ActivityLog::create([
            'admin_id' => $request->user_id, 'action' => 'send_message',
            'description' => "Kirim pesan {$data['type']}: {$data['title']}",
            'target_type' => 'message', 'target_id' => $message->id, 'ip' => request()->ip(),
        ]);
        return $this->success(['message' => $message], 'Message sent');
    }

    public function adminMessageRead($id)
    {
        $message = \App\Models\AdminMessage::findOrFail($id);
        $message->update(['is_read' => true]);
        return $this->success(null, 'Marked as read');
    }

    public function adminMessageDestroy($id)
    {
        \App\Models\AdminMessage::findOrFail($id)->delete();
        return $this->success(null, 'Message deleted');
    }
}
