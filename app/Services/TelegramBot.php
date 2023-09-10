<?php

namespace App\Services;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;

/**
 * TelegramBot
 */
class TelegramBot
{
    protected $token;
    protected $api_endpoint;
    protected $headers;

    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        $this->token = env('TELEGRAM_BOT_TOKEN');
        $this->api_endpoint = env('TELEGRAM_API_ENDPOINT');
        $this->setHeaders();
    }

    /**
     * setHeaders
     *
     * @return void
     */
    protected function setHeaders()
    {
        $this->headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * sendMessage
     *
     * @param  mixed $text
     * @param  mixed $chat_id
     * @param  mixed $reply_to_message_id
     * @return void
     */
    public function sendMessage($text = '', $chat_id, $reply_to_message_id)
    {
        // Default result array
        $result = ['success' => false, 'body' => []];

        // Create params array
        $params = [
            'chat_id' => $chat_id,
            'reply_to_message_id' => $reply_to_message_id,
            'text' => $text,
            'allow_sending_without_reply'=>true,
        ];

        // Create url -> https://api.telegram.org/bot{token}/sendMessage
        $url = "{$this->api_endpoint}/{$this->token}/sendMessage";

        // Send the request
        try {
            $response = Http::withHeaders($this->headers)->post($url, $params);
            $result = ['success' => $response->ok(), 'body' => $response->json()];
        } catch (\Throwable $th) {
            $result['error'] = $th->getMessage();
        }

        \Log::info('TelegramBot->sendMessage->result', ['result' => $result]);

        return $result;
    }
    public function buttonMessage($text = '', $opr,$chat_id, $reply_to_message_id)
    {
        // Default result array
        $result = ['success' => false, 'body' => []];

        // Create params array

        $params = [
            'chat_id' => $chat_id,
            'reply_to_message_id' => $reply_to_message_id,
            'allow_sending_without_reply'=>true,
            'text' => $text,
            'reply_markup' => ['keyboard' => $opr,  'resize_keyboard' => true],
        ];

        // Create url -> https://api.telegram.org/bot{token}/sendMessage
        $url = "{$this->api_endpoint}/{$this->token}/sendMessage";

        // Send the request
        try {
            $response = Http::withHeaders($this->headers)->post($url, $params);
            $result = ['success' => $response->ok(), 'body' => $response->json()];
        } catch (\Throwable $th) {
            $result['error'] = $th->getMessage();
        }

        \Log::info('TelegramBot->sendMessage->result', ['result' => $result]);

        return $result;
    }

    public function imageMessage($image,$chat_id,$caption)
    {
        // Default result array
        $result = ['success' => false, 'body' => []];

        // Create params array

        $params = [
            'chat_id' => $chat_id,
            'photo' => $image,
            'caption' => $caption,

        ];

        // Create url -> https://api.telegram.org/bot{token}/sendMessage
        $url = "{$this->api_endpoint}/{$this->token}/sendPhoto";

        // Send the request
        try {
            $response = Http::withHeaders($this->headers)->post($url, $params);
            $result = ['success' => $response->ok(), 'body' => $response->json()];
        } catch (\Throwable $th) {
            $result['error'] = $th->getMessage();
        }

        \Log::info('TelegramBot->sendMessage->result', ['result' => $result]);

        return $result;
    }
    public function commandMessage($command,$chat_id,$text)
    {
        // Default result array
        $result = ['success' => false, 'body' => []];

        // Create params array

        $params = [
            'chat_id' => $chat_id,
            'text' => $text,
            'reply_markup' => ['inline_keyboard' => $command,  'resize_keyboard' => true],


        ];

        // Create url -> https://api.telegram.org/bot{token}/sendMessage
        $url = "{$this->api_endpoint}/{$this->token}/sendMessage";

        // Send the request
        try {
            $response = Http::withHeaders($this->headers)->post($url, $params);
            $result = ['success' => $response->ok(), 'body' => $response->json()];
        } catch (\Throwable $th) {
            $result['error'] = $th->getMessage();
        }

        \Log::info('TelegramBot->sendMessage->result', ['result' => $result]);

        return $result;
    }

    /**
     * getImageUrl
     *
     * @param  mixed $photo
     * @return void
     */
    public function getImageUrl(array $photo)
    {
        $image_url = '';

        $file_id = $photo[count($photo) - 1]['file_id'];

        // set url -> https://api.telegram.org/bot<Your-Bot-token>/getFile?file_id=<Your-file-id>
        $url = "{$this->api_endpoint}/{$this->token}/getFile?file_id={$file_id}";

        // Send the request
        try {
            $response = Http::withHeaders($this->headers)->get($url);
            $result = ['success' => $response->ok(), 'body' => $response->json()];

            $file_path = $result['body']['result']['file_path'];

            // https://api.telegram.org/file/bot<Your-Bot-token>/<Your-file-path>
            $image_url = "{$this->api_endpoint}/file/{$this->token}/{$file_path}";
        } catch (\Throwable $th) {
            $result['error'] = $th->getMessage();
        }

        \Log::info('TelegramBot->getImageUrl->result', ['result' => $result]);

        return $image_url;
    }
    public function getImageId(array $photo)
    {
        $image_url = '';

        $file_id = $photo[count($photo) - 1]['file_id'];


        return $file_id;
    }
}
