<?php
namespace App\Http\Controllers;

use App\Models\CustomText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomTextController extends Controller
{
    private $customText;

    public function __construct()
    {
        $this->customText = new CustomText();
    }
    public function getAllTexts()
    {
        try {
            $data = CustomText::all();
            return response()->json($data);
        } catch (\Throwable $th) {
            $this->seed();
            $data = CustomText::all();
            if (count($data) == 0) {
                return response()->json(['error' => 'خطایی رخ داده است'], 500);
            }
            return response()->json($data);
        }
    }
    public function seed()
    {
        try {
            \Log::info('Seeding CustomText table');
            // check if we are on local
            if (env('APP_ENV') == 'local') {
                // delete all the data
                CustomText::truncate();
            }
            // check if the table is empty
            if (CustomText::count() == 0) {
                $data = [
                    ['key'         => 'action.welcome.message',
                        'default_text' => json_encode([
                            ['type' => 'bold', 'text' => "سلام {name} {lastName}! به ربات ما خوش آمدید. 👋"],
                            ['type' => 'newline'],
                            ['type' => 'newline'],
                            ['type' => 'text', 'text' => "برای شروع می‌توانید از گزینه های زیر استفاده کنید:"],
                            ['type' => 'newline'],
                            ['type' => 'italic', 'text' => "برای اطلاعات بیشتر به "],
                            ['type' => 'link', 'text' => "وب‌سایت ما", 'url' => "{website}"],
                            ['type' => 'text', 'text' => " مراجعه کنید."],
                        ]), 'custom_text' => null, 'description' => 'متن خوش آمدگویی برای کاربر - پارامترها: {name} {lastName} {website}'],

                    ['key' => 'action.start', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'سلام {name}! به ربات آموزشی خوش آمدید'],
                    ]), 'custom_text' => null, 'description' => 'متن خوش آمدگویی برای کاربر - پارامترها: {name} {website}'],
                    ['key' => 'action.chanel_lock_text', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'برای شروع، لطفا در کانالهای زیر عضو بشوید.'],
                    ]), 'custom_text' => null, 'description' => 'متن قفل ربات'],

                    ['key' => 'action.help', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'راهنما'],
                    ]), 'custom_text' => null, 'description' => 'متن راهنمای دستورات'],
                    ['key' => 'action.back', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'بازگشت'],
                    ]), 'custom_text' => null, 'description' => 'متن بازگشت به منوی قبلی'],
                    ['key' => 'action.process.reply.cancel', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'لغو'],
                    ]), 'custom_text' => null, 'description' => 'متن لغو در پرداخت'],
                    ['key' => 'action.send_location', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'ارسال موقعیت مکانی'],
                    ]), 'custom_text' => null, 'description' => 'متن ارسال موقعیت مکانی'],
                    ['key' => 'action.send_contact', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'ارسال شماره تماس'],
                    ]), 'custom_text' => null, 'description' => 'متن ارسال شماره تماس'],
                    ['key' => 'action.upload_file', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'آپلود فایل'],
                    ]), 'custom_text' => null, 'description' => 'متن آپلود فایل'],
                    ['key' => 'action.send_photo', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'ارسال عکس'],
                    ]), 'custom_text' => null, 'description' => 'متن ارسال عکس'],
                    ['key' => 'action.send_photo.success', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => '{name} عزیز عکس شما دریافت شد، منتظر بررسی توسط مدیر ربات باشید.'],
                    ]), 'custom_text' => null, 'description' => 'متن {name} عزیز عکس شما دریافت شد، منتظر بررسی توسط مدیر ربات باشید. پارامترها: {name}'],
                    ['key' => 'action.send_photo.success.admin', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'کاربر {account_id} برای شما عکسی ارسال کرده است.'],
                    ]), 'custom_text' => null, 'description' => 'متن کاربر {account_id} برای شما عکسی ارسال کرده است. پارامترها: {account_id}'],
                    ['key' => 'action.welcome_back', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'خوش برگشتی {name}! آخرین بازدید شما: {last_visit}'],
                    ]), 'custom_text' => null, 'description' => 'متن خوش برگشتی برای کاربر - پارامترها: {name} {last_visit}'],
                    ['key' => 'action.process.on_progress', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'در حال پردازش...'],
                    ]), 'custom_text' => null, 'description' => 'متن در حال پردازش'],
                    ['key' => 'action.process.success', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'عملیات با موفقیت انجام شد'],
                    ]), 'custom_text' => null, 'description' => 'متن عملیات موفق'],
                    ['key' => 'action.process.failed', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'عملیات با شکست مواجه شد'],
                    ]), 'custom_text' => null, 'description' => 'متن عملیات شکست خورده'],
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

                    ]), 'custom_text' => null, 'description' => 'متن عملیات شکست خورده - پارامترها: {user_balance_in_toman} {product_price_in_toman} {difference_in_toman}'],
                    ['key' => 'action.process.insufficient_balance_with_dollar', 'default_text' => json_encode([
                        ['type' => 'bold', 'text' => "موجودی شما کم تر از قیمت بسته انتخابی می باشد. لطفا حساب خود را شارژ بفرمایید. "],
                        ['type' => 'newline'],
                        ['type' => 'bold', 'text' => "موجودی شما:"],
                        ['type' => 'text', 'text' => "{user_balance_in_toman} - {user_balance_in_dollar}"],
                        ['type' => 'newline'],
                        ['type' => 'bold', 'text' => "قیمت بسته: {product_price_in_toman}"],
                        ['type' => 'newline'],
                        ['type' => 'text', 'text' => "میزان مبلغ مورد نیاز برای شارژ حساب:"],
                        ['type' => 'text', 'text' => "{difference_in_toman} - {difference_in_dollar}"],

                    ]), 'custom_text' => null, 'description' => 'متن عملیات شکست خورده - پارامترها: {user_balance_in_toman} {user_balance_in_dollar} {product_price_in_toman} {difference_in_toman} {difference_in_dollar}'],
                    ['key' => 'action.process.add_online_balance', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'افزایش موجودی کیف پول خود را با انتخاب یکی از گزینه های زیر انجام دهید.'],
                    ]), 'custom_text' => null, 'description' => 'متن افزایش موجودی کیف پول - پارامترها: {website}'],
                    ['key' => 'action.process.add_online_balance.zarinpal', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'پرداخت آنلاین با زرین پال'],
                    ]), 'custom_text' => null, 'description' => 'متن پرداخت آنلاین با زرین پال'],
                    ['key' => 'action.process.add_online_balance.zarinpal.reply', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'مقدار واریزی خود را وارد کنید. (حداقل 10 هزار تومان)'],
                    ]), 'custom_text' => null, 'description' => 'متن مقدار واریزی خود را وارد کنید. (حداقل 10 هزار تومان)'],
                    ['key' => 'action.process.add_online_balance.zarinpal.reply.invalid_amount', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'لطفا مبلغ را به صورت عددی وارد کنید'],
                    ]), 'custom_text' => null, 'description' => 'متن لطفا مبلغ را به صورت عددی وارد کنید'],
                    ['key' => 'action.process.add_online_balance.zarinpal.reply.invoice', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'صورت حساب جدید برای پرداخت ایجاد شد، بر روی لینک زیر کلیک کنید. (مهلت اعتبار لینک تنها 10 دقیقه می باشد.)'],
                    ]), 'custom_text' => null, 'description' => 'متن صورت حساب جدید برای پرداخت ایجاد شد، بر روی لینک زیر کلیک کنید. (مهلت اعتبار لینک تنها 10 دقیقه می باشد.)'],

                    ['key' => 'action.process.add_online_balance.nowpayments.reply', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'مقدار واریزی خود را وارد کنید. (حداقل 5 دلار)'],
                    ]), 'custom_text' => null, 'description' => 'متن مقدار واریزی خود را وارد کنید. (حداقل 5 دلار)'],

                    ['key' => 'action.process.add_online_balance.nowpayments.reply.invoice', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'صورت حساب جدید برای پرداخت ایجاد شد، بر روی لینک زیر کلیک کنید. (مهلت اعتبار لینک تنها 10 دقیقه می باشد.)'],
                    ]), 'custom_text' => null, 'description' => 'متن صورت حساب جدید برای پرداخت ایجاد شد، بر روی لینک زیر کلیک کنید. (مهلت اعتبار لینک تنها 10 دقیقه می باشد.)'],

                    ['key' => 'action.process.add_online_balance.dollarpay.zarinpal', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'پرداخت آنلاین با زرین پال'],
                    ]), 'custom_text' => null, 'description' => 'متن پرداخت آنلاین با زرین پال'],

                    ['key' => 'action.process.add_online_balance.dollarpay.nowpayment', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'پرداخت آنلاین با رمزارز'],
                    ]), 'custom_text' => null, 'description' => 'متن پرداخت آنلاین با رمزارز'],

                    ['key' => 'action.process.add_offline_balance_option_and_online_balance', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'همچنین می توانید با انتخاب یکی از گزینه های زیر نسبت به پرداخت اقدام نمایید.'],
                    ]), 'custom_text' => null, 'description' => 'متن همچنین می توانید با انتخاب یکی از گزینه های زیر نسبت به پرداخت اقدام نمایید.'],

                    ['key' => 'action.process.add_offline_balance_option', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'پرداخت آفلاین'],
                    ]), 'custom_text' => null, 'description' => 'متن پرداخت آفلاین'],
                    ['key' => 'action.process.add_offline_balance_option.image', 'default_text' => json_encode([
                        ['type' => 'bold', 'text' => "لطفا مبلغ را به این شماره کارت واریز کنید و رسید پرداختی را ارسال کنید. "],
                        ['type' => 'newline'],
                        ['type' => 'bold', 'text' => "شماره کارت:"],
                        ['type' => 'text', 'text' => "{merchant_id}"],
                    ]), 'custom_text' => null, 'description' => 'متن درخواست واریز به شماره کارت - پارامترها: {merchant_id}'],

                    ['key' => 'action.account.balance_added', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'کیف پول شما به مبلغ {amount} شارژ شد.'],
                    ]), 'custom_text' => null, 'description' => 'متن کیف پول شما به مبلغ {amount} شارژ شد.'],
                    ['key' => 'action.process.success_buy', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'اشتراک با موفقیت خریداری شد'],
                    ]), 'custom_text' => null, 'description' => 'متن اشتراک با موفقیت خریداری شد'],

                    ['key' => 'action.process.failed_buy', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'خرید اشتراک با شکست مواجه شد'],
                    ]), 'custom_text' => null, 'description' => 'متن خرید اشتراک با شکست مواجه شد'],

                    ['key' => 'action.buy_subscription', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'خرید اشتراک'],
                    ]), 'custom_text' => null, 'description' => 'متن خرید اشتراک'],

                    ['key' => 'action.buy_subscription_by_location', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'خرید اشتراک بر اساس مکان'],
                    ]), 'custom_text' => null, 'description' => 'متن خرید اشتراک بر اساس مکان'],

                    ['key' => 'action.buy_subscription_by_location.location', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'مکان سرور را انتخاب کنید.'],
                    ]), 'custom_text' => null, 'description' => 'متن مکان سرور را انتخاب کنید.'],

                    ['key' => 'action.buy_subscription.select_package', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'بسته خود را انتخاب کنید.'],
                    ]), 'custom_text' => null, 'description' => 'متن بسته خود را انتخاب کنید.'],

                    ['key' => 'action.help.add_ballance', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'لطفا کیف پول خود را با انتخاب یکی از گزینه های زیر شارژ کنید.'],
                    ]), 'custom_text' => null, 'description' => 'متن لطفا کیف پول خود را با انتخاب یکی از گزینه های زیر شارژ کنید.'],
                    ['key' => 'action.help.message', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'لطفا یکی از گزینه های زیر را انتخاب کنید.'],
                    ]), 'custom_text' => null, 'description' => 'متن لطفا یکی از گزینه های زیر را انتخاب کنید.'],
                    ['key' => 'action.help.using_subscription', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'به کمک نیاز داری؟ یک گزینه را انتخاب بکن'],
                    ]), 'custom_text' => null, 'description' => 'متن به کمک نیاز داری؟ یک گزینه را انتخاب بکن'],
                    ['key' => 'action.help.appDownload', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'دانلود برنامه'],
                    ]), 'custom_text' => null, 'description' => 'متن دانلود برنامه.'],

                    ['key' => 'action.help.appDownload.os', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'سیستم عامل خود را انتخاب کنید.'],
                    ]), 'custom_text' => null, 'description' => 'متن سیستم عامل خود را انتخاب کنید.'],
                    ['key' => 'action.help.appDownload.app', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'برنامه خود را انتخاب کنید.'],
                    ]), 'custom_text' => null, 'description' => 'متن برنامه خود را انتخاب کنید.'],

                    ['key' => 'action.help.appDownload.app.name_description', 'default_text' => json_encode([
                        ['type' => 'bold', 'text' => "نام برنامه: {name}"],
                        ['type' => 'newline'],
                        ['type' => 'bold', 'text' => "توضیحات برنامه: {description}"],
                        ['type' => 'newline'],
                        ['type' => 'bold', 'text' => "لینک دانلود:"],
                        ['type' => 'newline'],
                        ['type' => 'text', 'text' => "{download_link}"],
                        ['type' => 'newline'],
                        ['type' => 'bold', 'text' => "لینک آموزش استفاده:"],
                        ['type' => 'newline'],
                        ['type' => 'text', 'text' => "{how_to_use}"],
                        ['type' => 'newline'],
                        ['type' => 'bold', 'text' => "لینک آموزش یوتیوب:"],
                        ['type' => 'newline'],
                        ['type' => 'text', 'text' => "{youtube_link}"],
                    ]), 'custom_text' => null, 'description' => 'متن نام برنامه: {name} توضیحات برنامه: {description} لینک دانلود: {download_link} لینک آموزش استفاده: {how_to_use} لینک آموزش یوتیوب: {youtube_link}'],
                    ['key' => 'action.help.support', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'پشتیبانی'],
                    ]), 'custom_text' => null, 'description' => 'متن پشتیبانی'],
                    ['key' => 'action.help.support.title', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'یکی از گزینه های پشتیبانی زیر را انتخاب کنید.'],
                    ]), 'custom_text' => null, 'description' => 'متن یکی از گزینه های پشتیبانی زیر را انتخاب کنید.'],

                    ['key' => 'action.subscription.hiddify', 'default_text' => json_encode([
                        ['type' => 'bold', 'text' => "خرید شما با موفقیت انجام شد"],
                        ['type' => 'newline'],
                        ['type' => 'bold', 'text' => "لینک پنل شما برای مشاهده اطلاعات بسته خریداری شده:"],
                        ['type' => 'link', 'text' => "لینک پنل", 'url' => "{panel_link}"],
                        ['type' => 'newline'],
                        ['type' => 'bold', 'text' => "لینک سابسکریپشن:"],
                        ['type' => 'newline'],
                        ['type' => 'code', 'text' => "{subscription_link}"],
                        ['type' => 'newline'],
                        ['type' => 'text', 'text' => "همچینین شما می توانید QRCode ارسال شده را اسکن نمایید. در صورت نیاز به راهنمایی بر روی آموزش استفاده از لینک سابسکریپشن کلیک کنید."],
                    ]), 'custom_text' => null, 'description' => 'متن خرید شما با موفقیت انجام شد - پارامترها: {panel_link} {subscription_link}'],
                    ['key' => 'action.buy_history.title', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'سابقه خرید'],
                    ]), 'custom_text' => null, 'description' => 'متن سابقه خرید'],
                    ['key' => 'action.buy_history.no_history', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'شما هیچ سابقه خریدی ندارید'],
                    ]), 'custom_text' => null, 'description' => 'متن شما هیچ سابقه خریدی ندارید'],
                    ['key' => 'action.buy_history.history', 'default_text' => json_encode([
                        ['type' => 'bold', 'text' => 'سابقه خرید شما:'],
                        ['type' => 'newline'],
                        ['type' => 'text', 'text' => 'نام: {name}'],
                        ['type' => 'newline'],
                        ['type' => 'text', 'text' => 'بسته: {category_name}'],
                        ['type' => 'newline'],
                        ['type' => 'text', 'text' => 'تاریخ شروع: {start_date}'],
                        ['type' => 'newline'],
                        ['type' => 'text', 'text' => 'تاریخ انقضا: {expire_date}'],
                        ['type' => 'newline'],
                        ['type' => 'text', 'text' => 'میزان حجم بسته: {usage_limit_GB} GB'],
                        ['type' => 'newline'],
                        ['type' => 'text', 'text' => 'میزان حجم مصرف شده: {usage_GB} GB'],
                        ['type' => 'newline'],
                        ['type' => 'text', 'text' => 'وضعیت بسته: {enable}'],
                        ['type' => 'newline'],

                        ['type' => 'bold', 'text' => "لینک پنل شما برای مشاهده اطلاعات بسته خریداری شده:"],
                        ['type' => 'link', 'text' => "لینک پنل", 'url' => "{panel_link}"],
                        ['type' => 'newline'],
                        ['type' => 'bold', 'text' => "لینک سابسکریپشن:"],
                        ['type' => 'newline'],
                        ['type' => 'code', 'text' => "{subscription_link}"],
                        ['type' => 'newline'],
                        ['type' => 'text', 'text' => "همچینین شما می توانید QRCode ارسال شده را اسکن نمایید. در صورت نیاز به راهنمایی بر روی آموزش استفاده از لینک سابسکریپشن کلیک کنید."],
                    ]), 'custom_text' => null, 'description' => 'متن سابقه خرید شما: - پارامترها: {name} {category_name} {panel_link} {subscription_link} {start_date} {expire_date} {usage_limit_GB} {usage_GB} {enable}'],
                    ['key' => 'action.history.buttun.recharge', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'تمدید بسته'],
                    ]), 'custom_text' => null, 'description' => 'متن تمدید بسته'],
                    ['key' => 'action.history.buttun.remark', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'تغییر نام بسته'],
                    ]), 'custom_text' => null, 'description' => 'متن تغییر نام بسته'],
                    ['key' => 'action.recharge.success', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'شارژ مجدد با موفقیت انجام شد'],
                    ]), 'custom_text' => null, 'description' => 'متن شارژ مجدد با موفقیت انجام شد'],
                    ['key' => 'action.account.details', 'default_text' => json_encode([
                        ['type' => 'bold', 'text' => 'اطلاعات حساب شما:'],
                        ['type' => 'newline'],
                        ['type' => 'text', 'text' => 'نام کاربری: {username}'],
                        ['type' => 'newline'],
                        ['type' => 'text', 'text' => 'نام: {name} {last_name}'],
                        ['type' => 'newline'],
                        ['type' => 'text', 'text' => 'آیدی عددی: {account_id}'],
                        ['type' => 'newline'],
                        ['type' => 'text', 'text' => 'موجودی کیف پول: {balance}'],
                        ['type' => 'newline'],
                        ['type' => 'text', 'text' => 'موجودی دلاری: {balance_in_dollar}'],
                        ['type' => 'newline'],
                        ['type' => 'text', 'text' => 'موجودی کیف همکاری: {referral_balance}'],

                    ]), 'custom_text' => null, 'description' => 'متن اطلاعات حساب شما: - پارامترها: {username} {name} {last_name} {account_id} {balance} {balance_in_dollar} {referral_balance}'],
                    ['key' => 'action.account.additional_options', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'گزینه های اضافه'],
                    ]), 'custom_text' => null, 'description' => 'متن گزینه های اضافه'],
                    ['key' => 'action.account.additional_options.transactions', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'تراکنش ها'],
                    ]), 'custom_text' => null, 'description' => 'متن تراکنش ها'],
                    ['key' => 'action.account.additional_options.sub_accounts', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'زیر مجموعه ها'],
                    ]), 'custom_text' => null, 'description' => 'متن زیر مجموعه ها'],
                    ['key' => 'action.account.additional_options.add_balance', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'افزایش موجودی'],
                    ]), 'custom_text' => null, 'description' => 'متن افزایش موجودی'],
                    ['key' => 'action.account.transactions.title', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'سابقه تراکنش ها'],
                    ]), 'custom_text' => null, 'description' => 'متن سابقه تراکنش ها'],
                    ['key' => 'action.account.transactions.no_transactions', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'شما هیچ تراکنشی ندارید'],
                    ]), 'custom_text' => null, 'description' => 'متن شما هیچ تراکنشی ندارید'],
                    ['key' => 'action.account.sub_accounts.title', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'سابقه زیر مجموعه ها'],
                    ]), 'custom_text' => null, 'description' => 'متن سابقه زیر مجموعه ها'],
                    ['key' => 'action.account.sub_accounts.no_sub_accounts', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'شما هیچ زیر مجموعه ای ندارید'],
                    ]), 'custom_text' => null, 'description' => 'متن شما هیچ زیر مجموعه ای ندارید'],
                    ['key' => 'action.remark.title', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'لطفا نام جدید بسته خود را وارد کنید یا عبارت "لغو" را ارسال کنید:'],
                    ]), 'custom_text' => null, 'description' => 'متن لطفا نام جدید بسته خود را وارد کنید یا عبارت "لغو" را ارسال کنید:'],
                    ['key' => 'action.remark.success', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'نام بسته با موفقیت تغییر کرد.'],
                    ]), 'custom_text' => null, 'description' => 'متن نام بسته با موفقیت تغییر کرد.'],
                    ['key' => 'action.remark.cancel', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'تغییر نام بسته لغو شد.'],
                    ]), 'custom_text' => null, 'description' => 'متن لغو تغییر نام بسته'],
                    ['key' => 'action.web.generate_auto_login_link', 'default_text' => json_encode([
                        ['type' => 'bold', 'text' => "لینک ورود به پنل: "],
                        ['type' => 'link', 'text' => "لینک", 'url' => "{link}"],
                        ['type' => 'newline'],
                        ['type' => 'text', 'text' => "نام کاربری:"],
                        ['type' => 'newline'],
                        ['type' => 'code', 'text' => "{username}"],
                        ['type' => 'newline'],
                        ['type' => 'text', 'text' => "رمز عبور:"],
                        ['type' => 'newline'],
                        ['type' => 'code', 'text' => "{password}"],
                        ['type' => 'newline'],
                        ['type' => 'text', 'text' => "با این اطلاعات می توانید وارد پنل شوید."],
                    ]), 'custom_text' => null, 'description' => 'متن لینک ورود به پنل: - پارامترها: {link} {username} {password}'],
                    ['key' => 'action.web.auto_login_link', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'لینک ورود سریع به پنل: '],
                    ]), 'custom_text' => null, 'description' => 'متن لینک ورود سریع به پنل: '],
                    ['key' => 'action.help.faq', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'سوالات متداول'],
                    ]), 'custom_text' => null, 'description' => 'متن سوالات متداول'],
                    ['key' => 'action.test_account.success', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'اکانت آزمایشی با موفقیت فعال شد.'],
                    ]), 'custom_text' => null, 'description' => 'متن اکانت آزمایشی با موفقیت فعال شد.'],
                    ['key' => 'action.help.giftCard', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'کد گیفت کارت را وارد کنید.'],
                    ]), 'custom_text' => null, 'description' => 'متن کد گیفت کارت را وارد کنید.'],
                    ['key' => 'action.help.giftCard.success', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'کد گیفت کارت با موفقیت اعمال شد.'],
                    ]), 'custom_text' => null, 'description' => 'متن کد گیفت کارت با موفقیت اعمال شد.'],
                    ['key' => 'error.giftCard.not_found', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'کد گیفت کارت یافت نشد.'],
                    ]), 'custom_text' => null, 'description' => 'متن کد گیفت کارت یافت نشد.'],
                    ['key' => 'error.giftCard.already_used', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'کد گیفت کارت قبلا استفاده شده است.'],
                    ]), 'custom_text' => null, 'description' => 'متن کد گیفت کارت قبلا استفاده شده است.'],
                    ['key' => 'error.giftCard.too_many_attempts', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => "شما به دلیل تلاش‌های ناموفق زیاد به مدت {minutes} دقیقه نمی‌توانید گیفت کارت جدید وارد کنید."],
                    ]), 'custom_text' => null, 'description' => 'متن جلوگیری برای حدس زدن گیفت کارت ها - پارامترها: {minutes}'],
                    ['key' => 'error.giftCard.blocked', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'شما به دلیل وارد کردن گیفت کارت نامعتبر بیش از ۳ بار، به مدت یک ساعت نمی‌توانید گیفت کارت جدید وارد کنید.'],
                    ]), 'custom_text' => null, 'description' => 'متن شما به دلیل وارد کردن گیفت کارت نامعتبر بیش از ۳ بار، به مدت یک ساعت نمی‌توانید گیفت کارت جدید وارد کنید.'],
                    ['key' => 'action.referral.title', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'کسب درآمد از فروش بسته ها'],
                    ]), 'custom_text' => null, 'description' => 'متن کسب درآمد از فروش بسته ها'],
                    ['key' => 'action.referral.text', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'شما می توانید از لینک زیر برای دعوت دوستان خود استفاده کنید: {link}'],
                        ['type' => 'newline'],
                        ['type' => 'text', 'text' => 'درصد کسب درآمد شما {percent}% است.'],
                    ]), 'custom_text' => null, 'description' => 'متن شما می توانید از لینک زیر برای دعوت دوستان خود استفاده کنید: {link} - پارامترها: {link} {percent}'],

                    ['key' => 'error.test_account.exist', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'اکانت آزمایشی از قبل برای شما فعال شده است، می توانید از سابقه خرید به اطلاعات آن دسترسی داشته باشید.'],
                    ]), 'custom_text' => null, 'description' => 'متن اکانت آزمایشی از قبل برای شما فعال شده است، می توانید از سابقه خرید به اطلاعات آن دسترسی داشته باشید.'],
                    ['key' => 'error.blocked_user', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'اکانت شما توسط مدیر مسدود شده است.'],
                    ]), 'custom_text' => null, 'description' => 'متن اکانت شما توسط مدیر مسدود شده است.'],
                    ['key' => 'error.server_error', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'خطایی رخ داده است'],
                    ]), 'custom_text' => null, 'description' => 'متن خطایی رخ داده است'],
                    ['key' => 'action.block_user.success', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'کاربر با موفقیت مسدود شد.'],
                    ]), 'custom_text' => null, 'description' => 'متن کاربر با موفقیت مسدود شد.'],
                    ['key' => 'action.unblock_user.success', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'کاربر با موفقیت آزاد شد.'],
                    ]), 'custom_text' => null, 'description' => 'متن کاربر با موفقیت آزاد شد.'],

                    ['key' => 'error.menu.not_found', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'گزینه ای یافت نشد'],
                    ]), 'custom_text' => null, 'description' => 'متن گزینه ای یافت نشد'],
                    ['key' => 'error.user_not_found', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'کاربر یافت نشد'],
                    ]), 'custom_text' => null, 'description' => 'متن کاربر یافت نشد'],

                    ['key' => 'error.action.not_found', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'عملیات نامعتبر است'],
                    ]), 'custom_text' => null, 'description' => 'متن عملیات نامعتبر است'],
                    ['key' => 'error.command.not_found', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'دستور نامعتبر است. برای مشاهده لیست دستورات از /help استفاده کنید.'],
                    ]), 'custom_text' => null, 'description' => 'متن دستور نامعتبر'],
                    ['key' => 'error.product_not_rechargeable', 'default_text' => json_encode([
                        ['type' => 'text', 'text' => 'این بسته قابلیت شارژ ندارد'],
                    ]), 'custom_text' => null, 'description' => 'متن این بسته قابلیت شارژ ندارد'],

                ];
                CustomText::insert($data);

                \Log::info('CustomText table seeded successfully');
                return true;
            }
        } catch (\Throwable $th) {
            \Log::info("sdaaa: $th");
            return;
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
            \Log::info("getText: $key");
            $this->seed();
            // await for seeding
            sleep(1);
            $text = $this->customText->getText($key);
            if (json_validate($text)) {
                return json_decode($text, true);
            }
            return $text;
        }
    }

    public function setText($key, $text)
    {
        try {
            // check if the key is in the database
            if ($this->customText->where('key', $key)->exists()) {
                return $this->customText->setText($key, $text);
            }
            return false;
        } catch (\Throwable $th) {
            \Log::info("setText: $key => $text");
            return false;
        }
    }
    // set test by request
    public function setTest(Request $request)
    {
        try {
            // validate request
            $validator = Validator::make($request->all(), [
                'key'  => 'required',
                'text' => 'required',
            ]);
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }
            $key  = $request['key'];
            $text = $request['text'];
            $this->setText($key, $text);
            return response()->json(['message' => 'Text set successfully'], 200);
        } catch (\Throwable $th) {
            \Log::info("setTest: $th");
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }
}
