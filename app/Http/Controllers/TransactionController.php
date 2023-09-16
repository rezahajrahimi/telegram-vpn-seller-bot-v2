<?php

namespace App\Http\Controllers;
use Shetabit\Multipay\Invoice;
use Shetabit\Payment\Facade\Payment;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use SoapClient;
use Illuminate\Support\Facades\Config;

use App\Models\Transaction;

use Illuminate\Http\Request;
class TransactionController extends Controller
{
    public $account_id;
    public $amount;

    public function add_order(Request $request)
    {
        $pymntCntrrl = new PaymentTypeController();

        \Log::info($request->all());
        \Log::info($request->invoiceID);

        config::set('payment.drivers.zarinpal.merchantId', $pymntCntrrl->getZarinpalMerchantID());

        $value = config('payment.drivers.zarinpal.merchantId');
        //get amount from bill
        $bill = new BillController();
        $this->amount = $bill->getBillAmountByBillId($request->invoiceID);
        \Log::info("aaaaaa  $this->amount");
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

            $receipt = Payment::amount($amount)
                ->transactionId($transaction_id)
                ->verify();
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
        $transaction = new Transaction();
        $transaction->account_id = $userID;
        $transaction->username = '';
        $transaction->amount = $amount;
        $transaction->recipe_number = $recipeNUmber;
        $transaction->payment_type_id = $paymentTypeId;
        return $transaction->save();
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
}
