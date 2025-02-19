<?php
namespace App\Http\Controllers;

use App\Models\BotUser;
use App\Models\ReferralLogs;
use App\Models\Transaction;
use App\Models\UserState;
use App\Models\TransactionSetting;
use App\Services\TelegramMessageFormatter;
use App\Services\TelegramService;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;
// add cache
use Illuminate\Support\Facades\Cache;


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
    private TransactionSetting $trSetting;
    private $chatId;
    private TransactionController $trCntrl;
    private TransactionSettingController $trSettingCntrl;
    private PaymentTypeController $pymntCntrl;
    private PaymentMenuItemController $pymMenCntrl;
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
        $this->trSettingCntrl          = new TransactionSettingController();
        $this->pymntCntrl             = new PaymentTypeController();
        $this->pymMenCntrl              = new PaymentMenuItemController();
        $this->trCntrl              = new TransactionController();
                $this->trSetting                = new TransactionSetting();

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
            $this->customTextCtrl->getText('action.account.additional_options.sub_accounts') => "accountSubAccounts",
        ];
        $opr[] = [
            $this->customTextCtrl->getText('action.account.additional_options.add_balance') => "accountAddBalance",
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
        $text         = $this->customTextCtrl->getText('action.account.transactions.title') . "\n";
        if ($transactions->count() > 0) {
            foreach ($transactions as $transaction) {
                
                $text .=  $transaction->getTransactionText() . "\n";
            }
        } else {
            $text = $this->customTextCtrl->getText('action.account.transactions.no_transactions');
        }
        $this->telegramService->sendMessage($chatId, $text);
        return "";
    }
    public function accountSubAccounts($chatId)
    {
        // todo check on production
        $this->chatId = $chatId;
        $this->addNewBotLog('account', 'وارد بخش زیر مجموعه ها شد.', 'show');
        $botUser = BotUser::where('account_id', $chatId)->first();
        if ($botUser == null) {
            return $this->generalCntrl->return_main_menu_items($chatId, $this->customTextCtrl->getText('error.server_error'));
        }
        $subAccounts = ReferralLogs::where('referral_user_id', $botUser->id)->get();
        $text        = $this->customTextCtrl->getText('action.account.sub_accounts.title');
        if ($subAccounts->count() > 0) {
            foreach ($subAccounts as $subAccount) {
                $text .= $subAccount->getReferralLogsText();
            }
        } else {
            $text = $this->customTextCtrl->getText('action.account.sub_accounts.no_sub_accounts');
        }
        $this->telegramService->sendMessage($chatId, $text);
        return "";
    }
    public function accountAddBalance($chatId, $actionList = null)
    {
        $this->chatId = $chatId;
        $this->addNewBotLog('account', 'وارد بخش افزایش اعتبار حساب شد.', 'show');        // check if actionList is array and have not more than 1  elements

            $this->return_payment_options();
            return "";
   
    }
    private function return_payment_options()
    {
        try {
            $opr = [];

            $hasZarinPal = $this->pymntCntrl->getZarinpalStatus();
            if ($hasZarinPal == true || $hasZarinPal == 1) {
                $newOpr = [
                    $this->customTextCtrl->getText('action.process.add_online_balance.zarinpal') => "accountSubAccountsZarinpal",
                ];
                array_push($opr, $newOpr);
            }

            $hasDollarPay = $this->trSetting->getDollarTransactionSetting();
            if ($hasDollarPay == true || $hasDollarPay == 1) {
                $newOpr = [
                    $this->customTextCtrl->getText('action.process.add_online_balance.dollarpay.nowpayment') => "accountSubAccountsNowpayment",
                ];
                array_push($opr, $newOpr);
            }
            if (count($opr) > 0) {
                $text = $this->customTextCtrl->getText('action.process.add_online_balance');
                $this->telegramService->sendMessageWithInlineKeyboard($this->chatId, $text, $opr);
            }

// send offline item
            $opr = [];

            $offlinePayment = $this->pymntCntrl->getAllActiveOfflinePaymentTypes();
            if ($offlinePayment != null) {
                if ($hasZarinPal == true || $hasZarinPal == 1 || $hasDollarPay == true || $hasDollarPay == 1) {
                    $text = $this->customTextCtrl->getText('action.process.add_offline_balance_option_and_online_balance');
                } else {
                    $text = $this->customTextCtrl->getText('action.process.add_offline_balance_option');
                }

                $opr = [];

                foreach ($offlinePayment as $key => $value) {
                    $opr[] = [
                        "$value->name" => "offlineGateway-$value->id ",
                    ];
                }

            }
            $text = $this->customTextCtrl->getText('action.process.add_offline_balance_option');

            $this->telegramService->sendMessageWithInlineKeyboard($this->chatId, $text, $opr);
            return true;

        } catch (\Throwable $th) {
            \Log::error(["return_payment_options: " . $th]);
            $this->clearAwaitingReply($chatId, $this->customTextCtrl->getText('error.server_error'));
            return "";
        }
    }
    public function handleActionAddBalanceZarinpal(string $chatId): string
    {
        $this->setAwaitingReply($chatId, 'add_balance_reply', 'zarinpal');
        $this->telegramService->forceReply($chatId, $this->customTextCtrl->getText('action.process.add_online_balance.zarinpal.reply'));
        return "";
    }
    public function handleActionAddBalanceNowpayments(string $chatId): string
    {
        $this->setAwaitingReply($chatId, 'add_balance_reply', 'nowpayments');
        $this->telegramService->forceReply($chatId, $this->customTextCtrl->getText('action.process.add_online_balance.nowpayments.reply'));
        return "";
    }
    public function addBalanceReply(string $chatId, string $text): string
    {
        try {
            // check if text is valid int or float
        if (!is_numeric($text)) {
                 $this->telegramService->sendMessage($chatId, $this->customTextCtrl->getText('action.process.add_online_balance.zarinpal.reply.invalid_amount'));
            return "";
        }
        if ($text == null || trim($text) == 'لغو' || trim($text) == 'cancel') {
            $this->clearAwaitingReply($chatId, $this->customTextCtrl->getText('action.process.reply.cancel'));
            return "";
        }
        $user_state   = UserState::where('chat_id', $chatId)->latest()->first();
        $paymentType = $user_state->data;
        if ($paymentType == 'zarinpal') {
            // zarinpal => create a new invoice with amount
            $opr = [];
            $link =  $this->generalCntrl->createZarinpalPaymentLink($chatId, $text);
            array_push($opr, $link);
            $this->telegramService->sendMessageWithLinkButtons($chatId, $this->customTextCtrl->getText('action.process.add_online_balance.zarinpal.reply.invoice'), $opr);
            $this->clearAwaitingReply($chatId, $this->customTextCtrl->getText('action.process.add_online_balance.zarinpal.reply'));
            return "";
        } elseif($paymentType == "nowpayments") {
            $opr = [];
            $link =  $this->generalCntrl->createNowPaymentsLink($chatId, $text);
            array_push($opr, $link);

            $this->telegramService->sendMessageWithLinkButtons($chatId, $this->customTextCtrl->getText('action.process.add_online_balance.nowpayments.reply.invoice'), $opr);
            return "";


        }
        $this->clearAwaitingReply($chatId, $this->customTextCtrl->getText('action.process.add_online_balance.zarinpal.reply'));
        return "";
        } catch (\Throwable $th) {
            \Log::error(["addBalanceReply: " . $th]);
            $this->clearAwaitingReply($chatId, $this->customTextCtrl->getText('error.server_error'));
            return "";
        }
    }

     public function setAwaitingReply(string $chatId, string $type, string $paymentType): void
    {
        $user_state          = new UserState();
        $user_state->chat_id = $chatId;
        $user_state->state   = 'add_balance_reply';
        $user_state->data    = $paymentType;
        $user_state->save();

        // می‌توانید از کش یا دیتابیس استفاده کنید
        Cache::put("awaiting_reply_{$chatId}", $type, now()->addMinutes(5));
    }
    private function awaitingReply(string $chatId): bool
    {
        return Cache::has("awaiting_reply_{$chatId}");
    }
    private function getAwaitingReplyType(string $chatId): ?string
    {
        return Cache::get("awaiting_reply_{$chatId}");
    }
    private function clearAwaitingReply(string $chatId, string $text): void
    {
        try {
            \Log::info("clearAwaitingReply: " . $chatId . " - " . $text);
            Cache::forget("awaiting_reply_{$chatId}");
            // delete last user state where chat_id == $chatId
            $user_state = UserState::where('chat_id', $chatId)->latest()->first();
            if ($user_state != null) {
                $user_state->delete();
            }
        } catch (\Throwable $th) {
            \Log::error(["clearAwaitingReply: " . $th]);
        }
    }
    private function addNewBotLog($type, $message, $event)
    {
        $logCtrl = new LogController();
        $this->logCtrl->addNewLog($type, $message, $this->chatId, $this->botUser->username, $event);
        return true;
    }

}
