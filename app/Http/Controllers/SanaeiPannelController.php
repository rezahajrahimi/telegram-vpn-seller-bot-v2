<?php

namespace App\Http\Controllers;

use App\Models\Pannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Nette\Utils\Random;
use Carbon\Carbon;

class SanaeiPannelController extends Controller
{
    private $apiPrefix = '/panel/api';

    /** @var array<int, string> */
    private array $panelApiVersions = [];

    private function authToken(Pannel $panel): string
    {
        $token = trim((string) ($panel->token ?? ''));
        if ($token === '' || $token === 'Bearer') {
            return '';
        }
        if (!str_starts_with($token, 'Bearer ')) {
            return 'Bearer ' . $token;
        }
        return $token;
    }

    private function detectApiVersion(Pannel $panel): string
    {
        if (isset($this->panelApiVersions[$panel->id])) {
            return $this->panelApiVersions[$panel->id];
        }
        if (!$this->login($panel)) {
            $this->panelApiVersions[$panel->id] = 'v2';
            return 'v2';
        }
        $res = $this->performRequest($panel, 'GET', '/clients/list');
        $this->panelApiVersions[$panel->id] = ($res !== null) ? 'v3' : 'v2';
        return $this->panelApiVersions[$panel->id];
    }

    private function isV3(Pannel $panel): bool
    {
        return $this->detectApiVersion($panel) === 'v3';
    }

    private function performRequestWithFallback(
        Pannel $panel,
        string $method,
        string $v3Path,
        string $v2Path,
        $body = null,
        $asJson = true
    ) {
        $res = $this->performRequest($panel, $method, $v3Path, $body, $asJson);
        if ($res !== null) {
            $this->panelApiVersions[$panel->id] = 'v3';
            return $res;
        }
        $res = $this->performRequest($panel, $method, $v2Path, $body, $asJson);
        if ($res !== null) {
            $this->panelApiVersions[$panel->id] = 'v2';
        }
        return $res;
    }

