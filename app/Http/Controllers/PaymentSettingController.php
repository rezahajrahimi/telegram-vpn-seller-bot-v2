<?php
namespace App\Http\Controllers;

use App\Http\Controllers\HiddifyPannelController;
use App\Models\PaymentSetting;

class PaymentSettingController extends Controller
{
    public function __construct()
    {
        $this->hiddifyCtrl = new HiddifyPannelController();
    }
    public function seed()
    {
        try {
            // // check run on local
            // if (env('APP_ENV') != 'local') {
            //     \Log::info('PaymentSetting table seeding failed because run on local');
            //     return false;
            // }
            // truncate table
            PaymentSetting::truncate();
            // insert data
            $data = [

                [
                    'key'         => 'shetab_verify_api_key',
                    'value'       => $this->hiddifyCtrl->generateUUID(),
                    'description' => '6104-3333-3333-3333',
                    'status'      => true,
                ],
                [
                    'key'         => 'usd_transaction',
                    'value'       => '0',
                    'description' => '0',
                    'status'      => true,
                ],

            ];
            PaymentSetting::insert($data);
            \Log::info('PaymentSetting table seeded successfully');
            return true;
        } catch (\Throwable $th) {
            \Log::info("PaymentSetting table seeding failed: $th");
            return false;
        }
    }
    public function getPaymentSettingByKey($key)
    {
        $paymentSetting = PaymentSetting::where('key', $key)->first();
        if (!$paymentSetting) {
            $this->seed();
            $paymentSetting = PaymentSetting::where('key', $key)->first();
        }
        return $paymentSetting;
    }
    public function getPaymentSettingValueByKey($key)
    {
        return PaymentSetting::getPaymentSettingValueByKey($key);
    }
    public function getPaymentSettingDescriptionByKey($key)
    {
        return PaymentSetting::getPaymentSettingDescriptionByKey($key);
    }
    public function reverseStatusByKey($key)
    {
        return PaymentSetting::reverseStatusByKey($key);
    }
    public function setPaymentSettingValueByKey($key, $value)
    {
        $paymentSetting = $this->getPaymentSettingByKey($key);
        $paymentSetting->value = $value;
        $paymentSetting->save();
        return $paymentSetting;
    }
    public function setPaymentSettingDescriptionByKey($key, $description)
    {
        $paymentSetting = $this->getPaymentSettingByKey($key);
        $paymentSetting->description = $description;
        $paymentSetting->save();
        return $paymentSetting;
    }
    public function setPaymentSettingStatusByKey($key, $status)
    {
        try {
            $status = $status == 'true' || $status == 1 ? true : false;
            $paymentSetting = $this->getPaymentSettingByKey($key);
            $paymentSetting->status = $status;
            $paymentSetting->save();
            return $paymentSetting;
        } catch (\Throwable $th) {
            \Log::info("PaymentSetting table seeding failed: $th");
            return false;
        }
    }

}
