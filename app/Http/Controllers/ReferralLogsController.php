<?php

namespace App\Http\Controllers;

use App\Models\ReferralLogs;
use App\Models\User;
use App\Models\ReferralWallet;
use App\Models\ReferralSetting;
use App\Models\Transaction;

use Illuminate\Http\Request;

class ReferralLogsController extends Controller
{
    public function get_userId_by_accountId($referralCode)
    {
        try {
            $user = User::where('account_id', $referralCode)->first();
            if ($user != null) {
                return $user->id;
            }
            return null;
        } catch (\Throwable $th) {
            \Log::info("Throwable get_userId_by_accountId: $th");
            return response()->json(null, 500);
        }
    }
    public function check_user_is_referred($account_id)
    {
        try {
            $user_id = $this->get_userId_by_accountId($account_id);
            $referralLogs = ReferralLogs::where('referral_to_id', $user_id)->first();
            if ($referralLogs != null) {
                return true;
            }
            return false;
        } catch (\Throwable $th) {
            \Log::info("Throwable check_user_is_referred: $th");
            return response()->json(null, 500);
        }
    }
    public function check_user_has_referral_and_create($account_id, $referralCode)
    {

        try {
            // $saveRef = $referralLogsCntrl->check_user_has_referral_and_create($this->from_id, $this->referralCode);



            $user_id = $this->get_userId_by_accountId($account_id);
            $referral_id = $this->get_userId_by_accountId($referralCode);
            if ($user_id == $referral_id || $referral_id == null) {
                return false;
            }
            $referralLogs = ReferralLogs::where('referral_to_id', $user_id)->first();
            if ($referralLogs != null) {
                return true;
            } else {


                $newReferralLogs = new ReferralLogs();
                $newReferralLogs->referral_user_id = $referral_id;
                $newReferralLogs->referral_to_id = $user_id;
                $newReferralLogs->amount = 0.0;
                $newReferralLogs->save();
                \Log::info("newReferralLogs: $newReferralLogs->referral_user_id $newReferralLogs->referral_to_id");
                // send message to referral account
                $resualt = app('telegram_bot')->sendMessage('یک کاربر با لینک دعوت شما وارد ربات شد.', $referralCode, null, 'MarkDown');
                // $this->addNewBotLog('referral', 'یک کاربر با لینک دعوت شما وارد ربات شد.', 'newreferral');

                return true;
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable check_user_has_referral: $th");
            return null;
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

            $referralLogs = ReferralLogs::where('referral_user_id', $user_id)
                ->with(['referral_to', 'referral_user'])
                ->orderBy('created_at', 'desc')
                ->get();
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
            $referralLogs->referral_to_id = $this->get_userId_by_accountId($request->referral_to_id);
            if ($referralLogs->referral_to_id == $referralLogs->referral_user_id) {
                return false;
            }
            $referralLogs->amount = $request->amount;
            $referralLogs->transaction_id = $request->transaction_id ?? null;
            $referralLogs->save();
            return $referralLogs;
        } catch (\Throwable $th) {
            \Log::info("Throwable add_new_referral_logs: $th");
            return response()->json(null, 500);
        }
    }
    public function add_amount_to_refrerral_user_Log_and_referral_wallet($transaction_id, $amount, $isPaymentBack = false)
    {
        try {
            // check if referral is active

            $referralSetting = ReferralSetting::first();
            $refferalActivation = false;
            try {
                $refferalActivation = $referralSetting->is_active;
            } catch (\Throwable $th) {
                \Log::info("Throwable add_amount_to_refrerral_user_Log_and_referral_wallet inner: $th");
                $refferalActivation = false;
            }
            if ($refferalActivation == 0 || $refferalActivation == null || $refferalActivation == false) {
                return null;
            }

            $referralLogs = ReferralLogs::where('transaction_id', $transaction_id)->first();
            if ($referralLogs === null) {
                if ($isPaymentBack) {
                    $transaction = Transaction::find($transaction_id);

                    $referredUser = User::where('account_id', $transaction->account_id)->first()->id;
                    if ($referredUser == null) {
                        return null;
                    }
                    $refferId = ReferralLogs::where('referral_to_id', $referredUser)->first()->referral_user_id;
                    // get refer percent
                    $referralSettingCntrl = new ReferralSettingController();
                    $referral_percent = $referralSettingCntrl->get_referral_setting_referral_percent();
                    $percentAmount = 0;
                    if ($referral_percent !== null || $referral_percent !== 0) {
                        $percentAmount = ($transaction->amount / 100) * $referral_percent;
                    }

                    $referralLogs = new ReferralLogs();
                    $referralLogs->referral_user_id = $refferId;
                    $referralLogs->referral_to_id = $referredUser;
                    $referralLogs->amount = $percentAmount;
                    $referralLogs->transaction_id = $transaction->id;
                    $referralLogs->save();

                } else {
                    return null;

                }
            }

            // if referralLogs->updated_at != referralLogs->created_at means is updated before
            // and there is no need to decrease

            $referralLogs->amount = $amount;
            $referralLogs->update();

            $referralWallet = ReferralWallet::where('referral_user_id', $referralLogs->referral_user_id)->first();

            if ($referralWallet != null) {
                $referralWallet->amount = $referralWallet->amount + $referralLogs->amount;
                $referralWallet->update();
            } else {
                $referralWallet = new ReferralWallet();
                $referralWallet->referral_user_id = $referralLogs->referral_user_id;
                $referralWallet->amount = $referralLogs->amount;
                $referralWallet->save();
            }
            $user = User::where('id', $referralLogs->referral_user_id)->first();
            $referralCode = $user->account_id;
            $text = "مقدار {$referralLogs->amount} تومان به کیف همکاری شما افزوده شد.";
            $resualt = app('telegram_bot')->sendMessage($text, $referralCode, null, 'MarkDown');
            // $this->addNewBotLog('referral', $text, 'newreferral');
            return $referralLogs;
        } catch (\Throwable $th) {
            \Log::info("Throwable add_amount_to_refrerral_user_Log_and_referral_wallet: $th");
            return response()->json(null, 500);
        }
    }
    public function decrease_amount_to_refrerral_user_Log_and_referral_wallet($transaction_id, $amount)
    {
        try {
            $referralLogs = ReferralLogs::where('transaction_id', $transaction_id)->first();
            if ($referralLogs == null) {
                return null;
            }
            // check if referral is active

            $referralSetting = ReferralSetting::first();
            $refferalActivation = $referralSetting->is_active;
            if ($refferalActivation == 0 || $refferalActivation == null || $refferalActivation == false) {
                return null;
            }
            // if referralLogs->updated_at == referralLogs->created_at means is the first time
            // and there is no need to decrease
            if ($referralLogs->created_at === $referralLogs->updated_at) {
                return null;
            }
            $referralWallet = ReferralWallet::where('referral_user_id', $referralLogs->referral_user_id)->first();
            if ($referralWallet != null) {
                $referralWallet->amount = $referralWallet->amount - $referralLogs->amount;
                $referralWallet->save();
            }
            $user = User::where('id', $referralLogs->referral_user_id)->first();
            $referralCode = $user->account_id;

            $text = "مقدار {$referralLogs->amount} تومان به کیف همکاری شما کم شد.";
            $resualt = app('telegram_bot')->sendMessage($text, $referralCode, null, 'MarkDown');
            $this->addNewBotLog('referral', $text, 'newreferral');

            return $referralLogs;
        } catch (\Throwable $th) {
            \Log::info("Throwable add_amount_to_refrerral_user_Log_and_referral_wallet: $th");
            return response()->json(null, 500);
        }
    }
    public function addNewBotLog($type, $message, $event)
    {
        try {
            $logCtrl = new LogController();
            $username = Auth::user()->name;
            $account_id = Auth::user()->account_id;
            $logCtrl->addNewLog($type, $message, $account_id, $username, $event);
            return true;
        } catch (\Throwable $th) {
            \Log::info("Throwable addNewBotLog: $th");
            return;
        }
    }
}
