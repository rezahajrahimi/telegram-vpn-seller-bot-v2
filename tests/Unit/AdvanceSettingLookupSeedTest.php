<?php

namespace Tests\Unit;

use App\Http\Controllers\AdvanceSettingLookupController;
use App\Models\AdvanceSettingLookup;
use App\Services\BotKeyboardConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvanceSettingLookupSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_stores_full_button_style_rules_json(): void
    {
        (new AdvanceSettingLookupController())->seed();

        $value = AdvanceSettingLookup::query()
            ->where('name', BotKeyboardConfigService::SETTING_STYLE_RULES)
            ->value('value');

        $expected = json_encode(BotKeyboardConfigService::DEFAULT_STYLE_RULES, JSON_UNESCAPED_UNICODE);

        $this->assertSame($expected, $value);
        $this->assertGreaterThan(255, strlen((string) $value));
    }
}
