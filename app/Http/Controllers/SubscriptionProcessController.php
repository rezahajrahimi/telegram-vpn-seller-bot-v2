<?php
namespace App\Http\Controllers;

use App\Models\BotUser;
use App\Models\ProductCategory;
use App\Models\UserState;
use App\Services\TelegramMessageFormatter;

// add BotUser model
use App\Services\TelegramService;
use Illuminate\Http\Request;

class SubscriptionProcessController extends Controller
{
    private $chatId;
    private $botUser;
    private $selectedPrCat;
    private TelegramService $telegramService;
    private CustomTextController $customTextCtrl;
    private AccountBallanceController $accBlCtrl;
    private ReferralWalletController $referralCntrl;
    private ProductCategoryController $prCatCntrl;
    private PannelController $panelCntrl;
    private AdvanceSettingLookupController $advancedSettingCntrl;
    private GeneralController $generalCntrl;
    private LogController $logCtrl;
    private TransactionSettingController $trSettingCntrl;
    private PaymentTypeController $pymntCntrl;
    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService      = $telegramService;
        $this->customTextCtrl       = new CustomTextController();
        $this->accBlCtrl            = new AccountBallanceController();
        $this->referralCntrl        = new ReferralWalletController();
        $this->prCatCntrl           = new ProductCategoryController();
        $this->panelCntrl           = new PannelController();
        $this->advancedSettingCntrl = new AdvanceSettingLookupController();
        $this->generalCntrl         = new GeneralController();
        $this->logCtrl              = new LogController();
        $this->botUser              = new BotUser();
        $this->selectedPrCat        = new ProductCategory();
        $this->trSettingCntrl       = new TransactionSettingController();
        $this->pymntCntrl           = new PaymentTypeController();
    }

    public function buySubscriptionMenu($chatId)
    {
        try {
            $this->chatId = $chatId;
            // get the chat user name from user table with chatId
            $this->botUser = $this->botUser->getUserByAccountID($chatId);
            $this->addNewBotLog('subscription', 'وارد بخش خرید اشتراک شد.', 'show');

            $text = $this->customTextCtrl->getText('action.buy_subscription');

            // بررسی نمایش کانفیگ‌ها بر اساس دسته‌بندی پنل‌ها
            $hasShowConfigByPanelCategory = $this->advancedSettingCntrl->getValueByNameWithBooleanValue('bot_show_configs_by_panels_category');

            if ($hasShowConfigByPanelCategory == true || $hasShowConfigByPanelCategory == 1) {
                $panels = $this->panelCntrl->get_all_panells_by_location_capacity_mode();

                $text = $this->customTextCtrl->getText('action.buy_subscription_by_location.location');
                $opr  = [];

                foreach ($panels as $key => $value) {
                    $buttonText = $value;
                    $opr[]      = [
                        $buttonText => "buySubscriptionByLocation-" . $value,
                    ];
                }

                $this->telegramService->sendMessageWithInlineKeyboard($chatId, $text, $opr);
                return "";

            }
            $this->prepareSubscriptionButtons();

            return "";

        } catch (\Throwable $th) {
            \Log::error("خطا در خرید اشتراک: " . $th->getMessage());
            $this->telegramService->sendMessage($chatId, $this->customTextCtrl->getText('error.server_error'));
            return "";
        }
    }

    public function buySubscriptionByLocationAction($chatId, $location)
    {
        try {
            $this->chatId  = $chatId;
            $this->botUser = $this->botUser->getUserByAccountID($chatId);
            $this->addNewBotLog('subscription', 'وارد بخش خرید اشتراک بر اساس لوکیشن شد.', 'show');
            $text    = $this->customTextCtrl->getText('action.buy_subscription_by_location.location');
            $panelId = $this->panelCntrl->get_pannel_id_by_location($location);

            $prCatCntrl = new ProductCategoryController();
            $prCat      = $prCatCntrl->get_all_active_prodct_category_by_pannel_id_order_by_price($panelId);

            $this->prepareSubscriptionButtons($prCat);

            return "";

        } catch (\Throwable $th) {
            \Log::error("خطا در انتخاب لوکیشن: " . $th->getMessage());
            $this->telegramService->sendMessage($chatId, $this->customTextCtrl->getText('error.server_error'));
            return "";
        }
    }
    public function prepareSubscriptionButtons()
    {
        $text              = $this->customTextCtrl->getText('action.buy_subscription.select_package');
        $prCat             = $this->prCatCntrl->getAllActiveProdctCategoryOrderByPrice();
        $opr               = [];
        $dollarTransaction = $this->trSettingCntrl->getDollorTransactionSetting();
        $showOneRowConfig  = $this->advancedSettingCntrl->getValueByNameWithBooleanValue('bot_show_one_row_config');
        if ($showOneRowConfig) {
            foreach ($prCat as $key => $value) {
                // هر دکمه به صورت یک ردیف جداگانه
                if ($dollarTransaction == true) {
                    $buttonText = "$value->category_name - $value->price_in_dollar$ - $value->price تومان";
                } else {
                    $buttonText = "$value->category_name - $value->price تومان";
                }
                $opr[] = [
                    $buttonText => "buySubscription-" . strval($value->id),
                ];
            }
        } else {
            if ($dollarTransaction == true) {
                $opr[] = [
                    'قیمت(دلار)'  => '0',
                    'قیمت(تومان)' => '0',
                    'بسته'        => '0',
                ];
                foreach ($prCat as $key => $value) {
                    $opr[] = [
                        "$value->price_in_dollar" => "buySubscription-" . strval($value->id),
                        "$value->price"           => "buySubscription-" . strval($value->id),
                        "$value->category_name"   => "buySubscription-" . strval($value->id),
                    ];
                }
            } else {
                $opr[] = [
                    'قیمت(تومان)' => '0',
                    'بسته'        => '0',
                ];
                foreach ($prCat as $key => $value) {
                    $opr[] = [
                        "$value->price"         => "buySubscription-" . strval($value->id),
                        "$value->category_name" => "buySubscription-" . strval($value->id),
                    ];
                }
            }
        }
        $this->telegramService->sendMessageWithInlineKeyboard($this->chatId, $text, $opr);
        return "";
    }

    public function buySubscriptionAction($chatId, $subscriptionId)
    {
        try {
            $this->chatId        = $chatId;
            $this->selectedPrCat = $this->selectedPrCat->getProdctCategorByID($subscriptionId);
            // check if selectedPrCat is null
            if ($this->selectedPrCat == null) {
                return $this->customTextCtrl->getText('action.process.failed_buy');
            }
            // بررسی موجودی کاربر
            $productPrice         = $this->selectedPrCat->price;
            $productPriceInDollar = $this->selectedPrCat->price_in_dollar;

            $hasBallance = $this->accBlCtrl->checkUserHasBalance($chatId, $productPrice, $productPriceInDollar);
            // بررسی کیف پول ارجاع
            $hasRefballance = $this->referralCntrl->check_user_has_ref_wallet_ballance($this->chatId, $this->selectedPrCat->price);

            if ($hasRefballance == true || $hasBallance == true || $hasBallance == 1 || $hasRefballance == 1) {
                return $this->processSubscriptionPurchase();
                // return $this->customTextCtrl->getText('action.process.success_buy');
            } else {
                $this->generalCntrl->send_insufficient_balance_message($this->chatId, $this->selectedPrCat->id);
                return "";
            }

        } catch (\Throwable $th) {
            \Log::error("خطا در خرید بسته: " . $th->getMessage());
            return $this->customTextCtrl->getText('action.process.failed_buy');
        }
    }

    private function processSubscriptionPurchase()
    {
        try {
            $selectedPrCat = $this->selectedPrCat;
            // بررسی موجودی کاربر
            $productPrice         = $this->selectedPrCat->price;
            $productPriceInDollar = $this->selectedPrCat->price_in_dollar;
            $hasBallance          = $this->accBlCtrl->checkUserHasBalance($this->chatId, $productPrice, $productPriceInDollar);
            // بررسی کیف پول ارجاع
            $hasRefballance = $this->referralCntrl->check_user_has_ref_wallet_ballance($this->chatId, $this->selectedPrCat->price);

            if (($hasRefballance == false && $hasBallance == false) || ($hasBallance == 0 && $hasRefballance == 0)) {
                $this->generalCntrl->send_insufficient_balance_message($this->chatId, $this->selectedPrCat->id);
                return "";
            }

            $productID = $this->selectedPrCat->id;
            $productID += 1;

            $pannel    = $this->panelCntrl->getPannelById($this->selectedPrCat->pannel_id);
            $day       = $this->selectedPrCat->expire_day;
            $volume    = $this->selectedPrCat->volume;
            $productID = $this->selectedPrCat->id;
            $productID += 1;
            $resualt = false;

            if ($pannel->type == 'hiddify') {
                $resualt = $this->generalCntrl->new_hiddify_config_telegram_text($this->selectedPrCat, $pannel, $volume, $day, $this->chatId, $productID);
            } elseif ($pannel->type == 'marzban') {
                // create marzban user
                return " پنل مرزبان";
            } elseif ($pannel->type == 'sanaei') {
                // create sanaei user
                return " پنل سنائی";
            }

            if ($resualt == true) {
                // decrease user balance
                $request           = new Request();
                $request->userID   = $this->chatId;
                $request->ballance = $productPrice;
                $request->type     = 'toman';
                $this->accBlCtrl->decreaseUserAccuntBalanceByUserID($request);
                // send useful
                $this->generalCntrl->send_using_subscription_manual_message($this->chatId);

                $this->addNewBotLog('subscription', 'خرید اشتراک با موفقیت انجام شد.', 'show');
                return "";
            } else {
                $this->addNewBotLog('subscription', 'خرید اشتراک با شکست مواجه شد.', 'show');
                return $this->customTextCtrl->getText('action.process.failed_buy');
            }

        } catch (\Throwable $th) {
            \Log::error("خطا در خرید بسته: " . $th->getMessage());
            return $this->customTextCtrl->getText('action.process.failed_buy');
        }

    }
    public function handle_offline_add_balance($chatId, $offlinePaymentID)
    {
        try {
            $offlinePayment = $this->pymntCntrl->get_payment_type_by_id($offlinePaymentID);
            if ($offlinePayment == null) {
                return $this->customTextCtrl->getText('error.payment_type_not_found');
            }
            $text = $this->customTextCtrl->getText('action.process.add_offline_balance_option.image', ['merchant_id' => $offlinePayment->merchant_id]);
            //fromat text with formatter service
                $formatter = new TelegramMessageFormatter($this->telegramService);
            $text      = $formatter->addFormattedText('', $text)->getMessage();
            $this->telegramService->sendMessage($chatId, $text);
            // // ذخیره حالت کاربر
            // UserState::updateOrCreate(
            //     ['chat_id' => $chatId],
            //     [
            //         'state' => 'waiting_payment_receipt',
            //         'data' => [
            //             'payment_type_id' => $offlinePaymentID
            //         ]
            //     ]
            // );

            // $text = $this->customTextCtrl->getText('action.process.add_offline_balance_option.image');
            // $buttons = [
            //     [
            //         ['text' => 'لغو', 'callback_data' => 'cancel_payment'],
            //     ]
            // ];

            // $this->telegramService->sendMessageWithInlineKeyboard($chatId, $text, $buttons);

            // $replyMarkup = [
            //     'keyboard' => [[['text' => 'ارسال تصویر رسید', 'request_photo' => true]]],
            //     'resize_keyboard' => true,
            //     'one_time_keyboard' => true
            // ];


            return "";
        } catch (\Throwable $th) {
            \Log::error("خطا در درخواست تصویر رسید: " . $th->getMessage());
            return $this->customTextCtrl->getText('error.server_error');
        }
    }

    public function processOfflinePaymentImage($chatId, $photo)
    {
        try {
            // بررسی حالت کاربر
            $userState = UserState::where('chat_id', $chatId)
                ->where('state', 'waiting_payment_receipt')
                ->first();

            if (!$userState) {
                $this->telegramService->sendMessage($chatId, 'لطفاً ابتدا از منوی پرداخت آفلاین اقدام کنید.');
                return "";
            }

            $paymentTypeId = $userState->data['payment_type_id'];
            $photoSize = end($photo);
            $fileId = $photoSize['file_id'];

            // ذخیره اطلاعات پرداخت در دیتابیس
            $this->addNewBotLog('payment', 'تصویر رسید پرداخت آفلاین ارسال شد', 'upload');

            // پاک کردن حالت کاربر
            $userState->delete();

            // ارسال پیام تایید به کاربر
            $this->telegramService->sendMessage($chatId, 'تصویر رسید شما با موفقیت دریافت شد و در حال بررسی است.');

            // ارسال به ادمین
            $adminChatId = env('TELEGRAM_ADMIN_ID');
            if ($adminChatId) {
                $this->botUser = $this->botUser->getUserByAccountID($chatId);
                $adminMessage = "رسید پرداخت جدید:\nکاربر: {$this->botUser->username}\nChat ID: {$chatId}\nنوع پرداخت: {$paymentTypeId}";
                $this->telegramService->sendPhoto($adminChatId, $fileId, $adminMessage);
            }

            // برگشت به منوی اصلی
            $this->telegramService->sendMessage($chatId, 'لطفاً منتظر تایید ادمین بمانید.', [
                'reply_markup' => json_encode([
                    'remove_keyboard' => true
                ])
            ]);

            return "";
        } catch (\Throwable $th) {
            \Log::error("خطا در پردازش تصویر رسید: " . $th->getMessage());
            return $this->customTextCtrl->getText('error.upload_failed');
        }
    }

    private function addNewBotLog($type, $message, $event)
    {
        $logCtrl = new LogController();
        $logCtrl->addNewLog($type, $message, $this->chatId, $this->botUser->username, $event);
        return true;
    }

    // سایر متدهای کمکی مورد نیاز...
}
