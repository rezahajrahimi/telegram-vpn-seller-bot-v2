<?php

namespace App\Http\Controllers;

use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionCryptoController;
use Illuminate\Http\Request;

class WebViewController extends Controller
{
    public function welcome()
    {
        return view('welcome');
    }

    public function shop(string $account_id, string $invoiceID, string $price)
    {
        return view('shop', [
            'account_id' => $account_id,
            'invoiceID' => $invoiceID,
            'price' => $price,
        ]);
    }

    public function orderRedirect(Request $request)
    {
        return redirect()->action(
            [TransactionController::class, 'order'],
            ['transaction_id' => $request->Authority, 'status' => $request->Status],
        );
    }

    public function cryptoPayment(string $account_id, string $invoiceID, string $price)
    {
        return view('crypto', [
            'account_id' => $account_id,
            'invoiceID' => $invoiceID,
            'price' => $price,
        ]);
    }

    public function payback()
    {
        $transaction_id = request()->query('NP_id');

        return redirect()->action(
            [TransactionCryptoController::class, 'orderSuccess'],
            ['transaction_id' => $transaction_id],
        );
    }

    public function cancelPay()
    {
        return 'پرداخت شما لغو شد.';
    }
}
