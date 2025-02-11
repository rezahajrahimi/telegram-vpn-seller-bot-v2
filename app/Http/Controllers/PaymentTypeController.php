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
    public function getAllActivePaymentTypesWithZarinpalMerchentIDFilter()
    {
        $data = PaymentType::where('is_active', true)->get();
        if ($data != null) {
            foreach ($data as $key => $value) {
                if($value->name == 'زرین پال') {
                    $value->merchant_id = "xxxx-xxxxxxxx-xxxxxxxx-xxxxxx";
                }
            }
            return $data;
        } else {
            return null;
        }
    }

    public function getAllActiveOfflinePaymentTypes()
    {
        return PaymentType::where('is_active', true)->where('type', 'offline')->get();
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
    public function getZarinpalStatus()
    {
        $data = PaymentType::where('name', 'زرین پال')->first();
        if ($data != null) {
            if ($data->is_active == 1 || $data->is_active == true) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    public function getZarinpalMerchantID()
    {
        $data = PaymentType::where('name', 'زرین پال')->first();
        if ($data != null) {
            return $data->merchant_id;
        } else {
            $data = new PaymentType();
            $data->name = 'زرین پال';
            $data->type = 'online';
            $data->merchant_id = 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx';
            $data->save();
            return $data->merchant_id;
        }
    }
    public function getZarinpalTableID()
    {
        $data = PaymentType::where('name', 'زرین پال')->first();
        if ($data != null) {
            return $data->id;
        } else {
            $data = new PaymentType();
            $data->name = 'زرین پال';
            $data->type = 'online';
            $data->merchant_id = 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx';
            $data->save();
            return $data->id;
        }
    }
    public function getZarinpalLink()
    {
        $settingCntrl = new SettingController();
        $mainUrl = $settingCntrl->getMainUrl();
        return "$mainUrl/buy";
    }
    public function getNowPaymentsLink()
    {
        $settingCntrl = new SettingController();
        $mainUrl = $settingCntrl->getMainUrl();
        return "$mainUrl/cryptopayment";
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
    public function update_offline_payment_type(Request $request)
    {
        $data = PaymentType::find($request->id);
        if ($data != null) {
            $data->name = $request->name;
            $data->merchant_id = $request->merchant_id;
            $data->save();
            return $data;
        } else {
            return false;
        }
    }
    public function removePaymentType($name)
    {
        $data = PaymentType::where('name', $name)->first();
        $data->delete();
        return true;
        // if ($data != null) {
        //     $tr = Transaction::where('payment_type_id', $data->id)->count();

        //     if ($tr == 0) {
        //         $data->delete();
        //         return true;
        //     } else {
        //         return response()->json('این گزینه دارای تراکنش می باشد.', 202);
        //     }
        // } else {
        //     return false;
        // }
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
    public function get_payment_type_by_name($name)
    {
        $data = PaymentType::where('name', $name)->first();
        if ($data != null) {
            return $data;
        } else {
            return null;
        }
    }
    public function get_payment_type_by_id($id)
    {
        $data = PaymentType::find($id);
        if ($data != null) {
            return $data;
        } else {
            return null;
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
    public function getAllTypesOfpaymentData()
    {
        try {
            $zarinPal = $this->getZarinpalPaymentDetails();
            $offline = $this->getAllOfflinePayments();
            $cryptoPaymenyCntrl = new CryptoPaymentController();
            $nowpayment = $cryptoPaymenyCntrl->getNovPaymentData();
            return response()->json([$offline, $zarinPal, $cryptoPaymenyCntrl], 200);
        } catch (\Throwable $th) {
            return response()->json(null, 500);
        }
    }
}
