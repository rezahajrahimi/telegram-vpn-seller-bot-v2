<?php

namespace App\Http\Controllers;
use App\Models\BotUser;

use Illuminate\Http\Request;

class BotUserController extends Controller
{
    public function createNewUserBot($account_id, $userName, $firstName, $lastName)
    {
        $logCtrl = new LogController();
        $logCtrl->addNewLog('user', 'new user', $account_id, $userName, 'create');
        return BotUser::firstOrCreate([
            'account_id' => $account_id,
            'username' => $userName,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
    }
    public function hasRegistred($account_id, $userName, $firstName, $lastName)
    {
        $user = BotUser::where('account_id', $account_id)->first();
        if ($user != null) {
            return true;
        } else {
            $this->createNewUserBot($account_id, $userName, $firstName, $lastName);
            return false;
        }
    }
}
