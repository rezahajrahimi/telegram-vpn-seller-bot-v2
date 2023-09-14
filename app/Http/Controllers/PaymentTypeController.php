<?php

namespace App\Http\Controllers;

use App\Models\PaymentType;
use Illuminate\Http\Request;

class PaymentTypeController extends Controller
{
    public function getPaymentTypes()
    {
        return PaymentType::all();
    }
    public function getPaymentAddressByPaymentName($name)
    {
        $data = PaymentType::where('name', $name)->first();
        if ($data != null) {
            return $data->merchant_id;
        } else {
            return null;
        }
    }
    public function isPaymentType($name)
    {
        $data = PaymentType::where('name', $name)->first();
        if ($data != null) {
            return true;
        } else {
            return false;
        }
    }
    public function getAllOnlinePayments()
    {
        $data = PaymentType::where('type', 'online')->get();
        if ($data != null) {
            return $data;
        } else {
            return null;
        }
    }
    public function getAllOfflinePayments()
    {
        $data = PaymentType::where('type', 'offline')->get();
        if ($data != null) {
            return $data;
        } else {
            return null;
        }
    }
    public function getZarinpalPaymentDetails()
    {
        $data = PaymentType::where('name', 'zarinpal')->first();
        if ($data != null) {
            return $data;
        } else {
            $data = new PaymentType();
            $data->name = 'zarinpal';
            $data->type = 'online';
            $data->merchant_id = 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx';
            $data->save();
            return $data;
        }
    }
    public function createNewPaymentType(Request $request)
    {
        $data = new PaymentType();
        $data->name = $request->name;
        $data->type = $request->type;
        $data->merchant_id = $request->merchant_id;
        $data->save();
        return $data;
    }
}
