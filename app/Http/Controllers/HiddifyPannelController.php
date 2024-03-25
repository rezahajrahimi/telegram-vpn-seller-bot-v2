<?php

namespace App\Http\Controllers;

use App\Models\Pannel;
use App\Models\Proxy;
use App\Models\Inbound;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class HiddifyPannelController extends Controller
{
    public function checkHiddifyPanelUrl(Request $request)
    {
        $pannelUrl = $request->pannelUrl;
        // check is $pannelUrl ended with ""

        $secretValue = $request->secretValue;
        if (str_ends_with($pannelUrl, '/')) {
            // $pannelUrl = "$pannelUrl/";
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
            \Log::info('cookieJar=>', ['cookieJar' => $cookieJar]);
            $arr = explode(';', $cookieJar[0]);

            $cook = $arr[0];
            \Log::info('cookie1=>', [$cook]);
            $delimiterPos = strpos($cook, '=');
            $cook = substr($cook, $delimiterPos + 1);
            \Log::info('cookie=>', [$cook]);

            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];

            $cookies = [
                'session' => $cook,
            ];
            $url = "$pannelUrl/api/v2/admin/server_status/";

            $subsequentResponse = Http::withCookies($cookies, parse_url($url, PHP_URL_HOST))->get($url);

            \Log::info('aaaaaaaaaa=>', ['response' => $subsequentResponse->getBody()]);
            if ($subsequentResponse->getStatusCode() == 200) {
                $pos = strpos($subsequentResponse->getBody(), '<html>');
                if ($pos !== false) {
                    return response()->json(false, 401);
                }
                return response()->json(true, 200);
            }
            return response()->json(false, 402);

            // return $subsequentResponse->getBody();

            // Process the subsequent response
        } else {
            return $statusCode;
        }
    }
}
