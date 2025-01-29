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
use App\Models\Bill;

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
        $bill = Bill::where('bill_id', $request->invoiceID)->first();

        $this->amount = $bill->amount;
        $this->account_id = $request->account_id;
        if ($this->amount != null) {
            // Create new invoice.
            // getzarinpal merchent id from .env
            $zarinpalMerchentID = PaymentType::where('name', 'زرین پال')->first()->merchant_id;
            if ($zarinpalMerchentID == null) {
                return 'ZARINPAL_MERCHANT_ID is not set in .env';
            }
            $callbackUrl = $mainUrl . '/order';

            $response = zarinpal()
                ->merchantId($zarinpalMerchentID) // تعیین مرچنت کد در حین اجرا - اختیاری
                ->amount($this->amount) // مبلغ تراکنش
                ->request()
                ->description('خرید کالا') // توضیحات تراکنش
                ->callbackUrl($callbackUrl) // آدرس برگشت پس از پرداخت
                ->send();
            if (!$response->success()) {
                return $response->error()->message();
            }
            $authority = $response->authority();

            // save authority in db as new bill transaction_id
            // create a new transaction
            $transaction = new Transaction();
            $transaction->account_id = $this->account_id;
            $transaction->username = '';
            $transaction->amount = $this->amount;
            $transaction->confirmed = 0;
            $transaction->recipe_number = $authority;
            $transaction->payment_type_id = PaymentType::where('name', 'زرین پال')->first()->id;

            $transaction->save();

            $result = ['success' => $response->redirect()];
            // \Log::info('add_order', ['result' => $result]);

            $link = 'https://www.zarinpal.com/pg/StartPay/' . $authority;

            return $link;
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

            //  $receipt = Payment::amount($amount)->transactionId($transaction_id)->verify();
            // confirm transaction
            // get transaction with $transaction_id
            $transaction = Transaction::where('recipe_number', $transaction_id)->first();
            // check if transaction was confirmed before so return it's confirmed status

            if ($transaction->confirmed == true) {
                return 'تراکنش تکراری می باشد.';
            }
            // if ($status !== 'OK') {
            //     return 'تراکنش ناموفق می باشد.';
            // }

            $authority = $transaction_id; // دریافت کوئری استرینگ ارسال شده توسط زرین پال
            $zarinpalMerchentID = PaymentType::where('name', 'زرین پال')->first()->merchant_id;

            $response = zarinpal()
                ->merchantId($zarinpalMerchentID) // تعیین مرچنت کد در حین اجرا - اختیاری
                ->amount($amount)
                ->verification()
                ->authority($authority)
                ->send();

            if (!$response->success()) {
                return $response->error()->message();
            }

            $confirmReq = new Request();
            $confirmReq->id = $transaction->id;
            $confirmReq->confirmed = 1;
            $confirmReq->amount = $transaction->amount;
            $confirmReq->account_id = $transaction->account_id;
            $confirmReq->recipeNUmber = $transaction->recipe_number;
            $confirmReq->paymentTypeId = $transaction->payment_type_id;
            $confirmReq->isPaymntBack = true;

            $this->editUserTranaction($confirmReq);

            return 'پرداخت با موفقیت انجام شد. می توانید این پنجره را ببندید.';
        } catch (InvalidPaymentException $exception) {
            $transaction_id = $request->transaction_id;

            $transaction = Transaction::where('recipe_number', $transaction_id)->first();

            $this->removeUnconfirmedTransaction($transaction->id);
            \Log::info("back from zarinpal $exception");
            // $this->removeUnconfirmedTransaction($transaction_id);
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
        try {
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
        $hasRef = $referralLogsCntrl->check_user_is_referred($transaction->account_id);

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
        } catch (\Throwable $th) {
            \Log::info("Throwable  $th");
        }
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
                $isConfirmed = $request->confirmed == 1 || $request->confirmed == true ? true : false;

                // if ($transaction->amount != $request->amount && $isConfirmed == true) {
                if ($isConfirmed == true) {
                    $accBlCtrl = new AccountBallanceController();
                    if ($transaction->amount > $request->amount) {
                        $accBlCtrl->decUserAccuntBalance($transaction->account_id, $transaction->amount - $request->amount);
                    } else {
                        $accBlCtrl->incUserAccuntBalance($transaction->account_id, $request->amount);
                    }

                    $transaction->amount = $request->amount;
                }
                $transaction->recipe_number = $request->recipeNUmber;
                $transaction->payment_type_id = $request->paymentTypeId;
                $transaction->confirmed = $isConfirmed;

                if ($transaction->update()) {
                    $referralLogsCntrl = new ReferralLogsController();
                    $referralSettingCntrl = new ReferralSettingController();

                    $referral_percent = $referralSettingCntrl->get_referral_setting_referral_percent();
                    $amount = 0;
                    if ($referral_percent !== null || $referral_percent !== 0) {
                        $amount = ($transaction->amount / 100) * $referral_percent;
                    }
                    if ($isConfirmed) {
                        $result = app('telegram_bot')->sendMessage("تراکنش شما با موفقیت ثبت شد و مبلغ {$transaction->amount} به حساب شما افزوده شد.", $transaction->account_id, null, 'MarkDown');
                        // set referral wallet
                        if ($request->isPaymntBack == true) {
                            $referralLogsCntrl->add_amount_to_refrerral_user_Log_and_referral_wallet($transaction->id, $amount, true);
                        } else {
                            $referralLogsCntrl->add_amount_to_refrerral_user_Log_and_referral_wallet($transaction->id, $amount, false);
                        }
                    } else {
                        $result = app('telegram_bot')->sendMessage('تراکنش شما مورد تایید نمی باشد.', $transaction->account_id, null, 'MarkDown');
                        // set referral wallet
                        $referralLogsCntrl = new ReferralLogsController();
                        $referralLogsCntrl->decrease_amount_to_refrerral_user_Log_and_referral_wallet($transaction->id, $amount);
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

            $result = app('telegram_bot')->sendMessage("تراکنش شما با موفقیت ثبت شد و مبلغ {$data->amount} به حساب شما افزوده شد.", $data->account_id, null, 'MarkDown');
            // set referral wallet
            $referralLogsCntrl = new ReferralLogsController();
            $referralLogsCntrl->add_amount_to_refrerral_user_Log_and_referral_wallet($data->id, $data->amount);

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
