<?php
// https://api.telegram.org/bot6650381860:AAFCJka-B2NsIY5RlATIOQvlXiOpKdDqUlM/setwebhook?url=https://7d6e-77-105-147-128.ngrok-free.app/api/telegram/webhooks/inbound

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Cache;

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
                if (isset($request->message)) {
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
                } elseif (isset($request->callback_query)) {
                    $this->callbackId = $request->callback_query['id'];
                    $this->data = $request->callback_query['data'];
                    $this->text = $request->callback_query['message']['text'];
                    $this->message_id = $request->callback_query['message']['message_id'];
                    $this->chat_id = $request->callback_query['message']['chat']['id'];
                    $this->chat_type = $request->callback_query['message']['chat']['type'];
                    $this->username = $request->callback_query['from']['username'] ?? ' ندارد ';
                    $this->from_id = $request->callback_query['from']['id'];
                    $this->first_name = $request->callback_query['from']['first_name'];
                    $this->last_name = $request->callback_query['from']['last_name'];

                    $this->markup = json_decode(json_encode($request->callback_query['message']['reply_markup']['inline_keyboard']), true);
                    $this->recogniseMessage();
                }
            } catch (\Throwable $th) {
                if (isset($request->callback_query)) {
                    $this->callbackId = $request->callback_query['id'];
                    $this->data = $request->callback_query['data'];
                    $this->text = $request->callback_query['message']['text'];
                    $this->message_id = $request->callback_query['message']['message_id'];
                    $this->chat_id = $request->callback_query['message']['chat']['id'];
                    $this->chat_type = $request->callback_query['message']['chat']['type'];
                    $this->username = $request->callback_query['from']['username'] ?? ' ندارد ';
                    $this->from_id = $request->callback_query['from']['id'];
                    $this->first_name = $request->callback_query['from']['first_name'];
                    $this->last_name = $request->callback_query['from']['last_name'];

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
            $this->userCommandArr = explode('-', $this->data);
            $command = $this->userCommandArr[0];
            \Log::info("command recognise: $command");

            return $this->userCommandArr;
        } catch (\Throwable $th) {
            $this->userCommandArr = ['start'];
            \Log::info("command recognise: $this->userCommandArr");

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
        $menu = new MainMenuItemController();

        $selectedSubMenu = $menu->getMenuNameByID($this->userCommandArr[1]);
        \Log::info("selectedSubMenu:  $selectedSubMenu->name");

        switch ($selectedSubMenu->name) {
            case 'خرید اشتراک':
                $this->buySubscription();
                break;
            // case "سابقه خرید":
            // $this->buySubscription();
            //     break;
            // case "پشتیبانی":
            // $this->buySubscription();
            //     break;
            // case "آموزش استفاده و سوالات متداول":
            // $this->buySubscription();
            //     break;
            // case "اطلاعات حساب":
            // $this->buySubscription();
            //     break;
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
        // check user account balance
        $productPrice = $selectedPrCat->price;
        \Log::info("selectedPrCat->price: $productPrice");
        $opr = [];
        $accBlCtrl = new AccountBallanceController();
        if ($accBlCtrl->checkUserHasBalance($this->chat_id, $productPrice)) {
            $userAccouintBallance = $accBlCtrl->getUserAccuntBalance($this->chat_id);
            $text = "پول داره \r\n";

            $text .= "موجودی حساب شما: $userAccouintBallance";
            $opr = [[['text' => 'افزایش اعتبار', 'callback_data' => 'addAccountBalance']]];
            array_push($opr, [['text' => 'بازگشت به منوی اصلی', 'callback_data' => '0'], ['text' => 'پشتیبانی', 'callback_data' => 'support']]);

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

            return response()->json($result, 200);
        }
    }
    public function addAccountBalance()
    {
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
            $text = 'میزان افزایش اعتبار را انتخاب کنید.';
            $opr = [];
            array_push($opr, [['text' => '10 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-10000 ']]);
            array_push($opr, [['text' => '15 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-15000 ']]);
            array_push($opr, [['text' => '30 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-30000 ']]);
            array_push($opr, [['text' => '50 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-50000 ']]);
            array_push($opr, [['text' => '90 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-90000 ']]);
            array_push($opr, [['text' => '100 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-100000 ']]);
            array_push($opr, [['text' => '150 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-150000 ']]);
            array_push($opr, [['text' => '180 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-180000 ']]);
            array_push($opr, [['text' => '300 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-300000 ']]);
            array_push($opr, [['text' => '500 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-500000 ']]);

            $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
            // $this->setNewLevel($this->addZarinPalBalanceLevel);
            return response()->json($result, 200);
        } else {
            $pymCntrl = new PaymentTypeController();
            $pymentMenuCntrl = new PaymentMenuItemController();
            // $text = $pymentMenuCntrl->getResponseOfSelectedOfflineMenu();
            app('telegram_bot')->sendMessage($pymentMenuCntrl->getResponseOfSelectedOfflineMenu(), $this->chat_id, null, 'MarkDown');

            $name = $this->userCommandArr[1];
            $selectedPayment = $pymCntrl->getPaymentTypeNyID($this->userCommandArr[1]);
            // array_push($opr, [['text' => "$selectedPayment->merchant_id", 'callback_data' => "$selectedPayment->merchant_id"]]);
            // $text = $selectedPayment->merchant_id;
            $result= app('telegram_bot')->sendMessage($selectedPayment->merchant_id, $this->chat_id, null, 'MarkDown');

            // $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);


            // $this->setNewLevel($this->buySubscriptionLevel);
            return response()->json($result, 200);
        }
    }
    public function checkIsChannelsMember($chat_id)
    {
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
}