    private function normalizeV3ClientRecord(array $rec, ?array $inboundIds = null): array
    {
        $uuid = $rec['uuid'] ?? ($rec['id'] ?? '');
        $client = array_merge($rec, ['id' => $uuid]);
        $inboundId = $inboundIds[0] ?? 1;
        return [
            'inbound' => ['id' => $inboundId],
            'client' => $client,
        ];
    }

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
            // Common AJAX header used by the panel UI
            'X-Requested-With' => 'XMLHttpRequest',
            // Provide a Referer similar to browser requests (helps some panel configs)
            'Referer' => $this->baseUrl($panel) . '/panel/inbounds',
            // Friendly UA
            'User-Agent' => '3x-ui-bot/1.0',
        ];
        $token = $this->authToken($panel);
        if ($token !== '') {
            $headers['Authorization'] = $token;
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

    private function httpWithAuth(Pannel $panel)
    {
        $req = Http::withoutVerifying()->withHeaders($this->headers($panel));
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

    public function login($panelOrId)
    {
        if ($panelOrId instanceof Pannel) {
            $panel = $panelOrId;
        } else {
            $panel = Pannel::find($panelOrId);
        }
        if (!$panel) {
            \Log::error("login: panel not found for ID " . (is_object($panelOrId) ? 'OBJECT' : $panelOrId));
            return false;
        }
        $pannelID = $panel->id;

        // If a token exists, validate it before assuming we're authenticated.
        $token = $this->authToken($panel);
        if ($token !== '') {
            try {
                $checkUrl = $this->baseUrl($panel) . $this->apiPrefix . '/server/status';
                $r = Http::withoutVerifying()->withHeaders(['Authorization' => $token, 'Accept' => 'application/json'])->get($checkUrl);
                $json = $r->json();
                if ($r->ok() && is_array($json) && ($json['success'] ?? false)) {
                    \Log::info("Token validated for panel $pannelID");
                    return true;
                }
                \Log::warning("Token present but validation failed for panel $pannelID. Status: " . $r->status());
            } catch (\Throwable $th) {
                \Log::warning("Token validation exception: " . $th->getMessage());
            }
        }

        $base = $this->baseUrl($panel);
        if ($base === '') {
            \Log::error("Panel $pannelID has no base URL");
            return false;
        }

        // Check if cookies are valid and not expired
        if (!empty($panel->cookie_session)) {
            try {
                // Try a known endpoint to validate session
                // We try both common prefixes
                $prefixes = ['/panel/api', '/xui/API'];
                foreach ($prefixes as $prefix) {
                    $r = $this->httpWithAuth($panel)->get($base . $prefix . '/inbounds/list');
                    $json = $r->json();
                    if ($r->ok() && is_array($json) && ($json['success'] ?? false)) {
                        $this->apiPrefix = $prefix;
                        return true;
                    }
                }
                \Log::info("Existing session invalid or expired for panel $pannelID. Proceeding to login.");
            } catch (\Throwable $th) {
                \Log::warning("Session check exception for panel $pannelID: " . $th->getMessage());
            }
        }

        // Diagnostic: Check what server we are talking to
        try {
            $rootRes = Http::withoutVerifying()->get($base);
            \Log::info("Server check for $base: Status=" . $rootRes->status() . ", ServerHeader=" . ($rootRes->header('Server') ?? 'Unknown'));
        } catch (\Throwable $e) {
            \Log::error("Server check failed for $base: " . $e->getMessage());
        }

        try {
            $loginUrl = $base . '/login';
            // include our headers on login (some panels expect AJAX headers)
            $res = Http::withoutVerifying()->asForm()->withHeaders($this->headers($panel))->post($loginUrl, [
                'username' => $panel->username ?? 'admin',
                'password' => $panel->password ?? 'admin',
                // Ask the panel to set the remember cookie like browsers do
                'remember' => 'on',
            ]);

            \Log::info("Login attempt to $loginUrl. Status: " . $res->status());
            \Log::debug("Login response body: " . substr($res->body(), 0, 2000));

            if ($res->status() === 200 || $res->status() === 302) {
                $cookies = $res->cookies()->toArray();
                \Log::info("Login cookies received: " . json_encode($cookies));

                if (!empty($cookies)) {
                    $panel->cookie_session = json_encode($cookies);
                    $panel->update();
                    return true;
                } else {
                    \Log::error("Login succeeded but no cookies returned. URL: $loginUrl");
                }
            } else {
                \Log::error("Login failed. URL: $loginUrl, Status: " . $res->status() . ", Body: " . $res->body());
            }
        } catch (\Throwable $th) {
            \Log::error('Login failed', ['panel_id' => $pannelID, 'error' => $th->getMessage()]);
        }
        return false;
    }

    public function addUserToSanaeiPanel(Request $request)
    {
        try {
            $pannelID = (int) $request->pannelID;
            $day = (int) $request->day;
            $volGb = (int) $request->vol;
            $accountId = (string) ($request->accountId ?? 'bot');

            $panel = Pannel::find($pannelID);
            if (!$panel) {
                \Log::error("Panel $pannelID not found");
                return false;
            }
            if (!$this->login($panel)) {
                \Log::error("Login failed for panel $pannelID");
                return false;
            }

            // Refresh panel to get new cookies from DB if login updated them
            // (Though passing by reference/object should already have them)
            // $panel->refresh(); 

            $inboundId = $request->inbound_id ?: ($panel->inbound_id ?: 1);
            $inbound = $this->getInboundFromPanel($panel, $inboundId);

            if (!$inbound) {
                \Log::error("Inbound $inboundId not found in panel $pannelID");
                return false;
            }

            // Update inboundId to the actual ID found (in case of fallback)
            $inboundId = $inbound['id'];

            $uuid = (new HiddifyPannelController())->generateUUID();
            $expireSec = now('UTC')->addDays($day)->timestamp;
            $totalBytes = $volGb * 1024 * 1024 * 1024;
            $expiryMs = $expireSec * 1000;

            $flow = '';
            $streamSettings = json_decode($inbound['streamSettings'], true);
            if (isset($streamSettings['security']) && $streamSettings['security'] === 'reality') {
                $flow = 'xtls-rprx-vision';
            }

            $client = [
                'id' => $uuid,
                'email' => "bot-" . $accountId . "-" . Random::generate(4),
                'limitIp' => (int) ($request->ip_limit ?? 0),
                'totalGB' => $totalBytes,
                'expiryTime' => $expiryMs,
                'enable' => true,
                'tgId' => '',
                'subId' => Random::generate(16),
            ];
            if ($flow) {
                $client['flow'] = $flow;
            }

            $base = $this->baseUrl($panel);

            // 3x-ui v3: POST /clients/add
            $v3Body = [
                'client' => $client,
                'inboundIds' => [(int) $inboundId],
            ];
            $v3Url = $base . $this->apiPrefix . '/clients/add';
            $r = $this->httpWithAuth($panel)->post($v3Url, $v3Body);
            $json = $r->json();
            if ($r->ok() && is_array($json) && ($json['success'] ?? false)) {
                $this->panelApiVersions[$panel->id] = 'v3';
                return ['uuid' => $uuid, 'subId' => $client['subId'], 'email' => $client['email']];
            }

            // 3x-ui v2.x: POST /inbounds/addClient
            $body = [
                'id' => $inboundId,
                'settings' => json_encode(['clients' => [$client]])
            ];

            $url = $base . $this->apiPrefix . '/inbounds/addClient';
            $r = $this->httpWithAuth($panel)->post($url, $body);

            // If addClient returned 404, try raw POST with Cookie header and different encodings
            if ($r->status() === 404) {
                try {
                    \Log::debug("addClient initial POST returned 404, trying raw JSON POST with Cookie header");
                    $raw = $this->rawPostWithCookie($panel, $url, $body, true);
                    \Log::debug("Raw JSON POST returned status " . $raw->status() . ", body=" . substr($raw->body(), 0, 2000));
                    if ($raw->ok()) {
                        $r = $raw;
                    } else {
                        \Log::debug("Raw JSON POST did not work, trying raw form POST");
                        $raw = $this->rawPostWithCookie($panel, $url, $body, false);
                        \Log::debug("Raw Form POST returned status " . $raw->status() . ", body=" . substr($raw->body(), 0, 2000));
                        if ($raw->ok()) {
                            $r = $raw;
                        }
                    }
                } catch (\Throwable $th) {
                    \Log::warning('rawPostWithCookie failed: ' . $th->getMessage());
                }
            }

            $json = $r->json();
            // Log the response for debugging
            \Log::debug("addClient response: " . json_encode($json));
            if ($r->ok() && is_array($json) && ($json['success'] ?? false)) {
                // Success
                $this->panelApiVersions[$panel->id] = 'v2';
                return ['uuid' => $uuid, 'subId' => $client['subId'], 'email' => $client['email']];
            }

            \Log::error("Failed to add client. URL: $url, Status: " . $r->status() . ", Body: " . $r->body());
            return false;

        } catch (\Throwable $th) {
            \Log::error('addUserToSanaeiPanel error: ' . $th->getMessage());
            return false;
        }
    }

    public function changeUserActivationOfSanaeiPanelApi(Request $request)
    {
        try {
            $found = $this->findClientByUUID($request->pannelID, $request->uuid);
            if (!$found) {
                return response()->json(['success' => false, 'msg' => 'Client not found'], 404);
            }

            $res = $this->updateClient($request->pannelID, $found['client']['id'], ['enable' => (bool) $request->enable]);
            if ($res) {
                return response()->json(['success' => true], 200);
            }
            return response()->json(['success' => false], 400);
        } catch (\Throwable $th) {
            \Log::error('changeUserActivationOfSanaeiPanelApi error: ' . $th->getMessage());
            return response()->json(['success' => false, 'msg' => $th->getMessage()], 500);
        }
    }

    public function getUserLinks($panelOrId, $uuid, $remark = '', $inboundId = null, $email = null)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return [];

            if (!$this->login($panel)) {
                return [];
            }

            $panel->refresh();

            // v3: fetch links directly from panel
            if ($email === null) {
                $found = $this->findClientByUUID($panel, $uuid);
                $email = $found['client']['email'] ?? null;
            }
            if ($email) {
                $linksRes = $this->performRequestWithFallback(
                    $panel,
                    'GET',
                    '/clients/links/' . rawurlencode($email),
                    '/inbounds/getClientTraffics/' . rawurlencode($email)
                );
                $obj = $linksRes['obj'] ?? null;
                if (is_array($obj)) {
                    if (isset($obj[0]) && is_string($obj[0])) {
                        return $obj;
                    }
                    if (isset($obj['links']) && is_array($obj['links'])) {
                        return $obj['links'];
                    }
                }
            }

            $inboundId = $inboundId ?: ($panel->inbound_id ?: 1);
            $inbound = $this->getInboundFromPanel($panel, $inboundId);
            if (!$inbound)
                return [];

            return $this->generateLinksFromInboundData($inbound, $uuid, $remark, $this->getServerHost($panel));

        } catch (\Throwable $th) {
            \Log::error('getUserLinks error: ' . $th->getMessage());
            return [];
        }
    }

    private function getInboundFromPanel($panel, $id)
    {
        $base = $this->baseUrl($panel);
        $prefixes = ['/panel/api', '/xui/API'];

        foreach ($prefixes as $prefix) {
            $url = $base . $prefix . "/inbounds/get/$id";

            // Try direct get
            // Debug: log headers and cookies we're sending
            \Log::debug('Request headers: ' . json_encode($this->headers($panel)));
            $cookieLog = json_encode(json_decode($panel->cookie_session ?? '[]', true));
            \Log::debug('Request cookies: ' . $cookieLog);

            $r = $this->httpWithAuth($panel)->get($url);
            // If not found, try a raw Cookie header (some panels require explicit cookie header)
            if ($r->status() === 404 && !empty($panel->cookie_session)) {
                try {
                    $raw = $this->rawGetWithCookie($panel, $url);
                    \Log::debug("Raw GET $url returned status " . $raw->status() . ", body=" . substr($raw->body(), 0, 2000));
                    if ($raw->ok()) {
                        $r = $raw;
                    }
                } catch (\Throwable $th) {
                    \Log::warning("Raw cookie GET failed: " . $th->getMessage());
                }
            }
            // Log body on failures to help debug why we get 404
            if (!$r->ok()) {
                \Log::debug("GET $url returned status " . $r->status() . ", body=" . substr($r->body(), 0, 2000));
            } else {
                \Log::debug("GET $url returned OK. Response preview: " . substr($r->body(), 0, 500));
            }
            $json = $r->json();

            if ($r->ok() && is_array($json) && ($json['success'] ?? false)) {
                $this->apiPrefix = $prefix;
                return $json['obj'];
            }

            \Log::warning("Direct get inbound $id failed with prefix $prefix. URL: $url, Status: " . $r->status());
        }

        // Fallback to list
        foreach ($prefixes as $prefix) {
            $listUrl = $base . $prefix . "/inbounds/list";
            $r = $this->httpWithAuth($panel)->get($listUrl);
            if ($r->status() === 404 && !empty($panel->cookie_session)) {
                try {
                    $raw = $this->rawGetWithCookie($panel, $listUrl);
                    \Log::debug("Raw GET $listUrl returned status " . $raw->status() . ", body=" . substr($raw->body(), 0, 2000));
                    if ($raw->ok()) {
                        $r = $raw;
                    }
                } catch (\Throwable $th) {
                    \Log::warning("Raw cookie GET failed for list: " . $th->getMessage());
                }
            }
            $json = $r->json();

            if ($r->ok() && is_array($json) && ($json['success'] ?? false)) {
                $this->apiPrefix = $prefix;
                $list = $json['obj'];

                // Log available IDs
                $ids = array_column($list, 'id');
                \Log::info("Available inbounds in panel: " . implode(', ', $ids));

                foreach ($list as $item) {
                    if ($item['id'] == $id) {
                        return $item;
                    }
                }

                // If specific ID not found, return the first one as fallback
                if (count($list) > 0) {
                    $first = $list[0];
                    \Log::warning("Inbound $id not found. Using first available inbound: " . $first['id']);
                    return $first;
                }
            } else {
                \Log::warning("List inbounds failed with prefix $prefix. URL: $listUrl, Status: " . $r->status());
            }
        }

        \Log::error("Inbound $id not found and no fallbacks available.");
        return null;
    }
    private function generateLinksFromInboundData($inbound, $uuid, $remark, $host)
    {
        $links = [];
        $protocol = $inbound['protocol'];
        $port = $inbound['port'];
        $stream = $inbound['streamSettings'];
        if (is_string($stream)) {
            $stream = json_decode($stream, true);
        }
        $network = $stream['network'] ?? 'tcp';
        $security = $stream['security'] ?? 'none';

        $sni = '';
        if (isset($stream['tlsSettings']['serverName']))
            $sni = $stream['tlsSettings']['serverName'];
        if (isset($stream['realitySettings']['serverNames'][0]))
            $sni = $stream['realitySettings']['serverNames'][0];

        if ($protocol === 'vless') {
            $query = ['type' => $network, 'security' => $security, 'encryption' => 'none'];
            if ($sni)
                $query['sni'] = $sni;
            if (isset($stream['realitySettings']['publicKey']))
                $query['pbk'] = $stream['realitySettings']['publicKey'];
            if (isset($stream['realitySettings']['fingerprint']))
                $query['fp'] = $stream['realitySettings']['fingerprint'];
            if (isset($stream['realitySettings']['shortId']))
                $query['sid'] = $stream['realitySettings']['shortId'];

            // Network-specific parameters
            if ($network === 'tcp') {
                if (isset($stream['tcpSettings']['header']['type']) && $stream['tcpSettings']['header']['type'] === 'http') {
                    $query['headerType'] = 'http';
                    if (isset($stream['tcpSettings']['header']['request']['headers']['Host'][0])) {
                        $query['host'] = $stream['tcpSettings']['header']['request']['headers']['Host'][0];
                    }
                    if (isset($stream['tcpSettings']['header']['request']['path'][0])) {
                        $query['path'] = $stream['tcpSettings']['header']['request']['path'][0];
                    }
                }
            } elseif ($network === 'ws') {
                if (isset($stream['wsSettings']['path'])) {
                    $query['path'] = $stream['wsSettings']['path'];
                }
                if (isset($stream['wsSettings']['headers']['Host'])) {
                    $query['host'] = $stream['wsSettings']['headers']['Host'];
                }
            } elseif ($network === 'grpc') {
                if (isset($stream['grpcSettings']['serviceName'])) {
                    $query['serviceName'] = $stream['grpcSettings']['serviceName'];
                }
            } elseif ($network === 'http') {
                if (isset($stream['httpSettings']['host'][0])) {
                    $query['host'] = $stream['httpSettings']['host'][0];
                }
                if (isset($stream['httpSettings']['path'])) {
                    $query['path'] = $stream['httpSettings']['path'];
                }
            }

            $q = http_build_query($query);
            $links[] = "vless://$uuid@$host:$port?$q#" . rawurlencode($remark);
        }

        return $links;
    }

    /**
     * Perform a GET request to $url using a raw Cookie header built from panel->cookie_session
     * Some panel setups require the Cookie header to be present exactly as a single header.
     */
    private function rawGetWithCookie(Pannel $panel, $url)
    {
        $cookies = json_decode($panel->cookie_session ?? '[]', true) ?? [];
        $parts = [];
        foreach ($cookies as $c) {
            $name = $c['Name'] ?? ($c['name'] ?? null);
            $value = $c['Value'] ?? ($c['value'] ?? null);
            if ($name !== null && $value !== null) {
                $parts[] = $name . '=' . $value;
            }
        }
        $cookieHeader = implode('; ', $parts);
        $headers = $this->headers($panel);
        $headers['Cookie'] = $cookieHeader;
        // Marker header to indicate this is the raw-cookie attempt
        $headers['X-Raw-Cookie-Retry'] = '1';
        return Http::withoutVerifying()->withHeaders($headers)->get($url);
    }

    private function rawPostWithCookie(Pannel $panel, $url, $body, $asJson = true)
    {
        $cookies = json_decode($panel->cookie_session ?? '[]', true) ?? [];
        $parts = [];
        foreach ($cookies as $c) {
            $name = $c['Name'] ?? ($c['name'] ?? null);
            $value = $c['Value'] ?? ($c['value'] ?? null);
            if ($name !== null && $value !== null) {
                $parts[] = $name . '=' . $value;
            }
        }
        $cookieHeader = implode('; ', $parts);
        $headers = $this->headers($panel);
        $headers['Cookie'] = $cookieHeader;
        // Marker header to indicate this is the raw-cookie attempt
        $headers['X-Raw-Cookie-Retry'] = '1';

        if ($asJson) {
            return Http::withoutVerifying()->withHeaders($headers)->asJson()->post($url, $body);
        }

        // form encoded
        return Http::withoutVerifying()->withHeaders($headers)->asForm()->post($url, $body);
    }

    /**
     * Generic request helper that tries known API prefixes and falls back to raw cookie requests when needed.
     * Returns decoded JSON array on success, or null on failure.
     */
    private function performRequest(Pannel $panel, string $method, string $path, $body = null, $asJson = true)
    {
        $base = $this->baseUrl($panel);
        $prefixes = ['/panel/api', '/xui/API'];

        foreach ($prefixes as $prefix) {
            $url = $base . $prefix . $path;
            try {
                if (strtoupper($method) === 'GET') {
                    $r = $this->httpWithAuth($panel)->get($url);
                    if ($r->status() === 404 && !empty($panel->cookie_session)) {
                        $raw = $this->rawGetWithCookie($panel, $url);
                        if ($raw->ok()) {
                            $r = $raw;
                        }
                    }
                } else { // POST
                    // Log what we are about to send for debugging (truncate large bodies)
                    try {
                        $preview = json_encode($body ?? []);
                    } catch (\Throwable $th) {
                        $preview = "[unserializable body]";
                    }
                    \Log::debug("POST $url asJson=" . ($asJson ? '1' : '0') . " body_preview=" . substr($preview, 0, 2000));

                    if ($asJson) {
                        $r = $this->httpWithAuth($panel)->asJson()->post($url, $body ?? []);
                    } else {
                        $r = $this->httpWithAuth($panel)->asForm()->post($url, $body ?? []);
                    }
                    if ($r->status() === 404 && !empty($panel->cookie_session)) {
                        $raw = $this->rawPostWithCookie($panel, $url, $body ?? [], $asJson);
                        if ($raw->ok()) {
                            $r = $raw;
                        }
                    }
                }

                $json = null;
                try {
                    $json = $r->json();
                } catch (\Throwable $th) {
                    // ignore json parse errors
                }

                // If the request returned OK but the JSON indicates failure (e.g., unexpected end of JSON on server side),
                // try a raw-cookie retry which some panel setups require.
                if ($r->ok() && is_array($json) && ($json['success'] ?? false)) {
                    // remember working prefix
                    $this->apiPrefix = $prefix;
                    return $json;
                } elseif ($r->ok() && !($json['success'] ?? false) && !empty($panel->cookie_session)) {
                    // Try raw fallback (single Cookie header) in case the server couldn't parse our body
                    try {
                        if (strtoupper($method) === 'GET') {
                            $raw = $this->rawGetWithCookie($panel, $url);
                        } else {
                            $raw = $this->rawPostWithCookie($panel, $url, $body ?? [], $asJson);
                        }
                        $rawJson = null;
                        try {
                            $rawJson = $raw->json();
                        } catch (\Throwable $th) {
                        }
                        if ($raw->ok() && is_array($rawJson) && ($rawJson['success'] ?? false)) {
                            $this->apiPrefix = $prefix;
                            return $rawJson;
                        }
                        \Log::warning("Raw cookie retry for $url returned Status: " . $raw->status() . ", Body: " . substr($raw->body(), 0, 2000));
                    } catch (\Throwable $th) {
                        \Log::warning("Raw cookie retry exception for $url: " . $th->getMessage());
                    }
                }

                \Log::warning("Request to $url failed. Status: " . $r->status() . ", Body: " . substr($r->body(), 0, 2000));
            } catch (\Throwable $th) {
                \Log::warning("Request exception for $url: " . $th->getMessage());
            }
        }

        return null;
    }

    // --- High-level API wrappers ---

    public function deleteUser($panelOrId, $uuid)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return false;

            $found = $this->findClientByUUID($panel, $uuid);
            if (!$found) {
                \Log::warning("deleteUser: client $uuid not found on panel {$panel->id}");
                return false;
            }
            $inboundId = $found['inbound']['id'] ?? 1;
            return $this->deleteClient($panel, $inboundId, $uuid);
        } catch (\Throwable $th) {
            \Log::error('deleteUser error: ' . $th->getMessage());
            return false;
        }
    }

    public function deleteClient($panelOrId, $inboundId, $clientId)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return false;

            if (!$this->login($panel)) {
                \Log::error("Login failed for deleteClient on panel {$panel->id}");
                return false;
            }

            $found = $this->findClientByUUID($panel, $clientId);
            $email = $found['client']['email'] ?? null;
            if ($email) {
                $res = $this->performRequestWithFallback(
                    $panel,
                    'POST',
                    '/clients/del/' . rawurlencode($email),
                    "/inbounds/$inboundId/delClient/$clientId"
                );
                if ($res !== null) {
                    return true;
                }
            }

            $path = "/inbounds/$inboundId/delClient/$clientId";
            $res = $this->performRequest($panel, 'POST', $path);
            return $res !== null;
        } catch (\Throwable $th) {
            \Log::error('deleteClient error: ' . $th->getMessage());
            return false;
        }
    }

    public function updateClient($panelOrId, $clientId, array $data)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return false;

            if (!$this->login($panel)) {
                \Log::error("Login failed for updateClient on panel {$panel->id}");
                return false;
            }

            $found = $this->findClientByUUID($panel, $clientId);
            if (!$found) {
                \Log::error("updateClient: client $clientId not found on panel {$panel->id}");
                return false;
            }

            $inbound = $found['inbound'];
            $inboundId = $inbound['id'] ?? ($inbound['listen'] ?? 0);
            $currentClient = $found['client'];
            $mergedClient = array_merge($currentClient, $data);
            $email = $mergedClient['email'] ?? '';

            // 3x-ui v3: POST /clients/update/{email}
            if ($email !== '') {
                $v3Path = '/clients/update/' . rawurlencode($email);
                $res = $this->performRequest($panel, 'POST', $v3Path, $mergedClient);
                if ($res !== null) {
                    $this->panelApiVersions[$panel->id] = 'v3';
                    return true;
                }
            }

            // 3x-ui v2.x
            $body = [
                'id' => $inboundId,
                'settings' => json_encode(['clients' => [$mergedClient]])
            ];

            $paths = [
                "/inbounds/updateClient/$clientId",
                "/inbounds/updateClient/$inboundId",
                "/inbounds/updateClient",
            ];

            foreach ($paths as $path) {
                \Log::debug("Trying updateClient at $path");
                $res = $this->performRequest($panel, 'POST', $path, $body, false);
                if ($res !== null) {
                    $this->panelApiVersions[$panel->id] = 'v2';
                    return true;
                }
            }

            try {
                $settings = $inbound['settings'] ?? null;
                if (is_string($settings)) {
                    $settings = json_decode($settings, true);
                }
                if (isset($settings['clients']) && is_array($settings['clients'])) {
                    foreach ($settings['clients'] as &$c) {
                        if (($c['id'] ?? '') === $clientId) {
                            $c = array_merge($c, $data);
                        }
                    }
                    $fullBody = [
                        'id' => $inboundId,
                        'settings' => json_encode($settings),
                    ];
                    \Log::info("updateClient final fallback: sending full settings payload for client $clientId");
                    $res2 = $this->performRequest($panel, 'POST', "/inbounds/updateClient/$inboundId", $fullBody, false);
                    if ($res2 !== null) {
                        return true;
                    }
                }
            } catch (\Throwable $th) {
                \Log::warning('updateClient final fallback failed: ' . $th->getMessage());
            }

            return false;
        } catch (\Throwable $th) {
            \Log::error('updateClient error: ' . $th->getMessage());
            return false;
        }
    }

    /**
     * Recharge a client by UUID: add days to expiry and add GB to total quota.
     * $addDays: integer days to add
    /**
     * $addDays: integer days to add
     * $addGb: integer GB to add
     */
    public function rechargeClient($panelOrId, $uuid, int $addDays = 0, int $addGb = 0)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel) {
                \Log::error("rechargeClient: panel not found");
                return false;
            }

            $found = $this->findClientByUUID($panel, $uuid);
            if (!$found) {
                \Log::error("rechargeClient: client $uuid not found in panel {$panel->id}");
                return false;
            }

            $client = $found['client'];
            $clientId = $client['id'];

            // compute new expiry
            $nowMs = now('UTC')->timestamp * 1000;
            $currentExpiry = (int) ($client['expiryTime'] ?? 0);
            if ($currentExpiry <= 0) {
                $currentExpiry = $nowMs;
            }
            $addMs = $addDays * 86400 * 1000;
            $newExpiry = $currentExpiry + $addMs;

            // compute new totalGB (stored as bytes in this project)
            $currentTotal = (int) ($client['totalGB'] ?? 0);
            $addBytes = $addGb * 1024 * 1024 * 1024;
            $newTotal = $currentTotal + $addBytes;

            $data = [
                // Sanaei API uses updateClient by clientId
                'expiryTime' => $newExpiry,
                'totalGB' => $newTotal,
            ];

            return $this->updateClient($panel, $clientId, $data);
        } catch (\Throwable $th) {
            \Log::error('rechargeClient error: ' . $th->getMessage());
            return false;
        }
    }

    /**
     * Update the client's email (used as the 'remark' / package name in products)
     */
    public function updateClientEmail($panelOrId, $uuid, string $newEmail)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel) {
                \Log::error("updateClientEmail: panel not found");
                return false;
            }

            $found = $this->findClientByUUID($panel, $uuid);
            if (!$found) {
                \Log::error("updateClientEmail: client $uuid not found in panel {$panel->id}");
                return false;
            }

            $client = $found['client'];
            $clientId = $client['id'] ?? null;
            if (!$clientId) {
                \Log::error("updateClientEmail: client id missing for uuid $uuid on panel {$panel->id}");
                return false;
            }

            $ok = $this->updateClient($panel, $clientId, ['email' => $newEmail]);
            if ($ok) {
                \Log::info("updateClientEmail: updated email for client $clientId on panel {$panel->id} to $newEmail");
                return true;
            }
            \Log::warning("updateClientEmail: updateClient returned false for client $clientId on panel {$panel->id}");
            return false;
        } catch (\Throwable $th) {
            \Log::error('updateClientEmail error: ' . $th->getMessage());
            return false;
        }
    }

    public function resetClientTraffic($panelOrId, $inboundId, $email)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return false;

            if (!$this->login($panel)) {
                \Log::error("Login failed for resetClientTraffic on panel {$panel->id}");
                return false;
            }
            $encoded = rawurlencode($email);
            $res = $this->performRequestWithFallback(
                $panel,
                'POST',
                "/clients/resetTraffic/$encoded",
                "/inbounds/$inboundId/resetClientTraffic/$encoded"
            );
            return $res !== null;
        } catch (\Throwable $th) {
            \Log::error('resetClientTraffic error: ' . $th->getMessage());
            return false;
        }
    }

    public function changeUserActivation($panelOrId, $uuid, bool $enable)
    {
        return $this->updateClient($panelOrId, $uuid, ['enable' => $enable]);
    }

    public function updateLimits($panelOrId, $uuid, int $days, int $gb)
    {
        $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
        if (!$panel) {
            return false;
        }

        $found = $this->findClientByUUID($panel, $uuid);
        if (!$found) {
            return false;
        }

        $client = $found['client'];
        $inboundId = $found['inbound']['id'] ?? 1;

        // 1. Reset traffic
        $this->resetClientTraffic($panel, $inboundId, $client['email']);

        // 2. Update expiry and totalGB
        $newExpiry = now('UTC')->addDays($days)->timestamp * 1000;
        $newTotal = $gb * 1024 * 1024 * 1024;

        return $this->updateClient($panel, $uuid, [
            'expiryTime' => $newExpiry,
            'totalGB' => $newTotal,
            'enable' => true
        ]);
    }

    public function resetAllTraffics($panelOrId)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return false;

            if (!$this->login($panel)) {
                \Log::error("Login failed for resetAllTraffics on panel {$panel->id}");
                return false;
            }
            $res = $this->performRequestWithFallback(
                $panel,
                'POST',
                '/clients/resetAllTraffics',
                '/inbounds/resetAllTraffics'
            );
            return $res !== null;
        } catch (\Throwable $th) {
            \Log::error('resetAllTraffics error: ' . $th->getMessage());
            return false;
        }
    }

    public function delDepletedClients($panelOrId, $inboundId)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return false;

            if (!$this->login($panel)) {
                \Log::error("Login failed for delDepletedClients on panel {$panel->id}");
                return false;
            }
            $res = $this->performRequestWithFallback(
                $panel,
                'POST',
                '/clients/delDepleted',
                "/inbounds/delDepletedClients/$inboundId"
            );
            return $res !== null;
        } catch (\Throwable $th) {
            \Log::error('delDepletedClients error: ' . $th->getMessage());
            return false;
        }
    }

    public function getClientTrafficsByEmail($panelOrId, $email)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return null;

            if (!$this->login($panel)) {
                return null;
            }
            $encoded = rawurlencode($email);
            $res = $this->performRequestWithFallback(
                $panel,
                'GET',
                "/clients/traffic/$encoded",
                "/inbounds/getClientTraffics/$encoded"
            );
            return $res['obj'] ?? null;
        } catch (\Throwable $th) {
            \Log::error('getClientTrafficsByEmail error: ' . $th->getMessage());
            return null;
        }
    }

    public function getClientTrafficsById($panelOrId, $id)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return null;

            if (!$this->login($panel)) {
                return null;
            }
            $path = "/inbounds/getClientTrafficsById/$id";
            $res = $this->performRequest($panel, 'GET', $path);
            return $res['obj'] ?? null;
        } catch (\Throwable $th) {
            \Log::error('getClientTrafficsById error: ' . $th->getMessage());
            return null;
        }
    }

    public function onlines($panelOrId)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return null;

            if (!$this->login($panel)) {
                return null;
            }
            $res = $this->performRequestWithFallback(
                $panel,
                'POST',
                '/clients/onlines',
                '/inbounds/onlines'
            );
            return $res['obj'] ?? null;
        } catch (\Throwable $th) {
            \Log::error('onlines error: ' . $th->getMessage());
            return null;
        }
    }

    public function lastOnline($panelOrId)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return null;

            if (!$this->login($panel)) {
                return null;
            }
            $res = $this->performRequestWithFallback(
                $panel,
                'POST',
                '/clients/lastOnline',
                '/inbounds/lastOnline'
            );
            return $res['obj'] ?? null;
        } catch (\Throwable $th) {
            \Log::error('lastOnline error: ' . $th->getMessage());
            return null;
        }
    }

    public function clientIps($panelOrId, $email)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return null;

            if (!$this->login($panel)) {
                return null;
            }
            $encoded = rawurlencode($email);
            $res = $this->performRequestWithFallback(
                $panel,
                'POST',
                "/clients/ips/$encoded",
                "/inbounds/clientIps/$encoded"
            );
            return $res['obj'] ?? null;
        } catch (\Throwable $th) {
            \Log::error('clientIps error: ' . $th->getMessage());
            return null;
        }
    }

    public function clearClientIps($panelOrId, $email)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return false;

            if (!$this->login($panel)) {
                return false;
            }
            $encoded = rawurlencode($email);
            $res = $this->performRequestWithFallback(
                $panel,
                'POST',
                "/clients/clearIps/$encoded",
                "/inbounds/clearClientIps/$encoded"
            );
            return $res !== null;
        } catch (\Throwable $th) {
            \Log::error('clearClientIps error: ' . $th->getMessage());
            return false;
        }
    }

    public function delClientByEmail($panelOrId, $inboundId, $email)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return false;

            if (!$this->login($panel)) {
                return false;
            }
            $encoded = rawurlencode($email);
            $res = $this->performRequestWithFallback(
                $panel,
                'POST',
                "/clients/del/$encoded",
                "/inbounds/$inboundId/delClientByEmail/$encoded"
            );
            return $res !== null;
        } catch (\Throwable $th) {
            \Log::error('delClientByEmail error: ' . $th->getMessage());
            return false;
        }
    }

    /**
     * Find a client in an inbound by email and return client data and inbound id
     * Returns array ['inbound' => <inbound>, 'client' => <client>] or null
     */
    public function findClientByEmail($panelOrId, $email)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return null;

            if (!$this->login($panel)) {
                return null;
            }

            // v3: GET /clients/get/{email}
            $detail = $this->performRequest($panel, 'GET', '/clients/get/' . rawurlencode($email));
            if ($detail !== null && isset($detail['obj']['client'])) {
                $inboundIds = $detail['obj']['inboundIds'] ?? [1];
                return $this->normalizeV3ClientRecord($detail['obj']['client'], $inboundIds);
            }

            $res = $this->performRequest($panel, 'GET', '/inbounds/list');
            $list = $res['obj'] ?? [];
            foreach ($list as $inbound) {
                $settings = $inbound['settings'] ?? null;
                if (is_string($settings)) {
                    $settings = json_decode($settings, true);
                }
                $clients = $settings['clients'] ?? [];
                foreach ($clients as $client) {
                    if (($client['email'] ?? '') === $email) {
                        return ['inbound' => $inbound, 'client' => $client];
                    }
                }
            }
            return null;
        } catch (\Throwable $th) {
            \Log::error('findClientByEmail error: ' . $th->getMessage());
            return null;
        }
    }

    /**
     * Find a client by UUID across inbounds. Returns ['inbound'=>..., 'client'=>...] or null
     */
    public function findClientByUUID($panelOrId, $uuid)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return null;

            if (!$this->login($panel)) {
                return null;
            }

            // v3: search clients list by uuid
            $clientsRes = $this->performRequest($panel, 'GET', '/clients/list');
            if ($clientsRes !== null) {
                foreach ($clientsRes['obj'] ?? [] as $rec) {
                    if (($rec['uuid'] ?? '') === $uuid) {
                        $email = $rec['email'] ?? '';
                        if ($email !== '') {
                            $detail = $this->performRequest($panel, 'GET', '/clients/get/' . rawurlencode($email));
                            if ($detail !== null && isset($detail['obj']['client'])) {
                                return $this->normalizeV3ClientRecord(
                                    $detail['obj']['client'],
                                    $detail['obj']['inboundIds'] ?? [1]
                                );
                            }
                        }
                        return $this->normalizeV3ClientRecord($rec);
                    }
                }
            }

            $res = $this->performRequest($panel, 'GET', '/inbounds/list');
            $list = $res['obj'] ?? [];
            foreach ($list as $inbound) {
                $settings = $inbound['settings'] ?? null;
                if (is_string($settings)) {
                    $settings = json_decode($settings, true);
                }
                $clients = $settings['clients'] ?? [];
                foreach ($clients as $client) {
                    if (($client['id'] ?? '') === $uuid) {
                        return ['inbound' => $inbound, 'client' => $client];
                    }
                }
            }
            return null;
        } catch (\Throwable $th) {
            \Log::error('findClientByUUID error: ' . $th->getMessage());
            return null;
        }
    }

    /**
     * Returns all clients from all inbounds in a format compatible with HiddifyConfig model
     */
    public function getAllClients($panelOrId)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return [];

            if (!$this->login($panel))
                return [];

            // v3: GET /clients/list
            $clientsRes = $this->performRequest($panel, 'GET', '/clients/list');
            if ($clientsRes !== null) {
                $allClients = [];
                foreach ($clientsRes['obj'] ?? [] as $rec) {
                    $uuid = $rec['uuid'] ?? ($rec['id'] ?? '');
                    $email = $rec['email'] ?? '';
                    $total = (int) ($rec['totalGB'] ?? 0);
                    $enable = $rec['enable'] ?? true;
                    $traffic = $this->getClientTrafficsByEmail($panel, $email);
                    $up = (int) ($traffic['up'] ?? 0);
                    $down = (int) ($traffic['down'] ?? 0);

                    $allClients[] = [
                        'uuid' => $uuid,
                        'name' => $email,
                        'current_usage_GB' => round(($up + $down) / 1024 / 1024 / 1024, 2),
                        'usage_limit_GB' => round($total / 1024 / 1024 / 1024, 2),
                        'package_days' => 0,
                        'is_active' => $enable,
                    ];
                }
                return $allClients;
            }

            $res = $this->performRequest($panel, 'GET', '/inbounds/list');
            $list = $res['obj'] ?? [];
            $allClients = [];

            foreach ($list as $inbound) {
                $settings = $inbound['settings'] ?? null;
                if (is_string($settings)) {
                    $settings = json_decode($settings, true);
                }
                $clients = $settings['clients'] ?? [];
                foreach ($clients as $client) {
                    $uuid = $client['id'] ?? ($client['uuid'] ?? '');
                    $email = $client['email'] ?? '';

                    // Basic info
                    $total = (int) ($client['totalGB'] ?? ($client['total'] ?? 0));
                    $expiry = $client['expiryTime'] ?? ($client['expiry_time'] ?? 0);
                    $enable = $client['enable'] ?? true;

                    // We might want to fetch traffic for each, but that's many requests.
                    // For the list view, maybe just basic info is enough or we use what's in the client object.
                    $up = (int) ($client['up'] ?? 0);
                    $down = (int) ($client['down'] ?? 0);

                    $allClients[] = [
                        'uuid' => $uuid,
                        'name' => $email,
                        'current_usage_GB' => round(($up + $down) / 1024 / 1024 / 1024, 2),
                        'usage_limit_GB' => round($total / 1024 / 1024 / 1024, 2),
                        'package_days' => 0, // Hard to calculate without start_date
                        'is_active' => $enable,
                    ];
                }
            }
            return $allClients;
        } catch (\Throwable $th) {
            \Log::error('getAllClients error: ' . $th->getMessage());
            return [];
        }
    }

    /**
     * Returns structured status for a client by uuid: enable, current_usage_GB, usage_limit_GB, start_date, package_days
     */
    public function getClientStatus($panelOrId, $uuid)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return null;

            $found = $this->findClientByUUID($panel, $uuid);
            if (!$found)
                return null;
            $inbound = $found['inbound'];
            $client = $found['client'];

            // usage from traffics endpoint
            $usageObj = $this->getClientTrafficsByEmail($panel, $client['email'] ?? '');

            $up = (int) ($client['up'] ?? 0);
            $down = (int) ($client['down'] ?? 0);
            $limitBytes = (int) ($client['totalGB'] ?? ($client['total'] ?? 0));
            $expiryMs = $client['expiryTime'] ?? ($client['expiry_time'] ?? 0);

            if (is_array($usageObj)) {
                if ($up <= 0)
                    $up = (int) ($usageObj['up'] ?? 0);
                if ($down <= 0)
                    $down = (int) ($usageObj['down'] ?? 0);
                if ($expiryMs <= 0) {
                    $expiryMs = (int) ($usageObj['expiryTime'] ?? 0);
                }
            }

            $usageBytes = $up + $down;
            $current_usage_GB = round($usageBytes / 1024 / 1024 / 1024, 2);
            $usage_limit_GB = round($limitBytes / 1024 / 1024 / 1024, 2);

            // dates
            $createdMs = $client['created_at'] ?? ($client['createdAt'] ?? null);
            $startDate = null;
            $package_days = 0;
            if ($createdMs) {
                $startSec = intval($createdMs / 1000);
                $startDate = Carbon::createFromTimestamp($startSec)->toIso8601String();
            }
            if ($createdMs && $expiryMs > 0) {
                $startSec = intval($createdMs / 1000);
                $expirySec = intval($expiryMs / 1000);
                $diffDays = max(0, ceil(($expirySec - $startSec) / 86400));
                $package_days = intval($diffDays);
            }

            $isEnabled = ($client['enable'] ?? ($usageObj['enable'] ?? true));

            return [
                'enable' => $isEnabled,
                'is_active' => $isEnabled, // for compatibility with Hiddify models
                'current_usage_GB' => $current_usage_GB,
                'usage_limit_GB' => $usage_limit_GB,
                'start_date' => $startDate,
                'package_days' => $package_days,
                'inbound' => $inbound,
                'client' => $client,
                'traffic' => $usageObj,
            ];
        } catch (\Throwable $th) {
            \Log::error('getClientStatus error: ' . $th->getMessage());
            return null;
        }
    }

    /**
     * Alias kept for backward compatibility with AgentProductController.
     */
    public function updateUser($panelOrId, $uuid, array $data)
    {
        if (isset($data['email'])) {
            return $this->updateClientEmail($panelOrId, $uuid, (string) $data['email']);
        }
        return $this->updateClient($panelOrId, $uuid, $data);
    }

    public function checkLoginStatus($pannelID)
    {
        $panel = Pannel::find($pannelID);
        if (!$panel) {
            return response()->json(['success' => false, 'msg' => 'Panel not found'], 404);
        }
        $ok = $this->login($panel);
        $version = $ok ? $this->detectApiVersion($panel) : null;
        return response()->json([
            'success' => $ok,
            'api_version' => $version,
            'has_token' => $this->authToken($panel) !== '',
        ], $ok ? 200 : 401);
    }

    public function refreshLogin($pannelID)
    {
        $panel = Pannel::find($pannelID);
        if (!$panel) {
            return response()->json(['success' => false, 'msg' => 'Panel not found'], 404);
        }
        unset($this->panelApiVersions[$panel->id]);
        $panel->cookie_session = null;
        $panel->save();
        $ok = $this->login($panel);
        return response()->json([
            'success' => $ok,
            'api_version' => $ok ? $this->detectApiVersion($panel) : null,
        ], $ok ? 200 : 401);
    }

    public function syncInbounds($pannelID)
    {
        $panel = Pannel::find($pannelID);
        if (!$panel) {
            return response()->json(['success' => false, 'msg' => 'Panel not found'], 404);
        }
        if (!$this->login($panel)) {
            return response()->json(['success' => false, 'msg' => 'Login failed'], 401);
        }
        $res = $this->performRequestWithFallback(
            $panel,
            'GET',
            '/inbounds/options',
            '/inbounds/list'
        );
        if ($res === null) {
            return response()->json(['success' => false, 'msg' => 'Could not fetch inbounds'], 500);
        }
        $inbounds = [];
        foreach ($res['obj'] ?? [] as $item) {
            $inbounds[] = [
                'id' => $item['id'] ?? null,
                'remark' => $item['remark'] ?? ($item['tag'] ?? ''),
                'protocol' => $item['protocol'] ?? '',
                'port' => $item['port'] ?? null,
            ];
        }
        return response()->json(['success' => true, 'inbounds' => $inbounds, 'api_version' => $this->detectApiVersion($panel)]);
    }

    public function checkInboundSources($pannelID)
    {
        $panel = Pannel::find($pannelID);
        if (!$panel) {
            return response()->json(['success' => false], 404);
        }
        if (!$this->login($panel)) {
            return response()->json(['success' => false, 'msg' => 'Login failed'], 401);
        }
        $version = $this->detectApiVersion($panel);
        $list = $this->performRequest($panel, 'GET', '/inbounds/list');
        $clients = $this->performRequest($panel, 'GET', '/clients/list');
        return response()->json([
            'success' => true,
            'api_version' => $version,
            'inbounds_count' => count($list['obj'] ?? []),
            'clients_count' => count($clients['obj'] ?? []),
        ]);
    }

    public function addUserWithTemplate(Request $request)
    {
        return $this->addUserToSanaeiPanel($request);
    }
}


