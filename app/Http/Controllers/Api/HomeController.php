<?php

namespace App\Http\Controllers\Api;

use App\Models\Banner;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeController extends BaseApiController
{
    public function index()
    {
        $banner = Banner::all();
        $setting = Setting::orderBy('created_at', 'DESC')->first();
        $user = null;
        $balance = null;

        if ($user = $this->getAuthenticatedUser()) {
            $balance = $this->formatBalance($user->saldo);
        }

        return $this->success([
            'banner' => $banner,
            'setting' => $setting,
            'user' => $user,
            'balance' => $balance,
        ]);
    }

    public function claimDailyReward()
    {
        $user = User::find(Auth::id());
        if (!$user) return $this->error('Unauthenticated', 401);

        $user->level = 'New Player';
        $user->reward = 1;
        $user->point_player += 5;
        $user->save();

        return $this->success(['new_points' => $user->point_player], 'Reward claimed');
    }

    public function resetReward()
    {
        $user = User::find(Auth::id());
        if (!$user) return $this->error('Unauthenticated', 401);

        $user->reward = 0;
        $user->save();

        return $this->success(null, 'Reward reset');
    }

    public function updateReward()
    {
        return $this->resetReward();
    }

    public function getPlayerProgress()
    {
        $user = Auth::user();
        $pointPlayer = $user->point_player;
        $expPlayer = $user->exp_player;
        $progress = ($expPlayer > 0) ? min(($pointPlayer / $expPlayer) * 100, 100) : 0;

        return $this->success(['progress' => $progress]);
    }

    public function updateExpPlayer(Request $request)
    {
        $userId = Auth::id() ?: $request->input('user_id');
        $user = User::find($userId);
        if (!$user) return $this->error('Unauthenticated', 401);

        $request->validate(['exp_player' => 'required|integer|min:100000']);
        $user->exp_player = $request->exp_player;
        $user->save();

        return $this->success([
            'badge_level' => $this->determineBadgeLevel($user->exp_player)
        ]);
    }

    public function getUserBadge()
    {
        $user = Auth::user();
        return $this->success([
            'exp_player' => $user->exp_player,
            'badge_level' => $this->determineBadgeLevel($user->exp_player),
        ]);
    }

    private function determineBadgeLevel($expPlayer)
    {
        if ($expPlayer >= 1000000) return 'diamond';
        if ($expPlayer >= 500000) return 'gold';
        if ($expPlayer >= 100000) return 'silver';
        return 'bronze';
    }
}
