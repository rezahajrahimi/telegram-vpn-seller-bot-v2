<?php
namespace App\Http\Controllers;

use App\Http\Controllers\GeneralController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use App\Services\TelegramService;
class AuthController extends Controller
{
    private GeneralController $generalCntrl;
    private TelegramService $telegramService;
    public function __construct()
    {
        $this->generalCntrl = new GeneralController();
        $this->telegramService = new TelegramService();
    }
    public function getHostName()
    {

        $hostUrl = env('FRONT_URL');

        // get host
        return $hostUrl;
    }
    public function getPowerPsLicenseType()
    {
        // check app env
        $appEnv = env('APP_ENV');
        if ($appEnv != 'local' && $appEnv != 'testing') {

            $host = $this->getHostName();

            $licenseType = 'gold';
            // get afmin id from .env
            $adminId = env('TELEGRAM_ADMIN_ID');
            // $host = '';
            // if (isset($_SERVER['HTTP_HOST'])) {
            //     $host = $_SERVER['HTTP_HOST'];
            // }

            $hasLicense = Http::post('https://license-checker.chbk.app/api/checkLicense', [
                'name'     => 'Reza',
                'type'     => "{$licenseType}",
                'host'     => "{$host}",
                'admin_id' => "{$adminId}",
            ]);
            // check if $hasLicense response was 401 or not
            // if not go on
            // if yes return false

            if ($hasLicense->status() != 200) {
                return 'free';
            }

            return $hasLicense->json()['data']['account_type'];
        }
    }

    public function createFirstAdminUser()
    {
        try {
            $admin = User::where('role', 'admin')->first();
            if ($admin == null) {
                // get admin id from .env
                $adminId = env('TELEGRAM_ADMIN_ID');
                \Log::info("adminId: {$adminId}");
                $admin = User::create([
                    'name'       => 'admin',
                    'account_id' => $adminId,
                    'role'       => 'admin',
                    'password'   => Hash::make('admin123456'),
                ]);
                $this->generalCntrl->boot_seeding_data();
            }
            return $admin;
        } catch (\Exception $e) {
            \Log::error($e);
            return $e;
        }
    }
    public function register(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:255|unique:users',
            'account_id' => 'required|max:8|unique:users',
            'password'   => 'required|string|min:8',
            'role'       => 'required|string',
        ]);

        $user = User::create([
            'name'       => $request->name,
            'account_id' => $request->account_id,
            'role'       => $request->role,
            'password'   => Hash::make($request->password),
        ]);

        return response()->json(
            [
                'user'  => $user,
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
            'account_id' => 'required|max:255', // it's also can be name
            'password'   => 'required|string',
        ]);

        $user = User::where('account_id', $request->account_id)
            ->orWhere('name', $request->account_id)

            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'account_id' => ['The provided credentials are incorrect.'],
            ]);
        }

        return response()->json([
            'user'  => $user,
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
            'account_id' => 'required|min:8',
        ]);
        $user = User::where('account_id', $request->account_id)->first();
        if (! $user) {
            return response()->json(false);
        }
        $user_password  = substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRTUVWXYZ2346789'), 0, 8);
        $user->password = Hash::make($user_password);
        $user->update();
        // \Log::info("passss {$user_password}");
        $user_id = $user->account_id;
        $text    = "کاربر گرامی \n\r";
        $text .= "رمز عبور شما به پنل تغییر یافت \n\r";
        $text .= 'نام کاربری ورود به پنل:';
        $result = app('telegram_bot')->sendMessage($text, $user_id, null, 'MarkDown');
        $text   = "<code>{$user_id}</code>";
        $result = app('telegram_bot')->sendMessage($text, $user_id, null, 'HTML');

        $text   = "پسورد ورود به پنل:  \n\r";
        $result = app('telegram_bot')->sendMessage($text, $user_id, null, 'MarkDown');
        $text   = "<code>{$user_password}</code>";
        $result = app('telegram_bot')->sendMessage($text, $user_id, null, 'HTML');
        return response()->json(true);
    }
    public function generate_auto_login_link(Request $request)
    {
        // $request->validate([
        //     'account_id' => 'required|min:8',
        // ]);
        $user = User::where('account_id', $request->account_id)->first();
        if (! $user) {
            return response()->json(false);
        }

        $frontUrl = env('FRONT_URL');
        // check if $frontUrl ended with "/" remove it

        if (str_ends_with($frontUrl, '/')) {
            $frontUrl = substr($frontUrl, 0, -1);
        }

        $user_password  = substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRTUVWXYZ2346789'), 0, 8);
        $user->password = Hash::make($user_password);
        $user->update();
        $user_id       = $user->account_id;
        $mainMenuCntrl = new MainMenuItemController();
        $menuAliasName = $mainMenuCntrl->getMenuAliasNameByName('webapp');
        $text = "لینک ورود به پنل: \n\r <code>{$frontUrl}/#/login/{$user_id}/{$user_password}</code> \n\r username: \n\r <code>{$user_id}</code> \n\r password: \n\r <code>{$user_password}</code>";
        $this->telegramService->sendMessage($user_id, $text);
        $text = "ورود سریع به پنل ⬇️\n\r";

        $opr = [
            [
                'text' => "$menuAliasName",
                'url'  => "$frontUrl/#/login/$user_id/{$user_password}",
            ],
        ];
        
        $this->telegramService->sendMessageWithLinkButtons($user_id, $text, $opr);

        return response()->json(true);
    }
}
