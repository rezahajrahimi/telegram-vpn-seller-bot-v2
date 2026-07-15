<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyTransaction;
use App\Models\LoyaltyWallet;
use App\Models\User;
use Illuminate\Http\Request;

class LoyaltyLogsController extends Controller
{
    public function get_loyalty_logs($account_id)
    {
        try {
            $user = User::where('account_id', $account_id)->first();
            if ($user === null) {
                return response()->json([], 200);
            }

            return LoyaltyTransaction::where('user_id', $user->id)
                ->orderByDesc('id')
                ->limit(200)
                ->get();
        } catch (\Throwable $th) {
            \Log::info("Throwable get_loyalty_logs: $th");

            return response()->json(null, 500);
        }
    }

    public function get_all_loyalty_logs()
    {
        try {
            return LoyaltyTransaction::with('user')
                ->orderByDesc('id')
                ->limit(500)
                ->get();
        } catch (\Throwable $th) {
            \Log::info("Throwable get_all_loyalty_logs: $th");

            return response()->json(null, 500);
        }
    }

    public function get_top_loyalty_users()
    {
        try {
            return LoyaltyWallet::with('user')
                ->where('balance', '>', 0)
                ->orderByDesc('balance')
                ->limit(10)
                ->get()
                ->map(function (LoyaltyWallet $wallet) {
                    return [
                        'account_id' => $wallet->user?->account_id,
                        'name' => $wallet->user?->name ?? $wallet->user?->username ?? 'کاربر',
                        'balance' => (int) $wallet->balance,
                    ];
                });
        } catch (\Throwable $th) {
            \Log::info("Throwable get_top_loyalty_users: $th");

            return response()->json(null, 500);
        }
    }
}
