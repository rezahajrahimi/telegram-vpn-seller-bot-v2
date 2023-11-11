<?php
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ServiceTypeController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\MainMenuItemController;
use App\Http\Controllers\PaymentTypeController;
use App\Http\Controllers\PaymentMenuItemController;

use App\Http\Controllers\TransactionController;
use App\Http\Controllers\GiftCardMenuItemController;
use App\Http\Controllers\GiftCardController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\ChannelLockMenuItemController;
use App\Http\Controllers\ChannelLockController;
use App\Http\Controllers\PannelController;
use App\Http\Controllers\ProxyController;
use App\Http\Controllers\InboundController;
use App\Http\Controllers\BotUserController;


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


//  ProductCategory
Route::get('getAllProdctCategory', [ProductCategoryController::class, 'getAllProdctCategory']);
Route::get('getProdctPrice', [ProductCategoryController::class, 'getProdctPrice']);
Route::get('getProdctPannelID/{name}/pannelID', [ProductCategoryController::class, 'getProdctPannelID']);
Route::post('addNewProductCategory', [ProductCategoryController::class, 'addNewProductCategory']);
Route::post('editProductCategory', [ProductCategoryController::class, 'editProductCategory']);
Route::get('reActiveProductCategory/{id}', [ProductCategoryController::class, 'reActiveProductCategory']);
Route::get('deActiveProductCategory/{id}', [ProductCategoryController::class, 'deActiveProductCategory']);



//ProductController
Route::get('getActiveProductsByProductCatID/{selectedProductCatID}', [ProductController::class, 'getActiveProductsByProductCatID']);
Route::post('addNewProductDetails', [ProductController::class, 'addNewProductDetails']);
Route::get('deleteProduct/{id}', [ProductController::class, 'deleteProduct']);
Route::get('getLastBuyersByCatIdAndCount/{id}/{count}', [ProductController::class, 'getLastBuyersByCatIdAndCount']);
Route::get('getCountOfProductSelledSummeryByCatID/{id}', [ProductController::class, 'getCountOfProductSelledSummeryByCatID']);


//Settings
Route::get('getBotSetting', [SettingController::class, 'getBotSetting']);
Route::get('getBotToken', [SettingController::class, 'getBotToken']);
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
Route::get('getAllActiveOfflinePaymentTypes', [PaymentTypeController::class, 'getAllActiveOfflinePaymentTypes']);
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

// paymenyt type menu
Route::get('getPaymentTypeMainMenuTitle', [PaymentMenuItemController::class, 'getPaymentTypeMainMenuTitle']);
Route::get('getAllPaymentTypeMenues', [PaymentMenuItemController::class, 'getAllPaymentTypeMenues']);
Route::post('updatePaymentMenuAlisNameByLevel', [PaymentMenuItemController::class, 'updatePaymentMenuAlisNameByLevel']);


// TransactionController && online payment

Route::get('/order', [TransactionController::class, 'order']);
Route::get('/getConfirmedTransactions/{count?}', [TransactionController::class, 'getConfirmedTransactions']);
Route::get('/getUnConfirmedTransactions/{count?}', [TransactionController::class, 'getUnConfirmedTransactions']);
Route::post('/editUserTranaction', [TransactionController::class, 'editUserTranaction']);



// GiftCard menu
Route::get('getGiftCardMainMenuTitle', [GiftCardMenuItemController::class, 'getGiftCardMainMenuTitle']);
Route::get('getAllGiftCardMenues', [GiftCardMenuItemController::class, 'getAllGiftCardMenues']);
Route::post('updateGiftCardMenuAlisNameByLevel', [GiftCardMenuItemController::class, 'updateGiftCardMenuAlisNameByLevel']);

// GiftCard
Route::post('createNewGiftCard', [GiftCardController::class, 'createNewGiftCard']);
Route::post('updateGiftCard', [GiftCardController::class, 'updateGiftCard']);
Route::get('deleteGiftCardByCode/{code}', [GiftCardController::class, 'deleteGiftCardByCode']);
Route::get('getGiftCardList', [GiftCardController::class, 'getGiftCardList']);



