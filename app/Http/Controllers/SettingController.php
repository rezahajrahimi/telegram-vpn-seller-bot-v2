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
}
