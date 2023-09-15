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
    function generateBillId()
    {
        $number = mt_rand(1000000000, 9999999999);
        if (isBillIdExist($number)) {
            return generateBillId();
        }

        return $number;
    }

    public function createNewBill(Request $request)
    {
        $bill = new Bill();
        $bill->account_id = $request->account_id;
        $bill->bill_id = $this->generateBillId();
        $bill->amount = $request->amount;
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
