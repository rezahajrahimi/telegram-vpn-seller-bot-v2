<?php

namespace App\Http\Controllers;

use App\Models\CryptoPayment;
use Illuminate\Http\Request;

class CryptoPaymentController extends Controller
{
    public function createNowPaymentData()
    {
        try {
            $data = new CryptoPayment();
            $data->name = 'nowpayments';
            $data->api_key = 'xxxxxxx-xxxxxxx-xxxxxxx-xxxxxxx';
            $data->env = 'live';
            $data->callback_url = 'http://127.0.0.1:8000/laravel-nowpayments';
            $data->email = 'john@gmail.com';
            $data->password = '123456789';
            $data->ipn_callback_url = 'https://nowpayments.io';
            $data->success_url = 'https://nowpayments.io';
            $data->cancel_url = 'https://nowpayments.io';
            $data->partially_paid_url = 'https://nowpayments.io';
            $data->is_fixed_rate = true;
            $data->is_fee_paid_by_user = true;

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
}
