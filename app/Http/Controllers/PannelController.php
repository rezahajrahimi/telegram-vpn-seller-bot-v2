<?php

namespace App\Http\Controllers;
use App\Models\Pannel;
use App\Models\Proxy;
use App\Models\Inbound;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;

class PannelController extends Controller
{
    public function addNewPannel(Request $request)
    {
        try {
            $pannel = new Pannel();
            $pannel->type = $request->type;
            $pannel->username = $request->username ?? 'admin';
            $pannel->password = $request->password ?? '123456';
            $pannel->token = $request->token ?? 'Bearer ';
            $pannel->location = $request->location ?? null;
            $pannel->url_port = $request->url_port ?? null;
            $pannel->admin_url = $request->admin_url ?? null;
            $pannel->capacity = $request->capacity ?? 1333333;
            $pannel->save();
            return response()->json($pannel->id, 201);
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function addNewPannelMarzban(Request $request)
    {
        try {
            $pannel = new Pannel();
            $pannel->type = $request->type;
            $pannel->username = $request->username ?? 'admin';
            $pannel->password = $request->password ?? '123456';
            $pannel->token = $request->token ?? 'Bearer ';
            $pannel->location = $request->location ?? null;
            $pannel->url_port = $request->url_port ?? null;
            $pannel->admin_url = $request->admin_url ?? null;
            $pannel->capacity = $request->capacity ?? 1333333;
            $pannel->save();
            if ($request->vmess != null && $request->vmess == true) {
                $proxy = new Proxy();
                $proxy->pannel_id = $pannel->id;
                $proxy->type = 'vmess';
                $proxy->is_active = true;
                $proxy->save();
                if ($request->vmessTCP != null && $request->vmessTCP != false) {
                    $inbound = new Inbound();
                    $inbound->name = 'VMess TCP';
                    $inbound->data = 'VMess TCP';
                    $inbound->proxy_id = $proxy->id;
                    $inbound->is_active = true;
                    $inbound->save();
                }
                if ($request->vmessWebSocket != null && $request->vmessWebSocket != false) {
                    $inbound = new Inbound();
                    $inbound->name = 'VMess Websocket';
                    $inbound->data = 'VMess Websocket';
                    $inbound->proxy_id = $proxy->id;
                    $inbound->is_active = true;
                    $inbound->save();
                }
            }
            if ($request->vless != null && $request->vless == true) {
                $proxy = new Proxy();
                $proxy->pannel_id = $pannel->id;
                $proxy->type = 'vless';
                $proxy->is_active = true;
                $proxy->save();
                if ($request->vlessTcpReality != null && $request->vlessTcpReality != false) {
                    $inbound = new Inbound();
                    $inbound->name = 'VLESS TCP REALITY';
                    $inbound->data = 'VLESS TCP REALITY';
                    $inbound->proxy_id = $proxy->id;
                    $inbound->is_active = true;
                    $inbound->save();
                }
                if ($request->vlessGprcReality != null && $request->vlessGprcReality != false) {
                    $inbound = new Inbound();
                    $inbound->name = 'VLESS GRPC REALITY';
                    $inbound->data = 'VLESS GRPC REALITY';
                    $inbound->proxy_id = $proxy->id;
                    $inbound->is_active = true;
                    $inbound->save();
                }
            }
            if ($request->trojan != null && $request->trojan == true) {
                $proxy = new Proxy();
                $proxy->pannel_id = $pannel->id;
                $proxy->type = 'trojan';
                $proxy->is_active = true;
                $proxy->save();
                if ($request->trojanWebsocketTLS != null && $request->trojanWebsocketTLS != false) {
                    $inbound = new Inbound();
                    $inbound->name = 'Trojan Websocket TLS';
                    $inbound->data = 'Trojan Websocket TLS';
                    $inbound->proxy_id = $proxy->id;
                    $inbound->is_active = true;
                    $inbound->save();
                }
            }
            if ($request->shadowsocks != null && $request->shadowsocks == true) {
                $proxy = new Proxy();
                $proxy->pannel_id = $pannel->id;
                $proxy->type = 'shadowsocks';
                $proxy->is_active = true;
                $proxy->save();
                if ($request->shadowsocksTCP != null && $request->shadowsocksTCP != false) {
                    $inbound = new Inbound();
                    $inbound->name = 'Shadowsocks TCP';
                    $inbound->data = 'chacha20-poly1305';
                    $inbound->proxy_id = $proxy->id;
                    $inbound->is_active = true;
                    $inbound->save();
                }
            }
            return response()->json($pannel->id, 201);
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function updatePannel(Request $request)
    {
        try {
            $pannel = Pannel::find($request->id);
            if ($pannel) {
                $pannel->type = $request->type;
                $pannel->username = $request->username ?? 'admin';
                $pannel->password = $request->password ?? '123456';
                $pannel->token = $request->token ?? 'Bearer ';
                $pannel->location = $request->location ?? null;
                $pannel->url_port = $request->url_port ?? null;
                $pannel->admin_url = $request->admin_url ?? null;
                $pannel->capacity = $request->capacity ?? 1333333;
                if ($pannel->update()) {
                    return true;
                } else {
                    return response()->json(false, 500);
                }
            }
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function getPannels()
    {
        try {
            return Pannel::all();
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function getPannelById($id)
    {
        try {
            return Pannel::find($id);
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function getPannelByIdWithProxiesInbounds($id)
    {
        try {
            $pannel = Pannel::findOrFail($id);
            $proxyInbounds = Pannel::findOrFail($id)
                ->proxies()
                ->with('inbounds')
                ->get();
            return response()->json([$pannel, $proxyInbounds], 200);
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function deletePannel($id)
    {
        try {
            $pannel = Pannel::find($id);
            if ($pannel) {
                if ($pannel->delete()) {
                    return true;
                } else {
                    return response()->json(false, 500);
                }
            }
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function createHiddifyUser($accountId,$day,$vol,$pannelID)
    {
        $panel = Pannel::find($pannelID);
        $mainUrl = $panel->admin_url;

        $mainUrl = str_replace('/admin/', '', $mainUrl);
        $mainUrl = str_replace('/admin', '', $mainUrl);
        // get substring from end of str until /

        $adminUUID = substr($mainUrl,-36);
        \Log::info("adminUUID:$adminUUID");
        \Log::info("accountId:$accountId");
        \Log::info("day:$day");
        \Log::info("vol:$vol");

        \Log::info("mainUrl:$mainUrl");
        $uuid = $this->generateUUID();

        \Log::info("uuid:$uuid");
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        $params = [
            'uuid' => "$uuid",
            'name' => "bot$accountId",
            'current_usage_GB' => 0,
            'usage_limit_GB' => $vol,
            'package_days' =>$day,
            'start_date' => null,
            'comment' => null,
            'mode' => 'no_reset',
            'telegram_id' => null,
            'telegram_token' => null,
            "added_by_uuid" => "$adminUUID"
        ];
        $url = "$mainUrl/api/v1/user/";
        \Log::info("url:$url");

        $result = ['success' => false, 'body' => []];

        try {
            $response = Http::withHeaders($headers)->post($url, $params);
            $result = ['success' => $response->ok(), 'body' => $response->json(),'server response' => $response->serverError()];
        } catch (\Throwable $th) {
            $result['error'] = $th->getMessage();

        }

        \Log::info('TelegramBot->sendMessage->result', ['result' => $result]);

        return $uuid;
    }
    public function getHiddifyPannelLinkByPannelID($pannelID){
        $panel = Pannel::find($pannelID);
        $mainUrl = $panel->admin_url;

        $mainUrl = str_replace('/admin/', '', $mainUrl);
        $mainUrl = str_replace('/admin', '', $mainUrl);
        // get substring from end of str until /

        $adminUUID = substr($mainUrl,-36);
        $hidifyUrl = str_replace("/$adminUUID", '', $mainUrl);
        \Log::info("hidifyUrl:$hidifyUrl");

        return $hidifyUrl;
    }
    public function generateUUID($data = null)
    {
        // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
        $data = $data ?? random_bytes(16);
        assert(strlen($data) == 16);

        // Set version to 0100
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        // Output the 36 character UUID.
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
