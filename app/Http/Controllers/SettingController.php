<?php

namespace App\Http\Controllers;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function getWelcomeMessage()
    {
        return Setting::All()->first()->welcome_message;
    }
    public function getAdminId()
    {
        return Setting::All()->first()->admin_id;
    }
    public function getBotToken()
    {
        return Setting::find(1)->bot_token;
    }
    public function getBotSetting()
    {
        $setting = Setting::All()->first();
        if ($setting != null) {
            return $setting;
        } else {
            $setting = new Setting();
            $setting->bot_name = 'powerPsBot';
            $setting->admin_id = env('TELEGRAM_ADMIN_ID');
            $setting->bot_token = env('TELEGRAM_BOT_TOKEN');
            $setting->welcome_message = 'به ربات  [@powerPsBot] خوش آمدید.';
            $setting->panel_address = env('APP_URL');
            $setting->save();
            return $setting;
        }
    }
    public function updateBotSetting(Request $request)
    {
        $data = Setting::All()->first();
        $data->bot_name = $request->bot_name;
        $data->admin_id = $request->admin_id;
        $data->bot_token = $request->bot_token;
        $data->welcome_message = $request->welcome_message;
        $data->panel_address = $request->panel_address;
        if ($data->update()) {
            // change in .env
            $env = new DotenvEditor();
            $env->changeEnv([
                'TELEGRAM_BOT_TOKEN' => $request->bot_token,
                'TELEGRAM_ADMIN_ID' => $request->admin_id,
                'APP_URL' => $request->panel_address,
            ]);
            return true;
        } else {
            return false;
        }
    }
    public function getMainUrl()
    {
        $data = Setting::find(1);
        $string = $data->panel_address;
        $endsWith = '/';
        $result = str_ends_with($string, $endsWith) ? 'is' : 'is not';
        if ($result == 'is not') {
            return $data->panel_address;
        } else {
            // remove last charecter in string
            $string = substr($string, 0, -1);
            return $string;
        }
    }
    public function get_bot_name()
    {
        $data = Setting::All()->first();
        $name = $data->bot_name;
        if ($name != null) {
            // check is name have @ , if has remove it
            $name = str_replace('@', '', $name);
            return $name;
        } else {
            return 'setbotname';
        }
    }
}
