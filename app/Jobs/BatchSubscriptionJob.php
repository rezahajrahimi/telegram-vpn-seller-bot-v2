<?php

namespace App\Jobs;

use App\Models\Pannel;
use App\Services\BatchPanelOperationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
        $adminId = env(key: 'TELEGRAM_ADMIN_ID');
        $telegramService = app(\App\Services\TelegramService::class);
        $operationService = app(BatchPanelOperationService::class);

        try {
            $action = $this->action;
            $listOfConfigs = $this->listOfConfigs;
            $panelID = $this->panelID;
            $extra = $this->extra;
            $panel = Pannel::find($panelID);

            if (! isset($listOfConfigs)) {
                $success = false;
                $message = 'لیست پیکربندی‌ها ارسال نشده است.';

                return;
            }

            if (! $operationService->supportsPanel($panel)) {
                $success = false;
                $message = 'این نوع پنل از عملیات گروهی پشتیبانی نمی‌شود.';

                return;
            }

            $actionLabels = [
                'inc_days' => 'افزایش روزها',
                'dec_days' => 'کاهش روزها',
                'modify_days' => 'تغییر روزها',
                'inc_vol' => 'افزایش حجم',
                'dec_vol' => 'کاهش حجم',
                'modify_vol' => 'تغییر حجم',
                'reset' => 'ریست',
                'active' => 'فعالسازی',
                'deactive' => 'غیرفعالسازی',
                'delete' => 'حذف',
            ];

            if (! array_key_exists($action, $actionLabels)) {
                $success = false;
                $message = 'عملیات نامعتبر است.';

                return;
            }

            if (in_array($action, ['inc_days', 'dec_days', 'modify_days'], true) && ! isset($extra['days'])) {
                $success = false;
                $message = 'تعداد روزها ارسال نشده است.';

                return;
            }

            if (in_array($action, ['inc_vol', 'dec_vol', 'modify_vol'], true) && ! isset($extra['vol'])) {
                $success = false;
                $message = 'مقدار حجم ارسال نشده است.';

                return;
            }

            $telegramService->sendMessage($adminId, 'عملیات ' . $actionLabels[$action] . ' شروع شد.');

            foreach ($listOfConfigs as $config) {
                $aa = is_array($config) ? $config : json_decode($config, true);
                $config = (array) $aa;
                $configName = $config['name'] ?? ($config['uuid'] ?? 'نامشخص');

                $result = $operationService->execute($action, $config, $panel, $extra);
                if (! $result) {
                    $success = false;
                    $message = 'خطا در اجرای عملیات روی پیکربندی: ' . $configName;
                    $telegramService->sendMessage($adminId, $message);
                    break;
                }

                $telegramService->sendMessage($adminId, 'عملیات ' . $actionLabels[$action] . ' برای ' . $configName . ' با موفقیت انجام شد.');
            }
        } catch (\Throwable $th) {
            $success = false;
            $message = 'خطا در اجرای عملیات: ' . $th->getMessage();
            Log::error($message);
        }

        try {
            if ($success) {
                $telegramService->sendMessage($adminId, 'عملیات با موفقیت به اتمام رسید.');
            } elseif ($message !== '') {
                $telegramService->sendMessage($adminId, 'خطا: ' . $message);
            }
        } catch (\Throwable $th) {
            Log::error('خطا در ارسال پیام نتیجه به مدیر: ' . $th->getMessage());
        }
    }
}
