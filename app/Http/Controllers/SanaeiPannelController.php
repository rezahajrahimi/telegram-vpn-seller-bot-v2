<?php

namespace App\Http\Controllers;

use App\Models\Inbound;
use App\Models\Pannel;
use App\Models\Proxy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SanaeiPannelController extends Controller
{
    private function baseUrl(Pannel $panel): string
    {
        $url = trim((string) $panel->admin_url);
        if ($url === '') {
            return '';
        }
        return rtrim($url, '/');
    }

    private function headers(Pannel $panel): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        if (!empty($panel->token)) {
            $headers['Authorization'] = $panel->token;
        }
        return $headers;
    }

    private function getServerHost(Pannel $panel): string
    {
        if (!empty($panel->url_port)) {
            return $panel->url_port;
        }
        $parsed = parse_url($panel->admin_url);
        return $parsed['host'] ?? '';
    }

    private function urlencodeIfNotNull(?string $value): string
    {
        return $value === null ? '' : rawurlencode($value);
    }

    private function buildVlessLink(string $host, int $port, string $uuid, string $network, ?string $security, ?string $path, ?string $sni, ?array $alpn, ?array $reality, string $remark): string
    {
        $params = [];
        if ($network !== '') { $params[] = 'type=' . $this->urlencodeIfNotNull($network); }
        if (!empty($security)) { $params[] = 'security=' . $this->urlencodeIfNotNull($security); }
        if (!empty($path)) { $params[] = 'path=' . $this->urlencodeIfNotNull($path); }
        if (!empty($sni)) { $params[] = 'sni=' . $this->urlencodeIfNotNull($sni); }
        if (!empty($alpn)) { $params[] = 'alpn=' . $this->urlencodeIfNotNull(implode(',', $alpn)); }
        if (!empty($reality)) {
            if (!empty($reality['publicKey'])) { $params[] = 'pbk=' . $this->urlencodeIfNotNull($reality['publicKey']); }
            if (!empty($reality['shortId'])) { $params[] = 'sid=' . $this->urlencodeIfNotNull($reality['shortId']); }
            if (!empty($reality['fingerprint'])) { $params[] = 'fp=' . $this->urlencodeIfNotNull($reality['fingerprint']); }
        }
        $query = implode('&', $params);
        return "vless://{$uuid}@{$host}:{$port}?{$query}#" . rawurlencode($remark);
    }

    private function buildVmessLink(string $host, int $port, string $uuid, string $network, ?string $tls, ?string $path, ?string $hostHeader, string $remark): string
    {
        $vmess = [
            'v' => '2',
            'ps' => $remark,
            'add' => $host,
            'port' => (string) $port,
            'id' => $uuid,
            'aid' => '0',
            'net' => $network ?: 'tcp',
            'type' => 'none',
            'host' => $hostHeader ?? '',
            'path' => $path ?? '',
            'tls' => $tls ? 'tls' : '',
        ];
        $b64 = base64_encode(json_encode($vmess));
        return 'vmess://' . $b64;
    }

    private function buildTrojanLink(string $host, int $port, string $password, string $network, ?string $tls, ?string $path, ?string $sni, string $remark): string
    {
        $params = [];
        if ($network !== '') { $params[] = 'type=' . $this->urlencodeIfNotNull($network); }
        if (!empty($tls)) { $params[] = 'security=' . $this->urlencodeIfNotNull($tls); }
        if (!empty($path)) { $params[] = 'path=' . $this->urlencodeIfNotNull($path); }
        if (!empty($sni)) { $params[] = 'sni=' . $this->urlencodeIfNotNull($sni); }
        $query = implode('&', $params);
        return "trojan://{$password}@{$host}:{$port}?{$query}#" . rawurlencode($remark);
    }

    private function httpWithAuth(Pannel $panel)
    {
        $req = Http::withHeaders($this->headers($panel));
        if (empty($panel->token) && !empty($panel->cookie_session)) {
            $cookies = json_decode($panel->cookie_session, true) ?? [];
            foreach ($cookies as $cookie) {
                $name = $cookie['Name'] ?? ($cookie['name'] ?? null);
                $value = $cookie['Value'] ?? ($cookie['value'] ?? null);
                if ($name !== null) {
                    $req = $req->withCookie($name, $value);
                }
            }
        }
        return $req;
    }

    public function login($pannelID)
    {
        $panel = Pannel::findOrFail($pannelID);
        // If we already have token or cookies, assume logged in
        if (!empty($panel->token) || !empty($panel->cookie_session)) {
            return true;
        }

        $base = $this->baseUrl($panel);
        if ($base === '') {
            return false;
        }

        $loginCandidates = ['/xui/login', '/login', '/panel/login'];
        foreach ($loginCandidates as $path) {
            try {
                $res = Http::asForm()->post($base . $path, [
                    'username' => $panel->username ?? 'admin',
                    'password' => $panel->password ?? '123456',
                ]);
                if ($res->status() === 200 || $res->status() === 302) {
                    $cookies = $res->cookies()->toArray();
                    if (!empty($cookies)) {
                        $panel->cookie_session = json_encode($cookies);
                        $panel->save();
                        return true;
                    }
                }
            } catch (\Throwable $th) {
                // try next endpoint
            }
        }
        return false;
    }

    public function syncInbounds($pannelID)
    {
        try {
            $panel = Pannel::findOrFail($pannelID);
            if (!$this->login($panel->id)) {
                return response()->json(false, 401);
            }

            $base = $this->baseUrl($panel);
            $paths = [
                '/xui/inbound/list',
                '/panel/api/inbounds',
                '/api/inbounds',
            ];
            $list = null;
            foreach ($paths as $path) {
                try {
                    $r = $this->httpWithAuth($panel)->get($base . $path);
                    if ($r->ok()) {
                        $list = $r->json();
                        break;
                    }
                } catch (\Throwable $th) {
                    // continue
                }
            }
            if (!$list || !is_array($list)) {
                return response()->json(false, 404);
            }

            // Normalize structure to array of inbounds
            // Some APIs wrap payload inside {inbounds: [...]} or {obj: [...]} or similar
            if (isset($list['inbounds']) && is_array($list['inbounds'])) {
                $list = $list['inbounds'];
            }

            foreach ($list as $in) {
                $protocol = $in['protocol'] ?? ($in['type'] ?? 'vless');
                $remark = $in['remark'] ?? ($in['tag'] ?? ('inbound-' . ($in['id'] ?? 'unknown')));
                $proxy = Proxy::firstOrCreate(
                    ['pannel_id' => $panel->id, 'type' => $protocol],
                    ['is_active' => true]
                );

                Inbound::updateOrCreate(
                    ['proxy_id' => $proxy->id, 'name' => $remark],
                    [
                        'is_active' => true,
                        'data' => json_encode([
                            'id' => $in['id'] ?? null,
                            'protocol' => $protocol,
                            'port' => $in['port'] ?? null,
                            'settings' => $in['settings'] ?? null,
                            'streamSettings' => $in['streamSettings'] ?? null,
                        ]),
                    ]
                );
            }
            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::info('syncInbounds error: ' . $th->getMessage());
            return response()->json(false, 500);
        }
    }

    public function addUserToSanaeiPanel(Request $request)
    {
        try {
            $pannelID = (int) $request->pannelID;
            $day = (int) $request->day;
            $volGb = (int) $request->vol;
            $accountId = (string) ($request->accountId ?? 'bot');

            $panel = Pannel::findOrFail($pannelID);
            if (!$this->login($panel->id)) {
                return false;
            }

            $uuid = (new HiddifyPannelController())->generateUUID();
            $expireSec = now('UTC')->addDays($day)->timestamp;
            // Most x-ui variants expect total traffic in bytes and expiryTime in ms
            $totalBytes = $volGb * 1024 * 1024 * 1024;
            $expiryMs = $expireSec * 1000;

            $inbounds = Proxy::where('pannel_id', $panel->id)
                ->where('is_active', true)
                ->with(['inbounds' => function ($q) {
                    $q->where('is_active', true);
                }])->get()->flatMap->inbounds;

            if ($inbounds->count() === 0) {
                // try to sync first time
                $this->syncInbounds($panel->id);
                $inbounds = Proxy::where('pannel_id', $panel->id)
                    ->where('is_active', true)
                    ->with(['inbounds' => function ($q) {
                        $q->where('is_active', true);
                    }])->get()->flatMap->inbounds;
            }

            $base = $this->baseUrl($panel);
            $paths = ['/xui/inbound/addClient', '/panel/api/inbounds/addClient'];
            $success = false;

            foreach ($inbounds as $in) {
                $meta = json_decode($in->data, true);
                $inboundId = null;
                if (is_array($meta) && isset($meta['id'])) {
                    $inboundId = $meta['id'];
                } elseif (is_numeric($in->data)) {
                    $inboundId = (int) $in->data;
                }
                if (!$inboundId) {
                    continue;
                }

                $bodies = [
                    [
                        'id' => $inboundId,
                        'client' => [
                            'id' => $uuid,
                            'email' => "bot{$accountId}",
                            'flow' => '',
                            'limitIp' => 0,
                            'totalGB' => $totalBytes,
                            'expiryTime' => $expiryMs,
                            'enable' => true,
                        ],
                    ],
                    [
                        'id' => $inboundId,
                        'settings' => [
                            'clients' => [[
                                'id' => $uuid,
                                'email' => "bot{$accountId}",
                                'flow' => '',
                                'limitIp' => 0,
                                'totalGB' => $totalBytes,
                                'expiryTime' => $expiryMs,
                                'enable' => true,
                            ]],
                        ],
                    ],
                ];

                foreach ($paths as $path) {
                    foreach ($bodies as $body) {
                        try {
                            $r = $this->httpWithAuth($panel)->post($base . $path, $body);
                            if ($r->ok()) {
                                $success = true;
                                break 2;
                            }
                        } catch (\Throwable $th) {
                            // try next
                        }
                    }
                }
            }

            return $success ? $uuid : false;
        } catch (\Throwable $th) {
            \Log::info('addUserToSanaeiPanel error: ' . $th->getMessage());
            return false;
        }
    }

    public function getUserLinks($pannelID, $uuid, $remark = '')
    {
        try {
            $panel = Pannel::findOrFail($pannelID);
            $host = $this->getServerHost($panel);
            $inbounds = Proxy::where('pannel_id', $panel->id)
                ->where('is_active', true)
                ->with(['inbounds' => function ($q) { $q->where('is_active', true); }])
                ->get()->flatMap->inbounds;

            $links = [];
            foreach ($inbounds as $in) {
                $meta = json_decode($in->data, true);
                if (!is_array($meta)) { continue; }
                $protocol = $meta['protocol'] ?? 'vless';
                $port = (int) ($meta['port'] ?? 0);
                $stream = $meta['streamSettings'] ?? [];
                $network = $stream['network'] ?? 'tcp';
                $security = $stream['security'] ?? null; // tls or reality
                $sni = null; $alpn = null; $wsPath = null; $hostHeader = null;
                if ($network === 'ws') {
                    $ws = $stream['wsSettings'] ?? [];
                    $wsPath = $ws['path'] ?? null;
                    $headers = $ws['headers'] ?? [];
                    $hostHeader = $headers['Host'] ?? null;
                }
                if ($security === 'tls') {
                    $tlsSet = $stream['tlsSettings'] ?? [];
                    $sni = $tlsSet['serverName'] ?? null;
                    $alpn = $tlsSet['alpn'] ?? null;
                }
                $reality = null;
                if ($security === 'reality') {
                    $realitySet = $stream['realitySettings'] ?? [];
                    $reality = [
                        'publicKey' => $realitySet['publicKey'] ?? null,
                        'shortId' => is_array($realitySet['shortId'] ?? null) ? (($realitySet['shortId'][0] ?? null)) : ($realitySet['shortId'] ?? null),
                        'fingerprint' => $realitySet['fingerprint'] ?? null,
                    ];
                    $sni = null;
                    $serverNames = $realitySet['serverNames'] ?? null;
                    if (is_array($serverNames) && count($serverNames) > 0) { $sni = $serverNames[0]; }
                }

                if ($protocol === 'vless') {
                    $links[] = $this->buildVlessLink($host, $port, $uuid, $network, $security, $wsPath, $sni, $alpn, $reality, $remark);
                } elseif ($protocol === 'vmess') {
                    $links[] = $this->buildVmessLink($host, $port, $uuid, $network, $security, $wsPath, $hostHeader, $remark);
                } elseif ($protocol === 'trojan') {
                    $links[] = $this->buildTrojanLink($host, $port, $uuid, $network, $security, $wsPath, $sni, $remark);
                }
            }
            return $links;
        } catch (\Throwable $th) {
            \Log::info('getUserLinks error: ' . $th->getMessage());
            return [];
        }
    }

    public function generateClientLinks(int $pannelID, string $uuid, string $remark): array
    {
        $panel = Pannel::findOrFail($pannelID);
        $host = parse_url($this->baseUrl($panel), PHP_URL_HOST) ?? ($panel->url_port ?? '');
        $links = [];
        $inbounds = Proxy::where('pannel_id', $panel->id)
            ->where('is_active', true)
            ->with(['inbounds' => function ($q) { $q->where('is_active', true); }])
            ->get()->flatMap->inbounds;

        foreach ($inbounds as $in) {
            $meta = json_decode($in->data, true);
            if (!is_array($meta)) { continue; }
            $protocol = $meta['protocol'] ?? 'vless';
            $port = $meta['port'] ?? null;
            $stream = $meta['streamSettings'] ?? [];
            $network = $stream['network'] ?? 'tcp';
            $security = $stream['security'] ?? '';
            $sni = '';
            if (isset($stream['tlsSettings']['serverName'])) { $sni = $stream['tlsSettings']['serverName']; }
            if (isset($stream['realitySettings']['serverNames'][0])) { $sni = $stream['realitySettings']['serverNames'][0]; }

            if ($protocol === 'vless') {
                $query = [];
                $query['type'] = $network;
                if ($network === 'ws') {
                    $ws = $stream['wsSettings'] ?? [];
                    if (isset($ws['path'])) { $query['path'] = $ws['path']; }
                    $hostHeader = $ws['headers']['Host'] ?? null;
                    if ($hostHeader) { $query['host'] = $hostHeader; }
                } elseif ($network === 'grpc') {
                    $grpc = $stream['grpcSettings'] ?? [];
                    if (isset($grpc['serviceName'])) { $query['serviceName'] = $grpc['serviceName']; }
                }
                if ($security === 'tls' || $security === 'reality') { $query['security'] = $security; }
                if ($sni) { $query['sni'] = $sni; }
                $q = http_build_query($query);
                $links[] = sprintf('vless://%s@%s:%s?%s#%s', $uuid, $host, $port, $q, rawurlencode($remark));
            } elseif ($protocol === 'vmess') {
                $conf = [
                    'v' => '2',
                    'ps' => $remark,
                    'add' => $host,
                    'port' => (string) $port,
                    'id' => $uuid,
                    'aid' => '0',
                    'scy' => 'auto',
                    'net' => $network,
                    'type' => 'none',
                    'host' => '',
                    'path' => '',
                    'tls' => ($security === 'tls' ? 'tls' : ''),
                    'sni' => $sni,
                ];
                if ($network === 'ws') {
                    $ws = $stream['wsSettings'] ?? [];
                    $conf['path'] = $ws['path'] ?? '';
                    $conf['host'] = $ws['headers']['Host'] ?? '';
                }
                $json = json_encode($conf, JSON_UNESCAPED_SLASHES);
                $links[] = 'vmess://' . base64_encode($json);
            } elseif ($protocol === 'trojan') {
                // Best-effort: use uuid as password
                $query = [];
                if ($security === 'tls') { $query['security'] = 'tls'; }
                if ($sni) { $query['sni'] = $sni; }
                if ($network === 'ws') {
                    $query['type'] = 'ws';
                    $ws = $stream['wsSettings'] ?? [];
                    if (isset($ws['path'])) { $query['path'] = $ws['path']; }
                    $hostHeader = $ws['headers']['Host'] ?? null;
                    if ($hostHeader) { $query['host'] = $hostHeader; }
                }
                $q = http_build_query($query);
                $links[] = sprintf('trojan://%s@%s:%s?%s#%s', $uuid, $host, $port, $q, rawurlencode($remark));
            }
        }

        return $links;
    }

    // New methods for deletion, activation, updating and limits
    public function deleteUser($pannelID, $uuid)
    {
        try {
            $panel = Pannel::findOrFail($pannelID);
            if (!$this->login($panel->id)) {
                return response()->json(false, 401);
            }
            $base = $this->baseUrl($panel);
            $paths = ['/xui/inbound/delClient', '/panel/api/inbounds/delClient'];

            $inbounds = Proxy::where('pannel_id', $panel->id)
                ->with('inbounds')
                ->get()->flatMap->inbounds;

            $ok = false;
            foreach ($inbounds as $in) {
                $meta = json_decode($in->data, true);
                $inboundId = is_array($meta) ? ($meta['id'] ?? null) : (is_numeric($in->data) ? (int) $in->data : null);
                if (!$inboundId) continue;

                $bodies = [
                    ['id' => $inboundId, 'client' => ['id' => $uuid]],
                    ['id' => $inboundId, 'uuid' => $uuid],
                ];
                foreach ($paths as $path) {
                    foreach ($bodies as $body) {
                        try {
                            $r = $this->httpWithAuth($panel)->post($base . $path, $body);
                            if ($r->ok()) { $ok = true; break 2; }
                        } catch (\Throwable $th) {
                        }
                    }
                }
            }
            return $ok ? response()->json(true, 200) : response()->json(false, 401);
        } catch (\Throwable $th) {
            \Log::info('deleteUser error: ' . $th->getMessage());
            return response()->json(false, 500);
        }
    }

    public function changeUserActivation($pannelID, $uuid, bool $enable)
    {
        try {
            $panel = Pannel::findOrFail($pannelID);
            if (!$this->login($panel->id)) {
                return response()->json(false, 401);
            }
            $base = $this->baseUrl($panel);
            $paths = ['/xui/inbound/updateClient', '/panel/api/inbounds/updateClient'];

            $inbounds = Proxy::where('pannel_id', $panel->id)
                ->with('inbounds')
                ->get()->flatMap->inbounds;

            $ok = false;
            foreach ($inbounds as $in) {
                $meta = json_decode($in->data, true);
                $inboundId = is_array($meta) ? ($meta['id'] ?? null) : (is_numeric($in->data) ? (int) $in->data : null);
                if (!$inboundId) continue;

                $body = [
                    'id' => $inboundId,
                    'client' => [
                        'id' => $uuid,
                        'enable' => $enable,
                    ],
                ];
                foreach ($paths as $path) {
                    try {
                        $r = $this->httpWithAuth($panel)->post($base . $path, $body);
                        if ($r->ok()) { $ok = true; break; }
                    } catch (\Throwable $th) {
                    }
                }
                if ($ok) break;
            }
            return $ok ? response()->json(true, 200) : response()->json(false, 401);
        } catch (\Throwable $th) {
            \Log::info('changeUserActivation error: ' . $th->getMessage());
            return response()->json(false, 500);
        }
    }

    public function updateUser($pannelID, $uuid, array $fields)
    {
        // Generic update: can be used for rename (email) or limits
        try {
            $panel = Pannel::findOrFail($pannelID);
            if (!$this->login($panel->id)) {
                return response()->json(false, 401);
            }
            $base = $this->baseUrl($panel);
            $paths = ['/xui/inbound/updateClient', '/panel/api/inbounds/updateClient'];

            $inbounds = Proxy::where('pannel_id', $panel->id)
                ->with('inbounds')
                ->get()->flatMap->inbounds;

            $ok = false;
            foreach ($inbounds as $in) {
                $meta = json_decode($in->data, true);
                $inboundId = is_array($meta) ? ($meta['id'] ?? null) : (is_numeric($in->data) ? (int) $in->data : null);
                if (!$inboundId) continue;

                $client = array_merge(['id' => $uuid], $fields);
                $body = ['id' => $inboundId, 'client' => $client];
                foreach ($paths as $path) {
                    try {
                        $r = $this->httpWithAuth($panel)->post($base . $path, $body);
                        if ($r->ok()) { $ok = true; break; }
                    } catch (\Throwable $th) {
                    }
                }
                if ($ok) break;
            }
            return $ok ? response()->json(true, 200) : response()->json(false, 401);
        } catch (\Throwable $th) {
            \Log::info('updateUser error: ' . $th->getMessage());
            return response()->json(false, 500);
        }
    }

    public function updateLimits($pannelID, $uuid, int $day, int $volGb)
    {
        $expireSec = now('UTC')->addDays($day)->timestamp;
        $totalBytes = $volGb * 1024 * 1024 * 1024;
        return $this->updateUser($pannelID, $uuid, [
            'totalGB' => $totalBytes,
            'expiryTime' => $expireSec * 1000,
        ]);
    }
}


