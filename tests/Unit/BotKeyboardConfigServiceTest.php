<?php

namespace Tests\Unit;

use App\Services\BotKeyboardConfigService;
use Tests\TestCase;

class BotKeyboardConfigServiceTest extends TestCase
{
    public function test_regroups_single_button_rows(): void
    {
        $service = new BotKeyboardConfigService(forceCustomizationEnabled: true);
        $rows = $service->formatInlineKeyboard([
            ['یک' => 'a-1'],
            ['دو' => 'a-2'],
            ['سه' => 'a-3'],
        ], 2);

        $this->assertCount(2, $rows);
        $this->assertCount(2, $rows[0]);
        $this->assertCount(1, $rows[1]);
    }

    public function test_does_not_regroup_multi_button_rows(): void
    {
        $service = new BotKeyboardConfigService();
        $rows = $service->formatInlineKeyboard([
            [
                'قیمت' => '0',
                'بسته' => '0',
            ],
            [
                '1000' => 'buySubscription-1',
                'طلایی' => 'buySubscription-1',
            ],
        ], 3, true);

        $this->assertCount(2, $rows);
        $this->assertCount(2, $rows[0]);
    }

    public function test_reply_button_supports_style_fields(): void
    {
        $service = new BotKeyboardConfigService(forceCustomizationEnabled: true);
        $rows = $service->formatReplyKeyboard([
            [
                [
                    'text' => 'خرید',
                    'style' => 'primary',
                    'icon_custom_emoji_id' => '123456',
                ],
            ],
        ]);

        $this->assertSame('primary', $rows[0][0]['style'] ?? null);
        $this->assertSame('123456', $rows[0][0]['icon_custom_emoji_id'] ?? null);
    }

    public function test_preserves_numeric_price_labels_in_multi_column_rows(): void
    {
        $service = new BotKeyboardConfigService();
        $rows = $service->formatInlineKeyboard([
            [
                120000 => 'buySubscription-1',
                'تست مرزبان' => 'buySubscription-1',
            ],
        ], null, false);

        $this->assertCount(1, $rows);
        $this->assertCount(2, $rows[0]);
        $this->assertSame('120000', $rows[0][0]['text'] ?? null);
        $this->assertSame('تست مرزبان', $rows[0][1]['text'] ?? null);
    }
}
