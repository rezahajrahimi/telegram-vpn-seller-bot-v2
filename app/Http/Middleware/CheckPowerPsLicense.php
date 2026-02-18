<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CheckPowerPsLicense
{
    public function handle(Request $request, Closure $next)
    {
        $appEnv = env('APP_ENV');
        if ($appEnv != 'development') {
            $host = env('FRONT_URL');
            $licenseType = 'gold';
            $adminId = env('TELEGRAM_ADMIN_ID');
            $cacheKey = "license_check:{$host}:{$licenseType}";
            if (Cache::has($cacheKey)) {
                $accountType = Cache::get($cacheKey);
            } else {
                $hasLicense = Http::post('https://license.powerps.ir/api/checkLicense', [
                    'name' => 'Reza',
                    'type' => $licenseType,
                    'host' => $host,
                    'admin_id' => $adminId,
                ]);
                $accountType = $hasLicense->json()['data']['account_type'] ?? null;
                if ($hasLicense->status() != 200) {
                    Cache::put($cacheKey, 'free', 780);
                    $accountType = 'free';
                } else {
                    Cache::put($cacheKey, $accountType, 780);
                }
            }
            if ($accountType === 'free' || $accountType === null) {
                return response()->json(['error' => 'License is not valid.'], 403);
            }
        }
        return $next($request);
    }
}
