<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\DomCrawler\Crawler;

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
            $prCntrl = new ProductController();
            $last10ProductSelled = $prCntrl->getLastProductSelled(10);
            return response()->json(
                [
                    'Last10User' => $getLast10Users,
                    'Last20Logs' => $getTop20Log,
                    'Last10ConfirmedTransaction' => $last10ConfirmedTransaction,
                    'UnConfirmedTransaction' => $unConfirmedTransaction,
                    'MostSelledProductCategory' => $mostSelledProductCategory,
                    'last10ProductSelled' => $last10ProductSelled,
                ],
                200,
            );
        } catch (\Throwable $th) {
            \Log::info("error on getDashboardAnalytics-> $th");
            return response()->json(null, 500);
        }
    }
    public function getAgentDashboardAnalytics()
    {
        try {
            $accCntrl = new AccountBallanceController();
            $accBallance = $accCntrl->getLoggedUserBallancce();
            $agentPrCntrl = new AgentProductController();
            $products = $agentPrCntrl->getProductsOfLoggedAgent();
            // $boughtProducts =  $agentPrCntrl->getAgentSelledProducts(10);
            $logCntrl = new LogController();
            $getTop20Log = $logCntrl->getAllLogsOfLoggedAgent(20);
            return response()->json(
                [
                    'accBallance' => $accBallance,
                    'products' => $products,
                    // 'boughtProducts' => $boughtProducts,
                    'Last20Logs' => $getTop20Log,
                ],
                200,
            );
        } catch (\Throwable $th) {
            \Log::info("error on getAgentDashboardAnalytics-> $th");
            return response()->json(null, 500);
        }
    }
    public function getAgentPaymentWays()
    {
        try {
            $pymntCntrl = new PaymentTypeController();
            $pymentType = $pymntCntrl->getAllActivePaymentTypesWithZarinpalMerchentIDFilter();
            $cryptoPymentCntrl = new CryptoPaymentController();
            $cryptiPymentIsActive = $cryptoPymentCntrl->getNowPaymentsStatus();
            return response()->json(['active_payment' => $pymentType, 'crypto_payment_status' => $cryptiPymentIsActive], 200);
        } catch (\Throwable $th) {
            \Log::info("error on getAgentPaymentWays-> $th");
            return response()->json(null, 500);
        }
    }

    public function getUserDashboardAnalytics()
    {
        try {
            $accCntrl = new AccountBallanceController();
            $accBallance = $accCntrl->getLoggedUserBallancce();
            $prCatCntrl = new ProductCategoryController();

            $products = $prCatCntrl->getAllActiveProdctCategoryOrderByPrice();
            // $boughtProducts =  $agentPrCntrl->getAgentSelledProducts(10);
            $logCntrl = new LogController();
            $getTop20Log = $logCntrl->getAllLogsOfLoggedAgent(20);
            return response()->json(
                [
                    'accBallance' => $accBallance,
                    'products' => $products,
                    // 'boughtProducts' => $boughtProducts,
                    'Last20Logs' => $getTop20Log,
                ],
                200,
            );
        } catch (\Throwable $th) {
            \Log::info("error on getAgentDashboardAnalytics-> $th");
            return response()->json(null, 500);
        }
    }
    public function get_zarinpal_payment_link_from_html($htmlText)
    {
        // $htmlText = '<!DOCTYPE html>...'; // your HTML text here

        $crawler = new Crawler();
        $crawler->addHtmlContent($htmlText, 'UTF-8');

        $formTag = $crawler->filter('form')->first();

        if ($formTag) {
            $actionUrl = $formTag->attr('action');
            return $actionUrl; // Output: https://www.zarinpal.com/pg/StartPay/A000000000000000000000000000l353wx62
        } else {
            return '';
        }
    }
    public function get_nowpayment_payment_link_from_html($htmlText)
    {
        $crawler = new Crawler();
        $crawler->addHtmlContent($htmlText, 'UTF-8');

        $metaTag = $crawler->filter('meta[http-equiv="refresh"]')->first();

        if ($metaTag) {
            $redirectLink = $metaTag->attr('content');
            $redirectLink = explode(';', $redirectLink);
            $redirectLink = trim($redirectLink[1]);
            $redirectLink = str_replace("url='", '', $redirectLink);
            $redirectLink = str_replace("'", '', $redirectLink);
            return $redirectLink; // Output: https://nowpayments.io/payment/?iid=5096100130
        } else {
            $linkTag = $crawler->filter('a')->first();
            if ($linkTag) {
                $redirectLink = $linkTag->attr('href');
                return $redirectLink; // Output: https://nowpayments.io/payment/?iid=5096100130
            } else {
                return '';
            }
        }
    }
}
