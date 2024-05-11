<?php

namespace App\Http\Controllers;
use App\Models\AccountBallance;
use Illuminate\Http\Request;

class AccountBallanceController extends Controller
{
    public function checkUserHasBalance($userID, $price)
    {
        $data = AccountBallance::where('account_id', $userID)->first();
        if ($data != null) {
            if ($data->ballance >= $price) {
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
    public function incUserAccuntBalance($userID, $ballance)
    {
        $data = AccountBallance::where('account_id', $userID)->first();
        if ($data != null) {
            $data->ballance += $ballance;
            \Log::info('inc Baaaaaaaaaal ballance ' . $data->ballance);

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
    }
    public function incUserAccuntBalanceInDollar($userID, $ballance)
    {
        $data = AccountBallance::where('account_id', $userID)->first();
        if ($data != null) {
            $data->account_ballance_in_dollar += $ballance;
            \Log::info('inc Baaaaaaaaaal ballance ' . $data->account_ballance_in_dollar);

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
    }
    public function decUserAccuntBalance($userID, $ballance)
    {
        $data = AccountBallance::where('account_id', $userID)->first();
        if ($data != null) {
            $data->ballance -= $ballance;

            $data->update();
            return true;
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
}
