<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
class UserController extends Controller
{
    public function getUsers()
    {
        $users = User::all();
        return response()->json(
            [
                'users' => $users,
            ],
            200,
        );
    }
    public function getAgents()
    {
        try {
            $users = User::where('role', 'agent')->get();
            return response()->json(
                [
                    'agents' => $users,
                ],
                200,
            );
        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return response()->json(null, 500);
        }
    }
    public function getNormalUsers()
    {
        try {
        $users = User::where('role', 'user')->get();
        return response()->json(
            [
                'users' => $users,
            ],
            200,
        );

        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return response()->json(null, 500);

        }
    }
    public function getUserById($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(
                [
                    'message' => 'User not found',
                ],
                404,
            );
        }
        return response()->json(
            [
                'user' => $user,
            ],
            200,
        );
    }
    public function getUserIdByTelegramID($telID)
    {
        $user = User::where('account_id', $telID)->first();
        if (!$user) {
            return null;
        }
        return $user->id;
    }
    public function createUser(Request $request)
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
                'message' => 'User created successfully',
                'user' => $user,
            ],
            201,
        );
    }
    public function updateUser(Request $request)
    {
        try {
            //code..

            $request->validate([
                'name' => 'required|string|max:255',
                'account_id' => 'required|max:8',
                'password' => 'required|string|min:8',
                'role' => 'required|string',
            ]);

            $user = User::where('account_id', $request->account_id)->first();
            if (!$user) {
                return response()->json(
                    [
                        'message' => 'User not found',
                    ],
                    404,
                );
            }

            $user->name = $request->name;
            $user->account_id = $request->account_id;
            $user->role = $request->role;
            $user->password = Hash::make($request->password);
            $user->save();

            return response()->json(
                [
                    'message' => 'User updated successfully',
                    'user' => $user,
                ],
                200,
            );
        } catch (\Throwable $th) {
            \Log::info("Throwable $th");
        }
    }

    public function deleteUser(Request $request)
    {
        $request->validate([
            'account_id' => 'required|max:8',
        ]);

        $user = User::where('account_id', $request->account_id)->first();
        if (!$user) {
            return response()->json(
                [
                    'message' => 'User not found',
                ],
                404,
            );
        }

        $user->delete();

        return response()->json(
            [
                'message' => 'User deleted successfully',
            ],
            200,
        );
    }
}
