<?php

namespace Tests\Unit;

use App\Services\BotKeyboardConfigService;
use App\Services\PackageButtonLayoutService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PackageButtonLayoutServiceTest extends TestCase
{
    public function test_multi_column_layout_builds_two_column_rows(): void
    {
        $service = new PackageButtonLayoutService();
        $categories = collect([
            (object) [
                'id' => 7,
                'category_name' => 'تست مرزبان',
                'price' => 120000,
                'price_in_dollar' => 2.5,
            ],
        ]);

        $reflection = new \ReflectionClass(PackageButtonLayoutService::class);
        $method = $reflection->getMethod('buildMultiColumnLayout');
        $method->setAccessible(true);

        $result = $method->invoke($service, $categories, false, 'بسته خود را انتخاب کنید.');
        $keyboard = (new BotKeyboardConfigService(forceCustomizationEnabled: true))
            ->formatInlineKeyboard($result['buttons'], null, false);

        $this->assertCount(2, $keyboard);
        $this->assertCount(2, $keyboard[0]);
        $this->assertCount(2, $keyboard[1]);
        $this->assertSame('120,000', $keyboard[1][0]['text'] ?? null);
        $this->assertSame('تست مرزبان', $keyboard[1][1]['text'] ?? null);
    }
}
