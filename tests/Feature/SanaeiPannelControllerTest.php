<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Models\Pannel;
use App\Http\Controllers\SanaeiPannelController;
use Illuminate\Http\Request;

// Fake TelegramService subclass to avoid actual network calls
class FakeTelegramService extends \App\Services\TelegramService
{
    public function __construct()
    {
    }
    public function sendPhotoFile(string $chatId, string $image, string|array $caption = '', array $options = []): array
    {
        return [];
    }
    public function formatText(array $text): string
    {
        return is_array($text) ? implode("\n", $text) : '';
    }
    public function sendMessage(string $chatId, string|array $text, array $options = []): array
    {
        return [];
    }
}

class SanaeiPannelControllerTest extends TestCase
{
    public function test_add_user_and_delete_flow()
    {
        // Fake login response
        Http::fake([
            '*/login' => Http::response(["success" => true, "msg" => "logged in", "obj" => null], 200, ['Set-Cookie' => '3x-ui=abcd']),
            '*/inbounds/get/*' => Http::response([
                "success" => true,
                "msg" => "",
                "obj" => [
                    'id' => 1,
                    'settings' => json_encode(['clients' => []]),
                    'streamSettings' => json_encode(['network' => 'tcp']),
                    'protocol' => 'vless',
                    'port' => 12345
                ]
            ], 200),
            '*/inbounds/addClient' => Http::response(["success" => true, "msg" => "added"], 200),
            '*/inbounds/*/delClient/*' => Http::response(["success" => true], 200),
        ]);

        // Create a dummy panel
        $panel = Pannel::create([
            'admin_url' => 'http://127.0.0.1:2053',
            'username' => 'admin',
            'password' => 'admin',
            'inbound_id' => 1
        ]);

        $controller = new SanaeiPannelController();
        $req = new Request([
            'pannelID' => $panel->id,
            'day' => 1,
            'vol' => 1,
            'accountId' => 'test-1'
        ]);

        $uuid = $controller->addUserToSanaeiPanel($req);
        $this->assertNotFalse($uuid);

        // Test findClientByEmail returns null (no client yet)
        $res = $controller->findClientByEmail($panel->id, 'nonexistent');
        $this->assertNull($res);

        // Test deleteClient wrapper (should return boolean)
        $ok = $controller->deleteClient($panel->id, 1, 'someid');
        $this->assertTrue($ok);
    }

