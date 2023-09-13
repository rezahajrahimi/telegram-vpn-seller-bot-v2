<?php

use Illuminate\Support\Facades\Route;

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
Route::get('buy/{price}/{invoiceID}',function($price,$invoiceID){
    return view('shop',['price'=>$price , 'invoiceID'=>$invoiceID]);
});

// Route::get('order','siteController@order');
// Route::post('shop','siteController@add_order');
