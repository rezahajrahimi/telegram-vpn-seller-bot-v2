<?php
namespace App\Http\Controllers;

use App\Models\CronJob;
use App\Models\CronLog;
use App\Models\Pannel;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class CronJobController extends Controller
{
    public function seed()
    {
        if (CronJob::all()->isEmpty()) {
            // create neccessery cron jobs
            $expiredAccountsCronJob              = new CronJob();
            $expiredAccountsCronJob->name        = 'Expired';
            $expiredAccountsCronJob->frequency   = '10m';
            $expiredAccountsCronJob->is_active   = true;
            $expiredAccountsCronJob->description = 'ارسال پیام به کاربرانی که اکانت خریداری شده منقضی شده دارند.';
            $expiredAccountsCronJob->save();
            $lessThan3DaysCronJob              = new CronJob();
            $lessThan3DaysCronJob->name        = 'Less than 3 days';
            $lessThan3DaysCronJob->frequency   = '1d';
            $lessThan3DaysCronJob->is_active   = true;
            $lessThan3DaysCronJob->description = 'ارسال پیام به کاربرانی که سه روز دیگر اکانت آنها منقضی می شود.';
            $lessThan3DaysCronJob->save();
            $usageMoreThan85PercentCronJob              = new CronJob();
            $usageMoreThan85PercentCronJob->name        = 'Usage more than 85%';
            $usageMoreThan85PercentCronJob->frequency   = '5m';
            $usageMoreThan85PercentCronJob->is_active   = true;
            $usageMoreThan85PercentCronJob->description = 'ارسال پیام به کاربرانی که میزان استفاده از اکانت بیشتر از 85 درصد دارند.';
            $usageMoreThan85PercentCronJob->save();
            // $createDailyBackupCronJob              = new CronJob();
            // $createDailyBackupCronJob->name        = 'Create Daily Backup';
            // $createDailyBackupCronJob->frequency   = '1d';
            // $createDailyBackupCronJob->is_active   = true;
            // $createDailyBackupCronJob->description = 'ایجاد نسخه پشتیبان روزانه از پایگاه داده هر روز در ساعت 08:00';
            // $createDailyBackupCronJob->save();
            // $autoDeleteExpiredConfigsCronJob              = new CronJob();
            // $autoDeleteExpiredConfigsCronJob->name        = 'Auto Delete Expired Configs After 10 Days';
            // $autoDeleteExpiredConfigsCronJob->frequency   = '1d';
            // $autoDeleteExpiredConfigsCronJob->is_active   = true;
            // $autoDeleteExpiredConfigsCronJob->description = 'حذف کانفیگ هایی که 10 روز از انقضا آنها می گذرد.';
            // $autoDeleteExpiredConfigsCronJob->save();
            return true;
        }
        return false;
    }
    public function get_all_cron_jobs()
    {
        try {
            $cronJobs = CronJob::all();
            if ($cronJobs->count() > 0) {
                return response()->json($cronJobs);
            }
            $this->seed();
            $cronJobs = CronJob::all();
            return response()->json($cronJobs);
        } catch (\Throwable $th) {
            // \Log::info("Throwable $th");
            return response()->json('Server Error', 500);
        }
    }
    public function get_all_active_cron_jobs()
    {
        try {
            $cronJobs = CronJob::where('is_active', true)->get();
            return response()->json($cronJobs);
        } catch (\Throwable $th) {
            // \Log::info("Throwable $th");
            return response()->json('Server Error', 500);
        }
    }
    public function change_cron_job_active_status($id)
    {
        try {
            $cronJob            = CronJob::find($id);
            $cronJob->is_active = ! $cronJob->is_active;
            $cronJob->save();
            return response()->json($cronJob);
        } catch (\Throwable $th) {
            \Log::info("Throwable $th");
            return response()->json('Server Error', 500);
        }
    }
    public function execute_send_useage_more_than_85_percent()
    {
        $cronJob = CronJob::where('name', 'Usage more than 85%')->first();
        // check if is_active was false, return
        if ($cronJob->is_active == false) {
            return false;
        }
        $authCntrl             = new AuthController();
        $getPowerPsLicenseType = $authCntrl->getPowerPsLicenseType();
        if ($getPowerPsLicenseType == "false" || $getPowerPsLicenseType == "trial" || $getPowerPsLicenseType == "boronze") {
            return false;
        }

        $pannel           = Pannel::all();
        $hiddifyPanelCtrl = new HiddifyPannelController();
        foreach ($pannel as $key => $value) {
            $usersResponse = $hiddifyPanelCtrl->getHiddifyPanelUsersByPannelID($value->id);
            // تبدیل Response به آرایه
            // check if usersResponse is json or array
            // $users = json_decode($usersResponse->getContent(), true);
            // \Log::info("users: " . json_encode($users));

            foreach ($usersResponse as $key => $value) {
                $usageGB = $value['current_usage_GB'];
                // $usageGB = round($usageGB, 2);
                $limitGB = $value['usage_limit_GB'];

                // check divide zero
                if ($usageGB == 0 || $limitGB == 0) {
                    return true;
                }

                // get usage percent

                $usagePercent = ($usageGB / $limitGB) * 100;

                if ($usagePercent > 84.99 && $usagePercent < 99.99) {
                    // get releated products by uuid
                    $uuid    = $value['uuid'];
                    $product = Product::where('subscription_link', 'LIKE', "%{$uuid}%")->first();

                    if ($product != null) {
                        $usagePercent = round($usagePercent, 2);

                        $name = $value['name'];
                        // get product category
                        $prcategory = ProductCategory::find($product->product_categories_id);
                        // get product category name
                        $productCategoryName = $prcategory->category_name;
                        $productText         = "{$productCategoryName} - {$product->remark}";
                        // send notification
                        $user_id = $product->account_id;
                        // check has exist in cron log or not, if not exist send notification
                        $cronLog = CronLog::where('cron_id', $cronJob->id)
                            ->where('product_id', $product->id)
                            ->get();
                        if ($cronLog->count() == 0) {
                            $sendNotificationToUser = app('telegram_bot')->sendMessage("کاربر گرامی شما بیشتر از $usagePercent درصد از بسته $productText را مصرف کرده اید.", $user_id, null, 'MarkDown');

                            if ($sendNotificationToUser) {
                                $cronLog             = new CronLog();
                                $cronLog->cron_id    = $cronJob->id;
                                $cronLog->product_id = $product->id;
                                $cronLog->save();
                            }
                        }
                    }
                }
            }
        }
        return true;
    }
    public function execute_auto_delete_expired_configs()
    {
        try {
            $advanceSettingCntrl = new AdvanceSettingLookupController();
            $isEnable            = $advanceSettingCntrl->getValueByNameWithBooleanValue('bot_auto_delete_expired_configs');
            if ($isEnable == false || $isEnable == 0) {
                return false;
            }
            $cronJob = CronJob::where('name', 'Auto Delete Expired Configs After 10 Days')->first();
            // check if is_active was false, return
            if ($cronJob == null) {
                $autoDeleteExpiredConfigsCronJob              = new CronJob();
                $autoDeleteExpiredConfigsCronJob->name        = 'Auto Delete Expired Configs After 10 Days';
                $autoDeleteExpiredConfigsCronJob->frequency   = '1d';
                $autoDeleteExpiredConfigsCronJob->is_active   = true;
                $autoDeleteExpiredConfigsCronJob->description = 'حذف کانفیگ هایی که 10 روز از انقضا آنها می گذرد.';
                $autoDeleteExpiredConfigsCronJob->save();
                $cronJob = $autoDeleteExpiredConfigsCronJob;
            }
            if ($cronJob->is_active == false) {
                return false;
            }
            $authCntrl             = new AuthController();
            $getPowerPsLicenseType = $authCntrl->getPowerPsLicenseType();
            if ($getPowerPsLicenseType == "false" || $getPowerPsLicenseType == "trial" || $getPowerPsLicenseType == "boronze") {
                return false;
            }

            $pannel           = Pannel::all();
            $hiddifyPanelCtrl = new HiddifyPannelController();

            foreach ($pannel as $key => $panel) {
                $usersResponse = $hiddifyPanelCtrl->getHiddifyPanelUsersByPannelID($panel->id);
                if (! is_array($usersResponse)) {
                    continue;
                }
                $products = [];
                // create a empty array of products ids and uuid
                $productsIds   = [];
                $productsUuids = [];
                foreach ($usersResponse as $key => $value) {
                    // get releated products by uuid
                    $uuid = $value['uuid'];

                    $startDate = $value['start_date'];
                    // convert $startDate to valid carbon date
                    $startDate = Carbon::parse($startDate);

                    $package_days = $value['package_days'];
                    // convert $package_days to integer
                    $package_days = intval($package_days);
                    // add expireDate to $startDate
                    $expireDate = Carbon::parse($startDate);

                    // add $pacje_days to $expireDate
                    $expireDate->addDays($package_days);
                    // add 10 days to $expireDate
                    $expireDate->addDays(10);
                    // get usage_limit_GB
                    $currentUsageGB = $value['current_usage_GB'];
                    // check if usage_limit_GB is 0
                    if ($currentUsageGB == 0) {
                        continue;
                    }
                    // get usage_limit_GB
                    $usageLimitGB = $value['usage_limit_GB'];
                    // check if currentUsageGB is more than usageLimitGB
                    if ($currentUsageGB >= $usageLimitGB) {
                        $product = Product::where('subscription_link', 'LIKE', "%{$uuid}%")->first();
                        if ($product != null) {
                            $products[]      = $product;
                            $productsIds[]   = $product->id;
                            $productsUuids[] = $uuid;
                            continue;

                        }

                    }

                    // get usage_limit_GB
                    if (! $expireDate->isPast()) {
                        continue;
                    }
                    // check if $expireDate is in the past
                    $dateDifference = $expireDate->diffInDays(Carbon::now());

                    // creat a empty arrat of products
                    $product = Product::where('subscription_link', 'LIKE', "%{$uuid}%")->first();
                    if ($product != null) {
                        $products[]      = $product;
                        $productsIds[]   = $product->id;
                        $productsUuids[] = $uuid;
                    }
                    // delete config on hiddify panel

                    // delete products

                }
                Product::whereIn('id', $productsIds)->delete();
                // delete users from hiddify panel
                foreach ($productsUuids as $key => $uuid) {
                    $hiddifyPanelCtrl->deleteUserOfHiddifyPanel($panel->id, $uuid);
                }

            }

            return true;
        } catch (\Throwable $th) {
            \Log::error($th->getMessage());
            return false;
        }
    }

    public function execute_send_lass_there_than_3_days()
    {
        $cronJob = CronJob::where('name', 'Less than 3 days')->first();
        // check if is_active was false, return
        if ($cronJob->is_active == false) {
            return false;
        }

        $authCntrl             = new AuthController();
        $getPowerPsLicenseType = $authCntrl->getPowerPsLicenseType();
        if ($getPowerPsLicenseType == "false" || $getPowerPsLicenseType == "trial" || $getPowerPsLicenseType == "boronze") {
            return false;
        }
        $pannel           = Pannel::all();
        $hiddifyPanelCtrl = new HiddifyPannelController();
        foreach ($pannel as $key => $value) {
            $usersResponse = $hiddifyPanelCtrl->getHiddifyPanelUsersByPannelID($value->id);

            if (! is_array($usersResponse)) {
                continue;
            }
            foreach ($usersResponse as $key => $value) {
                $startDate = $value['start_date'];
                // convert $startDate to valid carbon date
                $startDate = Carbon::parse($startDate);

                $package_days = $value['package_days'];
                // convert $package_days to integer
                $package_days = intval($package_days);
                // add expireDate to $startDate
                $expireDate = Carbon::parse($startDate);
                // add $pacje_days to $expireDate
                $expireDate->addDays($package_days);
                //

                // get diffrence between current date and $expireDate
                $dateDifference = $expireDate->diffInDays(Carbon::now());
                // get usage percent
                if ($dateDifference < 4 && $dateDifference > 0) {
                    // get releated products by uuid
                    $uuid    = $value['uuid'];
                    $product = Product::where('subscription_link', 'LIKE', "%{$uuid}%")->first();

                    if ($product != null) {
                        // get product category
                        $prcategory = ProductCategory::find($product->product_categories_id);
                        // get product category name
                        $productCategoryName = $prcategory->category_name;
                        $productText         = "{$productCategoryName} - {$product->remark}";

                        // send notification
                        $user_id = $product->account_id;
                        // check has exist in cron log or not, if not exist send notification
                        $cronLog = CronLog::where('cron_id', $cronJob->id)
                            ->where('product_id', $product->id)
                            ->get();
                        // check has cronlog created in more than 23 hours ago or not
                        // add $dateDifference +1 because time diff is on hout base

                        if ($cronLog->count() < 4) {
                            $sendNotificationToUser = app('telegram_bot')->sendMessage("کاربر گرامی تنها $dateDifference روز دیگر از بسته $productText باقی مانده است.", $user_id, null, 'MarkDown');

                            if ($sendNotificationToUser) {
                                $cronLog             = new CronLog();
                                $cronLog->cron_id    = $cronJob->id;
                                $cronLog->product_id = $product->id;
                                $cronLog->save();
                            }
                        }
                    }
                }
            }
        }
        return true;
    }
    public function calculate_product_category_price_by_tether()
    {
        try {
            // check account license
            $authCntrl             = new AuthController();
            $getPowerPsLicenseType = $authCntrl->getPowerPsLicenseType();
            if ($getPowerPsLicenseType == "false" || $getPowerPsLicenseType == "trial" || $getPowerPsLicenseType == "boronze") {
                return false;
            }
            // checl is enable in advanced setting ot not
            $advancedSettingCntrl = new AdvanceSettingLookupController();
            $isEnable             = $advancedSettingCntrl->getValueByNameWithBooleanValue('bot_auto_set_price_by_dollar_price');
            if ($isEnable == false || $isEnable == 0) {
                return false;
            }

            $tetherPrice = $this->get_tether_price_by_nobitex();
            if ($tetherPrice == null) {
                return false;
            }
            $productCats = ProductCategory::all();

            foreach ($productCats as $key => $value) {
                if ($value->category_name != 'اکانت آزمایشی') {
                    $price        = $value->price_in_dollar * $tetherPrice;
                    $price        = round($price, 2);
                    $value->price = $price;
                    $value->update();
                }
            }
        } catch (\Throwable $th) {
            //throw $th;
            return;
        }
    }
    public function calculate_product_category_price_in_dollar_by_toman()
    {
        try {
            // check account license

            $authCntrl             = new AuthController();
            $getPowerPsLicenseType = $authCntrl->getPowerPsLicenseType();
            if ($getPowerPsLicenseType == "false" || $getPowerPsLicenseType == "trial" || $getPowerPsLicenseType == "boronze") {
                return false;
            }

            // checl is enable in advanced setting ot not
            $advancedSettingCntrl = new AdvanceSettingLookupController();
            $isEnable             = $advancedSettingCntrl->getValueByNameWithBooleanValue('bot_calculate_product_category_price_in_dollar_by_toman');
            if ($isEnable == false || $isEnable == 0) {
                return false;
            }

            $tetherPrice = $this->get_tether_price_by_nobitex();
            if ($tetherPrice == null) {
                return false;
            }
            $productCats = ProductCategory::all();

            foreach ($productCats as $key => $value) {
                if ($value->category_name != 'اکانت آزمایشی') {
                    $price = $value->price / $tetherPrice;
                    // set $price in $value->price_in_dollar by two decimal digit
                    $price = round($price, 2);

                    $value->price_in_dollar = round($price, 2);
                    $value->update();
                }
            }
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
    public function execute_send_expired_products()
    {
        $cronJob = CronJob::where('name', 'Expired')->first();
        // check if is_active was false, return
        if ($cronJob->is_active == false) {
            return false;
        }

        $authCntrl             = new AuthController();
        $getPowerPsLicenseType = $authCntrl->getPowerPsLicenseType();
        if ($getPowerPsLicenseType == "false" || $getPowerPsLicenseType == "trial" || $getPowerPsLicenseType == "boronze") {
            return false;
        }

        $pannel           = Pannel::all();
        $hiddifyPanelCtrl = new HiddifyPannelController();

        foreach ($pannel as $key => $value) {
            $usersResponse = $hiddifyPanelCtrl->getHiddifyPanelUsersByPannelID($value->id);
            // تبدیل Response به آرایه
          

            foreach ($usersResponse as $key => $value) {
                $usageGB = $value['current_usage_GB'];
                $limitGB = $value['usage_limit_GB'];

                if ($limitGB == 0 || $usageGB == 0) {
                    continue;
                }

                // get usage percent
                $usagePercent = ($usageGB / $limitGB) * 100;
                $usagePercent = round($usagePercent, 2);

                if ($usagePercent >= 99.97) {
                    // get releated products by uuid
                    $uuid    = $value['uuid'];
                    $product = Product::where('subscription_link', 'LIKE', "%{$uuid}%")->first();

                    if ($product != null) {
                        $cronLog = CronLog::where('cron_id', $cronJob->id)
                            ->where('product_id', $product->id)
                            ->get();

                        if ($cronLog->count() == 0) {
                            // get product category
                            $prcategory = ProductCategory::find($product->product_categories_id);
                            // get product category name
                            $productCategoryName = $prcategory->category_name;
                            $productText         = "{$productCategoryName} - {$product->remark}";

                            // send notification
                            $user_id = $product->account_id;

                            $sendNotificationToUser = app('telegram_bot')->sendMessage(
                                "کاربر گرامی بسته $productText منقضی شده است. لطفا برای تمدید بسته مجددا اقدام کنید.",
                                $user_id,
                                null,
                                'MarkDown'
                            );

                            if ($sendNotificationToUser['success']) {
                                $cronLog             = new CronLog();
                                $cronLog->cron_id    = $cronJob->id;
                                $cronLog->product_id = $product->id;
                                $cronLog->save();
                            }
                        }
                    }
                }
            }
        }
        return true;
    }

    public function get_tether_price_by_nobitex()
    {
        try {
            // gest Irt usdt by nobitex

            $response = Http::connectTimeout(30)->get('https://api.nobitex.ir/v2/trades/USDTIRT');
            // Decode the response JSON into an array of data.
            if ($response->ok()) {
                $data = json_decode($response->getBody()->getContents(), true);

                $price = $data['trades'][0]['price'];
                // change price to toman
                $price = mb_substr($price, 0, -1);

                $intPrice = (int) $price;
                return $intPrice;
            } else {
                return null;
            }
        } catch (\Exception $e) {
            \Log::error($e->getMessage());

            return null;
        }
    }
    public function execute_create_daily_backup()
    {
        try {
            $authCntrl             = new AuthController();
            $getPowerPsLicenseType = $authCntrl->getPowerPsLicenseType();
            if ($getPowerPsLicenseType == "false" || $getPowerPsLicenseType == "trial" || $getPowerPsLicenseType == "boronze") {
                return false;
            }

            $advancedSettingCntrl = new AdvanceSettingLookupController();
            $isEnable             = $advancedSettingCntrl->getValueByNameWithBooleanValue('bot_daily_backup');
            if ($isEnable == false || $isEnable == 0) {
                return false;
            }

            $backupCtrl = new BackupController();

            // استفاده از متد جدید که هم بکاپ می‌گیرد و هم به تلگرام ارسال می‌کند
            $admin = User::where('role', 'admin')->first();
            if (! $admin) {
                \Log::error('هیچ کاربر ادمینی یافت نشد');
                return false;
            }

            $result = $backupCtrl->createBackupAndSendToTelegram($admin->account_id);

            if ($result) {
                \Log::info('بکاپ روزانه با موفقیت ایجاد و ارسال شد');
                return "done";
            } else {
                \Log::error('خطا در ایجاد یا ارسال بکاپ روزانه');
                return "error";
            }

        } catch (\Throwable $th) {
            \Log::error('خطا در اجرای بکاپ روزانه: ' . $th->getMessage());
            return "error";
        }
    }
    public function create_cron_job_for_create_daily_backup()
    {
        $cronJob              = new CronJob();
        $cronJob->name        = 'Create Daily Backup';
        $cronJob->frequency   = '1d';
        $cronJob->is_active   = true;
        $cronJob->description = 'ایجاد نسخه پشتیبان روزانه از پایگاه داده هر روز در ساعت 08:00';
        $cronJob->save();
        return $cronJob;
    }
}
