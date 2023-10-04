<?php

namespace App\Http\Controllers;
use App\Models\Pannel;
use App\Models\Proxy;
use App\Models\Inbound;

use Illuminate\Http\Request;

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
                    $inbound->name = "VMess TCP";
                    $inbound->data = "VMess TCP";
                    $inbound->proxy_id  = $proxy->id ;
                    $inbound->is_active = true;
                    $inbound->save();
                }
                if ($request->vmessWebSocket != null && $request->vmessWebSocket != false) {
                    $inbound = new Inbound();
                    $inbound->name = "VMess Websocket";
                    $inbound->data = "VMess Websocket";
                    $inbound->proxy_id  = $proxy->id ;
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
                    $inbound->name = "VLESS TCP REALITY";
                    $inbound->data = "VLESS TCP REALITY";
                    $inbound->proxy_id  = $proxy->id ;
                    $inbound->is_active = true;
                    $inbound->save();
                }
                if ($request->vlessGprcReality != null && $request->vlessGprcReality != false) {
                    $inbound = new Inbound();
                    $inbound->name = "VLESS GRPC REALITY";
                    $inbound->data = "VLESS GRPC REALITY";
                    $inbound->proxy_id  = $proxy->id ;
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
                    $inbound->name = "Trojan Websocket TLS";
                    $inbound->data = "Trojan Websocket TLS";
                    $inbound->proxy_id  = $proxy->id ;
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
                    $inbound->name = "Shadowsocks TCP";
                    $inbound->data = "chacha20-poly1305";
                    $inbound->proxy_id  = $proxy->id ;
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
            return $pannel = Pannel::find($id);
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
}
