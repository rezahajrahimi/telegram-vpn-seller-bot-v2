<?php

namespace App\Http\Controllers;

use App\Models\PaymentType;
use App\Models\Transaction;
use App\Models\TransactionImage;
use Illuminate\Http\Request;

class PaymentTypeController extends Controller
{
    public function getPaymentTypes()
    {
        return PaymentType::all();
    }
    public function getAllActivePaymentTypes()
    {
        return PaymentType::where('is_active', true)->get();
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
        $data = PaymentType::where('name', 'زرین پال')->first();
        if ($data != null) {
            return $data;
        } else {
            $data = new PaymentType();
            $data->name = 'زرین پال';
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
        $data->is_active = true;
        $data->save();
        return $data;
    }
    public function chanegeMerChantIdByPaymentTypeName(Request $request)
    {
        $data = PaymentType::where('name', $request->name)->first();
        if ($data != null) {
            $data->merchant_id = $request->merchant_id;
            $data->update();
            return $data;
        } else {
            return false;
        }
    }
    public function removePaymentType($name)
    {
        $data = PaymentType::where('name', $name)->first();
        if ($data != null) {
            $trImage = TransactionImage::where('payment_type_id', $data->id)->count();
            $tr = Transaction::where('payment_type_id', $data->id)->count();
            if ($trImage == 0 && $tr == 0) {
                $data->delete();
                return true;
            } else {
                return response()->json("این گزینه دارای تراکنش می باشد.", 202);
            }
        } else {
            return false;
        }
    }
    public function deActivePaymentType($name)
    {
        $data = PaymentType::where('name', $name)->first();
        if ($data != null) {
            $data->is_active = false;
            $data->update();
            return true;
        } else {
            return false;
        }
    }
    public function reActivePaymentType($name)
    {
        $data = PaymentType::where('name', $name)->first();
        if ($data != null) {
            $data->is_active = true;
            $data->update();
            return true;
        } else {
            return false;
        }
    }
}
