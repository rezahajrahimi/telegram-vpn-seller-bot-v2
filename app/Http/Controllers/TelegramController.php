<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Cache;

use Illuminate\Http\Request;

class TelegramController extends Controller
{
    // https://api.telegram.org/bot5882116520:AAHfCMiKsQEMHvZb9K3w_lIs_HF6S8_vQ9w/setwebhook?url=https://powernad.ir/public/api/telegram/webhooks/inbound

    public function inbound(Request $request)
    {
        $srtyCtrl = new ServiceTypeController();
        $prcaCtrl = new ProductCategoryController();
        $prCtrl = new ProductController();
        $accBlCtrl = new AccountBallanceController();
        $pymCtrl = new PaymentTypeController();
        $trnsCtrl = new TransactionController();
        $ordCtrl = new OrderController();
        $buySubscriptionLevel = 1;
        $servicetypeLevel = 2;
        $productCategoryLevel = 3;
        \Log::info($request->all());

        // get telegram chat_id and reply to
        $chat_id = $request->message['from']['id'];
        $reply_to_message = $request->message['message_id'];
        $userText = '';
        $userName = '';
        if (!isset($request->message['photo'])) {
            $userText = $request->message['text'];
        }
        $text = 'یک گزینه را انتخاب کنید.';

        try {
            if ($chat_id == env('DEV_ID')) {
                if (str_contains($userText, 'کاربر:')) {
                    $arr = explode(' - ', $userText);
                    $userID = substr($arr[0], 11);
                    $ballance = $arr[1];
                    \Log::info("userID: {$userID}");
                    \Log::info("ballance: {$ballance}");

                    if ($accBlCtrl->incUserAccuntBalance($userID, $ballance)) {
                        // add transaction to db
                        $trnsCtrl->addUserTranaction($userID, $ballance, 1, 1);

                        $text = "اکانت شما به مقدار $ballance تومان شارژ شد. می توانید خرید سرویس خرید خود را کامل کنید.";
                        $result = app('telegram_bot')->sendMessage($text, $userID, '');
                        $text = 'زمان و حجم اکانت را انتخاب کنید.';

                        $service_type_id = $srtyCtrl->getServiceTypesIDByServiceName($userText);

                        Cache::put("service_type_{$userID}", $service_type_id, now()->addMinute(10));

                        $allprCat = $prcaCtrl->getAllProdctCategort($service_type_id);

                        $opr = [];
                        foreach ($allprCat as $key => $value) {
                            array_push($opr, [['text' => "$value->category_name  - $userText - تومان $value->price"]]);
                        }

                        $result = app('telegram_bot')->buttonMessage($text, $opr, $userID, $reply_to_message);
                        cache()->put("level_{$chat_id}", $servicetypeLevel, now()->addMinute(60));

                        return response()->json($result, 200);
                    } else {
                        $text = 'شارژ انجام نشد.';
                        $result = app('telegram_bot')->sendMessage($text, env('DEV_ID'), '');
                        return response()->json($result, 200);
                    }
                }
            } else {
                if (!cache()->has("chat_id_{$chat_id}")) {
                    $text = "سلام رفیق!  🤖 \r\n";
                    $text .= "با پروکسی های ما میتونی همیشه و همه جا تو هر موقعیتی به اینترنت وصل شی! 😉 \r\n ";
                    $text .= 'لطفا یکی از گزینه ها را انتخاب کنید. 🪄';

                    cache()->put("chat_id_{$chat_id}", true, now()->addMinute(60));
                } elseif ($userText == 'خرید اشتراک') {
                    $text = 'نوع اکانت را انتخاب کنید.';
                    $allSerTYpe = $srtyCtrl->getServiceTypes();
                    $opr = [];
                    foreach ($allSerTYpe as $key => $value) {
                        array_push($opr, [['text' => $value->service_name]]);
                    }
                    array_push($opr, [['text' => 'بازگشت به منوی اصلی'], ['text' => 'پشتیبانی']]);

                    $result = app('telegram_bot')->buttonMessage($text, $opr, $chat_id, $reply_to_message);
                    cache()->put("level_{$chat_id}=$buySubscriptionLevel", true, now()->addMinute(1));

                    return response()->json($result, 200);
                } elseif ($srtyCtrl->isServiceType($userText)) {
                    $text = 'زمان و حجم اکانت را انتخاب کنید.';

                    $service_type_id = $srtyCtrl->getServiceTypesIDByServiceName($userText);
                    \Log::info("service_type_idaaaaaa: {$service_type_id}");

                    Cache::put("service_type_{$chat_id}", $service_type_id, now()->addMinute(10));

                    $allprCat = $prcaCtrl->getAllProdctCategort($service_type_id);

                    $opr = [];
                    foreach ($allprCat as $key => $value) {
                        array_push($opr, [['text' => "$value->category_name  - $userText - تومان $value->price"]]);
                    }
                    array_push($opr, [['text' => 'بازگشت به منوی اصلی'], ['text' => 'پشتیبانی']]);

                    $result = app('telegram_bot')->buttonMessage($text, $opr, $chat_id, $reply_to_message);
                    cache()->put("level_{$chat_id}", $servicetypeLevel, now()->addMinute(30));

                    return response()->json($result, 200);
                } elseif ($this->isSelectedProduct($userText) || cache()->has("level_{$chat_id}=$productCategoryLevel")) {
                    $text = $userText;
                    $selectedProduct = $this->fetchSelectedProduct($userText);

                    $selectedType = Cache::get("service_type_{$chat_id}");

                    $productPrice = $prcaCtrl->getProdctPrice($selectedProduct, $selectedType);
                    if ($productPrice != -1) {
                        if ($accBlCtrl->checkUserHasBalance($chat_id, $productPrice)) {
                            $selectedProductCatID = $prcaCtrl->getProdctCategoryID($selectedProduct, $selectedType);

                            // config ra deactice kon

                            $config = $prCtrl->getProductConfigAndChangeStatus($selectedProductCatID, $chat_id);
                            // check kardan mojod bodan config
                            if ($config != null) {
                                // az hesab mojodi ra kam kon
                                $accBlCtrl->decUserAccuntBalance($chat_id, $productPrice);

                                // to trakonesh ha zakhire kon
                                $ordCtrl->addUserOrder($chat_id, $productPrice, $selectedProductCatID, $config->id);
                                // config ra behesh bede
                                $text = "کانفیگ: \r\n";
                                $result = app('telegram_bot')->sendMessage($text, $chat_id, $reply_to_message);

                                $text = $config->configs;
                                $result = app('telegram_bot')->sendMessage($text, $chat_id, $reply_to_message);

                                $text = 'subscription link:';

                                $text .= $config->subscription_link;
                                $result = app('telegram_bot')->sendMessage($text, $chat_id, $reply_to_message);

                                $text = "برای مشاهده دیگر کانفیگها و همچین میزان مصرف و زمان  باقی مانده به لینک زیر بروید: \r\n ";

                                $text .= $config->panel_link;

                                $result = app('telegram_bot')->sendMessage($text, $chat_id, $reply_to_message);

                                // menu ra bede
                                $opr = [[['text' => 'بازگشت به منوی اصلی'], ['text' => 'آموزش وارد کردن کانفیگها']]];

                                $result = app('telegram_bot')->buttonMessage(' خرید شما با موفقیت انجام گرفت.', $opr, $chat_id, $reply_to_message);

                                return response()->json($result, 200);
                            } else {
                                $result = app('telegram_bot')->sendMessage('این محصول در حال حاضر موجود نمی باشد، لطفا محصولی دیگر انتخاب کنید.', $chat_id, $reply_to_message);
                                return response()->json($result, 200);
                            }
                        } else {
                            $userAccouintBallance = $accBlCtrl->getUserAccuntBalance($chat_id);
                            $text = "موجودی شما کم تر از قیمت بسته انتخابی می باشد. لطفا حساب خود را شارژ بفرمایید. \r\n";

                            $text .= "موجودی حساب شما: $userAccouintBallance";
                            $opr = [[['text' => 'افزایش اعتبار']]];
                            array_push($opr, [['text' => 'بازگشت به منوی اصلی'], ['text' => 'پشتیبانی']]);

                            $result = app('telegram_bot')->buttonMessage($text, $opr, $chat_id, $reply_to_message);

                            return response()->json($result, 200);
                        }
                    } else {
                        $service_type_id = Cache::get("service_type_{$chat_id}");

                        $text = "$service_type_id";
                        $result = app('telegram_bot')->sendMessage($text, $chat_id, $reply_to_message);
                        return response()->json($result, 200);
                    }
                    // send config
                    //else send payment info
                    return response()->json($result, 200);
                } elseif ($userText == 'افزایش اعتبار') {
                    $text = 'روش پرداخت را انتخاب کنید.';
                    $allPeymentType = $pymCtrl->getPaymentTypes();
                    $opr = [];
                    foreach ($allPeymentType as $key => $value) {
                        array_push($opr, [['text' => $value->name]]);
                    }
                    array_push($opr, [['text' => 'بازگشت به منوی اصلی'], ['text' => 'پشتیبانی']]);

                    $result = app('telegram_bot')->buttonMessage($text, $opr, $chat_id, $reply_to_message);

                    return response()->json($result, 200);
                } elseif ($pymCtrl->isPaymentType($userText)) {
                    $opr = [];

                    array_push($opr, [['text' => 'بازگشت به منوی اصلی'], ['text' => 'پشتیبانی']]);

                    $result = app('telegram_bot')->buttonMessage($text, $opr, $chat_id, $reply_to_message);

                    $paymentAddress = $pymCtrl->getPaymentAddressByPaymentName($userText);
                    $text = "لطفا مبلغ مورد نظر را به شماره زیر واریز نمایید، سپس تصویر رسید را ارسال نمایید. \r\n";

                    $text .= "$paymentAddress";
                    $result = app('telegram_bot')->sendMessage($text, $chat_id, $reply_to_message);

                    return response()->json($result, 200);
                } elseif ($userText == 'آموزش وارد کردن کانفیگها' || $userText == 'پشتیبانی' || $userText == 'دریافت آموزش') {
                    $text = "جهت آموزش نحوه وارد کردن و استفاده از کانفیگها به کانال زیر بروید: \r\n";
                    $text .= "@v2ray_vip_fast \r\n";
                    $text .= "جهت ارتباط با پشتیبانی به اکانت زیر پیام بدهید: \r\n";
                    $text .= '@V2rayVip_poshtiban';

                    $result = app('telegram_bot')->sendMessage($text, $chat_id, $reply_to_message);
                    return response()->json($result, 200);
                } elseif (isset($request->message['photo'])) {
                    $text = 'رسید شما دریافت شد، منتظر تایید توسط مدیر باشید.';
                    $file_id = app('telegram_bot')->getImageId($request->message['photo']);
                    $result = app('telegram_bot')->sendMessage($text, $chat_id, $reply_to_message);

                    //send image to admin
                    $this->sendMessageToAdmin($chat_id, $file_id, "کاربر:$chat_id یک تصوبر ارسال کرد ", 'image');

                    return response()->json($result, 200);
                } elseif ($userText == 'سابقه خرید') {
                    $orderSummary = $ordCtrl->getUserOrder($chat_id);

                    $text = "سابقه خرید شما: \r\n";
                    if ($orderSummary != null) {
                        $opr = [];

                        foreach ($orderSummary as $key => $value) {
                            $mainName = $value->product_category->category_name;
                            array_push($opr, [['text' => "$mainName - شماره سفارش $value->order_number"]]);
                        }
                        array_push($opr, [['text' => 'بازگشت به منوی اصلی'], ['text' => 'پشتیبانی']]);

                        $result = app('telegram_bot')->buttonMessage($text, $opr, $chat_id, $reply_to_message);
                    } else {
                        $text .= 'سابقه خرید شما خالی است.';
                        $result = app('telegram_bot')->sendMessage($text, $chat_id, $reply_to_message);
                    }

                    return response()->json($result, 200);
                } elseif ($this->isOrderSummary($userText)) {
                    $orderNumber = $this->fetchOrderNumber($userText);
                    $text = "مشخصات خرید شماره $orderNumber: \r\n";
                    $productNumber = $ordCtrl->getPoductNumberByOrderNumber($orderNumber, $chat_id);

                    $config = $prCtrl->getProductConfigById($productNumber, $chat_id);

                    $text = "کانفیگ: \r\n";
                    $result = app('telegram_bot')->sendMessage($text, $chat_id, $reply_to_message);

                    $text = $config->configs;
                    $result = app('telegram_bot')->sendMessage($text, $chat_id, $reply_to_message);

                    $text = 'subscription link:';

                    $text .= $config->subscription_link;
                    $result = app('telegram_bot')->sendMessage($text, $chat_id, $reply_to_message);

                    $text = "برای مشاهده دیگر کانفیگها و همچین میزان مصرف و زمان  باقی مانده به لینک زیر بروید: \r\n ";

                    $text .= $config->panel_link;

                    $result = app('telegram_bot')->sendMessage($text, $chat_id, $reply_to_message);

                    // menu ra bede
                    $opr = [[['text' => 'بازگشت به منوی اصلی'], ['text' => 'پشتیبانی']]];

                    $result = app('telegram_bot')->buttonMessage('برای اطلاعات بیشتر با پشتیبانی تماس بگیرید', $opr, $chat_id, $reply_to_message);

                    return response()->json($result, 200);
                } elseif ($userText == 'اطلاعات حساب') {
                    $ballance = $accBlCtrl->getUserAccuntBalance($chat_id);
                    $text = "موجودی حساب شما: \r\n";

                    $result = app('telegram_bot')->sendMessage($text, $chat_id, $reply_to_message);

                    // menu ra bede
                    $opr = [[['text' => 'افزایش اعتبار']], [['text' => 'بازگشت به منوی اصلی'], ['text' => 'پشتیبانی']]];

                    $result = app('telegram_bot')->buttonMessage("$ballance تومان ", $opr, $chat_id, $reply_to_message);

                    return response()->json($result, 200);
                }

                $opr = [[['text' => 'خرید اشتراک'], ['text' => 'سابقه خرید']], [['text' => 'اطلاعات حساب'], ['text' => 'دریافت آموزش'], ['text' => 'پشتیبانی']]];

                $result = app('telegram_bot')->buttonMessage($text, $opr, $chat_id, $reply_to_message);

                return response()->json($result, 200);
            }
        } catch (\Throwable $th) {
            $opr = [[['text' => 'خرید اشتراک'], ['text' => 'سابقه خرید']], [['text' => 'اطلاعات حساب'], ['text' => 'دریافت آموزش'], ['text' => 'پشتیبانی']]];

            $result = app('telegram_bot')->buttonMessage($text, $opr, $chat_id, $reply_to_message);

            return response()->json($result, 200);
        }
    }
    public function isSelectedProduct($str)
    {
        if (str_contains($str, 'تومان')) {
            return true;
        } else {
            return false;
        }
    }
    public function fetchSelectedProduct($str)
    {
        try {
            $arr = explode(' - ', $str);
            return $arr[0];
        } catch (\Throwable $th) {
            return false;
        }
    }
    public function isOrderSummary($str)
    {
        if (str_contains($str, 'شماره سفارش')) {
            return true;
        } else {
            return false;
        }
    }
    public function fetchOrderNumber($str)
    {
        try {
            $arr = explode(' - ', $str);
            $orderID = str_replace('شماره سفارش ', '', $arr[1]);

            return $orderID;
        } catch (\Throwable $th) {
            return false;
        }
    }
    public function sendMessageToAdmin($chat_id, $image_url, $text, $messageType)
    {
        $admin_id = env('DEV_ID');
        if ($messageType == 'image') {
            $result = app('telegram_bot')->imageMessage($image_url, $admin_id, $text);

            return response()->json($result, 200);
        } else {
            $result = app('telegram_bot')->sendMessage($text, $admin_id, '');
        }
    }
}
