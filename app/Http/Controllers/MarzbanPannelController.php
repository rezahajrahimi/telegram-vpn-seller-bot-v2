<?php

namespace App\Http\Controllers;

use App\Models\Pannel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MarzbanPannelController extends Controller
{
    private function resolvePanel($panelOrId): ?Pannel
    {
        return $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
    }

    private function baseUrl(Pannel $panel): string
    {
        $url = trim((string) ($panel->url_port ?: $panel->admin_url));
        $url = str_replace('/dashboard/', '', $url);
        $url = str_replace('/dashboard', '', $url);

        return rtrim($url, '/');
    }

    private function authToken(Pannel $panel): string
    {
        $token = trim((string) ($panel->token ?? ''));
        if ($token === '' || $token === 'Bearer') {
            return '';
        }
        if (! str_starts_with(strtolower($token), 'bearer ')) {
            return 'Bearer ' . $token;
        }

        return $token;
    }

    private function headers(Pannel $panel): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'authorization' => $this->authToken($panel),
        ];
    }

    private function buildProxyPayload(int $pannelID): array
    {
        $proCntrl = new ProxyController();
        $proxies = $proCntrl->getActiveProxiesByPannelID($pannelID);

        $proxy = [];
        $inbounds = [];
        foreach ($proxies as $pr) {
            $proxy[$pr->type] = [];
            foreach ($pr->inbounds as $in) {
                $inbounds[$pr->type][] = $in->name;
            }
        }

        return [$proxy, $inbounds];
    }

    private function gbToBytes($gb): int
    {
        return (int) round((float) $gb * 1024 * 1024 * 1024);
    }

    private function expireTimestamp(int $days): int
    {
        $utc = Carbon::now('UTC')->addDays($days);

        return $utc->getTimestamp();
    }

    private function performRequest(Pannel $panel, string $method, string $path, array $body = null)
    {
        $url = $this->baseUrl($panel) . $path;
        $request = Http::withHeaders($this->headers($panel));
        $response = match (strtoupper($method)) {
            'GET' => $request->get($url),
            'POST' => $request->post($url, $body ?? []),
            'PUT' => $request->put($url, $body ?? []),
            'DELETE' => $request->delete($url),
            default => null,
        };

        if ($response === null || ! $response->successful()) {
            Log::info('Marzban API request failed', [
                'method' => $method,
                'path' => $path,
                'status' => $response?->status(),
                'body' => $response?->json(),
            ]);

            return null;
        }

        return $response->json();
    }

    public function createUser($panelOrId, string $username, int $day, $volGb): array|false
    {
        try {
            $panel = $this->resolvePanel($panelOrId);
            if (! $panel) {
                return false;
            }

            [$proxy, $inbounds] = $this->buildProxyPayload($panel->id);
            $params = [
                'username' => $username,
                'expire' => $this->expireTimestamp($day),
                'data_limit' => $this->gbToBytes($volGb),
                'proxies' => $proxy,
                'inbounds' => $inbounds,
                'status' => 'active',
            ];

            $body = $this->performRequest($panel, 'POST', '/api/user', $params);
            if (! is_array($body) || empty($body['subscription_url'])) {
                return false;
            }

            $mainUrl = $this->baseUrl($panel);
            $subPath = $body['subscription_url'];
            if (! str_starts_with($subPath, '/')) {
                $subPath = '/' . $subPath;
            }

            return [
                'username' => $username,
                'links' => $body['links'] ?? [],
                'subscription_link' => $mainUrl . $subPath,
                'subscription_url' => $subPath,
                'body' => $body,
            ];
        } catch (\Throwable $th) {
            Log::error('Marzban createUser failed: ' . $th->getMessage());

            return false;
        }
    }

    public function getUser($panelOrId, string $username): ?array
    {
        $panel = $this->resolvePanel($panelOrId);
        if (! $panel) {
            return null;
        }

        $body = $this->performRequest($panel, 'GET', '/api/user/' . rawurlencode($username));

        return is_array($body) ? $body : null;
    }

    public function modifyUser($panelOrId, string $username, int $day, $volGb, bool $resetTraffic = true): bool
    {
        $panel = $this->resolvePanel($panelOrId);
        if (! $panel) {
            return false;
        }

        [$proxy, $inbounds] = $this->buildProxyPayload($panel->id);
        $params = [
            'expire' => $this->expireTimestamp($day),
            'data_limit' => $this->gbToBytes($volGb),
            'proxies' => $proxy,
            'inbounds' => $inbounds,
            'status' => 'active',
        ];

        $body = $this->performRequest($panel, 'PUT', '/api/user/' . rawurlencode($username), $params);
        if (! is_array($body)) {
            return false;
        }

        if ($resetTraffic) {
            $this->resetTraffic($panel, $username);
        }

        return true;
    }

    public function updateLimits($panelOrId, string $username, int $day, $volGb): bool
    {
        return $this->modifyUser($panelOrId, $username, $day, $volGb, true);
    }

    public function rechargeUser($panelOrId, string $username, int $day, $volGb): bool
    {
        return $this->modifyUser($panelOrId, $username, $day, $volGb, true);
    }

    public function resetTraffic($panelOrId, string $username): bool
    {
        $panel = $this->resolvePanel($panelOrId);
        if (! $panel) {
            return false;
        }

        $body = $this->performRequest($panel, 'POST', '/api/user/' . rawurlencode($username) . '/reset');

        return is_array($body);
    }

    public function deleteUser($panelOrId, string $username): bool
    {
        $panel = $this->resolvePanel($panelOrId);
        if (! $panel) {
            return false;
        }

        $url = $this->baseUrl($panel) . '/api/user/' . rawurlencode($username);
        $response = Http::withHeaders($this->headers($panel))->delete($url);

        return $response->successful();
    }

    public function changeUserActivation($panelOrId, string $username, bool $enable): bool
    {
        $panel = $this->resolvePanel($panelOrId);
        if (! $panel) {
            return false;
        }

        $params = [
            'status' => $enable ? 'active' : 'disabled',
        ];

        $body = $this->performRequest($panel, 'PUT', '/api/user/' . rawurlencode($username), $params);

        return is_array($body);
    }

    public function renameUser($panelOrId, string $oldUsername, string $newUsername): bool
    {
        $panel = $this->resolvePanel($panelOrId);
        if (! $panel) {
            return false;
        }

        $body = $this->performRequest(
            $panel,
            'PUT',
            '/api/user/' . rawurlencode($oldUsername),
            ['username' => $newUsername]
        );

        return is_array($body);
    }

    public function getClientStatus($panelOrId, string $username): ?array
    {
        $user = $this->getUser($panelOrId, $username);
        if (! $user) {
            return null;
        }

        $usedBytes = (int) ($user['used_traffic'] ?? 0);
        $limitBytes = (int) ($user['data_limit'] ?? 0);
        $currentUsageGb = round($usedBytes / 1024 / 1024 / 1024, 2);
        $usageLimitGb = $limitBytes > 0 ? round($limitBytes / 1024 / 1024 / 1024, 2) : 0;

        $expireTs = (int) ($user['expire'] ?? 0);
        $startDate = null;
        $packageDays = 0;
        if ($expireTs > 0) {
            $expireDate = Carbon::createFromTimestamp($expireTs, 'UTC');
            $startDate = Carbon::now('UTC')->toDateString();
            $packageDays = max(0, Carbon::now('UTC')->diffInDays($expireDate, false));
        }

        $status = $user['status'] ?? 'unknown';
        $enable = $status === 'active';

        return array_merge($user, [
            'enable' => $enable,
            'is_active' => $enable,
            'current_usage_GB' => $currentUsageGb,
            'usage_limit_GB' => $usageLimitGb,
            'start_date' => $startDate,
            'package_days' => $packageDays,
            'marzban' => true,
        ]);
    }

    public function getSubscriptionLink($panelOrId, string $username): ?string
    {
        $user = $this->getUser($panelOrId, $username);
        if (! $user || empty($user['subscription_url'])) {
            return null;
        }

        $mainUrl = $this->baseUrl($this->resolvePanel($panelOrId));
        $subPath = $user['subscription_url'];
        if (! str_starts_with($subPath, '/')) {
            $subPath = '/' . $subPath;
        }

        return $mainUrl . $subPath;
    }

    public function getAllUsers($panelOrId): array
    {
        $panel = $this->resolvePanel($panelOrId);
        if (! $panel) {
            return [];
        }

        $allUsers = [];
        $offset = 0;
        $limit = 100;

        do {
            $body = $this->performRequest(
                $panel,
                'GET',
                '/api/users?offset=' . $offset . '&limit=' . $limit
            );
            if (! is_array($body)) {
                break;
            }

            $users = $body['users'] ?? [];
            foreach ($users as $user) {
                $username = $user['username'] ?? '';
                if ($username === '') {
                    continue;
                }

                $status = $this->formatUserForCron($user);
                $status['uuid'] = $username;
                $status['name'] = $username;
                $allUsers[] = $status;
            }

            $total = (int) ($body['total'] ?? count($users));
            $offset += $limit;
        } while ($offset < $total && count($users) === $limit);

        return $allUsers;
    }

    private function formatUserForCron(array $user): array
    {
        $usedBytes = (int) ($user['used_traffic'] ?? 0);
        $limitBytes = (int) ($user['data_limit'] ?? 0);
        $expireTs = (int) ($user['expire'] ?? 0);

        $packageDays = 0;
        $startDate = Carbon::now('UTC')->toDateString();
        if ($expireTs > 0) {
            $expireDate = Carbon::createFromTimestamp($expireTs, 'UTC');
            $packageDays = max(0, Carbon::now('UTC')->diffInDays($expireDate, false));
        }

        return [
            'current_usage_GB' => round($usedBytes / 1024 / 1024 / 1024, 2),
            'usage_limit_GB' => $limitBytes > 0
                ? round($limitBytes / 1024 / 1024 / 1024, 2)
                : 0,
            'start_date' => $startDate,
            'package_days' => $packageDays,
            'is_active' => ($user['status'] ?? '') === 'active',
        ];
    }
}
