<?php

namespace App\Http\Controllers;

use App\Models\UsedTestAccount;
use Illuminate\Http\Request;

class UsedTestAccountController extends Controller
{
    public function newTestAccount($account_id, $test_account_id)
    {
        if (!$this->checkUserHasTestAccount($account_id, $test_account_id)) {
            $testAccount = new UsedTestAccount();
            $testAccount->account_id = $account_id;
            $testAccount->test_account_id = $test_account_id;
            $testAccount->save();
            return true;
        }
        return false;
    }
    public function checkUserHasTestAccount($account_id, $test_account_id)
    {
        if ($this->getCountOfUsePerUser($test_account_id, $account_id) >= 1) {
            return true;
        }
        return false;
    }
    public function getCountOfUsePerUser($test_account_id, $account_id)
    {
        return UsedGiftCard::where('test_account_id', $test_account_id)->where('account_id', $account_id)->count();
    }
}
