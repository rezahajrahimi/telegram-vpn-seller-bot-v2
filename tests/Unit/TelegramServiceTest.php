<?php

namespace Tests\Unit;

use App\Services\BotKeyboardConfigService;
use App\Services\TelegramService;
use ReflectionMethod;
use Tests\TestCase;

class TelegramServiceTest extends TestCase
{
    public function test_is_cancel_or_exit_text(): void
    {
        $service = new TelegramService();

        $this->assertTrue($service->isCancelOrExitText('لغو'));
        $this->assertTrue($service->isCancelOrExitText('  لغو  '));
        $this->assertTrue($service->isCancelOrExitText('cancel'));
        $this->assertTrue($service->isCancelOrExitText('/start'));
        $this->assertTrue($service->isCancelOrExitText('/start ref123'));
        $this->assertTrue($service->isCancelOrExitText('/restart'));
        $this->assertFalse($service->isCancelOrExitText('660000'));
        $this->assertFalse($service->isCancelOrExitText('aria2026'));
        $this->assertFalse($service->isCancelOrExitText(''));
    }

    public function test_parse_numeric_amount_accepts_persian_digits_and_decimals(): void
    {
        $service = new TelegramService();

        $this->assertSame(5.0, $service->parseNumericAmount('5'));
        $this->assertSame(1.5, $service->parseNumericAmount('1.5'));
        $this->assertSame(1.5, $service->parseNumericAmount('۱٫۵'));
        $this->assertSame(10.0, $service->parseNumericAmount('۱۰'));
        $this->assertSame(1000000.0, $service->parseNumericAmount('1,000,000 تومان'));
        $this->assertNull($service->parseNumericAmount('abc'));
        $this->assertNull($service->parseNumericAmount('0'));
        $this->assertNull($service->parseNumericAmount(''));
    }

    public function test_inline_url_button_validation(): void
    {
        $this->assertTrue(TelegramService::isInlineUrlButtonValid('https://swapwallet.app/pay/1'));
        $this->assertTrue(TelegramService::isInlineUrlButtonValid('http://example.com'));
        $this->assertTrue(TelegramService::isInlineUrlButtonValid('tg://resolve?domain=swapwallet'));
        $this->assertFalse(TelegramService::isInlineUrlButtonValid(''));
        $this->assertFalse(TelegramService::isInlineUrlButtonValid('javascript:alert(1)'));
        $this->assertFalse(TelegramService::isInlineUrlButtonValid(null));
    }

    public function test_cancel_reply_button_has_no_callback_data(): void
    {
        $service = new TelegramService();
        $method = new ReflectionMethod(TelegramService::class, 'formatKeyboardButtons');
        $method->setAccessible(true);

        $keyboard = $method->invoke($service, [['لغو']]);

        $this->assertSame('لغو', $keyboard[0][0]['text'] ?? null);
        $this->assertArrayNotHasKey('callback_data', $keyboard[0][0]);
    }

    public function test_format_keyboard_buttons_strips_callback_data(): void
    {
        $service = new TelegramService();
        $method = new ReflectionMethod(TelegramService::class, 'formatKeyboardButtons');
        $method->setAccessible(true);

        $keyboard = $method->invoke($service, [
            [
                ['text' => 'خرید اشتراک', 'callback_data' => 'main-1'],
                ['text' => 'پشتیبانی', 'callback_data' => 'main-4'],
            ],
        ]);

        $this->assertSame([
            [
                ['text' => 'خرید اشتراک'],
                ['text' => 'پشتیبانی'],
            ],
        ], $keyboard);
    }

    public function test_format_inline_keyboard_applies_style_rules(): void
    {
        $keyboardConfig = new BotKeyboardConfigService(forceCustomizationEnabled: true);
        $service = new TelegramService($keyboardConfig);
        $keyboard = $service->formatInlineKeyboardButtons([
            ['تایید' => 'confirmBuy-12'],
            ['لغو' => 'cancelReceipt-9'],
        ]);

        $this->assertSame('success', $keyboard[0][0]['style'] ?? null);
        $this->assertSame('danger', $keyboard[1][0]['style'] ?? null);
    }

    public function test_detects_unreachable_chat_errors(): void
    {
        $service = new TelegramService();

        $this->assertTrue($service->isUnreachableChatError([
            'ok' => false,
            'error_code' => 400,
            'description' => 'Bad Request: chat not found',
        ]));

        $this->assertTrue($service->isUnreachableChatError([
            'ok' => false,
            'error_code' => 403,
            'description' => 'Forbidden: bot was blocked by the user',
        ]));

        $this->assertFalse($service->isUnreachableChatError([
            'ok' => false,
            'error_code' => 400,
            'description' => 'Bad Request: message text is empty',
        ]));
    }
}
