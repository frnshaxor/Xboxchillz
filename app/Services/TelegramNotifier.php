<?php
declare(strict_types=1);

/**
 * Telegram Notifier Service — bot notifications, chat ID detection, test messages.
 */
class TelegramNotifier
{
    private ActivityLog $activityModel;

    public function __construct(Connection $conn)
    {
        $this->activityModel = new ActivityLog($conn);
    }

    /** Get current Telegram config. */
    public function getConfig(): array
    {
        $db = Connection::getInstance()->db();
        $token = setting($db, 'telegram_bot_token', '');
        return [
            'has_token'  => $token !== '',
            'token_mask' => $token ? substr($token, 0, 8) . '…' . substr($token, -4) : '',
            'chat_id'    => setting($db, 'telegram_chat_id', ''),
            'enabled'    => setting($db, 'telegram_enabled', '0') === '1',
        ];
    }

    /** Save Telegram settings. */
    public function save(?string $token, string $chatId, string $enabled): array
    {
        $db = Connection::getInstance()->db();
        if ($token !== null && $token !== '') {
            set_setting($db, 'telegram_bot_token', $token);
        }
        set_setting($db, 'telegram_chat_id', $chatId);
        set_setting($db, 'telegram_enabled', $enabled === '1' ? '1' : '0');
        return ['ok' => true];
    }

    /** Send a test message to the configured chat. */
    public function test(): array
    {
        $db = Connection::getInstance()->db();
        $token  = setting($db, 'telegram_bot_token', '');
        $chatId = setting($db, 'telegram_chat_id', '');
        if (!$token) return ['error' => 'Bot token belum diatur.'];
        if (!$chatId) return ['error' => 'Chat ID belum diatur.'];

        $result = $this->sendMessage($token, $chatId, '🧪 Test dari Arsip Layar — notifikasi Telegram aktif!');
        if ($result['ok']) {
            $botInfo = $this->getBotInfo($token);
            return ['ok' => true, 'bot' => $botInfo, 'note' => 'Pesan test berhasil dikirim.'];
        }
        return ['error' => $result['error'] ?? 'Gagal mengirim pesan.'];
    }

    /** Fetch recent updates (for chat ID detection). */
    public function getUpdates(): array
    {
        $db = Connection::getInstance()->db();
        $token = setting($db, 'telegram_bot_token', '');
        if (!$token) return ['error' => 'Bot token belum diatur.'];

        $ch = curl_init("https://api.telegram.org/bot{$token}/getUpdates?limit=10");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);

        if (!isset($data['result'])) return ['error' => 'Gagal mengambil updates.'];

        $chats = [];
        $seen = [];
        foreach ($data['result'] as $update) {
            $chat = $update['message']['chat'] ?? null;
            if (!$chat) continue;
            $id = $chat['id'];
            if (isset($seen[$id])) continue;
            $seen[$id] = true;
            $chats[] = [
                'id'       => $id,
                'title'    => $chat['title'] ?? $chat['first_name'] ?? '',
                'username' => $chat['username'] ?? '',
                'type'     => $chat['type'] ?? '',
                'when'     => date('d M H:i', $update['message']['date'] ?? time()),
            ];
        }

        return ['ok' => true, 'chats' => $chats];
    }

    /** Send a video notification (poster + title + link). */
    public function notifyVideo(string $title, string $posterUrl, string $watchUrl): void
    {
        $db = Connection::getInstance()->db();
        $enabled = setting($db, 'telegram_enabled', '0') === '1';
        $token   = setting($db, 'telegram_bot_token', '');
        $chatId  = setting($db, 'telegram_chat_id', '');
        if (!$enabled || !$token || !$chatId) return;

        $text = "🎬 *New video uploaded*\n\n*" . addslashes($title) . "*\n\n[▶ Watch now]({$watchUrl})";
        $this->sendMessage($token, $chatId, $text, 'Markdown', $watchUrl);
    }

    /** Send a message via Telegram Bot API. */
    private function sendMessage(string $token, string $chatId, string $text, string $parseMode = '', string $watchUrl = ''): array
    {
        $payload = [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => $parseMode,
        ];
        if ($parseMode === 'Markdown' && $watchUrl) {
            $payload['reply_markup'] = json_encode([
                'inline_keyboard' => [[['text' => '▶ Watch', 'url' => $watchUrl]]],
            ]);
        }

        $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);
        if ($httpCode !== 200 || !($result['ok'] ?? false)) {
            return ['error' => $result['description'] ?? 'Telegram API error'];
        }
        return ['ok' => true];
    }

    /** Get bot info. */
    private function getBotInfo(string $token): ?array
    {
        $ch = curl_init("https://api.telegram.org/bot{$token}/getMe");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        return $data['result'] ?? null;
    }
}
