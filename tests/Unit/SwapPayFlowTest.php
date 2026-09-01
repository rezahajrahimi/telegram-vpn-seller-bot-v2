<?php

namespace Tests\Unit;

use App\Http\Controllers\GeneralController;
use App\Http\Controllers\SwapPayController;
use App\Services\SwapPayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

class SwapPayFlowTest extends TestCase
{
    use RefreshDatabase;
    public function test_pick_payment_url_prefers_requested_type_and_skips_invalid(): void
    {
        $links = [
            ['type' => 'TELEGRAM_BOT', 'url' => ''],
            ['type' => 'TELEGRAM_WEBAPP', 'url' => 'javascript:void(0)'],
            ['type' => 'WEBSITE', 'url' => 'https://swapwallet.app/pay/abc'],
        ];

        $this->assertSame(
            'https://swapwallet.app/pay/abc',
            SwapPayService::pickPaymentUrl($links, ['TELEGRAM_BOT', 'TELEGRAM_WEBAPP', 'WEBSITE'])
        );
    }

    public function test_pick_payment_url_accepts_telegram_deep_link(): void
    {
        $links = [
            ['type' => 'TELEGRAM_BOT', 'url' => 'tg://resolve?domain=swapwallet&start=inv1'],
            ['type' => 'WEBSITE', 'url' => 'https://swapwallet.app/pay/abc'],
        ];

        $this->assertSame(
            'tg://resolve?domain=swapwallet&start=inv1',
            SwapPayService::pickPaymentUrl($links, ['TELEGRAM_BOT', 'WEBSITE'])
        );
    }

    public function test_paid_status_aliases(): void
    {
        $this->assertTrue(SwapPayService::isPaidStatus('PAID'));
        $this->assertTrue(SwapPayService::isPaidStatus('paid'));
        $this->assertTrue(SwapPayService::isPaidStatus('SUCCESS'));
        $this->assertFalse(SwapPayService::isPaidStatus('ACTIVE'));
        $this->assertFalse(SwapPayService::isPaidStatus('pending'));
    }

    public function test_personal_account_id_and_username_are_rejected(): void
    {
        $whoami = ['id' => 512834, 'username' => 'rezahajrahimi'];

        $this->assertTrue(SwapPayService::isPersonalAccountIdentifier('512834', $whoami));
        $this->assertTrue(SwapPayService::isPersonalAccountIdentifier('rezahajrahimi', $whoami));
        $this->assertTrue(SwapPayService::isPersonalAccountIdentifier('RezaHajRahimi', $whoami));
        $this->assertFalse(SwapPayService::isPersonalAccountIdentifier('my-shop', $whoami));
    }

    public function test_extract_crypto_payment_url_from_json_error_is_null(): void
    {
        $controller = new GeneralController();
        $response = new JsonResponse([
            'success' => false,
            'message' => 'تنظیمات SwapPay کامل نیست.',
        ], 500);

        $this->assertNull($controller->extractCryptoPaymentUrl($response));
        $this->assertSame('https://example.com/pay', $controller->extractCryptoPaymentUrl('https://example.com/pay'));
        $this->assertNull($controller->extractCryptoPaymentUrl(''));
    }

    public function test_swappay_return_without_ids_returns_404(): void
    {
        $controller = new SwapPayController();
        $response = $controller->handleReturn(Request::create('/swappay/return', 'GET'));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_swappay_return_reads_post_body_ids(): void
    {
        $controller = new SwapPayController();
        $request = Request::create('/swappay/return', 'POST', [
            'invoice_id' => 'missing-invoice',
            'order_id' => 'missing-order',
        ]);

        $response = $controller->handleReturn($request);

        $this->assertSame(404, $response->getStatusCode());
    }
}
