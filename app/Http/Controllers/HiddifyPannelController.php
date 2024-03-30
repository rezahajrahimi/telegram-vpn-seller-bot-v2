<?php

namespace App\Http\Controllers;

use App\Models\Pannel;
use App\Models\Proxy;
use App\Models\Inbound;

use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class HiddifyPannelController extends Controller
{
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
    public function checkHiddifyPanelUrl(Request $request)
    {
        $pannelUrl = $request->pannelUrl;
        // check is $pannelUrl ended with "/"

        $secretValue = $request->secretValue;
        if (str_ends_with($pannelUrl, '/')) {
            $pannelUrl = rtrim($pannelUrl, '/');
        }

        $client = new Client(['cookies' => true]);

        $response = $client->post("{$pannelUrl}/", [
            'form_params' => [
                'secret_textbox' => $secretValue,
            ],
        ]);
        $statusCode = $response->getStatusCode();

        if ($statusCode === 200) {
            $cookieJar = new \GuzzleHttp\Cookie\CookieJar();
            $cookieJar = $response->getHeader('Set-Cookie');
            $arr = explode(';', $cookieJar[0]);

            $cook = $arr[0];
            $delimiterPos = strpos($cook, '=');
            $cook = substr($cook, $delimiterPos + 1);

            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];

            $cookies = [
                'session' => $cook,
            ];
            $url = "$pannelUrl/api/v2/admin/server_status/";

            $subsequentResponse = Http::withCookies($cookies, parse_url($url, PHP_URL_HOST))->get($url);

            if ($subsequentResponse->getStatusCode() == 200) {
                $checkIsHtmlPage = strpos($subsequentResponse->getBody(), '<html>');
                if ($checkIsHtmlPage !== false) {
                    return response()->json(false, 401);
                }
                return response()->json(true, 200);
            }
            return response()->json(false, 401);
        } else {
            return response()->json(false, 401);
        }
    }
    public function getNewCookieToken($pannelID)
    {
        $pannel = Pannel::find($pannelID);

        $pannelUrl = $pannel->admin_url;
        // check is $pannelUrl ended with "/"

        $secretValue = $pannel->secret_code;
        if (str_ends_with($pannelUrl, '/')) {
            $pannelUrl = rtrim($pannelUrl, '/');
        }

        $client = new Client(['cookies' => true]);

        $response = $client->post("{$pannelUrl}/", [
            'form_params' => [
                'secret_textbox' => $secretValue,
            ],
        ]);
        $statusCode = $response->getStatusCode();

        if ($statusCode === 200) {
            $cookieJar = new \GuzzleHttp\Cookie\CookieJar();
            $cookieJar = $response->getHeader('Set-Cookie');
            $arr = explode(';', $cookieJar[0]);

            $cook = $arr[0];
            $delimiterPos = strpos($cook, '=');
            $cook = substr($cook, $delimiterPos + 1);

            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];

            $cookies = [
                'session' => $cook,
            ];
            $url = "{$pannelUrl}/api/v2/admin/server_status/";

            $subsequentResponse = Http::withCookies($cookies, parse_url($url, PHP_URL_HOST))->get($url);

            if ($subsequentResponse->getStatusCode() == 200) {
                $checkIsHtmlPage = strpos($subsequentResponse->getBody(), '<html>');
                if ($checkIsHtmlPage !== false) {
                    return;
                }
                // save new header cookie
                $pannel->cookie_session = $cook;
                $pannel->update();
                \Log::info('pannel cookie updated');
            }
            return;
        } else {
            return;
        }
    }
    public function addHiddifyPannel(Request $request)
    {
        try {
            $pannel = new Pannel();
            $pannel->type = 'hiddify';
            $pannel->location = $request->location ?? null;
            $pannel->admin_url = $request->admin_url;
            $pannel->capacity = $request->capacity ?? 1333333;
            $pannel->secret_code = $request->secretValue;
            $pannel->url_port = parse_url($request->admin_url, PHP_URL_HOST);
            // check cookie
            $pannel->save();
            $this->checkCookieSeason($pannel->id);

            return response()->json($pannel->id, 201);
        } catch (\Throwable $th) {
            \Log::info("Throwable $th");

            return response()->json(false, 500);
        }
    }
    public function updateHiddifyPannel(Request $request)
    {
        try {
            $pannel = Pannel::find($request->id);
            $pannel->location = $request->location ?? null;
            $pannel->admin_url = $request->admin_url;
            $pannel->capacity = $request->capacity ?? 1333333;
            $pannel->secret_code = $request->secretValue;
            $pannel->url_port = parse_url($request->admin_url, PHP_URL_HOST);
            // check cookie
            if ($pannel->update()) {
                $this->getNewCookieToken($request->id);
                return response()->json(true, 201);
            }

            return response()->json(false, 500);
        } catch (\Throwable $th) {
            \Log::info("Throwable $th");

            return response()->json(false, 500);
        }
    }
    public function checkCookieSeason($pannelID)
    {
        $pannel = Pannel::find($pannelID);
        $checkLastTimeUpdated = $pannel->updated_at->diffInDays(now()) > 48;
        \Log::info("checkLastTimeUpdated:  $checkLastTimeUpdated");

        if ($pannel->secret_code == null || ($pannel->secret_code = '' || $checkLastTimeUpdated == true)) {
            \Log::info('need update');
            $this->getNewCookieToken($pannelID);
            return;
        }
        \Log::info('doestnt need update');

        return;
    }
    public function getHiddifyPanelUsersByPannelID($pannelID)
    {
        $data = $this->sendGetRequestToHiddifyPannel($pannelID, 'api/v2/admin/user/');
        return $data;
    }
    public function getHiddifyPanelUserByPannelID($pannelID, $userUUID)
    {
        $data = $this->sendGetRequestToHiddifyPannel($pannelID, "api/v2/admin/user/$userUUID/");
        return $data;
    }
    public function getHiddifyPanelAllConfigsUserByPannelID($pannelID, $userUUID)
    {
        $data = $this->sendGetRequestToHiddifyPannel($pannelID, '/api/v2/user/all-configs/');
        return $data;
    }
    public function addUserToHiddifyPanel(Request $request)
    {
        $pannelID = $request->pannelID;
        $vol = $request->vol;
        $day = $request->day;
        $accountId = $request->accountId;
        $adminUUID = $request->adminUUID;
        $uuid = $this->generateUUID();
        $params = [
            'uuid' => "$uuid",
            'name' => "bot$accountId",
            'current_usage_GB' => 0,
            'usage_limit_GB' => $vol,
            'package_days' => $day,
            'mode' => 'no_reset',
            'added_by_uuid' => "$adminUUID",
        ];
        $data = $this->sendPutRequestToHiddifyPannel($pannelID, '/api/v2/admin/user/', $params);
        return $data;
    }
    public function updateUserOfHiddifyPanel(Request $request)
    {
        $pannelID = $request->pannelID;
        $vol = $request->vol;
        $day = $request->day;
        $accountId = $request->accountId;
        $adminUUID = $request->adminUUID;
        $uuid = $request->uuid;
        $comment = $request->comment ?? '';
        $params = [
            'uuid' => "$uuid",
            'name' => "bot$accountId",
            'current_usage_GB' => 0,
            'usage_limit_GB' => $vol,
            'package_days' => $day,
            'mode' => 'no_reset',
            'added_by_uuid' => "$adminUUID",
            'comment' => "$comment",
        ];
        $data = $this->sendPatchRequestToHiddifyPannel($pannelID, "/api/v2/admin/user/$uuid/", $params);
        return $data;
    }
    public function deleteUserOfHiddifyPanel($pannelID, $userUUID)
    {

        $data = $this->sendDeleteRequestToHiddifyPannel($pannelID, "/api/v2/admin/user/$userUUID/");
        return $data;
    }
    public function sendGetRequestToHiddifyPannel($pannelID, $requestAPi)
    {
        $pannel = Pannel::find($pannelID);
        $this->checkCookieSeason($pannel->id);
        $url = '';
        if (str_ends_with($pannel->admin_url, '/')) {
            $url = "{$pannel->admin_url}{$requestAPi}";
        } else {
            $url = "{$pannel->admin_url}/{$requestAPi}";
        }
        \Log::info("url => $url");
        $cookies = [
            'session' => $pannel->cookie_session,
        ];

        $subsequentResponse = Http::withCookies($cookies, $pannel->url_port)->get($url);

        if ($subsequentResponse->getStatusCode() == 200) {
            $checkIsHtmlPage = strpos($subsequentResponse->getBody(), '<html>');
            if ($checkIsHtmlPage !== false) {
                return response()->json(false, 401);
            }
            // dd($subsequentResponse);
            return json_decode($subsequentResponse->getBody(), true);
        }
        return response()->json(false, 401);
    }
    public function sendDeleteRequestToHiddifyPannel($pannelID, $requestAPi)
    {
        $pannel = Pannel::find($pannelID);
        $this->checkCookieSeason($pannel->id);
        $url = '';
        if (str_ends_with($pannel->admin_url, '/')) {
            $url = "{$pannel->admin_url}{$requestAPi}";
        } else {
            $url = "{$pannel->admin_url}/{$requestAPi}";
        }
        \Log::info("url => $url");
        $cookies = [
            'session' => $pannel->cookie_session,
        ];

        $subsequentResponse = Http::withCookies($cookies, $pannel->url_port)->delete($url);

        if ($subsequentResponse->getStatusCode() == 200) {
            $checkIsHtmlPage = strpos($subsequentResponse->getBody(), '<html>');
            if ($checkIsHtmlPage !== false) {
                return response()->json(false, 401);
            }
            // dd($subsequentResponse);
            return json_decode($subsequentResponse->getBody(), true);
        }
        return response()->json(false, 401);
    }
    public function sendPutRequestToHiddifyPannel($pannelID, $requestAPi, $params = [])
    {
        $pannel = Pannel::find($pannelID);
        $this->checkCookieSeason($pannel->id);
        $url = '';
        if (str_ends_with($pannel->admin_url, '/')) {
            $url = "{$pannel->admin_url}{$requestAPi}";
        } else {
            $url = "{$pannel->admin_url}/{$requestAPi}";
        }
        \Log::info("url => $url");
        $cookies = [
            'session' => $pannel->cookie_session,
        ];

        $subsequentResponse = Http::withCookies($cookies, $pannel->url_port)->put($url, $params);
        if ($subsequentResponse->getStatusCode() == 200) {
            $checkIsHtmlPage = strpos($subsequentResponse->getBody(), '<html>');
            if ($checkIsHtmlPage !== false) {
                return response()->json(false, 401);
            }
            // dd($subsequentResponse);
            return json_decode($subsequentResponse->getBody(), true);
        }
        return response()->json(false, 401);
    }
    public function sendPatchRequestToHiddifyPannel($pannelID, $requestAPi, $params = [])
    {
        $pannel = Pannel::find($pannelID);
        $this->checkCookieSeason($pannel->id);
        $url = '';
        if (str_ends_with($pannel->admin_url, '/')) {
            $url = "{$pannel->admin_url}{$requestAPi}";
        } else {
            $url = "{$pannel->admin_url}/{$requestAPi}";
        }
        \Log::info("url => $url");
        $cookies = [
            'session' => $pannel->cookie_session,
        ];

        $subsequentResponse = Http::withCookies($cookies, $pannel->url_port)->patch($url, $params);
        \Log::info("getStatusCode => {$subsequentResponse->getStatusCode()}");
        \Log::info("getBody => {$subsequentResponse->getBody()}");
        if ($subsequentResponse->getStatusCode() == 200) {
            $checkIsHtmlPage = strpos($subsequentResponse->getBody(), '<html>');
            if ($checkIsHtmlPage !== false) {
                return response()->json(false, 401);
            }
            // dd($subsequentResponse);
            return json_decode($subsequentResponse->getBody(), true);
        }
        return response()->json(false, 401);
    }
}
