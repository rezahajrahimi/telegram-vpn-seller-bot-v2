<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\ConfigNameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigNameServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_hiddify_name_uses_default_prefix(): void
    {
        $this->assertSame('bot12345-67', ConfigNameService::buildHiddifyName('12345-67'));
    }

    public function test_build_hiddify_name_uses_custom_prefix_from_settings(): void
    {
        Setting::query()->create([
            'bot_name' => '@test',
            'admin_id' => 1,
            'bot_token' => 'token',
            'panel_address' => 'https://example.com',
            'welcome_message' => 'welcome',
            'config_name_prefix' => 'vip',
        ]);

        $this->assertSame('vip12345-67', ConfigNameService::buildHiddifyName('12345-67'));
    }

    public function test_normalize_prefix_strips_invalid_characters(): void
    {
        $this->assertSame('myshop', ConfigNameService::normalizePrefix('my-shop!'));
    }
}
