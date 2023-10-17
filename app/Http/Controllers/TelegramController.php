<?php
// https://api.telegram.org/bot6650381860:AAFCJka-B2NsIY5RlATIOQvlXiOpKdDqUlM/setwebhook?url=https://3d35-77-105-147-128.ngrok-free.app/api/telegram/webhooks/inbound

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
                    $this->first_name = $request->callback_query['from']['first_name'];
                    $this->markup = json_decode(json_encode($request->callback_query['message']['reply_markup']['inline_keyboard']), true);
                    $this->recogniseMessage();
                }
            }

            // if (!cache()->has("chat_id_{$this->from_id}") && $this->currentMenuLevel == 0) {
            //     $this->text = $settingCtrl->getWelcomeMessage();
            //     cache()->put("chat_id_{$this->from_id}", true, now()->addMinute(10));
            //     app('telegram_bot')->sendMessage($this->text, $this->chat_id, null, 'MarkDown');
            // } else {
                $channelLock = $this->checkIsChannelsMember($this->from_id);
                if( $channelLock) {
                    $this->defaultMenu();
                } else {
                    $this->channelLockMenu();
                }
            // }
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");
        }
    }
    public function defaultMenu()
    {
        // array_push($opr, [['text' => 'بازگشت', 'callback_data' => 'بازگشت'], ['text' => 'پشتیبانی', 'callback_data' => 'پشتیبانی']]);
        $text = 'یک گزینه را انتخاب کنید.';

        $opr = [[['text' => 'خرید اشتراک', 'callback_data' => 'buySubscription'], ['text' => 'سابقه خرید', 'callback_data' => 'subscriptionHistory']], [['text' => 'اطلاعات حساب', 'callback_data' => 'accountDetails'], ['text' => 'دریافت آموزش', 'callback_data' => 'learning'], ['text' => 'پشتیبانی', 'callback_data' => 'support']]];

        $result = app('telegram_bot')->buttonMessage($text, $opr, $this->chat_id, '');
        // $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
        $this->setNewLevel($this->buySubscriptionLevel);
        return response()->json($result, 200);
    }
    public function recogniseMessage()
    {
        $this->userCommandArr = explode('-', $this->callback_data);
        $command = $this->userCommandArr[0];
        \Log::info("command recognise: $command");

        return $this->userCommandArr;
    }
    public function changeMenuLevel()
    {
        if ($this->currentMenuLevel != 0) {
            $this->currentMenuLevel -= 1;
        }
        $this->userText = '';
        $menuCntrl = new MenuLevelController();

        $menuCntrl->newUserLevel($this->chat_id, $this->currentMenuLevel);
        switch ($this->currentMenuLevel) {
            case 0:
                $this->defaultMenu();

                break;
            case $this->buySubscriptionLevel:
                $this->buySubscription();

                break;

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
    public function checkIsChannelsMember($chat_id)
    {
        $channelLockCtrl = new ChannelLockController();
        $channels = $channelLockCtrl->getAllActiveChannelLock();
        foreach ($channels as $channel) {
            $res = app('telegram_bot')->checkMember($channel->channel_id, $chat_id);
            if ($res == false) {
                return false;
            }
        }
        return true;
    }
    public function channelLockMenu()
    {
        $channelLockCtrl = new ChannelLockController();
        $channels = $channelLockCtrl->getAllActiveChannelLock();
        $opr = [];

        foreach ($channels as $channel => $value) {

             array_push($opr, [[
             'text' => "$value->channel_id","url"=>"https://t.me/$value->channel_id"]]);
        }

        $channelLockMenuCtrl = new ChannelLockMenuItemController();

        // array_push($opr, [['text' => 'بازگشت', 'callback_data' => 'بازگشت'], ['text' => 'پشتیبانی', 'callback_data' => 'پشتیبانی']]);
        $text = $channelLockMenuCtrl->getChannelLockMenuText();

        $result = app('telegram_bot')->inlineKeyboardButton($text, $opr, $this->chat_id, '');
        // $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
        return response()->json($result, 200);
    }
}
