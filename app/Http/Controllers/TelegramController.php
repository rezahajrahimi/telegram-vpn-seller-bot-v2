<?php
// https://api.telegram.org/bot7449013530:AAEbAaPDU9AUkyKviA2ffhhuVIswN7iMqNQ/setwebhook?url=https://433a-46-226-165-205.ngrok-free.app/api/telegram/webhooks/inbound
// https://api.telegram.org/bot6650381860:AAFCJka-B2NsIY5RlATIOQvlXiOpKdDqUlM/setwebhook?url=https://laravel-rq3qi6.chbk.run/api/telegram/webhooks/inbound

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Cache;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\AgentProduct;
use App\Models\ProductCategory;
use App\Models\Product;
use App\Models\Pannel;
use App\Models\User;
use App\Models\AgentPermisson;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Hekmatinasser\Verta\Verta;

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
        // \Log::info($request->all());
        try {
            try {
                if (isset($request->message['photo'])) {
                    $this->message_id = $request->message['message_id'];
                    $this->chat_id = $request->message['chat']['id'];
                    $this->username = $request->message['from']['username'] ?? ' ندارد ';
                    $this->from_id = $request->message['from']['id'];
                    $this->first_name = $request->message['from']['first_name'] ?? '';
                    $this->last_name = $request->message['from']['last_name'] ?? '';
                    $this->text = $request->message['caption'] ?? '';

                    $text = 'رسید شما دریافت شد، منتظر تایید توسط مدیر باشید.';
                    $this->fileId = app('telegram_bot')->getImageId($request->message['photo']);
                    $this->chat_type = 'image';
                    $result = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');

                    $chId = $this->chat_id;
                    $this->sendMessageToAdmin($this->chat_id, $this->fileId, "کاربر: $chId یک تصوبر ارسال کرد ", 'image');
                    $transactionCntrl = new TransactionController();
                    $imageTrCntrl = new TransactionImageController();
                    $transactionID = $transactionCntrl->addUserTranaction($this->chat_id, 0, '000', 0);
                    $request = new Request();
                    $request->transaction_id = $transactionID;
                    $request->img_src = $this->fileId;
                    $request->account_id = $this->chat_id;
                    $request->user_text = $this->text ?? 'بدون متن';
                    $request->image_url = app('telegram_bot')->getImageUrlByFileID($this->fileId);

                    // $imageTrCntrl->addNewTransactionImage($request);
                    $imageTrCntrl->saveNewTransactionImage($request);
                    return response()->json($result, 200);
                } elseif (isset($request->message)) {
                    $this->from_id = $request->message['from']['id'];
                    $this->text = $request->message['text'];
                    $this->first_name = $request->message['from']['first_name'];
                    $this->caption = $request->message['caption'] ?? '';
                    $this->chat_id = $request->message['chat']['id'] ?? 0;
                    $this->last_name = $request->message['from']['last_name'] ?? '';
                    $this->username = $request->message['from']['username'] ?? ' ندارد ';
                    $this->message_id = $request->message['message_id'];
                    $this->forward_from_name = $request->message['reply_to_message']['forward_sender_name'] ?? 0;
                    $this->forward_from_id = $request->message['reply_to_message']['forward_from']['id'] ?? 0;
                    $this->reply_text = $request->message['reply_to_message']['text'] ?? '0';
                    $this->chat_type = 'text';
                    \Log::info('recogniseTextMessage');

                    return $this->recogniseTextMessage();
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
                    $this->chat_type = 'callback';
                    \Log::info('recogniseMessage');
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

            if ($botUserCtrl->hasRegistred($this->from_id, $this->username, $this->first_name, $this->last_name) == false) {
                $this->text = $settingCtrl->getWelcomeMessage();
                cache()->put("chat_id_{$this->from_id}", true, now()->addMinute(10));
                app('telegram_bot')->sendMessage($this->text, $this->chat_id, null, 'MarkDown');
                \Log::info('aaaaaaaaaaaaa');

                $this->stickyMenu();
            } else {
                $channelLock = $this->checkIsChannelsMember($this->from_id);
                if ($channelLock == true || $channelLock == 1) {
                    $this->changeMenuLevel();
                } else {
                    return $this->channelLockMenu();
                }
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");
        }
    }
    public function stickyMenu()
    {
        $menu = new MainMenuItemController();
        $menuItem = $menu->getAllActivatedMainMenuItems();
        $opr = [];
        // check if there is bought subscription or not
        // if ($menuItem[0]->name == 'webapp') {
        //     array_push($opr, [['text' => $menuItem[10]->alias_name, 'callback_data' => "main-{$menuItem[10]->id}"]]);
        //     // remove first item from menuItem list because we allreade added it to $opr
        //     $menuItem = $menuItem->slice(1);
        // }
        if ($menuItem[0]->name == 'خرید اشتراک') {
            array_push($opr, [['text' => $menuItem[0]->alias_name, 'callback_data' => "main-{$menuItem[0]->id}"]]);
            // remove first item from menuItem list because we allreade added it to $opr
            $menuItem = $menuItem->slice(1);
        }
        $countOfMenuItem = count($menuItem);
        for ($i = 0; $i < $countOfMenuItem; $i += 2) {
            $pair = $menuItem->slice($i, 2);
            $index = 1;

            foreach ($pair as $key => $value) {
                if ($index % 2 == 1) {
                    $firstRowIndicator = ['text' => $value->alias_name, 'callback_data' => "main-{$value->id}"];
                    $index += 1;
                } elseif ($index % 2 == 0) {
                    array_push($opr, [$firstRowIndicator, ['text' => $value->alias_name, 'callback_data' => "main-{$value->id}"]]);
                    $index += 1;
                    break;
                }
            }
        }
        // because of if count of menuItem is odd we need to add last row indicator
        if ($countOfMenuItem % 2 == 1) {
            $lastRowIndicator = ['text' => $menuItem[$countOfMenuItem - 1]->alias_name, 'callback_data' => "main-{$menuItem[$countOfMenuItem - 1]->id}"];
            array_push($opr, [$firstRowIndicator]);
        }
        $result = app('telegram_bot')->buttonMessage('از منوی پایین یک گزینه را انتخاب کنید.', $opr, $this->chat_id, $this->message_id);
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
    public function recogniseTextMessage()
    {
        $botUserCtrl = new BotUserController();
        $settingCtrl = new SettingController();
        if ($botUserCtrl->hasRegistred($this->from_id, $this->username, $this->first_name, $this->last_name) == false) {
            $this->text = $settingCtrl->getWelcomeMessage();
            cache()->put("chat_id_{$this->from_id}", true, now()->addMinute(10));
            app('telegram_bot')->sendMessage($this->text, $this->chat_id, null, 'MarkDown');
            $this->stickyMenu();
        } else {
            $channelLock = $this->checkIsChannelsMember($this->from_id);
            if ($channelLock == true || $channelLock == 1) {
                $this->changeMenuLevel();
            } else {
                return $this->channelLockMenu();
            }
        }
        try {
            if ($this->chat_type == 'image') {
                $result = app('telegram_bot')->imageMessage($image_url, $admin_id, $text);

                return response()->json($result, 200);
            }
            // check is $this->text start with giftcard-
            // if yes return $this->subGiftCard()
            if (str_starts_with($this->text, 'giftcard-')) {
                \Log::info('giftcard');

                return $this->subGiftCard();
            }
            // check is $this->text start with webapp
            // if yes return $this->webapp()




            $mainMenuCntrl = new MainMenuItemController();
            $checkIsMainMeniItem = $mainMenuCntrl->getMenuNameByAliasName($this->text);
            if ($checkIsMainMeniItem == false) {
                return $this->stickyMenu();
            }
            switch ($checkIsMainMeniItem) {
                case 'منوی اصلی':
                    return $this->subMainMenu();
                    break;
                case 'خرید اشتراک':
                    return $this->buySubscription();
                    break;
                case 'اطلاعات حساب':
                    return $this->accountDetails();
                    break;
                case 'سابقه خرید':
                    return $this->buyHistory();
                    break;
                case 'پشتیبانی':
                    return $this->supports();
                    break;
                case 'آموزش استفاده و سوالات متداول':
                    return $this->faqs();
                    break;
                case 'دانلود برنامه':
                    return $this->appDownload();
                    break;
                case 'گیف کارت':
                    return $this->giftCard();
                    break;
                case 'اکانت آزمایشی':
                    return $this->testAccount();
                    break;
                case 'webapp':
                    return $this->subWebapp();
                    break;

                default:
                    return $this->stickyMenu();
                    break;
            }

            return;
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
            case 'subAppDownload':
                $this->subAppDownload();
                break;
            case 'getAppDownload':
                $this->getAppDownload();
                break;
            case 'giftcard':
                $this->subGiftCard();
                break;
            case 'recharge':
                $this->subRecharge();
                break;

            default:
                $this->stickyMenu();
                break;
        }

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
                $this->stickyMenu();
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

        $prCat = $prCatCntrl->getAllActiveProdctCategoryOrderByPrice();
        $opr = [];
        $index = 0;
        if ($this->checkDollarPay() == true || $this->checkDollarPay() == 1) {
            array_push($opr, [['text' => 'قیمت(دلار)', 'callback_data' => '0'], ['text' => 'قیمت(تومان)', 'callback_data' => '0'], ['text' => 'بسته', 'callback_data' => '0']]);
            foreach ($prCat as $key => $value) {
                array_push($opr, [['text' => "$value->price_in_dollar", 'callback_data' => "buySubscription-$value->id"], ['text' => "$value->price", 'callback_data' => "buySubscription-$value->id"], ['text' => "$value->category_name", 'callback_data' => "buySubscription-$value->id"]]);
            }
        } else {
            array_push($opr, [['text' => 'قیمت(تومان)', 'callback_data' => '0'], ['text' => 'بسته', 'callback_data' => '0']]);
            foreach ($prCat as $key => $value) {
                array_push($opr, [['text' => "$value->price", 'callback_data' => "buySubscription-$value->id"], ['text' => "$value->category_name", 'callback_data' => "buySubscription-$value->id"]]);
            }
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
        $productPriceInDollar = $selectedPrCat->price_in_dollar;
        \Log::info("selectedPrCat->price: $productPrice");
        $opr = [];
        $accBlCtrl = new AccountBallanceController();
        $prCntrl = new ProductController();

        if ($accBlCtrl->checkUserHasBalance($this->chat_id, $productPrice, $productPriceInDollar)) {
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
                $req = new Request();
                $req->accountId = "$this->chat_id-$productID";
                $req->pannelID = $selectedPrCat->pannel_id;
                $req->vol = $volume;
                $req->day = $day;
                $hiddifcCntrl = new HiddifyPannelController();

                // $newUUID = $hiddifcCntrl->addUserToHiddifyPanel($req); api v2
                $newUUID = $hiddifcCntrl->addUserToHiddifyPanelOldApi($req); // api v1

                $userPannelLink = $hiddifcCntrl->getClearHiddifyRequestUrl($pannel->user_link, "/{$newUUID}/#{$req->accountId}");

                // $userPannelLink = $pnlCntrl->getHiddifyPannelLinkByPannelID($selectedPrCat->pannel_id);
                $userSubscriptionLInk = $hiddifcCntrl->getClearHiddifyRequestUrl($pannel->user_link, "/{$newUUID}/all.txt?name=sublink-unknown&asn=unknown&mode=new");

                // $userSubscriptionLInk = "$userPannelLink/$newUUID/all.txt?name=sublink-unknown&asn=unknown&mode=new";

                $image = $pnlCntrl->generateQrMOC($userSubscriptionLInk);
                $text = '';
                $text .= "خرید شما با موفقیت انجام شد\r\n";
                if ($selectedPrCat->show_pannel_link == 1) {
                    $text .= "لینک پنل شما برای مشاهده اطلاعات بسته خریداری شده:{$userPannelLink} \r\n";
                }
                $text .= "لینک سابسکریپشن: $userSubscriptionLInk \r\n";
                $text .= "همچینین شما می توانید QRCode ارسال شده را اسکن نمایید. در صورت نیاز به راهنمایی بر روی آموزش استفاده از لینک سابسکریپشن کلیک کنید.\r\n";

                $resualt = app('telegram_bot')->imageMessageByLink($image, $this->chat_id, $text);
                // save as dectivate product, So we can use it in future when user want to recharge it;
                $request = new Request();
                $request->account_id = $this->chat_id;
                $request->subscription_link = "/{$newUUID}/all.txt?name=sublink-unknown&asn=unknown&mode=new";
                $request->product_categories_id = $selectedPrCat->id;
                $request->panel_link = "/{$newUUID}/#{$req->accountId}";
                $request->configs = '';
                $request->remark = "$this->chat_id-$productID";

                $prCntrl->addAutomatedProductDetails($request);
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

                $prCntrl->addAutomatedProductDetails($request);
            } else {
                $userData = $prCntrl->getProductConfigAndChangeStatus($selectedPrCat->id, $this->chat_id);
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
            $accBlCtrl->decUserAccuntBalance($this->chat_id, $productPrice, $productPriceInDollar);
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
                    // $text = $pymMenCntrl->getPaymentTypeMainMenuTitle();
                    $mainMenu = $pymMenCntrl->getPaymentTypeMainMenuTitle();
                    $text = $mainMenu->alias_name;
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
        // add nowpayment if was active
        if ($this->checkDollarPay() == true || $this->checkDollarPay() == 1) {
            $cryptoCntrl = new CryptoPaymentController();
            $nowpayment = $cryptoCntrl->getNowPaymentsStatus();
            if ($nowpayment == true) {
                array_push($opr, [['text' => 'پرداخت آنلاین با ارز دیجیتال', 'callback_data' => 'subAccountBalance-nowpayment']]);
            }
        }

        // add offline payment
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

            if (count($this->userCommandArr) >= 3 && is_numeric($this->userCommandArr[2])) {
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
        } elseif ($this->userCommandArr[1] == 'nowpayment') {
            if (count($this->userCommandArr) >= 3 && is_numeric($this->userCommandArr[2])) {
                $amount = $this->userCommandArr[2];

                $request = new Request();

                $request->account_id = $this->chat_id;
                $request->amount = $amount;
                $billCntrl = new BillController();

                $bill = $billCntrl->createNewBillInDollar($request);

                $openLink = $pymCntrl->getNowPaymentsLink();
                $text = "پرداخت مبلغ $amount دلار از طریق درگاه آنلاین \r\n";
                $opr = [];
                array_push($opr, [
                    [
                        'text' => 'پرداخت آنلاین',
                        'url' => "$openLink/$this->chat_id/$bill->bill_id/$amount",
                    ],
                ]);
                $this->addNewBotLog('ballance', "صورتحساب به مبلغ $amount برای پرداهت از طریق درگاه زرین پال برای کاربر ارسال شد.", 'show');

                $result = app('telegram_bot')->inlineKeyboardButton($text, $opr, $this->chat_id, '');
            } else {
                $text = 'میزان افزایش اعتبار را انتخاب کنید.';
                $opr = [];
                array_push($opr, [['text' => '5$', 'callback_data' => 'subAccountBalance-nowpayment-5 '], ['text' => '7$', 'callback_data' => 'subAccountBalance-nowpayment-7 ']]);
                array_push($opr, [['text' => '10$', 'callback_data' => 'subAccountBalance-nowpayment-10 '], ['text' => '12$', 'callback_data' => 'subAccountBalance-nowpayment-12 ']]);
                array_push($opr, [['text' => '15$', 'callback_data' => 'subAccountBalance-nowpayment-15 '], ['text' => '20$', 'callback_data' => 'subAccountBalance-nowpayment-20 ']]);
                array_push($opr, [['text' => '50$', 'callback_data' => 'subAccountBalance-nowpayment-50 '], ['text' => '150$', 'callback_data' => 'subAccountBalance-nowpayment-150 ']]);
                array_push($opr, [['text' => '200$', 'callback_data' => 'subAccountBalance-nowpayment-200 '], ['text' => '300$', 'callback_data' => 'subAccountBalance-nowpayment-300 ']]);

                $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
                // $this->setNewLevel($this->addZarinPalBalanceLevel);
                $this->addNewBotLog('ballance', 'مبالغ مورد نیاز برای پرداخت از طریق درگاه nowpayments برای کاربر ارسال شد.', 'show');

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
            $channel_name = $channel->channel_id;
            // check $chanel start with @ char
            if (!preg_match('/^@/', $channel_name)) {
                $channel_name = "@$channel_name";
            }

            $res = app('telegram_bot')->checkMember($channel_name, $chat_id);
            if ($res == false || $res == null) {
                return $response = false;
            } else {
                return $response = true;
            }
            \Log::info("checkIsChannelsMember: $response");
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
                    $catName .= ' | ' . $history->remark;
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
                $hiddifcCntrl = new HiddifyPannelController();
                $userPannelLink = $hiddifcCntrl->getClearHiddifyRequestUrl($pannel->user_link, "{$selectedProduct->panel_link}");
                $userSubscriptionLInk = $hiddifcCntrl->getClearHiddifyRequestUrl($pannel->user_link, "{$selectedProduct->subscription_link}");

                $image = $pnlCntrl->generateQrMOC($userSubscriptionLInk);
                $text = '';
                $agentCntrl = new AgentProductController();
                $configStatus = $agentCntrl->getBoughtProductsStatusFromServerById($selectedProduct->id);
                if ($configStatus != null) {
                    // \Log::info('configStatus', ['configStatus' => $configStatus]);
                    $enableText = $configStatus['enable'] == true ? 'فعال' : 'غیر فعال';
                    // $text = "وضعیت بسته: {$enableText} \r\n";
                    $text = "📦 وضعیت بسته: {$enableText} \r\n";
                    $usageGB = $configStatus['current_usage_GB'];
                    // show usageGb only with two decimal
                    $usageGB = round($usageGB, 2);
                    $limitGB = $configStatus['usage_limit_GB'];
                    $text .= "📊 میزان حجم مصرف شده:  {$usageGB}GB از {$limitGB}GB \r\n";
                    //
                    $startDate = $configStatus['start_date'];
                    // convert $startDate to valid carbon date
                    $startDate = Carbon::parse($startDate);


                    // expire date
                    $package_days = $configStatus['package_days'];
                    // convert $package_days to integer
                    $package_days = intval($package_days);
                    // add expireDate to $startDate
                    $expireDate = Carbon::parse($startDate);
                    // add $pacje_days to $expireDate
                    $expireDate->addDays($package_days);

                    $expireDate = $expireDate->toJalali()->format('Y.m.d');
                    $startDate = $startDate->toJalali()->format('Y.m.d');

                    $text .= "🗓️ تاریخ شروع: {$startDate} \r\n";

                    $text .= "⏳ تاریخ انقضا: {$expireDate} \r\n";
                }
                if ($selectedProductCategory->show_pannel_link == 1) {
                    $text .= "🔗 لینک پنل شما برای مشاهده اطلاعات بسته خریداری شده:{$userPannelLink} \r\n";
                }
                if ($selectedProductCategory->show_subscription_link == 1) {
                    $text .= "🔗 لینک سابسکریپشن: $userSubscriptionLInk \r\n";
                }
                $text .= "ℹ️ همچینین شما می توانید QRCode ارسال شده را اسکن نمایید. در صورت نیاز به راهنمایی بر روی آموزش استفاده از لینک سابسکریپشن کلیک کنید.\r\n";
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
            // $selectedHistoryID = $this->userCommandArr[1];

            array_push($opr, [
                [
                    'text' => 'تمدید بسته',
                    'callback_data' => "recharge-{$selectedHistoryID}",
                ],
            ]);

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

            // array_push($opr, [
            //     [
            //         'text' => 'بازگشت به منوی اصلی',
            //         'callback_data' => 'main menu',
            //     ],
            // ]);
            $text = 'یک گزینه را انتخاب کنید.';
            $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);

            return response()->json($resualt, 200);
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");
        }
    }
    public function subRecharge()
    {
        // check is userCommandArr[1] an integer or not
        try {
            // $this->deleteMessage();
            $selectedHistoryID = $this->userCommandArr[1];
            $text = '';
            $data = Product::where('id', $selectedHistoryID)->with('product_category_and_panel')->first();
            $accountID = $this->chat_id;

            $selectedPrCat = ProductCategory::find($data->product_categories_id);
            // check selectedPrCat is اکانت آزمایشی or not
            if ($selectedPrCat->category_name == 'اکانت آزمایشی' || $selectedPrCat->is_active == false) {
                $text .= "این بسته قابلیت شارژ ندارد \r\n";
                $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');
                return $resualt;
            }

            // check account ballance
            $productPrice = $selectedPrCat->price;
            $productPriceInDollar = $selectedPrCat->price_in_dollar;
            $accBlCtrl = new AccountBallanceController();
            if ($accBlCtrl->checkUserHasBalance($this->chat_id, $productPrice, $productPriceInDollar)) {
                $pannel = Pannel::find($data->product_category_and_panel->pannel_id);

                // check pannel type
                $hiddifcCntrl = new HiddifyPannelController();

                $uuid = $hiddifcCntrl->extractUUID($data->subscription_link);
                $day = $selectedPrCat->expire_day;
                $volume = $selectedPrCat->volume;

                $req = new Request();
                $req->pannelID = $pannel->id;
                $req->name = $data->remark;
                $req->uuid = $uuid;
                $req->vol = $volume;
                $req->day = $day;
                // get today date with new variable
                $today = Verta::now();
                $req->comment = "شارژ مجدد در {$today}";

                $updateRemark = $hiddifcCntrl->rechargeUserOfHiddifyPanelOldApi($req);
                // $updateRemark = json_encode($updateRemark);
                if ($updateRemark['status'] == 200) {
                    if ($updateRemark['msg'] !== 'ok') {
                        return response()->json(false, 401);
                    }
                    $accBlCtrl->decUserAccuntBalance($accountID, $productPrice, $productPriceInDollar);
                    $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت شارژ بسته کم شد.", 'minus ballance');
                    $this->addNewBotLog('product', "$data->remark شارژ شد.", 'charge product');

                    $text .= "✅شارژ با موفقیت انجام شد✅ \r\n";
                    $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');

                    return $resualt;
                    // dd($subsequentResponse);
                } else {
                    $text .= "خطایی رخ داد، دوباره امتحان کنید یا با پشتیبانی تماس بگیرید\r\n";
                    $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');

                    return $resualt;
                }

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

                // array_push($opr, [
                //     [
                //         'text' => 'بازگشت به منوی اصلی',
                //         'callback_data' => 'main menu',
                //     ],
                // ]);
                $text = 'یک گزینه را انتخاب کنید.';
                $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);

                return response()->json($resualt, 200);
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
                        // $text = $pymMenCntrl->getPaymentTypeMainMenuTitle();
                        $mainMenu = $pymMenCntrl->getPaymentTypeMainMenuTitle();
                        $text = $mainMenu->alias_name;
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
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");
        }
    }
    public function subWebapp()
    {
        $this->addNewBotLog('webapp', 'ارسال لینک ورود سریع به پنل به کاربر', 'show');
        $authCntrl = new AuthController();
        $req = new Request();
        $req->account_id = $this->chat_id;
        $result = $authCntrl->generate_auto_login_link($req);
        return response()->json($result, 200);
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
            // array_push($opr, [
            //     [
            //         'text' => 'بازگشت به منوی اصلی',
            //         'callback_data' => '0',
            //     ],
            // ]);
            $text = 'یک گزینه را انتخاب کنید:';
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
            // array_push($opr, [
            //     [
            //         'text' => 'بازگشت به منوی اصلی',
            //         'callback_data' => '0',
            //     ],
            // ]);
            $text = 'یک گزینه را انتخاب کنید:';
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
            // array_push($opr, [
            //     [
            //         'text' => 'بازگشت به منوی اصلی',
            //         'callback_data' => '0',
            //     ],
            // ]);
            $text = 'یک گزینه را انتخاب کنید:';
            $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
            return response()->json($result, 200);
        }
    }
    public function subFaq()
    {
        $this->addNewBotLog('faq', 'نمایش جزییات گزینه انتخابی سوالات متداول به کاربر.', 'show');

        $this->deleteMessage();
        $selectedFaqID = $this->userCommandArr[1];
        // \Log::info("selectedFaqID:$selectedFaqID");
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
            // array_push($opr, [
            //     [
            //         'text' => 'بازگشت به منوی اصلی',
            //         'callback_data' => '0',
            //     ],
            // ]);
            $text = 'یک گزینه را انتخاب کنید:';
            $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
            return response()->json($result, 200);
        }
    }
    public function accountDetails()
    {
        $this->addNewBotLog('ballance', 'نمایش اطلاعات حساب کاربر.', 'show');

        $accCntrl = new AccountBallanceController();
        $ballance = $accCntrl->getUserAccuntBalance($this->chat_id);
        $ballanceInDollar = $accCntrl->getUserAccuntBalanceInDollar($this->chat_id);
        $text = "♦️ اطلاعات حساب شما: \n\r";

        $text .= "نام کاربری: $this->username \n\r";
        $text .= "نام: $this->first_name \n\r";
        $text .= "نام خانوادگی: $this->last_name \n\r";
        $text .= "آیدی عددی: $this->chat_id \n\r";
        $text .= 'موجودی کیف پول شما: ';
        // show $ballance with thousands seperator
        $text .= number_format($ballance, 0, '.', ',');
        $text .= " تومان \n\r";
        $text .= 'موجودی دلاری کیف پول شما: ';
        // show $ballance with thousands seperator
        $text .= number_format($ballanceInDollar, 0, '.', ',');
        $text .= "$ \n\r";
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
    public function appDownload()
    {
        $appCtrl = new ApplicationController();
        $oses = $appCtrl->getApplicationOSes();
        $opr = [];
        if ($oses != null) {
            foreach ($oses as $key => $os) {
                $catName = $os->os;
                // remove charecter '-' from $catName
                $catName = str_replace('-', ' ', $catName);

                array_push($opr, [
                    [
                        'text' => "$catName",
                        'callback_data' => 'subAppDownload-' . $os->os,
                    ],
                ]);
            }
            $text = 'سیستم عامل را انتخاب کنید:';
            $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
            $this->addNewBotLog('history', 'نمایش گزینه های دانلود برنامه بر اساس سیستم عامل.', 'show');

            return response()->json($result, 200);
        }
    }
    public function subAppDownload()
    {
        $this->addNewBotLog('app', 'نمایش لیست برنامه های مورد نیاز براساس سیتم عامل انتخابی به کاربر.', 'show');

        $this->deleteMessage();

        $selectedOsID = $this->userCommandArr[1];
        \Log::info("selectedOsID:$selectedOsID");
        $appCtrl = new ApplicationController();
        $apps = $appCtrl->getAllActiveAplicationListByOS($selectedOsID);

        $opr = [];
        if ($apps != null) {
            foreach ($apps as $key => $app) {
                $name = $app->name;
                // remove charecter '-' from $catName
                $name = str_replace('-', ' ', $name);

                array_push($opr, [
                    [
                        'text' => "$name",
                        'callback_data' => 'getAppDownload-' . $app->id,
                    ],
                ]);
            }
            $text = 'یک برنامه را انتخاب کنید:';
            $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
            $this->addNewBotLog('app', 'نمایش گزینه های دانلود برنامه بر اساس سیستم عامل.', 'show');

            return response()->json($result, 200);
        }
    }
    public function getAppDownload()
    {
        $this->addNewBotLog('app', 'نمایش لیست برنامه های مورد نیاز براساس سیتم عامل انتخابی به کاربر.', 'show');

        // $this->deleteMessage();

        $selectedOsID = $this->userCommandArr[1];
        \Log::info("selectedOsID:$selectedOsID");
        $appCtrl = new ApplicationController();
        $app = $appCtrl->getActiveAplicationByID($selectedOsID);
        $text = '';

        if (isset($app)) {
            $text .= "نام برنامه: $app->name \n\r";
            $text .= "$app->description \n\r";
            if (isset($app['download_link'])) {
                $text .= "لینک دانلود: $app->download_link \n\r";
            }
            if (isset($app['file_src'])) {
                $text .= "لینک فایل: $app->file_src \n\r";
            }
            if (isset($app['how_to_use'])) {
                $text .= "چطور استفاده کنی؟: $app->how_to_use \n\r";
            }
            if (isset($app['youtube_link'])) {
                $text .= "لینک یوتیوب: $app->youtube_link \n\r";
            }

            $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');

            return response()->json($resualt, 200);
        }
        $text = 'برنامه مورد نظر یافت نشد.';
        $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');
        return response()->json($resualt, 200);

        return response()->json($result, 200);
    }
    public function giftCard()
    {
        $giftMenuCntrl = new GiftCardMenuItemController();
        $mainText = $giftMenuCntrl->getGiftCardMainMenuTitle();
        $text = '';
        $text .= "{$mainText->alias_name} \n\r";
        $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');
    }
    public function subGiftCard()
    {
        $insertedGift = $this->text;
        \Log::info("imported gift code :$insertedGift");

        $giftMenuCntrl = new GiftCardMenuItemController();

        // check validation of inserted gift code
        $giftCntrl = new GiftCardController();
        $gift = $giftCntrl->getGiftCardByCode($insertedGift);

        if ($gift == null) {
            $text = 'کد وارد شده معتبر نمی باشد.';
            $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');
            return response()->json($resualt, 200);
        }

        // check how many time user used it and eligable to use it again

        $usedGiftCntrl = new UsedGiftCardController();
        $userUsedItemCount = $usedGiftCntrl->getCountOfUsePerUser($gift->id, $this->chat_id);

        if ($userUsedItemCount >= $gift->count_of_use_per_user) {
            $expire_text = $giftMenuCntrl->getGiftCardExpiredMenuTitle();
            $text = "{$expire_text}\n\r";
            $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');
            return response()->json($resualt, 200);
        }

        $reualt = $usedGiftCntrl->addGiftCardToUserAccount($gift->id, $this->chat_id, $insertedGift);

        if ($reualt) {
            $text = '';

            $text .= "{$giftMenuCntrl->getGiftCardAcceptedMenuTitle()} \n\r";
            $text .= "مبلغ $gift->discount تومان به حساب شما افزوده شد. \n\r";
            $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');
            return response()->json($resualt, 200);
        }
        $text = '';
        $expire_text = $giftMenuCntrl->getGiftCardExpiredMenuTitle();
        $text = "{$expire_text}\n\r";

        $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');
        return response()->json($resualt, 200);
    }
    public function testAccount()
    {
        $testAccountCntrl = new TestAccountController();
        $testAccount = $testAccountCntrl->getTestAccountDetails();

        $usedTestAccountCntrl = new UsedTestAccountController();
        $pnlCntrl = new PannelController();
        $pannel = $pnlCntrl->getPannelById($testAccount->pannel_id);

        $text = '';
        $hasAccount = $usedTestAccountCntrl->newTestAccount($this->chat_id, $testAccount->id);
        \Log::info("message: $hasAccount");
        if ($hasAccount == true || $hasAccount == 1) {
            $text .= "اکانت آزمایشی از قبل برای شما فعال شده است، می توانید از سابقه خرید به اطلاعات آن دسترسی داشته باشید.  \n\r";
            $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');

            return response()->json($resualt, 200);
        }

        // get test product id

        $prCat = new ProductCategoryController();
        $selectedPrCat = $prCat->getProdctCategoryByCategoryName('اکانت آزمایشی');

        $text .= "اکانت آزمایشی شما با موفقیت فعال شد. \n\r";

        $text .= "تاریخ انقضای اکانت آزمایشی : $testAccount->expire_day روز \n\r";
        $text .= "میزان امتیاز اکانت آزمایشی : $testAccount->volume \n\r";

        $text .= "شما می توانید از این اکانت آزمایشی استفاده کنید. \n\r";

        $prCntrl = new ProductController();

        $pnlCntrl = new PannelController();
        $pannel = $pnlCntrl->getPannelById($selectedPrCat->pannel_id);
        // get selected item specefic data
        $day = $selectedPrCat->expire_day;
        $volume = $selectedPrCat->volume;

        if ($pannel->type == 'hiddify') {
            $req = new Request();
            $req->accountId = "$this->chat_id-اکانت_آزمایشی";
            $req->pannelID = $selectedPrCat->pannel_id;
            $req->vol = $volume;
            $req->day = $day;
            \Log::info("vol $volume day $day");
            $hiddifcCntrl = new HiddifyPannelController();

            $newUUID = $hiddifcCntrl->addUserToHiddifyPanelOldApi($req);

            $userPannelLink = $hiddifcCntrl->getClearHiddifyRequestUrl($pannel->user_link, "/{$newUUID}/#{$req->accountId}");

            $userSubscriptionLInk = $hiddifcCntrl->getClearHiddifyRequestUrl($pannel->user_link, "/{$newUUID}/all.txt?name=sublink-unknown&asn=unknown&mode=new");

            $image = $pnlCntrl->generateQrMOC($userSubscriptionLInk);
            if ($selectedPrCat->show_pannel_link == 1) {
                $text .= "لینک پنل شما برای مشاهده اطلاعات بسته خریداری شده:{$userPannelLink} \r\n";
            }
            $text .= "لینک سابسکریپشن: $userSubscriptionLInk \r\n";
            $text .= "همچینین شما می توانید QRCode ارسال شده را اسکن نمایید. در صورت نیاز به راهنمایی بر روی آموزش استفاده از لینک سابسکریپشن کلیک کنید.\r\n";

            $resualt = app('telegram_bot')->imageMessageByLink($image, $this->chat_id, $text);
            // save as dectivate product, So we can use it in future when user want to recharge it;
            $request = new Request();
            $request->account_id = $this->chat_id;
            $request->subscription_link = "/{$newUUID}/all.txt?name=sublink-unknown&asn=unknown&mode=new";
            $request->product_categories_id = $selectedPrCat->id;
            $request->panel_link = "/{$newUUID}/#{$req->accountId}";
            $request->configs = '';
            $request->remark = "$this->chat_id-اکانت_آزمایشی";

            $prCntrl->addAutomatedProductDetails($request);
        } elseif ($pannel->type == 'marzban') {
            $userData = $pnlCntrl->createMarzbanUser("BotUser$this->chat_id اکانت_آزمایشی", $day, $volume, $selectedPrCat->pannel_id);
            $userSub = $userData['subscription_link'];
            $links = $userData['links'];

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
            $request->remark = "BotUser$this->chat_id اکانت_آزمایشی";

            $prCntrl->addAutomatedProductDetails($request);
        }

        $this->addNewBotLog('account', 'اکانت تست فعال شد', 'test-account');

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
        $text = 'یک گزینه را انتخاب کنید.';
        $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);

        return response()->json($result, 200);

        $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');
        return response()->json($resualt, 200);
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
    /// check  dollarPay is valid or not
    public function checkDollarPay()
    {
        $trSettingCntrl = new TransactionSettingController();

        return $trSettingCntrl->getDollorTransactionSetting();
    }
}
