<?php
declare(strict_types=1);

/**
 * MidtransPayment Service — checkout creation and webhook verification.
 */
class MidtransPayment
{
    private PaymentOrder $orderModel;
    private TokenManager $tokenManager;
    private WebhookRetry $webhookModel;
    private ActivityLog $activityModel;

    public function __construct(Connection $conn)
    {
        $this->orderModel    = new PaymentOrder($conn);
        $this->tokenManager  = new TokenManager($conn);
        $this->webhookModel  = new WebhookRetry($conn);
        $this->activityModel = new ActivityLog($conn);
    }

    /**
     * Create a Midtrans Snap checkout.
     * Returns ['ok' => true, 'snap_token' => '...', 'order_id' => '...', 'access_secret' => '...']
     */
    public function createCheckout(string $name, string $contact, string $clientIp): array
    {
        $db = Connection::getInstance()->db();
        $serverKey  = setting($db, 'midtrans_server_key', '');
        $clientKey  = setting($db, 'midtrans_client_key', '');
        $mode       = setting($db, 'midtrans_mode', 'sandbox');
        $price      = (int)setting($db, 'midtrans_token_price', '50000');

        if (!$serverKey || !$clientKey) {
            return ['error' => 'Midtrans belum dikonfigurasi.'];
        }

        $orderId = 'ARSIP-' . bin2hex(random_bytes(8));
        $accessSecret = bin2hex(random_bytes(32));
        $accessSecretHash = hash('sha256', $accessSecret);

        // Create order record
        $this->orderModel->create([
            'order_id'          => $orderId,
            'buyer_name'        => $name,
            'buyer_contact'     => $contact,
            'amount'            => $price,
            'status'            => 'pending',
            'snap_token'        => '',
            'access_secret_hash' => $accessSecretHash,
            'client_ip'         => $clientIp,
        ]);

        // Call Midtrans Snap API
        $payload = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => $price,
            ],
            'customer_details' => [
                'first_name' => $name,
                'phone'      => $contact,
            ],
        ];

        $ch = curl_init(midtrans_endpoint($mode));
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($serverKey . ':'),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);
        if ($httpCode !== 201 || empty($result['token'])) {
            $error = $result['error_messages'][0]['message'] ?? 'Midtrans API error';
            return ['error' => $error];
        }

        // Update order with snap token
        $this->orderModel->updateSnapToken($orderId, $result['token']);

        return [
            'ok'            => true,
            'snap_token'    => $result['token'],
            'order_id'      => $orderId,
            'access_secret' => $accessSecret,
        ];
    }

    /**
     * Handle Midtrans webhook notification.
     * Verifies signature + timestamp, updates order, auto-issues token on settlement.
     */
    public function handleWebhook(array $payload): void
    {
        $orderId    = (string)($payload['order_id'] ?? '');
        $statusCode = (string)($payload['status_code'] ?? '');
        $grossAmount = (string)($payload['gross_amount'] ?? '');
        $signature  = (string)($payload['signature_key'] ?? '');

        $db = Connection::getInstance()->db();
        $serverKey = setting($db, 'midtrans_server_key', '');
        $expected  = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        if (!$serverKey || !$signature || !hash_equals($expected, $signature)) {
            http_response_code(403);
            exit('Signature tidak valid');
        }

        // Replay attack prevention: reject webhooks older than 5 minutes
        $transactionTime = $payload['transaction_time'] ?? '';
        if ($transactionTime) {
            $webhookTime = strtotime($transactionTime);
            if ($webhookTime && abs(time() - $webhookTime) > 300) {
                http_response_code(410);
                exit('Webhook kedaluwarsa');
            }
        }

        $order = $this->orderModel->findByOrderIdForUpdate($orderId);
        if (!$order || number_format((float)$order['amount'], 2, '.', '') !== $grossAmount) {
            $this->orderModel->rollback();
            http_response_code(404);
            exit('Order tidak valid');
        }

        $transactionStatus = (string)($payload['transaction_status'] ?? '');
        $fraudStatus = (string)($payload['fraud_status'] ?? 'accept');
        $newStatus = in_array($transactionStatus, ['settlement'], true)
            || ($transactionStatus === 'capture' && $fraudStatus === 'accept')
            ? 'settlement' : $transactionStatus;

        $paymentType    = substr((string)($payload['payment_type'] ?? ''), 0, 60);
        $transactionId  = substr((string)($payload['transaction_id'] ?? ''), 0, 100);
        $json           = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $tokenId        = (int)($order['token_id'] ?? 0);

        // Auto-issue token on settlement
        if ($newStatus === 'settlement' && !$tokenId) {
            $result = $this->tokenManager->createFromPayment($order['buyer_name'], $order['buyer_contact']);
            if (isset($result['ok'])) {
                $tokenId = $result['id'];
                $this->activityModel->record(null, 'midtrans_token_issue', "order=$orderId token_id=$tokenId");
            }
        }

        $this->orderModel->updateAfterNotification($order['id'], [
            'status'         => $newStatus,
            'token_id'       => $tokenId,
            'transaction_id' => $transactionId,
            'payment_type'   => $paymentType,
            'notification_json' => $json,
        ]);

        $this->orderModel->commit();
        http_response_code(200);
        exit('OK');
    }

    /** Process pending webhook retries. */
    public function processRetries(): int
    {
        $pending = $this->webhookModel->getPending();
        $processed = 0;

        foreach ($pending as $wr) {
            $payload = json_decode($wr['payload'], true);
            if (!is_array($payload)) {
                $this->webhookModel->updateResult($wr['id'], 'failed', (int)$wr['attempts'], null, 'invalid_payload');
                continue;
            }

            // Re-process the webhook
            $orderId    = (string)($payload['order_id'] ?? '');
            $statusCode = (string)($payload['status_code'] ?? '');
            $grossAmount = (string)($payload['gross_amount'] ?? '');
            $signature  = (string)($payload['signature_key'] ?? '');
            $db = Connection::getInstance()->db();
            $serverKey = setting($db, 'midtrans_server_key', '');
            $expected  = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
            $error = '';

            if (!$serverKey || !$signature || !hash_equals($expected, $signature)) {
                $error = 'signature_invalid';
            } else {
                $order = $this->orderModel->findByOrderIdForUpdate($orderId);
                if (!$order) {
                    $this->orderModel->rollback();
                    $error = 'order_not_found';
                } else {
                    $transactionStatus = (string)($payload['transaction_status'] ?? '');
                    $fraudStatus = (string)($payload['fraud_status'] ?? 'accept');
                    $newStatus = in_array($transactionStatus, ['settlement'], true)
                        || ($transactionStatus === 'capture' && $fraudStatus === 'accept')
                        ? 'settlement' : $transactionStatus;
                    $paymentType   = substr((string)($payload['payment_type'] ?? ''), 0, 60);
                    $transactionId = substr((string)($payload['transaction_id'] ?? ''), 0, 100);
                    $json          = json_encode($payload, JSON_UNESCAPED_UNICODE);
                    $tokenId       = (int)($order['token_id'] ?? 0);

                    if ($newStatus === 'settlement' && !$tokenId) {
                        $result = $this->tokenManager->createFromPayment($order['buyer_name'], $order['buyer_contact']);
                        if (isset($result['ok'])) $tokenId = $result['id'];
                    }

                    $this->orderModel->updateAfterNotification($order['id'], [
                        'status'         => $newStatus,
                        'token_id'       => $tokenId,
                        'transaction_id' => $transactionId,
                        'payment_type'   => $paymentType,
                        'notification_json' => $json,
                    ]);
                    $this->orderModel->commit();
                }
            }

            // Exponential backoff: 5m, 15m, 45m, 2h, 6h
            $backoffMinutes = [5, 15, 45, 120, 360];
            $attempts = (int)$wr['attempts'] + 1;
            $newStatus = 'processed';
            $nextRetry = null;

            if ($error) {
                if ($attempts >= (int)$wr['max_attempts']) {
                    $newStatus = 'failed';
                } else {
                    $newStatus = 'pending';
                    $idx = min($attempts, count($backoffMinutes) - 1);
                    $nextRetry = date('Y-m-d H:i:s', time() + $backoffMinutes[$idx] * 60);
                }
            }

            $this->webhookModel->updateResult($wr['id'], $newStatus, $attempts, $nextRetry, $error);
            $processed++;
        }

        return $processed;
    }

    /** Get payment status for a specific order. */
    public function getStatus(string $orderId, string $accessSecret): array
    {
        $order = $this->orderModel->findByOrderId($orderId);
        if (!$order) return ['error' => 'Order tidak ditemukan.'];

        $expectedHash = hash('sha256', $accessSecret);
        if (!hash_equals($order['access_secret_hash'], $expectedHash)) {
            return ['error' => 'Secret tidak valid.'];
        }

        $result = ['status' => $order['status']];
        if ($order['status'] === 'settlement' && $order['token_id']) {
            $db = Connection::getInstance()->db();
            $s = $db->prepare('SELECT token FROM access_tokens WHERE id=?');
            $s->bind_param('i', $order['token_id']); $s->execute();
            $row = $s->get_result()->fetch_assoc();
            if ($row) $result['token'] = $row['token'];
        }
        return $result;
    }

    /** List recent orders. */
    public function listOrders(): array
    {
        return $this->orderModel->all();
    }
}
