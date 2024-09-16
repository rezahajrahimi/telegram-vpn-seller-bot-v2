<?php

namespace App\Http\Controllers;

use App\Models\CronJob;
use App\Models\Pannel;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\BotUser;

use Illuminate\Http\Request;

class CronJobController extends Controller
{
    public function get_all_cron_jobs()
    {
        try {
            $cronJobs = CronJob::all();
            if ($cronJobs) {
                return response()->json($cronJobs);
            }
            // create neccessery cron jobs
            $expiredAccountsCronJob = new CronJob();
            $expiredAccountsCronJob->name = 'Expired';
            $expiredAccountsCronJob->frequency = '5m';
            $expiredAccountsCronJob->is_active = true;
            $expiredAccountsCronJob->description = '.ارسال پیام به کاربرانی که دارای اکانت خریداری شده تمام شده دارند ';
            $expiredAccountsCronJob->save();
            $lessThan3DaysCronJob = new CronJob();
            $lessThan3DaysCronJob->name = 'Less than 3 days';
            $lessThan3DaysCronJob->frequency = '1d';
            $lessThan3DaysCronJob->is_active = true;
            $lessThan3DaysCronJob->description = 'ارسال پیام به کاربرانی که سه روز دیگر اکانت آنها تمام می شود.';
            $lessThan3DaysCronJob->save();
            $usageMoreThan85PercentCronJob = new CronJob();
            $usageMoreThan85PercentCronJob->name = 'Usage more than 85%';
            $usageMoreThan85PercentCronJob->frequency = '1d';
            $usageMoreThan85PercentCronJob->is_active = true;
            $usageMoreThan85PercentCronJob->description = 'ارسال پیام به کاربرانی که میزان استفاده از اکانت بیشتر از 85 درصد دارند.';
            $usageMoreThan85PercentCronJob->save();
            $cronJobs = CronJob::all();
            return response()->json($cronJobs);
        } catch (\Throwable $th) {
            \Log::info("Throwable $th");
            return response()->json('Server Error', 500);
        }
    }
    public function get_all_active_cron_jobs()
    {
        try {
            $cronJobs = CronJob::where('is_active', true)->get();
            return response()->json($cronJobs);
        } catch (\Throwable $th) {
            \Log::info("Throwable $th");
            return response()->json('Server Error', 500);
        }
    }
    public function change_cron_job_active_status($id)
    {
        try {
            $cronJob = CronJob::find($request->id);
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
        $pannel = Pannel::all();
        $hiddifyPanelCtrl = new HiddifyPannelController();
        foreach ($pannel as $key => $value) {
            $users = $hiddifyPanelCtrl->getHiddifyPanelUsersByPannelID($value->id);
            foreach ($users as $key => $value) {
                $usageGB = $value['current_usage_GB'];

                $usageGB = round($usageGB, 2);
                $limitGB = $value['usage_limit_GB'];
                // get usage percent
                $usagePercent = ($usageGB / $limitGB) * 100;
                $usagePercent = round($usageGB, 2);

                if ($usagePercent > 84.99) {
                    // get releated products by uuid
                    $uuid = $value['uuid'];
                    $product = Product::where('subscription_link', 'LIKE', "%{$uuid}%")->first();

                    if ($product != null) {
                        // get product category
                        $prcategory = ProductCategory::find($product->product_categories_id);
                        // get product category name
                        $productCategoryName = $prcategory->category_name;
                        // send notification
                        $user_id = $product->account_id;
                        $sendNotificationToUser = app('telegram_bot')->sendMessage("کاربر گرامی شما بیشتر از $usagePercent درصد از بسته $productCategoryName را مصرف کرده اید.", $user_id, null, 'MarkDown');
                    }
                }
            }
        }
    }
}
