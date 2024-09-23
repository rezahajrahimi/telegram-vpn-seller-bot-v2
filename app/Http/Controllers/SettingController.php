<?php

namespace App\Http\Controllers;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function getWelcomeMessage()
    {
        return Setting::find(1)->welcome_message;
    }
    public function getAdminId()
    {
        return Setting::find(1)->admin_id;
    }
    public function getBotToken()
    {
        return Setting::find(1)->bot_token;
    }
    public function getBotSetting()
    {
        return Setting::find(1);
    }
    public function updateBotSetting(Request $request)
    {
        $data = Setting::find(1);
        $data->bot_name = $request->bot_name;
        $data->admin_id = $request->admin_id;
        $data->bot_token = $request->bot_token;
        $data->welcome_message = $request->welcome_message;
        $data->panel_address = $request->panel_address;
        if ($data->update()) {
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
        $data = Setting::find(1);
        $name = $data->bot_name;
        if($name != null) {
                    // check is name have @ , if has remove it
        $name = str_replace('@', '', $name);
        return $name;

        } else {
            return "setbotname";
        }
    }
}
