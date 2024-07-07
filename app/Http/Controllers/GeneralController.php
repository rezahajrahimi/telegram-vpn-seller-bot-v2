<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GeneralController extends Controller
{
    public function getDashboardAnalytics()
    {
        try {
            $botUsetCntrl = new BotUserController();
        $getLast10Users = $botUsetCntrl->getLast10Users();
        $logCntrl = new LogController();
        $getTop20Log = $logCntrl->getAllLogs(20);
        $transactionCntrl = new TransactionController();
        $last10ConfirmedTransaction = $transactionCntrl->getConfirmedTransactions(10);
        $unConfirmedTransaction = $transactionCntrl->getUnConfirmedTransactions(1000);
        $productCatCntrl = new ProductCategoryController();
        $mostSelledProductCategory = $productCatCntrl->mostSelledProductCategory(10);
        return response()->json(
            [
                'Last10User' => $getLast10Users,
                'Last20Logs' => $getTop20Log,
                'Last10ConfirmedTransaction' => $last10ConfirmedTransaction,
                'UnConfirmedTransaction' => $unConfirmedTransaction,
                'MostSelledProductCategory' => $mostSelledProductCategory,
            ],
            200
        );
        } catch (\Throwable $th) {
            \Log::info("error on getDashboardAnalytics-> $th");
            return response()->json(null, 500);

        }

    }
    public function getAgentDashboardAnalytics(){
        try {
            $accCntrl = new AccountBallanceController();
        $accBallance = $accCntrl->getLoggedUserBallancce();
        $agentPrCntrl = new AgentProductController();
        $products =  $agentPrCntrl->getProductsOfLoggedAgent();
        $boughtProducts =  $agentPrCntrl->getAgentSelledProducts();
        return response()->json(
            [
                'accBallance' => $accBallance,
                'products' => $products,
                'boughtProducts' => $boughtProducts,
            ],
            200
        );
        } catch (\Throwable $th) {
            \Log::info("error on getAgentDashboardAnalytics-> $th");
            return response()->json(null, 500);

        }


    }
    public function getAgentPaymentWays(){
        try {
            $pymntCntrl = new PaymentTypeController();
        $pymentType = $pymntCntrl->getAllActivePaymentTypesWithZarinpalMerchentIDFilter();
        $cryptoPymentCntrl = new CryptoPaymentController();
         $cryptiPymentIsActive = $cryptoPymentCntrl->getNowPaymentsStatus();
         return response()->json(["active_payment"=>$pymentType,"crypto_payment_status"=> $cryptiPymentIsActive], 200);

        } catch (\Throwable $th) {
            \Log::info("error on getAgentPaymentWays-> $th");
            return response()->json(null, 500);
        }

    }
}
