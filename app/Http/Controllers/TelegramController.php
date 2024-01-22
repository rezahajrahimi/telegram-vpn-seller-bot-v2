<?php
// https://api.telegram.org/bot6650381860:AAFCJka-B2NsIY5RlATIOQvlXiOpKdDqUlM/setwebhook?url=https://a9a6-77-232-143-246.ngrok-free.app/api/telegram/webhooks/inbound

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Cache;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

use Illuminate\Http\Request;

class TelegramController extends Controller
{
    public $buySubscriptionLevel = 1;
    public $buySubSelectTypeLevel = 11;
    public $currentMenuLevel = 0;
    public $userCommandArr = [];
    public $from_id;
    public $text;
    public $first_name;
    public $caption;
    public $chat_id;
    public $last_name;
    public $username;
    public $message_id;
    public $forward_from_name;
    public $forward_from_id;
    public $callbackId;
    public $data;
    public $chat_type;
    public $markup;
    public $fileId;

    public function inbound(Request $request)
    {
        $srtyCtrl = new ServiceTypeController();
        $prcaCtrl = new ProductCategoryController();
        $prCtrl = new ProductController();
        $accBlCtrl = new AccountBallanceController();
        $pymCtrl = new PaymentTypeController();
        $trnsCtrl = new TransactionController();
        $ordCtrl = new OrderController();
        $settingCtrl = new SettingController();
        $botUserCtrl = new BotUserController();
        $menuCntrl = new MenuLevelController();
        $channelLockCtrl = new ChannelLockController();
        $buySubscriptionLevel = 1;
        $servicetypeLevel = 2;
        $productCategoryLevel = 3;
        \Log::info($request->all());
        try {
            try {
                if (isset($request->message['photo'])) {
                    \Log::info('Messsss Photo Started');
                    $this->message_id = $request->message['message_id'];
                    $this->chat_id = $request->message['chat']['id'];
                    $this->username = $request->message['from']['username'] ?? ' ندارد ';
                    $this->from_id = $request->message['from']['id'];
                    $this->first_name = $request->message['from']['first_name'] ?? '';
                    $this->last_name = $request->message['from']['last_name'] ?? '';
                    $this->text = $request->message["caption"] ?? "";

                    $text = 'رسید شما دریافت شد، منتظر تایید توسط مدیر باشید.';
                    $this->fileId = app('telegram_bot')->getImageId($request->message['photo']);
                    $this->chat_type = 'image';
                    $result = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');

                    //send image to admin
                    $chId = $this->chat_id;
                    $this->sendMessageToAdmin($this->chat_id, $this->fileId, "کاربر: $chId یک تصوبر ارسال کرد ", 'image');
                    $transactionCntrl = new TransactionController();
                    $imageTrCntrl = new TransactionImageController();
                    $transactionID = $transactionCntrl->addUserTranaction($this->chat_id, 0, '000', 0);
                    $request = new Request();
                    $request->transaction_id = $transactionID;
                    $request->img_src = $this->fileId;
                    $request->account_id = $this->chat_id;
                    $request->user_text = $this->text ?? "بدون متن";

                    $imageTrCntrl->addNewTransactionImage($request);
                    return response()->json($result, 200);
                    \Log::info('Messsss Photo Ended');
                } elseif (isset($request->message)) {
                    $this->from_id = $request->message['from']['id'];
                    $this->text = $request->message['text'];
                    $this->first_name = $request->message['from']['first_name'];
                    $this->caption = $request->message['caption'] ?? '';
                    $this->chat_id = $request->message['chat']['id'] ?? 0;
                    $this->last_name = $request->message['from']['last_name'];
                    $this->username = $request->message['from']['username'] ?? ' ندارد ';
                    $this->message_id = $request->message['message_id'];
                    $this->forward_from_name = $request->message['reply_to_message']['forward_sender_name'] ?? 0;
                    $this->forward_from_id = $request->message['reply_to_message']['forward_from']['id'] ?? 0;
                    $this->reply_text = $request->message['reply_to_message']['text'] ?? '0';
                    $this->chat_type = 'text';

                } elseif (isset($request->callback_query)) {
                    $this->callbackId = $request->callback_query['id'];
                    $this->data = $request->callback_query['data'];
                    $this->text = $request->callback_query['message']['text'];
                    $this->message_id = $request->callback_query['message']['message_id'];
                    $this->chat_id = $request->callback_query['message']['chat']['id'];
                    $this->chat_type = $request->callback_query['message']['chat']['type'];
                    $this->username = $request->callback_query['from']['username'] ?? ' ندارد ';
                    $this->from_id = $request->callback_query['from']['id'];
                    $this->first_name = $request->callback_query['from']['first_name'] ?? '';
                    $this->last_name = $request->callback_query['from']['last_name'] ?? '';

                    $this->markup = json_decode(json_encode($request->callback_query['message']['reply_markup']['inline_keyboard']), true);
                    $this->chat_type = 'callbacj';

                    $this->recogniseMessage();
                }
            } catch (\Throwable $th) {
                \Log::info("Throwable:  $th");

                if (isset($request->callback_query)) {
                    $this->callbackId = $request->callback_query['id'];
                    $this->data = $request->callback_query['data'];
                    $this->text = $request->callback_query['message']['text'];
                    $this->message_id = $request->callback_query['message']['message_id'];
                    $this->chat_id = $request->callback_query['message']['chat']['id'];
                    $this->chat_type = $request->callback_query['message']['chat']['type'];
                    $this->username = $request->callback_query['from']['username'] ?? ' ندارد ';
                    $this->from_id = $request->callback_query['from']['id'];
                    $this->first_name = $request->callback_query['from']['first_name'] ?? '';
                    $this->last_name = $request->callback_query['from']['last_name'] ?? '';

                    $this->markup = json_decode(json_encode($request->callback_query['message']['reply_markup']['inline_keyboard']), true);
                    $this->recogniseMessage();
                }
            }

            // if (!cache()->has("chat_id_{$this->from_id}") && $this->currentMenuLevel == 0) {
            // \Log::info("from_id:  $this->from_id");

            if (!$botUserCtrl->hasRegistred($this->from_id, $this->username, $this->first_name, $this->last_name)) {
                $this->text = $settingCtrl->getWelcomeMessage();
                cache()->put("chat_id_{$this->from_id}", true, now()->addMinute(10));
                app('telegram_bot')->sendMessage($this->text, $this->chat_id, null, 'MarkDown');
                $this->defaultMenu();
            } else {
                $channelLock = $this->checkIsChannelsMember($this->from_id);
                if ($channelLock == true || $channelLock == 1) {
                    $this->changeMenuLevel();
                } else {
                    $this->channelLockMenu();
                }
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");
        }
    }
    public function defaultMenu()
    {
        // array_push($opr, [['text' => 'بازگشت', 'callback_data' => 'بازگشت'], ['text' => 'پشتیبانی', 'callback_data' => 'پشتیبانی']]);
        $text = 'یک گزینه را انتخاب کنید.';

        $menu = new MainMenuItemController();
        $menuItem = $menu->getAllActivatedMainMenuItems();
        $opr = [];
        $index = 0;
        foreach ($menuItem as $key => $value) {
            array_push($opr, [['text' => $value->alias_name, 'callback_data' => "main-{$value->id}"]]);
        }
        // $opr = [[['text' => 'خرید اشتراک', 'callback_data' => 'buySubscription'], ['text' => 'سابقه خرید', 'callback_data' => 'subscriptionHistory']], [['text' => 'اطلاعات حساب', 'callback_data' => 'accountDetails'], ['text' => 'دریافت آموزش', 'callback_data' => 'learning'], ['text' => 'پشتیبانی', 'callback_data' => 'support']]];

        $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
        // $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
        $this->setNewLevel($this->buySubscriptionLevel);
        return response()->json($result, 200);
    }
    public function deleteMessage()
    {
        try {
            $result = app('telegram_bot')->deleteMessage($this->chat_id, $this->message_id);
        } catch (\Throwable $th) {
            \Log::info("Throwable deleteMessage: $th");
        }
    }
    public function editMessage()
    {
        try {
            $result = app('telegram_bot')->editMessage($this->chat_id, $this->message_id);
        } catch (\Throwable $th) {
            \Log::info("Throwable editMessage: $th");
        }
    }
    public function recogniseMessage()
    {
        try {
            if ($this->chat_type == 'image') {
                $result = app('telegram_bot')->imageMessage($image_url, $admin_id, $text);

                return response()->json($result, 200);
            }
            $this->userCommandArr = explode('-', $this->data);
            $command = $this->userCommandArr[0];
            \Log::info("command recognise: $command");

            return $this->userCommandArr;
        } catch (\Throwable $th) {
            $this->userCommandArr = ['start'];
            \Log::info("Throwable $th");

            return $this->userCommandArr;
        }
    }
    public function changeMenuLevel()
    {
        if ($this->currentMenuLevel != 0) {
            $this->currentMenuLevel -= 1;
        }
        $this->userText = '';
        $menuCntrl = new MenuLevelController();

        $menuCntrl->newUserLevel($this->chat_id, $this->currentMenuLevel);
        if ($this->userCommandArr == null) {
            $this->userCommandArr = ['start'];
        }
        switch ($this->userCommandArr[0]) {
            case 'main':
                $this->subMainMenu();
                break;
            case 'buySubscription':
                $this->subBuySubscription();
                break;
            case 'addAccountBalance':
                $this->addAccountBalance();
                break;
            case 'subAccountBalance':
                $this->subAccountBalance();
                break;
            case 'subBuyHistory':
                $this->subBuyHistory();
                break;
            case 'subSupport':
                $this->subSupport();
                break;
            case 'subFaq':
                $this->subFaq();
                break;
            // case 'addAccountBalance':
            //     $this->subFaq();
            //     break;

            default:
                $this->defaultMenu();
                break;
        }
        // switch ($this->currentMenuLevel) {
        //     case 0:
        //         $this->defaultMenu();

        //         break;
        //     case $this->buySubscriptionLevel:
        //         $this->buySubscription();

        //         break;

        //     default:
        //         $this->defaultMenu();
        //         break;
        // }
        return;
    }
    public function subMainMenu()
    {
        $this->addNewBotLog('menu', 'وارد منوی اصلی ربات شد.', 'show');

        $menu = new MainMenuItemController();

        $selectedSubMenu = $menu->getMenuNameByID($this->userCommandArr[1]);
        \Log::info("selectedSubMenu:  $selectedSubMenu->name");

        switch ($selectedSubMenu->name) {
            case 'خرید اشتراک':
                $this->buySubscription();
                break;
            case 'سابقه خرید':
                $this->buyHistory();
                break;
            case 'پشتیبانی':
                $this->supports();
                break;
            case 'آموزش استفاده و سوالات متداول':
                $this->faqs();
                break;
            case 'اطلاعات حساب':
                $this->accountDetails();
                break;
            // case "اکانت تستی":
            // $this->buySubscription();
            //     break;

            default:
                $this->defaultMenu();
                break;
        }
        return;
    }
    public function setNewLevel($level)
    {
        $menlvCtrl = new MenuLevelController();
        $menlvCtrl->newUserLevel($this->chat_id, $level);
    }
    public function buySubscription()
    {
        // $this->deleteMessage();
        $this->addNewBotLog('subscription', 'وارد بخش خرید اشتراک شد.', 'show');

        $text = 'بسته خود را انتخاب کنید.';
        $prCatCntrl = new ProductCategoryController();

        $prCat = $prCatCntrl->getAllProdctCategoryOrderByPrice();
        $opr = [];
        $index = 0;
        array_push($opr, [['text' => 'قیمت(تومان)', 'callback_data' => '0'], ['text' => 'بسته', 'callback_data' => '0']]);
        foreach ($prCat as $key => $value) {
            array_push($opr, [['text' => "$value->price", 'callback_data' => "buySubscription-$value->id"], ['text' => "$value->category_name", 'callback_data' => "buySubscription-$value->id"]]);
        }

        $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
        // $result = app('telegram_bot')->editMessageReplyMarkup( $this->chat_id,$this->message_id,$opr,);
        $this->setNewLevel($this->buySubscriptionLevel);
        return response()->json($result, 200);
    }
    public function subBuySubscription()
    {
        $this->deleteMessage();

        $prCat = new ProductCategoryController();

        $id = $this->userCommandArr[1];
        \Log::info("userCommandArr: $id");

        $selectedPrCat = $prCat->getProdctCategoryNameByID($this->userCommandArr[1]);
        $this->addNewBotLog('subscription', "بسته $selectedPrCat->category_name را انتخاب کرد.", 'buy subscription');

        // check user account balance
        $productPrice = $selectedPrCat->price;
        \Log::info("selectedPrCat->price: $productPrice");
        $opr = [];
        $accBlCtrl = new AccountBallanceController();
        $prCntrl = new ProductController();
        $prcCntrl = new ProductController();

        if ($accBlCtrl->checkUserHasBalance($this->chat_id, $productPrice)) {
            $userAccouintBallance = $accBlCtrl->getUserAccuntBalance($this->chat_id);

            // check pannel type
            $pnlCntrl = new PannelController();
            $pannel = $pnlCntrl->getPannelById($selectedPrCat->pannel_id);
            // get selected item specefic data
            $day = $selectedPrCat->expire_day;
            $volume = $selectedPrCat->volume;
            $productID = $prCntrl->getLastInsertedProductId();
            $productID += 1;
            if ($pannel->type == 'hiddify') {
                $newUUID = $pnlCntrl->createHiddifyUser("$this->chat_id-$productID", $day, $volume, $selectedPrCat->pannel_id);
                $userPannelLink = $pnlCntrl->getHiddifyPannelLinkByPannelID($selectedPrCat->pannel_id);

                $userSubscriptionLInk = "$userPannelLink/$newUUID/all.txt?name=sublink-unknown&asn=unknown&mode=new";

                $image = $pnlCntrl->generateQrMOC($userSubscriptionLInk);
                $text = '';
                $text .= "خرید شما با موفقیت انجام شد\r\n";
                $text .= "لینک پنل شما برای مشاهده اطلاعات بسته خریداری شده:$userPannelLink/$newUUID/ \r\n";
                $text .= "لینک سابسکریپشن: $userSubscriptionLInk \r\n";
                $text .= "همچینین شما می توانید QRCode ارسال شده را اسکن نمایید. در صورت نیاز به راهنمایی بر روی آموزش استفاده از لینک سابسکریپشن کلیک کنید.\r\n";

                $resualt = app('telegram_bot')->imageMessageByLink($image, $this->chat_id, $text);
                // save as dectivate product, So we can use it in future when user want to recharge it;
                $request = new Request();
                $request->account_id = $this->chat_id;
                $request->subscription_link = $userSubscriptionLInk;
                $request->product_categories_id = $selectedPrCat->id;
                $request->panel_link = "$userPannelLink/$newUUID/";
                $request->configs = '';
                $request->remark = "$this->chat_id-$productID";

                $prcCntrl->addAutomatedProductDetails($request);
            } elseif ($pannel->type == 'marzban') {
                $userData = $pnlCntrl->createMarzbanUser("BotUser$this->chat_id$productID", $day, $volume, $selectedPrCat->pannel_id);
                $userSub = $userData['subscription_link'];
                $links = $userData['links'];

                $text = '';
                $text .= "خرید شما با موفقیت انجام شد\r\n";
                $text .= "لینک پنل شما برای مشاهده اطلاعات بسته خریداری شده:$userSub \r\n";
                $text .= "کانفیگهای شما: \r\n";
                $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');

                foreach ($links as $key => $link) {
                    $image = $pnlCntrl->generateQrMOC($link);

                    $resualt = app('telegram_bot')->imageMessageByLink($image, $this->chat_id, $link);

                    // $text .= "$link \r\n";
                }
                // $text .= "لینک سابسکریپشن: $userSubscriptionLInk \r\n";
                $text = "جهت نیاز به راهنمایی بر روی یکی از این گزینه ها کلیک کنید. \r\n";
                $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');

                // save as dectivate product, So we can use it in future when user want to recharge it;
                $request = new Request();
                $request->account_id = $this->chat_id;
                $request->subscription_link = '';
                $request->product_categories_id = $selectedPrCat->id;
                $request->panel_link = $userSub;
                // convert links arrey to string
                $links = json_encode($links);

                $request->configs = $links;
                $request->remark = "BotUser$this->chat_id$productID";

                $prcCntrl->addAutomatedProductDetails($request);
            } else {
                $userData = $prcCntrl->getProductConfigAndChangeStatus($selectedPrCat->id, $this->chat_id);
                // $pannelLink = $userData["panel_link"];

                $text = '';
                $text .= "خرید شما با موفقیت انجام شد\r\n";
                if ($userData->panel_link != null) {
                    $pannel = $userData->panel_link;
                    $text .= "لینک پنل شما برای مشاهده اطلاعات بسته خریداری شده:$pannel\r\n";
                }
                if ($userData->subscription_link != null) {
                    $userSub = $userData->subscription_link;
                    $text .= "لینک سابسکریپشن: $userSub \r\n";
                    $image = $pnlCntrl->generateQrMOC($userSub);
                    $text .= "همچینین شما می توانید QRCode ارسال شده را اسکن نمایید. در صورت نیاز به راهنمایی بر روی آموزش استفاده از لینک سابسکریپشن کلیک کنید.\r\n";

                    $resualt = app('telegram_bot')->imageMessageByLink($image, $this->chat_id, $text);
                    $text = '';
                }
                if ($userData->configs != null) {
                    // json decode $userData->configs
                    $links = json_decode($userData->configs);
                    // check is $links is array or not
                    if (is_array($links)) {
                        foreach ($links as $key => $link) {
                            $image = $pnlCntrl->generateQrMOC($link);

                            $resualt = app('telegram_bot')->imageMessageByLink($image, $this->chat_id, $link);
                        }
                        $text = '';
                    } else {
                        $text .= "کانفیگ: \r\n";
                        $text .= "$userData->configs \r\n";
                    }
                }
                $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');
                $this->addNewBotLog('subscription', 'خرید یسته با موفقیت انجام شد.', 'successfull buy subscription');

                // $text .= "لینک سابسکریپشن: $userSubscriptionLInk \r\n";
                $text = "جهت نیاز به راهنمایی بر روی یکی از این گزینه ها کلیک کنید. \r\n";
                $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');
            }

            // minus balance
            $accBlCtrl->decUserAccuntBalance($this->chat_id, $productPrice);
            $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت خرید بسته کم شد.", 'minus ballance');

            // send how to use
            $opr = [];
            array_push($opr, [
                [
                    'text' => 'آموزش استفاده',
                    'callback_data' => 'help-subscription',
                ],
            ]);
            array_push($opr, [
                [
                    'text' => 'برنامه های مورد نیاز',
                    'callback_data' => 'help-applications',
                ],
            ]);
            array_push($opr, [
                [
                    'text' => 'بازگشت به منوی اصلی',
                    'callback_data' => 'main menu',
                ],
            ]);
            $text = 'یک گزینه را انتخاب کنید.';
            $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);

            return response()->json($result, 200);
        } else {
            $userAccouintBallance = $accBlCtrl->getUserAccuntBalance($this->chat_id);
            // get item price
            $estimatedPrice = $productPrice - $userAccouintBallance;

            // create new invoice
            $billCntrl = new BillController();
            $request = new Request();
            $request->account_id = $this->chat_id;
            $request->amount = $estimatedPrice;
            $bill = $billCntrl->createNewBill($request);
            // create link
            $text = "موجودی شما کم تر از قیمت بسته انتخابی می باشد. لطفا حساب خود را شارژ بفرمایید. \r\n";
            $text .= "موجودی حساب شما: $userAccouintBallance تومان  \r\n";
            $text .= "موجودی مورد نیاز: $productPrice تومان  \r\n";
            $text .= "میزان مبلغ مورد نیاز برای شارژ حساب: $estimatedPrice تومان  \r\n";

            $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
            $pymCntrl = new PaymentTypeController();
            $hasZarinPal = $pymCntrl->getZarinpalStatus();
            if ($hasZarinPal == true) {
                // send link

                $openLink = $pymCntrl->getZarinpalLink();
                $text = "پرداخت مبلغ $estimatedPrice تومان از طریق درگاه آنلاین \r\n";

                array_push($opr, [
                    [
                        'text' => 'پرداخت آنلاین',
                        'url' => "$openLink/$this->chat_id/$bill->bill_id/$bill->amount",
                    ],
                ]);
                $result = app('telegram_bot')->inlineKeyboardButton($text, $opr, $this->chat_id, '');
            }

            // send offline item
            $offlinePayment = $pymCntrl->getAllActiveOfflinePaymentTypes();
            if ($offlinePayment != null) {
                $pymMenCntrl = new PaymentMenuItemController();
                if ($hasZarinPal == true) {
                    $text = 'همچنین می توانید با انتخاب یکی از گزینه های زیر نسبت به پرداخت اقدام نمایید.';
                } else {
                    $text = $pymMenCntrl->getPaymentTypeMainMenuTitle();
                }

                $opr = [];
                foreach ($offlinePayment as $key => $value) {
                    \Log::info("offlinePayment:$value->name");
                    array_push($opr, [['text' => "$value->name", 'callback_data' => "subAccountBalance-$value->id "]]);
                }

                $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
            }
            $this->addNewBotLog('subscription', 'موجودی کیف پول کاربر برای حرید بسته کافی نبود.', 'low account ballance');

            return response()->json($result, 200);
        }
    }
    public function addAccountBalance()
    {
        $this->addNewBotLog('ballance', 'گزینه های شارژ حساب به کاربر نمایش داده شد.', 'show');

        $this->deleteMessage();

        $text = 'نوع پرداخت را انتخاب کنید.';
        $pymCntrl = new PaymentTypeController();
        $opr = [];

        $hasZarinPal = $pymCntrl->getZarinpalStatus();
        if ($hasZarinPal == true) {
            array_push($opr, [['text' => 'پرداخت آنلاین', 'callback_data' => 'subAccountBalance-zarinpal']]);
        }
        $offlinePayment = $pymCntrl->getAllActiveOfflinePaymentTypes();
        $index = 0;

        foreach ($offlinePayment as $key => $value) {
            \Log::info("offlinePayment:$value->name");
            array_push($opr, [['text' => "$value->name", 'callback_data' => "subAccountBalance-$value->name "]]);
        }

        $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
        // $result = app('telegram_bot')->editMessageReplyMarkup( $this->chat_id,$this->message_id,$opr,);
        $this->setNewLevel($this->buySubscriptionLevel);
        return response()->json($result, 200);
    }
    public function subAccountBalance()
    {
        $this->deleteMessage();
        $pymCntrl = new PaymentTypeController();

        if ($this->userCommandArr[1] == 'zarinpal') {
            // check if $this->userCommandArr[] lenght

            if (count($this->userCommandArr) >= 3) {
                $amount = $this->userCommandArr[2];

                $request = new Request();

                $request->account_id = $this->chat_id;
                $request->amount = $amount;
                $billCntrl = new BillController();

                $bill = $billCntrl->createNewBill($request);

                $openLink = $pymCntrl->getZarinpalLink();
                $text = "پرداخت مبلغ $amount تومان از طریق درگاه آنلاین \r\n";
                $opr = [];
                array_push($opr, [
                    [
                        'text' => 'پرداخت آنلاین',
                        'url' => "$openLink/$this->chat_id/$bill->bill_id/$bill->amount",
                    ],
                ]);
                $this->addNewBotLog('ballance', "صورتحساب به مبلغ $amount برای پرداهت از طریق درگاه زرین پال برای کاربر ارسال شد.", 'show');

                $result = app('telegram_bot')->inlineKeyboardButton($text, $opr, $this->chat_id, '');
            } else {
                $text = 'میزان افزایش اعتبار را انتخاب کنید.';
                $opr = [];
                array_push($opr, [['text' => '10 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-10000 '], ['text' => '15 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-15000 ']]);
                array_push($opr, [['text' => '30 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-30000 '], ['text' => '50 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-50000 ']]);
                array_push($opr, [['text' => '90 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-90000 '], ['text' => '100 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-100000 ']]);
                array_push($opr, [['text' => '150 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-150000 '], ['text' => '180 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-180000 ']]);
                array_push($opr, [['text' => '300 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-300000 '], ['text' => '500 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-500000 ']]);

                $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
                // $this->setNewLevel($this->addZarinPalBalanceLevel);
                $this->addNewBotLog('ballance', 'مبالغ مورد نیاز برای پرداخت از طریق درگاه زرین پال برای کاربر ارسال شد.', 'show');

                return response()->json($result, 200);
            }
        } else {
            $pymCntrl = new PaymentTypeController();
            $pymentMenuCntrl = new PaymentMenuItemController();
            app('telegram_bot')->sendMessage($pymentMenuCntrl->getResponseOfSelectedOfflineMenu(), $this->chat_id, null, 'MarkDown');

            $name = $this->userCommandArr[1];
            $selectedPayment = $pymCntrl->getPaymentTypeNyID($this->userCommandArr[1]);
            $result = app('telegram_bot')->sendMessage($selectedPayment->merchant_id, $this->chat_id, null, 'MarkDown');

            $this->addNewBotLog('ballance', 'مشخصات پرداخت آفلاین انتخابی به کاربر نمایش داده شد.', 'show');

            return response()->json($result, 200);
        }
    }
    public function checkIsChannelsMember($chat_id)
    {
        $this->addNewBotLog('lock', 'بررسی عضو بودن کاربر در کانالهای قفل ربات', 'check');

        $channelLockCtrl = new ChannelLockController();
        $channels = $channelLockCtrl->getAllActiveChannelLock();
        $response = true;
        foreach ($channels as $channel) {
            $res = app('telegram_bot')->checkMember($channel->channel_id, $chat_id);
            if ($res == false) {
                $response == false;
            }
        }
        return $response;
    }
    public function channelLockMenu()
    {
        $this->addNewBotLog('lock', 'درخواست از کاربر برای عضویت در کانالهای قفل ربات.', 'show');

        $channelLockCtrl = new ChannelLockController();
        $channels = $channelLockCtrl->getAllActiveChannelLock();
        $opr = [];

        foreach ($channels as $channel => $value) {
            array_push($opr, [
                [
                    'text' => "$value->channel_id",
                    'url' => "https://t.me/$value->channel_id",
                ],
            ]);
        }

        $channelLockMenuCtrl = new ChannelLockMenuItemController();

        $text = $channelLockMenuCtrl->getChannelLockMenuText();

        $result = app('telegram_bot')->inlineKeyboardButton($text, $opr, $this->chat_id, '');
        return response()->json($result, 200);
    }
    public function buyHistory()
    {
        $prCtrl = new ProductController();
        $histories = $prCtrl->getUserProductsHistoryByAccountID($this->chat_id);
        $opr = [];
        if ($histories != null) {
            foreach ($histories as $key => $history) {
                if ($history['product_category'] != null) {
                    $catName = $history->product_category->category_name;
                    // remove charecter '-' from $catName
                    $catName = str_replace('-', ' ', $catName);

                    array_push($opr, [
                        [
                            'text' => "$catName",
                            'callback_data' => 'subBuyHistory-' . $history->id,
                        ],
                    ]);
                }
            }
            array_push($opr, [
                [
                    'text' => 'بازگشت به منوی اصلی',
                    'callback_data' => '0',
                ],
            ]);
            $text = 'تاریخچه خرید شما:';
            $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
            $this->addNewBotLog('history', 'نمایش گزینه های تاریخچه خرید کاربر.', 'show');

            return response()->json($result, 200);
        }
    }
    public function subBuyHistory()
    {
        // check is userCommandArr[1] an integer or not
        try {
            $this->deleteMessage();
            $selectedHistoryID = $this->userCommandArr[1];
            $text = "ایتم انتخابی: $selectedHistoryID";
            $prCtrl = new ProductController();
            $prCatCntrl = new ProductCategoryController();
            $pnlCntrl = new PannelController();

            $selectedProduct = $prCtrl->getProductConfigById($selectedHistoryID, $this->chat_id);
            $selectedProductCategory = $prCatCntrl->getProdctCategoryNameByID($selectedProduct->product_categories_id);
            $pannel = $pnlCntrl->getPannelById($selectedProductCategory->pannel_id);
            $this->addNewBotLog('history', 'نمایش اطلاعات تاریخچه خرید انتخابی به کاربر', 'show');

            // check pannel type
            if ($pannel->type == 'hiddify') {
                $userSubscriptionLInk = $selectedProduct->subscription_link;
                $image = $pnlCntrl->generateQrMOC($userSubscriptionLInk);
                $text = '';
                $text .= "لینک پنل شما برای مشاهده اطلاعات بسته خریداری شده:$selectedProduct->panel_link \r\n";
                $text .= "لینک سابسکریپشن: $userSubscriptionLInk \r\n";
                $text .= "همچینین شما می توانید QRCode ارسال شده را اسکن نمایید. در صورت نیاز به راهنمایی بر روی آموزش استفاده از لینک سابسکریپشن کلیک کنید.\r\n";
                $resualt = app('telegram_bot')->imageMessageByLink($image, $this->chat_id, $text);
            } else {
                if ($selectedProduct->panel_link != null) {
                    $panel_link = $selectedProduct->panel_link;
                    $text = '';
                    $text .= "لینک پنل شما برای مشاهده اطلاعات بسته خریداری شده: $selectedProduct->panel_link \r\n";
                    $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');
                }

                if ($selectedProduct->subscription_link != null) {
                    $userSubscriptionLInk = $selectedProduct->subscription_link;
                    $image = $pnlCntrl->generateQrMOC($userSubscriptionLInk);
                    $text = '';
                    $text .= "لینک subscription: $selectedProduct->subscription_link \r\n";
                    $resualt = app('telegram_bot')->imageMessageByLink($image, $this->chat_id, $text);
                }

                $links = $selectedProduct->configs;
                // json decode links
                $links = json_decode($links);
                if (is_array($links)) {
                    foreach ($links as $key => $value) {
                        $text = "$value";
                        $image = $pnlCntrl->generateQrMOC($text);

                        $resualt = app('telegram_bot')->imageMessageByLink($image, $this->chat_id, $text);
                    }
                } else {
                    $text = "$selectedProduct->configs \r\n";
                    $image = $pnlCntrl->generateQrMOC($selectedProduct->configs);

                    $resualt = app('telegram_bot')->imageMessageByLink($image, $this->chat_id, $text);
                }
            }
            // sent data by pannel type

            // check is enough volume or  time or not
            // send how to use
            $opr = [];
            array_push($opr, [
                [
                    'text' => 'آموزش استفاده',
                    'callback_data' => 'help-subscription',
                ],
            ]);
            array_push($opr, [
                [
                    'text' => 'برنامه های مورد نیاز',
                    'callback_data' => 'help-applications',
                ],
            ]);

            // set back buttun
            $mainCntrl = new MainMenuItemController();
            $menuItem = $mainCntrl->getMenuIdByName('سابقه خرید');

            array_push($opr, [
                [
                    'text' => 'بازگشت به سابقه خرید',
                    'callback_data' => "main-{$menuItem->id}",
                ],
            ]);

            array_push($opr, [
                [
                    'text' => 'بازگشت به منوی اصلی',
                    'callback_data' => 'main menu',
                ],
            ]);
            $text = 'یک گزینه را انتخاب کنید.';
            $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);

            return response()->json($resualt, 200);
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");
        }
    }
    public function supports()
    {
        $this->addNewBotLog('support', 'نمایش گزینه های پشتیبانی به کاربر.', 'show');

        $supportCtrl = new SupportController();
        $supports = $supportCtrl->getSupporstList();
        $opr = [];
        if ($supports != null) {
            foreach ($supports as $key => $support) {
                if ($support['question'] != null) {
                    $question = $support->question;
                    // remove charecter '-' from $catName
                    $catName = str_replace('-', ' ', $question);

                    array_push($opr, [
                        [
                            'text' => "$question",
                            'callback_data' => 'subSupport-' . $support->id,
                        ],
                    ]);
                }
            }
            array_push($opr, [
                [
                    'text' => 'بازگشت به منوی اصلی',
                    'callback_data' => '0',
                ],
            ]);
            $text = 'یک گزینه را انتخاب کنید.:';
            $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
            return response()->json($result, 200);
        }
    }
    public function subSupport()
    {
        $this->addNewBotLog('support', 'نمایش جزییات گزینه انتخابی پشتیبانی به کاربر.', 'show');

        $this->deleteMessage();
        $selectedSupportID = $this->userCommandArr[1];
        \Log::info("selectedSupportID:$selectedSupportID");
        $text = '';

        $supportCtrl = new SupportController();
        $supports = $supportCtrl->getSupportById($selectedSupportID);
        $opr = [];
        if ($supports != null) {
            $text = $supports->question . "\r\n";

            $text = $supports->answer;

            $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');

            // set back buttun
            $mainCntrl = new MainMenuItemController();
            $menuItem = $mainCntrl->getMenuIdByName('پشتیبانی');

            array_push($opr, [
                [
                    'text' => "بازگشت به {$menuItem->alias_name}",
                    'callback_data' => "main-{$menuItem->id}",
                ],
            ]);
            array_push($opr, [
                [
                    'text' => 'بازگشت به منوی اصلی',
                    'callback_data' => '0',
                ],
            ]);
            $text = 'یک گزینه را انتخاب کنید.:';
            $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
            return response()->json($result, 200);
        }
    }
    public function faqs()
    {
        $this->addNewBotLog('faq', 'نمایش گزینه های سوالات متدوال به کاربر.', 'show');

        $faqCtrl = new FaqController();
        $faqs = $faqCtrl->getFaqList();
        $opr = [];
        if ($faqs != null) {
            foreach ($faqs as $key => $faq) {
                if ($faq['question'] != null) {
                    $question = $faq->question;
                    // remove charecter '-' from $catName
                    $catName = str_replace('-', ' ', $question);

                    array_push($opr, [
                        [
                            'text' => "$question",
                            'callback_data' => 'subFaq-' . $faq->id,
                        ],
                    ]);
                }
            }
            array_push($opr, [
                [
                    'text' => 'بازگشت به منوی اصلی',
                    'callback_data' => '0',
                ],
            ]);
            $text = 'یک گزینه را انتخاب کنید.:';
            $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
            return response()->json($result, 200);
        }
    }
    public function subFaq()
    {
        $this->addNewBotLog('faq', 'نمایش جزییات گزینه انتخابی سوالات متداول به کاربر.', 'show');

        $this->deleteMessage();
        $selectedFaqID = $this->userCommandArr[1];
        \Log::info("selectedFaqID:$selectedFaqID");
        $text = '';

        $supportCtrl = new FaqController();
        $supports = $supportCtrl->getFacById($selectedFaqID);
        $opr = [];
        if ($supports != null) {
            $text = $supports->question . "\r\n";

            $text = $supports->answer;

            $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');

            // set back buttun
            $mainCntrl = new MainMenuItemController();
            $menuItem = $mainCntrl->getMenuIdByName('آموزش استفاده و سوالات متداول');

            array_push($opr, [
                [
                    'text' => "بازگشت به {$menuItem->alias_name}",
                    'callback_data' => "main-{$menuItem->id}",
                ],
            ]);
            array_push($opr, [
                [
                    'text' => 'بازگشت به منوی اصلی',
                    'callback_data' => '0',
                ],
            ]);
            $text = 'یک گزینه را انتخاب کنید.:';
            $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
            return response()->json($result, 200);
        }
    }
    public function accountDetails()
    {
        $this->addNewBotLog('ballance', 'نمایش اطلاعات حساب کاربر.', 'show');

        $accCntrl = new AccountBallanceController();
        $ballance = $accCntrl->getUserAccuntBalance($this->chat_id);
        $text = "♦️ اطلاعات حساب شما: \n\r";

        $text .= "نام کاربری: $this->username \n\r";
        $text .= "نام: $this->first_name \n\r";
        $text .= "نام خانوادگی: $this->last_name \n\r";
        $text .= "آیدی عددی: $this->chat_id \n\r";
        $text .= 'موجودی کیف پول شما: ';
        // show $ballance with thousands seperator
        $text .= number_format($ballance, 0, '.', ',');
        $text .= " تومان \n\r";
        $text .= ' ➖➖➖ ';
        $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');

        $opr = [];

        array_push($opr, [
            [
                'text' => 'شارژ کیف پول 💰',
                'callback_data' => 'addAccountBalance',
            ],
        ]);
        array_push($opr, [
            [
                'text' => 'بازگشت به منوی اصلی',
                'callback_data' => '0',
            ],
        ]);
        $text = 'یک گزینه را انتخاب کنید.:';
        $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
        return response()->json($result, 200);
    }
    public function addNewBotLog($type, $message, $event)
    {
        $logCtrl = new LogController();
        $logCtrl->addNewLog($type, $message, $this->chat_id, $this->username, $event);
        return true;
    }
    public function sendMessageToAdmin($chat_id, $image_url, $text, $messageType)
    {
        $settingCtrl = new SettingController();

        $admin_id = $settingCtrl->getAdminId();
        if ($messageType == 'image') {
            $result = app('telegram_bot')->imageMessage($image_url, $admin_id, $text);

            return response()->json($result, 200);
        } else {
            $result = app('telegram_bot')->sendMessage($text, $admin_id, '');
        }
    }
}
