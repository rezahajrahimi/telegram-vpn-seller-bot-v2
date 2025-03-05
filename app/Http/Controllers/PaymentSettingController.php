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
                    'key'         => 'shetab_verify',
                    'value'       => $this->hiddifyCtrl->generateUUID(),
                    'description' => "6104-3333-3333-3333",
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
        $paymentSetting = new PaymentSetting();
        return $paymentSetting->getPaymentSettingByKey($key);
    }
    public function getPaymentSettingValueByKey($key)
    {
        $paymentSetting = new PaymentSetting();
        return $paymentSetting->getPaymentSettingValueByKey($key);
    }
    public function getPaymentSettingDescriptionByKey($key)
    {
        $paymentSetting = new PaymentSetting();
        $paymentSetting = $paymentSetting->getPaymentSettingDescriptionByKey($key);

        if (is_array($paymentSetting)) {
            // use format text service
            $paymentSetting = $this->telegramService->formatText($paymentSetting);
        }
        return $paymentSetting;
    }
    public function reverseStatusByKey($key)
    {
        $paymentSetting = new PaymentSetting();
        return $paymentSetting->reverseStatusByKey($key);
    }
    public function setPaymentSettingValueByKey($key, $value)
    {
        $paymentSetting = new PaymentSetting();
        return $paymentSetting->setPaymentSettingValueByKey($key, $value);
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
    public function getPaymentSettingStatusByKey($key)
    {
        $paymentSetting = $this->getPaymentSettingByKey($key);
        if (!$paymentSetting) {
            $this->seed();
            $paymentSetting = $this->getPaymentSettingByKey($key);
        }
        if ($paymentSetting->status == true || $paymentSetting->status == 1) {
            return true;
        }
        return false;
    }

}
