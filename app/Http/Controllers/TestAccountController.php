<?php

namespace App\Http\Controllers;

use App\Models\TestAccount;
use App\Models\Pannel;
use App\Models\UsedTestAccount;
use App\Models\User;
use App\Http\Controllers\ProductCategoryController;
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
            // create a new product category and save data
            $request = new Request();
            $request->pannel_id = $pannelID;
            $request->category_name = 'اکانت آزمایشی';
            $request->price = 0;
            $request->expire_day = 30;
            $request->volume = 0.5;
            $request->rechargable = false;
            $request->show_subscription_link = true;
            $request->show_pannel_link = true;
            $request->is_active = true;
            $prdCatCntrl = new ProductCategoryController();

            $prdCatCntrl->addNewProductCategory($request);
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

            $request = new Request();
            $request->pannel_id = $request->pannel_id;
            $request->category_name = 'اکانت آزمایشی';
            $request->price = 0;
            $request->expire_day = $request->expire_day;
            $request->volume = $request->volume;
            $request->rechargable = false;
            $request->show_subscription_link = true;
            $request->show_pannel_link = true;
            $request->is_active = true;
            $prdCatCntrl = new ProductCategoryController();

            $prdCatCntrl->editProductCategoryByName($request);

            return $testAccount;
        } catch (\Throwable $th) {
            \Log::error("Throwable $th");
            return response()->json(false, 500);
        }
    }

    public function getTestUsers()
    {
        try {
            $testUsers = UsedTestAccount::with('user')->get();
            return response()->json($testUsers);
        } catch (\Throwable $th) {
            \Log::error("Throwable $th");
            return response()->json([], 500);
        }
    }

    public function deleteTestUser(Request $request)
    {
        try {
            $testUser = UsedTestAccount::find($request->id);
            if ($testUser) {
                $testUser->delete();
                return response()->json(true);
            }
            return response()->json(false, 404);
        } catch (\Throwable $th) {
            \Log::error("Throwable $th");
            return response()->json(false, 500);
        }
    }

    public function clearTestUsers()
    {
        try {
            UsedTestAccount::truncate();
            return response()->json(true);
        } catch (\Throwable $th) {
            \Log::error("Throwable $th");
            return response()->json(false, 500);
        }
    }
}
