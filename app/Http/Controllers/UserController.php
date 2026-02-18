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
    public function getAgentByIdWithProductsAndPremissons($id)
    {
        try {
            $users = User::where('role', 'agent')->where('id', $id)->with('agent_products', 'agent_permisson')->get();

            return response()->json($users, 200);
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
    public function get_admin_users()
    {
        $users = User::where('role', 'admin')->get();
        return response()->json([
            'admins' => $users,
        ]);
    }
    public function change_user_role_to_admin($id){

        $user = User::where('account_id', $id)->first();
        if (!$user) {
           return response()->json(null, 401);
        }
        $user->role = 'admin';
        $user->update();
        return response()->json(true, 201);
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
    public function changeUserRoleToAgent($id)
    {
        $user = User::find($id);
        if (!$user) {
            return null;
        }
        $user->role = 'agent';
        $user->update();
        return true;
    }
    public function changeAgentRoleToUser($id)
    {
        try {
            $user = User::find($id);
            if (!$user) {
            return null;
        }
        // checl if user_account_id is not null and is not equal to TELEGRAM_ADMIN_ID in .env
            if ($user->role != 'agent') {
                \Log::info("user {$user->id} is  not agent");
                return null;
            }

            $user->role = 'user';
            $user->update();
            return true;
        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return false;
        }
    }
    public function change_user_role_to_user($id)
    {
        $user = User::where('account_id', $id)->first();
        if (!$user) {
            return response()->json(null, 401);
        }
        // checl if user_account_id is not null and is not equal to TELEGRAM_ADMIN_ID in .env
        if ($user->account_id == env('TELEGRAM_ADMIN_ID')) {
            return response()->json(null, 401);
        }

        $user->role = 'user';
        $user->update();
        return response()->json(true, 201);
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
    public function update_logged_password(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:8',
        ]);
        $accountID = auth('sanctum')->user()->account_id;
        $user = User::where('account_id', $accountID)->first();
        if (!$user) {
            return response()->json(
                [
                    'message' => 'User not found',
                ],
                404,
            );
        }

        $user->password = Hash::make($request->password);
        $user->update();

        return response()->json(
            [
                'message' => 'User updated successfully',
                'user' => $user,
            ],
            200,
        );
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

    /// Agent
    public function updateAgentPassword(Request $request)
    {
        try {
            //code..

            $request->validate([
                'password' => 'required|string|min:8',
            ]);
            $accountID = auth('sanctum')->user()->account_id;
            $user = User::where('account_id', $accountID)->first();
            if (!$user) {
                return response()->json(
                    [
                        'message' => 'User not found',
                    ],
                    404,
                );
            }

            $user->password = Hash::make($request->password);
            $user->save();

            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::info("Throwable $th");
        }
    }
}
