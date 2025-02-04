<?php

namespace App\Http\Controllers;

use App\Models\CustomText;
use Illuminate\Http\Request;

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
                ['key' => 'action.start', 'default_text' => 'سلام {name}! به ربات آموزشی خوش آمدید', 'custom_text' => null],
                ['key' => 'action.help', 'default_text' => 'راهنما', 'custom_text' => null],
                ['key' => 'action.back', 'default_text' => 'بازگشت', 'custom_text' => null],
                ['key' => 'action.send_location', 'default_text' => 'ارسال موقعیت مکانی', 'custom_text' => null],
                ['key' => 'action.send_contact', 'default_text' => 'ارسال شماره تماس', 'custom_text' => null],
                ['key' => 'action.upload_file', 'default_text' => 'آپلود فایل', 'custom_text' => null],
                ['key' => 'action.send_photo', 'default_text' => 'ارسال عکس', 'custom_text' => null],
                ['key' => 'error.server_error', 'default_text' => 'خطایی رخ داده است', 'custom_text' => null],
                ['key' => 'error.menu.not_found', 'default_text' => 'گزینه ای یافت نشد', 'custom_text' => null],
                ['key' => 'error.command.not_found', 'default_text' => 'دستور نامعتبر است. برای مشاهده لیست دستورات از /help استفاده کنید.', 'custom_text' => null],
                ['key' => 'action.welcome_back', 'default_text' => 'خوش برگشتی {name}! آخرین بازدید شما: {last_visit}', 'custom_text' => null],
                ['key' => 'welcome.message', 'default_text' => json_encode([
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
            \Log::error('Error getting text: ' . $th->getMessage());
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
