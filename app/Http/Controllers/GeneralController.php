<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GeneralController extends Controller
{
    public function getDashboardAnalytics()
    {
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
    }
}