// support
Route::get('getSupporstList', [SupportController::class, 'getSupporstList']);
Route::get('getSupportById/{id}', [SupportController::class, 'getSupportById']);
Route::get('deleteSupportById/{id}', [SupportController::class, 'deleteSupportById']);
Route::post('createNewSupport', [SupportController::class, 'createNewSupport']);
Route::post('updateSupportById', [SupportController::class, 'updateSupportById']);

//Faq
Route::post('createNewFac', [FaqController::class, 'createNewFac']);
Route::post('updateFac', [FaqController::class, 'updateFac']);
Route::get('deleteFacById/{id}', [FaqController::class, 'deleteFacById']);
Route::get('getFacById/{id}', [FaqController::class, 'getFacById']);
Route::get('getFaqList', [FaqController::class, 'getFaqList']);


// channel lock menu
Route::get('getChannelLockMainMenuTitle', [ChannelLockMenuItemController::class, 'getChannelLockMainMenuTitle']);
Route::post('updateChannelLockMenuAlisNameByLevel', [ChannelLockMenuItemController::class, 'updateChannelLockMenuAlisNameByLevel']);

// channel lock menu
Route::post('createNewChannelLock', [ChannelLockController::class, 'createNewChannelLock']);
Route::post('editChannelLock', [ChannelLockController::class, 'editChannelLock']);
Route::get('deActiveChannelLockByID/{id}', [ChannelLockController::class, 'deActiveChannelLockByID']);
Route::get('reActiveChannelLockByID/{id}', [ChannelLockController::class, 'reActiveChannelLockByID']);
Route::get('deleteChannelLockByID/{id}', [ChannelLockController::class, 'deleteChannelLockByID']);
Route::get('getAllChannelLock', [ChannelLockController::class, 'getAllChannelLock']);
Route::get('getAllActiveChannelLock', [ChannelLockController::class, 'getAllActiveChannelLock']);

// Pannel
Route::post('addNewPannel', [PannelController::class, 'addNewPannel']);
Route::post('addNewPannelMarzban', [PannelController::class, 'addNewPannelMarzban']);
Route::post('updatePannel', [PannelController::class, 'updatePannel']);
Route::get('deletePannel/{id}', [PannelController::class, 'deletePannel']);
Route::get('getPannels', [PannelController::class, 'getPannels']);
Route::get('getPannelById/{id}', [PannelController::class, 'getPannelById']);
Route::get('getPannelByIdWithProxiesInbounds/{id}', [PannelController::class, 'getPannelByIdWithProxiesInbounds']);
Route::get('createMarzbanUser/{accountId}/{day}/{vol}/{pannelID}', [PannelController::class, 'createMarzbanUser']);





//  Proxy
Route::post('addNewProxy', [ProxyController::class, 'addNewProxy']);
Route::post('updateProxy', [ProxyController::class, 'updateProxy']);
Route::get('deleteProxy/{id}', [ProxyController::class, 'deleteProxy']);
Route::get('reActiveProxy/{id}', [ProxyController::class, 'reActiveProxy']);
Route::get('deActiveProxy/{id}', [ProxyController::class, 'deActiveProxy']);
Route::get('getActiveProxiesByPannelID/{pannelID}', [ProxyController::class, 'getActiveProxiesByPannelID']);

//  Inbound
Route::post('addInbound', [InboundController::class, 'addInbound']);
Route::post('updateInbound', [InboundController::class, 'updateInbound']);
Route::get('deleteInbound/{id}', [InboundController::class, 'deleteInbound']);
Route::get('reActiveInbound/{id}', [InboundController::class, 'reActiveInbound']);
Route::get('deActiveInbound/{id}', [InboundController::class, 'deActiveInbound']);

//  BotUser
Route::get('getBotUserList', [BotUserController::class, 'getBotUserList']);
Route::get('getBotUserByID/{id}', [BotUserController::class, 'getBotUserByID']);
