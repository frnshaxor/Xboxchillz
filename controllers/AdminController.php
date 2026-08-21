<?php
declare(strict_types=1);

/**
 * Admin Controller — renders admin panel, saves site settings.
 */
class AdminController
{
    private ActivityLog $activityModel;

    public function __construct(Connection $conn)
    {
        $this->activityModel = new ActivityLog($conn);
    }

    /** Show admin panel. */
    public function index(): void
    {
        AuthMiddleware::requireAdmin();

        $db = Connection::getInstance()->db();

        $cats = $db->query('SELECT * FROM categories ORDER BY name');
        $vids = $db->query('SELECT v.*, c.name category FROM videos v LEFT JOIN categories c ON c.id=v.category_id ORDER BY v.created_at DESC');

        $meQ = $db->prepare('SELECT name,email,totp_enabled,last_login_at,last_login_ip FROM admins WHERE id=?');
        $meQ->bind_param('i', $_SESSION['admin_id']);
        $meQ->execute();
        $me = $meQ->get_result()->fetch_assoc();

        $site         = setting($db, 'site_name', 'Arsip Layar');
        $desc         = setting($db, 'site_description', 'Platform berbagi karya video untuk kreator.');
        $upload_mb    = (int)setting($db, 'upload_max_mb', '2048');
        $maintenance  = setting($db, 'maintenance_mode', '0');
        $tab          = $_GET['tab'] ?? 'content';
        $watermark_text     = setting($db, 'watermark_text', 'Codename F');
        $watermark_position = setting($db, 'watermark_position', 'br');
        $watermark_opacity  = (int)setting($db, 'watermark_opacity', '60');
        $cache_ver = setting($db, 'cache_ver', '1');
        $midtransEnabled   = setting($db, 'midtrans_enabled', '0') === '1';
        $midtransClientKey = setting($db, 'midtrans_client_key', '');
        $midtransMode      = setting($db, 'midtrans_mode', 'sandbox') === 'production' ? 'production' : 'sandbox';
        $midtransPrice     = (int)setting($db, 'midtrans_token_price', '50000');

        // Include the admin view
        require VIEWS_DIR . '/admin/index.php';
    }

    /** Save site identity settings. */
    public function saveSettings(): void
    {
        AuthMiddleware::requireAdmin();
        CsrfMiddleware::validate();

        $db = Connection::getInstance()->db();
        $old = [];
        $new = [];
        foreach (['site_name', 'site_description'] as $k) {
            $old[$k] = setting($db, $k, '');
            $v = trim((string)($_POST[$k] ?? ''));
            $new[$k] = $v;
            set_setting($db, $k, $v);
        }
        $this->activityModel->recordDiff((int)$_SESSION['admin_id'], 'settings_save', 'site_identity', $old, $new);
        go('?page=admin&saved=1');
    }

    /** Save contact page settings. */
    public function saveContact(): void
    {
        AuthMiddleware::requireAdmin();
        CsrfMiddleware::validate();

        $db = Connection::getInstance()->db();
        $old = [];
        $new = [];
        foreach (['contact_title', 'contact_subtitle', 'contact_telegram', 'contact_whatsapp', 'contact_email'] as $k) {
            $old[$k] = setting($db, $k, '');
            $v = trim((string)($_POST[$k] ?? ''));
            $new[$k] = $v;
            set_setting($db, $k, $v);
        }
        $this->activityModel->recordDiff((int)$_SESSION['admin_id'], 'contact_settings_save', 'contact_page', $old, $new);
        go('?page=admin&tab=system&contact_saved=1');
    }
}
