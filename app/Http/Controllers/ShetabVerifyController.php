<?php
namespace App\Http\Controllers;

use App\Models\PaymentSetting;
use App\Models\PaymentType;
use App\Models\ShetabVerify;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\TelegramService;
class ShetabVerifyController extends Controller
{
    private PaymentSettingController $paymnetSettingCntrl;
    private CustomTextController $customTextCtrl;
    public function __construct()
    {
        // $this->middleware('auth');
        $this->paymnetSettingCntrl = new PaymentSettingController();
        $this->customTextCtrl = new CustomTextController();
    }
    public function check_shetab_verify_status()
    {
        // $authCntrl             = new AuthController();
        // $getPowerPsLicenseType = $authCntrl->getPowerPsLicenseType();
        // if ($getPowerPsLicenseType == 'free') {
        //     \Log::info('You are not authorized to check the shetab verify status');
        //     return false;
        // }

        $shetabVerify = $this->paymnetSettingCntrl->getPaymentSettingStatusByKey('shetab_verify');
        return $shetabVerify;
    }
    public function create_new_shetab_verify(Request $request)
    {
        // check license type in auth controller
        // $authCntrl             = new AuthController();
        // $getPowerPsLicenseType = $authCntrl->getPowerPsLicenseType();
        // if ($getPowerPsLicenseType == 'free') {
        //     return response()->json(['message' => 'You are not authorized to create a new shetab verify'], 401);
        // }
        // check the amount it's not negative and not zero and not exist in shetab_verifies table where status is pending
        if ($request->amount <= 0) {
            return null;
        }
        $shetabVerify = ShetabVerify::where('amount', $request->amount)->where('status', 'pending')->first();
        if ($shetabVerify) {
            return null;
        }
        // create a new shetab verify
        $shetabVerify = ShetabVerify::create([
            'amount'          => $this->create_uniqe_amount($request->amount),
            'user_id'         => $request->user_id,
            'status'          => 'pending',
        ]);
        return $shetabVerify->amount;
    }
    public function create_uniqe_amount($amount){
        // get amount and change two last digits to random number bwtween 00 and 99
        $amount = substr($amount, 0, -2) . str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);
        // check if the amount is exist in shetab_verifies table where status is pending
        $shetabVerify = ShetabVerify::where('amount', $amount)->where('status', 'pending')->first();
        if ($shetabVerify) {
            return $this->create_uniqe_amount($amount);
        }
        return $amount;
    }
    public function validate_shetab_verify(Request $request)
    {
        //get api_key from header and check if it is valid
        $api_key = $request->header('Authorization');
        if (! $api_key) {
            return response()->json(['message' => 'Api key is required'], 401);
        }
        // validate api_key
        $api_key = PaymentSetting::where('key', 'shetab_verify')->first()->value;
        if (! $api_key) {
            return response()->json(['message' => 'Api key is invalid'], 401);
        }
        if ($api_key != $api_key) {
            return response()->json(['message' => 'Api key is invalid'], 401);
        }
        $amount = $request->amount / 10; // convert to toman
        // check the row in shetab_verifies table with amount and status is pending and created_at is less than 10 minutes ago
        $shetabVerify = ShetabVerify::where('amount', $amount)->where('status', 'pending')->where('created_at', '>', now()->subMinutes(10))->first();
        if (! $shetabVerify) {
            return response()->json(['message' => 'Shetab verify not found'], 404);
        }
        // check the status of the shetab verify
        if ($shetabVerify->status == 'pending') {
            // return response()->json(['message' => 'Shetab verify is pending'], 200);
            // find the user and update the balance
            // update the status of the shetab verify to verified
            $shetabVerify->status = 'verified';
            $shetabVerify->save();
            $telegramService = new TelegramService();
            $user = User::find($shetabVerify->user_id);
            // add to account ballance
            $accountBallanceCtrl = new AccountBallanceController();
            $accountBallanceCtrl->incUserAccuntBalance($user->account_id, $amount);
            $text = $this->customTextCtrl->getText('action.account.balance_added', ['amount' => $amount]);
            $telegramService->sendMessage($user->account_id, $text);
            // add log
            $this->addNewBotLog('shetab_verify', 'شارژ کیف پول از طریق کارت به کارت (شتاب)', $user->account_id, 'shetab_verify');
            // send message to admin
            $admin = User::where('role', 'admin')->first();
            $text = "شارژ کیف پول از طریق کارت به کارت (شتاب) بوسیله کاربر {$user->account_id} با مبلغ {$amount} تومان انجام شد.";
            $telegramService->sendMessage($admin->account_id, $text);
            return response()->json(['message' => 'Shetab verify is verified'], 200);
        }
        return response()->json(['message' => 'Shetab verify is not verified'], 400);
    }
    private function addNewBotLog($type, $message, $chatId, $opr)
    {
        $logCtrl = new LogController();
        $logCtrl->addNewLog($type, $message, $chatId, "", $opr);
        return true;
    }
}
