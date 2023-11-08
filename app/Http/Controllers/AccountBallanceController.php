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
    public function incUserAccuntBalance($userID,$ballance)
    {
        $data = AccountBallance::where('account_id', $userID)->first();
        if ($data != null) {
             $data->ballance += $ballance;
             \Log::info('inc Baaaaaaaaaal ballance '.$data->ballance);

             $data->update();
             return true;
        } else {
            $newAcc = new AccountBallance();
            $newAcc->account_id = $userID;
            $newAcc->ballance = $ballance;
            $newAcc->save();

            return true;
        }
    }
    public function decUserAccuntBalance($userID,$ballance)
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
}
