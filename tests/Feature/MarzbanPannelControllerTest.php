<?php

namespace Tests\Feature;

use App\Http\Controllers\MarzbanPannelController;
use App\Http\Controllers\PannelController;
use App\Models\Inbound;
use App\Models\Pannel;
use App\Models\Proxy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarzbanPannelControllerTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_URL = 'https://panel.example.com';

    private function fakeMarzbanInbounds(array $inboundsByProtocol = ['vless' => ['VLESS TCP REALITY']]): void
    {
        Http::fake([
            self::BASE_URL . '/api/inbounds' => Http::response($inboundsByProtocol, 200),
        ]);
    }

    private function createMarzbanPanel(array $overrides = []): Pannel
    {
        $panel = Pannel::create(array_merge([
            'type' => 'marzban',
            'url_port' => self::BASE_URL . '/dashboard/',
            'username' => 'admin',
            'password' => 'secret',
            'token' => 'Bearer expired-token',
        ], $overrides));

        $proxy = Proxy::create([
            'pannel_id' => $panel->id,
            'type' => 'vless',
            'is_active' => true,
        ]);

        $inbound = new Inbound();
        $inbound->proxy_id = $proxy->id;
        $inbound->name = 'VLESS TCP REALITY';
        $inbound->data = 'VLESS TCP REALITY';
        $inbound->is_active = true;
        $inbound->save();

        return $panel->fresh();
    }

    public function test_create_user_ignores_disabled_inbounds(): void
    {
        $panel = Pannel::create([
            'type' => 'marzban',
            'url_port' => self::BASE_URL,
            'token' => 'Bearer test-token',
        ]);

        $proxy = Proxy::create([
            'pannel_id' => $panel->id,
            'type' => 'vless',
            'is_active' => true,
        ]);

        $activeInbound = new Inbound();
        $activeInbound->proxy_id = $proxy->id;
        $activeInbound->name = 'VLESS ACTIVE';
        $activeInbound->data = 'VLESS ACTIVE';
        $activeInbound->is_active = true;
        $activeInbound->save();

        $disabledInbound = new Inbound();
        $disabledInbound->proxy_id = $proxy->id;
        $disabledInbound->name = 'VLESS DISABLED';
        $disabledInbound->data = 'VLESS DISABLED';
        $disabledInbound->is_active = false;
        $disabledInbound->save();

        Http::fake([
            self::BASE_URL . '/api/inbounds' => Http::response([
                'vless' => ['VLESS ACTIVE'],
            ], 200),
            self::BASE_URL . '/api/user' => Http::response([
                'username' => 'BotUser123',
                'subscription_url' => '/sub/abc123',
                'links' => [],
            ], 200),
        ]);

        (new MarzbanPannelController())->createUser($panel, 'BotUser123', 30, 10);

        Http::assertSent(function ($request) {
            return $request->url() === self::BASE_URL . '/api/user'
                && $request->data()['inbounds']['vless'] === ['VLESS ACTIVE'];
        });
    }

    public function test_create_user_skips_inbounds_missing_on_live_panel(): void
    {
        $panel = Pannel::create([
            'type' => 'marzban',
            'url_port' => self::BASE_URL,
            'token' => 'Bearer test-token',
        ]);

        $vlessProxy = Proxy::create([
            'pannel_id' => $panel->id,
            'type' => 'vless',
            'is_active' => true,
        ]);
        $vlessInbound = new Inbound();
        $vlessInbound->proxy_id = $vlessProxy->id;
        $vlessInbound->name = 'VLESS ACTIVE';
        $vlessInbound->data = 'VLESS ACTIVE';
        $vlessInbound->is_active = true;
        $vlessInbound->save();

        $ssProxy = Proxy::create([
            'pannel_id' => $panel->id,
            'type' => 'shadowsocks',
            'is_active' => true,
        ]);
        $ssInbound = new Inbound();
        $ssInbound->proxy_id = $ssProxy->id;
        $ssInbound->name = 'Shadowsocks TCP';
        $ssInbound->data = 'Shadowsocks TCP';
        $ssInbound->is_active = true;
        $ssInbound->save();

        Http::fake([
            self::BASE_URL . '/api/inbounds' => Http::response([
                'vless' => ['VLESS ACTIVE'],
            ], 200),
            self::BASE_URL . '/api/user' => Http::response([
                'username' => 'BotUser123',
                'subscription_url' => '/sub/abc123',
                'links' => [],
            ], 200),
        ]);

        (new MarzbanPannelController())->createUser($panel, 'BotUser123', 30, 10);

        Http::assertSent(function ($request) {
            if ($request->url() !== self::BASE_URL . '/api/user') {
                return false;
            }

            $inbounds = $request->data()['inbounds'] ?? [];

            return ($inbounds['vless'] ?? []) === ['VLESS ACTIVE']
                && ! array_key_exists('shadowsocks', $inbounds);
        });
    }

    public function test_create_user_retries_after_invalid_inbound_error(): void
    {
        $panel = $this->createMarzbanPanel();

        Http::fake([
            self::BASE_URL . '/api/inbounds' => Http::response([
                'shadowsocks' => ['Shadowsocks TCP'],
                'vless' => ['VLESS TCP REALITY'],
            ], 200),
            self::BASE_URL . '/api/user' => Http::sequence()
                ->push([
                    'detail' => [
                        'inbounds' => "Value error, Inbound {tag: Shadowsocks TCP, protocol: shadowsocks, network: tcp, tls: none, port: 1080} doesn't exist",
                    ],
                ], 422)
                ->push([
                    'username' => 'BotUser123',
                    'subscription_url' => '/sub/abc123',
                    'links' => ['vless://example'],
                ], 200),
        ]);

        $result = (new MarzbanPannelController())->createUser($panel, 'BotUser123', 30, 10);

        $this->assertIsArray($result);
        Http::assertSent(function ($request) {
            return $request->url() === self::BASE_URL . '/api/user'
                && $request->method() === 'POST'
                && ($request->data()['inbounds']['vless'] ?? null) === ['VLESS TCP REALITY']
                && ! array_key_exists('shadowsocks', $request->data()['inbounds'] ?? []);
        });
    }

    public function test_create_user_returns_existing_user_after_username_conflict(): void
    {
        $panel = $this->createMarzbanPanel();

        Http::fake([
            self::BASE_URL . '/api/inbounds' => Http::response([
                'vless' => ['VLESS TCP REALITY'],
            ], 200),
            self::BASE_URL . '/api/user' => Http::response(['detail' => 'User already exists'], 409),
            self::BASE_URL . '/api/user/BotUser123' => Http::response([
                'username' => 'BotUser123',
                'subscription_url' => '/sub/existing123',
                'links' => ['vless://existing'],
            ], 200),
        ]);

        $result = (new MarzbanPannelController())->createUser($panel, 'BotUser123', 30, 10);

        $this->assertIsArray($result);
        $this->assertSame('BotUser123', $result['username']);
        $this->assertSame('https://panel.example.com/sub/existing123', $result['subscription_link']);

        Http::assertSentCount(3);
    }

    public function test_create_user_retries_after_username_conflict(): void
    {
        $panel = $this->createMarzbanPanel();

        Http::fake([
            self::BASE_URL . '/api/inbounds' => Http::response([
                'vless' => ['VLESS TCP REALITY'],
            ], 200),
            self::BASE_URL . '/api/user/BotUser123' => Http::response(null, 404),
            self::BASE_URL . '/api/user' => Http::sequence()
                ->push(['detail' => 'User already exists'], 409)
                ->push([
                    'username' => 'BotUser123a1b2',
                    'subscription_url' => '/sub/conflict123',
                    'links' => [],
                ], 200),
        ]);

        $result = (new MarzbanPannelController())->createUser($panel, 'BotUser123', 30, 10);

        $this->assertIsArray($result);
        $this->assertMatchesRegularExpression('/^BotUser123[a-f0-9]{4}$/', $result['username']);
        $this->assertSame('https://panel.example.com/sub/conflict123', $result['subscription_link']);

        $requests = collect(Http::recorded())
            ->filter(fn ($pair) => $pair[0]->url() === self::BASE_URL . '/api/user')
            ->values();

        $this->assertCount(2, $requests);
        $this->assertSame('BotUser123', $requests[0][0]->data()['username']);
        $this->assertMatchesRegularExpression('/^BotUser123[a-f0-9]{4}$/', $requests[1][0]->data()['username']);
    }

    public function test_sanitize_username_keeps_only_alphanumeric_characters(): void
    {
        $controller = new MarzbanPannelController();

        $this->assertSame('bot91965429', $controller->sanitizeUsername('bot91965429 اکانت_آزمایشی'));
        $this->assertSame('bot9196542991', $controller->buildBotUsername(91965429, 91));
        $this->assertSame('bot91965429Test', $controller->buildTestAccountUsername(91965429));
    }

    public function test_create_user_builds_subscription_link(): void
    {
        $panel = $this->createMarzbanPanel();

        Http::fake([
            self::BASE_URL . '/api/inbounds' => Http::response([
                'vless' => ['VLESS TCP REALITY'],
            ], 200),
            self::BASE_URL . '/api/user' => Http::response([
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

        Http::assertSent(function ($request) {
            if ($request->url() !== self::BASE_URL . '/api/user') {
                return false;
            }

            $body = $request->data();
            return $body['username'] === 'BotUser123'
                && $body['status'] === 'active'
                && $body['data_limit'] === 10737418240
                && isset($body['proxies']['vless'])
                && $body['inbounds']['vless'] === ['VLESS TCP REALITY'];
        });
    }

    public function test_get_user_returns_panel_user(): void
    {
        $panel = $this->createMarzbanPanel();

        Http::fake([
            self::BASE_URL . '/api/user/test-user' => Http::response([
                'username' => 'test-user',
                'status' => 'active',
            ], 200),
        ]);

        $user = (new MarzbanPannelController())->getUser($panel, 'test-user');

        $this->assertIsArray($user);
        $this->assertSame('test-user', $user['username']);
    }

    public function test_modify_user_updates_limits_and_resets_traffic(): void
    {
        $panel = $this->createMarzbanPanel();

        Http::fake([
            self::BASE_URL . '/api/inbounds' => Http::response([
                'vless' => ['VLESS TCP REALITY'],
            ], 200),
            self::BASE_URL . '/api/user/test-user' => Http::response([
                'username' => 'test-user',
                'status' => 'active',
            ], 200),
            self::BASE_URL . '/api/user/test-user/reset' => Http::response([
                'username' => 'test-user',
            ], 200),
        ]);

        $controller = new MarzbanPannelController();
        $this->assertTrue($controller->modifyUser($panel, 'test-user', 15, 5));

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && $request->url() === self::BASE_URL . '/api/user/test-user'
                && $request->data()['data_limit'] === 5368709120;
        });

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === self::BASE_URL . '/api/user/test-user/reset';
        });
    }

    public function test_update_limits_and_recharge_user_delegate_to_modify_user(): void
    {
        $panel = $this->createMarzbanPanel();

        Http::fake([
            self::BASE_URL . '/api/inbounds' => Http::response([
                'vless' => ['VLESS TCP REALITY'],
            ], 200),
            self::BASE_URL . '/api/user/test-user' => Http::response([
                'username' => 'test-user',
                'status' => 'active',
            ], 200),
            self::BASE_URL . '/api/user/test-user/reset' => Http::response([
                'username' => 'test-user',
            ], 200),
        ]);

        $controller = new MarzbanPannelController();
        $this->assertTrue($controller->updateLimits($panel, 'test-user', 20, 8));
        $this->assertTrue($controller->rechargeUser($panel, 'test-user', 20, 8));
    }

    public function test_reset_traffic_calls_reset_endpoint(): void
    {
        $panel = $this->createMarzbanPanel();

        Http::fake([
            self::BASE_URL . '/api/user/test-user/reset' => Http::response([
                'username' => 'test-user',
            ], 200),
        ]);

        $this->assertTrue((new MarzbanPannelController())->resetTraffic($panel, 'test-user'));
    }

    public function test_delete_user_calls_delete_endpoint(): void
    {
        $panel = $this->createMarzbanPanel();

        Http::fake([
            self::BASE_URL . '/api/user/test-user' => Http::response(null, 200),
        ]);

        $this->assertTrue((new MarzbanPannelController())->deleteUser($panel, 'test-user'));
    }

    public function test_change_user_activation_toggles_status(): void
    {
        $panel = $this->createMarzbanPanel();

        Http::fake([
            self::BASE_URL . '/api/user/test-user' => Http::sequence()
                ->push(['username' => 'test-user', 'status' => 'disabled'], 200)
                ->push(['username' => 'test-user', 'status' => 'active'], 200),
        ]);

        $controller = new MarzbanPannelController();
        $this->assertTrue($controller->changeUserActivation($panel, 'test-user', false));
        $this->assertTrue($controller->changeUserActivation($panel, 'test-user', true));

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && $request->data()['status'] === 'disabled';
        });
    }

    public function test_rename_user_updates_username(): void
    {
        $panel = $this->createMarzbanPanel();

        Http::fake([
            self::BASE_URL . '/api/user/old-name' => Http::response([
                'username' => 'new-name',
            ], 200),
        ]);

        $this->assertTrue((new MarzbanPannelController())->renameUser($panel, 'old-name', 'new-name'));
    }

    public function test_get_client_status_returns_unified_fields(): void
    {
        $panel = $this->createMarzbanPanel();

        Http::fake([
            self::BASE_URL . '/api/user/test-user' => Http::response([
                'username' => 'test-user',
                'status' => 'active',
                'used_traffic' => 1073741824,
                'data_limit' => 10737418240,
                'expire' => now()->addDays(10)->timestamp,
            ], 200),
        ]);

        $status = (new MarzbanPannelController())->getClientStatus($panel, 'test-user');

        $this->assertNotNull($status);
        $this->assertTrue($status['enable']);
        $this->assertSame(1.0, $status['current_usage_GB']);
        $this->assertSame(10.0, $status['usage_limit_GB']);
        $this->assertTrue($status['marzban']);
    }

    public function test_get_subscription_link_builds_full_url(): void
    {
        $panel = $this->createMarzbanPanel();

        Http::fake([
            self::BASE_URL . '/api/user/test-user' => Http::response([
                'username' => 'test-user',
                'subscription_url' => '/sub/xyz',
            ], 200),
        ]);

        $link = (new MarzbanPannelController())->getSubscriptionLink($panel, 'test-user');

        $this->assertSame('https://panel.example.com/sub/xyz', $link);
    }

    public function test_get_all_users_returns_cron_format(): void
    {
        $panel = $this->createMarzbanPanel();

        Http::fake([
            self::BASE_URL . '/api/users*' => Http::response([
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

        $users = (new MarzbanPannelController())->getAllUsers($panel);

        $this->assertCount(1, $users);
        $this->assertSame('BotUser123', $users[0]['uuid']);
        $this->assertSame(0.5, $users[0]['current_usage_GB']);
        $this->assertTrue($users[0]['is_active']);
    }

    public function test_refreshes_token_on_unauthorized_and_retries_request(): void
    {
        $panel = $this->createMarzbanPanel();

        Http::fake([
            self::BASE_URL . '/api/user/test-user' => Http::sequence()
                ->push(['detail' => 'Unauthorized'], 401)
                ->push(['username' => 'test-user', 'status' => 'active'], 200),
            self::BASE_URL . '/api/admin/token' => Http::response([
                'token_type' => 'Bearer',
                'access_token' => 'fresh-token',
            ], 200),
        ]);

        $user = (new MarzbanPannelController())->getUser($panel, 'test-user');

        $this->assertIsArray($user);
        $this->assertSame('test-user', $user['username']);
        $this->assertSame('Bearer fresh-token', $panel->fresh()->token);
    }

    public function test_pannel_controller_create_and_modify_marzban_user(): void
    {
        $panel = $this->createMarzbanPanel();
        $username = 'BotUser999';

        Http::fake([
            self::BASE_URL . '/api/inbounds' => Http::response([
                'vless' => ['VLESS TCP REALITY'],
            ], 200),
            self::BASE_URL . '/api/user' => Http::response([
                'username' => $username,
                'subscription_url' => '/sub/create',
                'links' => ['vless://create'],
            ], 200),
            self::BASE_URL . '/api/user/' . $username => Http::response([
                'username' => $username,
                'status' => 'active',
                'links' => ['vless://updated'],
            ], 200),
            self::BASE_URL . '/api/user/' . $username . '/reset' => Http::response([
                'username' => $username,
            ], 200),
        ]);

        $pannelController = new PannelController();
        $created = $pannelController->createMarzbanUser($username, 30, 10, $panel->id);

        $this->assertIsArray($created);
        $this->assertSame('https://panel.example.com/sub/create', $created['subscription_link']);

        $links = $pannelController->modifyMarzbanUser($username, 20, 5, $panel->id);
        $this->assertSame(['vless://updated'], $links);
    }
}
