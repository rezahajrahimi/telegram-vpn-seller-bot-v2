<?php

namespace Tests\Feature;

use App\Http\Controllers\ShetabVerifyController;
use App\Models\PaymentSetting;
use App\Models\ShetabVerify;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShetabVerifyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_unique_amount_is_never_less_than_base(): void
    {
        $controller = new ShetabVerifyController();

        foreach ([111111, 50000, 109, 100, 99, 1500] as $base) {
            for ($i = 0; $i < 20; $i++) {
                $unique = $controller->create_uniqe_amount($base);
                $this->assertGreaterThanOrEqual(
                    $base,
                    $unique,
                    "Unique amount {$unique} was less than base {$base}"
                );
            }
        }
    }

    public function test_create_new_shetab_verify_reuses_pending_invoice_for_same_user(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'account_id' => 12345,
            'role' => 'user',
            'password' => 'secret',
        ]);

        ShetabVerify::create([
            'user_id' => $user->id,
            'amount' => '50045',
            'base_amount' => '50000',
            'status' => 'pending',
        ]);

        $controller = new ShetabVerifyController();
        $request = new \Illuminate\Http\Request();
        $request->amount = 50000;
        $request->user_id = $user->id;

        $this->assertSame('50045', $controller->create_new_shetab_verify($request));
    }

    public function test_create_new_shetab_verify_stores_auto_purchase_intent(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'account_id' => 12345,
            'role' => 'user',
            'password' => 'secret',
        ]);

        $controller = new ShetabVerifyController();
        $request = new \Illuminate\Http\Request();
        $request->amount = 50000;
        $request->user_id = $user->id;
        $request->product_category_id = 91;

        $amount = $controller->create_new_shetab_verify($request);

        $this->assertNotNull($amount);
        $record = ShetabVerify::first();
        $this->assertSame('91', (string) $record->product_category_id);
        $this->assertSame('50000', $record->base_amount);
    }

    public function test_validate_shetab_verify_rejects_invalid_api_key(): void
    {
        PaymentSetting::create([
            'key' => 'shetab_verify',
            'value' => 'secret-key',
            'status' => true,
            'description' => 'card',
        ]);

        $response = $this->postJson('/api/shetab-verify', [
            'amount' => '10000',
        ], [
            'Authorization' => 'wrong-key',
        ]);

        $response->assertUnauthorized();
    }
}
