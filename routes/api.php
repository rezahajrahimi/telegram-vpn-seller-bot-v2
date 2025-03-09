<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\OrderController;
// use App\Http\Controllers\ServiceTypeController;
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
use App\Http\Controllers\LogController;
use App\Http\Controllers\AccountBallanceController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\GeneralController;
use App\Http\Controllers\HiddifyPannelController;
use App\Http\Controllers\TestAccountController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\CryptoPaymentController;
use App\Http\Controllers\TransactionCryptoController;
use App\Http\Controllers\PaymentSettingController;
use App\Http\Controllers\AgentProductController;
use App\Http\Controllers\AgentPermissonController;
use App\Http\Controllers\ExecuteArtisanCommandController;
use App\Http\Controllers\CronJobController;
use App\Http\Controllers\ReferralSettingController;
use App\Http\Controllers\ReferralWalletController;
use App\Http\Controllers\ReferralLogsController;
use App\Http\Controllers\ReserverdConfigController;
use App\Http\Controllers\AdvanceSettingLookupController;
use App\Http\Controllers\WebAppMenuItemController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\CustomTextController;
use App\Http\Controllers\BlockedUserController;
use App\Http\Controllers\ShetabVerifyController;


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
Route::post('/telegram/webhooks/inbound', [TelegramWebhookController::class, 'handle']);

