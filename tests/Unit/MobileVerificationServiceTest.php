<?php

namespace Tests\Unit;

use App\Models\AdvanceSettingLookup;
use App\Models\BotUser;
use App\Models\User;
use App\Services\MobileVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MobileVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_from_contact_sets_user_verified_when_contact_matches_sender(): void
    {
        AdvanceSettingLookup::query()->create([
            'name' => MobileVerificationService::SETTING_KEY,
            'value' => 'true',
            'description' => 'test',
        ]);

        $accountId = 123456789;
        BotUser::query()->create([
            'account_id' => $accountId,
            'username' => 'tester',
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);

        User::query()->create([
            'name' => 'tester',
            'account_id' => $accountId,
            'role' => 'user',
            'password' => Hash::make('password'),
            'is_verified' => false,
        ]);

        $service = new MobileVerificationService();
        $result = $service->verifyFromContact(
            $accountId,
            [
                'phone_number' => '+989121234567',
                'first_name' => 'Test',
                'last_name' => 'User',
                'user_id' => $accountId,
            ],
            ['id' => $accountId]
        );

        $this->assertTrue($result['success']);

        $user = User::where('account_id', $accountId)->first();
        $botUser = BotUser::where('account_id', $accountId)->first();

        $this->assertTrue($user->is_verified);
        $this->assertSame('+989121234567', $botUser->phone_number);
    }

    public function test_verify_from_contact_rejects_foreign_contact(): void
    {
        AdvanceSettingLookup::query()->create([
            'name' => MobileVerificationService::SETTING_KEY,
            'value' => 'true',
            'description' => 'test',
        ]);

        $accountId = 123456789;
        User::query()->create([
            'name' => 'tester',
            'account_id' => $accountId,
            'role' => 'user',
            'password' => Hash::make('password'),
            'is_verified' => false,
        ]);

        $service = new MobileVerificationService();
        $result = $service->verifyFromContact(
            $accountId,
            [
                'phone_number' => '+989121234567',
                'first_name' => 'Other',
                'user_id' => 999999999,
            ],
            ['id' => $accountId]
        );

        $this->assertFalse($result['success']);
        $this->assertFalse((bool) User::where('account_id', $accountId)->value('is_verified'));
    }

    public function test_iran_only_rejects_non_iranian_phone(): void
    {
        AdvanceSettingLookup::query()->create([
            'name' => MobileVerificationService::SETTING_KEY,
            'value' => 'true',
            'description' => 'test',
        ]);
        AdvanceSettingLookup::query()->create([
            'name' => MobileVerificationService::IRAN_ONLY_SETTING_KEY,
            'value' => 'true',
            'description' => 'test',
        ]);

        $accountId = 123456789;
        User::query()->create([
            'name' => 'tester',
            'account_id' => $accountId,
            'role' => 'user',
            'password' => Hash::make('password'),
            'is_verified' => false,
        ]);

        $service = new MobileVerificationService();
        $result = $service->verifyFromContact(
            $accountId,
            [
                'phone_number' => '+14155552671',
                'first_name' => 'Test',
                'user_id' => $accountId,
            ],
            ['id' => $accountId]
        );

        $this->assertFalse($result['success']);
        $this->assertFalse((bool) User::where('account_id', $accountId)->value('is_verified'));
    }

    /**
     * @dataProvider iranianPhoneProvider
     */
    public function test_is_iranian_phone_number(string $phone, bool $expected): void
    {
        $service = new MobileVerificationService();
        $this->assertSame($expected, $service->isIranianPhoneNumber($phone));
    }

    public static function iranianPhoneProvider(): array
    {
        return [
            ['+989121234567', true],
            ['989121234567', true],
            ['09121234567', true],
            ['9121234567', true],
            ['+98 912 123 4567', true],
            ['+14155552671', false],
            ['442071838750', false],
        ];
    }
}
