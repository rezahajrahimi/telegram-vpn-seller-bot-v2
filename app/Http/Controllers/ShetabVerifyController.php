<?php
namespace App\Http\Controllers;

use App\Models\PaymentSetting;
use App\Models\PaymentType;
use App\Models\ShetabVerify;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
class ShetabVerifyController extends Controller
{
    private PaymentSettingController $paymnetSettingCntrl;
    public function __construct()
    {
        $this->middleware('auth');
        $this->paymnetSettingCntrl = new PaymentSettingController();
    }
    public function check_shetab_verify_status()
    {
        $authCntrl             = new AuthController();
        $getPowerPsLicenseType = $authCntrl->getPowerPsLicenseType();
        if ($getPowerPsLicenseType == 'free') {
            \Log::info('You are not authorized to check the shetab verify status');
            return false;
        }

        $shetabVerify = $this->paymnetSettingCntrl->getPaymentSettingStatusByKey('shetab_verify');
        return $shetabVerify;
    }
    public function create_new_shetab_verify(Request $request)
    {
        // check license type in auth controller
        $authCntrl             = new AuthController();
        $getPowerPsLicenseType = $authCntrl->getPowerPsLicenseType();
        if ($getPowerPsLicenseType == 'free') {
            return response()->json(['message' => 'You are not authorized to create a new shetab verify'], 401);
        }
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
        $api_key = $request->header('api_key');
        if (! $api_key) {
            return response()->json(['message' => 'Api key is required'], 401);
        }
        // validate api_key
        $api_key = PaymentSetting::where('key', 'shetab_verify_api_key')->first()->value;
        if (! $api_key) {
            return response()->json(['message' => 'Api key is invalid'], 401);
        }
        $amount = $request->amount;
        // check the row in shetab_verifies table with amount and status is pending and created_at is less than 10 minutes ago
        $shetabVerify = ShetabVerify::where('amount', $amount)->where('status', 'pending')->where('created_at', '>', now()->subMinutes(10))->first();
        if (! $shetabVerify) {
            return response()->json(['message' => 'Shetab verify not found'], 404);
        }
        // check the status of the shetab verify
        if ($shetabVerify->status == 'pending') {
            // return response()->json(['message' => 'Shetab verify is pending'], 200);
            // find the user and update the balance
            $user = User::find($shetabVerify->user_id);
            $user->balance += $amount;
            $user->save();
            // update the status of the shetab verify to verified
            $shetabVerify->status = 'verified';
            $shetabVerify->save();
        }
    }
}
