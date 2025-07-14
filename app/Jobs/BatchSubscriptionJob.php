<?php

namespace App\Jobs;

use App\Http\Controllers\HiddifyPannelController;
use App\Models\Product;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;

class BatchSubscriptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $action, $listOfConfigs, $panelID, $extra;

    public function __construct($action, $listOfConfigs, $panelID, $extra = [])
    {
        $this->action = $action;
        $this->listOfConfigs = $listOfConfigs;
        $this->panelID = $panelID;
        $this->extra = $extra;
    }

    public function handle()
    {
        $success = true;
        $message = '';
        try {
            $action = $this->action;
            $listOfConfigs = $this->listOfConfigs;
            $panelID = $this->panelID;
            $extra = $this->extra;
            $hiddifyPannelCntrl = App::make(HiddifyPannelController::class);
            if (!isset($listOfConfigs)) {
                $success = false;
                $message = 'لیست پیکربندی‌ها ارسال نشده است.';
                return;
            }
            if ($action == 'inc_days') {
                $days = $extra['days'] ?? null;
                if (!isset($days)) {
                    $success = false;
                    $message = 'تعداد روزها ارسال نشده است.';
                } else {
                    foreach ($listOfConfigs as $config) {
                        $aa = is_array($config) ? $config : json_decode($config, true);
                        $config = (array) $aa;
                        $comment = "افزایش روزها در " . Verta::now() . " به مدت " . $days . " روز";
                        $newDay = (int) $config['packageDays'] + (int) $days;
                        $uuid = $config['uuid'];
                        $name = $config['name'];
                        $params = [
                            'uuid' => "$uuid",
                            'name' => "$name",
                            'package_days' => $newDay,
                            'mode' => 'no_reset',
                            'comment' => "$comment",
                        ];
                        $result = $hiddifyPannelCntrl->sendPatchRequestToHiddifyPannel($panelID, "/api/v2/admin/user/$uuid/", $params);
                        if ($result === false) {
                            $success = false;
                            $message = 'خطا در افزودن روزها به پیکربندی: ' . $config['name'];
                            break;
                        }
                    }
                }
            } elseif ($action == 'dec_days') {
                $days = $extra['days'] ?? null;
                if (!isset($days)) {
                    $success = false;
                    $message = 'تعداد روزها ارسال نشده است.';
                } else {
                    foreach ($listOfConfigs as $config) {
                        $aa = is_array($config) ? $config : json_decode($config, true);
                        $config = (array) $aa;
                        $comment = "کاهش روزها در " . Verta::now() . " به مدت " . $days . " روز";
                        $newDay = (int) $config['packageDays'] - (int) $days;
                        $uuid = $config['uuid'];
                        $name = $config['name'];
                        $params = [
                            'uuid' => "$uuid",
                            'name' => "$name",
                            'package_days' => $newDay,
                            'mode' => 'no_reset',
                            'comment' => "$comment",
                        ];
                        $result = $hiddifyPannelCntrl->sendPatchRequestToHiddifyPannel($panelID, "/api/v2/admin/user/$uuid/", $params);
                        if ($result === false) {
                            $success = false;
                            $message = 'خطا در کاهش روزها به پیکربندی: ' . $config['name'];
                            break;
                        }
                    }
                }
            } elseif ($action == 'inc_vol') {
                $vol = $extra['vol'] ?? null;
                if (!isset($vol)) {
                    $success = false;
                    $message = 'مقدار حجم ارسال نشده است.';
                } else {
                    foreach ($listOfConfigs as $config) {
                        $aa = is_array($config) ? $config : json_decode($config, true);
                        $config = (array) $aa;
                        $comment = "افزایش حجم در " . Verta::now() . " به میزان " . $vol . " GB";
                        $newVol = (double) $config['usageLimitGB'] + $vol;
                        $uuid = $config['uuid'];
                        $name = $config['name'];
                        $params = [
                            'uuid' => "$uuid",
                            'name' => "$name",
                            'usage_limit_GB' => $newVol,
                            'mode' => 'no_reset',
                            'comment' => "$comment",
                        ];
                        $result = $hiddifyPannelCntrl->sendPatchRequestToHiddifyPannel($panelID, "/api/v2/admin/user/$uuid/", $params);
                        if ($result === false) {
                            $success = false;
                            $message = 'خطا در افزایش حجم پیکربندی: ' . $config['name'];
                            break;
                        }
                    }
                }
            } elseif ($action == 'dec_vol') {
                $vol = $extra['vol'] ?? null;
                if (!isset($vol)) {
                    $success = false;
                    $message = 'مقدار حجم ارسال نشده است.';
                } else {
                    foreach ($listOfConfigs as $config) {
                        $aa = is_array($config) ? $config : json_decode($config, true);
                        $config = (array) $aa;
                        $comment = "کاهش حجم در " . Verta::now() . " به میزان " . $vol . " GB";
                        $newVol = (double) $config['usageLimitGB'] - $vol;
                        $uuid = $config['uuid'];
                        $name = $config['name'];
                        $params = [
                            'uuid' => "$uuid",
                            'name' => "$name",
                            'usage_limit_GB' => $newVol,
                            'mode' => 'no_reset',
                            'comment' => "$comment",
                        ];
                        $result = $hiddifyPannelCntrl->sendPatchRequestToHiddifyPannel($panelID, "/api/v2/admin/user/$uuid/", $params);
                        if ($result === false) {
                            $success = false;
                            $message = 'خطا در کاهش حجم پیکربندی: ' . $config['name'];
                            break;
                        }
                    }
                }
            } elseif ($action == 'active') {
                foreach ($listOfConfigs as $config) {
                    $aa = is_array($config) ? $config : json_decode($config, true);
                    $config = (array) $aa;
                    $uuid = $config['uuid'];
                    $name = $config['name'];
                    $req = new \Illuminate\Http\Request();
                    $req->pannelID = $panelID;
                    $req->uuid = $uuid;
                    $req->comment = "فعالسازی در " . Verta::now();
                    $req->enable = true;
                    $result = $hiddifyPannelCntrl->changeUserActivationOfHiddifyPanelApi($req);
                    if ($result === false) {
                        $success = false;
                        $message = 'خطا در فعالسازی پیکربندی: ' . $config['name'];
                        break;
                    }
                }
            } elseif ($action == 'deactive') {
                foreach ($listOfConfigs as $config) {
                    $aa = is_array($config) ? $config : json_decode($config, true);
                    $config = (array) $aa;
                    $uuid = $config['uuid'];
                    $name = $config['name'];
                    $req = new \Illuminate\Http\Request();
                    $req->pannelID = $panelID;
                    $req->uuid = $uuid;
                    $req->comment = "غیرفعال سازی در " . Verta::now();
                    $req->enable = false;
                    $result = $hiddifyPannelCntrl->changeUserActivationOfHiddifyPanelApi($req);
                    if ($result === false) {
                        $success = false;
                        $message = 'خطا در غیرفعال سازی پیکربندی: ' . $config['name'];
                        break;
                    }
                }
            } elseif ($action == 'delete') {
                foreach ($listOfConfigs as $config) {
                    $aa = is_array($config) ? $config : json_decode($config, true);
                    $config = (array) $aa;
                    $uuid = $config['uuid'];
                    $result = $hiddifyPannelCntrl->deleteUserOfHiddifyPanel($panelID, $uuid);
                    if ($result === false) {
                        Log::error('خطا در حذف ' . $config['name']);
                        $success = false;
                        $message = 'خطا در حذف پیکربندی: ' . $config['name'];
                        break;
                    }
                    // حذف از جدول products
                    $product = Product::where('subscription_link', "/$uuid/all.txt?name=sublink-unknown&asn=unknown&mode=new")->first();
                    if ($product !== null) {
                        $product->delete();
                    }
                }
            } else {
                $success = false;
                $message = 'عملیات نامعتبر است.';
            }
        } catch (\Throwable $th) {
            $success = false;
            $message = 'خطا در اجرای عملیات: ' . $th->getMessage();
            Log::error($message);
        }
        // اگر chat_id وجود داشت، پیام نتیجه را به کاربر ارسال کن
        if (isset($this->extra['chat_id'])) {
            try {
                $telegramService = App::make(\App\Services\TelegramService::class);
                if ($success) {
                    $telegramService->sendMessage($this->extra['chat_id'], 'عملیات با موفقیت انجام شد.');
                } else {
                    $telegramService->sendMessage($this->extra['chat_id'], 'خطا: ' . $message);
                }
            } catch (\Throwable $th) {
                Log::error('خطا در ارسال پیام نتیجه به کاربر: ' . $th->getMessage());
            }
        }
    }
}
