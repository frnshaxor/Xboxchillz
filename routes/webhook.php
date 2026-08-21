<?php

declare(strict_types=1);

/**
 * Webhook Routes — external service callbacks (Midtrans, etc.).
 */

$conn = Connection::getInstance();

$page = $_GET['page'] ?? '';

switch ($page) {
    case 'midtrans-notify':
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            http_response_code(400);
            exit('Payload tidak valid');
        }
        (new MidtransPayment($conn))->handleWebhook($payload);

        return;

    default:
        http_response_code(404);
        exit('Webhook tidak ditemukan.');
}
