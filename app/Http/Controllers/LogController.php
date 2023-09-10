<?php

namespace App\Http\Controllers;
use App\Models\Log;

use Illuminate\Http\Request;

class LogController extends Controller
{
    public function addNewLog($type, $message, $account_id, $username, $event)
    {
        Log::create([
            'type' => $type,
            'message' => $message,
            'account_id' => $account_id,
            'username' => $username,
            'event' => $event,
        ]);
        return true;
    }
}
