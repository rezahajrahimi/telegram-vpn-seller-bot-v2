<?php

namespace App\Http\Controllers;

use App\Models\MessageSettingLookup;
use Illuminate\Http\Request;

class MessageSettingLookupController extends Controller
{
    public function getAll()
    {
        try {
            $messageSettingLookups = MessageSettingLookup::all();
            if ($messageSettingLookups->isEmpty()) {
                $this->seed();
                $messageSettingLookups = MessageSettingLookup::all();
            }
            return response()->json($messageSettingLookups);
        } catch (\Throwable $th) {
            \Log::info("MessageSettingLookupController->getAll->error", ['error' => $th->getMessage()]);
            return response()->json('Server Error', 500);
        }
    }

    public function seed()
    {
        $messageSettingLookups = [
            [
                'name' => 'tested_config',
                'value' => 'true',
                'description' => 'ارسال پیام به کاربرانی که اکانت تست تهیه کرده اند، اما خرید انجام نداده اند.',
                'text' => 'سلام دوست گلم! 😊
                    کانفیگ اصلی رو فراموش نکن! اگه سوال یا کمکی خواستی، ما همینجاییم. منتظرت هستیم! 🚀
                    ارادتمند شما... ❤️
                    ',
            ],
            ['name' => 'reminder_message_for_new_user', 'value' => 'true', 'description' => 'ارسال پیام به کاربرانی که ربات که عضو ربات شده اند، اما خریدی انجام نداده اند', 'text'=>'سلام عزیزم! 👋

                هنوز که هنوزه، هم می‌تونید اکانت تست بخرید هم کانفیگ اصلی رو با بهترین کیفیت دریافت کنید!

                اگه سوالی داری یا کمکی خواستی، من همینجا هستم. 😊

                بیا شروع کنیم! 🚀

            '],
            ['name' => 'deactivated_users', 'value' => 'true', 'description' => 'ارسال پیام به کاربرانی که هیچ اکانت فعالی ندارند و اکانتها را تمدید نکرده اند.', 'text' => 'سلام عزیز دیرینه! 🌟

            ما هنوز اینجا هستیم و آماده سرویس دادن به تو با بهترین کانفیگ‌ها!

            اگر هنوز نیاز داری یا سوالی هست، فقط بگو. خوشحال می‌شیم کمکت کنیم. 😊

            همین امروز دوباره به خانواده ما بپیوند! 🚀

            '],
        ];
        MessageSettingLookup::insert($messageSettingLookups);
    }
    public function re_seed_message_settings_lookup()
    {
        try {
            // truncate all data and run seed
            MessageSettingLookup::truncate();
            $this->seed();
        } catch (\Throwable $th) {
            \Log::info("MessageSettingLookupController->re_seed_message_settings_lookup->error", ['error' => $th->getMessage()]);
            return response()->json('Server Error', 500);
        }
        return response()->json('Message settings lookup reseeded successfully');
    }
    public function getByName($name)
    {
        try {
            $messageSettingLookup = MessageSettingLookup::getByName($name);
            if (!$messageSettingLookup) {
                return response()->json('Message setting not found', 404);
            }
            return response()->json($messageSettingLookup);
        } catch (\Throwable $th) {
            \Log::info("MessageSettingLookupController->getByName->error", ['error' => $th->getMessage()]);
            return response()->json('Server Error', 500);
        }
    }
    public function getByNameAndValue($name, $value)
    {
        try {
            $messageSettingLookup = MessageSettingLookup::getByNameAndValue($name, $value);
            if (!$messageSettingLookup) {
                return response()->json('Message setting not found', 404);
            }
            return response()->json($messageSettingLookup);
        } catch (\Throwable $th) {
            \Log::info("MessageSettingLookupController->getByNameAndValue->error", ['error' => $th->getMessage()]);
            return response()->json('Server Error', 500);
        }
    }
    public function getValueByName($name)
    {
        try {
            $messageSettingLookup = MessageSettingLookup::getByName($name);
            if (!$messageSettingLookup) {
                return response()->json('Message setting not found', 404);
            }
            return response()->json(['value' => $messageSettingLookup->value]);
        } catch (\Throwable $th) {
            \Log::info("MessageSettingLookupController->getValueByName->error", ['error' => $th->getMessage()]);
            return response()->json('Server Error', 500);
        }
    }

}
