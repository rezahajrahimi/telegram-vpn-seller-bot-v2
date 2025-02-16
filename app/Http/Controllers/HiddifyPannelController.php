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
    public function get_hiddify_subscription_link($url, $link): string
    {
        if (substr($url, -1) == '/') {
            $url = substr($url, 0, -1);
        }
        // check $link start with /
        if (str_starts_with($link, '/')) {
            $link = ltrim($link, '/');
        }
        return "{$url}/{$link}";
    }
    public function getClearHiddifyRequestUrl($mainUrl, $requestAPi)
    {
        // get substring from end of str until /
        $mainUrl = str_replace('/admin/', '', $mainUrl);
        $mainUrl = str_replace('/admin', '', $mainUrl);
        // if (str_starts_with($requestAPi, '/')) {
        //     $requestAPi = ltrim($requestAPi, '/');
        // }
        if (str_ends_with($mainUrl, '/')) {
            $mainUrl = rtrim($mainUrl, '/');
        }
        return "{$mainUrl}";
    }
    public function extractUUID($string)
    {
        // get substring between '/' and '/'
        $parts = explode('/', $string);

        return $parts[1];
    }
    public function checkHiddifyPanelUrl(Request $request)
    {
        $pannelUrl = $request->pannelUrl;
        // check is $pannelUrl ended with "/"

        $secretValue = $request->secretValue;
        if (str_ends_with($pannelUrl, '/')) {
            $pannelUrl = rtrim($pannelUrl, '/');
        }

        // $headers = [
        //     'Content-Type' => 'application/json',
        //     'Accept' => 'application/json',
        //     'Hiddify-API-Key' => $secretValue,
        // ];
        $url = "$pannelUrl/api/v2/admin/server_status/";

        $subsequentResponse = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Hiddify-API-Key' => $secretValue,
        ])->get($url);

        if ($subsequentResponse->getStatusCode() == 200) {

            return response()->json(true, 200);
        }
        return response()->json(false, 401);
    }

    public function addHiddifyPannel(Request $request)
    {
        try {
            $authCntrl = new AuthController();
            $getPowerPsLicenseType = $authCntrl->getPowerPsLicenseType();
            // check license
            $panelCount = pannel::where('type', 'hiddify')->count();
            $hasAccountLimitation = false;
            if ($panelCount >= 2 && $getPowerPsLicenseType == 'silver') {
                $hasAccountLimitation = true;
            }
            if ($panelCount >= 1 && $getPowerPsLicenseType == 'free') {
                $hasAccountLimitation = true;
            }
            if ($hasAccountLimitation == true) {
                return response()->json('به محدودیت افزودن پنل رسیده اید، برای افزودن پنل جدید با پشتیبانی تماس بگیرید و اکانت خود را ارتقا بدهید.', 201);
            }
            // add pannel
            $pannel = new Pannel();
            $pannel->type = 'hiddify';
            $pannel->location = $request->location ?? null;
            $pannel->admin_url = $request->admin_url;
            $pannel->user_link = $request->user_link ?? null;
            $pannel->capacity = $request->capacity ?? 1333333;
            $pannel->secret_code = $request->secretValue;
            $pannel->url_port = parse_url($request->admin_url, PHP_URL_HOST);
            // check cookie
            $pannel->save();
            return response()->json($pannel->id, 200);
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
            $pannel->user_link = $request->user_link ?? null;

            $pannel->url_port = parse_url($request->admin_url, PHP_URL_HOST);
            // check cookie
            if ($pannel->update()) {
                return response()->json(true, 201);
            }

            return response()->json(false, 500);
        } catch (\Throwable $th) {
            \Log::info("Throwable $th");

            return response()->json(false, 500);
        }
    }

    public function getHiddifyPanelUsersByPannelID($pannelID)
    {
        $pannel = Pannel::find($pannelID);

        $adminUUID = $pannel->admin_url;

        $data = $this->sendGetRequestToHiddifyPannel($pannelID, "/api/v2/admin/user/");
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
        $pannel = Pannel::find($pannelID);

        $adminUUID = $pannel->secret_code;
        $comment = $request->comment ?? '';

        $uuid = $this->generateUUID();
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
        $data = $this->sendPostRequestToHiddifyPannel($pannelID, '/api/v2/admin/user/', $params);
        // decode data
        // check data have not error and 401 response

        if (is_array($data) ) {
            if (isset($data['uuid'])) {
                return $uuid;
            }
        } else {
            return false;
        }
    }
    public function addUserToHiddifyPanelOldApi(Request $request)
    {
        $pannelID = $request->pannelID;
        $vol = $request->vol;
        $day = $request->day;
        $accountId = $request->accountId;
        $pannel = Pannel::find($pannelID);

        $adminUUID = $pannel->secret_code;
        $comment = $request->comment ?? '';

        $uuid = $this->generateUUID();
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
        $data = $this->sendPostRequestToHiddifyPannel($pannelID, "$adminUUID/api/v1/user/", $params);
        if ($data != false) {
            return $uuid;
        }
        return $data;
    }
    public function updateUserOfHiddifyPanel(Request $request)
    {
        $pannelID = $request->pannelID;
        $pannel = Pannel::find($pannelID);

        $vol = $request->vol;
        $day = $request->day;
        $accountId = $request->accountId;
        $adminUUID = $pannel->secret_code;
        $uuid = $request->uuid;
        $comment = $request->comment ?? '';
        $name = $request->name ?? '';

        $params = [
            'uuid' => "$uuid",
            'name' => $name,
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
    public function updateUserNameOfHiddifyPanelOldApi(Request $request)
    {
        $pannelID = $request->pannelID;
        $pannel = Pannel::find($pannelID);
        $adminUUID = $pannel->secret_code;

        $uuid = $request->uuid;
        $name = $request->name ?? '';
        $params = [
            'uuid' => "$uuid",
            'name' => "$name",
        ];
        $url = $this->getClearHiddifyRequestUrl($pannel->admin_url, $pannel->secret_code);
        $url = "$adminUUID/api/v1/user/?uuid={$uuid}";

        $data = $this->sendPostRequestToHiddifyPannel($pannelID, $url, $params);
        return $data;
    }
    public function updateUserNameOfHiddifyPanelApi(Request $request)
    {
        $pannelID = $request->pannelID;
        $pannel = Pannel::find($pannelID);
        $adminUUID = $pannel->secret_code;

        $uuid = $request->uuid;
        $name = $request->name ?? '';
        $params = [
            'uuid' => "$uuid",
            'name' => "$name",
        ];
        $data = $this->sendPatchRequestToHiddifyPannel($pannelID, "/api/v2/admin/user/$uuid/", $params);

        return $data;
    }
    public function rechargeUserOfHiddifyPanelOldApi(Request $request)
    {
        $pannelID = $request->pannelID;
        $pannel = Pannel::find($pannelID);
        $vol = $request->vol;
        $day = $request->day;

        $adminUUID = $pannel->secret_code;

        $uuid = $request->uuid;
        $name = $request->name ?? '';
        $comment = $request->comment ?? '';
        // get today date as format like 2024-01-01
        $today = date('Y-m-d');
        $params = [
            'uuid' => "$uuid",
            'name' => "$name",
            'current_usage_GB' => 0,
            'usage_limit_GB' => $vol,
            'package_days' => $day,
            'mode' => 'no_reset',
            'start_date' => "$today",
            'added_by_uuid' => "$adminUUID",
            'comment' => "$comment",
        ];
        $url = $this->getClearHiddifyRequestUrl($pannel->admin_url, $pannel->secret_code);
        $url = "$adminUUID/api/v1/user/?uuid={$uuid}";

        $data = $this->sendPostRequestToHiddifyPannel($pannelID, $url, $params);
        return $data;
    }
    public function rechargeUserOfHiddifyPanelApi(Request $request)
    {
        \Log::info("rechargeUserOfHiddifyPanelApi => 1111111");
        $pannelID = $request->pannelID;
        $pannel = Pannel::find($pannelID);
        $vol = $request->vol;
        $day = $request->day;

        $adminUUID = $pannel->secret_code;

        $uuid = $request->uuid;
        $name = $request->name ?? '';
        $comment = $request->comment ?? '';
        // get today date as format like 2024-01-01
        $today = date('Y-m-d');
        $params = [
            'uuid' => "$uuid",
            'name' => "$name",
            'current_usage_GB' => 0,
            'usage_limit_GB' => $vol,
            'package_days' => $day,
            'mode' => 'no_reset',
            'start_date' => "$today",
            'added_by_uuid' => "$adminUUID",
            'comment' => "$comment",
        ];

        return $this->sendPatchRequestToHiddifyPannel($pannelID, "/api/v2/admin/user/$uuid/", $params);
    }
    public function upgradeUserOfHiddifyPanelOldApi(Request $request)
    {
        $pannelID = $request->pannelID;
        $pannel = Pannel::find($pannelID);
        $vol = $request->vol;
        $day = $request->day;

        $adminUUID = $pannel->secret_code;

        $uuid = $request->uuid;
        $name = $request->name ?? '';
        $comment = $request->comment ?? '';
        // get today date as format like 2024-01-01
        $today = date('Y-m-d');
        $params = [
            'uuid' => "$uuid",
            'name' => "$name",
            'usage_limit_GB' => $vol,
            'package_days' => $day,
            'mode' => 'no_reset',
            'added_by_uuid' => "$adminUUID",
            'comment' => "$comment",
        ];
        $url = $this->getClearHiddifyRequestUrl($pannel->admin_url, $pannel->secret_code);
        $url = "$adminUUID/api/v1/user/?uuid={$uuid}";

        $data = $this->sendPostRequestToHiddifyPannel($pannelID, $url, $params);
        return $data;
    }
    public function upgradeUserOfHiddifyPanelApi(Request $request)
    {
        $pannelID = $request->pannelID;
        $pannel = Pannel::find($pannelID);
        $vol = $request->vol;
        $day = $request->day;

        $adminUUID = $pannel->secret_code;

        $uuid = $request->uuid;
        $name = $request->name ?? '';
        $comment = $request->comment ?? '';
        // get today date as format like 2024-01-01
        $today = date('Y-m-d');
        $params = [
            'uuid' => "$uuid",
            'name' => "$name",
            'usage_limit_GB' => $vol,
            'package_days' => $day,
            'mode' => 'no_reset',
            'added_by_uuid' => "$adminUUID",
            'comment' => "$comment",
        ];

        $data = $this->sendPatchRequestToHiddifyPannel($pannelID, "/api/v2/admin/user/$uuid/", $params);
        return $data;
    }
    public function changeUserActivationOfHiddifyPanelOldApi(Request $request)
    {
        $pannelID = $request->pannelID;
        $pannel = Pannel::find($pannelID);

        $adminUUID = $pannel->secret_code;

        $uuid = $request->uuid;
        $comment = $request->comment ?? '';

        $enable = $request->enable == true || $request->enable == 1 ? true : false;
        // get today date as format like 2024-01-01
        $today = date('Y-m-d');
        $params = [
            'uuid' => "$uuid",
            'comment' => "$comment",
            'enable' => $enable,
            'added_by_uuid' => "$adminUUID",
        ];
        $url = $this->getClearHiddifyRequestUrl($pannel->admin_url, $pannel->secret_code);
        $url = "$adminUUID/api/v1/user/?uuid={$uuid}";

        $data = $this->sendPostRequestToHiddifyPannel($pannelID, $url, $params);
        return $data;
    }
    public function changeUserActivationOfHiddifyPanelApi(Request $request)
    {
        $pannelID = $request->pannelID;
        $pannel = Pannel::find($pannelID);

        $adminUUID = $pannel->secret_code;

        $uuid = $request->uuid;
        $comment = $request->comment ?? '';

        $enable = $request->enable == true || $request->enable == 1 ? true : false;
        // get today date as format like 2024-01-01
        $today = date('Y-m-d');
        $params = [
            'uuid' => "$uuid",
            'comment' => "$comment",
            'enable' => $enable,
            'added_by_uuid' => "$adminUUID",
        ];
        $data = $this->sendPatchRequestToHiddifyPannel($pannelID, "/api/v2/admin/user/$uuid/", $params);
        return $data;
    }
    public function deleteUserOfHiddifyPanelOldApi(Request $request)
    {
        $pannelID = $request->pannelID;
        $pannel = Pannel::find($pannelID);
        $vol = $request->vol;
        $day = $request->day;

        $adminUUID = $pannel->secret_code;

        $uuid = $request->uuid;
        $name = $request->name ?? '';
        $comment = $request->comment ?? '';

        $params = [
            'uuid' => "$uuid",
            'name' => "$name",
            'usage_limit_GB' => $vol,
            'package_days' => $day,
            'mode' => 'no_reset',
            'added_by_uuid' => "$adminUUID",
            'comment' => "$comment",
            'enable' => false,
            'start_date' => '2024-01-01',
        ];
        $url = $this->getClearHiddifyRequestUrl($pannel->admin_url, $pannel->secret_code);
        $url = "$adminUUID/api/v1/user/?uuid={$uuid}";

        $data = $this->sendPostRequestToHiddifyPannel($pannelID, $url, $params);
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
        $url = $this->getClearHiddifyRequestUrl($pannel->admin_url, "");
        if (str_starts_with($requestAPi, '/')) {
            $requestAPi = ltrim($requestAPi, '/');
        }
        $url = $url . '/' . $requestAPi;
        $secretValue = $pannel->secret_code;
        $subsequentResponse = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Hiddify-API-Key' => $secretValue,
        ])->get($url);

        if ($subsequentResponse->getStatusCode() == 200) {
            $checkIsHtmlPage = strpos($subsequentResponse->getBody(), '<html>');
            if ($checkIsHtmlPage !== false) {
                return response()->json(false, 401);
            }
            // dd($subsequentResponse);
            return json_decode($subsequentResponse->getBody(), true);
        }
        \Log::info("sendGetRequestToHiddifyPannel => {$subsequentResponse->getBody()}");
        return response()->json(false, 401);
    }
    public function sendDeleteRequestToHiddifyPannel($pannelID, $requestAPi)
    {
        $pannel = Pannel::find($pannelID);
        $secretValue = $pannel->secret_code;

        $url = $pannel->admin_url;
        // checkj if url ended with "/" remove it
        if (substr($url, -1) == '/') {
            $url = substr($url, 0, -1);
        }
        $url = $url . $requestAPi;

        $subsequentResponse = Http::withHeaders(['Content-Type' => 'application/json', 'Accept' => 'application/json', 'Hiddify-API-Key' => $secretValue])->delete($url);

        if ($subsequentResponse->getStatusCode() == 200) {
            // dd($subsequentResponse);
            return response()->json(true, 200);
        }
        return response()->json(false, 401);
    }
    public function sendPutRequestToHiddifyPannel($pannelID, $requestAPi, $params = [])
    {
        $pannel = Pannel::find($pannelID);
        $secretValue = $pannel->secret_code;

        $url = $pannel->admin_url;
        // checkj if url ended with "/" remove it
        if (substr($url, -1) == '/') {
            $url = substr($url, 0, -1);
        }
        $url = $url . $requestAPi;

        $subsequentResponse = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Hiddify-API-Key' => $secretValue,
        ])->put($url, $params);

        if ($subsequentResponse->getStatusCode() == 200) {
            $checkIsHtmlPage = strpos($subsequentResponse->getBody(), '<html>');
            if ($checkIsHtmlPage !== false) {
                return response()->json(false, 401);
            }
            // dd($subsequentResponse);
            \Log::info("message => {$subsequentResponse->getBody()}");
            return json_decode($subsequentResponse->getBody(), true);
        }
        return response()->json(false, 401);
    }
    public function sendPostRequestToHiddifyPannel($pannelID, $requestAPi, $params = [])
    {
        $pannel = Pannel::find($pannelID);
        $secretValue = $pannel->secret_code;

        $url = $pannel->admin_url;
        // checkj if url ended with "/" remove it
        if (substr($url, -1) == '/') {
            $url = substr($url, 0, -1);
        }
        $url = $url . $requestAPi;

        $subsequentResponse = Http::withHeaders(['Content-Type' => 'application/json', 'Accept' => 'application/json', 'Hiddify-API-Key' => $secretValue])->post($url, $params);
        \Log::info(["subsequentResponse => {$subsequentResponse->getBody()}"]);
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
        $secretValue = $pannel->secret_code;

        $url = $pannel->admin_url;
        // checkj if url ended with "/" remove it
        if (substr($url, -1) == '/') {
            $url = substr($url, 0, -1);
        }
        $url = $url . $requestAPi;
        $subsequentResponse = Http::withHeaders(['Content-Type' => 'application/json', 'Accept' => 'application/json', 'Hiddify-API-Key' => $secretValue])->patch($url, $params);
        // check if status code is 200
        if ($subsequentResponse->getStatusCode() == 200) {
            return $subsequentResponse;
        }
        return false;
    }
}
