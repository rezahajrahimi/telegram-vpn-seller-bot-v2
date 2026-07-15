<?php

namespace Tests\Feature;

use App\Http\Controllers\PasarguardPannelController;
use App\Models\Pannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PasarguardPannelControllerTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_URL = 'https://pasarguard.example.com';

    private function createPasarguardPanel(): Pannel
    {
        return Pannel::create([
            'type' => 'pasarguard',
            'url_port' => self::BASE_URL,
            'username' => 'admin',
            'password' => 'secret',
            'token' => 'Bearer test-token',
        ]);
    }

    public function test_create_user_sends_group_ids_instead_of_inbounds(): void
    {
        $panel = $this->createPasarguardPanel();
        $selectedInbounds = [
            'vless' => ['VLESS TCP REALITY 3350', 'VLESS WS TLS 11000'],
        ];

        Http::fake([
            self::BASE_URL . '/api/groups*' => Http::response([
                'groups' => [
                    [
                        'id' => 7,
                        'name' => 'main',
                        'inbound_tags' => ['VLESS TCP REALITY 3350', 'VLESS WS TLS 11000'],
                        'is_disabled' => false,
                    ],
                ],
                'total' => 1,
            ], 200),
            self::BASE_URL . '/api/user' => Http::response([
                'username' => 'BotUser123',
                'subscription_url' => '/sub/abc123',
                'links' => ['vless://example'],
            ], 200),
        ]);

        $result = (new PasarguardPannelController())->createUser(
            $panel,
            'BotUser123',
            30,
            10,
            $selectedInbounds
        );

        $this->assertIsArray($result);
        Http::assertSent(function ($request) {
            if ($request->url() !== self::BASE_URL . '/api/user') {
                return false;
            }

            $body = $request->data();

            return ($body['group_ids'] ?? null) === [7]
                && isset($body['proxy_settings'])
                && ($body['data_limit_reset_strategy'] ?? null) === 'no_reset'
                && ! array_key_exists('inbounds', $body)
                && ! array_key_exists('proxies', $body);
        });
    }

    public function test_create_user_uses_explicit_group_ids_from_category(): void
    {
        $panel = $this->createPasarguardPanel();

        Http::fake([
            self::BASE_URL . '/api/groups*' => Http::response([
                'groups' => [
                    [
                        'id' => 3,
                        'name' => 'premium',
                        'inbound_tags' => ['VLESS TCP REALITY 3350'],
                        'is_disabled' => false,
                    ],
                    [
                        'id' => 9,
                        'name' => 'disabled',
                        'inbound_tags' => ['VLESS WS TLS 11000'],
                        'is_disabled' => true,
                    ],
                ],
                'total' => 2,
            ], 200),
            self::BASE_URL . '/api/user' => Http::response([
                'username' => 'BotUser123',
                'subscription_url' => '/sub/abc123',
                'links' => [],
            ], 200),
        ]);

        (new PasarguardPannelController())->createUser(
            $panel,
            'BotUser123',
            30,
            10,
            null,
            [3, 9]
        );

        Http::assertSent(function ($request) {
            if ($request->url() !== self::BASE_URL . '/api/user') {
                return false;
            }

            return ($request->data()['group_ids'] ?? null) === [3];
        });
    }

    public function test_create_user_covers_multiple_groups_when_needed(): void
    {
        $panel = $this->createPasarguardPanel();
        $selectedInbounds = [
            'vless' => ['VLESS TCP REALITY 3350', 'VLESS WS TLS 11000'],
        ];

        Http::fake([
            self::BASE_URL . '/api/groups*' => Http::response([
                'groups' => [
                    [
                        'id' => 1,
                        'name' => 'reality',
                        'inbound_tags' => ['VLESS TCP REALITY 3350'],
                        'is_disabled' => false,
                    ],
                    [
                        'id' => 2,
                        'name' => 'ws',
                        'inbound_tags' => ['VLESS WS TLS 11000'],
                        'is_disabled' => false,
                    ],
                ],
                'total' => 2,
            ], 200),
            self::BASE_URL . '/api/user' => Http::response([
                'username' => 'BotUser123',
                'subscription_url' => '/sub/abc123',
                'links' => [],
            ], 200),
        ]);

        (new PasarguardPannelController())->createUser($panel, 'BotUser123', 30, 10, $selectedInbounds);

        Http::assertSent(function ($request) {
            if ($request->url() !== self::BASE_URL . '/api/user') {
                return false;
            }

            $groupIds = $request->data()['group_ids'] ?? [];
            sort($groupIds);

            return $groupIds === [1, 2];
        });
    }

    public function test_create_user_fails_when_selected_inbounds_not_in_any_group(): void
    {
        $panel = $this->createPasarguardPanel();

        Http::fake([
            self::BASE_URL . '/api/groups*' => Http::response([
                'groups' => [
                    [
                        'id' => 1,
                        'name' => 'other',
                        'inbound_tags' => ['VLESS TCP NONE 52349'],
                        'is_disabled' => false,
                    ],
                ],
                'total' => 1,
            ], 200),
        ]);

        $result = (new PasarguardPannelController())->createUser(
            $panel,
            'BotUser123',
            30,
            10,
            ['vless' => ['VLESS TCP REALITY 3350']]
        );

        $this->assertFalse($result);
        Http::assertNotSent(function ($request) {
            return $request->url() === self::BASE_URL . '/api/user';
        });
    }
}
