<?php

namespace App\Http\Controllers;
use App\Models\BotUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Services\TelegramService;
use Carbon\Carbon;
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
        $user->name = $userName;
        $user->account_id = $account_id;
        $user->password = Hash::make("12345678");
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
    public function get_last_10_bot_user()
    {
        try {
            return BotUser::orderBy('id', 'desc')->take(10)->get();
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return response()->json('Server Error', 500);
        }
    }
    public function get_users_by_past_days($days){
        try {
            $startDay = Carbon::now()->subDays($days)->startOfDay();
            $endDay = Carbon::now()->endOfDay();
            $data = BotUser::whereBetween('created_at', [$startDay, $endDay])->orderBy('id','desc')->get();
            return $data;
        } catch (\Throwable $th) {
            \Log::debug('get_users_by_past_days'. $th->getMessage());
            return response()->json('get_users_by_past_days error', 500);
        }
    }

    public function search_bot_users(Request $request)
    {
        try {
            $data = BotUser::where('username', 'like', '%' . $request->search . '%')
                ->orWhere('first_name', 'like', '%' . $request->search . '%')
                ->orWhere('last_name', 'like', '%' . $request->search . '%')
                ->orWhere('account_id', 'like', '%' . $request->search . '%')
                ->get();

            return $data;
        } catch (\Throwable $th) {
            \Log::info("search_bot_users:  $th");

            return response()->json('Server Error', 500);
        }
    }
    public function getBotUserByID($id)
    {
        try {
            $data = BotUser::where('id', $id)
                ->with(['products', 'transaction', 'ballance', 'logs', 'user', 'blocked_user'])
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
    public function getUserIDByAccountID($accountID)
    {
        $data = BotUser::where('account_id', $accountID)->first();
        if ($data != null) {
            return $data->id;
        } else {
            return null;
        }
    }
    public function send_Admin_message_to_All_users(Request $request)
    {
        try {
            $data = BotUser::all();
            $telegramService = new TelegramService();
            $message = $request->message;
            foreach ($data as $key => $value) {
                try {
                    //$telegramService->sendMessage($value->account_id,  $message);
                    \Log::info($message . " " . $value->account_id);

                } catch (\Throwable $th) {
                    \Log::debug('seng_message_to_all_user => ' . $th->getMessage());
                }
            }
            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::debug('seng_message_to_all_user' . $th->getMessage());
        }
    }
    public function send_admin_message_to_all_users_without_configs(Request $request)
    {
        try {
            $data = BotUser::with('products')->get();
            // get all $data which have zero count of products
            $data = $data->filter(function ($user) {
                return $user->products->count() === 0;
            })->values();

            $telegramService = new TelegramService();
            $message = $request->message;
            foreach ($data as $key => $value) {
                try {
                    //$telegramService->sendMessage($value->account_id,  $message);
                    \Log::info($message . " " . $value->account_id);

                } catch (\Throwable $th) {
                    \Log::debug('send_admin_message_to_all_users_without_configs => ' . $th->getMessage());
                }
            }
            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::debug('seng_message_to_all_user' . $th->getMessage());
        }
    }

}
