<?php

namespace App\Http\Controllers;
use App\Models\AccountBallance;
use Illuminate\Http\Request;

class AccountBallanceController extends Controller
{
    public function checkUserHasBalance($userID, $price, $parice_in_dollar)
    {
        // for test account
        if ($price == 0 && $parice_in_dollar == 0) {
            return true;
        }
        // common product categorey check
        $data = AccountBallance::where('account_id', $userID)->first();
        if ($data != null) {
            if ($data->ballance >= $price) {
                return true;
            } elseif ($data->account_ballance_in_dollar >= $parice_in_dollar) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    public function getUserAccuntBalance($userID)
    {
        $data = AccountBallance::where('account_id', $userID)->first();
        if ($data != null) {
            return $data->ballance;
        } else {
            return 0;
        }
    }
    public function getUserAccuntBalanceInDollar($userID)
    {
        $data = AccountBallance::where('account_id', $userID)->first();
        if ($data != null) {
            return $data->account_ballance_in_dollar;
        } else {
            return 0;
        }
    }
    public function incUserAccuntBalance($userID, $ballance)
    {
        try {
            $data = AccountBallance::where('account_id', $userID)->first();
        if ($data != null) {
            $data->ballance += $ballance;

            $data->update();
            return true;
        } else {
            $newAcc = new AccountBallance();
            $newAcc->account_id = $userID;
            $newAcc->ballance = $ballance;
            $newAcc->account_ballance_in_dollar = 0;
            $newAcc->save();

            return true;
        }
        } catch (\Throwable $th) {
            \Log::info("message $th");
            return false;
        }

    }
    public function incUserAccuntBalanceInDollar($userID, $ballance)
    {
        try {
            $data = AccountBallance::where('account_id', $userID)->first();
        if ($data != null) {
            $data->account_ballance_in_dollar += $ballance;

            $data->update();
            return true;
        } else {
            $newAcc = new AccountBallance();
            $newAcc->account_id = $userID;
            $newAcc->account_ballance_in_dollar = $ballance;
            $newAcc->ballance = 0;
            $newAcc->save();

            return true;
        }
        } catch (\Throwable $th) {
            \Log::info("message $th");
            return false;
        }

    }
    public function decUserAccuntBalance($userID, $ballance, $parice_in_dollar)
    {
        $data = AccountBallance::where('account_id', $userID)->first();
        if ($data != null) {
            if ($data->ballance >= $ballance) {
                $data->ballance -= $ballance;
                $data->update();

                return true;
            } else {
                $data->account_ballance_in_dollar -=doubleval($parice_in_dollar);
                $data->update();

                return true;
            }
            return false;
        } else {
            return false;
        }
    }
    public function setNewAccountBallance(Request $request)
    {
        try {
            $data = AccountBallance::where('account_id', $request->userID)->first();
            if ($data != null) {
                $data->ballance = $request->ballance;

                $data->update();

                $logCtrl = new LogController();
                $logCtrl->addNewLog('ballance', 'میزان موجودی کاربر بصورت دستی به ' . $request->ballance . ' تومان تغییر کرد', $request->userID, '', 'edit');

                return true;
            } else {
                $newAcc = new AccountBallance();
                $newAcc->account_id = $request->userID;
                $newAcc->ballance = $request->ballance;
                $newAcc->save();
                $logCtrl->addNewLog('ballance', 'میزان موجودی کاربر بصورت دستی به ' . $request->ballance . ' تومان تغییر کرد', $request->userID, '', 'edit');

                return true;
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable $th");
            return response()->json('Server Error', 500);
        }
    }
    /// Agent Functions
    public function getLoggedUserBallancce()
    {
        try {
            $userId = auth('sanctum')->user()->account_id;
            $data = AccountBallance::where('account_id', $userId)->first();
            if (!$data) {
                $newAcc = new AccountBallance();
                $newAcc->account_id = auth('sanctum')->user()->account_id;
                $newAcc->account_ballance_in_dollar = 0;
                $newAcc->ballance = 0;
                $newAcc->save();
                return $newAcc;
            }
            return $data;
        } catch (\Throwable $th) {
            \Log::info("$th");
            return response()->json(false, 500);
        }
    }
}
