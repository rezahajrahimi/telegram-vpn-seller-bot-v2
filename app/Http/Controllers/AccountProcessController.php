<?php
namespace App\Http\Controllers;

use App\Models\BotUser;
use App\Services\TelegramMessageFormatter;
use App\Services\TelegramService;
// add cache

// add BotUser model
// add cache

class AccountProcessController extends Controller
{
    private TelegramService $telegramService;
    private CustomTextController $customTextCtrl;
    private SubscriptionProcessController $subscriptionProcessCtrl;
    private TransactionController $transactionCntrl;
    private GeneralController $generalCntrl;
    private ReferralWalletController $referralWalletCtrl;
    private AccountBallanceController $accBlCtrl;
    private BotUser $botUser;
    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService         = $telegramService;
        $this->customTextCtrl          = new CustomTextController();
        $this->subscriptionProcessCtrl = new SubscriptionProcessController($this->telegramService);
        $this->transactionCntrl        = new TransactionController($this->telegramService);
        $this->generalCntrl            = new GeneralController();
        $this->referralWalletCtrl      = new ReferralWalletController();
        $this->accBlCtrl               = new AccountBallanceController();
        $this->botUser                 = new BotUser();
    }
    public function accountDetails($chatId)
    {
        $botUser = BotUser::where('account_id', $chatId)->first();
        if ($botUser == null) {
            return $this->generalCntrl->return_main_menu_items($chatId, $this->customTextCtrl->getText('error.user_not_found'));
        }

        $ballance         = $this->accBlCtrl->getUserAccuntBalance($chatId);
        $ballanceInDollar = $this->accBlCtrl->getUserAccuntBalanceInDollar($chatId);
        $referralAmount   = $this->referralWalletCtrl->get_amount_of_ref_wallet_by_account_id($chatId);
        $ballance         = number_format($ballance, 0, '.', ',');
        $ballanceInDollar = number_format($ballanceInDollar, 0, '.', ',');
        $referralAmount   = number_format($referralAmount, 0, '.', ',');
        $text             = $this->customTextCtrl->getText('action.account.details', [
            'username'          => $botUser->username,
            'name'              => $botUser->first_name,
            'last_name'         => $botUser->last_name,
            'account_id'        => $botUser->account_id,
            'balance'           => "$ballance تومان",
            'balance_in_dollar' => "$ballanceInDollar دلار",
            'referral_balance'  => "$referralAmount تومان",
        ]);

        $formatter = new TelegramMessageFormatter($this->telegramService);
        $text      = $formatter->addFormattedText('', $text)->getMessage();

        // $transactions = Transaction::where('user_id', $botUser->id)->get();
        // $transactions = $transactions->sortByDesc('created_at');
        // $transactions = $transactions->take(10);
        // $transactions = $transactions->map(function ($transaction) {
        //     return $transaction->toArray();
        // });

        return $this->generalCntrl->return_main_menu_items($chatId, $text);
    }
}
