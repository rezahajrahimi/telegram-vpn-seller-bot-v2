<?php

namespace App\Http\Controllers;
use Shetabit\Multipay\Invoice;
use Shetabit\Payment\Facade\Payment;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use SoapClient;
use Illuminate\Support\Facades\Config;

use App\Models\Transaction;
use App\Models\PaymentType;
use App\Models\CryptoPayment;
use App\Models\TransactionImage;

use Illuminate\Http\Request;
class TransactionController extends Controller
{
    public $account_id;
    public $amount;
    public $amount_dollar;
    public function add_order(Request $request)
    {
        $pymntCntrrl = new PaymentTypeController();

        config::set('payment.drivers.zarinpal.merchantId', $pymntCntrrl->getZarinpalMerchantID());

        $settingCntrl = new SettingController();
        $mainUrl = $settingCntrl->getMainUrl();

        config::set('payment.drivers.zarinpal.callbackUrl', "{$mainUrl}/order");

        $value = config('payment.drivers.zarinpal.merchantId');
        //get amount from bill
        $bill = new BillController();
        $this->amount = $bill->getBillAmountByBillId($request->invoiceID);
        $this->account_id = $request->account_id;
        if ($this->amount != null) {
            // Create new invoice.
            $invoice = (new Invoice())->amount($this->amount);
            return Payment::purchase($invoice, function ($driver, $transactionId) {
                $pymntCntrrl = new PaymentTypeController();

                $zarinPalId = $pymntCntrrl->getZarinpalTableID();
                $this->addUserTranaction($this->account_id, $this->amount, $transactionId, $zarinPalId);
            })
                ->pay()
                ->render();
        } else {
            return 'این صورتحساب موجود نمی باشد.';
        }
    }
    public function order(Request $request)
    {
        try {
            $transaction_id = $request->transaction_id;
            $status = $request->status;

            $amount = $this->getAmountByRecipeNUmber($transaction_id);

            $receipt = Payment::amount($amount)->transactionId($transaction_id)->verify();
            // confirm transaction
            $this->setConfirmedTransaction($transaction_id);
            // add to user account balance.

            $accBlCtrl = new AccountBallanceController();
            $userID = $this->getUserAccountIDByTransactionId($transaction_id);
            $accBlCtrl->incUserAccuntBalance($userID, $amount);
            return 'پرداخت با موفقیت انجام شد. می توانید این پنجره را ببندید و برای ادامه خرید به تلگرام برگردید.';
        } catch (InvalidPaymentException $exception) {
            return 'خطا در انجام عملیات';
        }
    }
    public function getUserTranaction($userID)
    {
        $data = Transaction::where('account_id', $userID)->get();
        if ($data != null) {
            return $data;
        } else {
            return null;
        }
    }
    public function addUserTranaction($userID, $amount, $recipeNUmber, $paymentTypeId)
    {
        if ($paymentTypeId == 0 || $paymentTypeId == null) {
            $pay = PaymentType::where('is_active', true)->where('type', 'offline')->first();

            $paymentTypeId = $pay->id;
        }
        $transaction = new Transaction();
        $transaction->account_id = $userID;
        $transaction->username = '';
        $transaction->amount = $amount;
        $transaction->recipe_number = $recipeNUmber;
        $transaction->payment_type_id = $paymentTypeId;
        $transaction->save();
        // check user have referral, if has create referral log
        $referralLogsCntrl = new ReferralLogsController();
        $hasRef = $referralLogsCntrl->check_user_is_referred($userID);

        if ($hasRef == true) {
            // get amount from referralsetting and calculate by percent stored in db
            $referralSettingCntrl = new ReferralSettingController();
            $referral_percent = $referralSettingCntrl->get_referral_setting_referral_percent();
            $amount = 0;
            if ($referral_percent !== null || $referral_percent !== 0) {
                $amount = ($transaction->amount / 100) * $referral_percent;
            }

            $referReq = new Request();
            $referReq->referral_to_id = $userID;

            $referReq->amount = $amount;
            $referReq->transaction_id = $transaction->id;

            $referralLogsCntrl->add_new_referral_logs($referReq);
        }

        return $transaction->id;
    }
    public function removeUnconfirmedTransaction($id)
    {
        try {
            $transaction = Transaction::find($id);
            if ($transaction->confirmed == false || $transaction->confirmed == 0) {
                // remove transaction image on disk
                $transactionImage = TransactionImage::where('transaction_id', $id)->first();
                if ($transactionImage != null) {
                    $path = public_path() . '' . $transactionImage->img_src;
                    if (file_exists($path)) {
                        unlink($path);
                    }
                }

                $transaction->delete();
                return response()->json(true, 200);
            } else {
                return response()->json(false, 401);
            }
        } catch (\Throwable $th) {
            \Log::info("$th");

            return response()->json('NO Data Founded', 404);
        }
    }
    public function editUserTranaction(Request $request)
    {
        try {
            $transaction = Transaction::find($request->id);
            if ($transaction != null) {
                $isConfirmed = $request->confirmed == 1 ? true : false;

                if ($transaction->amount != $request->amount && $isConfirmed == true) {
                    $accBlCtrl = new AccountBallanceController();
                    if ($transaction->amount > $request->amount) {
                        $accBlCtrl->decUserAccuntBalance($transaction->account_id, $transaction->amount - $request->amount);
                    } else {
                        $accBlCtrl->incUserAccuntBalance($transaction->account_id, $request->amount - $transaction->amount);
                    }

                    $transaction->amount = $request->amount;
                }
                $transaction->recipe_number = $request->recipeNUmber;
                $transaction->payment_type_id = $request->paymentTypeId;
                $transaction->confirmed = $isConfirmed;

                if ($transaction->update()) {
                    if ($isConfirmed) {
                        $result = app('telegram_bot')->sendMessage("تراکنش شما با موفقیت ثبت شد و مبلغ {$transaction->amount} به حساب شما افزوده شد.", $transaction->account_id, null, 'MarkDown');
                        // set referral wallet
                        $referralLogsCntrl = new ReferralLogsController();
                        $referralLogsCntrl->add_amount_to_refrerral_user_Log_and_referral_wallet($transaction->id);
                    } else {
                        $result = app('telegram_bot')->sendMessage('تراکنش شما مورد تایید نمی باشد.', $transaction->account_id, null, 'MarkDown');
                        // set referral wallet
                        $referralLogsCntrl = new ReferralLogsController();
                        $referralLogsCntrl->decrease_amount_to_refrerral_user_Log_and_referral_wallet($transaction->id);
                    }

                    return response()->json($transaction, 200);
                } else {
                    \Log::info('Failed to update transaction');

                    return response()->json($transaction, 404);
                }
            } else {
                \Log::info('NO Data Founded');

                return response()->json('NO Data Founded', 404);
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable  $th");
        }
    }
    public function getAmountByRecipeNUmber($recipeNUmber)
    {
        $data = Transaction::where('recipe_number', $recipeNUmber)->first();
        if ($data != null) {
            return $data->amount;
        } else {
            return 0;
        }
    }
    public function setConfirmedTransaction($recipeNUmber)
    {
        $data = Transaction::where('recipe_number', $recipeNUmber)->first();
        if ($data != null) {
            $data->confirmed = true;
            $data->update();


            $result = app('telegram_bot')->sendMessage("تراکنش شما با موفقیت ثبت شد و مبلغ {$transaction->amount} به حساب شما افزوده شد.", $transaction->account_id, null, 'MarkDown');
            // set referral wallet
            $referralLogsCntrl = new ReferralLogsController();
            $referralLogsCntrl->add_amount_to_refrerral_user_Log_and_referral_wallet($data->id);

            return true;
        } else {
            return false;
        }
    }
    public function getUserAccountIDByTransactionId($recipeNUmber)
    {
        $data = Transaction::where('recipe_number', $recipeNUmber)->first();
        if ($data != null) {
            return $data->account_id;
        } else {
            return null;
        }
    }

    public function getConfirmedTransactions($count = 10)
    {
        try {
            return Transaction::where('confirmed', true)
                ->with(['payment_types', 'transaction_image', 'user'])
                ->take($count)
                ->orderBy('id', 'desc')
                ->get();
        } catch (\Throwable $th) {
            return response()->json($data, 404);
        }
    }
    public function getUnConfirmedTransactions($count = 10)
    {
        try {
            return Transaction::where('confirmed', false)
                ->with(['payment_types', 'transaction_image', 'user'])
                ->take($count)
                ->orderBy('id', 'desc')
                ->get();
        } catch (\Throwable $th) {
            return response()->json($data, 404);
        }
    }
}
