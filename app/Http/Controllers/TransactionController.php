<?php

namespace App\Http\Controllers;
use App\Models\Transaction;

use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function getUserTranaction($userID)
    {
        $data = Transaction::where('account_id', $userID)->get();
        if ($data != null) {
            return $data;
        } else {
            return null;
        }
    }
    public function addUserTranaction($userID, $amount, $recipeNUmber, $paymentTypeId)
    {
        $transaction = new Transaction();
        $transaction->account_id = $userID;
        $transaction->username = "";
        $transaction->amount = $amount;
        $transaction->recipe_number = $recipeNUmber;
        $transaction->payment_type_id = $paymentTypeId;
        return $transaction->save();
    }
}