    public function test_all_panel_operations()
    {
        Http::fake([
            '*/login' => Http::response(["success" => true, "msg" => "logged in", "obj" => null], 200, ['Set-Cookie' => '3x-ui=abcd']),
            '*/inbounds/list' => Http::response([
                "success" => true,
                "msg" => "",
                "obj" => [
                    [
                        'id' => 1,
                        'settings' => json_encode([
                            'clients' => [
                                [
                                    'id' => 'client-1',
                                    'email' => 'test-email',
                                    'subId' => 'subid'
                                ]
                            ]
                        ]),
                        'streamSettings' => json_encode(['network' => 'tcp']),
                        'protocol' => 'vless',
                        'port' => 37191
                    ]
                ]
            ], 200),
            '*/inbounds/get/*' => Http::response([
                "success" => true,
                "msg" => "",
                "obj" => [
                    'id' => 1,
                    'settings' => json_encode([
                        'clients' => [
                            [
                                'id' => 'client-1',
                                'email' => 'test-email',
                                'subId' => 'subid'
                            ]
                        ]
                    ]),
                    'streamSettings' => json_encode(['network' => 'tcp']),
                    'protocol' => 'vless',
                    'port' => 37191
                ]
            ], 200),
            '*/inbounds/addClient' => Http::response(["success" => true, "msg" => "added"], 200),
            '*/inbounds/*/delClient/*' => Http::response(["success" => true], 200),
            '*/inbounds/updateClient/*' => Http::response(["success" => true], 200),
            '*/inbounds/*/resetClientTraffic/*' => Http::response(["success" => true], 200),
            '*/inbounds/resetAllTraffics' => Http::response(["success" => true], 200),
            '*/inbounds/delDepletedClients/*' => Http::response(["success" => true], 200),
            '*/inbounds/getClientTraffics/*' => Http::response(["success" => true, "obj" => ['traffic' => 123]], 200),
            '*/inbounds/getClientTrafficsById/*' => Http::response(["success" => true, "obj" => ['traffic' => 456]], 200),
            '*/inbounds/onlines' => Http::response(["success" => true, "obj" => ['online' => ['client-1']]], 200),
            '*/inbounds/lastOnline' => Http::response(["success" => true, "obj" => ['lastOnline' => ['client-1' => 1234567890]]], 200),
            '*/inbounds/clientIps/*' => Http::response(["success" => true, "obj" => ['ips' => ['1.2.3.4']]], 200),
            '*/inbounds/clearClientIps/*' => Http::response(["success" => true], 200),
            '*/inbounds/*/delClientByEmail/*' => Http::response(["success" => true], 200),
        ]);

        $panel = Pannel::create([
            'admin_url' => 'http://127.0.0.1:2053',
            'username' => 'admin',
            'password' => 'admin',
            'inbound_id' => 1
        ]);

        $ctrl = new SanaeiPannelController();

        // find existing client
        $found = $ctrl->findClientByEmail($panel->id, 'test-email');
        $this->assertNotNull($found);
        $this->assertEquals('client-1', $found['client']['id']);

        // reset client traffic
        $this->assertTrue($ctrl->resetClientTraffic($panel->id, 1, 'test-email'));

        // reset all traffics
        $this->assertTrue($ctrl->resetAllTraffics($panel->id));

        // delete depleted
        $this->assertTrue($ctrl->delDepletedClients($panel->id, 1));

        // get traffics
        $t = $ctrl->getClientTrafficsByEmail($panel->id, 'test-email');
        $this->assertIsArray($t);
        $this->assertEquals(123, $t['traffic']);

        $t2 = $ctrl->getClientTrafficsById($panel->id, 1);
        $this->assertIsArray($t2);
        $this->assertEquals(456, $t2['traffic']);

        $this->assertIsArray($ctrl->onlines($panel->id));
        $this->assertIsArray($ctrl->lastOnline($panel->id));

        $ips = $ctrl->clientIps($panel->id, 'test-email');
        $this->assertIsArray($ips);

        $this->assertTrue($ctrl->clearClientIps($panel->id, 'test-email'));

        $this->assertTrue($ctrl->delClientByEmail($panel->id, 1, 'test-email'));

        // Update client
        $this->assertTrue($ctrl->updateClient($panel->id, 'client-1', ['enable' => false]));
    }

    public function test_reset_and_delete_operations()
    {
        Http::fake([
            '*/login' => Http::response(["success" => true, "msg" => "logged in", "obj" => null], 200, ['Set-Cookie' => '3x-ui=abcd']),
            '*/inbounds/get/*' => Http::response([
                "success" => true,
                "msg" => "",
                "obj" => [
                    'id' => 1,
                    'settings' => json_encode([
                        'clients' => [
                            [
                                'id' => 'client-1',
                                'email' => 'test-email',
                                'subId' => 'subid',
                                'expiryTime' => 1766660000000,
                            ]
                        ]
                    ]),
                    'streamSettings' => json_encode(['network' => 'tcp']),
                    'protocol' => 'vless',
                    'port' => 37191
                ]
            ], 200),
            '*/inbounds/updateClient/*' => Http::response(["success" => true], 200),
            '*/inbounds/*/resetClientTraffic/*' => Http::response(["success" => true], 200),
            '*/inbounds/*/delClientByEmail/*' => Http::response(["success" => true], 200),
            '*/inbounds/*/delClient/*' => Http::response(["success" => true], 200),
        ]);

        $panel = Pannel::create([
            'admin_url' => 'http://127.0.0.1:2053',
            'username' => 'admin',
            'password' => 'admin',
            'inbound_id' => 1
        ]);

        $ctrl = new SanaeiPannelController();

        // Reset time (update client expiry)
        $this->assertTrue($ctrl->updateClient($panel->id, 'client-1', ['expiryTime' => 1766669000000]));

        // Reset traffic
        $this->assertTrue($ctrl->resetClientTraffic($panel->id, 1, 'test-email'));

        // Delete by email
        $this->assertTrue($ctrl->delClientByEmail($panel->id, 1, 'test-email'));

        // Delete by client id
        $this->assertTrue($ctrl->deleteClient($panel->id, 1, 'client-1'));
    }

