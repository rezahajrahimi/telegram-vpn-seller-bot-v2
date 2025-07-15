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
        $adminId = env('TELEGRAM_ADMIN_ID');
        try {
            $action = $this->action;
            $listOfConfigs = $this->listOfConfigs;
            $panelID = $this->panelID;
            $extra = $this->extra;
            $hiddifyPannelCntrl = App::make(HiddifyPannelController::class);
            $telegramService = App::make(\App\Services\TelegramService::class);



            if (!isset($listOfConfigs)) {
                $success = false;
                $message = 'لیست پیکربندی‌ها ارسال نشده است.';
                return;
            }
            // ارسال پیام به مدیر
            if ($action == 'inc_days') {
                $telegramService->sendMessage($adminId, 'عملیات افزایش روزها شروع شد.');

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
                            $telegramService->sendMessage($adminId, 'خطا در افزودن روزها به پیکربندی: ' . $config['name']);
                            break;
                        }
                        $telegramService->sendMessage($adminId, 'عملیات افزایش روزها به پیکربندی: ' . $config['name'] . ' با موفقیت انجام شد.');
                    }
                }
            } elseif ($action == 'modify_days') {
                $days = $extra['days'] ?? null;
                if (!isset($days)) {
                    $success = false;
                    $message = 'تعداد روزها ارسال نشده است.';
                } else {
                    $telegramService->sendMessage($adminId, 'عملیات تغییر روزها شروع شد.');
                    foreach ($listOfConfigs as $config) {
                        $aa = is_array($config) ? $config : json_decode($config, true);
                        $config = (array) $aa;
                        $comment = "تغییر روزها در " . Verta::now() . " به میزان " . $days . " روز";
                        $uuid = $config['uuid'];
                        $name = $config['name'];
                        $params = [
                            'uuid' => "$uuid",
                            'name' => "$name",
                            'package_days' => $days,
                            'mode' => 'no_reset',
                            'comment' => "$comment",
                        ];
                        $result = $hiddifyPannelCntrl->sendPatchRequestToHiddifyPannel($panelID, "/api/v2/admin/user/$uuid/", $params);
                        if ($result === false) {
                            $success = false;
                            $message = 'خطا در تغییر روزها به پیکربندی: ' . $config['name'];
                            $telegramService->sendMessage($adminId, 'خطا در تغییر روزها به پیکربندی: ' . $config['name']);
                            break;
                        }
                        $telegramService->sendMessage($adminId, 'عملیات تغییر روزها به میزان ' . $days . ' روز در پیکربندی: ' . $config['name'] . ' با موفقیت انجام شد.');
                    }
                }
            } elseif ($action == 'dec_days') {
                $days = $extra['days'] ?? null;
                if (!isset($days)) {
                    $success = false;
                    $message = 'تعداد روزها ارسال نشده است.';
                } else {
                    $telegramService->sendMessage($adminId, 'عملیات کاهش روزها شروع شد.');
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
                            $telegramService->sendMessage($adminId, 'خطا در کاهش روزها به پیکربندی: ' . $config['name']);
                            break;
                        }
                        $telegramService->sendMessage($adminId, 'عملیات کاهش روزها به میزان ' . $days . ' روز در پیکربندی: ' . $config['name'] . ' با موفقیت انجام شد.');
                    }
                }
            } elseif ($action == 'reset') {
                $telegramService->sendMessage($adminId, 'عملیات ریست شروع شد.');
                foreach ($listOfConfigs as $config) {
                    $aa = is_array($config) ? $config : json_decode($config, true);
                    $config = (array) $aa;
                    $uuid = $config['uuid'];
                    $name = $config['name'];
                    $today = date('Y-m-d');
                    $vol = $config['usageLimitGB'];
                    $day = $config['packageDays'];
                    $comment = "ریست در " . Verta::now();
                    $today = date('Y-m-d');
                    $params = [
                        'uuid' => "$uuid",
                        'name' => "$name",
                        'current_usage_GB' => 0,
                        'usage_limit_GB' => $vol,
                        'package_days' => $day,
                        'mode' => 'no_reset',
                        'start_date' => "$today",
                        'comment' => "$comment",
                    ];
                    $result = $hiddifyPannelCntrl->sendPatchRequestToHiddifyPannel($panelID, "/api/v2/admin/user/$uuid/", $params);
                    if ($result === false) {
                        $success = false;
                        $message = 'خطا در ریست روزها به پیکربندی: ' . $config['name'];
                        $telegramService->sendMessage($adminId, 'خطا در ریست به پیکربندی: ' . $config['name']);
                        break;
                    }
                    $telegramService->sendMessage($adminId, 'عملیات ریست به پیکربندی: ' . $config['name'] . ' با موفقیت انجام شد.');
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
                            $telegramService->sendMessage($adminId, 'خطا در افزایش حجم پیکربندی: ' . $config['name']);
                            break;
                        }
                        $telegramService->sendMessage($adminId, 'عملیات افزایش حجم به میزان ' . $vol . ' GB در پیکربندی: ' . $config['name'] . ' با موفقیت انجام شد.');
                    }
                }
            } elseif ($action == 'dec_vol') {
                $vol = $extra['vol'] ?? null;
                if (!isset($vol)) {
                    $success = false;
                    $message = 'مقدار حجم ارسال نشده است.';
                } else {
                    $telegramService->sendMessage($adminId, 'عملیات کاهش حجم شروع شد.');
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
                            $telegramService->sendMessage($adminId, 'خطا در کاهش حجم پیکربندی: ' . $config['name']);
                            break;
                        }
                        $telegramService->sendMessage($adminId, 'عملیات کاهش حجم به میزان ' . $vol . ' GB در پیکربندی: ' . $config['name'] . ' با موفقیت انجام شد.');
                    }
                }
            } elseif ($action == 'modify_vol') {
                $vol = $extra['vol'] ?? null;
                if (!isset($vol)) {
                    $success = false;
                    $message = 'مقدار حجم ارسال نشده است.';
                } else {
                    $telegramService->sendMessage($adminId, 'عملیات تغییر حجم شروع شد.');
                    foreach ($listOfConfigs as $config) {
                        $aa = is_array($config) ? $config : json_decode($config, true);
                        $config = (array) $aa;
                        $comment = "تغییر حجم در " . Verta::now() . " به میزان " . $vol . " GB";
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
                            $message = 'خطا در تغییر حجم پیکربندی: ' . $config['name'];
                            $telegramService->sendMessage($adminId, 'خطا در تغییر حجم پیکربندی: ' . $config['name']);
                            break;
                        }
                        $telegramService->sendMessage($adminId, 'عملیات تغییر حجم به میزان ' . $vol . ' GB در پیکربندی: ' . $config['name'] . ' با موفقیت انجام شد.');
                    }
                }
            } elseif ($action == 'reset_vol') {
                $telegramService->sendMessage($adminId, 'عملیات ریست حجم شروع شد.');
                foreach ($listOfConfigs as $config) {
                    $aa = is_array($config) ? $config : json_decode($config, true);
                    $config = (array) $aa;

                    $uuid = $config['uuid'];
                    $name = $config['name'];
                    $comment = "ریست حجم در " . Verta::now();
                    $params = [
                        'uuid' => "$uuid",
                        'name' => "$name",
                        'current_usage_GB' => 0,
                        'mode' => 'no_reset',
                        'comment' => "$comment",
                    ];
                    $result = $hiddifyPannelCntrl->sendPatchRequestToHiddifyPannel($panelID, "/api/v2/admin/user/$uuid/", $params);
                    if ($result === false) {
                        $success = false;
                        $message = 'خطا در ریست حجم پیکربندی: ' . $config['name'];
                        $telegramService->sendMessage($adminId, 'خطا در ریست حجم پیکربندی: ' . $config['name']);
                        break;
                    }
                    $telegramService->sendMessage($adminId, 'عملیات ریست حجم به پیکربندی: ' . $config['name'] . ' با موفقیت انجام شد.');
                }
            } elseif ($action == 'active') {
                $telegramService->sendMessage($adminId, 'عملیات فعالسازی شروع شد.');
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
                        $telegramService->sendMessage($adminId, 'خطا در فعالسازی پیکربندی: ' . $config['name']);
                        break;
                    }
                    $telegramService->sendMessage($adminId, 'عملیات فعالسازی پیکربندی: ' . $config['name'] . ' با موفقیت انجام شد.');
                }
            } elseif ($action == 'deactive') {
                $telegramService->sendMessage($adminId, 'عملیات غیرفعالسازی شروع شد.');
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
                        $telegramService->sendMessage($adminId, 'خطا در غیرفعال سازی پیکربندی: ' . $config['name']);
                        break;
                    }
                    $telegramService->sendMessage($adminId, 'عملیات غیرفعال سازی پیکربندی: ' . $config['name'] . ' با موفقیت انجام شد.');
                }
            } elseif ($action == 'delete') {
                $telegramService->sendMessage($adminId, 'عملیات حذف شروع شد.');
                foreach ($listOfConfigs as $config) {
                    $aa = is_array($config) ? $config : json_decode($config, true);
                    $config = (array) $aa;
                    $uuid = $config['uuid'];
                    $result = $hiddifyPannelCntrl->deleteUserOfHiddifyPanel($panelID, $uuid);
                    if ($result === false) {
                        Log::error('خطا در حذف ' . $config['name']);
                        $success = false;
                        $message = 'خطا در حذف پیکربندی: ' . $config['name'];
                        $telegramService->sendMessage($adminId, 'خطا در حذف پیکربندی: ' . $config['name']);
                        break;
                    }
                    // حذف از جدول products
                    $product = Product::where('subscription_link', "/$uuid/all.txt?name=sublink-unknown&asn=unknown&mode=new")->first();
                    if ($product !== null) {
                        $product->delete();
                    }
                    $telegramService->sendMessage($adminId, 'عملیات حذف پیکربندی: ' . $config['name'] . ' با موفقیت انجام شد.');
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
        // ارسال پیام نتیجه به مدیر
        try {
            if ($success) {
                $telegramService->sendMessage($adminId, 'عملیات با موفقیت به اتمام رسید.');
            } else {
                $telegramService->sendMessage($adminId, 'خطا: ' . $message);
            }
        } catch (\Throwable $th) {
            Log::error('خطا در ارسال پیام نتیجه به مدیر: ' . $th->getMessage());
        }

    }
}
