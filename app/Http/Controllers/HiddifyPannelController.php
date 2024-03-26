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
            $str = rtrim($pannelUrl, '/');
        }

        $client = new Client(['cookies' => true]);

        $response = $client->post($pannelUrl, [
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

        $secretValue = $pannel->secrets_code;
        if (str_ends_with($pannelUrl, '/')) {
            $str = rtrim($pannelUrl, '/');
        }

        $client = new Client(['cookies' => true]);

        $response = $client->post($pannelUrl, [
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
                    return;
                }
                    // save new header cookie
                    $pannel->cookie_session = $cookies;
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
            $pannel->secrets_code = $request->secretValue;
            $pannel->url_port =  parse_url($request->admin_url, PHP_URL_HOST);
            // check cookie
            $pannel->save();
            $this->checkCookieSeason($pannel->id);

            return response()->json($pannel->id, 201);
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

        if ($pannel->secrets_code == null || ($pannel->secrets_code = '' || $checkLastTimeUpdated == false)) {
            \Log::info('need update');
            $this->getNewCookieToken($pannelID);
            return;
        }
        \Log::info('doestnt need update');

        return;
    }
}
