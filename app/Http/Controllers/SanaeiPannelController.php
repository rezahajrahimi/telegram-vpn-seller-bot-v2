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
        if ($network !== '') {
            $params[] = 'type=' . $this->urlencodeIfNotNull($network);
        }
        if (!empty($security)) {
            $params[] = 'security=' . $this->urlencodeIfNotNull($security);
        }
        if (!empty($path)) {
            $params[] = 'path=' . $this->urlencodeIfNotNull($path);
        }
        if (!empty($sni)) {
            $params[] = 'sni=' . $this->urlencodeIfNotNull($sni);
        }
        if (!empty($alpn)) {
            $params[] = 'alpn=' . $this->urlencodeIfNotNull(implode(',', $alpn));
        }
        if (!empty($reality)) {
            if (!empty($reality['publicKey'])) {
                $params[] = 'pbk=' . $this->urlencodeIfNotNull($reality['publicKey']);
            }
            if (!empty($reality['shortId'])) {
                $params[] = 'sid=' . $this->urlencodeIfNotNull($reality['shortId']);
            }
            if (!empty($reality['fingerprint'])) {
                $params[] = 'fp=' . $this->urlencodeIfNotNull($reality['fingerprint']);
            }
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
        if ($network !== '') {
            $params[] = 'type=' . $this->urlencodeIfNotNull($network);
        }
        if (!empty($tls)) {
            $params[] = 'security=' . $this->urlencodeIfNotNull($tls);
        }
        if (!empty($path)) {
            $params[] = 'path=' . $this->urlencodeIfNotNull($path);
        }
        if (!empty($sni)) {
            $params[] = 'sni=' . $this->urlencodeIfNotNull($sni);
        }
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

    /**
     * Parse a raw Cookie header string (e.g. "lang=en-US; 3x-ui=...") into an array of
     * structures compatible with stored cookie format (Name/Value).
     */
    private function parseCookieHeader(string $cookieHeader): array
    {
        $raw = trim($cookieHeader);
        if ($raw === '') {
            return [];
        }
        if (stripos($raw, 'Cookie:') === 0) {
            $raw = trim(substr($raw, 7));
        }
        $raw = trim($raw, " \t\n\r\0\x0B\"'");
        $parts = preg_split('/;\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $cookies = [];
        foreach ($parts as $part) {
            $eqPos = strpos($part, '=');
            if ($eqPos === false) {
                continue;
            }
            $name = trim(substr($part, 0, $eqPos));
            $value = substr($part, $eqPos + 1);
            if ($name === '') {
                continue;
            }
            $cookies[] = [
                'Name' => $name,
                'Value' => $value,
            ];
        }
        return $cookies;
    }

    /**
     * Set raw cookie header string for a panel, store into cookie_session, and validate.
     */
    public function setRawCookie(Request $request, $pannelID)
    {
        try {
            $panel = Pannel::findOrFail((int) $pannelID);
            $raw = (string) ($request->input('cookie') ?? $request->input('cookie_raw') ?? '');
            if ($raw === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'cookie is required',
                ], 422);
            }
            $cookiesArray = $this->parseCookieHeader($raw);
            if (empty($cookiesArray)) {
                return response()->json([
                    'success' => false,
                    'message' => 'invalid cookie format',
                ], 422);
            }
            $panel->cookie_session = json_encode($cookiesArray);
            $panel->save();
            $valid = $this->isCookieValid($panel);
            \Log::info('setRawCookie applied', ['panel_id' => (int) $pannelID, 'valid' => $valid]);
            return response()->json([
                'success' => true,
                'valid' => $valid,
            ]);
        } catch (\Throwable $th) {
            \Log::error('setRawCookie error: ' . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'server error',
            ], 500);
        }
    }

    /**
     * Check if current cookies are still valid
     */
    private function isCookieValid(Pannel $panel): bool
    {
        if (empty($panel->cookie_session)) {
            return false;
        }

        try {
            // Try to make a simple API call to check if cookies are valid
            $base = $this->baseUrl($panel);
            $testPaths = ['/panel/api/inbounds'];

            foreach ($testPaths as $path) {
                try {
                    $response = $this->httpWithAuth($panel)->get($base . $path);
                    if ($response->ok()) {
                        return true; // Cookies are still valid
                    }
                } catch (\Throwable $th) {
                    continue;
                }
            }

            return false; // All test paths failed
        } catch (\Throwable $th) {
            return false;
        }
    }

    public function login($pannelID)
    {
        $panel = Pannel::findOrFail($pannelID);

        // // If we have a token, assume we're authenticated
        // if (!empty($panel->token)) {
        //     return true;
        // }

        // If we have cookies, check if they're still valid
        if (!empty($panel->cookie_session) && $this->isCookieValid($panel)) {
            \Log::info("cook valid");
            return true;
        }

        // If cookies are invalid or expired, clear them
        if (!empty($panel->cookie_session)) {
            \Log::info("If cookies are invalid or expired, clear them");
            $panel->cookie_session = null;
            $panel->update();
        }

        $base = $this->baseUrl($panel);
        if ($base === '') {
            return false;
        }

        $loginCandidates = ['/login'];
        foreach ($loginCandidates as $path) {
            try {
                $res = Http::asForm()->post($base . $path, [
                    'username' => $panel->username ?? 'admin',
                    'password' => $panel->password ?? '123456',
                ]);
                if ($res->status() === 200 || $res->status() === 302) {
                    $cookies = $res->cookies()->toArray();
                    if (!empty($cookies)) {
                        $panel->cookie_session = json_encode($cookies); // Always store as JSON
                        \Log::info($cookies);
                        $panel->update();
                        \Log::info('Successfully logged in to Sanaei panel', ['panel_id' => $pannelID]);
                        return  $cookies;
                    }
                }
            } catch (\Throwable $th) {
                \Log::warning('Login attempt failed for path', ['panel_id' => $pannelID, 'path' => $path, 'error' => $th->getMessage()]);
                // try next endpoint
            }
        }

        \Log::error('Failed to login to Sanaei panel', ['panel_id' => $pannelID]);
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
                '/panel/api/inbounds',
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
                        'port' => $in['port'] ?? null,
                        'protocol' => $protocol,
                        'data' => json_encode([
                            'id' => $in['id'] ?? null,
                            'protocol' => $protocol,
                            'port' => $in['port'] ?? null,
                            'settings' => $in['settings'] ?? null,
                            'streamSettings' => $in['streamSettings'] ?? null,
                        ]),
                        'settings' => json_encode($in['settings'] ?? []),
                        'stream_settings' => json_encode($in['streamSettings'] ?? []),
                        'tag' => $in['tag'] ?? null,
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
            $templateId = (int) ($request->template_id ?? 0);

            $panel = Pannel::findOrFail($pannelID);
            if (!$this->login($panel->id)) {
                return false;
            }
            \Log::info("addUserToSanaeiPanel request: " . json_encode($request));

            $uuid = (new HiddifyPannelController())->generateUUID();
            $expireSec = now('UTC')->addDays($day)->timestamp;
            $totalBytes = $volGb * 1024 * 1024 * 1024;
            $expiryMs = $expireSec * 1000;

            // Select the best available inbound source
            $source = $this->selectBestInboundSource($pannelID, $templateId);
            \Log::info("Selected inbound source", $source);

            if ($source['source_type'] === 'none') {
                \Log::error("No inbound source available", ['panel_id' => $pannelID]);
                return false;
            }

            // If we have a template, use it
            if (in_array($source['source_type'], ['specific_template', 'auto_template'])) {
                $request->merge(['template_id' => $source['template_id']]);
                \Log::info("Using template for user creation", ['template_id' => $source['template_id']]);
                return $this->addUserWithTemplate($request);
            }

            // Otherwise, use inbounds table
            if ($source['source_type'] === 'inbounds_table') {
                return $this->createUserWithInbounds($panel, $uuid, $totalBytes, $expiryMs, $accountId, $source['inbounds']);
            }

            return false;
        } catch (\Throwable $th) {
            \Log::error('addUserToSanaeiPanel error: ' . $th->getMessage());
            return false;
        }
    }

    /**
     * Create user using inbounds from the table
     */
    private function createUserWithInbounds(Pannel $panel, string $uuid, int $totalBytes, int $expiryMs, string $accountId, $inbounds): bool
    {
        try {
            $base = $this->baseUrl($panel);
            $paths = ['/xui/inbound/add', '/panel/api/inbounds/add'];
            $success = false;

            foreach ($inbounds as $in) {
                $inboundId = $in->getInboundId();
                if (!$inboundId) {
                    \Log::warning("Could not get inbound ID", ['inbound' => $in->toArray()]);
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
                            'clients' => [
                                [
                                    'id' => $uuid,
                                    'email' => "bot{$accountId}",
                                    'flow' => '',
                                    'limitIp' => 0,
                                    'totalGB' => $totalBytes,
                                    'expiryTime' => $expiryMs,
                                    'enable' => true,
                                ]
                            ],
                        ],
                    ],
                ];

                foreach ($paths as $path) {
                    foreach ($bodies as $body) {
                        try {
                            \Log::info("Creating user with inbound", [
                                'inbound_id' => $inboundId,
                                'body' => $body
                            ]);

                            $r = $this->httpWithAuth($panel)->post($base . $path, $body);

                            if ($r->ok()) {
                                $success = true;
                                \Log::info("User created successfully with inbound", [
                                    'inbound_id' => $inboundId,
                                    'uuid' => $uuid
                                ]);
                                break 2;
                            } else {
                                \Log::warning("Failed to create user with inbound", [
                                    'inbound_id' => $inboundId,
                                    'response_status' => $r->status(),
                                    'response_body' => $r->body()
                                ]);
                            }
                        } catch (\Throwable $th) {
                            \Log::error("Error creating user with inbound", [
                                'inbound_id' => $inboundId,
                                'error' => $th->getMessage()
                            ]);
                        }
                    }
                }
            }

            if ($success) {
                \Log::info("User created successfully using inbounds table", [
                    'uuid' => $uuid,
                    'panel_id' => $panel->id
                ]);
            } else {
                \Log::error("Failed to create user using inbounds table", [
                    'panel_id' => $panel->id,
                    'inbound_count' => $inbounds->count()
                ]);
            }

            return $success;
        } catch (\Throwable $th) {
            \Log::error("createUserWithInbounds error: " . $th->getMessage());
            return false;
        }
    }

    public function getUserLinks($pannelID, $uuid, $remark = '')
    {
        try {
            $panel = Pannel::findOrFail($pannelID);

            // Check if we're logged in
            if (!$this->login($panel->id)) {
                \Log::warning('getUserLinks: Failed to login to panel', ['panel_id' => $pannelID]);
                return [];
            }

            $host = $this->getServerHost($panel);
            $inbounds = Proxy::where('pannel_id', $panel->id)
                ->where('is_active', true)
                ->with([
                    'inbounds' => function ($q) {
                        $q->where('is_active', true);
                    }
                ])
                ->get()->flatMap->inbounds;

            $links = [];
            foreach ($inbounds as $in) {
                // Use new model fields and methods
                $protocol = $in->protocol ?? 'vless';
                $port = $in->port ?? 0;
                $stream = $in->parsed_stream_settings;
                $network = $stream['network'] ?? 'tcp';
                $security = $stream['security'] ?? null; // tls or reality
                $sni = null;
                $alpn = null;
                $wsPath = null;
                $hostHeader = null;
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
                    if (is_array($serverNames) && count($serverNames) > 0) {
                        $sni = $serverNames[0];
                    }
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
        try {
            $panel = Pannel::findOrFail($pannelID);

            // Check if we're logged in
            if (!$this->login($panel->id)) {
                \Log::warning('generateClientLinks: Failed to login to panel', ['panel_id' => $pannelID]);
                return [];
            }

            $host = parse_url($this->baseUrl($panel), PHP_URL_HOST) ?? ($panel->url_port ?? '');
            $links = [];
            $inbounds = Proxy::where('pannel_id', $panel->id)
                ->where('is_active', true)
                ->with([
                    'inbounds' => function ($q) {
                        $q->where('is_active', true);
                    }
                ])
                ->get()->flatMap->inbounds;

            foreach ($inbounds as $in) {
                // Use new model fields
                $protocol = $in->protocol ?? 'vless';
                $port = $in->port ?? null;
                $stream = $in->parsed_stream_settings;
                $network = $stream['network'] ?? 'tcp';
                $security = $stream['security'] ?? '';
                $sni = '';
                if (isset($stream['tlsSettings']['serverName'])) {
                    $sni = $stream['tlsSettings']['serverName'];
                }
                if (isset($stream['realitySettings']['serverNames'][0])) {
                    $sni = $stream['realitySettings']['serverNames'][0];
                }

                if ($protocol === 'vless') {
                    $query = [];
                    $query['type'] = $network;
                    if ($network === 'ws') {
                        $ws = $stream['wsSettings'] ?? [];
                        if (isset($ws['path'])) {
                            $query['path'] = $ws['path'];
                        }
                        $hostHeader = $ws['headers']['Host'] ?? null;
                        if ($hostHeader) {
                            $query['host'] = $hostHeader;
                        }
                    } elseif ($network === 'grpc') {
                        $grpc = $stream['grpcSettings'] ?? [];
                        if (isset($grpc['serviceName'])) {
                            $query['serviceName'] = $grpc['serviceName'];
                        }
                    }
                    if ($security === 'tls' || $security === 'reality') {
                        $query['security'] = $security;
                    }
                    if ($sni) {
                        $query['sni'] = $sni;
                    }
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
                    if ($security === 'tls') {
                        $query['security'] = 'tls';
                    }
                    if ($sni) {
                        $query['sni'] = $sni;
                    }
                    if ($network === 'ws') {
                        $query['type'] = 'ws';
                        $ws = $stream['wsSettings'] ?? [];
                        if (isset($ws['path'])) {
                            $query['path'] = $ws['path'];
                        }
                        $hostHeader = $ws['headers']['Host'] ?? null;
                        if ($hostHeader) {
                            $query['host'] = $hostHeader;
                        }
                    }
                    $q = http_build_query($query);
                    $links[] = sprintf('trojan://%s@%s:%s?%s#%s', $uuid, $host, $port, $q, rawurlencode($remark));
                }
            }

            return $links;
        } catch (\Throwable $th) {
            \Log::error('generateClientLinks error: ' . $th->getMessage());
            return [];
        }
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
                if (!$inboundId)
                    continue;

                $bodies = [
                    ['id' => $inboundId, 'client' => ['id' => $uuid]],
                    ['id' => $inboundId, 'uuid' => $uuid],
                ];
                foreach ($paths as $path) {
                    foreach ($bodies as $body) {
                        try {
                            $r = $this->httpWithAuth($panel)->post($base . $path, $body);
                            if ($r->ok()) {
                                $ok = true;
                                break 2;
                            }
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
                if (!$inboundId)
                    continue;

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
                        if ($r->ok()) {
                            $ok = true;
                            break;
                        }
                    } catch (\Throwable $th) {
                    }
                }
                if ($ok)
                    break;
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
                if (!$inboundId)
                    continue;

                $client = array_merge(['id' => $uuid], $fields);
                $body = ['id' => $inboundId, 'client' => $client];
                foreach ($paths as $path) {
                    try {
                        $r = $this->httpWithAuth($panel)->post($base . $path, $body);
                        if ($r->ok()) {
                            $ok = true;
                            break;
                        }
                    } catch (\Throwable $th) {
                    }
                }
                if ($ok)
                    break;
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

    /**
     * Add user to Sanaei panel using a specific inbound template
     */
    public function addUserWithTemplate(Request $request)
    {
        try {
            $pannelID = (int) $request->pannelID;
            $day = (int) $request->day;
            $volGb = (int) $request->vol;
            $accountId = (string) ($request->accountId ?? 'bot');
            $templateId = (int) $request->template_id;

            $panel = Pannel::findOrFail($pannelID);
            if (!$this->login($panel->id)) {
                return false;
            }

            // Get the template
            $template = \App\Models\InboundTemplate::findOrFail($templateId);
            if (!$template->is_active || $template->pannel_id !== $pannelID) {
                return false;
            }

            $uuid = (new HiddifyPannelController())->generateUUID();
            $expireSec = now('UTC')->addDays($day)->timestamp;
            $totalBytes = $volGb * 1024 * 1024 * 1024;
            $expiryMs = $expireSec * 1000;

            // Use template configuration
            $inboundConfig = $template->toInboundConfig();
            $inboundId = $inboundConfig['id'];

            $base = $this->baseUrl($panel);

            // Per provided cURL, use the form-encoded endpoint
            $paths = ['/panel/inbound/add', '/xui/inbound/add'];
            $success = false;

            $inboundProtocol = strtolower((string) ($inboundConfig['protocol'] ?? $template->protocol ?? ''));
            $settingsFromTemplate = $inboundConfig['settings'] ?? $template->parsed_settings ?? [];
            $clientRecord = [];
            if (in_array($inboundProtocol, ['vless', 'vmess'], true)) {
                $clientRecord = [
                    'id' => $uuid,
                    'email' => "bot{$accountId}",
                    'flow' => '',
                    'limitIp' => 0,
                    'totalGB' => 0,
                    'expiryTime' => 0,
                    'enable' => true,
                    'tgId' => '',
                    'subId' => substr(md5($uuid), 0, 16),
                    'comment' => '',
                    'reset' => 0,
                ];
            } elseif ($inboundProtocol === 'trojan') {
                $clientRecord = [
                    'password' => $uuid,
                    'email' => "bot{$accountId}",
                    'limitIp' => 0,
                    'totalGB' => 0,
                    'expiryTime' => 0,
                    'enable' => true,
                ];
            }

            $settings = $settingsFromTemplate;
            $settings['clients'] = [$clientRecord];
            if ($inboundProtocol === 'vless' && empty($settings['decryption'])) {
                $settings['decryption'] = 'none';
            }

            $streamSettings = $inboundConfig['streamSettings'] ?? $template->parsed_stream_settings ?? [];
            if (empty($streamSettings)) {
                $streamSettings = [
                    'network' => 'tcp',
                    'security' => 'none',
                    'externalProxy' => [],
                    'tcpSettings' => [
                        'acceptProxyProtocol' => false,
                        'header' => ['type' => 'none'],
                    ],
                ];
            }

            $sniffing = [
                'enabled' => false,
                'destOverride' => ['http', 'tls', 'quic', 'fakedns'],
                'metadataOnly' => false,
                'routeOnly' => false,
            ];
            $allocate = [
                'strategy' => 'always',
                'refresh' => 5,
                'concurrency' => 3,
            ];

            $listen = $inboundConfig['listen'] ?? $template->listen ?? '';
            $port = (int) ($inboundConfig['port'] ?? $template->port ?? 0);

            $form = [
                'up' => 0,
                'down' => 0,
                'total' => 0,
                'remark' => (string) $accountId,
                'enable' => true,
                'expiryTime' => $expiryMs,
                'listen' => (string) $listen,
                'port' => $port,
                'protocol' => $inboundProtocol,
                'settings' => json_encode($settings, JSON_UNESCAPED_SLASHES),
                'streamSettings' => json_encode($streamSettings, JSON_UNESCAPED_SLASHES),
                'sniffing' => json_encode($sniffing, JSON_UNESCAPED_SLASHES),
                'allocate' => json_encode($allocate, JSON_UNESCAPED_SLASHES),
            ];

            // Prepare cookies from panel->cookie_session
            $cookies = json_decode($panel->cookie_session, true) ?? [];
            foreach ($paths as $path) {
                try {
                    $req = $this->httpWithAuth($panel)
                        ->asForm()
                        ->withHeaders([
                            'Accept' => 'application/json, text/plain, */*',
                            'X-Requested-With' => 'XMLHttpRequest',
                        ]);
                    // Add all cookies if present
                    foreach ($cookies as $cookie) {
                        $name = $cookie['Name'] ?? ($cookie['name'] ?? null);
                        $value = $cookie['Value'] ?? ($cookie['value'] ?? null);
                        if ($name !== null) {
                            $req = $req->withCookie($name, $value);
                        }
                    }
                    $r = $req->post($base . $path, $form);
                    \Log::info('addUserWithTemplate form endpoint response', [
                        'path' => $base . $path,
                        'status' => $r->status(),
                        'body' => $r->body(),
                    ]);
                    // if cookie/session expired, refresh login and retry once
                    if ($r->status() === 401) {
                        try {
                            \Log::info('401 from panel, refreshing login and retrying once', ['panel_id' => $pannelID, 'path' => $path]);
                            // clear and re-login
                            $panel->cookie_session = null;
                            $panel->save();
                            if ($this->login($panel->id)) {
                                $panel = Pannel::findOrFail($pannelID);
                                $cookies = json_decode($panel->cookie_session, true) ?? [];
                                $req = $this->httpWithAuth($panel)
                                    ->asForm()
                                    ->withHeaders([
                                        'Accept' => 'application/json, text/plain, */*',
                                        'X-Requested-With' => 'XMLHttpRequest',
                                    ]);
                                foreach ($cookies as $cookie) {
                                    $name = $cookie['Name'] ?? ($cookie['name'] ?? null);
                                    $value = $cookie['Value'] ?? ($cookie['value'] ?? null);
                                    if ($name !== null) {
                                        $req = $req->withCookie($name, $value);
                                    }
                                }
                                $r = $req->post($base . $path, $form);
                                \Log::info('Retry after refresh response', ['status' => $r->status(), 'body' => $r->body()]);
                            }
                        } catch (\Throwable $th) {
                            \Log::error('Retry after 401 failed', ['error' => $th->getMessage()]);
                        }
                    }

                    if ($r->ok()) {
                        $success = true;
                        break;
                    }
                } catch (\Throwable $th) {
                    // try next
                }
            }

            if ($success) {
                // \Log::info("User created with template", [
                //     'template_id' => $templateId,
                //     'uuid' => $uuid,
                // ]);
            }
            return $success;
        } catch (\Throwable $th) {
            \Log::error('Error in user creation: ' . $th->getMessage());
            return false;
        }
    }
    /**
     * Check login status and return panel info
     */
    public function checkLoginStatus($pannelID)
    {
        try {
            $panel = Pannel::findOrFail($pannelID);

            $status = [
                'panel_id' => $panel->id,
                'panel_name' => $panel->name ?? 'Unknown',
                'admin_url' => $panel->admin_url,
                'has_token' => !empty($panel->token),
                'has_cookies' => !empty($panel->cookie_session),
                'cookies_valid' => false,
                'login_status' => 'unknown'
            ];

            if (!empty($panel->token)) {
                $status['login_status'] = 'token_authenticated';
                $status['cookies_valid'] = true;
            } elseif (!empty($panel->cookie_session)) {
                if ($this->isCookieValid($panel)) {
                    $status['login_status'] = 'cookie_authenticated';
                    $status['cookies_valid'] = true;
                } else {
                    $status['login_status'] = 'cookie_expired';
                    $status['cookies_valid'] = false;
                }
            } else {
                $status['login_status'] = 'not_authenticated';
            }

            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (\Exception $e) {
            \Log::error('Check login status error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error occurred'
            ], 500);
        }
    }

    /**
     * Select the best available template or inbound for user creation
     */
    private function selectBestInboundSource(int $pannelID, int $templateId = 0): array
    {
        $result = [
            'source_type' => 'none',
            'template_id' => null,
            'inbounds' => collect(),
            'message' => ''
        ];

        // 1. If specific template is requested, use it
        if ($templateId > 0) {
            $template = \App\Models\InboundTemplate::where('id', $templateId)
                ->where('pannel_id', $pannelID)
                ->where('is_active', true)
                ->first();

            if ($template) {
                $result['source_type'] = 'specific_template';
                $result['template_id'] = $template->id;
                $result['message'] = "Using specific template: {$template->name}";
                \Log::info("Selected specific template", ['template_id' => $template->id, 'name' => $template->name]);
                return $result;
            } else {
                $result['message'] = "Requested template not found or inactive";
                \Log::warning("Requested template not found", ['template_id' => $templateId, 'panel_id' => $pannelID]);
            }
        }

        // 2. Try to find active templates for this panel
        $activeTemplates = \App\Models\InboundTemplate::where('pannel_id', $pannelID)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc') // Use newest first
            ->get();

        if ($activeTemplates->count() > 0) {
            $bestTemplate = $activeTemplates->first();
            $result['source_type'] = 'auto_template';
            $result['template_id'] = $bestTemplate->id;
            $result['message'] = "Using auto-selected template: {$bestTemplate->name}";
            \Log::info("Selected auto template", [
                'template_id' => $bestTemplate->id,
                'name' => $bestTemplate->name,
                'total_templates' => $activeTemplates->count()
            ]);
            return $result;
        }

        // 3. Fall back to inbounds table
        $inbounds = Proxy::where('pannel_id', $pannelID)
            ->where('is_active', true)
            ->with([
                'inbounds' => function ($q) {
                    $q->where('is_active', true);
                }
            ])->get()->flatMap->inbounds;

        if ($inbounds->count() === 0) {
            // Try to sync from panel
            \Log::info("No inbounds found, attempting to sync from panel", ['panel_id' => $pannelID]);
            $this->syncInbounds($pannelID);

            $inbounds = Proxy::where('pannel_id', $pannelID)
                ->where('is_active', true)
                ->with([
                    'inbounds' => function ($q) {
                        $q->where('is_active', true);
                    }
                ])->get()->flatMap->inbounds;
        }

        if ($inbounds->count() > 0) {
            $result['source_type'] = 'inbounds_table';
            $result['inbounds'] = $inbounds;
            $result['message'] = "Using inbounds table with {$inbounds->count()} inbounds";
            \Log::info("Selected inbounds table", [
                'inbound_count' => $inbounds->count(),
                'inbound_ids' => $inbounds->pluck('id')->toArray()
            ]);
            return $result;
        }

        // 4. Nothing available
        $result['message'] = "No templates or inbounds available for this panel";
        \Log::error("No inbound sources available", ['panel_id' => $pannelID]);
        return $result;
    }

    /**
     * Check available inbound sources for a panel
     */
    public function checkInboundSources($pannelID)
    {
        try {
            $panel = Pannel::findOrFail($pannelID);

            // Check login status first
            if (!$this->login($panel->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to login to panel',
                    'data' => null
                ], 401);
            }

            $result = [
                'panel_id' => $panel->id,
                'panel_name' => $panel->name ?? 'Unknown',
                'admin_url' => $panel->admin_url,
                'sources' => []
            ];

            // Check templates
            $templates = \App\Models\InboundTemplate::where('pannel_id', $pannelID)
                ->where('is_active', true)
                ->select('id', 'name', 'description', 'protocol', 'port', 'config_type')
                ->get();

            if ($templates->count() > 0) {
                $result['sources']['templates'] = [
                    'count' => $templates->count(),
                    'items' => $templates->toArray(),
                    'recommended' => true
                ];
            }

            // Check inbounds table
            $inbounds = Proxy::where('pannel_id', $panel->id)
                ->where('is_active', true)
                ->with([
                    'inbounds' => function ($q) {
                        $q->where('is_active', true);
                    }
                ])->get()->flatMap->inbounds;

            if ($inbounds->count() === 0) {
                // Try to sync
                $this->syncInbounds($panel->id);
                $inbounds = Proxy::where('pannel_id', $panel->id)
                    ->where('is_active', true)
                    ->with([
                        'inbounds' => function ($q) {
                            $q->where('is_active', true);
                        }
                    ])->get()->flatMap->inbounds;
            }

            if ($inbounds->count() > 0) {
                $result['sources']['inbounds_table'] = [
                    'count' => $inbounds->count(),
                    'items' => $inbounds->map(function ($in) {
                        return [
                            'id' => $in->id,
                            'name' => $in->name,
                            'protocol' => $in->protocol,
                            'port' => $in->port,
                            'tag' => $in->tag
                        ];
                    })->toArray(),
                    'recommended' => !$templates->count() // Only recommended if no templates
                ];
            }

            // Determine best source
            if ($templates->count() > 0) {
                $result['best_source'] = 'template';
                $result['best_source_id'] = $templates->first()->id;
                $result['best_source_name'] = $templates->first()->name;
            } elseif ($inbounds->count() > 0) {
                $result['best_source'] = 'inbounds_table';
                $result['best_source_count'] = $inbounds->count();
            } else {
                $result['best_source'] = 'none';
                $result['message'] = 'No inbound sources available';
            }

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            \Log::error('Check inbound sources error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error occurred'
            ], 500);
        }
    }
}


