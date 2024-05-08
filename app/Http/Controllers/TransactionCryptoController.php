<?php

namespace App\Http\Controllers;

use App\Models\TransactionCrypto;
use Illuminate\Http\Request;
use App\Models\CryptoPayment;
use Illuminate\Support\Facades\Config;
use PrevailExcel\Nowpayments\Facades\Nowpayments;

class TransactionCryptoController extends Controller
{
    public $amount_dollar;
    public $account_id;

    public function changeNovaPaymentData()
    {
        $cryptoPaymentCtrl = new CryptoPaymentController();
        $nowpayment = $cryptoPaymentCtrl->getNovPaymentData();

        config::set('nowpayments.apiKey', $nowpayment->api_key);
        config::set('nowpayments.liveUrl', $nowpayment->env);
        return;
    }
    public function add_order_crypto_by_nowpayment(Request $request)
    {
        $cryptoPaymentCtrl = new CryptoPaymentController();
        $nowpayment = $cryptoPaymentCtrl->getNovPaymentData();
        $this->changeNovaPaymentData();
        //get amount from bill
        $bill = new BillController();
        $this->amount_dollar = $bill->getBillAmountDollarByBillId($request->invoiceID);
        \Log::info("aaaaaa  $this->amount_dollar");
        $this->account_id = $request->account_id;
        if ($this->amount_dollar != null) {
            // Create new invoice.
            $cryptoPaymentCntrl = new CryptoPaymentController();
            $npwPaymentCntrl = new NowPaymentsController();
            $req = new Request();
            $req->amount = $this->amount_dollar;
            $req->order_id = $request->invoiceID;
            $req->order_description = "invoice {$request->invoiceID}";
            $req->ipn_callback_url = $nowpayment->ipn_callback_url;
            $req->success_url = $nowpayment->success_url;
            $req->cancel_url = $nowpayment->cancel_url;
            $req->is_fixed_rate = $nowpayment->is_fixed_rate;
            $req->is_fee_paid_by_user = $nowpayment->is_fee_paid_by_user;
            // save created transaction
            $transactionCrypto = new TransactionCrypto();
            $transactionCrypto->account_id = $this->account_id;
            $transactionCrypto->username = '';

            $transactionCrypto->crypto_payment_id = $cryptoPaymentCntrl->getNowPaymentID();
            $transactionCrypto->amount_dollar = $this->amount_dollar;
            $transactionCrypto->confirmed = false;
            $transactionCrypto->recipe_number = $request->invoiceID;
            $transactionCrypto->save();
            return $npwPaymentCntrl->createCryptoInvoice($req);
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
    public function getPaymentStatus($transactionID)
    {
        $data =  Nowpayments::getPaymentStatus($transactionID);
        return $data["payment_status"];
    }

}
