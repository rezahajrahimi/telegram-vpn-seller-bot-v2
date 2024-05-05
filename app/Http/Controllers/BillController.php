<?php

namespace App\Http\Controllers;
use App\Models\Bill;

use Illuminate\Http\Request;

class BillController extends Controller
{
    public function isBillIdExist($billId)
    {
        $data = Bill::where('bill_id', $billId)->first();
        if ($data != null) {
            return false;
        } else {
            return true;
        }
    }


    public function createNewBill(Request $request)
    {
        $bill = new Bill();
        $bill->account_id = $request->account_id;
        $bill->bill_id = abs(crc32(uniqid()));
        $bill->amount = $request->amount;
        $bill->amount_dollar = 0.0;
        if ($bill->save()) {
            return $bill;
        } else {
            return null;
        }
    }
    public function createNewBillInDollar(Request $request)
    {
        $bill = new Bill();
        $bill->account_id = $request->account_id;
        $bill->bill_id = abs(crc32(uniqid()));
        $bill->amount = 0;
        $bill->amount_dollar = $request->amount;
        if ($bill->save()) {
            return $bill;
        } else {
            return null;
        }
    }
    public function getBillAmountByBillId($billId)
    {
        $data = Bill::where('bill_id', $billId)->first();
        \Log::info("billllllll $data");

        if ($data != null) {
            return $data->amount;
        } else {
            return null;
        }
    }
}
