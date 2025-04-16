<?php

namespace App\Http\Controllers;

use App\Models\CryptoPayment;
use Illuminate\Http\Request;

class CryptoPaymentController extends Controller
{
    public function seed(){
        $this->createNowPaymentData();
        

    }
    public function createNowPaymentData()
    {
        try {
            $transactionCryptoCntrl = new TransactionCryptoController();
            $data = new CryptoPayment();
            $data->name = 'nowpayments';
            $data->api_key = 'xxxxxxx-xxxxxxx-xxxxxxx-xxxxxxx';
            $data->env = 'live';
            $data->callback_url = "https://{$transactionCryptoCntrl->getCurrentUrl()}/payback/";
            $data->email = 'john@gmail.com';
            $data->password = '123456789';
            $data->ipn_callback_url = "https://{$transactionCryptoCntrl->getCurrentUrl()}/payback/";
            $data->success_url = "https://{$transactionCryptoCntrl->getCurrentUrl()}/payback/";
            $data->cancel_url = "https://{$transactionCryptoCntrl->getCurrentUrl()}/cancelpay/";
            $data->partially_paid_url = "https://{$transactionCryptoCntrl->getCurrentUrl()}/payback/";
            $data->is_fixed_rate = true;
            $data->is_fee_paid_by_user = true;
            $data->is_active = true;

            $data->save();
            return $data;
        } catch (\Throwable $th) {
            \Log::info('message : ' . $th->getMessage());
            return null;
        }
    }
    public function getNowPaymentID()
    {
        try {
            $data = CryptoPayment::where('name', 'nowpayments')->first();
            if ($data != null) {
                return $data->id;
            }
            return null;
        } catch (\Throwable $th) {
            \Log::info('message : ' . $th->getMessage());
            return null;
        }
    }
    public function getNovPaymentData()
    {
        try {
            $data = CryptoPayment::where('name', 'nowpayments')->first();
            if ($data != null) {
                return $data;
            }
            return $this->createNowPaymentData();
        } catch (\Throwable $th) {
            \Log::info('message : ' . $th->getMessage());
            return null;
        }
    }
    public function updateNowPayment(Request $request)
    {
        try {
            $data = CryptoPayment::where('name', 'nowpayments')->first();

            $data->api_key = $request->api_key;
            $data->email = $request->email;
            $data->password = $request->password;
            $data->is_fee_paid_by_user = $request->is_fee_paid_by_user;
            $data->is_active = $request->is_active;

            $data->update();
            return $data;
        } catch (\Throwable $th) {
            \Log::info('message : ' . $th->getMessage());
            return response()->json(null, 500);
        }
    }
    public function getNowPaymentsStatus()
    {
        try {
            $data = CryptoPayment::where('name', 'nowpayments')->first();
            $sataus = $data->is_active == true || $data->is_active == 1 ? true : false;
            return $sataus;
        } catch (\Throwable $th) {
            //throw $th;
            \Log::info('message : ' . $th->getMessage());
            return false;
        }
    }
}
