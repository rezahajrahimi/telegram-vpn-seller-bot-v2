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
            // $transaction_id = 1212984;
            $transaction_id = $receipt->getReferenceId();
            \Log::info("VV receipt $receipt");
            \Log::info("VV transaction_id $transaction_id");

            $amount = $this->getAmountByRecipeNUmber($transaction_id);
            \Log::info("VV amount $amount");

            $receipt = Payment::amount(1000)
                ->transactionId($transaction_id)
                ->verify();

            // You can show payment referenceId to the user.
            // echo $receipt->getReferenceId();
            \Log::info($receipt->getReferenceId());

            return 'پرداخت با موفقیت انجام شد. می توانید این پنجره را ببندید و برای ادامه خرید به تلگرام برگردید.';
        } catch (InvalidPaymentException $exception) {
            \Log::info($exception->getMessage());

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
}
