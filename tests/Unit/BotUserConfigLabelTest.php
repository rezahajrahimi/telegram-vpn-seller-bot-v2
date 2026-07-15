<?php

namespace Tests\Unit;

use App\Models\BotUser;
use App\Models\Setting;
use App\Services\ConfigNameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotUserConfigLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_config_account_label_uses_admin_alias_when_enabled(): void
    {
        Setting::query()->create([
            'bot_name' => '@test',
            'admin_id' => 1,
            'bot_token' => 'token',
            'panel_address' => 'https://example.com',
            'welcome_message' => 'welcome',
            'use_admin_alias_in_config_name' => true,
        ]);

        BotUser::query()->create([
            'account_id' => 123456789,
            'admin_alias' => 'ali',
        ]);

        $this->assertSame('ali-42', BotUser::resolveConfigAccountLabel(123456789, 42));
    }

    public function test_resolve_config_account_label_ignores_admin_alias_when_disabled(): void
    {
        Setting::query()->create([
            'bot_name' => '@test',
            'admin_id' => 1,
            'bot_token' => 'token',
            'panel_address' => 'https://example.com',
            'welcome_message' => 'welcome',
            'use_admin_alias_in_config_name' => false,
        ]);

        BotUser::query()->create([
            'account_id' => 123456789,
            'admin_alias' => 'ali',
        ]);

        $this->assertSame('123456789-42', BotUser::resolveConfigAccountLabel(123456789, 42));
    }

    public function test_resolve_panel_account_label_ignores_stale_alias_when_chat_id_present(): void
    {
        Setting::query()->create([
            'bot_name' => '@test',
            'admin_id' => 1,
            'bot_token' => 'token',
            'panel_address' => 'https://example.com',
            'welcome_message' => 'welcome',
            'use_admin_alias_in_config_name' => false,
        ]);

        BotUser::query()->create([
            'account_id' => 123456789,
            'admin_alias' => 'ali',
        ]);

        $this->assertSame(
            '123456789-42',
            ConfigNameService::resolvePanelAccountLabel(123456789, 42, 'ali-42')
        );
    }
}
