<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\ConfigNameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigNameServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_hiddify_name_uses_default_format(): void
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
            'config_name_format' => '{prefix}{account_label}',
        ]);

        $this->assertSame('vip12345-67', ConfigNameService::buildHiddifyName('12345-67'));
    }

    public function test_build_hiddify_name_uses_custom_format(): void
    {
        Setting::query()->create([
            'bot_name' => '@test',
            'admin_id' => 1,
            'bot_token' => 'token',
            'panel_address' => 'https://example.com',
            'welcome_message' => 'welcome',
            'config_name_prefix' => 'shop',
            'config_name_format' => '{prefix}-{account_id}-{product_id}',
        ]);

        $this->assertSame('shop-12345-67', ConfigNameService::buildHiddifyName('12345-67'));
    }

    public function test_build_sanaei_client_id_appends_random_when_missing_from_format(): void
    {
        $this->assertSame(
            'bot12345-67-abcd',
            ConfigNameService::buildSanaeiClientId('12345-67', 'abcd')
        );
    }

    public function test_build_sanaei_client_id_uses_random_placeholder(): void
    {
        Setting::query()->create([
            'bot_name' => '@test',
            'admin_id' => 1,
            'bot_token' => 'token',
            'panel_address' => 'https://example.com',
            'welcome_message' => 'welcome',
            'config_name_prefix' => 'bot',
            'config_name_format' => '{prefix}-{account_label}-{random}',
        ]);

        $this->assertSame(
            'bot-12345-67-abcd',
            ConfigNameService::buildSanaeiClientId('12345-67', 'abcd')
        );
    }

    public function test_build_marzban_fallback_uses_custom_format_with_chat_id(): void
    {
        Setting::query()->create([
            'bot_name' => '@test',
            'admin_id' => 1,
            'bot_token' => 'token',
            'panel_address' => 'https://example.com',
            'welcome_message' => 'welcome',
            'config_name_prefix' => 'bot',
            'config_name_format' => '{prefix}-{chat_id}-{product_id}',
        ]);

        $this->assertSame('bot-91965429-91', ConfigNameService::buildMarzbanFallbackUsername(91965429, 91));
    }

    public function test_build_marzban_fallback_uses_default_marzban_format(): void
    {
        $this->assertSame('bot9196542991', ConfigNameService::buildMarzbanFallbackUsername(91965429, 91));
    }

    public function test_normalize_prefix_strips_invalid_characters(): void
    {
        $this->assertSame('myshop', ConfigNameService::normalizePrefix('my-shop!'));
    }

    public function test_preview_renders_sample_name(): void
    {
        $this->assertSame(
            'vip-123456789-42',
            ConfigNameService::preview('{prefix}-{account_label}', 'vip')
        );
    }
}
