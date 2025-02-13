<?php
namespace App\Http\Controllers;

use App\Http\Controllers\CustomTextController;
use App\Models\MainMenuItem;
use App\Models\ProductCategory;
use App\Models\TransactionSetting;
use App\Models\User;
use App\Services\TelegramMessageFormatter;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\DomCrawler\Crawler;

class GeneralController extends Controller
{
    private CustomTextController $customTextCtrl;
    private TelegramService $telegramService;
    private AccountBallanceController $accBlCtrl;
    private ReferralWalletController $referralCntrl;
    private ProductCategoryController $prCatCntrl;
    private PannelController $panelCntrl;
    private MainMenuItemController $menuItemCntrl;
    private PaymentTypeController $pymntCntrl;
    private PaymentMenuItemController $pymMenCntrl;
    private CryptoPaymentController $cryptoPymentCntrl;
    private TransactionController $trCntrl;
    private TransactionSettingController $trSettingCntrl;
    private BillController $billCntrl;
    private MainMenuItem $mainMenuItem;
    private ProductCategory $productCategory;
    private TransactionSetting $trSetting;
    private ChannelLockMenuItemController $channelLockMenuItemCntrl;
    private CronJobController $cronJobCntrl;
    private GiftCardMenuItemController $giftCardMenuItemCntrl;
    private SettingController $settingCntrl;
    public function __construct()
    {
        $this->customTextCtrl    = new CustomTextController();
        $this->telegramService   = new TelegramService();
        $this->accBlCtrl         = new AccountBallanceController();
        $this->referralCntrl     = new ReferralWalletController();
        $this->prCatCntrl        = new ProductCategoryController();
        $this->panelCntrl        = new PannelController();
        $this->menuItemCntrl     = new MainMenuItemController();
        $this->pymntCntrl        = new PaymentTypeController();
        $this->pymMenCntrl       = new PaymentMenuItemController();
        $this->cryptoPymentCntrl = new CryptoPaymentController();
        $this->trCntrl           = new TransactionController();
        $this->billCntrl         = new BillController();
        $this->mainMenuItem      = new MainMenuItem();
        $this->productCategory   = new ProductCategory();
        $this->trSetting         = new TransactionSetting();
        $this->trSettingCntrl    = new TransactionSettingController();
        $this->channelLockMenuItemCntrl = new ChannelLockMenuItemController();
        $this->cronJobCntrl      = new CronJobController();
        $this->giftCardMenuItemCntrl = new GiftCardMenuItemController();
        $this->settingCntrl          = new SettingController();
    }
    public function boot_seeding_data()
    {

        // add default menu items
        $this->menuItemCntrl->seed();
        // add default channel lock menu items
        $this->channelLockMenuItemCntrl->seed();
        // add default cron jobs
        $this->cronJobCntrl->seed();
        // add default crypto payment
        $this->cryptoPymentCntrl->createNowPaymentData();
        // add default gift card menu items
        $this->giftCardMenuItemCntrl->seed();
        // add default setting
        $this->settingCntrl->seed();
        // add default payment types
        $this->pymntCntrl->seed();
        // add default payment menu items
        $this->pymMenCntrl->seed();
        // add default custom texts
        $this->customTextCtrl->seed();
        // add default transaction settings
        $this->trSettingCntrl->seed();


    }
    public function getDashboardAnalytics()
    {
        try {
            $botUsetCntrl               = new BotUserController();
            $getLast10Users             = $botUsetCntrl->getLast10Users();
            $logCntrl                   = new LogController();
            $getTop20Log                = $logCntrl->getAllLogs(20);
            $transactionCntrl           = new TransactionController();
            $last10ConfirmedTransaction = $transactionCntrl->getConfirmedTransactions(10);
            $unConfirmedTransaction     = $transactionCntrl->getUnConfirmedTransactions(1000);
            $productCatCntrl            = new ProductCategoryController();
            $mostSelledProductCategory  = $productCatCntrl->mostSelledProductCategory(10);
            $prCntrl                    = new ProductController();
            $last10ProductSelled        = $prCntrl->getLastProductSelled(10);
            return response()->json(
                [
                    'Last10User'                 => $getLast10Users,
                    'Last20Logs'                 => $getTop20Log,
                    'Last10ConfirmedTransaction' => $last10ConfirmedTransaction,
                    'UnConfirmedTransaction'     => $unConfirmedTransaction,
                    'MostSelledProductCategory'  => $mostSelledProductCategory,
                    'last10ProductSelled'        => $last10ProductSelled,
                ],
                200,
            );
        } catch (\Throwable $th) {
            \Log::info("error on getDashboardAnalytics-> $th");
            return response()->json(null, 500);
        }
    }
    public function getAgentDashboardAnalytics()
    {
        try {
            $accCntrl     = new AccountBallanceController();
            $accBallance  = $accCntrl->getLoggedUserBallancce();
            $agentPrCntrl = new AgentProductController();
            $products     = $agentPrCntrl->getProductsOfLoggedAgent();
            // $boughtProducts =  $agentPrCntrl->getAgentSelledProducts(10);
            $logCntrl    = new LogController();
            $getTop20Log = $logCntrl->getAllLogsOfLoggedAgent(20);
            return response()->json(
                [
                    'accBallance' => $accBallance,
                    'products'    => $products,
                    // 'boughtProducts' => $boughtProducts,
                    'Last20Logs'  => $getTop20Log,
                ],
                200,
            );
        } catch (\Throwable $th) {
            \Log::info("error on getAgentDashboardAnalytics-> $th");
            return response()->json(null, 500);
        }
    }
    public function getAgentPaymentWays()
    {
        try {
            $pymntCntrl           = new PaymentTypeController();
            $pymentType           = $pymntCntrl->getAllActivePaymentTypesWithZarinpalMerchentIDFilter();
            $cryptoPymentCntrl    = new CryptoPaymentController();
            $cryptiPymentIsActive = $cryptoPymentCntrl->getNowPaymentsStatus();
            return response()->json(['active_payment' => $pymentType, 'crypto_payment_status' => $cryptiPymentIsActive], 200);
        } catch (\Throwable $th) {
            \Log::info("error on getAgentPaymentWays-> $th");
            return response()->json(null, 500);
        }
    }

