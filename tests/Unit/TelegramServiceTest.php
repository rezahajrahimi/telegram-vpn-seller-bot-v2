<?php

namespace Tests\Unit;

use App\Services\BotKeyboardConfigService;
use App\Services\TelegramService;
use ReflectionMethod;
use Tests\TestCase;

class TelegramServiceTest extends TestCase
{
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
