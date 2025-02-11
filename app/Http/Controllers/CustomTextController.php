<?php
namespace App\Http\Controllers;

use App\Models\CustomText;

class CustomTextController extends Controller
{
    private $customText;

    public function __construct()
    {
        $this->customText = new CustomText();
    }
    private function seed()
    {
        \Log::info('Seeding CustomText table');
        // check if we are on local
        if (env('APP_ENV') == 'local') {
            // delete all the data
            CustomText::truncate();
        }
        // check if the table is empty
        if (CustomText::count() == 0) {
            $data = [
                ['key' => 'action.welcome.message', 'default_text' => json_encode([
                    ['type' => 'bold', 'text' => "سلام {name} {lastName}! به ربات ما خوش آمدید. 👋"],
                    ['type' => 'newline'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "برای شروع می‌توانید از دستورات زیر استفاده کنید:"],
                    ['type' => 'newline'],
                    ['type' => 'code', 'text' => "/help"],
                    ['type' => 'text', 'text' => " - راهنمای دستورات"],
                    ['type' => 'newline'],
                    ['type' => 'code', 'text' => "/menu"],
                    ['type' => 'text', 'text' => " - منوی اصلی"],
                    ['type' => 'newline'],
                    ['type' => 'newline'],
                    ['type' => 'italic', 'text' => "برای اطلاعات بیشتر به "],
                    ['type' => 'link', 'text' => "وب‌سایت ما", 'url' => "{website}"],
                    ['type' => 'text', 'text' => " مراجعه کنید."],
                ]), 'custom_text' => null],

                ['key' => 'action.start', 'default_text' => 'سلام {name}! به ربات آموزشی خوش آمدید', 'custom_text' => null],
                ['key' => 'action.help', 'default_text' => 'راهنما', 'custom_text' => null],
                ['key' => 'action.back', 'default_text' => 'بازگشت', 'custom_text' => null],
                ['key' => 'action.send_location', 'default_text' => 'ارسال موقعیت مکانی', 'custom_text' => null],
                ['key' => 'action.send_contact', 'default_text' => 'ارسال شماره تماس', 'custom_text' => null],
                ['key' => 'action.upload_file', 'default_text' => 'آپلود فایل', 'custom_text' => null],
                ['key' => 'action.send_photo', 'default_text' => 'ارسال عکس', 'custom_text' => null],
                ['key' => 'action.welcome_back', 'default_text' => 'خوش برگشتی {name}! آخرین بازدید شما: {last_visit}', 'custom_text' => null],
                ['key' => 'action.process.on_progress', 'default_text' => 'در حال پردازش...', 'custom_text' => null],
                ['key' => 'action.process.success', 'default_text' => 'عملیات با موفقیت انجام شد', 'custom_text' => null],
                ['key' => 'action.process.failed', 'default_text' => 'عملیات با شکست مواجه شد', 'custom_text' => null],
                ['key' => 'action.process.insufficient_balance', 'default_text' => json_encode([
                    ['type' => 'bold', 'text' => "موجودی شما کم تر از قیمت بسته انتخابی می باشد. لطفا حساب خود را شارژ بفرمایید. "],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "موجودی شما:"],
                    ['type' => 'text', 'text' => "{user_balance_in_toman}"],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "قیمت بسته:"],
                    ['type' => 'text', 'text' => "{product_price_in_toman}"],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "میزان مبلغ مورد نیاز برای شارژ حساب:"],
                    ['type' => 'text', 'text' => "{difference_in_toman}"],

                ]), 'custom_text' => null],
                ['key' => 'action.process.insufficient_balance_with_dollar', 'default_text' => json_encode([
                    ['type' => 'bold', 'text' => "موجودی شما کم تر از قیمت بسته انتخابی می باشد. لطفا حساب خود را شارژ بفرمایید. "],
                    ['type' => 'newline'],
                    ['type' => 'bold', 'text' => "موجودی شما:"],
                    ['type' => 'text', 'text' => "{user_balance_in_toman} - {user_balance_in_dollar}"],

                    ['type' => 'newline'],
                    ['type' => 'bold', 'text' => "قیمت بسته: {product_price_in_toman}"],
                       ['type' => 'newline'],
                    ['type' => 'text', 'text' => "میزان مبلغ مورد نیاز برای شارژ حساب:"],
                    ['type' => 'text', 'text' => "{diffrence_in_toman} - {diffrence_in_dollar}"],

                ]), 'custom_text' => null],
                ['key' => 'action.process.add_online_balance', 'default_text' => 'افزایش موجودی کیف پول خود را با انتخاب یکی از گزینه های زیر انجام دهید.', 'custom_text' => null],
                ['key' => 'action.process.add_online_balance.zarinpal', 'default_text' => 'پرداخت آنلاین با زرین پال', 'custom_text' => null],
                ['key' => 'action.process.add_online_balance.dollarpay.zarinpal', 'default_text' => 'پرداخت آنلاین با زرین پال', 'custom_text' => null],
                ['key' => 'action.process.add_online_balance.dollarpay.nowpayment', 'default_text' => 'پرداخت آنلاین با رمزارز', 'custom_text' => null],
                ['key' => 'action.process.add_offline_balance_option_and_online_balance', 'default_text' => 'همچنین می توانید با انتخاب یکی از گزینه های زیر نسبت به پرداخت اقدام نمایید.', 'custom_text' => null],
                ['key' => 'action.process.add_offline_balance_option', 'default_text' => 'پرداخت آفلاین', 'custom_text' => null],
                ['key' => 'action.process.add_offline_balance_option.image', 'default_text' => 'لطفا مبلغ را واریز کنید و رسید پرداختی را ارسال کنید.', 'custom_text' => null],


