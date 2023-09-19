<?php

namespace App\Http\Controllers;
use App\Models\ChannelLockMenuItem;

use Illuminate\Http\Request;

class ChannelLockMenuItemController extends Controller
{
    public function getChannelLockMainMenuTitle()
    {
        $data = ChannelLockMenuItem::where('name', 'main')->first();
        if ($data != null) {
            return $data;
        } else {
            $payment = new ChannelLockMenuItem();
            $payment->name = 'main';
            $payment->alias_name = 'برای شروع، لطفا در کانالهای زیر عضو بشوید.';
            $payment->level = 1;
            $payment->save();
            return $payment;
        }
    }
    public function updateChannelLockMenuAlisNameByLevel(Request $request)
    {
        $data = ChannelLockMenuItem::where('level', $request->level)->first();
        if ($data != null) {
            $data->alias_name = $request->alias_name;
            $data->update();
            return true;
        } else {
            return response()->json(false, 401);        }
    }
}
