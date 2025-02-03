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
    public function seed()
    {
        // check if the table is empty
        if (CustomText::count() == 0) {
            $data = [
                ['key' => 'action.start', 'default_text' => 'به بات آموزشی خوش آمدید', 'custom_text' => null],
                ['key' => 'action.help', 'default_text' => 'راهنما', 'custom_text' => null],
                ['key' => 'action.back', 'default_text' => 'بازگشت', 'custom_text' => null],
                ['key' => 'action.send_location', 'default_text' => 'ارسال موقعیت مکانی', 'custom_text' => null],
                ['key' => 'action.send_contact', 'default_text' => 'ارسال شماره تماس', 'custom_text' => null],
                ['key' => 'action.upload_file', 'default_text' => 'آپلود فایل', 'custom_text' => null],
                ['key' => 'action.send_photo', 'default_text' => 'ارسال عکس', 'custom_text' => null],
                ['key' => 'error.server_error', 'default_text' => 'خطایی رخ داده است', 'custom_text' => null],

            ];
            CustomText::insert($data);
        }
    }
    public function getText($key)
    {
        $text = $this->customText->getText($key);
        if ($text == null) {
            // run the seed function
            $this->seed();
            return $this->customText->getDefaultText($key);
        }
        return $text;
    }

    public function setText($key, $text)
    {
        return $this->customText->setText($key, $text);
    }
}
