<?php

namespace App\Http\Controllers;

use App\Http\API\fiver;
use App\Http\API\exa; 
use App\Models\User;
use App\Models\Banner;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function index()
    {
        if (Auth::check()) {
            if (Auth::user()->role == 'admin') {
                return redirect('/Admin/Dashboard');
            } else {
                $user = Auth::user();
                $hiddenBalance = (float) $user->saldo;

                if ($hiddenBalance == 0) {
                    $balance = '0,00';
                } else {
                    if ($hiddenBalance < 1000 && $hiddenBalance > 0) {
                        $formattedBalance = number_format($hiddenBalance, 2, ',', '.');
                        $balance = '0' . '.' . substr_replace($formattedBalance, '', -4);
                    } else {
                        $formattedBalance = number_format($hiddenBalance, 2, ',', '.');
                        $balance = substr_replace($formattedBalance, '', -4);
                    }
                }
                
                $banner = Banner::all();
                $setting = Setting::orderBy('created_at', 'DESC')->first();
                return view('index', compact('banner', 'setting', 'balance'));
            }
        } else {
            $banner = Banner::all();
            $setting = Setting::orderBy('created_at', 'DESC')->first();
            return view('index', compact('banner', 'setting'));
        }
    }

    public function claimDailyReward()
    {
        $user = User::where('id', Auth()->user()->id)->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
        }
        $user->level = 'New Player';
        $user->reward = 1;
        $user->point_player += 5;
        $user->save();

        return response()->json(['success' => true, 'new_points' => $user->point_player]);
    }
    
    public function resetReward()
    {
        $user = User::where('id', Auth()->user()->id)->first();

        $user->reward = 0;
        $user->save();

        return response()->json(['success' => true]);
    }

    public function updateReward()
    {
        $user = User::where('id', Auth()->user()->id)->first();

        $user->reward = 0;
        $user->save();

        return response()->json(['success' => true]);
    }

    public function inject()
    {
        // Return dummy response sementara API dimatikan
        return response()->json(['msg' => 'API is currently bypassed']);
    }
    
    public function getPlayerProgress()
    {
        $user = auth()->user();
        $pointPlayer = $user->point_player;
        $expPlayer = $user->exp_player;

        $progress = ($expPlayer > 0) ? min(($pointPlayer / $expPlayer) * 100, 100) : 0;

        return response()->json([
            'success' => true,
            'progress' => $progress
        ]);
    }

    public function updateExpPlayer(Request $request)
    {
        $user = User::where('id', Auth()->user()->id)->first();

        $request->validate([
            'exp_player' => 'required|integer|min:100000'
        ]);

        $user->exp_player = $request->exp_player;
        $user->save();

        $badgeLevel = $this->determineBadgeLevel($user->exp_player);

        return response()->json([
            'success' => true,
            'badge_level' => $badgeLevel
        ]);
    }

    public function getUserBadge()
    {
        $user = Auth::user();
        $expPlayer = $user->exp_player;
        $badgeLevel = $this->determineBadgeLevel($expPlayer);

        return response()->json([
            'success' => true,
            'exp_player' => $expPlayer,
            'badge_level' => $badgeLevel,
        ]);
    }

    // Saya menyatukan fungsi penentuan level agar konsisten menggunakan 'diamond'
    private function determineBadgeLevel($expPlayer)
    {
        if ($expPlayer >= 1000000) {
            return 'diamond';
        } elseif ($expPlayer >= 500000) {
            return 'gold';
        } elseif ($expPlayer >= 100000) {
            return 'silver';
        } else {
            return 'bronze';
        }
    }
}