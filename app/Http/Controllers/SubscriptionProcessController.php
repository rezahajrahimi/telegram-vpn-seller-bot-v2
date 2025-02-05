<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\TelegramService;
use App\Http\Controllers\CustomTextController;
// add BotUser model
use App\Models\BotUser;
use App\Models\ProductCategory;



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

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
        $this->customTextCtrl = new CustomTextController();
        $this->accBlCtrl = new AccountBallanceController();
        $this->referralCntrl = new ReferralWalletController();
        $this->prCatCntrl = new ProductCategoryController();
        $this->panelCntrl = new PannelController();
        $this->advancedSettingCntrl = new AdvanceSettingLookupController();
        $this->generalCntrl = new GeneralController();
        $this->logCtrl = new LogController();
        $this->botUser = new BotUser();
        $this->selectedPrCat = new ProductCategory();
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
                $opr = [];

                foreach ($panels as $value) {
                    array_push($opr, [['text' => $value, 'callback_data' => 'buySubscriptionByLocation-' . $value]]);
                }

                 $this->telegramService->sendMessageWithKeyboard($chatId, $text, $opr);
                 return true;

            }

            $text = $this->customTextCtrl->getText('action.buy_subscription.select_package');
            $prCat = $this->prCatCntrl->getAllActiveProdctCategoryOrderByPrice();
            $opr = [];

            foreach ($prCat as $key => $value) {
                // هر دکمه به صورت یک ردیف جداگانه
                $buttonText = "$value->category_name - $value->price_in_dollar$ - $value->price تومان";
                $opr[] = [
                    $buttonText => "buySubscription-" . strval($value->id)
                ];
            }

            \Log::info('Button structure: ' . json_encode($opr));
            $this->telegramService->sendMessageWithInlineKeyboard($chatId, $text, $opr);
            return true;


        } catch (\Throwable $th) {
            \Log::error("خطا در خرید اشتراک: " . $th->getMessage());
             $this->telegramService->sendMessage($chatId, $this->customTextCtrl->getText('error.server_error'));
             return false;
        }
    }

    public function buySubscriptionByLocationAction($chatId, $location)
    {
        try {
            $text = $this->customTextCtrl->getText('action.buy_subscription_by_location.location');
            $panelId = $this->panelCntrl->get_pannel_id_by_location($location);

            $prCatCntrl = new ProductCategoryController();
            $prCat = $prCatCntrl->get_all_active_prodct_category_by_pannel_id_order_by_price($panelId);

            $opr = $this->prepareSubscriptionButtons($prCat);

            $this->telegramService->sendMessageWithKeyboard($chatId, $text, $opr);
            return true;

        } catch (\Throwable $th) {
            \Log::error("خطا در انتخاب لوکیشن: " . $th->getMessage());
            $this->telegramService->sendMessage($chatId, $this->customTextCtrl->getText('error.server_error'));
            return false;
        }
    }

    public function buySubscriptionAction($chatId, $subscriptionId)
    {
        try {
            $this->chatId = $chatId;
            $prCat = new ProductCategoryController();
            $this->selectedPrCat = $this->selectedPrCat->getProdctCategorByID($subscriptionId);
            // check if selectedPrCat is null
            if ($this->selectedPrCat == null) {
                return $this->customTextCtrl->getText('action.process.failed_buy');
            }
            // بررسی موجودی کاربر
            $productPrice = $this->selectedPrCat->price;
            $productPriceInDollar = $this->selectedPrCat->price_in_dollar;

            $hasBallance = $this->accBlCtrl->checkUserHasBalance($chatId, $productPrice, $productPriceInDollar);
            \Log::info('hasBallance: ' . $hasBallance);
            // بررسی کیف پول ارجاع
            $hasRefballance = $this->referralCntrl->check_user_has_ref_wallet_ballance($this->chatId, $this->selectedPrCat->price);

            if ($hasRefballance == true || $hasBallance == true || $hasBallance == 1 || $hasRefballance == 1) {
                \Log::info('mojodid dashte: ' . $hasRefballance);
                return $this->processSubscriptionPurchase();
                // return $this->customTextCtrl->getText('action.process.success_buy');
            } else {
                return $this->customTextCtrl->getText('action.process.insufficient_balance');
            }


        } catch (\Throwable $th) {
            \Log::error("خطا در خرید بسته: " . $th->getMessage());
            return $this->customTextCtrl->getText('action.process.failed_buy');
        }
    }


    private function processSubscriptionPurchase()
    {
        try {
            \Log::info('processSubscriptionPurchase');

            $selectedPrCat = $this->selectedPrCat;
             // بررسی موجودی کاربر
            $productPrice = $this->selectedPrCat->price;
            $productPriceInDollar = $this->selectedPrCat->price_in_dollar;
            \Log::info('chatId: ' . $this->chatId);
            $hasBallance = $this->accBlCtrl->checkUserHasBalance($this->chatId, $productPrice, $productPriceInDollar);
            \Log::info('hasBallance: ' . $hasBallance);
            // بررسی کیف پول ارجاع
            $hasRefballance = $this->referralCntrl->check_user_has_ref_wallet_ballance($this->chatId, $this->selectedPrCat->price);

            if (($hasRefballance == false && $hasBallance == false) || ($hasBallance == 0 && $hasRefballance == 0)) {
                \Log::info('processSubscriptionPurchase: ' . $hasBallance);
                return $this->customTextCtrl->getText('action.process.insufficient_balance');
            }
            \Log::info('hasBallance: ' . $hasBallance);

            $productID = $this->selectedPrCat->id;
            $productID += 1;

            $pannel = $this->panelCntrl->getPannelById($this->selectedPrCat->pannel_id);
            $day = $this->selectedPrCat->expire_day;
            $volume = $this->selectedPrCat->volume;
            $productID = $this->selectedPrCat->id;
            $productID += 1;

            if ($pannel->type == 'hiddify') {
                $generalCntrl = new GeneralController();
                $resualt= $generalCntrl->new_hiddify_config_telegram_text($this->selectedPrCat,$pannel,$volume,$day,$this->chatId,$productID);

            } elseif ($pannel->type == 'marzban') {
                // create marzban user
                return " پنل مارزبان";
            }

            return $this->customTextCtrl->getText('action.process.success_buy');



        } catch (\Throwable $th) {
            \Log::error("خطا در خرید بسته: " . $th->getMessage());
            return $this->customTextCtrl->getText('action.process.failed_buy');
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
