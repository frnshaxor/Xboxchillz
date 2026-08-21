<?php

declare(strict_types=1);

/**
 * API Routes — maps ?op= parameter to API controller actions.
 * All responses are JSON.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Global API rate limit: 100 requests per minute per IP
RateLimitMiddleware::enforceGlobalApi();

$op = $request->op();
$conn = Connection::getInstance();

switch ($op) {
    // ─── Health check (no auth, no rate limit) ───
    case 'health':
        $dbOk = false;
        try {
            $testDb = Connection::getInstance()->db();
            $testDb->query('SELECT 1');
            $dbOk = true;
        } catch (\Throwable $e) {
            $dbOk = false;
        }
        Response::json([
            'status' => $dbOk ? 'ok' : 'degraded',
            'db' => $dbOk,
            'php' => PHP_VERSION,
            'time' => date('c'),
        ], $dbOk ? 200 : 503);

        return;

        // ─── Public state ───
    case 'state':
        (new SettingsApiController($conn))->state();

        return;

        // ─── Analytics (public + admin) ───
    case 'event':
        if ($request->isPost()) {
            (new AnalyticsApiController($conn))->recordEvent();
        }

        return;

    case 'heatmap':
        if ($request->isPost()) {
            (new AnalyticsApiController($conn))->recordHeatmap();
        }

        return;

    case 'insights':
        (new AnalyticsApiController($conn))->getInsights();

        return;

    case 'heatmap_data':
        (new AnalyticsApiController($conn))->getVideoHeatmap();

        return;

        // ─── Admin-only settings ───
    case 'watermark_get':
        (new SettingsApiController($conn))->getWatermark();

        return;

    case 'watermark':
        if ($request->isPost()) {
            (new SettingsApiController($conn))->watermark();
        }

        return;

    case 'upload_limit':
        if ($request->isPost()) {
            (new SettingsApiController($conn))->uploadLimit();
        }

        return;

    case 'maintenance':
        if ($request->isPost()) {
            (new SettingsApiController($conn))->maintenance();
        }

        return;

    case 'cache_bust':
        if ($request->isPost()) {
            (new SettingsApiController($conn))->cacheBust();
        }

        return;

        // ─── Backup ───
    case 'backup':
        if ($request->isPost()) {
            (new SettingsApiController($conn))->backup();
        }

        return;

    case 'backup_list':
        (new SettingsApiController($conn))->backupList();

        return;

    case 'backup_download':
        (new SettingsApiController($conn))->backupDownload();

        return;

        // ─── Telegram ───
    case 'telegram_get':
        (new TelegramApiController($conn))->getConfig();

        return;

    case 'telegram_save':
        if ($request->isPost()) {
            (new TelegramApiController($conn))->save();
        }

        return;

    case 'telegram_test':
        if ($request->isPost()) {
            (new TelegramApiController($conn))->test();
        }

        return;

    case 'telegram_updates':
        (new TelegramApiController($conn))->updates();

        return;

        // ─── Token management (admin API) ───
    case 'token_list':
        AuthMiddleware::requireAdmin();
        Response::json(['tokens' => (new TokenManager($conn))->list()]);

        return;

    case 'token_create':
        if ($request->isPost()) {
            $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            CsrfMiddleware::validateApi($body);
            $result = (new TokenManager($conn))->create(
                $body['label'] ?? '',
                $body['contact_type'] ?? 'telegram',
                $body['contact_value'] ?? ''
            );
            if (isset($result['ok'])) {
                // Return the full token object
                $tokens = (new TokenManager($conn))->list();
                $newToken = null;
                foreach ($tokens as $t) {
                    if ($t['id'] === $result['id']) {
                        $newToken = $t;
                        break;
                    }
                }
                Response::json(['ok' => true, 'token' => $newToken]);
            } else {
                Response::json($result, 422);
            }
        }

        return;

    case 'token_toggle':
        if ($request->isPost()) {
            $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            CsrfMiddleware::validateApi($body);
            $id = (int) ($body['id'] ?? 0);
            Response::json((new TokenManager($conn))->toggle($id));
        }

        return;

    case 'token_edit':
        if ($request->isPost()) {
            $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            CsrfMiddleware::validateApi($body);
            $result = (new TokenManager($conn))->update(
                (int) ($body['id'] ?? 0),
                $body['label'] ?? '',
                $body['contact_type'] ?? 'telegram',
                $body['contact_value'] ?? ''
            );
            Response::json($result);
        }

        return;

    case 'token_delete':
        if ($request->isPost()) {
            $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            CsrfMiddleware::validateApi($body);
            (new TokenManager($conn))->delete((int) ($body['id'] ?? 0));
            Response::json(['ok' => true]);
        }

        return;

        // ─── 2FA ───
    case '2fa_setup':
        AuthMiddleware::requireAdmin();
        $auth = new Auth($conn);
        $setup = $auth->setup2fa();
        $_SESSION['2fa_secret'] = $setup['secret'];
        Response::json($setup);

        return;

    case '2fa_enable':
        AuthMiddleware::requireAdmin();
        $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $auth = new Auth($conn);
        if ($auth->enable2fa($_SESSION['2fa_secret'] ?? '', $body['code'] ?? '')) {
            unset($_SESSION['2fa_secret']);
            Response::json(['ok' => true]);
        } else {
            Response::json(['error' => 'Kode salah'], 422);
        }

        return;

    case '2fa_disable':
        AuthMiddleware::requireAdmin();
        (new Auth($conn))->disable2fa();
        Response::json(['ok' => true]);

        return;

        // ─── Activity log ───
    case 'activity':
        AuthMiddleware::requireAdmin();
        $activityLog = new ActivityLog($conn);
        $loginAttempt = new LoginAttempt($conn);
        Response::json([
            'activity' => $activityLog->getRecent(),
            'failed_logins' => $loginAttempt->getRecentFailed(),
        ]);

        return;

        // ─── Video Library (admin) ───
    case 'video_library':
        AuthMiddleware::requireAdmin();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(128, max(8, (int) ($_GET['per_page'] ?? 64)));
        $search = trim((string) ($_GET['search'] ?? ''));
        $videoModel = new Video($conn);
        $result = $videoModel->searchPaginated($search, $page, $perPage);
        // Add poster URLs
        foreach ($result['videos'] as &$v) {
            $v['poster_url'] = $v['poster'] ? poster_url($v['poster']) : '';
        }
        unset($v);
        $result['page'] = $page;
        $result['per_page'] = $perPage;
        $result['pages'] = max(1, (int) ceil($result['total'] / $perPage));
        Response::json($result);

        return;

    case 'video_update':
        if ($request->isPost()) {
            AuthMiddleware::requireAdmin();
            $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            CsrfMiddleware::validateApi($body);
            $id = (int) ($body['id'] ?? 0);
            $title = trim((string) ($body['title'] ?? ''));
            $categoryId = (int) ($body['category_id'] ?? 0);
            if (!$id || $title === '') {
                Response::json(['error' => 'ID dan judul wajib diisi.'], 422);
            }
            $videoModel = new Video($conn);
            $ok = $videoModel->updateMetadata($id, $title, $categoryId);
            if ($ok) {
                (new ActivityLog($conn))->record(
                    (int) ($_SESSION['admin_id'] ?? 0),
                    'video_update',
                    "id=$id title=$title cat=$categoryId"
                );
                Response::json(['ok' => true]);
            } else {
                Response::json(['error' => 'Video tidak ditemukan.'], 404);
            }
        }

        return;

    case 'categories_list':
        AuthMiddleware::requireAdmin();
        $cats = $conn->selectAll('SELECT id, name FROM categories ORDER BY name');
        Response::json(['categories' => $cats]);

        return;

        // ─── Chunked Upload (Bulk Video Upload) ───
    case 'upload_init':
        if ($request->isPost()) {
            (new VideoController($conn))->uploadInit();
        }

        return;

    case 'upload_chunk':
        if ($request->isPost()) {
            (new VideoController($conn))->uploadChunk();
        }

        return;

    case 'upload_complete':
        if ($request->isPost()) {
            (new VideoController($conn))->uploadComplete();
        }

        return;

    case 'upload_status':
        (new VideoController($conn))->uploadStatus();

        return;

    case 'upload_abort':
        if ($request->isPost()) {
            (new VideoController($conn))->uploadAbort();
        }

        return;

        // ─── Midtrans checkout (called from token modal) ───
    case 'midtrans_checkout':
        if ($request->isPost()) {
            $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            CsrfMiddleware::validateApi($body);
            $result = (new MidtransPayment($conn))->createCheckout(
                $body['name'] ?? '',
                $body['contact'] ?? '',
                client_ip()
            );
            Response::json($result, isset($result['error']) ? 422 : 200);
        }

        return;

    case 'midtrans_orders':
        AuthMiddleware::requireAdmin();
        Response::json(['orders' => (new MidtransPayment($conn))->listOrders()]);

        return;

    case 'payment_status':
        Response::json((new PaymentController($conn))->getStatus());

        return;

    case 'process_webhook_retries':
        if ($request->isPost()) {
            AuthMiddleware::requireAdmin();
            Response::json(['ok' => true, 'processed' => (new MidtransPayment($conn))->processRetries()]);
        }

        return;

    default:
        Response::json(['error' => 'Unknown operation'], 404);

        return;
}
