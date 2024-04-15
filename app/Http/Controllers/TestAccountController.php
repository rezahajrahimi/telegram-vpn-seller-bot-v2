<?php

namespace App\Http\Controllers;

use App\Models\TestAccount;
use App\Models\Pannel;
use Illuminate\Http\Request;

class TestAccountController extends Controller
{
    public function getTestAccountDetails()
    {
        try {
            // return first row of TestAccount or create new TestAccount
            $data = TestAccount::first();
            if ($data != null) {
                return $data;
            }
            $pannelID = Pannel::first()->id;
            $testAccount = new TestAccount();
            $testAccount->pannel_id = $pannelID;
            $testAccount->expire_day = 30;
            $testAccount->volume = 0.5;
            $testAccount->save();
            return $testAccount;
        } catch (\Throwable $th) {
            \Log::error("Throwable $th");
            return null;
        }
    }
    public function updateTestAccountDetails(Request $request)
    {
        try {
            // return first row of TestAccount or create new TestAccount
            $testAccount = TestAccount::first();

            $testAccount->pannel_id = $request->pannel_id;
            $testAccount->expire_day = $request->expire_day;
            $testAccount->volume = $request->volume;
            $testAccount->save();
            return $testAccount;
        } catch (\Throwable $th) {
            \Log::error("Throwable $th");
            return response()->json(false, 500);
        }
    }
}