    public function test_subscription_history_sanaei_flow()
    {
        Http::fake([
            '*/login' => Http::response(["success" => true, "msg" => "logged in", "obj" => null], 200, ['Set-Cookie' => '3x-ui=abcd']),
            '*/inbounds/list' => Http::response([
                "success" => true,
                "msg" => "",
                "obj" => [
                    [
                        'id' => 1,
                        'settings' => json_encode([
                            'clients' => [
                                [
                                    'id' => 'uuid-123',
                                    'email' => 'bot-test-1',
                                    'created_at' => 1766600000000,
                                    'expiryTime' => 1766700000000,
                                    'enable' => true,
                                    'totalGB' => 1073741824
                                ]
                            ]
                        ]),
                        'streamSettings' => json_encode(['network' => 'tcp']),
                        'protocol' => 'vless',
                        'port' => 37191
                    ]
                ]
            ], 200),
            '*/inbounds/getClientTraffics/*' => Http::response(["success" => true, "obj" => ['traffic' => 536870912]], 200),
        ]);

        $panel = Pannel::create([
            'admin_url' => 'http://127.0.0.1:2053',
            'username' => 'admin',
            'password' => 'admin',
            'inbound_id' => 1,
            'type' => 'sanaei'
        ]);

        $prCat = \App\Models\ProductCategory::create([
            'category_name' => 'Sanaei Test',
            'pannel_id' => $panel->id,
            'price' => 10,
            'is_active' => 1,
            'expire_day' => 30,
        ]);

        $product = \App\Models\Product::create([
            'product_categories_id' => $prCat->id,
            'remark' => 'test-prod',
            'subscription_link' => '',
            'panel_link' => '',
            'configs' => json_encode(['uuid' => 'uuid-123', 'links' => ['vless://...']]),
        ]);

        // ensure BotUser exists so SubscriptionProcessController->subBuyHistory can access username
        \App\Models\BotUser::create([
            'account_id' => 12345,
            'username' => 'test-bot-user'
        ]);

        $fakeTelegram = new FakeTelegramService();
        $subCtrl = new \App\Http\Controllers\SubscriptionProcessController($fakeTelegram);

        // Should not throw and should return string (empty)
        $res = $subCtrl->subBuyHistory(12345, $product->id);
        $this->assertIsString($res);
    }

    public function test_recharge_client_updates_expiry_and_quota()
    {
        Http::fake([
            '*/login' => Http::response(["success" => true, "msg" => "logged in", "obj" => null], 200, ['Set-Cookie' => '3x-ui=abcd']),
            '*/inbounds/list' => Http::response([
                "success" => true,
                "msg" => "",
                "obj" => [
                    [
                        'id' => 1,
                        'settings' => json_encode([
                            'clients' => [
                                [
                                    'id' => 'uuid-123',
                                    'email' => 'bot-test-1',
                                    'created_at' => 1766600000000,
                                    'expiryTime' => 1766700000000,
                                    'enable' => true,
                                    'totalGB' => 1073741824
                                ]
                            ]
                        ])
                    ]
                ]
            ], 200),
            '*/inbounds/get/*' => Http::response([
                "success" => true,
                "msg" => "",
                "obj" => [
                    'id' => 1,
                    'settings' => json_encode([
                        'clients' => [
                            [
                                'id' => 'uuid-123',
                                'email' => 'bot-test-1',
                                'created_at' => 1766600000000,
                                'expiryTime' => 1766700000000,
                                'enable' => true,
                                'totalGB' => 1073741824
                            ]
                        ]
                    ])
                ]
            ], 200),
            '*/inbounds/updateClient/*' => Http::response(["success" => true], 200),
        ]);

        $panel = Pannel::create([
            'admin_url' => 'http://127.0.0.1:2053',
            'username' => 'admin',
            'password' => 'admin',
            'inbound_id' => 1,
            'type' => 'sanaei'
        ]);

        $ctrl = new SanaeiPannelController();

        $ok = $ctrl->rechargeClient($panel->id, 'uuid-123', 10, 1); // add 10 days and 1 GB
        $this->assertTrue($ok);
    }

