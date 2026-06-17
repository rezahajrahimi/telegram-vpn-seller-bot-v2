<?php
namespace App\Http\Controllers;

use App\Http\Controllers\SanaeiPannelController;
use App\Http\Controllers\MarzbanPannelController;
use App\Models\CronJob;
use App\Models\CronLog;
use App\Models\Pannel;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class CronJobController extends Controller
{
    private function telegramService(): TelegramService
    {
        return app(TelegramService::class);
    }

    private function customTextCtrl(): CustomTextController
    {
        return new CustomTextController();
    }

    private function formatCronMessage(string $messageKey, array $variables): string
    {
        $text = $this->customTextCtrl()->getText($messageKey, $variables);
        if (is_array($text)) {
            return $this->telegramService()->formatText($text);
        }

        return (string) $text;
    }

    private function formatCronButtonLabel(string $messageKey): string
    {
        $text = $this->customTextCtrl()->getText($messageKey);
        if (is_array($text)) {
            return $this->telegramService()->formatText($text);
        }

        return (string) $text;
    }

    private function canShowRenewButton(?ProductCategory $category, Pannel $panel): bool
    {
        if ($category === null || $panel->isInventoryPanel()) {
            return false;
        }

        return $category->rechargable == true || $category->rechargable == 1;
    }

    private function sendProductCronNotification(
        Product $product,
        ProductCategory $category,
        Pannel $panel,
        string $messageKey,
        array $variables = []
    ): bool {
        $productText = "{$category->category_name} - {$product->remark}";
        $text = $this->formatCronMessage($messageKey, array_merge([
            'product_name' => $product->remark,
            'category_name' => $category->category_name,
            'product_text' => $productText,
        ], $variables));

        $telegramService = $this->telegramService();
        if ($this->canShowRenewButton($category, $panel)) {
            $buttons = [[
                $this->formatCronButtonLabel('cron.button.renew') => "recharge-{$product->id}",
            ]];
            $response = $telegramService->sendMessageWithInlineKeyboard($product->account_id, $text, $buttons);
        } else {
            $response = $telegramService->sendMessage($product->account_id, $text);
        }

        return ($response['ok'] ?? false) === true;
    }

    public function seed()
    {
        if (CronJob::all()->isEmpty()) {
            // create neccessery cron jobs
            $expiredAccountsCronJob = new CronJob();
            $expiredAccountsCronJob->name = 'Expired';
            $expiredAccountsCronJob->frequency = '10m';
            $expiredAccountsCronJob->is_active = true;
            $expiredAccountsCronJob->description = 'ارسال پیام به کاربرانی که اکانت خریداری شده منقضی شده دارند.';
            $expiredAccountsCronJob->save();
            $lessThan3DaysCronJob = new CronJob();
            $lessThan3DaysCronJob->name = 'Less than 3 days';
            $lessThan3DaysCronJob->frequency = '1d';
            $lessThan3DaysCronJob->is_active = true;
            $lessThan3DaysCronJob->description = 'ارسال پیام به کاربرانی که سه روز دیگر اکانت آنها منقضی می شود.';
            $lessThan3DaysCronJob->save();
            $usageMoreThan85PercentCronJob = new CronJob();
            $usageMoreThan85PercentCronJob->name = 'Usage more than 85%';
            $usageMoreThan85PercentCronJob->frequency = '5m';
            $usageMoreThan85PercentCronJob->is_active = true;
            $usageMoreThan85PercentCronJob->description = 'ارسال پیام به کاربرانی که میزان استفاده از اکانت بیشتر از 85 درصد دارند.';
            $usageMoreThan85PercentCronJob->save();
            $abandonedCartCronJob = new CronJob();
            $abandonedCartCronJob->name = 'Abandoned Cart Reminders';
            $abandonedCartCronJob->frequency = '30m';
            $abandonedCartCronJob->is_active = true;
            $abandonedCartCronJob->description = 'یادآوری خریدهای ناتمام و موجودی ناکافی.';
            $abandonedCartCronJob->save();
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
            $this->syncMissingCronJobs();
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
    private function syncMissingCronJobs(): void
    {
        if (! CronJob::where('name', 'Abandoned Cart Reminders')->exists()) {
            $abandonedCartCronJob = new CronJob();
            $abandonedCartCronJob->name = 'Abandoned Cart Reminders';
            $abandonedCartCronJob->frequency = '30m';
            $abandonedCartCronJob->is_active = true;
            $abandonedCartCronJob->description = 'یادآوری خریدهای ناتمام و موجودی ناکافی.';
            $abandonedCartCronJob->save();
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
            $cronJob = CronJob::find($id);
            $cronJob->is_active = !$cronJob->is_active;
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
        $authCntrl = new AuthController();
        $getPowerPsLicenseType = $authCntrl->getPowerPsLicenseType();
        if ($getPowerPsLicenseType == "false" || $getPowerPsLicenseType == "trial" || $getPowerPsLicenseType == "boronze") {
            return false;
        }

        $pannel = Pannel::all();
        foreach ($pannel as $key => $panel) {
            $usersResponse = $this->getPanelUsers($panel);
            if ($usersResponse === []) {
                continue;
            }

            foreach ($usersResponse as $key => $value) {
                $usageGB = $value['current_usage_GB'];
                // $usageGB = round($usageGB, 2);
                $limitGB = $value['usage_limit_GB'];

                // check divide zero
                if ($usageGB == 0 || $limitGB == 0) {
                    continue;
                }

                // get usage percent

                $usagePercent = ($usageGB / $limitGB) * 100;

                if ($usagePercent > 84.99 && $usagePercent < 99.99) {
                    // get releated products by uuid
                    $uuid = $value['uuid'];
                    $product = $this->findProductForPanelUser($panel, $uuid);

                    if ($product != null) {
                        $usagePercent = round($usagePercent, 2);

                        $name = $value['name'];
                        // get product category
                        $prcategory = ProductCategory::find($product->product_categories_id);
                        // get product category name
                        $productCategoryName = $prcategory->category_name;
                        $productText = "{$productCategoryName} - {$product->remark}";
                        // send notification
                        $user_id = $product->account_id;
                        // check has exist in cron log or not, if not exist send notification
                        $cronLog = CronLog::where('cron_id', $cronJob->id)
                            ->where('product_id', $product->id)
                            ->get();
                        if ($cronLog->count() == 0) {
                            $prcategory = ProductCategory::find($product->product_categories_id);
                            if ($prcategory === null) {
                                continue;
                            }

                            $sendNotificationToUser = $this->sendProductCronNotification(
                                $product,
                                $prcategory,
                                $panel,
                                'cron.usage_high.message',
                                ['usage_percent' => (string) $usagePercent]
                            );

                            if ($sendNotificationToUser) {
                                $cronLog = new CronLog();
                                $cronLog->cron_id = $cronJob->id;
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
            $isEnable = $advanceSettingCntrl->getValueByNameWithBooleanValue('bot_auto_delete_expired_configs');
            if ($isEnable == false || $isEnable == 0) {
                return false;
            }
            $cronJob = CronJob::where('name', 'Auto Delete Expired Configs After 10 Days')->first();
            // check if is_active was false, return
            if ($cronJob == null) {
                $autoDeleteExpiredConfigsCronJob = new CronJob();
                $autoDeleteExpiredConfigsCronJob->name = 'Auto Delete Expired Configs After 10 Days';
                $autoDeleteExpiredConfigsCronJob->frequency = '1d';
                $autoDeleteExpiredConfigsCronJob->is_active = true;
                $autoDeleteExpiredConfigsCronJob->description = 'حذف کانفیگ هایی که 10 روز از انقضا آنها می گذرد.';
                $autoDeleteExpiredConfigsCronJob->save();
                $cronJob = $autoDeleteExpiredConfigsCronJob;
            }
            if ($cronJob->is_active == false) {
                return false;
            }
            $authCntrl = new AuthController();
            $getPowerPsLicenseType = $authCntrl->getPowerPsLicenseType();
            if ($getPowerPsLicenseType == "false" || $getPowerPsLicenseType == "trial" || $getPowerPsLicenseType == "boronze") {
                return false;
            }

            $pannel = Pannel::all();

            foreach ($pannel as $key => $panel) {
                $usersResponse = $this->getPanelUsers($panel);
                if ($usersResponse === []) {
                    continue;
                }

                $products = [];
                // create a empty array of products ids and uuid
                $productsIds = [];
                $productsUuids = [];
                foreach ($usersResponse as $key => $value) {
                    // get releated products by uuid
                    $uuid = $value['uuid'];

                    $package_days = intval($value['package_days'] ?? 0);
                    $startDateRaw = $value['start_date'] ?? null;
                    if (empty($startDateRaw) || $package_days <= 0) {
                        $currentUsageGB = $value['current_usage_GB'] ?? 0;
                        $usageLimitGB = $value['usage_limit_GB'] ?? 0;
                        if ($currentUsageGB > 0 && $usageLimitGB > 0 && $currentUsageGB >= $usageLimitGB) {
                            $product = $this->findProductForPanelUser($panel, $uuid);
                            if ($product != null) {
                                $products[] = $product;
                                $productsIds[] = $product->id;
                                $productsUuids[] = $uuid;
                            }
                        }
                        continue;
                    }

                    $startDate = Carbon::parse($startDateRaw);
                    // add expireDate to $startDate
                    $expireDate = Carbon::parse($startDate);

                    // add $package_days to $expireDate
                    $expireDate->addDays($package_days);

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
                        $product = $this->findProductForPanelUser($panel, $uuid);
                        if ($product != null) {
                            $products[] = $product;
                            $productsIds[] = $product->id;
                            $productsUuids[] = $uuid;
                            continue;

                        }

                    }

                    // check if expireDate is not in the past, skip
                    if (!$expireDate->isPast()) {
                        continue;
                    }
                    // check if more than 10 days have passed since expireDate
                    $dateDifference = Carbon::now()->diffInDays($expireDate);
                    if ($dateDifference < 10) {
                        continue;
                    }

                    // add product to deletion list
                    $product = $this->findProductForPanelUser($panel, $uuid);
                    if ($product != null) {
                        $products[] = $product;
                        $productsIds[] = $product->id;
                        $productsUuids[] = $uuid;
                    }
                    // delete config on hiddify panel

                    // delete products

                }
                Product::whereIn('id', $productsIds)->delete();
                // delete users from panel
                foreach ($productsUuids as $key => $uuid) {
                    $this->deletePanelUser($panel, $uuid);
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

        $authCntrl = new AuthController();
        $getPowerPsLicenseType = $authCntrl->getPowerPsLicenseType();
        if ($getPowerPsLicenseType == "false" || $getPowerPsLicenseType == "trial" || $getPowerPsLicenseType == "boronze") {
            return false;
        }
        $pannel = Pannel::all();
        foreach ($pannel as $key => $panel) {
            $usersResponse = $this->getPanelUsers($panel);
            if ($usersResponse === []) {
                continue;
            }

            foreach ($usersResponse as $key => $value) {
                $package_days = intval($value['package_days'] ?? 0);
                $startDateRaw = $value['start_date'] ?? null;
                if (empty($startDateRaw) || $package_days <= 0) {
                    continue;
                }

                $startDate = Carbon::parse($startDateRaw);
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
                    $uuid = $value['uuid'];
                    $product = $this->findProductForPanelUser($panel, $uuid);

                    if ($product != null) {
                        // get product category
                        $prcategory = ProductCategory::find($product->product_categories_id);
                        // get product category name
                        $productCategoryName = $prcategory->category_name;
                        $productText = "{$productCategoryName} - {$product->remark}";

                        // send notification
                        $user_id = $product->account_id;
                        // check has exist in cron log or not, if not exist send notification
                        $cronLog = CronLog::where('cron_id', $cronJob->id)
                            ->where('product_id', $product->id)
                            ->get();
                        // check has cronlog created in more than 23 hours ago or not
                        // add $dateDifference +1 because time diff is on hout base

                        if ($cronLog->count() < 4) {
                            $prcategory = ProductCategory::find($product->product_categories_id);
                            if ($prcategory === null) {
                                continue;
                            }

                            $sendNotificationToUser = $this->sendProductCronNotification(
                                $product,
                                $prcategory,
                                $panel,
                                'cron.expiring_soon.message',
                                ['days_left' => (string) $dateDifference]
                            );

                            if ($sendNotificationToUser) {
                                $cronLog = new CronLog();
                                $cronLog->cron_id = $cronJob->id;
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
            $authCntrl = new AuthController();
            $getPowerPsLicenseType = $authCntrl->getPowerPsLicenseType();
            if ($getPowerPsLicenseType == "false" || $getPowerPsLicenseType == "trial" || $getPowerPsLicenseType == "boronze") {
                return false;
            }
            // checl is enable in advanced setting ot not
            $advancedSettingCntrl = new AdvanceSettingLookupController();
            $isEnable = $advancedSettingCntrl->getValueByNameWithBooleanValue('bot_auto_set_price_by_dollar_price');
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
                    $price = $value->price_in_dollar * $tetherPrice;
                    $price = round($price, 2);
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

            $authCntrl = new AuthController();
            $getPowerPsLicenseType = $authCntrl->getPowerPsLicenseType();
            if ($getPowerPsLicenseType == "false" || $getPowerPsLicenseType == "trial" || $getPowerPsLicenseType == "boronze") {
                return false;
            }

            // checl is enable in advanced setting ot not
            $advancedSettingCntrl = new AdvanceSettingLookupController();
            $isEnable = $advancedSettingCntrl->getValueByNameWithBooleanValue('bot_calculate_product_category_price_in_dollar_by_toman');
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

        $authCntrl = new AuthController();
        $getPowerPsLicenseType = $authCntrl->getPowerPsLicenseType();
        if ($getPowerPsLicenseType == "false" || $getPowerPsLicenseType == "trial" || $getPowerPsLicenseType == "boronze") {
            return false;
        }

        $pannel = Pannel::all();

        foreach ($pannel as $key => $panel) {
            $usersResponse = $this->getPanelUsers($panel);
            if ($usersResponse === []) {
                continue;
            }

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
                    $uuid = $value['uuid'];
                    $product = $this->findProductForPanelUser($panel, $uuid);

                    if ($product != null) {
                        $cronLog = CronLog::where('cron_id', $cronJob->id)
                            ->where('product_id', $product->id)
                            ->get();

                        if ($cronLog->count() == 0) {
                            $prcategory = ProductCategory::find($product->product_categories_id);
                            if ($prcategory === null) {
                                continue;
                            }

                            $sendNotificationToUser = $this->sendProductCronNotification(
                                $product,
                                $prcategory,
                                $panel,
                                'cron.expired.message'
                            );

                            if ($sendNotificationToUser) {
                                $cronLog = new CronLog();
                                $cronLog->cron_id = $cronJob->id;
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

    public function execute_send_abandoned_cart_reminders()
    {
        $cronJob = CronJob::where('name', 'Abandoned Cart Reminders')->first();
        if ($cronJob === null || $cronJob->is_active == false) {
            return false;
        }

        $authCntrl = new AuthController();
        $license = strtolower((string) $authCntrl->getPowerPsLicenseType());
        if ($license !== 'gold') {
            return false;
        }

        $intentService = new \App\Services\PurchaseIntentService();
        $customTextCtrl = $this->customTextCtrl();
        $telegramService = $this->telegramService();

        foreach ($intentService->getFirstReminders('package_selected', 1) as $intent) {
            $category = ProductCategory::find($intent->product_category_id);
            if ($category === null) {
                continue;
            }

            $text = $this->formatCronMessage('recovery.package_selected.message', [
                'package_name' => $category->category_name,
            ]);
            $buyLabel = $this->formatCronButtonLabel('recovery.button.buy');
            $response = $telegramService->sendMessageWithInlineKeyboard($intent->account_id, $text, [[
                $buyLabel => "buySubscription-{$category->id}",
            ]]);

            if (($response['ok'] ?? false) === true) {
                $intentService->markReminded($intent);
            }
        }

        foreach ($intentService->getFollowUpReminders('package_selected', 23) as $intent) {
            if ($intent->reminder_count >= 2) {
                continue;
            }
            $category = ProductCategory::find($intent->product_category_id);
            if ($category === null) {
                continue;
            }

            $text = $this->formatCronMessage('recovery.package_selected.message', [
                'package_name' => $category->category_name,
            ]);
            $buyLabel = $this->formatCronButtonLabel('recovery.button.buy');
            $response = $telegramService->sendMessageWithInlineKeyboard($intent->account_id, $text, [[
                $buyLabel => "buySubscription-{$category->id}",
            ]]);

            if (($response['ok'] ?? false) === true) {
                $intentService->markReminded($intent);
            }
        }

        foreach ($intentService->getFirstReminders('insufficient_balance', 2) as $intent) {
            $category = ProductCategory::find($intent->product_category_id);
            if ($category === null) {
                continue;
            }

            $text = $this->formatCronMessage('recovery.insufficient_balance.message', [
                'package_name' => $category->category_name,
            ]);
            $balanceLabel = $this->formatCronButtonLabel('recovery.button.add_balance');
            $response = $telegramService->sendMessageWithInlineKeyboard($intent->account_id, $text, [[
                $balanceLabel => 'accountAddBalance',
            ]]);

            if (($response['ok'] ?? false) === true) {
                $intentService->markReminded($intent);
            }
        }

        foreach ($intentService->getFirstReminders('recharge_pending', 2) as $intent) {
            if ($intent->product_id === null) {
                continue;
            }
            $category = ProductCategory::find($intent->product_category_id);
            if ($category === null) {
                continue;
            }

            $text = $this->formatCronMessage('recovery.recharge.message', [
                'package_name' => $category->category_name,
            ]);
            $renewLabel = $this->formatCronButtonLabel('cron.button.renew');
            $response = $telegramService->sendMessageWithInlineKeyboard($intent->account_id, $text, [[
                $renewLabel => "recharge-{$intent->product_id}",
            ]]);

            if (($response['ok'] ?? false) === true) {
                $intentService->markReminded($intent);
            }
        }

        return true;
    }

    public function get_tether_price_by_nobitex()
    {
        try {
            // get USDT price by nobitex v3 api
            $response = Http::connectTimeout(30)->get('https://apiv2.nobitex.ir/v3/orderbook/USDTIRT');

            if ($response->ok()) {
                $data = $response->json();

                if (isset($data['lastTradePrice'])) {
                    $price = $data['lastTradePrice'];
                    // change price from Rial to Toman
                    $intPrice = (int) ($price / 10);
                    // دو رقم آخر را 0 قرار بده
                    $intPrice = round($intPrice, -3);
                    return $intPrice;
                }
            }
            return null;
        } catch (\Exception $e) {
            \Log::error($e->getMessage());

            return null;
        }
    }
    public function execute_create_daily_backup()
    {
        try {
            $authCntrl = new AuthController();
            $getPowerPsLicenseType = $authCntrl->getPowerPsLicenseType();
            if ($getPowerPsLicenseType == "false" || $getPowerPsLicenseType == "trial" || $getPowerPsLicenseType == "boronze") {
                return false;
            }

            $advancedSettingCntrl = new AdvanceSettingLookupController();
            $isEnable = $advancedSettingCntrl->getValueByNameWithBooleanValue('bot_daily_backup');
            if ($isEnable == false || $isEnable == 0) {
                return false;
            }

            $backupCtrl = new BackupController();

            // استفاده از متد جدید که هم بکاپ می‌گیرد و هم به تلگرام ارسال می‌کند
            $admin = User::where('role', 'admin')->first();
            if (!$admin) {
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
        $cronJob = new CronJob();
        $cronJob->name = 'Create Daily Backup';
        $cronJob->frequency = '1d';
        $cronJob->is_active = true;
        $cronJob->description = 'ایجاد نسخه پشتیبان روزانه از پایگاه داده هر روز در ساعت 08:00';
        $cronJob->save();
        return $cronJob;
    }

    public function clear_laravel_log()
    {
        try {
            $logPath = storage_path('logs/laravel.log');
            if (file_exists($logPath)) {
                file_put_contents($logPath, '');
            }
            return true;
        } catch (\Throwable $th) {
            \Log::error("Error clearing laravel log: " . $th->getMessage());
            return false;
        }
    }

    private function getPanelUsers(Pannel $panel): array
    {
        if ($panel->type === 'hiddify' || $panel->type === 'sanaei' || $panel->isMarzbanCompatible()) {
            return (new HiddifyPannelController())->resolvePanelUsersList($panel->id);
        }

        return [];
    }

    private function deletePanelUser(Pannel $panel, string $uuid): void
    {
        if ($panel->type === 'hiddify') {
            (new HiddifyPannelController())->deleteUserOfHiddifyPanel($panel->id, $uuid);
        } elseif ($panel->type === 'sanaei') {
            (new SanaeiPannelController())->deleteUser($panel, $uuid);
        } elseif ($panel->isMarzbanCompatible()) {
            MarzbanPannelController::resolve($panel)->deleteUser($panel, $uuid);
        }
    }

    private function findProductForPanelUser(Pannel $panel, string $identifier): ?Product
    {
        if ($panel->isMarzbanCompatible()) {
            return Product::where('remark', $identifier)->first();
        }

        if ($panel->type == 'sanaei') {
            $byConfig = Product::where('configs', 'like', '%"uuid":"' . $identifier . '"%')->first();
            if ($byConfig) {
                return $byConfig;
            }
        }

        return Product::where('subscription_link', 'LIKE', "%{$identifier}%")->first();
    }
}
