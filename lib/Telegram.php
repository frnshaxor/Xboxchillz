<?php
// Telegram Bot API helper (native cURL, no deps)
declare(strict_types=1);

class Telegram {
    public function __construct(private string $token) {}

    private function call(string $method, array $data = [], ?array $file = null): array {
        $url = 'https://api.telegram.org/bot' . $this->token . '/' . $method;
        $ch = curl_init($url);
        if ($file) {
            $data[$file['field']] = new CURLFile($file['path']);
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
        ]);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($resp === false) return ['ok' => false, 'error' => $err];
        $decoded = json_decode($resp, true);
        return is_array($decoded) ? $decoded : ['ok' => false, 'error' => 'invalid_json'];
    }

    public function getMe(): array { return $this->call('getMe'); }
    public function sendMessage(string $chat_id, string $text): array {
        return $this->call('sendMessage', ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => false]);
    }
    public function sendPhoto(string $chat_id, string $photo_path, string $caption = ''): array {
        return $this->call('sendPhoto', ['chat_id' => $chat_id, 'caption' => $caption, 'parse_mode' => 'HTML'], ['field' => 'photo', 'path' => $photo_path]);
    }
    public function getUpdates(int $offset = 0, int $limit = 20): array {
        return $this->call('getUpdates', ['offset' => $offset, 'limit' => $limit, 'timeout' => 0, 'allowed_updates' => json_encode(['message'])]);
    }
}
