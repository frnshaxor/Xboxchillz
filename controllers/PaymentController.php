<?php
declare(strict_types=1);

/**
 * Payment Controller — Midtrans settings, checkout, webhook.
 */
class PaymentController
{
    private MidtransPayment $paymentService;

    public function __construct(Connection $conn)
    {
        $this->paymentService = new MidtransPayment($conn);
    }

    /** Save Midtrans settings (admin form). */
    public function saveSettings(): void
    {
        AuthMiddleware::requireAdmin();
        CsrfMiddleware::validate();

        $db = Connection::getInstance()->db();
        $isHttps = !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        $mode    = ($_POST['mode'] ?? 'sandbox') === 'production' ? 'production' : 'sandbox';
        $enabled = ($_POST['enabled'] ?? '') === '1' ? '1' : '0';
        if ($enabled === '1' && $mode === 'production' && !$isHttps) {
            go('?page=admin&tab=payments&midtrans_err=https');
        }

        $price = max(1000, min(100000000, (int)($_POST['price'] ?? 50000)));

        $old = [
            'midtrans_mode' => setting($db, 'midtrans_mode', 'sandbox'),
            'midtrans_enabled' => setting($db, 'midtrans_enabled', '0'),
            'midtrans_token_price' => setting($db, 'midtrans_token_price', '50000'),
        ];
        $new = ['midtrans_mode' => $mode, 'midtrans_enabled' => $enabled, 'midtrans_token_price' => (string)$price];

        set_setting($db, 'midtrans_mode', $mode);
        set_setting($db, 'midtrans_enabled', $enabled);
        set_setting($db, 'midtrans_token_price', (string)$price);

        foreach (['client_key' => 'midtrans_client_key', 'server_key' => 'midtrans_server_key'] as $field => $settingKey) {
            $value = trim((string)($_POST[$field] ?? ''));
            if ($value !== '') {
                $old[$settingKey] = '(hidden)';
                $new[$settingKey] = '(updated)';
                set_setting($db, $settingKey, $value);
            }
        }

        log_activity_diff($db, (int)$_SESSION['admin_id'], 'midtrans_settings_save', "mode=$mode enabled=$enabled price=$price", $old, $new);
        go('?page=admin&tab=payments&saved=1');
    }

    /** Handle Midtrans webhook notification. */
    public function webhook(): void
    {
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            http_response_code(400);
            exit('Payload tidak valid');
        }
        $this->paymentService->handleWebhook($payload);
    }

    /** Process webhook retries (admin API). */
    public function processRetries(): void
    {
        AuthMiddleware::requireAdmin();
        $processed = $this->paymentService->processRetries();
        Response::json(['ok' => true, 'processed' => $processed]);
    }

    /** Get payment status (API, for client-side polling). */
    public function getStatus(): void
    {
        $orderId      = $_GET['order_id'] ?? '';
        $accessSecret = $_GET['access_secret'] ?? '';
        Response::json($this->paymentService->getStatus($orderId, $accessSecret));
    }

    /** List recent orders (admin API). */
    public function listOrders(): void
    {
        AuthMiddleware::requireAdmin();
        Response::json(['orders' => $this->paymentService->listOrders()]);
    }
}
