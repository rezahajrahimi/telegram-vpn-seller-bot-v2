<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TransactionController;
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

Route::get('order', function (Request $request) {
    return redirect()->action([TransactionController::class, 'order'], ['transaction_id' => $request->Authority, 'status' => $request->Status]);
});

Route::post('shop', [TransactionController::class, 'add_order']);
