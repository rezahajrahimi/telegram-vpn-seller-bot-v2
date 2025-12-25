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
        $token = trim((string) ($panel->token ?? ''));
        // Avoid sending meaningless tokens like 'Bearer' or empty strings
        if ($token !== '' && $token !== 'Bearer') {
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

        // If a token exists, validate it before assuming we're authenticated.
        $token = trim((string) ($panel->token ?? ''));
        if ($token !== '' && $token !== 'Bearer') {
            try {
                $checkUrl = $this->baseUrl($panel) . $this->apiPrefix . '/server/status';
                $r = Http::withHeaders(['Authorization' => $token, 'Accept' => 'application/json'])->get($checkUrl);
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

        // Diagnostic: Check what server we are talking to
        try {
            $rootRes = Http::get($base);
            \Log::info("Server check for $base: Status=" . $rootRes->status() . ", ServerHeader=" . ($rootRes->header('Server') ?? 'Unknown'));
        } catch (\Throwable $e) {
            \Log::error("Server check failed for $base: " . $e->getMessage());
        }

        // Force login every time as requested, ignoring existing session check
        /*
        // Check if cookies are valid
        if (!empty($panel->cookie_session)) {
            try {
                // Try a known endpoint to validate session
                $r = $this->httpWithAuth($panel)->get($base . '/panel/api/inbounds/list');
                $json = $r->json();
                // Must check for success: true, because panel might return 200 OK with login page or error
                if ($r->ok() && ($json['success'] ?? false)) {
                    return true;
                }
                \Log::warning("Existing session invalid. Status: " . $r->status() . ", Success: " . ($json['success'] ?? 'false'));
            } catch (\Throwable $th) {
                \Log::warning("Session check exception: " . $th->getMessage());
            }
        }
        */

        try {
            $loginUrl = $base . '/login';
            // include our headers on login (some panels expect AJAX headers)
            $res = Http::asForm()->withHeaders($this->headers($panel))->post($loginUrl, [
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

            $panel = Pannel::findOrFail($pannelID);
            if (!$this->login($panel->id)) {
                \Log::error("Login failed for panel $pannelID");
                return false;
            }

            // Refresh panel to get new cookies from DB
            $panel->refresh();

            $inboundId = $panel->inbound_id ?: 1;
            $inbound = $this->getInboundFromPanel($panel, $inboundId);

            if (!$inbound) {
                \Log::error("Inbound $inboundId not found in panel $pannelID");
                return false;
            }

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
                'limitIp' => 0,
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
            if ($r->ok() && is_array($json) && ($json['success'] ?? false)) {
                \Log::info("User created: $uuid");
                return $uuid;
            }

            \Log::error("Failed to add client. URL: $url, Status: " . $r->status() . ", Body: " . $r->body());
            return false;

        } catch (\Throwable $th) {
            \Log::error('addUserToSanaeiPanel error: ' . $th->getMessage());
            return false;
        }
    }

    public function getUserLinks($pannelID, $uuid, $remark = '')
    {
        try {
            $panel = Pannel::findOrFail($pannelID);
            if (!$this->login($panel->id)) {
                return [];
            }

            // Refresh panel to get new cookies from DB
            $panel->refresh();

            $inboundId = $panel->inbound_id ?: 1;
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
            $query = ['type' => $network, 'security' => $security];
            if ($sni)
                $query['sni'] = $sni;
            if (isset($stream['realitySettings']['publicKey']))
                $query['pbk'] = $stream['realitySettings']['publicKey'];
            if (isset($stream['realitySettings']['fingerprint']))
                $query['fp'] = $stream['realitySettings']['fingerprint'];
            if (isset($stream['realitySettings']['shortId']))
                $query['sid'] = $stream['realitySettings']['shortId'];
            if ($network === 'ws' && isset($stream['wsSettings']['path']))
                $query['path'] = $stream['wsSettings']['path'];
            if ($network === 'grpc' && isset($stream['grpcSettings']['serviceName']))
                $query['serviceName'] = $stream['grpcSettings']['serviceName'];

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
        return Http::withHeaders($headers)->get($url);
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

        if ($asJson) {
            return Http::withHeaders($headers)->asJson()->post($url, $body);
        }

        // form encoded
        return Http::withHeaders($headers)->asForm()->post($url, $body);
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
                    $r = $this->httpWithAuth($panel)->post($url, $body ?? []);
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

                if ($r->ok() && is_array($json) && ($json['success'] ?? false)) {
                    // remember working prefix
                    $this->apiPrefix = $prefix;
                    return $json;
                }

                \Log::warning("Request to $url failed. Status: " . $r->status() . ", Body: " . substr($r->body(), 0, 2000));
            } catch (\Throwable $th) {
                \Log::warning("Request exception for $url: " . $th->getMessage());
            }
        }

        return null;
    }

    // --- High-level API wrappers ---

    public function deleteClient($panelId, $inboundId, $clientId)
    {
        try {
            $panel = Pannel::findOrFail($panelId);
            if (!$this->login($panel->id)) {
                \Log::error("Login failed for deleteClient on panel $panelId");
                return false;
            }
            $path = "/inbounds/$inboundId/delClient/$clientId";
            $res = $this->performRequest($panel, 'POST', $path);
            return $res !== null;
        } catch (\Throwable $th) {
            \Log::error('deleteClient error: ' . $th->getMessage());
            return false;
        }
    }

    public function updateClient($panelId, $clientId, array $data)
    {
        try {
            $panel = Pannel::findOrFail($panelId);
            if (!$this->login($panel->id)) {
                \Log::error("Login failed for updateClient on panel $panelId");
                return false;
            }
            $path = "/inbounds/updateClient/$clientId";
            $res = $this->performRequest($panel, 'POST', $path, $data);
            return $res !== null;
        } catch (\Throwable $th) {
            \Log::error('updateClient error: ' . $th->getMessage());
            return false;
        }
    }

    public function resetClientTraffic($panelId, $inboundId, $email)
    {
        try {
            $panel = Pannel::findOrFail($panelId);
            if (!$this->login($panel->id)) {
                \Log::error("Login failed for resetClientTraffic on panel $panelId");
                return false;
            }
            $path = "/inbounds/$inboundId/resetClientTraffic/$email";
            $res = $this->performRequest($panel, 'POST', $path);
            return $res !== null;
        } catch (\Throwable $th) {
            \Log::error('resetClientTraffic error: ' . $th->getMessage());
            return false;
        }
    }

    public function resetAllTraffics($panelId)
    {
        try {
            $panel = Pannel::findOrFail($panelId);
            if (!$this->login($panel->id)) {
                \Log::error("Login failed for resetAllTraffics on panel $panelId");
                return false;
            }
            $path = "/inbounds/resetAllTraffics";
            $res = $this->performRequest($panel, 'POST', $path);
            return $res !== null;
        } catch (\Throwable $th) {
            \Log::error('resetAllTraffics error: ' . $th->getMessage());
            return false;
        }
    }

    public function delDepletedClients($panelId, $inboundId)
    {
        try {
            $panel = Pannel::findOrFail($panelId);
            if (!$this->login($panel->id)) {
                \Log::error("Login failed for delDepletedClients on panel $panelId");
                return false;
            }
            $path = "/inbounds/delDepletedClients/$inboundId";
            $res = $this->performRequest($panel, 'POST', $path);
            return $res !== null;
        } catch (\Throwable $th) {
            \Log::error('delDepletedClients error: ' . $th->getMessage());
            return false;
        }
    }

    public function getClientTrafficsByEmail($panelId, $email)
    {
        try {
            $panel = Pannel::findOrFail($panelId);
            if (!$this->login($panel->id)) {
                return null;
            }
            $path = "/inbounds/getClientTraffics/$email";
            $res = $this->performRequest($panel, 'GET', $path);
            return $res['obj'] ?? null;
        } catch (\Throwable $th) {
            \Log::error('getClientTrafficsByEmail error: ' . $th->getMessage());
            return null;
        }
    }

    public function getClientTrafficsById($panelId, $id)
    {
        try {
            $panel = Pannel::findOrFail($panelId);
            if (!$this->login($panel->id)) {
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

    public function onlines($panelId)
    {
        try {
            $panel = Pannel::findOrFail($panelId);
            if (!$this->login($panel->id)) {
                return null;
            }
            $path = "/inbounds/onlines";
            $res = $this->performRequest($panel, 'POST', $path);
            return $res['obj'] ?? null;
        } catch (\Throwable $th) {
            \Log::error('onlines error: ' . $th->getMessage());
            return null;
        }
    }

    public function lastOnline($panelId)
    {
        try {
            $panel = Pannel::findOrFail($panelId);
            if (!$this->login($panel->id)) {
                return null;
            }
            $path = "/inbounds/lastOnline";
            $res = $this->performRequest($panel, 'POST', $path);
            return $res['obj'] ?? null;
        } catch (\Throwable $th) {
            \Log::error('lastOnline error: ' . $th->getMessage());
            return null;
        }
    }

    public function clientIps($panelId, $email)
    {
        try {
            $panel = Pannel::findOrFail($panelId);
            if (!$this->login($panel->id)) {
                return null;
            }
            $path = "/inbounds/clientIps/$email";
            $res = $this->performRequest($panel, 'POST', $path);
            return $res['obj'] ?? null;
        } catch (\Throwable $th) {
            \Log::error('clientIps error: ' . $th->getMessage());
            return null;
        }
    }

    public function clearClientIps($panelId, $email)
    {
        try {
            $panel = Pannel::findOrFail($panelId);
            if (!$this->login($panel->id)) {
                return false;
            }
            $path = "/inbounds/clearClientIps/$email";
            $res = $this->performRequest($panel, 'POST', $path);
            return $res !== null;
        } catch (\Throwable $th) {
            \Log::error('clearClientIps error: ' . $th->getMessage());
            return false;
        }
    }

    public function delClientByEmail($panelId, $inboundId, $email)
    {
        try {
            $panel = Pannel::findOrFail($panelId);
            if (!$this->login($panel->id)) {
                return false;
            }
            $path = "/inbounds/$inboundId/delClientByEmail/$email";
            $res = $this->performRequest($panel, 'POST', $path);
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
    public function findClientByEmail($panelId, $email)
    {
        try {
            $panel = Pannel::findOrFail($panelId);
            if (!$this->login($panel->id)) {
                return null;
            }
            // Get list of inbounds
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
    public function findClientByUUID($panelId, $uuid)
    {
        try {
            $panel = Pannel::findOrFail($panelId);
            if (!$this->login($panel->id)) {
                return null;
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
     * Returns structured status for a client by uuid: enable, current_usage_GB, usage_limit_GB, start_date, package_days
     */
    public function getClientStatus($panelId, $uuid)
    {
        try {
            $found = $this->findClientByUUID($panelId, $uuid);
            if (!$found)
                return null;
            $inbound = $found['inbound'];
            $client = $found['client'];

            // usage from traffics endpoint
            $usageObj = $this->getClientTrafficsByEmail($panelId, $client['email'] ?? '');
            $usageBytes = 0;
            if (is_array($usageObj) && isset($usageObj['traffic'])) {
                $usageBytes = (int) $usageObj['traffic'];
            }

            $limitBytes = (int) ($client['totalGB'] ?? 0);
            $current_usage_GB = round($usageBytes / 1024 / 1024 / 1024, 2);
            $usage_limit_GB = round($limitBytes / 1024 / 1024 / 1024, 2);

            // dates
            $createdMs = $client['created_at'] ?? null;
            $expiryMs = $client['expiryTime'] ?? ($client['expiry_time'] ?? null);
            $startDate = null;
            $package_days = 0;
            if ($createdMs) {
                $startSec = intval($createdMs / 1000);
                $startDate = Carbon::createFromTimestamp($startSec)->toIso8601String();
            }
            if ($createdMs && $expiryMs) {
                $startSec = intval($createdMs / 1000);
                $expirySec = intval($expiryMs / 1000);
                $diffDays = max(0, ceil(($expirySec - $startSec) / 86400));
                $package_days = intval($diffDays);
            }

            return [
                'enable' => ($client['enable'] ?? true),
                'current_usage_GB' => $current_usage_GB,
                'usage_limit_GB' => $usage_limit_GB,
                'start_date' => $startDate,
                'package_days' => $package_days,
                'inbound' => $inbound,
                'client' => $client,
            ];
        } catch (\Throwable $th) {
            \Log::error('getClientStatus error: ' . $th->getMessage());
            return null;
        }
    }
}


