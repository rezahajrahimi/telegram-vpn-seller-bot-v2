<?php
namespace App\Http\Controllers;

use App\Models\BotUser;
use App\Models\Transaction;
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
    private LogController $logCtrl;
    private $chatId;
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
        $this->logCtrl                 = new LogController();
    }
    public function accountDetails($chatId)
    {
        $this->chatId = $chatId;
        $botUser      = BotUser::where('account_id', $chatId)->first();
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

        $this->generalCntrl->return_main_menu_items($chatId, $text);
        $this->show_additional_options($chatId);
        $this->addNewBotLog('account', 'وارد بخش جزئیات حساب شد.', 'show');
        return "";
    }
    private function show_additional_options($chatId)
    {
        $opr   = [];
        $opr[] = [
            $this->customTextCtrl->getText('action.account.additional_options.transactions') => "accountTransactions",
        ];
        $opr[] = [
            $this->customTextCtrl->getText('action.account.additional_options.sub_accounts') => "account-subaccounts",
        ];
        $opr[] = [
            $this->customTextCtrl->getText('action.account.additional_options.add_balance') => "account-addbalance",
        ];
        $text = $this->customTextCtrl->getText('action.account.additional_options');
        $this->telegramService->sendMessageWithInlineKeyboard($chatId, $text, $opr);
        return "";
    }
    public function accountTransactions($chatId)
    {
        $this->chatId = $chatId;
        // $this->show_additional_options($chatId);
        $this->addNewBotLog('account', 'وارد بخش سابقه تراکنش‌ها شد.', 'show');
        $botUser = BotUser::where('account_id', $chatId)->first();
        if ($botUser == null) {
            return $this->generalCntrl->return_main_menu_items($chatId, $this->customTextCtrl->getText('error.server_error'));
        }

        $transactions = Transaction::where('account_id', $botUser->account_id)->get();
        $transactions = $transactions->sortByDesc('created_at');
        $transactions = $transactions->take(10);
        $text         = $this->customTextCtrl->getText('action.account.transactions.title');
        if ($transactions->count() > 0) {
            foreach ($transactions as $transaction) {
                $text .= $transaction->getTransactionText();
            }
        } else {
            $text = $this->customTextCtrl->getText('action.account.transactions.no_transactions');
        }
        $this->telegramService->sendMessage($chatId, $text);
        return "";
    }

    private function addNewBotLog($type, $message, $event)
    {
        $logCtrl = new LogController();
        $this->logCtrl->addNewLog($type, $message, $this->chatId, $this->botUser->username, $event);
        return true;
    }

}
