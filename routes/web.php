<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionCryptoController;
use App\Http\Controllers\ExecuteArtisanCommandController;
use App\Http\Controllers\CryptomusController; // Added this line
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::get('buy/{account_id}/{invoiceID}/{price}', function ($account_id, $invoiceID, $price) {
    return view('shop', ['account_id' => $account_id, 'invoiceID' => $invoiceID, 'price' => $price]);
});
Route::post('shop', [TransactionController::class, 'add_order'])->name('shop.submit'); // for zarinpal

Route::get('order', function (Request $request) {

    return redirect()->action([TransactionController::class, 'order'], ['transaction_id' => $request->Authority, 'status' => $request->Status]);
});

// Laravel 8 & 9
Route::get('/pay', [App\Http\Controllers\NowPaymentsController::class, 'createCryptoInvoice'])->name('pay');
// Route::post('/pay', [App\Http\Controllers\NowPaymentsController::class, 'createCryptoPayment'])->name('pay');

Route::get('cryptopayment/{account_id}/{invoiceID}/{price}', function ($account_id, $invoiceID, $price) {
    return view('crypto', ['account_id' => $account_id, 'invoiceID' => $invoiceID, 'price' => $price]);
 });
 
 Route::post('cryptogateway', [TransactionCryptoController::class, 'initiateCryptoPayment'])->name('crypto.initiate'); // Changed target method
 // back mowpayments
 // Route::post('/orderSuccess', [App\Http\Controllers\TransactionCryptoController::class, 'orderSuccess']);

Route::get('/payback', function () {
    $transaction_id = request()->query('NP_id');
    // Now you can use $npId in your logic
    return redirect()->action([TransactionCryptoController::class, 'orderSuccess'], ['transaction_id' => $transaction_id]);
});
Route::get('/cancelpay', function () {
    return "پرداخت شما لغو شد.";
});

// Cryptomus Routes
Route::post('/cryptomus/create', [CryptomusController::class, 'createPayment'])->name('cryptomus.create');
Route::post('/cryptomus/callback', [CryptomusController::class, 'handleCallback'])->name('cryptomus.callback'); // Needs CSRF exemption
Route::get('/payment/success', [CryptomusController::class, 'paymentSuccess'])->name('payment.success');
Route::get('/payment/return', [CryptomusController::class, 'paymentReturn'])->name('payment.return');


// run command by url
Route::get('/run-command/{name_of_command}', ExecuteArtisanCommandController::class);
