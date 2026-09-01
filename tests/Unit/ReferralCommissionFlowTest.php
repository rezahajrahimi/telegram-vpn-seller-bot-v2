<?php

namespace Tests\Unit;

use App\Http\Controllers\ReferralLogsController;
use App\Http\Controllers\ShetabVerifyController;
use App\Http\Controllers\TelegramWebhookController;
use App\Models\PaymentType;
use App\Models\ReferralLogs;
use App\Models\ReferralSetting;
use App\Models\ReferralWallet;
use App\Models\ShetabVerify;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralCommissionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance('telegram_bot', new class {
            public function sendMessage(...$args)
            {
                return true;
            }
        });

        ReferralSetting::create([
            'description' => 'test',
            'visit_card_text' => 'test',
            'referral_percent' => 20,
            'is_active' => true,
        ]);
    }

    public function test_extract_start_payload_keeps_referrer_chat_id(): void
    {
        $this->assertSame('111222333', TelegramWebhookController::extractStartPayload('/start 111222333'));
        $this->assertSame('111222333', TelegramWebhookController::extractStartPayload('/start@MyBot 111222333'));
        $this->assertNull(TelegramWebhookController::extractStartPayload('/start'));
        $this->assertNull(TelegramWebhookController::extractStartPayload('خرید اشتراک'));
    }

    public function test_channel_lock_resume_button_keeps_referral_payload(): void
    {
        $this->assertSame(
            '111222333',
            TelegramWebhookController::channelLockResumeStartParam('111222333')
        );
        $this->assertSame('start', TelegramWebhookController::channelLockResumeStartParam(null));
        $this->assertSame('start', TelegramWebhookController::channelLockResumeStartParam('bad payload'));
    }

    public function test_signup_referral_is_created_from_invite_link(): void
    {
        $referrer = $this->makeUser(111222333, 'referrer');
        $invitee = $this->makeUser(444555666, 'invitee');

        $controller = new ReferralLogsController();
        $this->assertTrue($controller->check_user_has_referral_and_create(
            $invitee->account_id,
            $referrer->account_id
        ));

        $this->assertDatabaseHas('referral_logs', [
            'referral_user_id' => $referrer->id,
            'referral_to_id' => $invitee->id,
            'amount' => 0,
            'transaction_id' => null,
        ]);
    }

    public function test_confirmed_deposit_credits_referrer_wallet(): void
    {
        $referrer = $this->makeUser(111222333, 'referrer');
        $invitee = $this->makeUser(444555666, 'invitee');
        $controller = new ReferralLogsController();
        $controller->check_user_has_referral_and_create($invitee->account_id, $referrer->account_id);

        $paymentType = PaymentType::create([
            'name' => 'offline',
            'type' => 'offline',
            'is_active' => true,
        ]);
        $transaction = new Transaction();
        $transaction->account_id = $invitee->account_id;
        $transaction->username = '';
        $transaction->amount = 660000;
        $transaction->payment_type_id = $paymentType->id;
        $transaction->confirmed = true;
        $transaction->recipe_number = 'T1';
        $transaction->save();

        $result = $controller->creditCommissionForDeposit($invitee->account_id, 660000, $transaction->id);

        $this->assertNotNull($result);
        $this->assertSame(132000.0, (float) $result->amount);
        $this->assertSame(132000, (int) ReferralWallet::where('referral_user_id', $referrer->id)->value('amount'));
        $this->assertTrue(
            ReferralLogs::where('referral_user_id', $referrer->id)
                ->where('referral_to_id', $invitee->id)
                ->where('amount', '>', 0)
                ->exists()
        );
    }

    public function test_shetab_verify_credits_referrer_after_payment(): void
    {
        $referrer = $this->makeUser(111222333, 'referrer');
        $invitee = $this->makeUser(444555666, 'invitee');
        (new ReferralLogsController())->check_user_has_referral_and_create(
            $invitee->account_id,
            $referrer->account_id
        );

        $shetabVerify = ShetabVerify::create([
            'user_id' => $invitee->id,
            'amount' => '100000',
            'base_amount' => '100000',
            'status' => 'verified',
        ]);

        (new ShetabVerifyController())->creditShetabDepositRewards($invitee, 100000, $shetabVerify);

        $this->assertSame(20000, (int) ReferralWallet::where('referral_user_id', $referrer->id)->value('amount'));
        $this->assertDatabaseHas('transactions', [
            'account_id' => $invitee->account_id,
            'amount' => 100000,
            'confirmed' => 1,
            'recipe_number' => 'SHETAB-' . $shetabVerify->id,
        ]);
    }

    public function test_deposit_without_invite_does_not_credit_anyone(): void
    {
        $invitee = $this->makeUser(444555666, 'invitee');
        $result = (new ReferralLogsController())->creditCommissionForDeposit($invitee->account_id, 100000);

        $this->assertNull($result);
        $this->assertSame(0, ReferralWallet::count());
    }

    private function makeUser(int $accountId, string $name): User
    {
        return User::create([
            'name' => $name,
            'account_id' => $accountId,
            'role' => 'user',
            'password' => 'secret',
        ]);
    }
}
