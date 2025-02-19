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
        //  https://nowpayments.io/?NP_id=4771846894

        $cryptoPaymentCtrl = new CryptoPaymentController();
        $nowpayment = $cryptoPaymentCtrl->getNovPaymentData();
        $this->changeNovaPaymentData();
        //get amount from bill
        $bill = new BillController();
        $this->amount_dollar = $bill->getBillAmountDollarByBillId($request->invoiceID);
        $this->account_id = $request->account_id;
        if ($this->amount_dollar != null) {

            // set nowpayments env

            // Create new invoice.
            $cryptoPaymentCntrl = new CryptoPaymentController();
            $npwPaymentCntrl = new NowPaymentsController();
            $req = new Request();
            $req->amount = $this->amount_dollar;
            $req->order_id = $request->invoiceID;
            $req->order_description = "invoice {$request->invoiceID}";

            // get payment back url

            $settingCntrl = new SettingController();
            $mainUrl = $settingCntrl->getMainUrl();

            //
            $req->ipn_callback_url = "$mainUrl/payback/";
            // $req->ipn_callback_url = $nowpayment->ipn_callback_url;
            $req->success_url = "$mainUrl/payback/";
            // $req->success_url = $nowpayment->success_url;
            $req->cancel_url = "$mainUrl/cancelpay/";
            $req->is_fixed_rate = $nowpayment->is_fixed_rate;
            $req->is_fee_paid_by_user = $nowpayment->is_fee_paid_by_user;
            // save created transaction
            $transactionCrypto = new TransactionCrypto();
            $transactionCrypto->account_id = $this->account_id;
            $transactionCrypto->username = '';

            $transactionCrypto->crypto_payment_id = $cryptoPaymentCntrl->getNowPaymentID();
            $transactionCrypto->amount_dollar = $this->amount_dollar;
            $transactionCrypto->confirmed = false;
            $transactionCrypto->order_id = $request->invoiceID;
            $transactionCrypto->save();
            return $npwPaymentCntrl->createCryptoInvoice($req);
        } else {
            return 'این صورتحساب موجود نمی باشد.';
        }
    }
    public function orderSuccess(Request $request)
    {
        try {
            $transaction_id = $request->transaction_id;
            $status = $request->status;

            if (!$this->isvalidPayment($transaction_id)) {
                return 'تراکنش معتبر نمی باشد.';
            }

            // add to user account balance.

            $accBlCtrl = new AccountBallanceController();
            $userID = $this->getUserAccountIDByRecipeId($transaction_id);
            $amount = $this->getOrderIdByRecipeNumber($transaction_id);
            \Log::info("$userID \  $amount");
            $accBlCtrl->incUserAccuntBalanceInDollar($userID, $amount);

            // send message to user
            $text = '';
            $text .= "✅شارژ با موفقیت انجام شد✅ \r\n";

            $text .= "مبلغ {$amount} دلار به حساب شما افزوده شد. \r\n";
            $resualt = app('telegram_bot')->sendMessage($text, $userID, null, 'MarkDown');
            //////////////////

            return $this->orderSuccessMessage();
        } catch (InvalidPaymentException $exception) {
            return 'خطا در انجام عملیات';
        }
    }
    public function getAmountByOrderID($order_id)
    {
        $data = TransactionCrypto::where('order_id', $order_id)->first();
        \Log::info($data);

        if ($data != null) {
            return $data->amount_dollar;
        } else {
            return 0;
        }
    }
    public function setConfirmedTransaction($recipe_number, $order_id)
    {
        $data = TransactionCrypto::where('order_id', $order_id)->first();
        if ($data != null) {
            $data->recipe_number = $recipe_number;
            $data->confirmed = true;
            $data->update();
            return true;
        } else {
            return false;
        }
    }
    public function getUserAccountIDByRecipeId($recipe_number)
    {
        $data = TransactionCrypto::where('recipe_number', $recipe_number)->first();
        if ($data != null) {
            return $data->account_id;
        } else {
            return null;
        }
    }
    public function isvalidPayment($transactionID)
    {
        // http://localhost:8000/payback/?NP_id=4671586017

        $data = Nowpayments::getPaymentStatus($transactionID);
        \Log::info($data);
        $status = $data['payment_status'];
        $recived_amount = $data['price_amount'];
        $order_id = $data['order_id'];
        \Log::info("order_id $order_id");

        $amount = $this->getAmountByOrderID($order_id);

        \Log::info("$recived_amount // $amount");

        if ($status == 'finished' && $recived_amount == $amount) {
            // confirm transaction
            $this->setConfirmedTransaction($transactionID, $order_id);

            return true;
        }
        return false;
    }
    public function getOrderIdByRecipeNumber($recipe_number)
    {
        try {
            $data = TransactionCrypto::where('recipe_number', $recipe_number)->first();
            return $data->order_id;
        } catch (\Throwable $th) {
            \Log::info("Throwable $th");
            return null;
        }
    }
    public function orderSuccessMessage()
    {
        // retunr a html page with success purchess message
        return '<html>
                <head>
                    <link href="https://fonts.googleapis.com/css?family=Nunito+Sans:400,400i,700,900&display=swap" rel="stylesheet">
                </head>
                    <style>
                    body {
                        text-align: center;
                        padding: 40px 0;
                        background: #EBF0F5;
                    }
                        h1 {
                        color: #88B04B;
                        font-family: "Nunito Sans", "Helvetica Neue", sans-serif;
                        font-weight: 900;
                        font-size: 40px;
                        margin-bottom: 10px;
                        }
                        p {
                        color: #404F5E;
                        font-family: "Nunito Sans", "Helvetica Neue", sans-serif;
                        font-size:20px;
                        margin: 0;
                        }
                    i {
                        color: #9ABC66;
                        font-size: 100px;
                        line-height: 200px;
                        margin-left:-15px;
                    }
                    .card {
                        background: white;
                        padding: 60px;
                        border-radius: 4px;
                        box-shadow: 0 2px 3px #C8D0D8;
                        display: inline-block;
                        margin: 0 auto;
                    }
                    </style>
                    <body>
                    <div class="card">
                    <div style="border-radius:200px; height:200px; width:200px; background: #F8FAF5; margin:0 auto;">
                        <i class="checkmark">✓</i>
                    </div>
                        <h1>Success</h1>
                        <p>پرداخت شما با موفقیت انجام شد<br/> </p>
                    </div>
                    </body>
             </html>';
    }
    public function getCurrentUrl()
    {
        // $currentUrlWithoutQuery = request()->url();
        // $host = parse_url($currentUrlWithoutQuery, PHP_URL_HOST);

        // // Extract the subdomain
        // $subdomain = explode('.', $host)[0];

        // // Combine the subdomain with the desired domain
        // $domain = 'google.com';
        // $finalUrl = "https://$subdomain.$domain";

        return request()->getHttpHost();
    }
}
