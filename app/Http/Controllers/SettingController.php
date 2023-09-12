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
}