                ['key' => 'action.process.success_buy', 'default_text' => 'اشتراک با موفقیت خریداری شد', 'custom_text' => null],
                ['key' => 'action.process.failed_buy', 'default_text' => 'خرید اشتراک با شکست مواجه شد', 'custom_text' => null],
                ['key' => 'action.buy_subscription', 'default_text' => 'خرید اشتراک', 'custom_text' => null],
                ['key' => 'action.buy_subscription_by_location', 'default_text' => 'خرید اشتراک بر اساس مکان', 'custom_text' => null],
                ['key' => 'action.buy_subscription_by_location.location', 'default_text' => 'مکان سرور را انتخاب کنید.', 'custom_text' => null],
                ['key' => 'action.buy_subscription.select_package', 'default_text' => 'بسته خود را انتخاب کنید.', 'custom_text' => null],
                ['key' => 'action.help.add_ballance', 'default_text' => 'لطفا کیف پول خود را با انتخاب یکی از گزینه های زیر شارژ کنید.', 'custom_text' => null],
                ['key' => 'action.help.using_subscription', 'default_text' => 'به کمک نیاز داری؟ یک گزینه را انتخاب بکن', 'custom_text' => null],
                ['key' => 'action.subscription.hiddify', 'default_text' => json_encode([
                    ['type' => 'bold', 'text' => "خرید شما با موفقیت انجام شد"],
                    ['type' => 'newline'],
                    ['type' => 'bold', 'text' => "لینک پنل شما برای مشاهده اطلاعات بسته خریداری شده:"],
                    ['type' => 'link', 'text' => "لینک پنل", 'url' => "{panel_link}"],
                    ['type' => 'newline'],
                    ['type' => 'bold', 'text' => "لینک سابسکریپشن:"],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "{userSubscriptionLInk}"],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "همچینین شما می توانید QRCode ارسال شده را اسکن نمایید. در صورت نیاز به راهنمایی بر روی آموزش استفاده از لینک سابسکریپشن کلیک کنید."],
                ]), 'custom_text' => null],
                ['key' => 'error.server_error', 'default_text' => 'خطایی رخ داده است', 'custom_text' => null],
                ['key' => 'error.menu.not_found', 'default_text' => 'گزینه ای یافت نشد', 'custom_text' => null],
                ['key' => 'error.action.not_found', 'default_text' => 'عملیات نامعتبر است', 'custom_text' => null],
                ['key' => 'error.command.not_found', 'default_text' => 'دستور نامعتبر است. برای مشاهده لیست دستورات از /help استفاده کنید.', 'custom_text' => null],

            ];
            CustomText::insert($data);

            \Log::info('CustomText table seeded successfully');
            return true;
        }

    }
    public function getText($key, $variables = [])
    {
        try {
            $text = $this->customText->getText($key, $variables);
            if (json_validate($text)) {
                return json_decode($text, true);
            }
            return $text;
        } catch (\Throwable $th) {
            \Log::error('Error getting text: ' . $key . ' ' . $th->getMessage());
            $this->seed();
            return $this->customText->getDefaultText($key);
        }
    }

    public function setText($key, $text)
    {

        // check if the key is in the database
        if ($this->customText->where('key', $key)->exists()) {
            return $this->customText->setText($key, $text);
        }
        return false;
    }
}
