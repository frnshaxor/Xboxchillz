<?php
/**
 * API entry point — routes api.php?op= requests through the new architecture.
 * This exists because vue_enhance.js calls api.php?op= directly.
 * 
 * API Versioning:
 *   - Default: api.php?op=state (current version)
 *   - Explicit: api.php?v=1&op=state (version 1)
 *   - All current endpoints are v1. Future versions can add new controllers.
 */

declare(strict_types=1);

// Load bootstrap (session, DB, helpers, models, services, etc.)
require_once dirname(__DIR__) . '/app/bootstrap.php';

// Create Request object
$request = new Request();

// API version (for future use — all current endpoints are v1)
$apiVersion = (int)($_GET['v'] ?? 1);
if ($apiVersion < 1 || $apiVersion > 1) {
    Response::json(['error' => 'API version not supported'], 400);
}
header('X-API-Version: v' . $apiVersion);

// Route API requests
$op = $request->op();

if ($op !== '') {
    require ROUTES_API;
    return;
}

// No op specified
Response::json(['error' => 'No operation specified'], 400);
