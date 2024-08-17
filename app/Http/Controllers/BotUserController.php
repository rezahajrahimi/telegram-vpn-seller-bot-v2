<?php

namespace App\Http\Controllers;
use App\Models\BotUser;
use App\Models\User;

use Illuminate\Http\Request;

class BotUserController extends Controller
{
    public function createNewUserBot($account_id, $userName, $firstName, $lastName)
    {
        $logCtrl = new LogController();
        $logCtrl->addNewLog('user', 'کاربر جدید وارد ربات شد.', $account_id, $userName, 'new user');
        $botUser = BotUser::firstOrCreate([
            'account_id' => $account_id,
            'username' => $userName,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
        $user = new User;
        $user->name = $userName ;
        $user->account_id = $account_id;
        $user->password = "$account_id$userName";
        $user->role = "user";
        $user->save();
        return $botUser;

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
    public function getBotUserList()
    {
        try {
            $data = BotUser::all();
            if ($data != null) {
                return response()->json($data, 200);
            } else {
                return response()->json('No Data', 404);
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return response()->json('Server Error', 500);
        }
    }
    public function getBotUserListByPagination()
    {
        try {
            $data = BotUser::paginate(16, ['*'], 'page');
            return $data;
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return response()->json('Server Error', 500);
        }
    }

    public function getBotUserByID($id)
    {
        try {
            $data = BotUser::where('id', $id)
                ->with(['products', 'transaction', 'ballance', 'logs'])
                ->first();
            if ($data != null) {
                return response()->json($data, 200);
            } else {
                return response()->json('No Data', 404);
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return response()->json('Server Error', 500);
        }
    }
    public function getLast10Users()
    {
        try {
            $data = BotUser::orderBy('id', 'desc')
                ->limit(10)
                ->get();
            if ($data != null) {
                return $data;
            } else {
                return null;
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");
        }
    }
    public function getUserIDByAccountID($accountID){
        $data = BotUser::where('account_id', $accountID)->first();
        if ($data != null) {
            return $data->id;
        } else {
            return null;
        }
    }
}