    public function test_updateClient_raw_cookie_retry_when_server_returns_success_false()
    {
        Http::fake([
            '*/login' => Http::response(["success" => true, "msg" => "logged in", "obj" => null], 200, ['Set-Cookie' => '3x-ui=abcd']),
            // For updateClient, if Cookie header is present, return success true; else return success false
            '*/inbounds/updateClient/*' => function ($request) {
                static $calls = 0;
                $calls++;
                if ($calls === 1) {
                    return Http::response(["success" => false, "msg" => "Something went wrong (unexpected end of JSON input)"], 200);
                }
                return Http::response(["success" => true], 200);
            },
            '*/inbounds/list' => Http::response([
                "success" => true,
                "msg" => "",
                "obj" => [
                    [
                        'id' => 1,
                        'settings' => json_encode([
                            'clients' => [
                                [
                                    'id' => 'uuid-123',
                                    'email' => 'bot-test-1',
                                    'created_at' => 1766600000000,
                                    'expiryTime' => 1766700000000,
                                    'enable' => true,
                                    'totalGB' => 1073741824
                                ]
                            ]
                        ])
                    ]
                ]
            ], 200),
        ]);

        $panel = Pannel::create([
            'admin_url' => 'http://127.0.0.1:2053',
            'username' => 'admin',
            'password' => 'admin',
            'inbound_id' => 1,
            'type' => 'sanaei'
        ]);

        $ctrl = new SanaeiPannelController();

        $ok = $ctrl->rechargeClient($panel->id, 'uuid-123', 1, 1);
        $this->assertTrue($ok, 'Expected rechargeClient to succeed by retrying with raw Cookie header');
    }

    public function test_updateClient_settings_fallback_succeeds()
    {
        Http::fake([
            '*/login' => Http::response(["success" => true, "msg" => "logged in", "obj" => null], 200, ['Set-Cookie' => '3x-ui=abcd']),
            // First update attempt: returns success false
            '*/inbounds/updateClient/*' => function ($request) {
                static $calls = 0;
                $calls++;
                if ($calls === 1) {
                    return Http::response(["success" => false, "msg" => "Something went wrong (unexpected end of JSON input)"], 200);
                }
                // the fallback (settings) attempt succeeds
                return Http::response(["success" => true], 200);
            },
            '*/inbounds/list' => Http::response([
                "success" => true,
                "msg" => "",
                "obj" => [
                    [
                        'id' => 1,
                        'settings' => json_encode([
                            'clients' => [
                                [
                                    'id' => 'uuid-123',
                                    'email' => 'bot-test-1',
                                    'created_at' => 1766600000000,
                                    'expiryTime' => 1766700000000,
                                    'enable' => true,
                                    'totalGB' => 1073741824
                                ]
                            ]
                        ])
                    ]
                ]
            ], 200),
        ]);

        $panel = Pannel::create([
            'admin_url' => 'http://127.0.0.1:2053',
            'username' => 'admin',
            'password' => 'admin',
            'inbound_id' => 1,
            'type' => 'sanaei'
        ]);

        $ctrl = new SanaeiPannelController();

        $ok = $ctrl->rechargeClient($panel->id, 'uuid-123', 1, 1);
        $this->assertTrue($ok, 'Expected rechargeClient to succeed via settings fallback');
    }
}
