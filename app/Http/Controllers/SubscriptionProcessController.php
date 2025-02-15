<?php
namespace App\Http\Controllers;

use App\Models\BotUser;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UserState;
use App\Services\TelegramMessageFormatter;

// add BotUser model
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Hekmatinasser\Verta\Verta;
class SubscriptionProcessController extends Controller
{
    private $chatId;
    private $botUser;
    private $product;
    private $selectedPrCat;

    private TelegramService $telegramService;
    private CustomTextController $customTextCtrl;
    private AccountBallanceController $accBlCtrl;
    private ReferralWalletController $referralCntrl;
    private ProductCategoryController $prCatCntrl;
    private ProductController $prCtrl;
    private PannelController $panelCntrl;
    private AdvanceSettingLookupController $advancedSettingCntrl;
    private GeneralController $generalCntrl;
    private LogController $logCtrl;
    private TransactionSettingController $trSettingCntrl;
    private PaymentTypeController $pymntCntrl;
    private HiddifyPannelController $hiddifyPannelCntrl;
    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService      = $telegramService;
        $this->customTextCtrl       = new CustomTextController();
        $this->accBlCtrl            = new AccountBallanceController();
        $this->referralCntrl        = new ReferralWalletController();
        $this->prCatCntrl           = new ProductCategoryController();
        $this->prCtrl               = new ProductController();
        $this->panelCntrl           = new PannelController();
        $this->advancedSettingCntrl = new AdvanceSettingLookupController();
        $this->generalCntrl         = new GeneralController();
        $this->logCtrl              = new LogController();
        $this->botUser              = new BotUser();
        $this->product              = new Product();
        $this->selectedPrCat        = new ProductCategory();
        $this->trSettingCntrl       = new TransactionSettingController();
        $this->pymntCntrl           = new PaymentTypeController();
        $this->hiddifyPannelCntrl   = new HiddifyPannelController();
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

