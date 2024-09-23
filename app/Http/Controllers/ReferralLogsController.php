<?php

namespace App\Http\Controllers;

use App\Models\ReferralLogs;
use Illuminate\Http\Request;

class ReferralLogsController extends Controller
{
    public function get_userId_by_accountId($referralCode){
        try {
            $userCntrl = new UserController();
            $userId = $userCntrl->getUserIdByTelegramID($referralCode);
            if ($userId != null) {
                return $userId;
            }
            return null;
        } catch (\Throwable $th) {
            \Log::info("Throwable get_userId_by_accountId: $th");
            return response()->json(null, 500);
        }
    }
    public function check_user_has_referral_and_create($account_id,$referralCode)
    {
        try {
            $user_id = $this->get_userId_by_accountId($account_id);
            $referral_id = $this->get_userId_by_accountId($referralCode);

            $referralLogs = ReferralLogs::where('referral_to_id', $user_id)->first();
            if ($referralLogs != null) {
                return true;
            } else {
                $newReferralLogs = new ReferralLogs();
                $newReferralLogs->referral_user_id = $referral_id;
                $newReferralLogs->referral_to_id = $user_id;
                $newReferralLogs->amount = 0.0;
                $newReferralLogs->save();
                // send message to referral account
                $resualt = app('telegram_bot')->sendMessage("یک کاربر با لینک دعوت شما وارد ربات شد.", $referralCode, null, 'MarkDown');

                return true;
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable check_user_has_referral: $th");
            return response()->json(null, 500);
        }
    }
    public function get_refreal_user_by_account_id($account_id)
    {
        try {
            $user_id = $this->get_userId_by_accountId($account_id);

            $referralLogs = ReferralLogs::where('referral_to_id', $user_id)->first();
            if ($referralLogs != null) {
                return $referralLogs->referral_user_id;
            }
            return null;
        } catch (\Throwable $th) {
            \Log::info("Throwable get_refreal_user_by_account_id: $th");
            return response()->json(null, 500);
        }
    }
    public function get_referral_logs($account_id)
    {
        try {
            $user_id = $this->get_userId_by_accountId($account_id);

            $referralLogs = ReferralLogs::where('referral_user_id', $user_id)->get();
            if ($referralLogs != null) {
                return $referralLogs;
            } else {
                return null;
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable get_referral_logs: $th");
            return response()->json(null, 500);
        }
    }
    public function get_all_referral_logs()
    {
        try {
            $referralLogs = ReferralLogs::all();
            if ($referralLogs != null) {
                return $referralLogs;
            } else {
                return null;
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable get_all_referral_logs: $th");
            return response()->json(null, 500);
        }
    }
    public function add_new_referral_logs(Request $request)
    {
        try {
            $referralLogs = new ReferralLogs();
            $referralLogs->referral_user_id = $this->get_refreal_user_by_account_id($request->referral_to_id);
            $referralLogs->referral_to_id =$this->get_userId_by_accountId( $request->referral_to_id);
            $referralLogs->amount = 0.0;
            $referralLogs->save();
            return $referralLogs;
        } catch (\Throwable $th) {
            \Log::info("Throwable add_new_referral_logs: $th");
            return response()->json(null, 500);
        }
    }
    public function add_amount_to_refrerral_user_Log_and_referral_wallet(Request $request)
    {
        try {
            $user_id = $this->get_userId_by_accountId($account_id);

            $referralLogs = new ReferralLogs();
            $referralLogs->referral_user_id = $this->get_refreal_user_by_account_id($request->referral_to_id);
            $referralLogs->referral_to_id = $this->get_userId_by_accountId( $request->referral_to_id);
            $referralLogs->amount = $request->amount;
            $referralLogs->save();
            $referralWallet = ReferralWallet::where('referral_user_id', $referralLogs->referral_user_id)->first();
            if ($referralWallet != null) {
                $referralWallet->amount = $referralWallet->amount + $request->amount;
                $referralWallet->save();
            } else {
                $referralWallet = new ReferralWallet();
                $referralWallet->referral_user_id = $this->get_refreal_user_by_account_id($request->referral_to_id);
                $referralWallet->amount = $request->amount;
                $referralWallet->update();
            }
            $text = "مقدار {$request->amount} تومان به کیف همکاری شما افزوده شد.";
            $resualt = app('telegram_bot')->sendMessage($text, $referralCode, null, 'MarkDown');

            return $referralLogs;
        } catch (\Throwable $th) {
            \Log::info("Throwable add_amount_to_refrerral_user_Log_and_referral_wallet: $th");
            return response()->json(null, 500);
        }
    }
}
