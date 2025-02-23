<?php
namespace App\Http\Controllers;

use App\Http\Controllers\CustomTextController;
use App\Http\Controllers\SubscriptionProcessController;
use App\Http\Controllers\AccountProcessController;

use App\Models\User;
use App\Services\TelegramMessageFormatter;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TelegramWebhookController extends Controller
{
    private TelegramService $telegramService;
    private CustomTextController $customTextCtrl;
    private SubscriptionProcessController $subscriptionProcessCtrl;
    private TransactionController $transactionCntrl;
    private GeneralController $generalCntrl;
    private AccountProcessController $accountProcessCtrl;
    private AuthController $authCntrl;
    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService         = $telegramService;
        $this->customTextCtrl          = new CustomTextController();
        $this->subscriptionProcessCtrl = new SubscriptionProcessController($this->telegramService);
        $this->transactionCntrl        = new TransactionController($this->telegramService);
        $this->generalCntrl            = new GeneralController();
        $this->accountProcessCtrl      = new AccountProcessController($this->telegramService);
        $this->authCntrl               = new AuthController();
    }

    public function handle(Request $request)
    {
        try {
            // handle the first time bit start
            if ($this->is_first_time_bot_start_event()) {
                return response()->json(['status' => 'success']);
            }
            $update = $request->all();

            // پردازش callback queries (دکمه‌های اینلاین)
            if (isset($update['callback_query'])) {
                return $this->handleCallbackQuery($update['callback_query']);
            }

            $message = $update['message'] ?? null;
            if (! $message) {
                return response()->json(['status' => 'success']);
            }

            $chatId = $message['chat']['id'];

            // check the chatId is exist in users on account_id
            $isChannelMember = $this->checkChannelLock();
            if (! $isChannelMember) {
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
        ///

        // پردازش دستورات
        if (str_starts_with($text, '/')) {
            return $this->processCommand($text);
        }
        // check if text is a menu item
        $menuItemCtrl = new MainMenuItemController();
        $menuItem     = $menuItemCtrl->getMenuItemByAliasName($text);
        if ($menuItem) {
            $response = $this->processMenuCommand($menuItem);
            // if response == true or false or null, don't return anything
            if ($response == true || $response == false || $response == null || $response == 1 || $response == 0) {
                return "";
            }
            return $response;
        }
        // return main menu items
        $chatId = $this->getCurrentChatId();
        $this->generalCntrl->return_main_menu_items($chatId, $text);

        return "پیام متنی شما دریافت شد: " . $text;
    }
    private function processMenuCommand($menuItem)
    {
        $this->addNewBotLog('menu', "وارد منوی {$menuItem->name} ربات شد.", 'show');
        $chatId = $this->getCurrentChatId();
        switch ($menuItem->name) {
            case 'خرید اشتراک':
                return $this->subscriptionProcessCtrl->buySubscriptionMenu($chatId);
                break;
            case 'اطلاعات حساب':
                return $this->accountProcessCtrl->accountDetails($chatId);
                break;
            case 'سابقه خرید':
                return $this->subscriptionProcessCtrl->buyHistory($chatId);
                break;
            // case 'پشتیبانی':
            //     return $this->supports();
            //     break;
            case 'آموزش استفاده و سوالات متداول':
                return $this->generalCntrl->getFaqs($chatId);
                break;
            case 'دانلود برنامه':
                return $this->generalCntrl->appDownload($chatId);
                break;
            // case 'گیفت کارت':
            //     return $this->giftCard();
            //     break;
            // case 'اکانت آزمایشی':
            //     return $this->testAccount();
            //     break;
            case 'webapp':
                return $this->authCntrl->generate_auto_login_link(new Request(['account_id' => $chatId]));
                break;
            // case 'کسب درآمد':
            //     return $this->referral();
            //     break;
            // case 'خرید گیفت کارت':
            //     return $this->buyGiftCard();
            //     break;

            default:
                return $this->customTextCtrl->getText('error.menu.not_found');
                break;
        }
        return $this->customTextCtrl->getText('error.menu.not_found');
    }

    private function processPhotoMessage(array $message): string
    {
        try {
            $photos  = $message['photo'];
            $photo   = end($photos); // بزرگترین سایز عکس
            $fileId  = $photo['file_id'];
            $caption = $message['caption'] ?? '';
            $chatId  = $message['chat']['id'];

            // دریافت اطلاعات فایل از تلگرام
            $fileInfo = $this->telegramService->getFile($fileId);
            if (! isset($fileInfo['result']['file_path'])) {
                \Log::error("خطا در دریافت اطلاعات فایل از تلگرام: " . json_encode($fileInfo));
                return "با پشتیبان ربات تماس بگیرید ،خطا در دریافت تصویر";
            }

            $request                 = new Request();
            $request->transaction_id = $this->transactionCntrl->addUserTranaction($chatId, 0, '000', 0);
            $request->img_src        = $fileInfo['result']['file_path']; // ارسال file_path به جای file_id
            $request->account_id     = $chatId;
            $request->user_text      = $caption ?? 'بدون متن';

            $imageTrCntrl = new TransactionImageController();
            $imageTrCntrl->saveNewTransactionImage($request);

            $message = "کاربر {$chatId} یک عکس ارسال کرد ";
            $this->sendMessageToAdmin($chatId, $fileId, $message, 'image');
            return "";
        } catch (\Throwable $th) {
            \Log::error("خطا در پردازش تصویر: " . $th->getMessage());
            return "با پشتیبان ربات تماس بگیرید ،خطا در دریافت تصویر";
        }
    }

    private function processDocumentMessage(array $message): string
    {
        $document = $message['document'];
        $fileId   = $document['file_id'];
        $fileName = $document['file_name'] ?? 'بدون نام';
        $mimeType = $document['mime_type'] ?? 'نامشخص';

        return "فایل شما با نام {$fileName} و نوع {$mimeType} دریافت شد.";
    }

    private function processLocationMessage(array $message): string
    {
        $location  = $message['location'];
        $latitude  = $location['latitude'];
        $longitude = $location['longitude'];

        return "موقعیت مکانی شما در مختصات {$latitude}, {$longitude} دریافت شد.";
    }

    private function processVoiceMessage(array $message): string
    {
        $voice    = $message['voice'];
        $fileId   = $voice['file_id'];
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
        $video    = $message['video'];
        $fileId   = $video['file_id'];
        $duration = $video['duration'];
        $caption  = $message['caption'] ?? '';

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
        $contact     = $message['contact'];
        $phoneNumber = $contact['phone_number'];
        $firstName   = $contact['first_name'];
        $lastName    = $contact['last_name'] ?? '';

        return "اطلاعات تماس دریافت شد:\nنام: {$firstName} {$lastName}\nشماره تماس: {$phoneNumber}";
    }

    private function processCommand(string $text): string
    {
        $parts   = explode(' ', $text);
        $command = $parts[0];
        $ref     = $parts[1] ?? null;
        $ref != null ? $this->handleReferralCommand($text) : null;
        if ($ref != null) {
            $command = '/start';
        }

        $response = match ($command) {
            '/start' => $this->handleStartCommand($text),
            '/restart' => $this->handleStartCommand($text),
            '/help' => $this->handleHelpCommand(),
            '/menu' => $this->handleMenuCommand(),
            default => $this->customTextCtrl->getText('error.command.not_found')
        };
        return $response;
    }
    public function checkChannelLock()
    {
        try {

            $chatId          = $this->getCurrentChatId();
            $channelLockCtrl = new ChannelLockController();
            $channels        = $channelLockCtrl->getAllActiveChannelLock();
            $opr             = [];
            if ($channels->count() > 0) {
                foreach ($channels as $channel => $value) {
                    $isChannelMember = $this->telegramService->checkChatIdIsChannelMember($chatId, $value->channel_id);
                    if (! $isChannelMember) {
                        array_push($opr, [
                            [
                                'text' => "$value->channel_id",
                                'url'  => "https://t.me/$value->channel_id",
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

            return true;
            //code...
        } catch (\Throwable $th) {
            \Log::error("خطا در پردازش checkChannelLock: " . $th->getMessage());
            return true;
        }
    }

    private function handleStartCommand(String $message, ): string
    {
        try {
            $chatId            = $this->getCurrentChatId();
            $firstName         = $this->getCurrentChatFirstName();
            $lastName          = $this->getCurrentChatLastName();
            $userName          = $this->getCurrentChatUserName();
            $referralLogsCntrl = new ReferralLogsController();
            $botUserCtrl       = new BotUserController();

            $botUserCtrl->hasRegistred($chatId, $userName, $firstName, $lastName);

            $welcomeFormats = $this->customTextCtrl->getText('action.welcome.message', [
                'name'     => $firstName,
                'lastName' => $lastName,
                'website'  => 'https://powerps.ir',
            ]);

            $formatter = new TelegramMessageFormatter($this->telegramService);
            $message   = $formatter
                ->addFormattedText('', $welcomeFormats)
                ->getMessage();
            $this->generalCntrl->return_main_menu_items($chatId,$message);
            return '';

        } catch (\Throwable $th) {
            \Log::error("خطا در پردازش handleStartCommand: " . $th->getMessage());
            return $this->customTextCtrl->getText('error.server_error');

        }
    }
    public function handleHelpCommand($action = null): string
    {
        if ($action == 'faq') {
            return $this->generalCntrl->getFaqs($this->getCurrentChatId());
        }
        if ($action == 'app') {
            return $this->generalCntrl->appDownload();
        }
        $text = $this->customTextCtrl->getText('action.help.message');
        $this->generalCntrl->return_main_menu_items($this->getCurrentChatId(), $text);
        return "";
    }

    public function handleReferralCommand($text): string
    {
        try {
            $parts             = explode('=', $text);
            $command_path      = $parts[0];
            $ref               = $parts[1] ?? null;
            $command           = strtolower(explode(' ', $text)[0]);
            $chatId            = $this->getCurrentChatId();
            $firstName         = $this->getCurrentChatFirstName();
            $lastName          = $this->getCurrentChatLastName();
            $userName          = $this->getCurrentChatUserName();
            $referralLogsCntrl = new ReferralLogsController();
            $botUserCtrl       = new BotUserController();

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
            ['راهنما' => 'help', 'بازگشت' => 'back'],
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
        $chatId          = $callbackQuery['from']['id'];
        $data            = $callbackQuery['data'];
        $callbackQueryId = $callbackQuery['id'];
        // checl is force replay
        $this->handleAwaitingReply($chatId, $data);

        \Log::info('Callback query data: ' . json_encode($data));
        // explode the data to get the action
        $actionList = explode('-', $data);

        $action   = $actionList[0];
        $response = match ($action) {
            'buySubscription' => $this->subscriptionProcessCtrl->buySubscriptionAction($chatId, $actionList[1]),
            'buySubscriptionByLocation' => $this->subscriptionProcessCtrl->buySubscriptionByLocationAction($chatId, $actionList[1]),
            'offlineGateway' => $this->subscriptionProcessCtrl->handle_offline_add_balance($chatId, $actionList[1]),
            'buyHistory' => $this->subscriptionProcessCtrl->subBuyHistory($chatId, $actionList[1]),
            'recharge' => $this->subscriptionProcessCtrl->recharge($chatId, $actionList[1]),
            'remark' => $this->subscriptionProcessCtrl->remark($chatId, $actionList[1]),
            'accountTransactions' => $this->accountProcessCtrl->accountTransactions($chatId),
            'accountSubAccounts' => $this->accountProcessCtrl->accountSubAccounts($chatId),
            'accountAddBalance' => $this->accountProcessCtrl->accountAddBalance($chatId),
            'accountSubAccountsZarinpal' => $this->accountProcessCtrl->handleActionAddBalanceZarinpal($chatId),
            'accountSubAccountsNowpayment' => $this->accountProcessCtrl->handleActionAddBalanceNowpayments($chatId),
            'addBalanceReply' => $this->accountProcessCtrl->addBalanceReply($chatId, $actionList[1]),
            'help' => $this->handleHelpCommand( $actionList[1]),
            'faqs' => $this->generalCntrl->subFaq($chatId, $actionList[1]),
            'appDownload' => $this->generalCntrl->appDownload($chatId),
            'subAppDownloadOs' => $this->generalCntrl->subAppDownloadOs($chatId, $actionList[1]),
            'subAppDownloadApp' => $this->generalCntrl->subAppDownloadApp($chatId, $actionList[1]),


            default => $this->customTextCtrl->getText('error.action.not_found')
        };

        // ارسال پاسخ به callback query
        $this->telegramService->answerCallbackQuery(
            $callbackQueryId,
            $this->customTextCtrl->getText('action.process.on_progress'),
            false
        );


        if ($response != "" || $response != null || $response != " ") {
            $this->telegramService->sendMessage($chatId, $response);
        }
        return response()->json(['status' => 'success']);
    }
    private function handleCancelPayment(string $chatId): string
    {

        $this->telegramService->sendMessage($chatId, 'پرداخت با موفقیت لغو شد.');
        return '';
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
                'keyboard'          => $buttons,
                'resize_keyboard'   => true,
                'one_time_keyboard' => true,
            ]),
        ]);

        return '';
    }

    private function handleAction3(string $chatId): string
    {
        // درخواست موقعیت مکانی با کیبورد مخصوص
        $buttons = [[['text' => 'ارسال موقعیت مکانی', 'request_location' => true]]];
        $this->telegramService->sendMessage($chatId, 'لطفاً موقعیت مکانی خود را به اشتراک بگذارید:', [
            'reply_markup' => json_encode([
                'keyboard'          => $buttons,
                'resize_keyboard'   => true,
                'one_time_keyboard' => true,
            ]),
        ]);

        return '';
    }

    private function handleAwaitingReply(string $chatId, string $text): void
    {
        $awaitingType = $this->getAwaitingReplyType($chatId);

        switch ($awaitingType) {
            case 'action_1_reply':
                $this->telegramService->sendMessage($chatId, "نام شما با موفقیت ثبت شد");
                $this->clearAwaitingReply($chatId);
                break;
            case 'remark_reply':
                $this->subscriptionProcessCtrl->remarkReply($chatId, $text);
                // $this->clearAwaitingReply($chatId);
                break;
            case 'add_balance_reply':
                $this->accountProcessCtrl->addBalanceReply($chatId, $text);
                // $this->clearAwaitingReply($chatId);
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
            $resualt = $this->telegramService->sendPhoto($admin_id, $image_url, $text);

            return response()->json($resualt, 200);
        } else {
            $result = $this->telegramService->sendMessage($admin_id, $text);
        }
    }
    private function addNewBotLog($type, $message, $event)
    {
        $logCtrl = new LogController();
        $logCtrl->addNewLog($type, $message, $this->getCurrentChatId(), $this->getCurrentChatUserName(), $event);
        return true;
    }
    private function is_first_time_bot_start_event()
    {
        // check if the bot is started for the first time
        // check we have a user with admin id or not
        $admin = User::where('role', 'admin')->first();
        if ($admin == null) {
            // send message in telegram to first you have to login in webapp and broken other process
            $this->telegramService->sendMessage($this->getCurrentChatId(), "برای شروع ربات ابتدا می بایست وارد وب اپلیکیشن شوید و تنظیمات ربات را انجام بدهید");
            $authCtrl = new AuthController();
            $authCtrl->createFirstAdminUser();
            return true;
        }
        return false;
    }
}