    private function processPayment($productPrice, $productPriceInDollar, $hasRefballance)
    {
        $request           = new Request();
        $request->userID   = $this->chatId;
        $request->ballance = $productPrice;
        $request->type     = 'toman';

        // تلاش برای کسر از کیف پول تومانی
        $balance = $this->accBlCtrl->decreaseUserAccuntBalanceByUserID($request);
        if ($balance) {
            $this->addNewBotLog('subscription', 'کسر موجودی از کیف پول کاربر به مقدار ' . $productPrice . ' تومان', 'show');
            return true;
        }

        // بررسی پرداخت دلاری
        $dollarTransaction = $this->trSettingCntrl->getDollorTransactionSetting();
        if ($dollarTransaction) {
            $request->ballance = $productPriceInDollar;
            $request->type     = 'dollar';
            $balance           = $this->accBlCtrl->decreaseUserAccuntBalanceByUserID($request);
            if ($balance) {
                $this->addNewBotLog('subscription', 'کسر موجودی از کیف پول کاربر به مقدار ' . $productPriceInDollar . ' دلار', 'show');
                return true;
            }
        }

        // بررسی کیف پول ارجاع
        if ($hasRefballance) {
            $balance = $this->referralCntrl->dec_user_ref_wallet_ballance($this->chatId, $productPrice);
            if ($balance) {
                $this->addNewBotLog('subscription', 'کسر موجودی از کیف پول همکاری به مقدار ' . $productPrice . ' تومان', 'show');
                return true;
            }
        }

        return false;
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

            if ($resualt !== false && $resualt !== null) {
                // پردازش پرداخت
                $paymentSuccess = $this->processPayment($productPrice, $productPriceInDollar, $hasRefballance);

                if (! $paymentSuccess) {
                    if ($pannel->type == 'hiddify') {
                        // remove created product from database and panel
                        $uuid = $resualt;
                        $this->hiddifyPannelCntrl->deleteUserOfHiddifyPanel($pannel->id, $uuid);
                        // delete product from database
                        $prCntrl = new ProductController();
                        $res     = $prCntrl->delete_product_by_uuid($uuid);
                        if ($res) {
                            $this->addNewBotLog('subscription', 'به دلیل عدم داشتن موجودی، حذف کالا از پنل و دیتابیس', 'show');
                        }

                    }
                    return $this->customTextCtrl->getText('action.process.failed_buy');
                }

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

            if (! $userState) {
                $this->telegramService->sendMessage($chatId, 'لطفاً ابتدا از منوی پرداخت آفلاین اقدام کنید.');
                return "";
            }

            $paymentTypeId = $userState->data['payment_type_id'];
            $photoSize     = end($photo);
            $fileId        = $photoSize['file_id'];

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
                $adminMessage  = "رسید پرداخت جدید:\nکاربر: {$this->botUser->username}\nChat ID: {$chatId}\nنوع پرداخت: {$paymentTypeId}";
                $this->telegramService->sendPhoto($adminChatId, $fileId, $adminMessage);
            }

            // برگشت به منوی اصلی
            $this->telegramService->sendMessage($chatId, 'لطفاً منتظر تایید ادمین بمانید.', [
                'reply_markup' => json_encode([
                    'remove_keyboard' => true,
                ]),
            ]);

            return "";
        } catch (\Throwable $th) {
            \Log::error("خطا در پردازش تصویر رسید: " . $th->getMessage());
            return $this->customTextCtrl->getText('error.upload_failed');
        }
    }

    public function buyHistory($chatId)
    {
        try {
            $this->chatId  = $chatId;
            $this->botUser = $this->botUser->getUserByAccountID($chatId);
            $this->addNewBotLog('subscription', 'وارد بخش سابقه خرید شد.', 'show');
            $histories = $this->prCtrl->getUserProductsHistoryByAccountID($chatId);
            if ($histories == null) {
                $text = $this->customTextCtrl->getText('action.buy_history.no_history');
                $this->telegramService->sendMessage($chatId, $text);
                return "";
            }
            $text = $this->customTextCtrl->getText('action.buy_history.title');
            $opr  = [];
            foreach ($histories as $key => $history) {
                $opr[] = [
                    $history->remark . ' | ' . $history->product_category->category_name => 'buyHistory-' . $history->id,
                ];
            }
            $this->telegramService->sendMessageWithInlineKeyboard($chatId, $text, $opr);
            return "";
        } catch (\Throwable $th) {
            \Log::error("خطا در سابقه خرید: " . $th->getMessage());
            return $this->customTextCtrl->getText('error.server_error');
        }
    }
    public function subBuyHistory($chatId, $historyId)
    {
        try {
            $this->chatId  = $chatId;
            $this->botUser = $this->botUser->getUserByAccountID($chatId);

            // ابتدا رکورد تاریخچه را از دیتابیس دریافت کنید
            $product = Product::find($historyId);
            if ($product == null) {
                return $this->customTextCtrl->getText('error.history_not_found');
            }

            if ($product != null) {
                // convert $historyId->product_categories_id to int
                $prCatId = (int) $product->product_categories_id;
                $prCat   = $this->selectedPrCat->getProdctCategorByID($prCatId);
                $pannel  = $this->panelCntrl->getPannelById($prCat->pannel_id);

                $text = $this->customTextCtrl->getText('action.buy_history.title');
                $this->addNewBotLog('subscription', 'وارد سابقه خرید با ایدی ' . $product->remark . ' شد.', 'show');
                // check panel name is hiddify
                if ($pannel->type == 'hiddify') {
                    $userLink = $pannel->user_link;
                    if (substr($userLink, -1) == '/') {
                        $userLink = substr($userLink, 0, -1);
                    }

                    $hiddifcCntrl         = new HiddifyPannelController();
                    $userPannelLink       = $hiddifcCntrl->get_hiddify_subscription_link($pannel->user_link, $product->panel_link);
                    $userSubscriptionLInk = $hiddifcCntrl->get_hiddify_subscription_link($pannel->user_link, $product->subscription_link);
                    $pnlCntrl             = new PannelController();
                    $image                = $pnlCntrl->generateQrMOC($userSubscriptionLInk);

                    $text      = $this->customTextCtrl->getText('action.buy_history.history', ['name' => $product->remark, 'category_name' => $prCat->category_name, 'panel_link' => $userPannelLink, 'subscription_link' => $userSubscriptionLInk]);
                    $formatter = new TelegramMessageFormatter($this->telegramService);
                    $text      = $formatter->addFormattedText('', $text)->getMessage();

                    $this->telegramService->sendPhotoFile($chatId, $image, $text);
                    $this->generalCntrl->send_using_subscription_manual_message($chatId, true, $product->id);
                    return "";

                }

            }
            return $history;
        } catch (\Throwable $th) {
            \Log::error("خطا در سابقه خرید: " . $th->getMessage());
            return $this->customTextCtrl->getText('error.server_error');
        }
    }
    public function recharge($chatId, $productID)
    {
        try {
            $this->chatId  = $chatId;
            $this->botUser = $this->botUser->getUserByAccountID($chatId);
            $this->addNewBotLog('subscription', 'وارد بخش شارژ مجدد شد.', 'show');
            // check product is exist
            $product = Product::find($productID);
            if ($product == null) {
                return $this->customTextCtrl->getText('error.product_not_found');
            }
            // get product category
            $prCat = $this->selectedPrCat->getProdctCategorByID($product->product_categories_id);
            if ($prCat == null) {
                return $this->customTextCtrl->getText('error.product_category_not_found');
            }
            // chcek product cat is rechargeable or not
            if ($prCat->rechargable == false || $prCat->rechargable == 0) {
                $text    = $this->customTextCtrl->getText('error.product_not_rechargeable');
                $resualt = app('telegram_bot')->sendMessage($text, $this->chatId, null, 'MarkDown');
                return $resualt;
            }
            // check selectedPrCat is اکانت آزمایشی or not
            if ($prCat->category_name == 'اکانت آزمایشی' || $prCat->is_active == false || $prCat->is_active == 0) {
                $text    = $this->customTextCtrl->getText('error.product_not_rechargeable');
                $resualt = app('telegram_bot')->sendMessage($text, $this->chatId, null, 'MarkDown');
                return $resualt;
            }
            // get product price & price in dollar
            $productPrice         = $prCat->price;
            $productPriceInDollar = $prCat->price_in_dollar;
            // check user has balance or has ref ballance
            $hasBallance    = $this->accBlCtrl->checkUserHasBalance($this->chatId, $productPrice, $productPriceInDollar);
            $hasRefballance = $this->referralCntrl->check_user_has_ref_wallet_ballance($this->chatId, $productPrice);
            if (($hasRefballance == false && $hasBallance == false) || ($hasBallance == 0 && $hasRefballance == 0)) {
                $this->generalCntrl->send_insufficient_balance_message($this->chatId, $this->selectedPrCat->id);
                return "";
            }
            // get pannel
            $pannel = $this->panelCntrl->getPannelById($prCat->pannel_id);
            if ($pannel == null) {
                return $this->customTextCtrl->getText('error.pannel_not_found');
            }
            // check pannel type is hiddify
            if ($pannel->type == 'hiddify') {
                $hiddifcCntrl = new HiddifyPannelController();
                $uuid         = $hiddifcCntrl->extractUUID($product->subscription_link);
                $day          = $prCat->expire_day;
                $volume       = $prCat->volume;

                $req           = new Request();
                $req->pannelID = $pannel->id;
                $req->name     = $product->remark;
                $req->uuid     = $uuid;
                $req->vol      = $volume;
                $req->day      = $day;
                $req->comment  = "شارژ مجدد در " . Verta::now();

                $updateRemark = $hiddifcCntrl->rechargeUserOfHiddifyPanelApi($req);
                if ($updateRemark->getStatusCode() == 200) {
                    $paymentSuccess = $this->processPayment($productPrice, $productPriceInDollar, $hasRefballance);

                    $text    = $this->customTextCtrl->getText('action.recharge.success');
                    $resualt = app('telegram_bot')->sendMessage($text, $this->chatId, null, 'MarkDown');
                    $this->addNewBotLog('subscription', 'تمدید اشتراک با موفقیت انجام شد.', 'show');
                    return "";
                }
                \Log::info(["message"=>$updateRemark]);
                return "خطا";
            }

            return "";
        } catch (\Throwable $th) {
            \Log::error("خطا در شارژ مجدد: " . $th->getMessage());
            return $this->customTextCtrl->getText('error.server_error');
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
