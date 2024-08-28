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
    public function sendMessage($text , $chat_id, $reply_to_message_id, $parse, $key = null)
    {
        // Default result array
        $result = ['success' => false, 'body' => []];

        // Create params array
        $params = [
            'chat_id' => $chat_id,
            'reply_to_message_id' => $reply_to_message_id,
            'text' => $text,
            'allow_sending_without_reply' => true,
            // 'reply_markup' => $key,

            'parse_mode' => $parse,
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

        // \Log::info('TelegramBot->sendMessage->result', ['result' => $result]);

        return $result;
    }
    public function deleteMessage($chat_id, $message_id)
    {
        // Default result array
        $result = ['success' => false, 'body' => []];

        // Create params array
        $params = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
        ];

        // Create url -> https://api.telegram.org/bot{token}/sendMessage
        $url = "{$this->api_endpoint}/{$this->token}/deleteMessage";

        // Send the request
        try {
            $response = Http::withHeaders($this->headers)->post($url, $params);
            $result = ['success' => $response->ok(), 'body' => $response->json()];
        } catch (\Throwable $th) {
            $result['error'] = $th->getMessage();
            return false;
        }

        return true;
    }
    public function editMessageReplyMarkup($chat_id, $message_id, $command)
    {
        // Default result array
        $result = ['success' => false, 'body' => []];

        // Create params array

        $params = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'reply_markup' => ['inline_keyboard' => $command, 'resize_keyboard' => true],
        ];

        // Create url -> https://api.telegram.org/bot{token}/sendMessage
        $url = "{$this->api_endpoint}/{$this->token}/editMessageReplyMarkup";

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
    public function checkMember($channelID, $chat_id)
    {
        // Default result array
        $result = ['success' => false, 'body' => []];

        // Create params array
        $params = [
            'chat_id' => $channelID,
            'user_id' => $chat_id,
        ];

        // Create url -> https://api.telegram.org/bot{token}/sendMessage
        $url = "{$this->api_endpoint}/{$this->token}/getChatMember";

        // Send the request
        try {
            $response = Http::withHeaders($this->headers)->post($url, $params);
            if ($response->ok() != false) {

                $result = ['success' => $response->ok(), 'body' => $response->json()];
                $json = $response->json();

                // $res = $json['status'];
                if( $json["result"]["status"] == "left"){
                    return false;
                }
                // \Log::info('rss', ['json' => $json["result"]["status"]]);

                // \Log::info('yesssssssss', ['result' => $result]);


                return true;
            } else {

                $result = ['False' => $response->ok(), 'body' => $response->json()];
                // \Log::info('noooooooooo', ['result' => $result]);

                return false;
            }
        } catch (\Throwable $th) {
            $result['error'] = $th->getMessage();
            \Log::info("Throwable  $th");

            return false;
        }

        \Log::info('TelegramBot->sendMessage->result', ['result' => $result]);

        return false;
    }
    public function buttonMessage($text , $opr, $chat_id, $reply_to_message_id)
    {
        // Default result array
        $result = ['success' => false, 'body' => []];

        // Create params array

        $params = [
            'chat_id' => $chat_id,
            // 'reply_to_message_id' => $reply_to_message_id,
            'allow_sending_without_reply' => true,
            'text' => $text,
            'reply_markup' => ['keyboard' => $opr, 'resize_keyboard' => true],
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
    public function inlineKeyboardButton($text, $opr, $chat_id, $reply_to_message_id)
    {
        // Default result array
        $result = ['success' => false, 'body' => []];

        // Create params array

        $params = [
            'chat_id' => $chat_id,
            'reply_to_message_id' => $reply_to_message_id,
            'allow_sending_without_reply' => true,
            'text' => $text,
            'reply_markup' => ['inline_keyboard' => $opr, 'resize_keyboard' => true],
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

    public function imageMessage($image, $chat_id, $caption)
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
    public function imageMessageByLink($image, $chat_id, $caption)
    {
        // Default result array
        $result = ['success' => false, 'body' => []];

        $params = [
            'chat_id' => $chat_id,
            'caption' => $caption,
        ];
        // $file = public_path() . '/images/' . 'aa.png';
        $url = "{$this->api_endpoint}/{$this->token}/sendPhoto";

        // Send the request
        try {
            $response = Http::attach('photo', file_get_contents($image), 'aa.png')->post($url, [
                'chat_id' => $chat_id,
                'caption' => $caption,
            ]);
            $result = ['success' => $response->ok(), 'body' => $response->json()];
        } catch (\Throwable $th) {
            $result['error'] = $th->getMessage();
        }

        \Log::info('TelegramBot->sendMessage->result', ['result' => $result]);

        return $result;
    }
    public function commandMessage($command, $chat_id, $text)
    {
        // Default result array
        $result = ['success' => false, 'body' => []];

        // Create params array

        $params = [
            'chat_id' => $chat_id,
            'text' => $text,
            'reply_markup' => ['inline_keyboard' => $command, 'resize_keyboard' => true],
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
    public function getImageUrlByFileID($file_id)
    {
        $image_url = '';

        // $file_id = $photo[count($photo) - 1]['file_id'];

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
        \Log::info("image_url:  $image_url");

        return $image_url;
    }
    public function getImageId(array $photo)
    {
        $image_url = '';

        $file_id = $photo[count($photo) - 1]['file_id'];
        // \Log::info('TelegramBot->getImageUrl->result', ['imaaaaaaaaaaaaage' => $photo]);
        return $file_id;
    }
}