    public function getUserDashboardAnalytics()
    {
        try {
            $accCntrl    = new AccountBallanceController();
            $accBallance = $accCntrl->getLoggedUserBallancce();
            $prCatCntrl  = new ProductCategoryController();

            $products = $prCatCntrl->getAllActiveProdctCategoryOrderByPrice();
            // $boughtProducts =  $agentPrCntrl->getAgentSelledProducts(10);
            $logCntrl    = new LogController();
            $getTop20Log = $logCntrl->getAllLogsOfLoggedAgent(20);
            return response()->json(
                [
                    'accBallance' => $accBallance,
                    'products'    => $products,
                    // 'boughtProducts' => $boughtProducts,
                    'Last20Logs'  => $getTop20Log,
                ],
                200,
            );
        } catch (\Throwable $th) {
            \Log::info("error on getAgentDashboardAnalytics-> $th");
            return response()->json(null, 500);
        }
    }
    public function get_zarinpal_payment_link_from_html($htmlText)
    {
        // $htmlText = '<!DOCTYPE html>...'; // your HTML text here

        $crawler = new Crawler();
        $crawler->addHtmlContent($htmlText, 'UTF-8');

        $formTag = $crawler->filter('form')->first();

        if ($formTag) {
            $actionUrl = $formTag->attr('action');
            return $actionUrl; // Output: https://www.zarinpal.com/pg/StartPay/A000000000000000000000000000l353wx62
        } else {
            return '';
        }
    }
    public function get_nowpayment_payment_link_from_html($htmlText)
    {
        $crawler = new Crawler();
        $crawler->addHtmlContent($htmlText, 'UTF-8');

        $metaTag = $crawler->filter('meta[http-equiv="refresh"]')->first();

        if ($metaTag) {
            $redirectLink = $metaTag->attr('content');
            $redirectLink = explode(';', $redirectLink);
            $redirectLink = trim($redirectLink[1]);
            $redirectLink = str_replace("url='", '', $redirectLink);
            $redirectLink = str_replace("'", '', $redirectLink);
            return $redirectLink; // Output: https://nowpayments.io/payment/?iid=5096100130
        } else {
            $linkTag = $crawler->filter('a')->first();
            if ($linkTag) {
                $redirectLink = $linkTag->attr('href');
                return $redirectLink; // Output: https://nowpayments.io/payment/?iid=5096100130
            } else {
                return '';
            }
        }
    }
    public function return_exist_hiddify_config_telegram_text($selectedProduct, $selectedProductCategory, $pannel, $chat_id)
    {
        $hiddifcCntrl         = new HiddifyPannelController();
        $pnlCntrl             = new PannelController();
        $userPannelLink       = $hiddifcCntrl->get_hiddify_subscription_link($pannel->user_link, $selectedProduct->panel_link);
        $userSubscriptionLInk = $hiddifcCntrl->get_hiddify_subscription_link($pannel->user_link, $selectedProduct->subscription_link);
        $image                = $pnlCntrl->generateQrMOC($userSubscriptionLInk);
        $text                 = '';
        $agentCntrl           = new AgentProductController();
        $configStatus         = $agentCntrl->getBoughtProductsStatusFromServerById($selectedProduct->id);

        // روش 1: بررسی نوع داده
        if (is_string($configStatus)) {
            $configStatus = json_decode($configStatus, true);
        }
        // یا
        // روش 2: حذف json_decode
        // $configStatus = $configStatus;

        if ($configStatus != null) {
            $enableText = $configStatus['enable'] == true ? 'فعال' : 'غیر فعال';
            $text       = "📦 وضعیت بسته: {$enableText} \r\n";
            $usageGB    = $configStatus['current_usage_GB'];
            $usageGB    = round($usageGB, 2);
            $limitGB    = $configStatus['usage_limit_GB'];
            $text .= "📊 میزان حجم مصرف شده:  {$usageGB}GB از {$limitGB}GB \r\n";

            $startDate    = $configStatus['start_date'];
            $startDate    = Carbon::parse($startDate);
            $package_days = $configStatus['package_days'];
            $package_days = intval($package_days);
            $expireDate   = Carbon::parse($startDate);
            $expireDate->addDays($package_days);

            $expireDate = $expireDate->toJalali()->format('Y.m.d');
            $startDate  = $startDate->toJalali()->format('Y.m.d');

            $text .= "🗓️ تاریخ شروع: {$startDate} \r\n";
            $text .= "⏳ تاریخ انقضا: {$expireDate} \r\n";
        }
        if ($selectedProductCategory->show_pannel_link == 1) {
            $text .= "🔗 لینک پنل شما برای مشاهده اطلاعات بسته خریداری شده:\r\n{$userPannelLink} \r\n";
        }
        if ($selectedProductCategory->show_subscription_link == 1) {
            $text .= "🔗 لینک سابسکریپشن: \r\n{$userSubscriptionLInk} \r\n";
        }
        app('telegram_bot')->sendMessage($text, $chat_id, null, 'MarkDown');

        $text = "ℹ️ همچینین شما می توانید QRCode ارسال شده را اسکن نمایید. در صورت نیاز به راهنمایی بر روی آموزش استفاده از لینک سابسکریپشن کلیک کنید.\r\n";
        return app('telegram_bot')->imageMessageByLink($image, $chat_id, $text);
    }
    public function new_hiddify_config_telegram_text($selectedPrCat, $pannel, $volume, $day, $chat_id, $productID)
    {
        try {
            $hiddifcCntrl = new HiddifyPannelController();
            $pnlCntrl     = new PannelController();

            $req            = new Request();
            $req->accountId = "$chat_id-$productID";
            $req->pannelID  = $selectedPrCat->pannel_id;
            $req->vol       = $volume;
            $req->day       = $day;

            $newUUID = $hiddifcCntrl->addUserToHiddifyPanel($req); // api v2
            if ($newUUID == false) {

                return false;
            }
            \Log::info("newUUID => $newUUID");
            // $newUUID = $hiddifcCntrl->addUserToHiddifyPanelOldApi($req); // api v1

            $userLink = $pannel->user_link;
            // check $pannel->user_link ended with "/" if be remove it
            if (substr($userLink, -1) == '/') {
                $userLink = substr($userLink, 0, -1);
            }

            $userSubscriptionLInk = "$userLink/{$newUUID}/all.txt?name=sublink-unknown&asn=unknown&mode=new";
            $userPannelLink       = "$userLink/{$newUUID}/#{$req->accountId}";

            $image = $pnlCntrl->generateQrMOC($userSubscriptionLInk);
            $text  = $this->customTextCtrl->getText('action.subscription.hiddify', [
                'panel_link'           => $userPannelLink,
                'userSubscriptionLInk' => $userSubscriptionLInk,
            ]);
            $formatter = new TelegramMessageFormatter($this->telegramService);
            $text      = $formatter->addFormattedText('', $text)->getMessage();
            // save as dectivate product, So we can use it in future when user want to recharge it;
            $resualt = $this->telegramService->sendPhotoFile($chat_id, $image, $text);

            $request                        = new Request();
            $request->account_id            = $chat_id;
            $request->subscription_link     = "/{$newUUID}/all.txt?name=sublink-unknown&asn=unknown&mode=new";
            $request->product_categories_id = $selectedPrCat->id;
            $request->panel_link            = "/{$newUUID}/#{$req->accountId}";
            $request->configs               = '';
            $request->remark                = "$chat_id-$productID";
            $prCntrl                        = new ProductController();
            $prCntrl->addAutomatedProductDetails($request);
            return true;
        } catch (\Throwable $th) {
            \Log::info("error on new_hiddify_config_telegram_text-> $th");
            return false;
        }

    }
    public function send_using_subscription_manual_message($chat_id)
    {
        $opr = [];
        // check faq is active in menu
        $faqItemAliasName = $this->mainMenuItem->getAliasNameByName('آموزش استفاده و سوالات متداول');
        $faqItem          = $this->mainMenuItem->isActiveByAliasName($faqItemAliasName);
        if ($faqItem == true || $faqItem == 1) {
            $opr[] = [
                $faqItemAliasName => "help-faqs",
            ];
        }
        $appDownloadItemAliasName = $this->mainMenuItem->getAliasNameByName('دانلود برنامه');
        $appDownloadItem          = $this->mainMenuItem->isActiveByAliasName($appDownloadItemAliasName);
        if ($appDownloadItem == true || $appDownloadItem == 1) {
            $opr[] = [
                $appDownloadItemAliasName => "help-appDownload",
            ];
        }

        $text = $this->customTextCtrl->getText('action.help.using_subscription');
        $this->telegramService->sendMessageWithInlineKeyboard($chat_id, $text, $opr);
    }
    public function send_insufficient_balance_message($chat_id, $productCategoryID)
    {
        try {
            $productCategory = $this->productCategory->find($productCategoryID);
            if ($productCategory == null) {
                return;
            }

            $user_ballance          = $this->accBlCtrl->getLoggedUserBallancce($chat_id);
            $user_ballance_in_toman = $user_ballance->ballance;
            $user_ballance_in_toman = number_format($user_ballance_in_toman, 0, ',', '.');
            $user_ballance_in_toman = $user_ballance_in_toman . ' تومان';
            $productPriceInToman    = $productCategory->price;
            // calculate the diffrence between user_ballance and productPriceInToman
            $mainDiffrenceInToman  = $diffrence  = $productPriceInToman - $user_ballance->ballance;
            $diffrence             = number_format($diffrence, 0, ',', '.');
            $diffrence             = $diffrence . ' تومان';
            $productPriceInToman   = number_format($productPriceInToman, 0, ',', '.');
            $productPriceInToman   = $productPriceInToman . ' تومان';
            $mainDiffrenceInDollar = $diffrence_in_dollar = 0.00;

            $dollarTransaction = $this->trSetting->getDollarTransactionSetting();
            $text              = '';
            if ($dollarTransaction == true || $dollarTransaction == 1) {
                $productPriceInDollar    = $productPriceInToman / 10;
                $productPriceInDollar    = number_format($productPriceInDollar, 2, ',', '.');
                $productPriceInDollar    = $productPriceInDollar . ' دلار';
                $user_ballance_in_dollar = $user_ballance->account_ballance_in_dollar;
                $mainDiffrenceInDollar   = $diffrence_in_dollar   = $productPriceInDollar - $user_ballance_in_dollar;
                $user_ballance_in_dollar = number_format($user_ballance_in_dollar, 2, ',', '.');
                $user_ballance_in_dollar = $user_ballance_in_dollar . ' دلار';
                $diffrence_in_dollar     = number_format($diffrence_in_dollar, 2, ',', '.');
                $diffrence_in_dollar     = $diffrence_in_dollar . ' دلار';

                $text = $this->customTextCtrl->getText('action.process.insufficient_balance_with_dollar', [
                    'product_category_name'   => $productCategory->name,
                    'product_price_in_toman'  => $productPriceInToman,
                    'product_price_in_dollar' => $productPriceInDollar,
                    'user_balance_in_toman'   => $user_ballance_in_toman,
                    'user_balance_in_dollar'  => $user_ballance_in_dollar,
                    'difference_in_toman'     => $diffrence,
                    'diffrence_in_dollar'     => $diffrence_in_dollar,
                ]);
                $formatter = new TelegramMessageFormatter($this->telegramService);
                $text      = $formatter->addFormattedText('', $text)->getMessage();

            } else {
                $text = $this->customTextCtrl->getText('action.process.insufficient_balance', [
                    'product_category_name'  => $productCategory->name,
                    'product_price_in_toman' => $productPriceInToman,
                    'user_balance_in_toman'  => $user_ballance_in_toman,
                    'difference_in_toman'    => $diffrence,
                ]);
            }
            if (is_array($text)) {
                $formatter = new TelegramMessageFormatter($this->telegramService);
                $text      = $formatter->addFormattedText('', $text)->getMessage();
            } else {
                $text = $text;
                \Log::info('text', ['text' => $text]);
            }

            $this->telegramService->sendMessage($chat_id, $text);
            $this->send_add_ballance_option_message($chat_id, $mainDiffrenceInToman, $mainDiffrenceInDollar);
            return true;
        } catch (\Throwable $th) {
            \Log::info("error on send_insufficient_balance_message-> $th");
            return false;
        }
    }
    public function send_add_ballance_option_message($chat_id, $estimatedPrice, $estimatedPriceInDollar)
    {
        $opr                 = [];
        $request             = new Request();
        $request->account_id = $chat_id;
        $request->amount     = $estimatedPrice;
        $bill                = $this->billCntrl->createNewBill($request);
        $hasZarinPal         = $this->pymntCntrl->getZarinpalStatus();
        if ($hasZarinPal == true || $hasZarinPal == 1) {
            $trRequest             = new Request();
            $trRequest->invoiceID  = $bill->bill_id;
            $trRequest->account_id = $chat_id;
            $trRequest->amount     = $estimatedPrice;
            $paymentLink           = $this->trCntrl->add_order($trRequest);
            // format $estimatedPrice to 0 decimal
            $estimatedPrice = number_format($estimatedPrice, 0, ',', '.');

            $opr[] = [
                'text' => $this->customTextCtrl->getText('action.process.add_online_balance.zarinpal') . " $estimatedPrice تومان",
                'url'  => $paymentLink,
            ];
        }

        $hasDollarPay = $this->trSetting->getDollarTransactionSetting();
        if ($hasDollarPay == true || $hasDollarPay == 1) {

            $bill                  = $this->billCntrl->createNewBillInDollar($request);
            $openLink              = $this->cryptoPymentCntrl->getNowPaymentsLink();
            $trCryptoCntrl         = new TransactionCryptoController();
            $trRequest             = new Request();
            $trRequest->invoiceID  = $bill->bill_id;
            $trRequest->account_id = $chat_id;
            $trRequest->amount     = $estimatedPriceInDollar;
            $paymentLink           = $this->trCryptoCntrl->add_order_crypto_by_nowpayment($trRequest);
            $nowpaymentLink        = $this->get_nowpayment_payment_link_from_html($paymentLink);
            // format $estimatedPrice to 0 decimal
            $estimatedPriceInDollar = number_format($estimatedPriceInDollar, 0, ',', '.');
            $opr[]                  = [
                'text' => $this->customTextCtrl->getText('action.process.add_online_balance.dollarpay.nowpayment') . " $estimatedPriceInDollar دلار",
                'url'  => $nowpaymentLink,
            ];
        }
        if (count($opr) > 0) {
            $text = $this->customTextCtrl->getText('action.process.add_online_balance');
            $this->telegramService->sendMessageWithLinkButtons($chat_id, $text, $opr);
        }

        // send offline item
        $opr = [];

        $offlinePayment = $this->pymntCntrl->getAllActiveOfflinePaymentTypes();
        if ($offlinePayment != null) {
            if ($hasZarinPal == true || $hasZarinPal == 1 || $this->checkDollarPay() == true || $this->checkDollarPay() == 1) {
                $text = $this->customTextCtrl->getText('action.process.add_offline_balance_option_and_online_balance');
            } else {
                $text = $this->customTextCtrl->getText('action.process.add_offline_balance_option');
            }

            $opr = [];

            foreach ($offlinePayment as $key => $value) {
                $opr[] = [
                    "$value->name" => "offlineGateway-$value->id ",
                ];
            }

        }

        $this->telegramService->sendMessageWithInlineKeyboard($chat_id, $text, $opr);
        return true;

    }
}
