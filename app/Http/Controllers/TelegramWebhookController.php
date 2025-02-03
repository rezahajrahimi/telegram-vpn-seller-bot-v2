<?php

namespace App\Http\Controllers;

use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use App\Services\TelegramMessageFormatter;

class TelegramWebhookController extends Controller
{
    private TelegramService $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    public function handle(Request $request)
    {
        try {
            $update = $request->all();

            // پردازش callback queries (دکمه‌های اینلاین)
            if (isset($update['callback_query'])) {
                return $this->handleCallbackQuery($update['callback_query']);
            }

            $message = $update['message'] ?? null;
            if (!$message) {
                return response()->json(['status' => 'success']);
            }

            $chatId = $message['chat']['id'];

            // check the chatId is exist in users on account_id
            $isChannelMember = $this->checkChannelLock();
            if (!$isChannelMember) {
                return response()->json(['status' => 'success']);
            }





            // نمایش وضعیت تایپ کردن
            $this->telegramService->sendChatAction($chatId, 'typing');

            // پردازش انواع مختلف پیام
            if (isset($message['text'])) {
                $response = $this->processTextMessage($message);
                // بررسی وضعیت کاربر برای دریافت پاسخ اجباری
                if ($this->awaitingReply($chatId)) {
                    $this->handleAwaitingReply($chatId, $message['text']);
                    return response()->json(['status' => 'success']);
                }
                $this->telegramService->sendMessage($chatId, $response);
            } elseif (isset($message['photo'])) {
                $response = $this->processPhotoMessage($message);
                $this->telegramService->sendMessage($chatId, $response);
            } elseif (isset($message['document'])) {
                $response = $this->processDocumentMessage($message);
                $this->telegramService->sendMessage($chatId, $response);
            } elseif (isset($message['location'])) {
                $response = $this->processLocationMessage($message);
                $this->telegramService->sendMessage($chatId, $response);
            } elseif (isset($message['voice'])) {
                $response = $this->processVoiceMessage($message);
                $this->telegramService->sendMessage($chatId, $response);
            } elseif (isset($message['video'])) {
                $response = $this->processVideoMessage($message);
                $this->telegramService->sendMessage($chatId, $response);
            } elseif (isset($message['contact'])) {
                $response = $this->processContactMessage($message);
                $this->telegramService->sendMessage($chatId, $response);
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('خطا در پردازش webhook تلگرام: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    private function processTextMessage(array $message): string
    {
        $text = $message['text'];

        // پردازش دستورات
        if (str_starts_with($text, '/')) {
            return $this->processCommand($text);
        }

        return "پیام متنی شما دریافت شد: " . $text;
    }

    private function processPhotoMessage(array $message): string
    {
        try {

            $photos = $message['photo'];
            $photo = end($photos); // بزرگترین سایز عکس
            $fileId = $photo['file_id'];
            $caption = $message['caption'] ?? '';
            $chatId = $message['chat']['id'];
            // در اینجا می‌توانید عکس را ذخیره یا پردازش کنید
            $transactionCntrl = new TransactionController();
            $imageTrCntrl = new TransactionImageController();
            \Log::info("fileId1111: $fileId");
            $transactionID = $transactionCntrl->addUserTranaction($chatId, 0, '000', 0);
            \Log::info("transactionID: $transactionID");
            $request = new Request();
            $request->transaction_id = $transactionID;
            $request->img_src = $fileId;
            $request->account_id = $chatId;
            $request->user_text = $caption ?? 'بدون متن';

            $imageTrCntrl->saveNewTransactionImage($request);
            $message = "کاربر {$chatId} یک عکس ارسال کرد ";
            $this->sendMessageToAdmin($chatId, $fileId, $message, 'image');
            return "عکس شما با موفقیت ذخیره شد.";
        } catch (\Throwable $th) {
            return "با پشتیبان ربات تماس بگیرید ،خطا در دریافت تصویر";
        }
    }

    private function processDocumentMessage(array $message): string
    {
        $document = $message['document'];
        $fileId = $document['file_id'];
        $fileName = $document['file_name'] ?? 'بدون نام';
        $mimeType = $document['mime_type'] ?? 'نامشخص';

        return "فایل شما با نام {$fileName} و نوع {$mimeType} دریافت شد.";
    }

    private function processLocationMessage(array $message): string
    {
        $location = $message['location'];
        $latitude = $location['latitude'];
        $longitude = $location['longitude'];

        return "موقعیت مکانی شما در مختصات {$latitude}, {$longitude} دریافت شد.";
    }

    private function processVoiceMessage(array $message): string
    {
        $voice = $message['voice'];
        $fileId = $voice['file_id'];
        $duration = $voice['duration'];

        // ذخیره فایل صوتی
        $fileInfo = $this->telegramService->getFile($fileId);
        if (isset($fileInfo['result']['file_path'])) {
            $fileContent = $this->telegramService->downloadFile($fileInfo['result']['file_path']);
            Storage::put("telegram/voices/{$fileId}.ogg", $fileContent);
        }

        return "پیام صوتی شما با مدت زمان {$duration} ثانیه دریافت شد.";
    }

    private function processVideoMessage(array $message): string
    {
        $video = $message['video'];
        $fileId = $video['file_id'];
        $duration = $video['duration'];
        $caption = $message['caption'] ?? '';

        // ذخیره ویدیو
        $fileInfo = $this->telegramService->getFile($fileId);
        if (isset($fileInfo['result']['file_path'])) {
            $fileContent = $this->telegramService->downloadFile($fileInfo['result']['file_path']);
            Storage::put("telegram/videos/{$fileId}.mp4", $fileContent);
        }

        return "ویدیوی شما با مدت زمان {$duration} ثانیه دریافت شد." .
            ($caption ? "\nکپشن: {$caption}" : '');
    }

    private function processContactMessage(array $message): string
    {
        $contact = $message['contact'];
        $phoneNumber = $contact['phone_number'];
        $firstName = $contact['first_name'];
        $lastName = $contact['last_name'] ?? '';

        return "اطلاعات تماس دریافت شد:\nنام: {$firstName} {$lastName}\nشماره تماس: {$phoneNumber}";
    }

    private function processCommand(string $text): string
    {
        $parts = explode(' ', $text);
        $command = $parts[0];
        $ref = $parts[1] ?? null;
        $ref != null ? $this->handleReferralCommand($text) : null;
        if ($ref != null) {
            $command = '/start';
        }


        $response = match ($command) {
            '/start' => $this->handleStartCommand($text),
            '/restart' => $this->handleStartCommand($text),
            '/help' => $this->handleHelpCommand(),
            '/menu' => $this->handleMenuCommand(),
            default => "دستور نامعتبر است. برای مشاهده لیست دستورات از /help استفاده کنید."
        };
        return $response;
    }
    public function checkChannelLock()
    {
        try {

            $chatId = $this->getCurrentChatId();
            $channelLockCtrl = new ChannelLockController();
            $channels = $channelLockCtrl->getAllActiveChannelLock();
            $opr = [];
            if ($channels->count() > 0) {
                foreach ($channels as $channel => $value) {
                    $isChannelMember = $this->telegramService->checkChatIdIsChannelMember($chatId, $value->channel_id);
                    if (!$isChannelMember) {
                        array_push($opr, [
                            [
                                'text' => "$value->channel_id",
                                'url' => "https://t.me/$value->channel_id",
                            ],
                        ]);
                    }
                }
                if (count($opr) > 0) {
                    $channelLockMenuCtrl = new ChannelLockMenuItemController();

                    $text = $channelLockMenuCtrl->getChannelLockMenuText();

                    $this->telegramService->sendMessageWithInlineKeyboard($chatId, $text, $opr);
                    return false;
                }
            }
            \Log::info("checkChannelLock=> true");

            return true;
            //code...
        } catch (\Throwable $th) {
            \Log::error("خطا در پردازش checkChannelLock: " . $th->getMessage());
            return true;
        }
    }

    private function handleStartCommand(String $message,): string
    {
        try {

            $chatId = $this->getCurrentChatId();
            $firstName = $this->getCurrentChatFirstName();
            $lastName = $this->getCurrentChatLastName();
            $userName = $this->getCurrentChatUserName();
            $referralLogsCntrl = new ReferralLogsController();
            $botUserCtrl = new BotUserController();

            $botUserCtrl->hasRegistred($chatId, $userName, $firstName, $lastName);







            // $formatter = new TelegramMessageFormatter($this->telegramService);
            // $message = $formatter
            //     ->addBold("سلام! به ربات ما خوش آمدید. 👋")
            //     ->addNewLine()
            //     ->addNewLine()
            //     ->addText("برای شروع می‌توانید از دستورات زیر استفاده کنید:")
            //     ->addNewLine()
            //     ->addCode("/help")
            //     ->addText(" - راهنمای دستورات")
            //     ->addNewLine()
            //     ->addCode("/menu")
            //     ->addText(" - منوی اصلی")
            //     ->addNewLine()
            //     ->addNewLine()
            //     ->addItalic("برای اطلاعات بیشتر به ")
            //     ->addLink("وب‌سایت ما", "https://example.com")
            //     ->addText(" مراجعه کنید.")
            //     ->getMessage();

            $menu = new MainMenuItemController();
        $menuItem = $menu->getAllActivatedMainMenuItems();
        $opr = [];

        if ($menuItem[0]->name == 'خرید اشتراک') {
            array_push($opr, [['text' => $menuItem[0]->alias_name, 'callback_data' => "main-{$menuItem[0]->id}"]]);
            // remove first item from menuItem list because we allreade added it to $opr
            $menuItem = $menuItem->slice(1);
        }
        $countOfMenuItem = count($menuItem);
        for ($i = 0; $i < $countOfMenuItem; $i += 2) {
            $pair = $menuItem->slice($i, 2);
            $index = 1;

            foreach ($pair as $key => $value) {
                if ($index % 2 == 1) {
                    $firstRowIndicator = ['text' => $value->alias_name, 'callback_data' => "main-{$value->id}"];
                    $index += 1;
                } elseif ($index % 2 == 0) {
                    array_push($opr, [$firstRowIndicator, ['text' => $value->alias_name, 'callback_data' => "main-{$value->id}"]]);
                    $index += 1;
                    break;
                }
            }
        }
        // because of if count of menuItem is odd we need to add last row indicator
        if ($countOfMenuItem % 2 == 1) {
            $lastRowIndicator = ['text' => $menuItem[$countOfMenuItem - 1]->alias_name, 'callback_data' => "main-{$menuItem[$countOfMenuItem - 1]->id}"];
            array_push($opr, [$firstRowIndicator]);
        }
            // extract menu alias_name
        

            $settingCtrl = new SettingController();
            $this->message = $settingCtrl->getWelcomeMessage();

            $this->telegramService->sendMessageWithKeyboard($chatId, $message, $opr);

            return '';
        } catch (\Throwable $th) {
            \Log::error("خطا در پردازش handleStartCommand: " . $th->getMessage());
            return "خطا در پردازش ";
        }
    }
    public function handleHelpCommand(): string
    {
        return "help";
    }

    public function handleReferralCommand($text): string
    {
        try {
            $parts = explode('=', $text);
            $command_path = $parts[0];
            $ref = $parts[1] ?? null;
            $command = strtolower(explode(' ', $text)[0]);
            $chatId = $this->getCurrentChatId();
            $firstName = $this->getCurrentChatFirstName();
            $lastName = $this->getCurrentChatLastName();
            $userName = $this->getCurrentChatUserName();
            $referralLogsCntrl = new ReferralLogsController();
            $botUserCtrl = new BotUserController();

            $result = $botUserCtrl->hasRegistred($chatId, $userName, $firstName, $lastName);
            if ($result == 1) {
                $saveRef = $referralLogsCntrl->check_user_has_referral_and_create($chatId, $ref);
            }
            return '/start';
        } catch (\Throwable $th) {
            \Log::error("خطا در پردازش handleReferralCommand: " . $th->getMessage());
            return '/start';
        }
    }

    private function handleMenuCommand(): string
    {
        $chatId = $this->getCurrentChatId();

        $buttons = [
            ['ارسال موقعیت مکانی' => 'send_location', 'ارسال شماره تماس' => 'send_contact'],
            ['آپلود فایل' => 'upload_file', 'ارسال عکس' => 'send_photo'],
            ['راهنما' => 'help', 'بازگشت' => 'back']
        ];

        $this->telegramService->sendMessageWithInlineKeyboard(
            $chatId,
            "لطفاً یکی از گزینه‌های زیر را انتخاب کنید:",
            $buttons
        );

        return '';
    }

    private function handleCallbackQuery(array $callbackQuery): \Illuminate\Http\JsonResponse
    {
        $chatId = $callbackQuery['from']['id'];
        $data = $callbackQuery['data'];
        $callbackQueryId = $callbackQuery['id'];

        $response = match ($data) {
            'action_1' => $this->handleAction1($chatId),
            'action_2' => $this->handleAction2($chatId),
            'action_3' => $this->handleAction3($chatId),
            'action_4' => $this->handleAction4($chatId),
            default => "عملیات نامعتبر است."
        };

        // ارسال پاسخ به callback query
        $this->telegramService->answerCallbackQuery(
            $callbackQueryId,
            "عملیات با موفقیت انجام شد",
            false
        );

        $this->telegramService->sendMessage($chatId, $response);
        return response()->json(['status' => 'success']);
    }

    private function handleAction1(string $chatId): string
    {
        // مثال درخواست اطلاعات از کاربر
        $this->setAwaitingReply($chatId, 'action_1_reply');
        return $this->telegramService->forceReply($chatId, "لطفاً نام خود را وارد کنید:");
    }

    private function handleAction2(string $chatId): string
    {
        // درخواست شماره تماس با کیبورد مخصوص
        $buttons = [[['text' => 'ارسال شماره تماس', 'request_contact' => true]]];
        $this->telegramService->sendMessage($chatId, 'لطفاً شماره تماس خود را به اشتراک بگذارید:', [
            'reply_markup' => json_encode([
                'keyboard' => $buttons,
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);

        return '';
    }

    private function handleAction3(string $chatId): string
    {
        // درخواست موقعیت مکانی با کیبورد مخصوص
        $buttons = [[['text' => 'ارسال موقعیت مکانی', 'request_location' => true]]];
        $this->telegramService->sendMessage($chatId, 'لطفاً موقعیت مکانی خود را به اشتراک بگذارید:', [
            'reply_markup' => json_encode([
                'keyboard' => $buttons,
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);

        return '';
    }

    private function handleAwaitingReply(string $chatId, string $text): void
    {
        $awaitingType = $this->getAwaitingReplyType($chatId);

        switch ($awaitingType) {
            case 'action_1_reply':
                $this->telegramService->sendMessage($chatId, "نام شما با موفقیت ثبت شد: {$text}");
                $this->clearAwaitingReply($chatId);
                break;
                // سایر موارد...
        }
    }

    // متدهای کمکی برای مدیریت وضعیت انتظار پاسخ
    private function setAwaitingReply(string $chatId, string $type): void
    {
        // می‌توانید از کش یا دیتابیس استفاده کنید
        Cache::put("awaiting_reply_{$chatId}", $type, now()->addMinutes(5));
    }

    private function awaitingReply(string $chatId): bool
    {
        return Cache::has("awaiting_reply_{$chatId}");
    }

    private function getAwaitingReplyType(string $chatId): ?string
    {
        return Cache::get("awaiting_reply_{$chatId}");
    }

    private function clearAwaitingReply(string $chatId): void
    {
        Cache::forget("awaiting_reply_{$chatId}");
    }

    private function getCurrentChatId(): string
    {
        return request()->input('message.chat.id');
    }
    private function getCurrentChatFirstName(): string
    {
        return request()->input('message.from.first_name') ?? '';
    }
    private function getCurrentChatLastName(): string
    {
        return request()->input('message.from.last_name') ?? '';
    }
    private function getCurrentChatUserName(): string
    {
        return request()->input('message.from.username') ?? '';
    }
    public function sendMessageToAdmin($chat_id, $image_url, $text, $messageType)
    {
        $settingCtrl = new SettingController();

        $admin_id = $settingCtrl->getAdminId();
        if ($messageType == 'image') {
            $result = $this->telegramService->imageMessage($image_url, $admin_id, $text);

            return response()->json($result, 200);
        } else {
            $result = $this->telegramService->sendMessage($admin_id, $text);
        }
    }
}
