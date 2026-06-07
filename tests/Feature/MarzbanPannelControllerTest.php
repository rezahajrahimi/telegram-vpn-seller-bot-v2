<?php

namespace Tests\Feature;

use App\Http\Controllers\MarzbanPannelController;
use App\Models\Pannel;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarzbanPannelControllerTest extends TestCase
{
    public function test_create_user_builds_subscription_link(): void
    {
        $panel = Pannel::create([
            'type' => 'marzban',
            'url_port' => 'https://panel.example.com/dashboard/',
            'token' => 'Bearer test-token',
            'name' => 'test-marzban',
        ]);

        Http::fake([
            'https://panel.example.com/api/user' => Http::response([
                'username' => 'BotUser123',
                'subscription_url' => '/sub/abc123',
                'links' => ['vless://example'],
            ], 200),
        ]);

        $controller = new MarzbanPannelController();
        $result = $controller->createUser($panel, 'BotUser123', 30, 10);

        $this->assertIsArray($result);
        $this->assertSame('https://panel.example.com/sub/abc123', $result['subscription_link']);
        $this->assertSame(['vless://example'], $result['links']);
    }

    public function test_get_client_status_returns_unified_fields(): void
    {
        $panel = Pannel::create([
            'type' => 'marzban',
            'url_port' => 'https://panel.example.com',
            'token' => 'Bearer test-token',
            'name' => 'test-marzban-2',
        ]);

        Http::fake([
            'https://panel.example.com/api/user/test-user' => Http::response([
                'username' => 'test-user',
                'status' => 'active',
                'used_traffic' => 1073741824,
                'data_limit' => 10737418240,
                'expire' => now()->addDays(10)->timestamp,
            ], 200),
        ]);

        $controller = new MarzbanPannelController();
        $status = $controller->getClientStatus($panel, 'test-user');

        $this->assertNotNull($status);
        $this->assertTrue($status['enable']);
        $this->assertSame(1.0, $status['current_usage_GB']);
        $this->assertSame(10.0, $status['usage_limit_GB']);
        $this->assertTrue($status['marzban']);
    }

    public function test_get_all_users_returns_cron_format(): void
    {
        $panel = Pannel::create([
            'type' => 'marzban',
            'url_port' => 'https://panel.example.com',
            'token' => 'Bearer test-token',
            'name' => 'test-marzban-3',
        ]);

        Http::fake([
            'https://panel.example.com/api/users*' => Http::response([
                'users' => [
                    [
                        'username' => 'BotUser123',
                        'status' => 'active',
                        'used_traffic' => 536870912,
                        'data_limit' => 10737418240,
                        'expire' => now()->addDays(5)->timestamp,
                    ],
                ],
                'total' => 1,
            ], 200),
        ]);

        $controller = new MarzbanPannelController();
        $users = $controller->getAllUsers($panel);

        $this->assertCount(1, $users);
        $this->assertSame('BotUser123', $users[0]['uuid']);
        $this->assertSame(0.5, $users[0]['current_usage_GB']);
    }
}
