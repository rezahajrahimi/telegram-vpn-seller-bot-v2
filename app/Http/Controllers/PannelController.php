<?php

namespace App\Http\Controllers;
use App\Models\Pannel;
use App\Models\Proxy;
use App\Models\Inbound;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

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
            if ($pannel->type == 'marzban') {
                $vmess = new Proxy();
                $vmess->pannel_id = $pannel->id;
                $vmess->type = 'vmess';
                $vmess->is_active = $request->vmess == true || $request->vmess == 1 ? true : false;
                $vmess->save();
                $inbound = new Inbound();
                $inbound->name = 'VMess TCP';
                $inbound->data = 'VMess TCP';
                $inbound->proxy_id = $vmess->id;
                $inbound->is_active = $request->vmessTCP == true || $request->vmessTCP == 1 ? true : false;
                $inbound->save();

                $inbound = new Inbound();
                $inbound->name = 'VMess Websocket';
                $inbound->data = 'VMess Websocket';
                $inbound->proxy_id = $vmess->id;
                $inbound->is_active = $request->vmessWebSocket == true || $request->vmessWebSocket == 1 ? true : false;
                $inbound->save();

                $vless = new Proxy();
                $vless->pannel_id = $pannel->id;
                $vless->type = 'vless';
                $vless->is_active = $request->vless == true || $request->vless == 1 ? true : false;
                $vless->save();
                $inbound = new Inbound();
                $inbound->name = 'VLESS TCP REALITY';
                $inbound->data = 'VLESS TCP REALITY';
                $inbound->proxy_id = $vless->id;
                $inbound->is_active = $request->vlessTcpReality == true || $request->vlessTcpReality == 1 ? true : false;
                $inbound->save();

                $inbound = new Inbound();
                $inbound->name = 'VLESS GRPC REALITY';
                $inbound->data = 'VLESS GRPC REALITY';
                $inbound->proxy_id = $vless->id;
                $inbound->is_active = $request->vlessGprcReality == true || $request->vlessGprcReality == 1 ? true : false;
                $inbound->save();

                $trojan = new Proxy();
                $trojan->pannel_id = $pannel->id;
                $trojan->type = 'trojan';
                $trojan->is_active = $request->trojan == true || $request->trojan == 1 ? true : false;
                $trojan->save();
                $inbound = new Inbound();
                $inbound->name = 'Trojan Websocket TLS';
                $inbound->data = 'Trojan Websocket TLS';
                $inbound->proxy_id = $trojan->id;
                $inbound->is_active = $request->trojanWebsocketTLS == true || $request->trojanWebsocketTLS == 1 ? true : false;
                $inbound->save();

                $shadowsocks = new Proxy();
                $shadowsocks->pannel_id = $pannel->id;
                $shadowsocks->type = 'shadowsocks';
                $shadowsocks->is_active = $request->shadowsocks == true || $request->shadowsocks == 1 ? true : false;
                $shadowsocks->save();
                $inbound = new Inbound();
                $inbound->name = 'Shadowsocks TCP';
                $inbound->data = 'chacha20-poly1305';
                $inbound->proxy_id = $shadowsocks->id;
                $inbound->is_active = $request->shadowsocksTCP == true || $request->shadowsocksTCP == 1 ? true : false;
                $inbound->save();
            }
            return response()->json($pannel->id, 201);
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return response()->json(false, 500);
        }
    }
    public function editMarzbanPannel(Request $request)
    {
        try {
            $pannel = Pannel::find($request->id);
            $pannel->type = 'marzban';
            $pannel->username = $request->username ?? 'admin';
            $pannel->password = $request->password ?? '123456';
            $pannel->token = $request->token ?? 'Bearer ';
            $pannel->location = $request->location ?? null;
            $pannel->url_port = $request->url_port ?? null;
            $pannel->admin_url = $request->admin_url ?? null;
            $pannel->capacity = $request->capacity ?? 1333333;
            $pannel->update();

            $proxy = Proxy::where('pannel_id', $pannel->id)
                ->where('type', 'vmess')
                ->first();
            $proxy->is_active = $request->vmess == true || $request->vmess == 1 ? true : false;
            $proxy->update();
            if ($request->vmessTCP != null && $request->vmessTCP != false) {
                $inbound = Inbound::where('proxy_id', $proxy->id)
                    ->where('name', 'VMess TCP')
                    ->first();
                $inbound->is_active = true;
                $inbound->update();
            } else {
                $inbound = Inbound::where('proxy_id', $proxy->id)
                    ->where('name', 'VMess TCP')
                    ->first();
                $inbound->is_active = false;
                $inbound->update();
            }
            if ($request->vmessWebSocket != null && $request->vmessWebSocket != false) {
                $inbound = Inbound::where('proxy_id', $proxy->id)
                    ->where('name', 'VMess Websocket')
                    ->first();
                $inbound->is_active = true;
                $inbound->update();
            }

            $proxy = Proxy::where('pannel_id', $pannel->id)
                ->where('type', 'vless')
                ->first();

            $proxy->is_active = $request->vless == true || $request->vless == 1 ? true : false;
            $proxy->update();
            if ($request->vlessTcpReality != null && $request->vlessTcpReality != false) {
                $inbound = Inbound::where('proxy_id', $proxy->id)
                    ->where('name', 'VLESS TCP REALITY')
                    ->first();
                $inbound->is_active = true;
                $inbound->update();
            } else {
                $inbound = Inbound::where('proxy_id', $proxy->id)
                    ->where('name', 'VLESS TCP REALITY')
                    ->first();
                $inbound->is_active = false;
                $inbound->update();
            }
            if ($request->vlessGprcReality != null && $request->vlessGprcReality != false) {
                $inbound = Inbound::where('proxy_id', $proxy->id)
                    ->where('name', 'VLESS GRPC REALITY')
                    ->first();
                $inbound->is_active = true;
                $inbound->update();
            } else {
                $inbound = Inbound::where('proxy_id', $proxy->id)
                    ->where('name', 'VLESS GRPC REALITY')
                    ->first();
                $inbound->is_active = false;
                $inbound->update();
            }

            $proxy = Proxy::where('pannel_id', $pannel->id)
                ->where('type', 'trojan')
                ->first();

            $proxy->is_active = $request->trojan == true || $request->trojan == 1 ? true : false;
            $proxy->update();
            if ($request->trojanWebsocketTLS != null && $request->trojanWebsocketTLS != false) {
                $inbound = Inbound::where('proxy_id', $proxy->id)
                    ->where('name', 'Trojan Websocket TLS')
                    ->first();
                $inbound->is_active = true;
                $inbound->update();
            } else {
                $inbound = Inbound::where('proxy_id', $proxy->id)
                    ->where('name', 'Trojan Websocket TLS')
                    ->first();
                $inbound->is_active = false;
                $inbound->update();
            }

            $proxy = Proxy::where('pannel_id', $pannel->id)
                ->where('type', 'shadowsocks')
                ->first();

            $proxy->is_active = $request->shadowsocks == true || $request->shadowsocks == 1 ? true : false;
            $proxy->update();

            if ($request->shadowsocksTCP != null && $request->shadowsocksTCP != false) {
                $inbound = Inbound::where('proxy_id', $proxy->id)
                    ->where('name', 'Shadowsocks TCP')
                    ->first();
                $inbound->is_active = true;
                $inbound->update();
            } else {
                $inbound = Inbound::where('proxy_id', $proxy->id)
                    ->where('name', 'Shadowsocks TCP')
                    ->first();
                $inbound->is_active = false;
                $inbound->update();
            }

            return response()->json($pannel->id, 201);
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

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
                }
                return response()->json(false, 500);
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

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
            $proxyInbounds = Pannel::findOrFail($id)->proxies()->with('inbounds')->get();
            return response()->json([$pannel, $proxyInbounds], 200);
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }

    public function deletePannel($id)
    {
        try {
            $panel = Pannel::find($id);
            if ($panel) {
                if ($panel->delete()) {
                    return true;
                } else {
                    return response()->json(false, 500);
                }
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return response()->json(false, 500);
        }
    }
    public function createHiddifyUser($accountId, $day, $vol, $pannelID)
    {
        $panel = Pannel::find($pannelID);
        $mainUrl = $panel->admin_url;

        $mainUrl = str_replace('/admin/', '', $mainUrl);
        $mainUrl = str_replace('/admin', '', $mainUrl);
        // get substring from end of str until /

        $adminUUID = substr($mainUrl, -36);
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
            'package_days' => $day,
            'start_date' => null,
            'comment' => null,
            'mode' => 'no_reset',
            'telegram_id' => null,
            'telegram_token' => null,
            'added_by_uuid' => "$adminUUID",
        ];
        $url = "$mainUrl/api/v1/user/";
        \Log::info("url:$url");

        $result = ['success' => false, 'body' => []];

        try {
            $response = Http::withHeaders($headers)->post($url, $params);
            $result = ['success' => $response->ok(), 'body' => $response->json(), 'server response' => $response->serverError()];
        } catch (\Throwable $th) {
            $result['error'] = $th->getMessage();
        }

        \Log::info('TelegramBot->sendMessage->result', ['result' => $result]);

        return $uuid;
    }

    public function getHiddifyPannelLinkByPannelID($pannelID)
    {
        $panel = Pannel::find($pannelID);
        $mainUrl = $panel->admin_url;

        $mainUrl = str_replace('/admin/', '', $mainUrl);
        $mainUrl = str_replace('/admin', '', $mainUrl);
        // get substring from end of str until /

        $adminUUID = substr($mainUrl, -36);
        $hidifyUrl = str_replace("/$adminUUID", '', $mainUrl);

        return $hidifyUrl;
    }

    public function generateQrMOC($str)
    {
        $str = 'reza';
        $uuid = $this->generateUUID();
        $image = QrCode::format('png')->generate($str);

        $path = public_path() . '/images/' . 'aa.png';
        if (file_exists($path)) {
            unlink($path);
        }

        $res = file_put_contents($path, $image);
        return $path;
    }
    public function createMarzbanUser($accountId, $day, $vol, $pannelID)
    {
        \Log::info("accountIdaaaaaaaaaaaaaaaaaaaaaaaa:$accountId");
        // try {
        $panel = Pannel::find($pannelID);
        $token = $panel->token;
        $mainUrl = $panel->url_port;
        $mainUrl = str_replace('/dashboard/', '', $mainUrl);
        $mainUrl = str_replace('/dashboard', '', $mainUrl);
        //$vol must change to byte
        $vol = $vol * 1024 * 1024 * 1024;
        // crete an UTC date + $day
        $utc = new \DateTime('now', new \DateTimeZone('UTC'));
        $utc = $utc->add(new \DateInterval('P' . $day . 'D'));
        // convert utc to integer
        $utc = $utc->getTimestamp();
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'authorization' => $token,
        ];
        $result = ['success' => false, 'body' => []];
        // get active proxies
        $proCntrl = new ProxyController();
        $proxies = $proCntrl->getActiveProxiesByPannelID($pannelID);

        $proxy = [];
        $inbounds = [];
        foreach ($proxies as $key => $pr) {
            $proxy[$pr->type] = [];
            foreach ($pr->inbounds as $key => $in) {
                // merge inbounds
                $inbounds[$pr->type][] = $in->name;
            }
        }

        $params = [
            'username' => $accountId,
            'expire' => $utc,
            'data_limit' => $vol,
            'proxies' => $proxy,
            'inbounds' => $inbounds,
        ];
        $url = "{$mainUrl}/api/user";

        $response = Http::withHeaders($headers)->post($url, $params);
        $result = ['success' => $response->ok(), 'body' => $response->json()];
        \Log::info('TelegramBot->sendMessage->result', ['result' => $result]);

        $sub = $result['body']['subscription_url'];

        $sublink = "$mainUrl$sub";
        return ['links' => $result['body']['links'], 'subscription_link' => $sublink];
        // } catch (\Throwable $th) {
        //     \Log::info('Marzban Resault', ['error' => ($result['error'] = $th->getMessage())]);

        //     return null;
        // }
    }
    public function modifyMarzbanUser($accountId, $day, $vol, $pannelID)
    {
        try {
            $panel = Pannel::find($pannelID);
            $token = $panel->token;
            $mainUrl = $panel->url_port;
            $mainUrl = str_replace('/dashboard/', '', $mainUrl);
            $mainUrl = str_replace('/dashboard', '', $mainUrl);
            //$vol must change to byte
            $vol = $vol * 1024 * 1024 * 1024;
            // crete an UTC date + $day
            $utc = new \DateTime('now', new \DateTimeZone('UTC'));
            $utc = $utc->add(new \DateInterval('P' . $day . 'D'));
            // convert utc to integer
            $utc = $utc->getTimestamp();
            $proCntrl = new ProxyController();
            $proxies = $proCntrl->getActiveProxiesByPannelID($pannelID);

            $proxy = [];
            $inbounds = [];
            foreach ($proxies as $key => $pr) {
                $proxy[$pr->type] = [];
                foreach ($pr->inbounds as $key => $in) {
                    // merge inbounds
                    $inbounds[$pr->type][] = $in->name;
                }
            }
            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'authorization' => $token,
            ];
            $result = ['success' => false, 'body' => []];
            $vmess = [];
            $params = [
                'username' => $accountId,
                'expire' => $utc,
                'data_limit' => $vol,
                'proxies' => $proxy,
                'inbounds' => $inbounds,
            ];

            $url = "{$mainUrl}/api/user/$accountId";

            $response = Http::withHeaders($headers)->put($url, $params);
            $result = ['success' => $response->ok(), 'body' => $response->json()];

            return $result['body']['links'];
        } catch (\Throwable $th) {
            \Log::info('Marzban Resault', ['error' => ($result['error'] = $th->getMessage())]);

            return null;
        }
    }
}
