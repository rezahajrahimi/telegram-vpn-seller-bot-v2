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
        // $client = new Client(['cookies' => true]);
        // $pannel = Pannel::find($pannelID);
        // $secretValue = $pannel->secret_code;
        // $mainUrl = $pannel->admin_url;
        // // $secretValue = '2bc0c955-6c33-43cc-97e7-6ff6718d18ea';
        // // $mainUrl = 'https://irsub.powernad.ir/Br3ehFw87ZtoISMegDhwSN/';

        // $response = $client->post($mainUrl, [
        //     'form_params' => [
        //         'secret_textbox' => $secretValue,
        //     ],
        // ]);
        // $statusCode = $response->getStatusCode();

        // if ($statusCode === 200) {
        //     $cookieJar = new \GuzzleHttp\Cookie\CookieJar();
        //     $cookieJar = $response->getHeader('Set-Cookie');
        //     \Log::info('cookieJar=>', ['cookieJar' => $cookieJar]);
        //     $arr = explode(';', $cookieJar[0]);

        //     $cook = $arr[0];
        //     \Log::info('cookie1=>', [$cook]);
        //     $delimiterPos = strpos($cook, '=');
        //     $cook = substr($cook, $delimiterPos + 1);
        //     \Log::info('cookie=>', [$cook]);

        //     $headers = [
        //         'Content-Type' => 'application/json',
        //         'Accept' => 'application/json',
        //     ];

        //     $cookies = [
        //         'session' => $cook,
        //     ];
        //     $url = "{$mainUrl}api/v2/admin/user/";
        //     $subsequentResponse = Http::withCookies($cookies, $pannel->url_port)->get($url);

        //     \Log::info('aaaaaaaaaa=>', ['response' => $subsequentResponse->getBody()]);
        //      return $subsequentResponse->getBody();

        //     // Process the subsequent response
        // } else {
        //     return $statusCode;
        // }

        $pannel = Pannel::find($pannelID);
        $this->checkCookieSeason($pannel->id);
        $url = "{$pannel->admin_url}api/v2/admin/user/";
        \Log::info("url => $url");
        $cookies = [
            'session' => $pannel->cookie_session,
        ];

        $subsequentResponse = Http::withCookies($cookies, $pannel->url_port)->get($url);

        if ($subsequentResponse->getStatusCode() == 200) {
            $checkIsHtmlPage = strpos($subsequentResponse->getBody(), '<html>');
            if ($checkIsHtmlPage !== false) {
                // return response()->json(false, 401);
                return response()->json([$subsequentResponse->getBody(), $subsequentResponse->getStatusCode(), '111'], 401);
            }
            // dd($subsequentResponse);
            return json_decode($subsequentResponse->getBody(), true);
            // return $subsequentResponse->getBody();
        }
        // return response()->json(false, 401);
        return response()->json([$subsequentResponse->getBody(), $subsequentResponse->getStatusCode(), '222'], 401);
    }
}
