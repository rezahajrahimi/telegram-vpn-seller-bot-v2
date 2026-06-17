<?php
namespace App\Http\Controllers;

use App\Jobs\ProcessRemarkJob;
use App\Jobs\ProcessSubscriptionPurchase;
use App\Models\BotUser;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;

// add BotUser model
use App\Models\UserState;
use App\Services\TelegramMessageFormatter;
use App\Services\TelegramService;
use App\Services\SubscriptionPurchaseLock;
use App\Services\InventoryPurchaseService;
use App\Services\PromoCodeService;
use App\Services\PurchaseIntentService;
// add cache
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
    private PaymentTypeController $pymntCntrl;
    private HiddifyPannelController $hiddifyPannelCntrl;
    private PaymentSettingController $paymnetSettingCntrl;
    private AgentProductController $agentProductCtrl;
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
        $this->pymntCntrl           = new PaymentTypeController();
        $this->hiddifyPannelCntrl   = new HiddifyPannelController();
        $this->paymnetSettingCntrl  = new PaymentSettingController();
        $this->agentProductCtrl     = new AgentProductController();
    }

    public function buySubscriptionMenu($chatId)
    {
        try {
            $this->telegramService->sendChatAction($chatId, 'typing');
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

                // Check if all panel names are short (less than 15 characters)
                $allShort = true;
                foreach ($panels as $value) {
                    if (strlen($value) >= 15) {
                        $allShort = false;
                        break;
                    }
                }

                if ($allShort) {
                    $tempRow = [];
                    foreach ($panels as $key => $value) {
                        $buttonText           = $value;
                        $tempRow[$buttonText] = "buySubscriptionByLocation-" . $value;
                        if (count($tempRow) == 2) {
                            $opr[]   = $tempRow;
                            $tempRow = [];
                        }
                    }
                    if (! empty($tempRow)) {
                        $opr[] = $tempRow;
                    }
                } else {
                    foreach ($panels as $key => $value) {
                        $buttonText = $value;
                        $opr[]      = [
                            $buttonText => "buySubscriptionByLocation-" . $value,
                        ];
                    }
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

    public function buySubscriptionAction($chatId, $categoryId)
    {
        try {
            $this->chatId  = $chatId;
            $this->botUser = $this->botUser->getUserByAccountID($chatId);

            if (! $categoryId) {
                $this->telegramService->sendMessage($chatId, "دسته‌بندی نامعتبر است.");
                return "";
            }

            $pricing = $this->agentProductCtrl->resolveProductPricingForAccount($chatId, $categoryId);
            if ($pricing === null) {
                $this->telegramService->sendMessage($chatId, "این بسته برای شما در دسترس نیست.");
                return "";
            }

            $limitMessage = $this->agentProductCtrl->checkAgentPurchaseLimits(
                $chatId,
                (float) ($pricing['category']->volume ?? 0),
                1
            );
            if ($limitMessage !== null) {
                $this->telegramService->sendMessage($chatId, $limitMessage);
                return "";
            }

            (new PurchaseIntentService())->record($chatId, (int) $categoryId, 'package_selected');

            $selectedCategory = $pricing['category'];
            if (! empty($selectedCategory->upsell_category_id)) {
                return $this->showUpsellOffer($chatId, (int) $categoryId, $selectedCategory);
            }

            return $this->showPurchaseConfirm($chatId, (int) $categoryId, $pricing);

        } catch (\Throwable $th) {
            \Log::error("Error in buySubscriptionAction: " . $th->getMessage());
            $this->telegramService->sendMessage($chatId, $this->customTextCtrl->getText('error.server_error'));
            return "";
        }
    }

    public function showPurchaseConfirm($chatId, int $categoryId, array $pricing)
    {
        $category = $pricing['category'];
        $price = number_format((float) $pricing['price'], 0, ',', '.');

        $text = $this->customTextCtrl->getText('action.buy_subscription.confirm', [
            'package' => $category->category_name,
            'price' => $price,
        ]);
        if (is_array($text)) {
            $text = $this->telegramService->formatText($text);
        }

        $confirmLabel = $this->customTextCtrl->getText('action.buy_subscription.button_confirm');
        $promoLabel = $this->customTextCtrl->getText('action.promo.button');
        if (is_array($confirmLabel)) {
            $confirmLabel = $this->telegramService->formatText($confirmLabel);
        }
        if (is_array($promoLabel)) {
            $promoLabel = $this->telegramService->formatText($promoLabel);
        }

        $opr = [
            [$confirmLabel => "confirmBuy-{$categoryId}"],
            [$promoLabel => "applyPromo-{$categoryId}"],
        ];
        $this->telegramService->sendMessageWithInlineKeyboard($chatId, $text, $opr);

        return "";
    }

    public function confirmPurchase($chatId, $categoryId, ?string $promoCode = null)
    {
        try {
            $this->chatId = $chatId;

            if (SubscriptionPurchaseLock::isInProgress($chatId)) {
                return [
                    'alert' => 'خرید قبلی شما در حال پردازش است. لطفاً چند لحظه صبر کنید.',
                ];
            }

            SubscriptionPurchaseLock::markInProgress($chatId);
            ProcessSubscriptionPurchase::dispatch($chatId, $categoryId, $promoCode);
            $this->telegramService->sendChatAction($chatId, 'typing');

            return "";
        } catch (\Throwable $th) {
            \Log::error("Error in confirmPurchase: " . $th->getMessage());
            $this->telegramService->sendMessage($chatId, $this->customTextCtrl->getText('error.server_error'));

            return "";
        }
    }

    public function showUpsellOffer($chatId, int $categoryId, ProductCategory $category)
    {
        $upsell = ProductCategory::find($category->upsell_category_id);
        if ($upsell === null || ! $upsell->is_active) {
            return $this->confirmPurchase($chatId, $categoryId, null);
        }

        $upsellPricing = $this->agentProductCtrl->resolveProductPricingForAccount($chatId, $upsell->id);
        if ($upsellPricing === null) {
            return $this->confirmPurchase($chatId, $categoryId, null);
        }

        $text = $this->customTextCtrl->getText('action.upsell.offer', [
            'current_package' => $category->category_name,
            'upsell_package' => $upsell->category_name,
            'current_price' => number_format((float) $category->price, 0, ',', '.'),
            'upsell_price' => number_format((float) $upsellPricing['price'], 0, ',', '.'),
        ]);
        if (is_array($text)) {
            $text = $this->telegramService->formatText($text);
        }

        $upsellLabel = $this->customTextCtrl->getText('action.upsell.buy_upsell', [
            'package' => $upsell->category_name,
        ]);
        $continueLabel = $this->customTextCtrl->getText('action.upsell.continue_current', [
            'package' => $category->category_name,
        ]);
        $promoLabel = $this->customTextCtrl->getText('action.promo.button');
        if (is_array($upsellLabel)) {
            $upsellLabel = $this->telegramService->formatText($upsellLabel);
        }
        if (is_array($continueLabel)) {
            $continueLabel = $this->telegramService->formatText($continueLabel);
        }
        if (is_array($promoLabel)) {
            $promoLabel = $this->telegramService->formatText($promoLabel);
        }

        $opr = [
            [$upsellLabel => "confirmBuy-{$upsell->id}"],
            [$continueLabel => "confirmBuy-{$categoryId}"],
            [$promoLabel => "applyPromo-{$categoryId}"],
        ];
        $this->telegramService->sendMessageWithInlineKeyboard($chatId, $text, $opr);

        return "";
    }

    public function promptPromoCode($chatId, $categoryId)
    {
        $userState = new UserState();
        $userState->chat_id = $chatId;
        $userState->state = 'promo_code_pending';
        $userState->data = ['category_id' => (int) $categoryId];
        $userState->save();

        $text = $this->customTextCtrl->getText('action.promo.enter_code');
        if (is_array($text)) {
            $text = $this->telegramService->formatText($text);
        }
        $this->telegramService->sendMessage($chatId, $text);

        return "";
    }

    public function handlePromoCodeReply($chatId, string $code)
    {
        $userState = UserState::where('chat_id', $chatId)
            ->whereIn('state', ['promo_code_pending', 'promo_code_pending_recharge'])
            ->latest()
            ->first();

        if ($userState === null) {
            return "";
        }

        if ($userState->state === 'promo_code_pending_recharge') {
            return $this->handleRechargePromoCodeReply($chatId, $code, $userState);
        }

        if (empty($userState->data['category_id'])) {
            return "";
        }

        $categoryId = (int) $userState->data['category_id'];
        $userState->delete();

        $pricing = $this->agentProductCtrl->resolveProductPricingForAccount($chatId, $categoryId);
        if ($pricing === null) {
            $this->telegramService->sendMessage($chatId, 'این بسته برای شما در دسترس نیست.');
            return "";
        }

        $promoService = new PromoCodeService();
        $result = $promoService->validate(
            $code,
            $chatId,
            $categoryId,
            (float) $pricing['price'],
            (float) $pricing['price_in_dollar']
        );

        if (! ($result['valid'] ?? false)) {
            $text = $this->customTextCtrl->getText('action.promo.invalid', [
                'reason' => $result['message'] ?? '',
            ]);
            if (is_array($text)) {
                $text = $this->telegramService->formatText($text);
            }
            $this->telegramService->sendMessage($chatId, $text);
            return "";
        }

        $text = $this->customTextCtrl->getText('action.promo.applied', [
            'code' => strtoupper(trim($code)),
            'discount' => number_format((float) ($result['discount_toman'] ?? 0), 0, ',', '.'),
            'final_price' => number_format((float) ($result['final_price_toman'] ?? 0), 0, ',', '.'),
        ]);
        if (is_array($text)) {
            $text = $this->telegramService->formatText($text);
        }

        $confirmLabel = $this->customTextCtrl->getText('action.promo.confirm_buy');
        if (is_array($confirmLabel)) {
            $confirmLabel = $this->telegramService->formatText($confirmLabel);
        }

        $opr = [[$confirmLabel => "confirmBuyPromo-{$categoryId}-" . strtoupper(trim($code))]];
        $this->telegramService->sendMessageWithInlineKeyboard($chatId, $text, $opr);

        return "";
    }

    public function promptPromoCodeForRecharge($chatId, $productId)
    {
        $userState = new UserState();
        $userState->chat_id = $chatId;
        $userState->state = 'promo_code_pending_recharge';
        $userState->data = ['product_id' => (int) $productId];
        $userState->save();

        $text = $this->customTextCtrl->getText('action.promo.enter_code');
        if (is_array($text)) {
            $text = $this->telegramService->formatText($text);
        }
        $this->telegramService->sendMessage($chatId, $text);

        return "";
    }

    public function handleRechargePromoCodeReply($chatId, string $code, ?UserState $userState = null)
    {
        $userState ??= UserState::where('chat_id', $chatId)
            ->where('state', 'promo_code_pending_recharge')
            ->latest()
            ->first();

        if ($userState === null || empty($userState->data['product_id'])) {
            return "";
        }

        $productId = (int) $userState->data['product_id'];
        $userState->delete();

        $context = $this->resolveRechargeContext($chatId, $productId);
        if (isset($context['error'])) {
            $this->telegramService->sendMessage($chatId, $context['error']);
            return "";
        }

        $prCat = $context['prCat'];
        $pricing = $context['pricing'];

        $promoService = new PromoCodeService();
        $result = $promoService->validate(
            $code,
            $chatId,
            (int) $prCat->id,
            (float) $pricing['price'],
            (float) $pricing['price_in_dollar']
        );

        if (! ($result['valid'] ?? false)) {
            $text = $this->customTextCtrl->getText('action.promo.invalid', [
                'reason' => $result['message'] ?? '',
            ]);
            if (is_array($text)) {
                $text = $this->telegramService->formatText($text);
            }
            $this->telegramService->sendMessage($chatId, $text);
            return "";
        }

        $text = $this->customTextCtrl->getText('action.promo.applied', [
            'code' => strtoupper(trim($code)),
            'discount' => number_format((float) ($result['discount_toman'] ?? 0), 0, ',', '.'),
            'final_price' => number_format((float) ($result['final_price_toman'] ?? 0), 0, ',', '.'),
        ]);
        if (is_array($text)) {
            $text = $this->telegramService->formatText($text);
        }

        $confirmLabel = $this->customTextCtrl->getText('action.promo.confirm_recharge');
        if (is_array($confirmLabel)) {
            $confirmLabel = $this->telegramService->formatText($confirmLabel);
        }

        $opr = [[$confirmLabel => "confirmRechargePromo-{$productId}-" . strtoupper(trim($code))]];
        $this->telegramService->sendMessageWithInlineKeyboard($chatId, $text, $opr);

        return "";
    }

    public function showRechargeConfirm($chatId, int $productId, array $context)
    {
        $prCat = $context['prCat'];
        $pricing = $context['pricing'];
        $price = number_format((float) $pricing['price'], 0, ',', '.');

        $text = $this->customTextCtrl->getText('action.recharge.confirm', [
            'package' => $prCat->category_name,
            'price' => $price,
        ]);
        if (is_array($text)) {
            $text = $this->telegramService->formatText($text);
        }

        $confirmLabel = $this->customTextCtrl->getText('action.recharge.button_confirm');
        $promoLabel = $this->customTextCtrl->getText('action.promo.button');
        if (is_array($confirmLabel)) {
            $confirmLabel = $this->telegramService->formatText($confirmLabel);
        }
        if (is_array($promoLabel)) {
            $promoLabel = $this->telegramService->formatText($promoLabel);
        }

        $opr = [
            [$confirmLabel => "confirmRecharge-{$productId}"],
            [$promoLabel => "applyPromoRecharge-{$productId}"],
        ];
        $this->telegramService->sendMessageWithInlineKeyboard($chatId, $text, $opr);

        return "";
    }

    /**
     * @return array{product: Product, prCat: ProductCategory, pannel: mixed, pricing: array}|array{error: string}
     */
    private function resolveRechargeContext($chatId, $productID): array
    {
        $this->chatId = $chatId;
        $this->botUser = $this->botUser->getUserByAccountID($chatId);

        $product = Product::find($productID);
        if ($product == null) {
            return ['error' => $this->customTextCtrl->getText('error.product_not_found')];
        }

        $prCat = $this->selectedPrCat->getProdctCategorByID($product->product_categories_id);
        if ($prCat == null) {
            return ['error' => $this->customTextCtrl->getText('error.product_category_not_found')];
        }

        $pannel = $this->panelCntrl->getPannelById($prCat->pannel_id);
        if ($pannel?->isInventoryPanel()) {
            return ['error' => $this->customTextCtrl->getText('error.product_not_rechargeable')];
        }

        if ($prCat->rechargable == false || $prCat->rechargable == 0) {
            return ['error' => $this->customTextCtrl->getText('error.product_not_rechargeable')];
        }

        if ($prCat->category_name == 'اکانت آزمایشی' || $prCat->is_active == false || $prCat->is_active == 0) {
            return ['error' => $this->customTextCtrl->getText('error.product_not_rechargeable')];
        }

        $pricing = $this->agentProductCtrl->resolveProductPricingForAccount($chatId, $prCat->id);
        if ($pricing === null) {
            return ['error' => 'این بسته برای شما در دسترس نیست.'];
        }

        if ($pannel == null) {
            return ['error' => $this->customTextCtrl->getText('error.pannel_not_found')];
        }

        return [
            'product' => $product,
            'prCat' => $prCat,
            'pannel' => $pannel,
            'pricing' => $pricing,
        ];
    }

    public function confirmRecharge($chatId, $productId, ?string $promoCode = null)
    {
        try {
            $this->chatId = $chatId;
            $this->botUser = $this->botUser->getUserByAccountID($chatId);
            $this->addNewBotLog('subscription', 'تایید شارژ مجدد', 'show');

            $context = $this->resolveRechargeContext($chatId, $productId);
            if (isset($context['error'])) {
                $this->telegramService->sendMessage($chatId, $context['error']);
                return "";
            }

            $product = $context['product'];
            $prCat = $context['prCat'];
            $pannel = $context['pannel'];
            $productPrice = (float) $context['pricing']['price'];
            $productPriceInDollar = (float) $context['pricing']['price_in_dollar'];

            $appliedPromo = null;
            $promoDiscountToman = 0.0;

            if ($promoCode) {
                $promoService = new PromoCodeService();
                $promoResult = $promoService->validate(
                    $promoCode,
                    $chatId,
                    (int) $prCat->id,
                    $productPrice,
                    $productPriceInDollar
                );

                if (! ($promoResult['valid'] ?? false)) {
                    $this->telegramService->sendMessage($chatId, $promoResult['message'] ?? 'کد تخفیف نامعتبر است.');
                    return "";
                }

                $productPrice = (float) ($promoResult['final_price_toman'] ?? $productPrice);
                $productPriceInDollar = (float) ($promoResult['final_price_dollar'] ?? $productPriceInDollar);
                $promoDiscountToman = (float) ($promoResult['discount_toman'] ?? 0);
                $appliedPromo = $promoResult['promo'] ?? null;
            }

            $hasBallance = $this->accBlCtrl->checkUserHasBalance($chatId, $productPrice, $productPriceInDollar);
            $hasRefballance = $this->referralCntrl->check_user_has_ref_wallet_ballance($chatId, $productPrice);
            if (($hasRefballance == false && $hasBallance == false) || ($hasBallance == 0 && $hasRefballance == 0)) {
                $this->generalCntrl->send_insufficient_balance_message($chatId, $prCat->id);
                return '';
            }

            if ($pannel->type == 'hiddify') {
                $hiddifcCntrl = new HiddifyPannelController();
                $uuid = $hiddifcCntrl->extractUUID($product->subscription_link);
                $day = $prCat->expire_day;
                $volume = $prCat->volume;

                $req = new Request();
                $req->pannelID = $pannel->id;
                $req->name = $product->remark;
                $req->uuid = $uuid;
                $req->vol = $volume;
                $req->day = $day;
                $req->comment = "شارژ مجدد در " . Verta::now();

                $updateRemark = $hiddifcCntrl->rechargeUserOfHiddifyPanelApi($req);
                if ($updateRemark->getStatusCode() == 200) {
                    $paymentSuccess = $this->processPayment($productPrice, $productPriceInDollar, $hasRefballance);
                    if ($paymentSuccess) {
                        if ($appliedPromo) {
                            (new PromoCodeService())->recordUsage($appliedPromo, $chatId, $promoDiscountToman, $product->id);
                        }
                        (new PurchaseIntentService())->completeForAccount($chatId, (int) $prCat->id);
                        $text = $this->customTextCtrl->getText('action.recharge.success');
                        $this->addNewBotLog('subscription', 'تمدید اشتراک با موفقیت انجام شد.', 'show');
                        $this->telegramService->sendMessage($chatId, $text);
                    }
                    return "";
                }
                return $this->customTextCtrl->getText('error.server_error');
            }

            if ($pannel->type == 'sanaei') {
                $sn = new SanaeiPannelController();
                $uuid = json_decode($product->configs ?? '{}', true)['uuid'] ?? null;
                if (! $uuid) {
                    return $this->customTextCtrl->getText('error.server_error');
                }

                $day = $prCat->expire_day;
                $volume = $prCat->volume;

                $ok = $sn->rechargeClient($pannel->id, $uuid, $day, $volume);
                if ($ok) {
                    $paymentSuccess = $this->processPayment($productPrice, $productPriceInDollar, $hasRefballance);
                    if ($paymentSuccess) {
                        if ($appliedPromo) {
                            (new PromoCodeService())->recordUsage($appliedPromo, $chatId, $promoDiscountToman, $product->id);
                        }
                        (new PurchaseIntentService())->completeForAccount($chatId, (int) $prCat->id);
                        $text = $this->customTextCtrl->getText('action.recharge.success');
                        $this->addNewBotLog('subscription', 'تمدید اشتراک با موفقیت انجام شد.', 'show');
                        $this->telegramService->sendMessage($chatId, $text);
                        return "";
                    }
                }
                return $this->customTextCtrl->getText('error.server_error');
            }

            if ($pannel->isMarzbanCompatible()) {
                $mb = MarzbanPannelController::resolve($pannel);
                $day = $prCat->expire_day;
                $volume = $prCat->volume;

                $ok = $mb->rechargeUser($pannel->id, $product->remark, $day, $volume);
                if ($ok) {
                    $paymentSuccess = $this->processPayment($productPrice, $productPriceInDollar, $hasRefballance);
                    if ($paymentSuccess) {
                        if ($appliedPromo) {
                            (new PromoCodeService())->recordUsage($appliedPromo, $chatId, $promoDiscountToman, $product->id);
                        }
                        (new PurchaseIntentService())->completeForAccount($chatId, (int) $prCat->id);
                        $text = $this->customTextCtrl->getText('action.recharge.success');
                        $this->addNewBotLog('subscription', 'تمدید اشتراک با موفقیت انجام شد.', 'show');
                        $this->telegramService->sendMessage($chatId, $text);
                        return "";
                    }
                }
                return $this->customTextCtrl->getText('error.server_error');
            }

            return "";
        } catch (\Throwable $th) {
            \Log::error("خطا در شارژ مجدد: " . $th->getMessage());
            return $this->customTextCtrl->getText('error.server_error');
        }
    }

    public function buySubscriptionByLocationAction($chatId, $location)
    {
        try {
            $this->chatId  = $chatId;
            $this->botUser = $this->botUser->getUserByAccountID($chatId);
            $this->addNewBotLog('subscription', 'وارد بخش خرید اشتراک بر اساس لوکیشن شد.', 'show');
            $text       = $this->customTextCtrl->getText('action.buy_subscription_by_location.location');
            $panelId    = $this->panelCntrl->get_pannel_id_by_location($location);
            $prCat      = $this->resolveProductCategoriesForChat(null, $panelId);

            $this->prepareSubscriptionButtons($prCat);

            return "";

        } catch (\Throwable $th) {
            \Log::error("خطا در انتخاب لوکیشن: " . $th->getMessage());
            $this->telegramService->sendMessage($chatId, $this->customTextCtrl->getText('error.server_error'));
            return "";
        }
    }
    private function resolveProductCategoriesForChat($prCat = null, ?int $panelId = null)
    {
        if ($prCat !== null) {
            return collect($prCat);
        }

        $agentUser = $this->agentProductCtrl->getAgentUserByAccountId($this->chatId);
        if ($agentUser !== null) {
            return $this->agentProductCtrl->getActiveProductCategoriesForAgent($agentUser->id, $panelId);
        }

        $filterByUserGroup = User::hasUserGroupColumn()
            && \Illuminate\Support\Facades\Schema::hasColumn('product_categories', 'allowed_user_group_ids');
        $userGroupId = $filterByUserGroup
            ? User::resolveUserGroupIdForAccount($this->chatId)
            : null;

        if ($panelId !== null) {
            return $this->prCatCntrl->get_all_active_prodct_category_by_pannel_id_order_by_price($panelId, $userGroupId, $filterByUserGroup);
        }

        return $this->prCatCntrl->getAllActiveProdctCategoryOrderByPrice($userGroupId, $filterByUserGroup);
    }

    public function prepareSubscriptionButtons($prCat = null)
    {
        $text = $this->customTextCtrl->getText('action.buy_subscription.select_package');
        if ($prCat === null) {
            $prCat = $this->resolveProductCategoriesForChat();
        }

        if ($prCat->isEmpty()) {
            $this->telegramService->sendMessage($this->chatId, 'هیچ بسته‌ای برای شما تعریف نشده است.');
            return "";
        }

        $opr               = [];
        $dollarTransaction = $this->paymnetSettingCntrl->getPaymentSettingStatusByKey('usd_transaction');
        \Log::info("dollarTransaction: " . $dollarTransaction);
        $showOneRowConfig = $this->advancedSettingCntrl->getValueByNameWithBooleanValue('bot_show_one_row_config');
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

    private function processPayment($productPrice, $productPriceInDollar, $hasRefballance)
    {
        try {
            $request           = new Request();
            $request->userID   = $this->chatId;
            $request->ballance = $productPrice;
            $request->type     = 'toman';

            // تلاش برای کسر از کیف پول تومانی
            $balance = $this->accBlCtrl->decreaseUserAccuntBalanceByUserID($request);
            \Log::info("processPayment balance: " . $balance);
            if ($balance != false || $balance != 0 || $balance != null) {
                $this->addNewBotLog('subscription', 'کسر موجودی از کیف پول کاربر به مقدار ' . $productPrice . ' تومان', 'show');
                return true;
            }

            // بررسی پرداخت دلاری
            $dollarTransaction = $this->paymnetSettingCntrl->getPaymentSettingStatusByKey('usd_transaction');
            \Log::info("dollarTransaction: " . $dollarTransaction);
            if ($dollarTransaction == true || $dollarTransaction == 1) {
                $request->ballance = $productPriceInDollar;
                $request->type     = 'dollar';
                $balance           = $this->accBlCtrl->decreaseUserAccuntBalanceByUserID($request);
                if ($balance != false || $balance != 0 || $balance != null) {
                    $this->addNewBotLog('subscription', 'کسر موجودی از کیف پول کاربر به مقدار ' . $productPriceInDollar . ' دلار', 'show');
                    return true;
                }
            }

            // بررسی کیف پول ارجاع
            if ($hasRefballance == true || $hasRefballance == 1) {
                $balance = $this->referralCntrl->dec_user_ref_wallet_ballance($this->chatId, $productPrice);
                \Log::info("processPayment referral balance: " . $balance);
                if ($balance != false || $balance != 0 || $balance != null) {
                    $this->addNewBotLog('subscription', 'کسر موجودی از کیف پول همکاری به مقدار ' . $productPrice . ' تومان', 'show');
                    return true;
                }
            }
            return false;
        } catch (\Throwable $th) {
            \Log::error("خطا در پرداخت: " . $th->getMessage());
            return false;
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

            $pannel = $this->panelCntrl->getPannelById($this->selectedPrCat->pannel_id);
            $day    = $this->selectedPrCat->expire_day;
            $volume = $this->selectedPrCat->volume;
            $resualt = false;

            $prCntrl = new ProductController();
            $inventoryPurchaseService = new InventoryPurchaseService();
            $soldInventoryProductId = null;

            if ($pannel->isInventoryPanel()) {
                if ($prCntrl->countActiveInventory($this->selectedPrCat->id) < 1) {
                    return 'موجودی این بسته تمام شده است.';
                }

                $soldInventoryProductId = $inventoryPurchaseService->deliverInventoryProduct($this->selectedPrCat, $this->chatId);
                $resualt = $soldInventoryProductId !== false ? $soldInventoryProductId : false;
            } else {
                $reservedProductId = $prCntrl->reserveProductId($this->chatId, $this->selectedPrCat->id);
                if ($reservedProductId === null) {
                    return $this->customTextCtrl->getText('action.process.failed_buy');
                }

                if ($pannel->type == 'hiddify') {
                    $resualt = $this->generalCntrl->new_hiddify_config_telegram_text($this->selectedPrCat, $pannel, $volume, $day, $this->chatId, $reservedProductId);
                } elseif ($pannel->isMarzbanCompatible()) {
                    $resualt = $this->generalCntrl->new_marzban_config_telegram_text(
                        $this->selectedPrCat,
                        $pannel,
                        $volume,
                        $day,
                        $this->chatId,
                        $reservedProductId
                    );
                } elseif ($pannel->type == 'sanaei') {
                    \Log::info("sanaei pannel");
                    $resualt = $this->generalCntrl->new_sanaei_config_telegram_text(
                        $this->selectedPrCat,
                        $pannel,
                        $volume,
                        $day,
                        $this->chatId,
                        $reservedProductId
                    );
                }
            }
            \Log::info("resualt response buoght from sanaei: " . $resualt);

            if ($resualt == false || $resualt == null) {
                $this->addNewBotLog('subscription', 'خرید اشتراک با شکست مواجه شد.', 'show');
                return $this->customTextCtrl->getText('action.process.failed_buy');
            }
            // پردازش پرداخت
            $paymentSuccess = $this->processPayment($productPrice, $productPriceInDollar, $hasRefballance);

            \Log::info("paymentSuccess: " . $paymentSuccess);

            if ($paymentSuccess == false || $paymentSuccess == null) {
                if ($pannel->isInventoryPanel() && $soldInventoryProductId !== null) {
                    $inventoryPurchaseService->rollbackDelivery($soldInventoryProductId);
                } elseif ($pannel->type == 'hiddify') {
                    // remove created product from database and panel
                    $uuid = $resualt;
                    $this->hiddifyPannelCntrl->deleteUserOfHiddifyPanel($pannel->id, $uuid);
                    // delete product from database
                    $prCntrl = new ProductController();
                    $res     = $prCntrl->delete_product_by_uuid($uuid);
                    if ($res) {
                        $this->addNewBotLog('subscription', 'به دلیل عدم داشتن موجودی، حذف کالا از پنل و دیتابیس', 'show');
                    }
                } elseif ($pannel->type == 'sanaei') {
                    // remove created product from Sanaei panel and database
                    $uuid = $resualt;
                    $sn   = new SanaeiPannelController();
                    $sn->deleteUser($pannel->id, $uuid);
                    // delete product from database
                    $prCntrl = new ProductController();
                    $res     = $prCntrl->delete_sanaei_product_by_uuid($uuid);
                    if ($res) {
                        $this->addNewBotLog('subscription', 'به دلیل عدم داشتن موجودی، حذف کالا از پنل سنایی و دیتابیس', 'show');
                    }
                } elseif ($pannel->isMarzbanCompatible()) {
                    $marzbanUsername = $resualt;
                    $mb              = MarzbanPannelController::resolve($pannel);
                    $mb->deleteUser($pannel->id, $marzbanUsername);
                    $prCntrl = new ProductController();
                    $res     = $prCntrl->delete_marzban_product_by_username($marzbanUsername);
                    if ($res) {
                        $this->addNewBotLog('subscription', 'به دلیل عدم داشتن موجودی، حذف کالا از پنل مرزبان و دیتابیس', 'show');
                    }
                }
                return $this->customTextCtrl->getText('action.process.failed_buy');
            }

            // send useful
            $this->generalCntrl->send_using_subscription_manual_message($this->chatId, null, null, $pannel->isInventoryPanel());
            $this->addNewBotLog('subscription', 'خرید اشتراک با موفقیت انجام شد.', 'show');
            return "";

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

    public function buyHistory($chatId, $page = 1)
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
            if (count($opr) > 10) {
                $lastPage = ceil(count($opr) / 10);
                // add pagination if count bigger than 10
                if (count($opr) > 10 && $page == 1) {
                    $opr          = array_chunk($opr, 10);
                    $opr          = $opr[0]; // get first 10 items
                    $nextPage     = 2;
                    $previousPage = 1;
                    $opr[]        = [
                        'ادامه' => "buyHistoryNext-$nextPage",
                    ];
                } elseif ($page > 1) {
                    $firstItemsIndex  = ($page * 10);
                    $firstItemsIndex -= 10; // adjust index for zero-based array
                                            // slice opr array to get 10 items starting from firstItemsIndex
                    if ($firstItemsIndex < 0) {
                        $firstItemsIndex = 0; // prevent negative index
                    }
                    // slice opr array to get 10 items
                    if ($firstItemsIndex >= count($opr)) {
                        $firstItemsIndex = count($opr) - 10; // prevent out of bounds
                    }
                    // slice opr array to get 10 items starting from firstItemsIndex
                    if ($firstItemsIndex < 0) {
                        $firstItemsIndex = 0; // prevent negative index
                    }
                    $opr = array_slice($opr, $firstItemsIndex, 10);

                    // slice opr array to get 10 items
                    // check is last page, if not add next button
                    if ($page < $lastPage) {
                        $nextPage = $page + 1;
                        $opr[]    = ['ادامه' => "buyHistoryNext-$nextPage"];
                    }

                }
                $this->telegramService->sendMessageWithInlineKeyboard($chatId, $text, $opr);
            } elseif (count($opr) < 10) {
                $this->telegramService->sendMessageWithInlineKeyboard($chatId, $text, $opr);
            } else {
                $text = $this->customTextCtrl->getText('action.buy_history.no_history');
                $this->telegramService->sendMessage($chatId, $text);
            }
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
                    $agentCntrl           = new AgentProductController();
                    $configStatus         = $agentCntrl->getBoughtProductsStatusFromServerById($product->id);
                    // check configStatus is json
                    if (is_string($configStatus)) {
                        $configStatus = json_decode($configStatus, true);
                    }
                    $enableText = $configStatus['enable'] == true ? 'فعال' : 'غیر فعال';
                    $usageGB    = $configStatus['current_usage_GB'];
                    $usageGB    = round($usageGB, 2);
                    $limitGB    = $configStatus['usage_limit_GB'];

                    $startDate    = $configStatus['start_date'];
                    $startDate    = Carbon::parse($startDate);
                    $package_days = $configStatus['package_days'];
                    $package_days = intval($package_days);
                    $expireDate   = Carbon::parse($startDate);
                    $expireDate->addDays($package_days);

                    $expireDate = $expireDate->toJalali()->format('Y.m.d');
                    $startDate  = $startDate->toJalali()->format('Y.m.d');

                    $text = $this->customTextCtrl->getText('action.buy_history.history', [
                        'name'              => $product->remark,
                        'category_name'     => $prCat->category_name,
                        'panel_link'        => $userPannelLink,
                        'subscription_link' => $userSubscriptionLInk,
                        'start_date'        => $startDate,
                        'expire_date'       => $expireDate,
                        'usage_limit_GB'    => $limitGB,
                        'usage_GB'          => $usageGB,
                        'enable'            => $enableText,
                    ]);
                    $formatter = new TelegramMessageFormatter($this->telegramService);
                    $text      = $formatter->addFormattedText('', $text)->getMessage();

                    $this->telegramService->sendPhotoFile($chatId, $image, $text);
                    $this->generalCntrl->send_using_subscription_manual_message($chatId, true, $product->id);
                    return "";

                } elseif ($pannel->type == 'sanaei') {
                    // Sanaei panel: retrieve client status using UUID stored in product->configs
                    $configs = json_decode($product->configs ?? '', true) ?? [];
                    $uuid    = $configs['uuid'] ?? null;
                    $sn      = new SanaeiPannelController();
                    if (! $uuid) {
                        return "";
                    }

                    $status = $sn->getClientStatus($pannel->id, $uuid);
                    if (! $status) {
                        return "";
                    }

                    $links = $sn->getUserLinks($pannel->id, $uuid, $product->remark, $product->product_category->inbound_id ?? null);

                    $subId                = $status['client']['subId'] ?? $uuid;
                    $userSubscriptionLink = "";
                    if ($prCat->show_subscription_link) {
                        $userSubscriptionLink = $sn->buildSubscriptionLink($pannel, $subId);
                    } else {
                        if (! empty($product->selectedPrCat->sample_inbound) && ! empty($links)) {
                            $config   = preg_replace('/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/i', $uuid, $product->selectedPrCat->sample_inbound);
                            $userSubscriptionLink = $config;

                        }
                    }

                    $panelLink = $userSubscriptionLink;
                    $pnlCntrl  = new PannelController();
                    $image     = $pnlCntrl->generateQrMOC($panelLink);

                    $enableText = $status['enable'] == true ? 'فعال' : 'غیر فعال';
                    $usageGB    = $status['current_usage_GB'];
                    $usageGB    = round($usageGB, 2);
                    $limitGB    = $status['usage_limit_GB'];

                    $startDate = $status['start_date'];
                    if ($startDate) {
                        $startDate    = Carbon::parse($startDate);
                        $package_days = $status['package_days'] ?? 0;
                        $expireDate   = Carbon::parse($startDate);
                        $expireDate->addDays($package_days);

                        $expireDate = $expireDate->toJalali()->format('Y.m.d');
                        $startDate  = $startDate->toJalali()->format('Y.m.d');
                    } else {
                        $expireDate = '-';
                        $startDate  = '-';
                    }

                    $text = $this->customTextCtrl->getText('action.buy_history.history', [
                        'name'              => $product->remark,
                        'category_name'     => $prCat->category_name,
                        'panel_link'        => $panelLink,
                        'subscription_link' => $userSubscriptionLink,
                        'start_date'        => $startDate,
                        'expire_date'       => $expireDate,
                        'usage_limit_GB'    => $limitGB,
                        'usage_GB'          => $usageGB,
                        'enable'            => $enableText,
                    ]);
                    $formatter = new TelegramMessageFormatter($this->telegramService);
                    $text      = $formatter->addFormattedText('', $text)->getMessage();

                    $this->telegramService->sendPhotoFile($chatId, $image, $text);
                    $this->generalCntrl->send_using_subscription_manual_message($chatId, true, $product->id);
                    return "";

                } elseif ($pannel->isMarzbanCompatible()) {
                    $mb     = MarzbanPannelController::resolve($pannel);
                    $status = $mb->getClientStatus($pannel, $product->remark);
                    if (! $status) {
                        return "";
                    }

                    $userSubscriptionLink = $product->panel_link;
                    if (empty($userSubscriptionLink)) {
                        $userSubscriptionLink = $mb->getSubscriptionLink($pannel, $product->remark) ?? '';
                    }

                    $pnlCntrl = new PannelController();
                    $image    = $pnlCntrl->generateQrMOC($userSubscriptionLink);

                    $enableText = $status['enable'] == true ? 'فعال' : 'غیر فعال';
                    $usageGB    = round($status['current_usage_GB'], 2);
                    $limitGB    = $status['usage_limit_GB'];

                    $expireTs = (int) ($status['expire'] ?? 0);
                    if ($expireTs > 0) {
                        $expireDate = Carbon::createFromTimestamp($expireTs, 'UTC')->toJalali()->format('Y.m.d');
                        $startDate  = Carbon::now('UTC')->toJalali()->format('Y.m.d');
                    } else {
                        $expireDate = '-';
                        $startDate  = '-';
                    }

                    $text = $this->customTextCtrl->getText('action.buy_history.history', [
                        'name'              => $product->remark,
                        'category_name'     => $prCat->category_name,
                        'panel_link'        => $userSubscriptionLink,
                        'subscription_link' => $userSubscriptionLink,
                        'start_date'        => $startDate,
                        'expire_date'       => $expireDate,
                        'usage_limit_GB'    => $limitGB,
                        'usage_GB'          => $usageGB,
                        'enable'            => $enableText,
                    ]);
                    $formatter = new TelegramMessageFormatter($this->telegramService);
                    $text      = $formatter->addFormattedText('', $text)->getMessage();

                    $this->telegramService->sendPhotoFile($chatId, $image, $text);
                    $this->generalCntrl->send_using_subscription_manual_message($chatId, true, $product->id);

                    return "";

                } elseif ($pannel->isInventoryPanel()) {
                    $text = "📦 بسته: {$product->remark}\r\n";
                    $text .= "🏷️ دسته: {$prCat->category_name}\r\n";

                    if ($prCat->show_subscription_link && ! empty($product->subscription_link)) {
                        $text .= "🔗 لینک سابسکریپشن:\r\n{$product->subscription_link}\r\n";
                    }

                    if ($prCat->shouldSendConfigToUser() && ! empty($product->configs)) {
                        $configLinks = ProductCategory::extractConfigLinks($product->configs);
                        if ($configLinks !== []) {
                            foreach ($configLinks as $link) {
                                $image = $this->panelCntrl->generateQrMOC($link);
                                $this->telegramService->sendPhotoFile($chatId, $image, $link);
                            }
                        } else {
                            $text .= "⚙️ کانفیگ:\r\n{$product->configs}\r\n";
                        }
                    }

                    if (trim($text) !== '') {
                        $this->telegramService->sendMessage($chatId, $text);
                    }

                    $this->generalCntrl->send_using_subscription_manual_message($chatId, true, $product->id);

                    return "";
                }

            }
            return "";
        } catch (\Throwable $th) {
            \Log::error("خطا در سابقه خرید: " . $th->getMessage());
            return $this->customTextCtrl->getText('error.server_error');
        }
    }
    public function recharge($chatId, $productID)
    {
        try {
            $this->chatId = $chatId;
            $this->botUser = $this->botUser->getUserByAccountID($chatId);
            $this->addNewBotLog('subscription', 'وارد بخش شارژ مجدد شد.', 'show');

            $context = $this->resolveRechargeContext($chatId, $productID);
            if (isset($context['error'])) {
                $this->telegramService->sendMessage($chatId, $context['error']);
                return "";
            }

            (new PurchaseIntentService())->record(
                $chatId,
                (int) $context['prCat']->id,
                'recharge_pending',
                (int) $productID
            );

            return $this->showRechargeConfirm($chatId, (int) $productID, $context);
        } catch (\Throwable $th) {
            \Log::error("خطا در شارژ مجدد: " . $th->getMessage());
            return $this->customTextCtrl->getText('error.server_error');
        }
    }
    public function batchExistSubscriptionJob(Request $request)
    {
        // check license
        $authCntrl             = new AuthController();
        $getPowerPsLicenseType = $authCntrl->getPowerPsLicenseType();
        if ($getPowerPsLicenseType == "false" || $getPowerPsLicenseType == "trial" || $getPowerPsLicenseType == "boronze") {
            return response()->json(['status' => 'error', 'message' => 'لایسنس شما منقضی شده است.']);
        }
        $action        = $request->action;
        $listOfConfigs = json_decode($request['configs'], true);
        $panelID       = $request->panel_id;
        $extra         = $request->all();
        // اگر chat_id ارسال شده بود، پیام به کاربر بده
        if ($request->has('chat_id')) {
            try {
                $chatId = $request->input('chat_id');
                $this->telegramService->sendMessage($chatId, 'درخواست شما دریافت شد و در حال اجراست.');
            } catch (\Throwable $th) {
                \Log::error('خطا در ارسال پیام به کاربر: ' . $th->getMessage());
            }
        }
        // Dispatch Job
        \App\Jobs\BatchSubscriptionJob::dispatch(
            $action,
            $listOfConfigs,
            $panelID,
            $extra
        );
        return response()->json(['status' => 'success', 'message' => 'درخواست شما دریافت شد و در حال اجراست.']);
    }
    public function remark($chatId, $productID)
    {
        try {
            $this->handleActionRemark($chatId, $productID);
            return "";
        } catch (\Throwable $th) {
            \Log::error("خطا در تغییر نام بسته: " . $th->getMessage());
            return $this->customTextCtrl->getText('error.server_error');
        }
    }

    public function deleteHistory($chatId, $productID)
    {
        try {
            $this->chatId  = $chatId;
            $this->botUser = $this->botUser->getUserByAccountID($chatId);

            $product = Product::find($productID);
            if ($product == null || (string) $product->account_id !== (string) $chatId) {
                return $this->customTextCtrl->getText('error.history_not_found');
            }

            $prCat = $this->selectedPrCat->getProdctCategorByID((int) $product->product_categories_id);
            $pannel = $prCat ? $this->panelCntrl->getPannelById($prCat->pannel_id) : null;
            if ($pannel?->isInventoryPanel()) {
                return $this->customTextCtrl->getText('error.history_not_found');
            }

            $text = $this->customTextCtrl->getText('action.delete_history.confirm', [
                'name' => $product->remark,
            ]);
            if (is_array($text)) {
                $text = $this->telegramService->formatText($text);
            }

            $confirmText = $this->customTextCtrl->getText('action.delete_history.confirm_button');
            $cancelText  = $this->customTextCtrl->getText('action.delete_history.cancel_button');
            if (is_array($confirmText)) {
                $confirmText = $this->telegramService->formatText($confirmText);
            }
            if (is_array($cancelText)) {
                $cancelText = $this->telegramService->formatText($cancelText);
            }

            $opr = [
                [$confirmText => "confirmDeleteHistory-{$productID}"],
                [$cancelText => "buyHistory-{$productID}"],
            ];

            $this->telegramService->sendMessageWithInlineKeyboard($chatId, $text, $opr);

            return "";
        } catch (\Throwable $th) {
            \Log::error("خطا در نمایش تایید حذف بسته: " . $th->getMessage());

            return $this->customTextCtrl->getText('error.server_error');
        }
    }

    public function confirmDeleteHistory($chatId, $productID)
    {
        try {
            $this->chatId  = $chatId;
            $this->botUser = $this->botUser->getUserByAccountID($chatId);

            $product = Product::with('product_category_and_panel')->find($productID);
            if ($product == null || (string) $product->account_id !== (string) $chatId) {
                return $this->customTextCtrl->getText('error.history_not_found');
            }

            $prCat  = $this->selectedPrCat->getProdctCategorByID((int) $product->product_categories_id);
            $pannel = $this->panelCntrl->getPannelById($prCat->pannel_id);
            if ($pannel == null) {
                return $this->customTextCtrl->getText('error.server_error');
            }

            $this->telegramService->sendChatAction($chatId, 'typing');

            if (! $this->deleteProductOnPanel($product, $pannel)) {
                $text = $this->customTextCtrl->getText('action.delete_history.failed');
                if (is_array($text)) {
                    $text = $this->telegramService->formatText($text);
                }
                $this->telegramService->sendMessage($chatId, $text);

                return "";
            }

            $remark = $product->remark;
            $product->delete();

            $this->addNewBotLog('history', "بسته $remark حذف شد.", 'delete product');

            $text = $this->customTextCtrl->getText('action.delete_history.success', [
                'name' => $remark,
            ]);
            if (is_array($text)) {
                $text = $this->telegramService->formatText($text);
            }
            $this->telegramService->sendMessage($chatId, $text);

            return $this->buyHistory($chatId, 1);
        } catch (\Throwable $th) {
            \Log::error("خطا در حذف بسته: " . $th->getMessage());

            return $this->customTextCtrl->getText('error.server_error');
        }
    }

    private function deleteProductOnPanel(Product $product, $pannel): bool
    {
        if ($pannel->type == 'sanaei') {
            $configs = json_decode($product->configs ?? '', true) ?? [];
            $uuid    = $configs['uuid'] ?? null;
            if ($uuid == null) {
                return false;
            }
            $sn  = new SanaeiPannelController();
            $res = $sn->deleteUser($pannel->id, $uuid);

            return $res !== false && $res !== null;
        }

        if ($pannel->isMarzbanCompatible()) {
            $mb = MarzbanPannelController::resolve($pannel);

            return $mb->deleteUser($pannel->id, $product->remark);
        }

        $uuid = $this->hiddifyPannelCntrl->extractUUID($product->subscription_link);
        if ($uuid == null || $uuid === '') {
            return false;
        }

        $result = $this->hiddifyPannelCntrl->deleteUserOfHiddifyPanel($pannel->id, $uuid);
        if (is_object($result) && method_exists($result, 'getStatusCode')) {
            return $result->getStatusCode() == 200;
        }

        return $result !== false;
    }

    public function remarkReply($chatId, $newName)
    {
        try {
            if ($newName == null || trim($newName) == 'لغو' || trim($newName) == 'cancel') {
                $this->clearAwaitingReply($chatId, $this->customTextCtrl->getText('action.remark.cancel'));
                return "";
            }

            $user_state = UserState::where('chat_id', $chatId)->latest()->first();

            if (! $user_state || ! $user_state->data) {
                $this->clearAwaitingReply($chatId, $this->customTextCtrl->getText('error.server_error'));
                return "";
            }

            $productId = $user_state->data;

            // Dispatch the job
            ProcessRemarkJob::dispatch($chatId, $productId, $newName);

            $this->telegramService->sendChatAction($chatId, 'typing');
            $this->telegramService->sendMessage($chatId, "در حال تغییر نام بسته، لطفاً صبر کنید...");

            return "";

        } catch (\Throwable $th) {
            \Log::error("Error in remarkReply: " . $th->getMessage());
            $this->clearAwaitingReply($chatId, $this->customTextCtrl->getText('error.server_error'));
            return "";
        }
    }

    private function handleActionRemark(string $chatId, int $productID): string
    {
        $this->setAwaitingReply($chatId, 'remark_reply', $productID);
        $this->telegramService->forceReply($chatId, $this->customTextCtrl->getText('action.remark.title'));
        return "";
    }
    public function setAwaitingReply(string $chatId, string $type, int $id): void
    {
        $user_state          = new UserState();
        $user_state->chat_id = $chatId;
        $user_state->state   = 'remark_reply';
        $user_state->data    = $id;
        $user_state->save();

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

    private function clearAwaitingReply(string $chatId, string | array $text): void
    {
        try {
            $text = $this->telegramService->formatText($text);
            Cache::forget("awaiting_reply_{$chatId}");
            // delete last user state where chat_id == $chatId
            $user_state = UserState::where('chat_id', $chatId)->latest()->first();
            if ($user_state != null) {
                $user_state->delete();
            }
            $this->generalCntrl->return_main_menu_items($chatId, $text);
        } catch (\Throwable $th) {
            \Log::error("خطا در پاک کردن حالت کاربر: " . $th->getMessage());
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
