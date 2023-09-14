<?php
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ServiceTypeController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\MainMenuItemController;
use App\Http\Controllers\PaymentTypeController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
Route::prefix('telegram/webhooks')->group(function () {
    // Route::post('inbound',function(Request $request){
    //     \Log::info($request->all());
    // });

    Route::post('inbound', [TelegramController::class, 'inbound'])->name('telegram.inbound');
});
// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
Route::get('getUserOrder/{userID}', [OrderController::class, 'getUserOrder']);
Route::get('getServiceTypes', [ServiceTypeController::class, 'getServiceTypes']);
Route::get('getAllProdctCategort/{servicetypeID}', [ProductCategoryController::class, 'getAllProdctCategort']);
Route::post('addNewProductCategory', [ProductCategoryController::class, 'addNewProductCategory']);
Route::get('getActiveProductsByProductCatID/{selectedProductCatID}', [ProductController::class, 'getActiveProductsByProductCatID']);
Route::post('addNewProductDetails', [ProductController::class, 'addNewProductDetails']);


//Settings
Route::get('getBotSetting', [SettingController::class, 'getBotSetting']);
Route::post('updateBotSetting', [SettingController::class, 'updateBotSetting']);

// menu items
Route::get('getAllMainMenuItems', [MainMenuItemController::class, 'getAllMainMenuItems']);
Route::get('getAllActivatedMainMenuItems', [MainMenuItemController::class, 'getAllActivatedMainMenuItems']);
Route::get('deActiveMainMenuItem/{name}', [MainMenuItemController::class, 'deActiveMainMenuItem']);
Route::get('reActiveMainMenuItem/{name}', [MainMenuItemController::class, 'reActiveMainMenuItem']);
Route::post('changeMainMenuAliasName', [MainMenuItemController::class, 'changeMainMenuAliasName']);
Route::post('changeMainMenuPosition', [MainMenuItemController::class, 'changeMainMenuPosition']);

// payment type
Route::get('getPaymentTypes', [PaymentTypeController::class, 'getPaymentTypes']);
Route::get('getPaymentAddressByPaymentName/{name}', [PaymentTypeController::class, 'getPaymentAddressByPaymentName']);
Route::get('isPaymentType/{name}', [PaymentTypeController::class, 'isPaymentType']);
Route::get('getAllOnlinePayments', [PaymentTypeController::class, 'getAllOnlinePayments']);
Route::get('getAllOfflinePayments', [PaymentTypeController::class, 'getAllOfflinePayments']);
Route::get('getZarinpalPaymentDetails', [PaymentTypeController::class, 'getZarinpalPaymentDetails']);
Route::post('createNewPaymentType', [PaymentTypeController::class, 'createNewPaymentType']);
Route::get('getAllActivePaymentTypes', [PaymentTypeController::class, 'getAllActivePaymentTypes']);
Route::get('deActivePaymentType/{name}', [PaymentTypeController::class, 'deActivePaymentType']);
Route::get('reActivePaymentType/{name}', [PaymentTypeController::class, 'reActivePaymentType']);
Route::get('removePaymentType/{name}', [PaymentTypeController::class, 'removePaymentType']);
Route::post('chanegeMerChantIdByPaymentTypeName', [PaymentTypeController::class, 'chanegeMerChantIdByPaymentTypeName']);
