<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
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
        $request->validate([
            'account_id' => 'required|max:8',
            'password' => 'required|string',
        ]);

        $user = User::where('account_id', $request->account_id)->first();

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
        $request->user()->tokens()->delete();

        return response()->json('Logged out successfully');
    }
}
