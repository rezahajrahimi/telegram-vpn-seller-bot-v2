<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function createFirstAdminUser(){
        $admin = User::where('role', 'admin')->first();
        if(!$admin){
            $admin = User::create([
                'name' => 'admin',
                'account_id' => env('TELEGRAM_ADMIN_ID'),
                'role' => 'admin',
                'password' => Hash::make('admin123456'),
            ]);
        }
        return $admin;
    }
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'account_id' => 'required|max:8|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string',
        ]);

        $user = User::create([
            'name' => $request->name,
            'account_id' => $request->account_id,
            'role' => $request->role,
            'password' => Hash::make($request->password),
        ]);

        return response()->json(
            [
                'user' => $user,
                'token' => $user->createToken('token-name')->plainTextToken,
            ],
            201,
        );
    }
    public function login(Request $request)
    {
        // check first admin login
        $this->createFirstAdminUser();

        $request->validate([
            'account_id' => 'required|max:8',
            'password' => 'required|string',
        ]);

        $user = User::where('account_id', $request->account_id)
                   ->orWhere('name', $request->account_id)

        ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'account_id' => ['The provided credentials are incorrect.'],
            ]);
        }

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('token-name')->plainTextToken,
        ]);
    }
    public function logout(Request $request)
    {
        auth('sanctum')->user()->tokens()->delete();

        return response()->json('Logged out successfully');
    }
    public function forgetPassword(Request $request)
    {
        $request->validate([
            'account_id' => 'required|max:8',
        ]);

        $user = User::where('account_id', $request->account_id)->first();

        $user_password = substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRTUVWXYZ2346789'), 0, 8);
        $user->password =  Hash::make($user_password);
        $user->update();
        $user_id = $user->account_id;
        $text = "کاربر گرامی \n\r";
        $text .= "رمز عبور شما به پنل تغییر یافت \n\r";
        $text .= "اکانت آیدی ورد به پنل: $user_id \n\r";
        $text .= "پسورد ورود به پنل: $user_password";
        $result = app('telegram_bot')->sendMessage($text, $user_id, null, 'MarkDown');

        return response()->json(true);
    }
}
