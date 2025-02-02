<?php

namespace App\Services;

class TelegramService
{
    private string $baseUrl;
    private string $botToken;

    public function __construct()
    {
        $this->baseUrl = 'https://api.telegram.org/';
        $this->botToken = config('services.telegram.bot_token');
    }

    public function sendMessage(string $chatId, string $text, array $options = []): array
    {
        return $this->makeRequest('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ], $options));
    }

    public function sendMarkdownMessage(string $chatId, string $text, array $options = []): array
    {
        return $this->sendMessage($chatId, $text, array_merge([
            'parse_mode' => 'MarkdownV2'
        ], $options));
    }

    public function sendHTMLMessage(string $chatId, string $text, array $options = []): array
    {
        return $this->sendMessage($chatId, $text, array_merge([
            'parse_mode' => 'HTML'
        ], $options));
    }
      // check that the chatId is channel member
    public function checkChatIdIsChannelMember(string $chatId, $channelId): bool
    {
        $url = $this->baseUrl . $this->botToken . '/getChatMember';
        $params = [
            'chat_id' => $channelId,
            'user_id' => $chatId
        ];
        $response = $this->makeRequest('getChatMember', $params);
        return $response['result'] > 0;
    }

    /**
     * ایجاد متن با فرمت HTML برای تلگرام
     *
     * @param string $text متن اصلی
     * @return string متن فرمت شده
     */
    public function formatHTML(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * ایجاد متن با فرمت Markdown برای تلگرام
     *
     * @param string $text متن اصلی
     * @return string متن فرمت شده
     */
    public function formatMarkdown(string $text): string
    {
        $escapeChars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        return str_replace($escapeChars, array_map(fn($char) => "\\$char", $escapeChars), $text);
    }

    /**
     * ایجاد لینک با فرمت HTML
     */
    public function createHTMLLink(string $text, string $url): string
    {
        return sprintf('<a href="%s">%s</a>', $this->formatHTML($url), $this->formatHTML($text));
    }

    /**
     * ایجاد لینک با فرمت Markdown
     */
    public function createMarkdownLink(string $text, string $url): string
    {
        return sprintf('[%s](%s)', $this->formatMarkdown($text), $this->formatMarkdown($url));
    }

    /**
     * ایجاد متن پررنگ با HTML
     */
    public function boldHTML(string $text): string
    {
        return sprintf('<b>%s</b>', $this->formatHTML($text));
    }

    /**
     * ایجاد متن پررنگ با Markdown
     */
    public function boldMarkdown(string $text): string
    {
        return sprintf('*%s*', $this->formatMarkdown($text));
    }

    /**
     * ایجاد متن کج با HTML
     */
    public function italicHTML(string $text): string
    {
        return sprintf('<i>%s</i>', $this->formatHTML($text));
    }

    /**
     * ایجاد متن کج با Markdown
     */
    public function italicMarkdown(string $text): string
    {
        return sprintf('_%s_', $this->formatMarkdown($text));
    }

    /**
     * ایجاد متن کد با HTML
     */
    public function codeHTML(string $text): string
    {
        return sprintf('<code>%s</code>', $this->formatHTML($text));
    }

    /**
     * ایجاد متن کد با Markdown
     */
    public function codeMarkdown(string $text): string
    {
        return sprintf('`%s`', $this->formatMarkdown($text));
    }

    /**
     * ایجاد بلوک کد با HTML
     */
    public function preHTML(string $text, string $language = ''): string
    {
        if ($language) {
            return sprintf('<pre><code class="language-%s">%s</code></pre>',
                $this->formatHTML($language),
                $this->formatHTML($text)
            );
        }
        return sprintf('<pre>%s</pre>', $this->formatHTML($text));
    }

    /**
     * ایجاد بلوک کد با Markdown
     */
    public function preMarkdown(string $text, string $language = ''): string
    {
        return sprintf('```%s\n%s\n```',
            $language,
            $this->formatMarkdown($text)
        );
    }

    public function sendMessageWithKeyboard(string $chatId, string $text, array $buttons, bool $resize = true): array
    {
        $response = $this->sendMessage($chatId, $text, [
            'reply_markup' => json_encode([
                'keyboard' => $this->formatKeyboardButtons($buttons),
                'resize_keyboard' => $resize
            ])
        ]);
        return $response;
    }

    public function sendMessageWithInlineKeyboard(string $chatId, string $text, array $buttons): array
    {
        return $this->sendMessage($chatId, $text, [
            'reply_markup' => json_encode([
                'inline_keyboard' => $this->formatInlineKeyboardButtons($buttons)
            ])
        ]);
    }

    public function removeKeyboard(string $chatId, string $text): array
    {
        return $this->sendMessage($chatId, $text, [
            'reply_markup' => json_encode([
                'remove_keyboard' => true
            ])
        ]);
    }

    public function forceReply(string $chatId, string $text): array
    {
        return $this->sendMessage($chatId, $text, [
            'reply_markup' => json_encode([
                'force_reply' => true
            ])
        ]);
    }

    public function sendPhoto(string $chatId, string $photo, string $caption = '', array $options = []): array
    {
        return $this->makeRequest('sendPhoto', array_merge([
            'chat_id' => $chatId,
            'photo' => $photo,
            'caption' => $caption,
            'parse_mode' => 'HTML'
        ], $options));
    }

    public function sendDocument(string $chatId, string $document, string $caption = '', array $options = []): array
    {
        try {
            $result = $this->makeRequest('sendDocument', array_merge([
                'chat_id' => $chatId,
                'document' => $document,
                'caption' => $caption,
                'parse_mode' => 'HTML'
        ], $options));
        // \Log::info("sendDocument result=> $result");
        return $result;
        } catch (\Throwable $th) {
            \Log::error($th->getMessage());
            return [];
        }
    }

    public function sendLocation(string $chatId, float $latitude, float $longitude): array
    {
        return $this->makeRequest('sendLocation', [
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude
        ]);
    }

    public function getFile(string $fileId): array
    {
        return $this->makeRequest('getFile', [
            'file_id' => $fileId
        ]);
    }

    public function downloadFile(string $filePath): string
    {
        $url = "https://api.telegram.org/file/bot{$this->botToken}/{$filePath}";
        return file_get_contents($url);
    }

    public function sendVoice(string $chatId, string $voice, string $caption = '', array $options = []): array
    {
        return $this->makeRequest('sendVoice', array_merge([
            'chat_id' => $chatId,
            'voice' => $voice,
            'caption' => $caption,
            'parse_mode' => 'HTML'
        ], $options));
    }

    public function sendVideo(string $chatId, string $video, string $caption = '', array $options = []): array
    {
        return $this->makeRequest('sendVideo', array_merge([
            'chat_id' => $chatId,
            'video' => $video,
            'caption' => $caption,
            'parse_mode' => 'HTML'
        ], $options));
    }

    public function sendContact(string $chatId, string $phoneNumber, string $firstName, string $lastName = ''): array
    {
        return $this->makeRequest('sendContact', [
            'chat_id' => $chatId,
            'phone_number' => $phoneNumber,
            'first_name' => $firstName,
            'last_name' => $lastName
        ]);
    }

    public function sendChatAction(string $chatId, string $action): array
    {
        return $this->makeRequest('sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action // typing, upload_photo, record_video, upload_video, record_voice, upload_voice, upload_document
        ]);
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): array
    {
        return $this->makeRequest('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert
        ]);
    }

    private function formatKeyboardButtons(array $buttons): array
    {
        $keyboard = [];
        foreach ($buttons as $row) {
            $keyboardRow = [];
            foreach ($row as $button) {
                $keyboardRow[] = ['text' => $button];
            }
            $keyboard[] = $keyboardRow;
        }
        return $keyboard;
    }


    private function formatInlineKeyboardButtons(array $buttons): array
    {
        $keyboard = [];
        foreach ($buttons as $row) {
            $keyboardRow = [];
            foreach ($row as $text => $callbackData) {
                $keyboardRow[] = [
                    'text' => $text,
                    'callback_data' => $callbackData
                ];
            }
            $keyboard[] = $keyboardRow;
        }
        return $keyboard;
    }

    private function makeRequest(string $method, array $params = []): array
    {
        $url = $this->baseUrl . $this->botToken . '/' . $method;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('خطا در ارتباط با تلگرام: ' . $error);
        }

        return json_decode($response, true) ?? [];
    }
}