// Route::prefix('telegram/webhooks')->group(function () {
//     Route::post('inbound', [TelegramController::class, 'inbound'])->name('telegram.inbound');
// });

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::post('/forgetPassword', [AuthController::class, 'forgetPassword']);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return $request->user();
});
// /auth/me
Route::get('/auth/me', [AuthController::class, 'me']);
// Admin Routes
Route::group(['middleware' => ['auth:sanctum', 'restrictRole:admin']], function () {
    // run a command by api
    Route::get('/run-command/{name_of_command}', ExecuteArtisanCommandController::class);
    ///
    Route::get('getUserOrder/{userID}', [OrderController::class, 'getUserOrder']);
    // Route::get('getServiceTypes', [ServiceTypeController::class, 'getServiceTypes']);
    // Admin
    Route::put('buyProductByAdmin', [AgentProductController::class, 'buyProductByAdmin']);
    Route::put('changeProductByAdminWithPrID', [AgentProductController::class, 'changeProductByAdminWithPrID']);
    Route::post('changeActivationOfHiddifyUserByAdmin', [AgentProductController::class, 'changeActivationOfHiddifyUserByAdmin']);

    // backup
    Route::get('createBackup', [BackupController::class, 'createBackup']);
    Route::post('restoreBackup', [BackupController::class, 'restoreBackup']);
    Route::get('testDatabaseConnection', [BackupController::class, 'testDatabaseConnection']);
    Route::get('testMysqldump', [BackupController::class, 'testMysqldump']);
    Route::get('createBackupWithPHP', [BackupController::class, 'createBackupWithPHP']);

    // UserController
    Route::get('getUsers', [UserController::class, 'getUsers']);
    Route::get('getAgents', [UserController::class, 'getAgents']);
    Route::get('getNormalUsers', [UserController::class, 'getNormalUsers']);
    Route::get('getUserById/{id}', [UserController::class, 'getUserById']);
    Route::get('getAgentByIdWithProductsAndPremissons/{id}', [UserController::class, 'getAgentByIdWithProductsAndPremissons']);
    Route::post('createUser', [UserController::class, 'createUser']);
    Route::put('updateUser', [UserController::class, 'updateUser']);
    Route::delete('deleteUser', [UserController::class, 'deleteUser']);
    Route::get('getAdminUsers', [UserController::class, 'get_admin_users']);
    Route::patch('changeUserRoleToAdmin/{id}', [UserController::class, 'change_user_role_to_admin']);
    Route::patch('changeAgentRoleToUser/{id}', [UserController::class, 'change_user_role_to_user']);

    // GeneralController
    Route::get('getDashboardAnalytics', [GeneralController::class, 'getDashboardAnalytics']);

    //  ProductCategory
    Route::get('getAllProdctCategory', [ProductCategoryController::class, 'getAllProdctCategory']);
    Route::get('getProdctPrice', [ProductCategoryController::class, 'getProdctPrice']);
    Route::get('getProdctPannelID/{name}/pannelID', [ProductCategoryController::class, 'getProdctPannelID']);
    Route::post('addNewProductCategory', [ProductCategoryController::class, 'addNewProductCategory']);
    Route::post('editProductCategory', [ProductCategoryController::class, 'editProductCategory']);
    Route::get('reActiveProductCategory/{id}', [ProductCategoryController::class, 'reActiveProductCategory']);
    Route::get('deActiveProductCategory/{id}', [ProductCategoryController::class, 'deActiveProductCategory']);
    Route::get('deleteProductCategoryByID/{id}', [ProductCategoryController::class, 'deleteProductCategoryByID']);
    Route::get('getAgentProductsNotSelectedByUserID/{userID}', [ProductCategoryController::class, 'getAgentProductsNotSelectedByUserID']);

    //ProductController
    Route::get('getActiveProductsByProductCatID/{selectedProductCatID}', [ProductController::class, 'getActiveProductsByProductCatID']);
    Route::post('addNewProductDetails', [ProductController::class, 'addNewProductDetails']);
    Route::get('deleteProduct/{id}', [ProductController::class, 'deleteProduct']);
    Route::get('getLastBuyersByCatIdAndCount/{id}/{count}', [ProductController::class, 'getLastBuyersByCatIdAndCount']);
    Route::get('getCountOfProductSelledSummeryByCatID/{id}', [ProductController::class, 'getCountOfProductSelledSummeryByCatID']);
    Route::get('deleteProductByProductID/{id}', [ProductController::class, 'deleteProductByProductID']);
    Route::get('getUserProductsHistoryByUserIDWithPagination/{userId}', [ProductController::class, 'getUserProductsHistoryByUserIDWithPagination']);

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
    Route::post('reorder-main-menu-items', [MainMenuItemController::class, 'reorderMainMenuItems']);

    // payment type
    Route::get('getPaymentTypes', [PaymentTypeController::class, 'getPaymentTypes']);
    Route::get('getAllActiveOfflinePaymentTypes', [PaymentTypeController::class, 'getAllActiveOfflinePaymentTypes']);
    Route::get('getPaymentAddressByPaymentName/{name}', [PaymentTypeController::class, 'getPaymentAddressByPaymentName']);
    Route::get('isPaymentType/{name}', [PaymentTypeController::class, 'isPaymentType']);
    Route::get('getAllOnlinePayments', [PaymentTypeController::class, 'getAllOnlinePayments']);
    Route::get('getAllOfflinePayments', [PaymentTypeController::class, 'getAllOfflinePayments']);
    Route::get('getZarinpalPaymentDetails', [PaymentTypeController::class, 'getZarinpalPaymentDetails']);
    Route::get('getAllTypesOfpaymentData', [PaymentTypeController::class, 'getAllTypesOfpaymentData']);
    Route::post('createNewPaymentType', [PaymentTypeController::class, 'createNewPaymentType']);
    Route::get('getAllActivePaymentTypes', [PaymentTypeController::class, 'getAllActivePaymentTypes']);
    Route::get('deActivePaymentType/{name}', [PaymentTypeController::class, 'deActivePaymentType']);
    Route::get('reActivePaymentType/{name}', [PaymentTypeController::class, 'reActivePaymentType']);
    Route::get('removePaymentType/{name}', [PaymentTypeController::class, 'removePaymentType']);
    Route::post('chanegeMerChantIdByPaymentTypeName', [PaymentTypeController::class, 'chanegeMerChantIdByPaymentTypeName']);
    Route::post('updateOfflinePaymentType', [PaymentTypeController::class, 'update_offline_payment_type']);

    // paymenyt type menu
    Route::get('getPaymentTypeMainMenuTitle', [PaymentMenuItemController::class, 'getPaymentTypeMainMenuTitle']);
    Route::get('getAllPaymentTypeMenues', [PaymentMenuItemController::class, 'getAllPaymentTypeMenues']);
    Route::post('updatePaymentMenuAlisNameByLevel', [PaymentMenuItemController::class, 'updatePaymentMenuAlisNameByLevel']);

    // TransactionController && online payment

    Route::get('/changeNovaPaymentData', [TransactionController::class, 'changeNovaPaymentData']);
    Route::get('/getConfirmedTransactions/{count?}', [TransactionController::class, 'getConfirmedTransactions']);
    Route::get('/getUnConfirmedTransactions/{count?}', [TransactionController::class, 'getUnConfirmedTransactions']);
    Route::get('/removeUnconfirmedTransaction/{id}', [TransactionController::class, 'removeUnconfirmedTransaction']);
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
    Route::get('getFaqById/{id}', [FaqController::class, 'getFaqById']);
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
    Route::post('editMarzbanPannel', [PannelController::class, 'editMarzbanPannel']);
    Route::post('updatePannel', [PannelController::class, 'updatePannel']);
    Route::get('deletePannel/{id}', [PannelController::class, 'deletePannel']);
    Route::get('getPannels', [PannelController::class, 'getPannels']);
    Route::get('getPannelById/{id}', [PannelController::class, 'getPannelById']);
    Route::get('getPannelByIdWithProxiesInbounds/{id}', [PannelController::class, 'getPannelByIdWithProxiesInbounds']);
    Route::get('createMarzbanUser/{accountId}/{day}/{vol}/{pannelID}', [PannelController::class, 'createMarzbanUser']);

    // Hiddify Panel

    Route::post('checkHiddifyPanelUrl', [HiddifyPannelController::class, 'checkHiddifyPanelUrl']);
    Route::post('addHiddifyPannel', [HiddifyPannelController::class, 'addHiddifyPannel']);
    Route::post('updateHiddifyPannel', [HiddifyPannelController::class, 'updateHiddifyPannel']);
    Route::post('addUserToHiddifyPanel', [HiddifyPannelController::class, 'addUserToHiddifyPanel']);
    Route::post('updateUserOfHiddifyPanel', [HiddifyPannelController::class, 'updateUserOfHiddifyPanel']);
    Route::get('deleteUserOfHiddifyPanel/{pannelID}/{userUUID}', [HiddifyPannelController::class, 'deleteUserOfHiddifyPanel']);
    Route::get('getHiddifyPanelUsersByPannelID/{pannelID}', [HiddifyPannelController::class, 'getHiddifyPanelUsersByPannelID']);
    Route::get('getHiddifyPanelUserByPannelID/{pannelID}/{userUUID}', [HiddifyPannelController::class, 'getHiddifyPanelUserByPannelID']);

    //  Proxy
    Route::post('addNewProxy', [ProxyController::class, 'addNewProxy']);
    Route::post('updateProxy', [ProxyController::class, 'updateProxy']);
    Route::get('deleteProxy/{id}', [ProxyController::class, 'deleteProxy']);
    Route::get('reActiveProxy/{id}', [ProxyController::class, 'reActiveProxy']);
    Route::get('deActiveProxy/{id}', [ProxyController::class, 'deActiveProxy']);
    Route::get('getActiveProxiesByPannelID/{pannelID}', [ProxyController::class, 'getActiveProxiesByPannelID']);
    Route::get('getProxiesByPannelID/{pannelID}', [ProxyController::class, 'getProxiesByPannelID']);

    //  Inbound
    Route::post('addInbound', [InboundController::class, 'addInbound']);
    Route::post('updateInbound', [InboundController::class, 'updateInbound']);
    Route::get('deleteInbound/{id}', [InboundController::class, 'deleteInbound']);
    Route::get('reActiveInbound/{id}', [InboundController::class, 'reActiveInbound']);
    Route::get('deActiveInbound/{id}', [InboundController::class, 'deActiveInbound']);

    //  BotUser
    Route::get('getBotUserList', [BotUserController::class, 'getBotUserList']);
    Route::get('getBotUserListByPagination', [BotUserController::class, 'getBotUserListByPagination']);
    Route::get('getBotUserByID/{id}', [BotUserController::class, 'getBotUserByID']);
    Route::post('searchBotUsers', [BotUserController::class, 'search_bot_users']);

    Route::get('getLast10Users', [BotUserController::class, 'getLast10Users']);
    Route::get('getProductBoughtedByProductId/{id}', [AgentProductController::class, 'getBoughtProductsStatusFromServerById']);
    Route::patch('reChargeProductByAdminWithPrID', [AgentProductController::class, 'reChargeProductByAdminWithPrID']);
    Route::get('getBoughtProductsPannelLinkFromServerByIdAdminMode/{id}', [AgentProductController::class, 'getBoughtProductsPannelLinkFromServerByIdAdminMode']);
    Route::delete('softDeleteProductByAgentWithPrIDAdminMOde/{id}', [AgentProductController::class, 'softDeleteProductByAgentWithPrIDAdminMOde']);


    // Log
    Route::get('getAllLogs/{count}', [LogController::class, 'getAllLogs']);

    //  AccountBallanceController
    Route::post('setNewAccountBallance', [AccountBallanceController::class, 'setNewAccountBallance']);
    Route::post('setNewDollarAccountBallance', [AccountBallanceController::class, 'setNewDollarAccountBallance']);
    Route::put('increaseUserAccuntBalanceByUserID', [AccountBallanceController::class, 'increaseUserAccuntBalanceByUserID']);
    Route::put('decreaseUserAccuntBalanceByUserID', [AccountBallanceController::class, 'decreaseUserAccuntBalanceByUserID']);

    // Application
    Route::get('getAllAplicationList', [ApplicationController::class, 'getAllAplicationList']);
    Route::get('getAllActiveAplicationList', [ApplicationController::class, 'getAllActiveAplicationList']);
    Route::get('getAllActiveAplicationListByOS/{os}', [ApplicationController::class, 'getAllActiveAplicationListByOS']);
    Route::get('getActiveAplicationByName/{name}', [ApplicationController::class, 'getActiveAplicationListByName']);
    Route::get('getActiveAplicationByID/{id}', [ApplicationController::class, 'getActiveAplicationListByID']);
    Route::post('createNewApplication', [ApplicationController::class, 'createNewApplication']);
    Route::post('updateApplication', [ApplicationController::class, 'updateApplication']);
    Route::delete('deleteApplication/{id}', [ApplicationController::class, 'deleteApplication']);

    //  TestAccountController
    Route::get('getTestAccountDetails', [TestAccountController::class, 'getTestAccountDetails']);
    Route::post('updateTestAccountDetails', [TestAccountController::class, 'updateTestAccountDetails']);

    // CryptoPaymentController
    Route::get('getNovPaymentData', [CryptoPaymentController::class, 'getNovPaymentData']);
    Route::patch('updateNowPayment', [CryptoPaymentController::class, 'updateNowPayment']);
    // PaymentSettingController
    Route::get('get-payment-setting-by-key/{key}', [PaymentSettingController::class, 'getPaymentSettingByKey']);
    Route::get('get-payment-setting-value-by-key/{key}', [PaymentSettingController::class, 'getPaymentSettingValueByKey']);
    Route::get('get-payment-setting-description-by-key/{key}', [PaymentSettingController::class, 'getPaymentSettingDescriptionByKey']);
    Route::get('reverse-status-by-key/{key}', [PaymentSettingController::class, 'reverseStatusByKey']);
    Route::get('seed-payment-setting', [PaymentSettingController::class, 'seed']);
    Route::get('re-generate-shetab-verify', [PaymentSettingController::class, 'reGenerateShetabVerify']);
    Route::patch('set-payment-setting-value-by-key/{key}/{value}', [PaymentSettingController::class, 'setPaymentSettingValueByKey']);
    Route::patch('set-payment-setting-description-by-key/{key}/{description}', [PaymentSettingController::class, 'setPaymentSettingDescriptionByKey']);
    Route::patch('set-payment-setting-status-by-key/{key}/{status}', [PaymentSettingController::class, 'setPaymentSettingStatusByKey']);

    // AgentProductController
    Route::post('createBatchOfUserAgentProduct', [AgentProductController::class, 'createBatchOfUserAgentProduct']);
    Route::post('removeAgent', [AgentProductController::class, 'removeAgent']);
    Route::post('obtainBatchOfExistProductsToUser', [AgentProductController::class, 'obtainBatchOfExistProductsToUser']);
    Route::post('deleteBatchOfUserAgentProduct', [AgentProductController::class, 'deleteBatchOfUserAgentProduct']);
    Route::post('createANewAgentProduct', [AgentProductController::class, 'createANewAgentProduct']);
    Route::patch('updateAgentProduct', [AgentProductController::class, 'updateAgentProduct']);
    Route::delete('deleteAgentProduct/{id}', [AgentProductController::class, 'deleteAgentProduct']);
    Route::get('getAgentProductsByUserID/{userID}', [AgentProductController::class, 'getAgentProductsByUserID']);
    Route::get('getAgentProductsByID/{ID}', [AgentProductController::class, 'getAgentProductsByID']);

    // AgentPermissonController
    Route::get('getUserPremissionByAgentID/{ID}', [AgentPermissonController::class, 'getUserPremissionByAgentID']);
    Route::post('createANewAgentPremission', [AgentPermissonController::class, 'createANewAgentPremission']);
    Route::patch('updateAgentPremission', [AgentPermissonController::class, 'updateAgentPremission']);
    Route::delete('deleteAgentPremission/{id}', [AgentPermissonController::class, 'deleteAgentPremission']);

    // CronJobController
    Route::get('/getAllCronJobs', [CronJobController::class, 'get_all_cron_jobs']);
    Route::get('/getAllActiveCronJobs', [CronJobController::class, 'get_all_active_cron_jobs']);
    Route::get('/changeCronJobActiveStatusById/{id}', [CronJobController::class, 'change_cron_job_active_status']);
    // Route::get('/getTetherPriceByNobitex', [CronJobController::class, 'get_tether_price_by_nobitex']);
    Route::get('/dailyBackup', [CronJobController::class, 'execute_create_daily_backup']);
    Route::get('/usage-more-than-85-percent', [CronJobController::class, 'execute_send_useage_more_than_85_percent']);
    Route::get('/auto-delete-expired-configs', [CronJobController::class, 'execute_auto_delete_expired_configs']);
    Route::get('/less-than-3-days', [CronJobController::class, 'execute_send_lass_there_than_3_days']);

    // ReferralSettingController
    Route::get('/getReferralSetting', [ReferralSettingController::class, 'get_referral_setting']);
    Route::put('/updateReferralSetting', [ReferralSettingController::class, 'update_referral_setting']);

    //  ReferralWalletController
    Route::put('/editAmountOfRefWalletByAccountId', [ReferralWalletController::class, 'edit_amount_of_ref_wallet_by_account_id']);

    // ReserverdConfigController
    Route::post('/checkAProductHasReservedConfigByProductId', [ReserverdConfigController::class, 'check_a_product_has_reserved_config_by_product_id']);

    // AdvanceSettingLookupController
    Route::get('/advanceSettingLookup', [AdvanceSettingLookupController::class, 'getAll']);
    Route::get('/advanceSettingLookupByName/{name}', [AdvanceSettingLookupController::class, 'getByName']);
    Route::get('/advanceSettingLookupByNameWithBooleanValue/{name}', [AdvanceSettingLookupController::class, 'getByNameWithBooleanValue']);
    Route::get('/advanceSettingLookupByNameAndValue/{name}/{value}', [AdvanceSettingLookupController::class, 'getByNameAndValue']);
    Route::get('/advanceSettingLookupByValueWithBooleanValue/{name}', [AdvanceSettingLookupController::class, 'getValueByNameWithBooleanValue']);
    Route::get('/restore-default-advanced-settings', [AdvanceSettingLookupController::class, 're_seed_advance_settings_lookup']);

    Route::post('/advanceSettingLookupCreate', [AdvanceSettingLookupController::class, 'create']);
    Route::post('/advanceSettingLookupUpdate', [AdvanceSettingLookupController::class, 'update']);
    Route::post('/advanceSettingLookupUpdateByName', [AdvanceSettingLookupController::class, 'updateByName']);


    // CustomTextController
    Route::get('/get-text/{key}', [CustomTextController::class, 'getText']);
    Route::put('/set-text/{key}/{text}', [CustomTextController::class, 'setText']);
    Route::post('/set-text', [CustomTextController::class, 'setTest']);
    Route::get('/get-all-texts', [CustomTextController::class, 'getAllTexts']);

    // BlockedUserController
    Route::get('/get-all-blocked-users', [BlockedUserController::class, 'getBlockedUserList']);
    Route::post('/add-blocked-user', [BlockedUserController::class, 'addBlockedUser']);
    Route::post('/remove-blocked-user', [BlockedUserController::class, 'removeBlockedUser']);
    Route::get('/get-blocked-user', [BlockedUserController::class, 'getBlockedUser']);
    Route::get('/is-blocked', [BlockedUserController::class, 'isBlocked']);
    Route::get('/get-blocked-user-count', [BlockedUserController::class, 'getBlockedUserCount']);
});
Route::group(['middleware' => ['auth:sanctum', 'restrictRole:agent']], function () {
    // User
    Route::put('updateAgentPassword', [UserController::class, 'updateAgentPassword']);

    // GeneralController
    Route::get('getAgentDashboardAnalytics', [GeneralController::class, 'getAgentDashboardAnalytics']);

    // AccountBallanceController
    Route::get('getLoggedAgentUserBallancce', [AccountBallanceController::class, 'getLoggedUserBallancce']);
    // AgentProductController
    Route::get('getProductsOfLoggedAgent', [AgentProductController::class, 'getProductsOfLoggedAgent']);
    Route::get('getAgentSelledProducts', [AgentProductController::class, 'getAgentSelledProducts']);
    Route::get('getAgentSelledProductsByPagination', [AgentProductController::class, 'getAgentSelledProductsByPagination']);
    Route::get('getBoughtProductsStatusFromServerById/{id}', [AgentProductController::class, 'getBoughtProductsStatusFromServerById']);
    Route::put('buyProductByAgentWithPrID', [AgentProductController::class, 'buyProductByAgentWithPrID']);
    Route::patch('renameHiddifyRemark', [AgentProductController::class, 'renameHiddifyRemark']);
    Route::patch('reChargeProductByAgentWithPrID', [AgentProductController::class, 'reChargeProductByAgentWithPrID']);
    Route::put('changeProductByAgentWithPrID', [AgentProductController::class, 'changeProductByAgentWithPrID']);
    Route::delete('softDeleteProductByAgentWithPrID/{id}', [AgentProductController::class, 'softDeleteProductByAgentWithPrID']);
});
Route::group(['middleware' => ['auth:sanctum', 'restrictRole:user']], function () {
    // GeneralController
    Route::get('getUserDashboardAnalytics', [GeneralController::class, 'getUserDashboardAnalytics']);

    // AccountBallanceController
    Route::get('getLoggedUserBallancce', [AccountBallanceController::class, 'getLoggedUserBallancce']);

    // BillController
    Route::get('createNewUserTomanBillUrl/{amount}', [BillController::class, 'createNewAgentTomanBillUrl']);
    Route::get('createNewUserDollarBillUrl/{amount}', [BillController::class, 'createNewAgentDollarBillUrl']);

    // AgentProductController
    Route::put('buyProductByUserWithPrID', [AgentProductController::class, 'buyProductByUserWithPrID']);
    Route::get('getUserSelledProductsByPagination', [AgentProductController::class, 'getAgentSelledProductsByPagination']);
    Route::get('getProductBoughtedByProductIdUserMode/{id}', [AgentProductController::class, 'getBoughtProductsStatusFromServerById']);
    Route::patch('reChargeProductByUserWithPrID', [AgentProductController::class, 'reChargeProductByUserWithPrID']);
    Route::delete('softDeleteProductByUserWithPrID/{id}', [AgentProductController::class, 'softDeleteProductByUserWithPrID']);
});
// shared route
Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::get('getBoughtProductsPannelLinkFromServerById/{id}', [AgentProductController::class, 'getBoughtProductsPannelLinkFromServerById']);
    Route::post('changeActivationOfHiddifyUserByAgent', [AgentProductController::class, 'changeActivationOfHiddifyUserByAgent']);
    Route::get('getAgentPaymentWays', [GeneralController::class, 'getAgentPaymentWays']);
    // BillController
    Route::get('createNewAgentTomanBillUrl/{amount}', [BillController::class, 'createNewAgentTomanBillUrl']);
    Route::get('createNewAgentDollarBillUrl/{amount}', [BillController::class, 'createNewAgentDollarBillUrl']);

    // UserController
    Route::put('updateUserPassword', [UserController::class, 'update_logged_password']);
    //  ReferralLogsController
    Route::get('/getReferralLogsByAccountId/{account_id}', [ReferralLogsController::class, 'get_referral_logs']);
    // WebAppMenuItemController
    Route::get('/getAllActiveWebAppMenuItems', [WebAppMenuItemController::class, 'get_all_active_web_app_menu_items']);

    //ProxyController

});


Route::get('/getAllActiveProdctCategoryOrderByPrice', [ProductCategoryController::class, 'getAllActiveProdctCategoryOrderByPrice']);






Route::post('createNewBillInDollar', [BillController::class, 'createNewBillInDollar']);
Route::get('/order', [TransactionController::class, 'order']);
Route::get('/orderSuccess', [TransactionCryptoController::class, 'orderSuccess']);
Route::get('/getPaymentStatus/{id}', [TransactionCryptoController::class, 'getPaymentStatus']);

Route::get('/prd', [CronJobController::class, 'execute_auto_delete_expired_configs']);

Route::get('/create-backup-and-send-to-telegram', [BackupController::class, 'createBackupAndSendToTelegram']);


Route::post('/orderch', [TransactionController::class, 'add_order']);

Route::post('/shetab-verify', [ShetabVerifyController::class, 'validate_shetab_verify']);

