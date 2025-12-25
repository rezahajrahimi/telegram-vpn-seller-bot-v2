<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Models\Pannel;
use App\Http\Controllers\SanaeiPannelController;
use Illuminate\Http\Request;

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
}
