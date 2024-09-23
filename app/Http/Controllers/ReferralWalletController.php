<?php

namespace App\Http\Controllers;

use App\Models\ReferralWallet;
use Illuminate\Http\Request;

class ReferralWalletController extends Controller
{
    public function get_auth_user_wallet(Request $request)
    {
        try {
            $user = $request->user();
            $wallet = ReferralWallet::where('referral_user_id', $user->id)->first();
            if ($wallet == null) {
                $wallet = new ReferralWallet();
                $wallet->referral_user_id = $user->id;
                $wallet->amount = 0.0;
                $wallet->save();
            }
            return $wallet;
        } catch (\Throwable $th) {
            \Log::info("Throwable get_auth_user_wallet: $th");
            return response()->json(null, 500);
        }
    }
}
