<?php

declare(strict_types=1);

/**
 * Telegram API Controller — bot configuration, test, chat ID detection.
 */
class TelegramApiController
{
    private TelegramNotifier $telegramService;

    public function __construct(Connection $conn)
    {
        $this->telegramService = new TelegramNotifier($conn);
    }

    /** Get Telegram config. */
    public function getConfig(): void
    {
        AuthMiddleware::requireAdmin();
        Response::json($this->telegramService->getConfig());
    }

    /** Save Telegram settings. */
    public function save(): void
    {
        AuthMiddleware::requireAdmin();
        $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $token = $body['token'] ?? null;
        $chatId = $body['chat_id'] ?? '';
        $enabled = $body['enabled'] ?? '0';
        Response::json($this->telegramService->save($token, $chatId, $enabled));
    }

    /** Send test message. */
    public function test(): void
    {
        AuthMiddleware::requireAdmin();
        Response::json($this->telegramService->test());
    }

    /** Fetch recent updates for chat ID detection. */
    public function updates(): void
    {
        AuthMiddleware::requireAdmin();
        Response::json($this->telegramService->getUpdates());
    }
}
