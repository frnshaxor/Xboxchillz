<?php
declare(strict_types=1);

/**
 * Arsip Layar — Front Controller.
 * Single entry point: all requests go through here.
 */

// ─── Bootstrap ───
require_once dirname(__DIR__) . '/app/bootstrap.php';

// ─── Create Request object ───
$request = new Request();

// ─── Maintenance guard ───
maintenance_guard($db);

// ─── Route the request ───
$page = $request->page();
$op   = $request->op();

// Webhook routes (Midtrans notify) — no HTML rendering
if ($page === 'midtrans-notify' && $request->isPost()) {
    require ROUTES_WEBHOOK;
    return;
}

// API routes — return JSON
if ($op !== '' && $page !== 'midtrans-notify') {
    require ROUTES_API;
    return;
}

// Web routes — may render HTML
require ROUTES_WEB;

// If a specific page was routed (watch, admin, contact, etc.),
// the controller already rendered the full page — stop here.
if ($page !== 'home') return;

// ─── Default: Home page (gallery) ───
// No specific page requested — render the home gallery
$conn = Connection::getInstance();
$dbh  = $conn->db();

$site = setting($dbh, 'site_name', 'Arsip Layar');
$desc = setting($dbh, 'site_description', 'Platform berbagi karya video untuk kreator.');
$cache_ver = setting($dbh, 'cache_ver', '1');

$cat = (int)($_GET['category'] ?? 0);
$where = $cat ? ' WHERE v.category_id=' . $cat : '';
$videos = $dbh->query('SELECT v.*, c.name category FROM videos v LEFT JOIN categories c ON c.id=v.category_id' . $where . ' ORDER BY v.created_at DESC');
$categories = $dbh->query('SELECT * FROM categories ORDER BY name');

require VIEWS_DIR . '/pages/home.php';
