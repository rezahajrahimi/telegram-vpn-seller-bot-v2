<?php
namespace App\Http\Controllers;

use App\Http\Controllers\AccountProcessController;
use App\Http\Controllers\CustomTextController;
use App\Http\Controllers\SubscriptionProcessController;
use App\Models\User;
use App\Models\Transaction;
use App\Services\TelegramMessageFormatter;
use App\Services\TelegramService;
use App\Services\TelegramCallbackHandler;
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
    private BlockedUserController $blockedUserCtrl;
    private UserController $userCtrl;
    private TelegramCallbackHandler $callbackHandler;
    private $chatId;

    public function __construct(TelegramService $telegramService, TelegramCallbackHandler $callbackHandler)
    {
        $this->telegramService = $telegramService;
        $this->customTextCtrl = new CustomTextController();
        $this->subscriptionProcessCtrl = new SubscriptionProcessController($this->telegramService);
        $this->transactionCntrl = new TransactionController();
        $this->generalCntrl = new GeneralController();
        $this->accountProcessCtrl = new AccountProcessController($this->telegramService);
        $this->authCntrl = new AuthController();
        $this->blockedUserCtrl = new BlockedUserController();
        $this->userCtrl = new UserController();
        $this->callbackHandler = $callbackHandler;
        $this->callbackHandler->setWebhookController($this);
    }

    public function handle(Request $request)
    {
        try {
            // handle the first time bit start
            if ($this->is_first_time_bot_start_event()) {
                return response()->json(['status' => 'success']);
            }
            $update = $request->all();
            $this->chatId = $update['message']['chat']['id'] ?? $update['callback_query']['from']['id'] ?? null;

            try {
                if (isset($update['message'])) {
                    $isBlocked = $this->blockedUserCtrl->isBlocked($this->chatId);
                    if ($isBlocked) {
                        $text = $this->customTextCtrl->getText('error.blocked_user');
                        $this->telegramService->sendMessage($this->chatId, $text);
                        return response()->json(['status' => 'success']);
                    }
                }
            } catch (\Exception $e) {
                Log::error('خطا در پردازش webhook تلگرام: ' . $e->getMessage());
                // return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
            }

            // پردازش callback queries (دکمه‌های اینلاین)
            if (isset($update['callback_query'])) {
                return $this->handleCallbackQuery($update['callback_query']);
            }

            $message = $update['message'] ?? null;
            if (!$message) {
                return response()->json(['status' => 'success']);
            }

            $chatId = $this->chatId;

            // check the chatId is exist in users on account_id
            $isChannelMember = $this->checkChannelLock();
            if (!$isChannelMember) {
                return response()->json(['status' => 'success']);
            }

            // نمایش وضعیت تایپ کردن
            $this->telegramService->sendChatAction($this->chatId, 'typing');

            // پردازش انواع مختلف پیام
            if (isset($message['text'])) {
                $response = $this->processTextMessage($message);
                // بررسی وضعیت کاربر برای دریافت پاسخ اجباری
                if ($this->awaitingReply($this->chatId)) {
                    $this->handleAwaitingReply($this->chatId, $message['text']);
                    return response()->json(['status' => 'success']);
                }
                $this->sendResponseIfNotEmpty($this->chatId, $response);
            } elseif (isset($message['photo'])) {
                $response = $this->processPhotoMessage($message);
                $this->sendResponseIfNotEmpty($this->chatId, $response);
            } elseif (isset($message['document'])) {
                $response = $this->processDocumentMessage($message);
                $this->sendResponseIfNotEmpty($this->chatId, $response);
            } elseif (isset($message['location'])) {
                $response = $this->processLocationMessage($message);
                $this->sendResponseIfNotEmpty($this->chatId, $response);
            } elseif (isset($message['voice'])) {
                $response = $this->processVoiceMessage($message);
                $this->sendResponseIfNotEmpty($this->chatId, $response);
            } elseif (isset($message['video'])) {
                $response = $this->processVideoMessage($message);
                $this->sendResponseIfNotEmpty($this->chatId, $response);
            } elseif (isset($message['contact'])) {
                $response = $this->processContactMessage($message);
                $this->sendResponseIfNotEmpty($this->chatId, $response);
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('خطا در پردازش webhook تلگرام: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    private function processTextMessage(array $message): string|array
    {
        try {
            $text = $message['text'];
            ///

            // پردازش دستورات
            if (str_starts_with($text, '/')) {
                return $this->processCommand($text);
            }
            // check if text is a menu item
            $menuItemCtrl = new MainMenuItemController();
            $menuItem = $menuItemCtrl->getMenuItemByAliasName($text);
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
            // check if text is a gift card
            if (str_starts_with(strtolower($text), 'giftcard-')) {
                $this->generalCntrl->subGiftCard($chatId, $text);
                return "";
            }
            if (str_starts_with(strtolower($text), 'charge') !== false) {
                $actionList = explode('-', $text);

                return $this->accountProcessCtrl->adminFastCharge($chatId, $actionList[2], $actionList[1]);

            }
            if (str_starts_with(strtolower($text), 'block') !== false) {

                // check chatId is user and have admin role
                $user = new User();
                $user = $user->get_role_by_account_id($chatId);
                if ($user != 'admin') {
                    $text = $this->customTextCtrl->getText('error.action.not_found');
                    $this->telegramService->sendMessage($chatId, $text);
                    return "";
                }
                $actionList = explode('-', $text);
                $this->generalCntrl->block_user_command('block', $actionList[1], $actionList[2]);
                $text = $this->customTextCtrl->getText('action.block_user.success');
                $this->telegramService->sendMessage($chatId, $text);
                return "";
            }
            if (str_starts_with(strtolower($text), 'unblock') !== false) {
                // check chatId is admin
                $user = new User();
                $user = $user->get_role_by_account_id($chatId);
                if ($user != 'admin') {
                    $text = $this->customTextCtrl->getText('error.action.not_found');
                    $this->telegramService->sendMessage($chatId, $text);
                    return "";
                }
                $actionList = explode('-', $text);
                $this->generalCntrl->block_user_command('unblock', $actionList[1], null);
                $text = $this->customTextCtrl->getText('action.unblock_user.success');
                $this->telegramService->sendMessage($chatId, $text);

                return "";
            }

            return "پیام متنی شما دریافت شد: " . $text;
        } catch (\Throwable $th) {
            \Log::error("خطا در پردازش processTextMessage: " . $th->getMessage());
            $this->telegramService->sendMessage($this->chatId, $this->customTextCtrl->getText('error.server_error'));
            return "";
        }
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
                return $this->subscriptionProcessCtrl->buyHistory($chatId, 1);
                break;
            case 'پشتیبانی':
                return $this->generalCntrl->support($chatId);
                break;
            case 'آموزش استفاده و سوالات متداول':
                return $this->generalCntrl->getFaqs($chatId);
                break;
            case 'دانلود برنامه':
                return $this->generalCntrl->appDownload($chatId);
                break;
            case 'گیفت کارت':
                return $this->generalCntrl->giftCard($chatId);
                break;
            case 'اکانت آزمایشی':
                return $this->generalCntrl->testAccount($chatId);
                break;
            case 'webapp':
                return $this->authCntrl->generate_auto_login_link(new Request(['account_id' => $chatId]));
                break;
            case 'کسب درآمد':
                return $this->generalCntrl->subReferral($chatId);
                break;

            default:
                return $this->customTextCtrl->getText('error.menu.not_found');
                break;
        }
        return $this->customTextCtrl->getText('error.menu.not_found');
    }

    private function processPhotoMessage(array $message): string|array
    {
        try {
            $photos = $message['photo'];
            $photo = end($photos); // بزرگترین سایز عکس
            $fileId = $photo['file_id'];
            $caption = $message['caption'] ?? '';
            $chatId = $message['chat']['id'];

            // دریافت اطلاعات فایل از تلگرام
            $fileInfo = $this->telegramService->getFile($fileId);
            if (!isset($fileInfo['result']['file_path'])) {
                $text = $this->customTextCtrl->getText('action.server_error');
                $this->telegramService->sendMessage($chatId, $text);
                return "";
            }

            $request = new Request();
            $request->transaction_id = $this->transactionCntrl->addUserTranaction($chatId, 0, '000', 0);
            $request->img_src = $fileInfo['result']['file_path']; // ارسال file_path به جای file_id
            $request->account_id = $chatId;
            $request->user_text = $caption ?? 'بدون متن';

            $imageTrCntrl = new TransactionImageController();
            $imageTrCntrl->saveNewTransactionImage($request);
            \Log::info("processPhotoMessage received 44");
            $this->sendMessageToAdmin($chatId, $fileId, $request->transaction_id, 'image');
            // tell user that image is received
            $text = $this->customTextCtrl->getText('action.send_photo.success', [
                'name' => $this->getCurrentChatFirstName(),
            ]);
            $this->telegramService->sendMessage($chatId, $text);
            return "";
        } catch (\Throwable $th) {
            \Log::error("خطا در پردازش تصویر: " . $th->getMessage());
            return "با پشتیبان ربات تماس بگیرید ،خطا در دریافت تصویر";
        }
    }

    private function processDocumentMessage(array $message): string|array
    {
        $document = $message['document'];
        $fileId = $document['file_id'];
        $fileName = $document['file_name'] ?? 'بدون نام';
        $mimeType = $document['mime_type'] ?? 'نامشخص';

        return "فایل شما با نام {$fileName} و نوع {$mimeType} دریافت شد.";
    }

    private function processLocationMessage(array $message): string|array
    {
        $location = $message['location'];
        $latitude = $location['latitude'];
        $longitude = $location['longitude'];

        return "موقعیت مکانی شما در مختصات {$latitude}, {$longitude} دریافت شد.";
    }

    private function processVoiceMessage(array $message): string|array
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

    private function processVideoMessage(array $message): string|array
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

    private function processContactMessage(array $message): string|array
    {
        $contact = $message['contact'];
        $phoneNumber = $contact['phone_number'];
        $firstName = $contact['first_name'];
        $lastName = $contact['last_name'] ?? '';

        return "اطلاعات تماس دریافت شد:\nنام: {$firstName} {$lastName}\nشماره تماس: {$phoneNumber}";
    }

    private function processCommand(string $text): string|array
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
            default => $this->customTextCtrl->getText('error.command.not_found')
        };
        return $response;
    }
    public function checkChannelLock()
    {
        try {
            $chatId = $this->getCurrentChatId();
            $channelLockCtrl = new ChannelLockController();
            $channels = $channelLockCtrl->getAllActiveChannelLock();
            $notJoinedChannels = [];

            if ($channels->count() > 0) {
                foreach ($channels as $channel) {
                    $channelId = $channel->channel_id;
                    // حذف @ از ابتدای نام کانال اگر وجود داشته باشد
                    $channelId = ltrim($channelId, '@');

                    $isChannelMember = $this->telegramService->checkChatIdIsChannelMember($chatId, $channelId);
                    if (!$isChannelMember) {
                        // اصلاح ساختار آرایه دکمه‌ها
                        $notJoinedChannels[] = [
                            'text' => "@" . $channelId,
                            'url' => "https://t.me/" . $channelId,
                        ];
                    }
                }

                if (count($notJoinedChannels) > 0) {
                    $text = $this->customTextCtrl->getText('action.chanel_lock_text');
                    // add start link by reflink
                    // get bot name from setting controller
                    $settingCntrl = new SettingController();
                    $botName = $settingCntrl->get_bot_name();
                    $notJoinedChannels[] = [
                        'text' => "عضو شدم",
                        'url' => "https://t.me/" . $botName . "?start=start",
                    ];
                    // add start command to $notJoinedChannels

                    $this->telegramService->sendMessageWithLinkButtons($chatId, $text, $notJoinedChannels);



                    return false;
                }
            }

            return true;

        } catch (\Throwable $th) {
            \Log::error("خطا در پردازش checkChannelLock: " . $th->getMessage());
            return true;
        }
    }

    private function handleStartCommand(string $message, ): string|array
    {
        try {
            $chatId = $this->getCurrentChatId();
            $firstName = $this->getCurrentChatFirstName();
            $lastName = $this->getCurrentChatLastName();
            $userName = $this->getCurrentChatUserName();
            $referralLogsCntrl = new ReferralLogsController();
            $botUserCtrl = new BotUserController();

            $botUserCtrl->hasRegistred($chatId, $userName, $firstName, $lastName);

            $welcomeFormats = $this->customTextCtrl->getText('action.welcome.message', [
                'name' => $firstName,
                'lastName' => $lastName,
            ]);
            if (is_array($welcomeFormats)) {
                $welcomeFormats = $this->telegramService->formatText($welcomeFormats);
            }

            $this->generalCntrl->return_main_menu_items($chatId, $welcomeFormats);
            return '';

        } catch (\Throwable $th) {
            \Log::error("خطا در پردازش handleStartCommand: " . $th->getMessage());
            return $this->customTextCtrl->getText('error.server_error');

        }
    }
    public function handleHelpCommand($action = null): string|array
    {
        if ($action == 'faqs') {
            return $this->generalCntrl->getFaqs($this->getCurrentChatId());
        }
        if ($action == 'appDownload') {
            return $this->generalCntrl->appDownload();
        }
        // $text = $this->customTextCtrl->getText('action.help.message');
        // $this->generalCntrl->return_main_menu_items($this->getCurrentChatId(), $text);
        return "";
    }

    public function handleReferralCommand($text): string|array
    {
        try {
            $parts = explode(' ', $text);
            $ref = $parts[1] ?? null;
            $chatId = $this->getCurrentChatId();
            $firstName = $this->getCurrentChatFirstName();
            $lastName = $this->getCurrentChatLastName();
            $userName = $this->getCurrentChatUserName();
            $referralLogsCntrl = new ReferralLogsController();
            $botUserCtrl = new BotUserController();

            $result = $botUserCtrl->hasRegistred($chatId, $userName, $firstName, $lastName);
            if ($ref != null) {
                $saveRef = $referralLogsCntrl->check_user_has_referral_and_create($chatId, $ref);
            }
            return '/start';
        } catch (\Throwable $th) {
            \Log::error("خطا در پردازش handleReferralCommand: " . $th->getMessage());
            return '/start';
        }
    }

    private function handleMenuCommand(): string|array
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
        $chatId = $callbackQuery['from']['id'];
        $data = $callbackQuery['data'];
        $callbackQueryId = $callbackQuery['id'];
        $messageId = $callbackQuery['message']['message_id'] ?? null;

        \Log::info("handleCallbackQuery data=> {$data}");
        // checl is force replay
        $this->handleAwaitingReply($chatId, $data);

        // explode the data to get the action
        $actionList = explode('-', $data);
        $action = array_shift($actionList); // Get action and remove it from list
        $params = $actionList; // Remaining items are params

        $response = $this->callbackHandler->handle($chatId, $action, $params, $messageId, $callbackQueryId);

        $alertText = $this->customTextCtrl->getText('action.process.on_progress');
        $showAlert = false;

        if (is_array($response) && isset($response['alert'])) {
            $alertText = $response['alert'];
            $showAlert = $response['show_alert'] ?? true;
            $response = null;
        }

        // ارسال پاسخ به callback query
        $this->telegramService->answerCallbackQuery(
            $callbackQueryId,
            $alertText,
            $showAlert
        );

        if ($response && ($response != "" || $response != null || $response != " ")) {
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
                'keyboard' => $buttons,
                'resize_keyboard' => true,
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
                'keyboard' => $buttons,
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ]),
        ]);

        return '';
    }

    private function handleAwaitingReply(string $chatId, string $text): void
    {
        $awaitingType = $this->getAwaitingReplyType($chatId);

        if ($awaitingType && str_starts_with($awaitingType, 'awaiting_receipt_amount:')) {
            $transactionId = str_replace('awaiting_receipt_amount:', '', $awaitingType);
            $this->processAdminReceiptAmount($chatId, $transactionId, $text);
            return;
        }

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
        return $this->chatId ?? request()->input('message.chat.id') ?? request()->input('callback_query.from.id');
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
    public function sendMessageToAdmin($chat_id, $image_url, $transaction_id, $messageType)
    {
        try {
            $admins = User::where('role', 'admin')->get();

            if ($admins->isEmpty()) {
                \Log::warning("No admins found to send message.");
                return "";
            }

            $adminMessages = [];
            foreach ($admins as $admin) {
                $admin_id = $admin->account_id;
                if ($messageType == 'image') {
                    $text = $this->customTextCtrl->getText('action.send_photo.success.admin', [
                        'account_id' => $chat_id,
                    ]);

                    $buttons = [
                        [
                            'تایید ✅' => "confirmReceipt-{$transaction_id}",
                            'لغو ❌' => "cancelReceipt-{$transaction_id}"
                        ]
                    ];

                    $result = $this->telegramService->sendPhoto($admin_id, $image_url, $text, [
                        'reply_markup' => json_encode([
                            'inline_keyboard' => $this->telegramService->formatInlineKeyboardButtons($buttons)
                        ])
                    ]);

                    if (isset($result['ok']) && $result['ok'] && isset($result['result']['message_id'])) {
                        $adminMessages[] = [
                            'chat_id' => $admin_id,
                            'message_id' => $result['result']['message_id']
                        ];
                    }
                } else {
                    // For other message types, transaction_id might be the text
                    $this->telegramService->sendMessage($admin_id, $transaction_id);
                }
            }

            if (!empty($adminMessages)) {
                Cache::put("admin_receipt_messages_{$transaction_id}", $adminMessages, now()->addDays(1));
            }

            return "";
        } catch (\Throwable $th) {
            \Log::error("خطا در پردازش sendMessageToAdmin: " . $th);
            return "";
        }
    }
    private function removeReceiptButtonsFromAllAdmins($transactionId)
    {
        $messages = Cache::get("admin_receipt_messages_{$transactionId}", []);
        foreach ($messages as $msg) {
            try {
                $this->telegramService->editMessageReplyMarkup($msg['chat_id'], $msg['message_id'], ['inline_keyboard' => []]);
            } catch (\Throwable $th) {
                \Log::error("Error removing buttons for admin {$msg['chat_id']}: " . $th->getMessage());
            }
        }
    }

    public function handleConfirmReceipt($adminChatId, $transactionId, $callbackQueryId, $messageId = null)
    {
        if (Cache::has("receipt_processed_{$transactionId}")) {
            $this->telegramService->answerCallbackQuery($callbackQueryId, "این رسید قبلاً توسط مدیر دیگری بررسی شده است.", true);
            $this->removeReceiptButtonsFromAllAdmins($transactionId);
            return "";
        }

        $transaction = Transaction::find($transactionId);
        if (!$transaction) {
            $this->telegramService->answerCallbackQuery($callbackQueryId, "تراکنش یافت نشد.", true);
            $this->removeReceiptButtonsFromAllAdmins($transactionId);
            return "";
        }

        // Remove buttons for ALL admins
        $this->removeReceiptButtonsFromAllAdmins($transactionId);

        // Set state for admin to wait for amount
        $this->setAwaitingReply($adminChatId, "awaiting_receipt_amount:{$transactionId}");

        $this->telegramService->sendMessage($adminChatId, "لطفاً مبلغ شارژ برای کاربر {$transaction->account_id} را به تومان وارد کنید:");
        return "";
    }

    public function handleCancelReceipt($adminChatId, $transactionId, $callbackQueryId, $messageId = null)
    {
        if (Cache::has("receipt_processed_{$transactionId}")) {
            $this->telegramService->answerCallbackQuery($callbackQueryId, "این رسید قبلاً توسط مدیر دیگری بررسی شده است.", true);
            $this->removeReceiptButtonsFromAllAdmins($transactionId);
            return "";
        }

        // Remove buttons for ALL admins
        $this->removeReceiptButtonsFromAllAdmins($transactionId);

        Cache::put("receipt_processed_{$transactionId}", true, now()->addDays(1));

        $transaction = Transaction::find($transactionId);
        if ($transaction) {
            $transaction->confirmed = 0;
            $transaction->recipe_number = 'REJECTED';
            $transaction->save();
            $this->telegramService->sendMessage($transaction->account_id, "رسید تراکنش شما توسط مدیریت رد شد.");
        }

        $this->telegramService->sendMessage($adminChatId, "رسید با موفقیت رد شد.");
        return "";
    }

    private function processAdminReceiptAmount($adminChatId, $transactionId, $amount)
    {
        if (Cache::has("receipt_processed_{$transactionId}")) {
            $this->telegramService->sendMessage($adminChatId, "این رسید قبلاً توسط مدیر دیگری بررسی شده است.");
            $this->clearAwaitingReply($adminChatId);
            return;
        }

        if (!is_numeric($amount) || $amount <= 0) {
            $this->telegramService->sendMessage($adminChatId, "لطفاً یک مبلغ معتبر وارد کنید:");
            return;
        }

        Cache::put("receipt_processed_{$transactionId}", true, now()->addDays(1));

        $transaction = Transaction::find($transactionId);
        if ($transaction) {
            $transaction->amount = $amount;
            $transaction->confirmed = 1;
            $transaction->save();

            $this->accountProcessCtrl->adminFastCharge($adminChatId, $amount, $transaction->account_id);

            // Add referral amount
            $referralLogsCntrl = new ReferralLogsController();
            $referralSettingCntrl = new ReferralSettingController();
            $referral_percent = $referralSettingCntrl->get_referral_setting_referral_percent();
            $referralAmount = 0;
            if ($referral_percent !== null && $referral_percent !== 0) {
                $referralAmount = ($amount / 100) * $referral_percent;
            }
            $referralLogsCntrl->add_amount_to_refrerral_user_Log_and_referral_wallet($transaction->id, $referralAmount, false);

            $this->clearAwaitingReply($adminChatId);
        } else {
            $this->telegramService->sendMessage($adminChatId, "تراکنش یافت نشد.");
            $this->clearAwaitingReply($adminChatId);
        }
    }

    private function sendResponseIfNotEmpty(string $chatId, string|array|null $response): void
    {
        if ($response === null || $response === '') {
            return;
        }

        if (is_array($response) && $response === []) {
            return;
        }

        $this->telegramService->sendMessage($chatId, $response);
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
