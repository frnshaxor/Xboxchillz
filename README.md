# Arsip Layar — Codebase Documentation

> **Complete technical reference for AI agents and developers.**
> Last updated: August 20, 2026

---

## ⚠️ STRICT RULES FOR AI AGENTS

> **MANDATORY COMPLIANCE** — Every AI agent working on this codebase MUST follow these rules without exception.
> Violation of these rules can cause production outages, data loss, or security vulnerabilities.
>
> **📖 Also read `rules.md`** — A comprehensive AI agent guideline that consolidates all rules, workflows, and lessons learned from this file, `audit.md`, and `changelog.md`. Use it as your primary reference before starting ANY work.

### Rule 1: Understand Before You Modify
- **READ** the entire relevant file before making ANY changes
- **UNDERSTAND** the data flow: how does this code interact with other parts?
- **VERIFY** that your change won't break existing functionality
- **NEVER** assume — always double-check with `read_files` before editing

### Rule 2: Follow Existing Patterns
- **Models** use `$this->conn->selectOne/All/insert/execute()` with explicit type strings
- **Services** use `Connection::getInstance()` singleton
- **Controllers** call `AuthMiddleware::requireAdmin()` FIRST for admin routes
- **CSRF** is validated via `CsrfMiddleware::validate()` on ALL POST requests
- **Settings** use `setting()` and `set_setting()` — NOT raw queries
- **Logging** uses `log_activity()` or `log_activity_diff()` — NOT raw inserts

### Rule 3: Backup Before Major Changes
- **ALWAYS** create a backup before modifying critical files
- **VERIFY** the backup exists before proceeding
- **Document** what you changed in `changelog.md`

### Rule 4: Test Every Change
- **Run** `php -l <file>` to syntax-check modified PHP files
- **Verify** no duplicate function definitions
- **Check** that all type strings match parameter counts exactly
- **Confirm** session variables are set before accessing them

### Rule 5: Security First
- **NEVER** output user input without `e()` escaping
- **NEVER** use raw SQL — always use prepared statements
- **NEVER** store secrets in code — use the `settings` table
- **ALWAYS** validate file uploads (extension, MIME, size)
- **ALWAYS** use `escapeshellarg()` for shell commands

### Rule 6: Respect the Architecture
- **Entry point** is `public/index.php` — NOT root `index.php`
- **Legacy files** (root `index.php`, `config.php`, `api.php`) are NOT used by nginx
- **Auto-loading** happens via `glob()` in `bootstrap.php` — new files in `Models/`, `Services/`, `controllers/` are auto-loaded
- **Views** are plain PHP templates in `views/` — use `e()` for escaping
- **Frontend JS** is `public/assets/js/vue_enhance.js` — Vue 3 CDN components

### Rule 7: Document Everything
- **Update** `changelog.md` after every implementation
- **Update** `README.md` if architecture changes
- **Update** `audit.md` if new findings are discovered
- **Add comments** for non-obvious logic
- **Use** descriptive variable and function names

### Rule 8: Post-Fix Verification Procedure (MANDATORY)

> **Every AI agent MUST complete ALL steps below after fixing any bug or implementing any feature.**
> This is not optional. Skipping any step can leave broken code in production.

#### Step 1: Syntax Check
```bash
php -l <modified_file.php>   # Repeat for EVERY modified PHP file
```
- All files must pass with "No syntax errors detected"
- If any file fails, fix the syntax error before proceeding

#### Step 2: Runtime Verification
- **DB queries:** Run relevant SQL to verify data state (e.g., `SELECT status FROM videos WHERE...`)
- **Process check:** Verify background processes are running if applicable (e.g., `ps aux | grep arsip-hls-worker`)
- **Service check:** If a systemd service was created/modified, verify with `systemctl status <service>`
- **File system check:** Verify expected files exist (e.g., `ls media/{slug}/` for HLS outputs)

#### Step 3: End-to-End Flow Test
- Trace the full data flow from entry point to exit point
- Verify the fix works for the reported symptom AND doesn't break related workflows
- Check edge cases: empty inputs, concurrent access, error paths

#### Step 4: Documentation Update
- **`changelog.md`**: Add entry with date, description, files modified, and audit section reference
- **`audit.md`**: Add investigation section with symptom, root cause, methodology, fixes, verification, and lessons learned
- **`README.md`**: Update relevant sections (architecture, gotchas, patterns) if the fix changes understanding of the codebase
- **Cross-reference**: Every changelog entry should reference the audit section, and vice versa

#### Step 5: UI/UX Verification (MANDATORY for frontend changes)
- **Run ESLint:** `npm run lint` — must report 0 errors
- **Check contrast:** Verify text passes WCAG AA (4.5:1 minimum)
- **Test keyboard:** Tab through all interactive elements, verify focus-visible outlines
- **Test responsive:** Check at 320px, 768px, 1024px, 1440px widths
- **Test ARIA:** Verify `role`, `aria-label`, `aria-modal` on modals/dialogs
- **Test loading states:** Verify skeleton/spinner appears during async operations
- **Test error states:** Verify toast/inline error appears on failure

#### Step 6: Cleanup
- Remove any temporary files created during investigation
- Verify no debug code (`var_dump`, `error_log`, `dd()`) was left in production files
- Confirm all type strings match parameter counts (if database changes were made)

#### Verification Checklist Template
```markdown
## Verification — [bug/feature name]
- [ ] All modified files pass `php -l`
- [ ] DB state verified via SQL query
- [ ] Background processes running (if applicable)
- [ ] systemd service active (if applicable)
- [ ] Expected files exist on disk
- [ ] End-to-end flow tested
- [ ] changelog.md updated
- [ ] audit.md updated
- [ ] README.md updated (if architecture changed)
- [ ] No debug code left in production
```

---

## 1. Project Overview

**Arsip Layar** is a self-hosted video sharing platform built in PHP. It supports HLS adaptive streaming, token-based access control, Midtrans payment integration, Telegram bot notifications, and a full admin panel.

- **Language:** PHP 8.5 (no framework — custom MVC architecture)
- **Database:** MySQL/MariaDB (via `mysqli`, NOT PDO)
- **Frontend:** Vanilla JS + Vue 3 (CDN) + Plyr.js + HLS.js + Tailwind CSS (CDN) + Shadcn-Vue Dark-First Design System
- **Server:** Nginx + PHP-FPM (unix socket)
- **No Composer autoloader** — all classes loaded via `glob()` in bootstrap
- **No build step** — no npm, no webpack, no Vite

---

## 2. Server Environment

> **Full server documentation:** See `AUTODEPLOY.md` for complete setup instructions, disaster recovery guide, and server configuration details.

| Component | Detail |
|-----------|--------|
| OS | Linux VPS |
| Web Server | Nginx (port 80) |
| PHP | 8.5.4 (FPM, unix socket `/run/php/php8.5-fpm.sock`) |
| Database | MySQL/MariaDB |
| DB Host | `127.0.0.1` |
| DB User | `arsip` |
| DB Password | Set in `/etc/php/8.5/fpm/pool.d/www.conf` as `env[DB_PASS]` |
| DB Name | `arsip_layar` |
| App Root | `/var/www/arsip-layar` |
| Document Root | `/var/www/arsip-layar/public` |
| Nginx Config | `/etc/nginx/sites-enabled/arsip-layar` |
| Media Storage | `/var/www/arsip-layar/media/` (denied by nginx, served via PHP) |
| Backups | `/var/www/arsip-layar/storage/backups/` |
| Firewall | UFW (ports 22, 80, 443) |
| Fail2ban | SSH + Nginx brute-force |
| Systemd Service | `arsip-hls-worker.service` — job queue runner daemon (auto-restart) |
| Systemd Timer | `arsip-health-check.timer` — health check every 10 min (video integrity monitor) |
| Systemd Env | `/etc/arsip-layar/env` — DB credentials for CLI services |

### Nginx Routing Rules

```
/media/*           → DENIED (403) — never serve raw media
/protected-media/* → rewrite to /index.php?page=media&path=... (PHP serves with session check)
/assets/*          → static files with 84600s cache
/                  → try_files → /index.php?$query_string (front controller)
```

---

## 3. Directory Structure (Comprehensive Sitemap)

```
arsip-layar/
│
├── public/                          ← Document root (nginx serves from here)
│   ├── index.php                    ← Front controller (55 lines — ALL web requests)
│   ├── api.php                      ← API entry point for vue_enhance.js fetch calls
│   ├── sw.js                        ← Service worker (PWA, cache-first static assets)
│   ├── plyr.svg                     ← Plyr icon sprite
│   └── assets/
│       ├── css/
│       │   └── style.css            ← Main stylesheet (~1200 lines, Shadcn Zinc dark-first, mobile-first)
│       └── js/
│           └── vue_enhance.js       ← Main frontend JS (~1400 lines, Vue components + vanilla)
│
├── app/                             ← Core application layer (auto-loaded by bootstrap)
│   ├── bootstrap.php                ← Init: constants, helpers, DB, models, services, session, security
│   ├── helpers.php                  ← 30+ pure functions: e(), csrf, setting, totp, rate_limit, URL generators
│   ├── Database/
│   │   └── Connection.php           ← DB singleton + prepared statement helpers (selectOne/All, insert, execute)
│   ├── Http/
│   │   ├── Request.php              ← Wraps $_GET, $_POST, $_FILES, $_SERVER (page(), op(), files(), jsonBody())
│   │   └── Response.php             ← JSON, redirect, view, download, serveMedia, error, maintenance helpers
│   ├── Middleware/
│   │   ├── AuthMiddleware.php       ← requireAdmin(), isAdmin()
│   │   ├── CsrfMiddleware.php       ← validate(), validateApi() (supports JSON body CSRF)
│   │   └── RateLimitMiddleware.php  ← File-based rate limiter (check, enforce, checkLogin)
│   ├── Models/                      ← 11 thin DB models (auto-loaded via glob)
│   │   ├── AccessToken.php          ← Token CRUD, verify, toggle, expiry, generateUnique()
│   │   ├── ActivityLog.php          ← Admin activity logging (record, getRecent)
│   │   ├── Admin.php                ← Admin CRUD, 2FA enable/disable, findByEmail/Id
│   │   ├── AnalyticsEvent.php       ← Event recording, metrics, heatmap, retention, device breakdown
│   │   ├── Category.php             ← Category CRUD (create, delete with reassign)
│   │   ├── LoginAttempt.php         ← Failed login tracking (record, recentFailedCount, getRecentFailed)
│   │   ├── PaymentOrder.php         ← Midtrans order CRUD, FOR UPDATE locking, transaction handling
│   │   ├── Setting.php              ← Key-value settings (cached, get/set/getMany)
│   │   ├── Video.php                ← Video CRUD, view counting, file deletion, status updates
│   │   ├── VideoHeatmap.php         ← Per-second engagement tracking (batch upsert, getForVideo)
│   │   └── WebhookRetry.php         ← Failed webhook retry queue (create, getPending, updateResult)
│   └── Services/                    ← 8 business logic services (auto-loaded via glob)
│       ├── Auth.php                 ← Login (rate-limited, 2FA), logout, profile, password, 2FA setup
│       ├── AnalyticsService.php     ← Event recording, insights aggregation, heatmap data
│       ├── BackupService.php        ← mysqldump + gzip backup creation, listing, pruning
│       ├── MediaService.php         ← Media serving with access control, HLS info, download
│       ├── MidtransPayment.php      ← Checkout creation (Snap API), webhook verification, retry processing
│       ├── TelegramNotifier.php     ← Bot config, test messages, video notifications, chat ID detection
│       ├── TokenManager.php         ← Token CRUD, verification, payment auto-issue
│       └── VideoUpload.php          ← Multi-file upload, validation, preview gen, HLS queue, stale cleanup
│
├── controllers/                     ← 11 route handlers (auto-loaded via glob)
│   ├── AdminController.php          ← Admin panel rendering, saveSettings(), saveContact()
│   ├── AuthController.php           ← loginForm(), login(), logout(), 2FA, profile, password
│   ├── ContactController.php        ← Public contact page (reads settings from DB)
│   ├── MediaController.php          ← serve() (poster/preview/protected), download()
│   ├── PaymentController.php        ← saveSettings(), webhook(), processRetries(), getStatus()
│   ├── SettingsApiController.php    ← API: state, theme, watermark, uploadLimit, maintenance, backup
│   ├── TelegramApiController.php    ← API: getConfig, save, test, updates
│   ├── AnalyticsApiController.php   ← API: recordEvent, recordHeatmap, getInsights, getVideoHeatmap
│   ├── TokenController.php          ← verify(), revoke(), create(), toggle(), delete()
│   ├── VideoController.php          ← upload(), delete(), addCategory(), deleteCategory()
│   └── WatchController.php          ← show() — loads video data + HLS info + renders watch.php
│
├── routes/                          ← Routing files (dispatched by front controller)
│   ├── web.php                      ← ?page= → controller (HTML pages + form POSTs, ~50 routes)
│   ├── api.php                      ← ?op= → controller (JSON API responses, ~30 ops)
│   └── webhook.php                  ← External callbacks (Midtrans notify only)
│
├── views/                           ← PHP templates (no template engine)
│   ├── layouts/
│   │   ├── head.php                 ← <head> tag: CSS, JS CDN, Tailwind config, CSP
│   │   ├── header.php               ← Top nav bar (brand, nav links, admin/logout)
│   │   └── footer.php               ← Service worker registration, </body></html>
│   ├── pages/
│   │   ├── home.php                 ← Gallery: hero section, category filters, video cards grid
│   │   ├── watch.php                ← Player (unlocked) or 15s preview + token gate modal
│   │   └── contact.php              ← Contact page (Telegram, WhatsApp, Email cards)
│   ├── admin/
│   │   ├── index.php                ← Admin panel: sidebar + 7 tabs (content through payments)
│   │   ├── tabs/                    ← (empty — tabs are inline in admin/index.php)
│   │   └── components/              ← (empty — components are in vue_enhance.js)
│   └── auth/
│       └── login.php                ← Login form with 2FA field
│
├── cli/                             ← CLI scripts (standalone, not web-accessible)
│   ├── run_jobs.php                 ← Job queue runner (--daemon, --stats modes)
│   └── notify_video_ready.php       ← Called by HLS worker after transcode — sends Telegram notification
│
├── lib/                             ← Standalone libraries (no autoloader)
│   └── Telegram.php                 ← Telegram Bot API helper (native cURL, sendMessage/sendPhoto/getUpdates)
│
├── media/                           ← Video storage (denied by nginx, served via PHP)
│   └── {slug}/                      ← One directory per video
│       ├── source.mp4               ← Original uploaded file
│       ├── poster.jpg               ← Auto-generated poster frame (ffprobe timestamp)
│       ├── preview.mp4              ← 15-second preview clip (h264, faststart)
│       ├── master.m3u8              ← HLS master playlist (auto-selected by player)
│       ├── 360p.m3u8                ← HLS 360p rendition playlist
│       ├── 720p.m3u8                ← HLS 720p rendition playlist
│       ├── 360p_000.ts ...          ← HLS 360p segments
│       └── 720p_000.ts ...          ← HLS 720p segments
│
├── storage/
│   ├── backups/                     ← Database backup files (.sql.gz)
│   ├── cache/
│   │   └── ratelimit/               ← File-based rate limiter state (JSON per key)
│   └── uploads/                     ← Temp chunked upload directories (auto-cleaned)
│
├── systemd/
│   └── arsip-hls-worker.service     ← Systemd service for job queue runner daemon
│
├── sandbox/                         ← Laravel project scaffold (UNUSED — migration experiment, not active)
│
├── config.php                       ← Legacy monolith config — still used by root index.php
├── index.php                        ← Legacy monolith entry point (997 lines) — NOT used by nginx
├── api.php                          ← Legacy monolith API — NOT used by nginx
├── schema.sql                       ← Database schema (idempotent CREATE TABLE IF NOT EXISTS)
├── style.css                        ← Legacy CSS (copy of public/assets/css/style.css)
├── vue_enhance.js                   ← Legacy JS (copy of public/assets/js/vue_enhance.js)
├── deploy.sh                        ← VPS deployment script (SSH-based, installs nginx/PHP/MySQL)
├── nginx-new-site.conf              ← Reference nginx config for new VPS setup
├── nginx-arsip-limits.conf          ← Nginx rate-limit zone definitions
├── sw.js                            ← Service worker (root copy, legacy)
└── arsip-layar-full-backup.zip      ← Full backup archive
```

### Sitemap Summary

| Layer | Count | Auto-loaded? |
|-------|-------|-------------|
| Models | 11 | Yes (`glob()` in bootstrap) |
| Services | 8 | Yes (`glob()` in bootstrap) |
| Controllers | 11 | Yes (`glob()` in bootstrap) |
| Middleware | 3 | Yes (`glob()` in bootstrap) |
| Views | 8 templates | No (required explicitly by controllers/routes) |
| Routes | 3 files | No (required explicitly by front controller) |
| CLI scripts | 2 | No (standalone, run via `php cli/...`) |
| Lib | 1 | No (standalone, required by CLI scripts) |

---

## 4. Request Flow

```
Browser Request
    │
    ▼
Nginx (port 80)
    │
    ├── /media/* → 403 DENIED
    ├── /protected-media/* → rewrite to /index.php?page=media&path=...
    ├── /assets/* → static file (cache 84600s)
    ├── *.php → PHP-FPM
    │
    ▼
public/index.php (Front Controller)
    │
    ├── require bootstrap.php
    │   ├── Define constants (APP_ROOT, MEDIA_ROOT, etc.)
    │   ├── Load helpers.php (pure functions)
    │   ├── Load Connection.php (DB singleton)
    │   ├── glob Models/*.php → load all 11 models
    │   ├── glob Services/*.php → load all 8 services
    │   ├── Load Request.php, Response.php
    │   ├── glob Middleware/*.php → load all 3 middleware
    │   ├── glob controllers/*.php → load all 11 controllers
    │   ├── Session init (hardened: SameSite=Strict, HttpOnly, UA-binding, idle timeout)
    │   ├── Security headers (CSP, HSTS, X-Frame-Options, etc.)
    │   └── DB singleton init
    │
    ├── Create Request object
    ├── Maintenance guard check
    │
    ├── Route decision:
    │   ├── page=midtrans-notify POST → routes/webhook.php → exit
    │   ├── op=? (non-empty) → routes/api.php (JSON) → exit
    │   └── otherwise → routes/web.php (HTML) → exit if page != 'home'
    │
    └── Default: render home page (gallery)
```

### API Flow

```
vue_enhance.js → fetch('api.php?op=state')
    → public/api.php → bootstrap → routes/api.php → Controller → JSON response
```

### Media Serving Flow

```
Browser requests /protected-media/slug/720p.m3u8
    → Nginx rewrites to /index.php?page=media&path=/protected-media/slug/720p.m3u8
    → public/index.php → bootstrap → routes/web.php → MediaController::serve()
    → MediaService::serve() checks access (has_access() || admin())
    → If authorized: Response::serveMedia() → readfile() → exit
    → If denied: 403 error
```

### CLI Notification Flow

```
arsip-hls-worker (external binary) finishes HLS transcode
    → calls: php cli/notify_video_ready.php {slug}
    → cli/notify_video_ready.php
        → Loads lib/Telegram.php
        → Queries video info from DB
        → Sends poster photo + caption via Telegram Bot API
        → Logs to activity_log table
```

---

## 5. Database Schema (12 Tables)

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `admins` | Admin accounts | id, name, email, password (Argon2id), totp_secret, totp_enabled, last_login_at/ip, active |
| `categories` | Video categories | id, name (unique) |
| `videos` | Video records | id, title, slug (unique), category_id, poster, source, duration_sec, size_bytes, views, status |
| `settings` | Key-value config | name (PK), value |
| `access_tokens` | Viewership tokens | id, token (unique, XXXX-XXXX-XXXX), label, contact_type, contact_value, status, created_by, use_count, expires_at |
| `payment_orders` | Midtrans orders | id, order_id (unique), buyer_name, buyer_contact, amount, status, snap_token, access_secret_hash, token_id |
| `analytics_events` | Page/video events | id, event, path, visitor_hash, video_id, progress_sec, device, browser, referrer |
| `video_heatmap` | Per-second engagement | video_id, viewer_hash, second_index (unique combo), view_count |
| `login_attempts` | Failed login tracking | ip, email, success, reason |
| `activity_log` | Admin action audit | admin_id, action, detail, ip |
| `backups` | Backup file records | file, size_bytes |
| `webhook_retry` | Failed webhook queue | source, payload, status, attempts, max_attempts, next_retry_at, last_error |

### Settings Keys

| Key | Default | Purpose |
|-----|---------|---------|
| `site_name` | Arsip Layar | Site title |
| `site_description` | Platform berbagi... | Site description |
| `theme_key` | dark | Active theme (dark-first, single Shadcn theme) |
| `maintenance_mode` | 0 | 1 = maintenance mode active |
| `upload_max_mb` | 2048 | Max upload size per file |
| `watermark_text` | Codename F | Video watermark text |
| `watermark_position` | br | Watermark position (tl/tr/bl/br/center) |
| `watermark_opacity` | 60 | Watermark opacity (0-100) |
| `telegram_bot_token` | (empty) | Telegram bot API token |
| `telegram_chat_id` | (empty) | Telegram notification chat ID |
| `telegram_enabled` | 0 | 1 = send notifications |
| `midtrans_enabled` | 0 | 1 = enable payment |
| `midtrans_mode` | sandbox | sandbox or production |
| `midtrans_client_key` | (empty) | Midtrans client key |
| `midtrans_server_key` | (empty) | Midtrans server key |
| `midtrans_token_price` | 50000 | Token price in IDR |
| `contact_title` | Hubungi Admin | Contact page title |
| `contact_subtitle` | Pilih platform... | Contact page subtitle |
| `contact_telegram` | (empty) | Telegram link |
| `contact_whatsapp` | (empty) | WhatsApp link |
| `contact_email` | (empty) | Email address |
| `cache_ver` | 1 | Cache busting version |

---

## 6. Routing Reference

### Web Routes (`?page=`)

| Page | Method | Controller | Auth | Description |
|------|--------|------------|------|-------------|
| (home) | GET | inline (front controller) | No | Gallery with video cards |
| `login` | GET/POST | AuthController | No | Login form / handler |
| `logout` | GET | AuthController | Admin | Destroy session |
| `admin` | GET | AdminController | Admin | Admin panel (7 tabs) |
| `watch` | GET | WatchController | No* | Video player (preview or full) |
| `contact` | GET | ContactController | No | Contact page |
| `media` | GET | MediaController | Session** | Protected media serving |
| `poster` | GET | MediaController | No | Poster images (public) |
| `preview` | GET | MediaController | No | 15s preview clips (public) |
| `download` | GET | MediaController | Session** | Video MP4 download |
| `verify-token` | POST | TokenController | No | Verify access token |
| `revoke-access` | POST | TokenController | No | Revoke session access (CSRF-protected) |
| `save-settings` | POST | AdminController | Admin | Save site name/description |
| `save-contact` | POST | AdminController | Admin | Save contact page settings |
| `upload` | POST | VideoController | Admin | Upload video(s) |
| `delete-video` | POST | VideoController | Admin | Delete video |
| `add-category` | POST | VideoController | Admin | Add category |
| `delete-category` | POST | VideoController | Admin | Delete category |
| `token-create` | POST | TokenController | Admin | Create access token |
| `token-toggle` | POST | TokenController | Admin | Toggle token active/suspended |
| `token-delete` | POST | TokenController | Admin | Delete token |
| `account-update` | POST | AuthController | Admin | Update profile |
| `password-change` | POST | AuthController | Admin | Change password |
| `save-midtrans` | POST | PaymentController | Admin | Save Midtrans settings |

\* Watch page shows preview player for guests, full player for token holders/admins.
\** "Session" = `has_access() || admin()` — token verified or admin logged in.

### API Routes (`?op=`)

All API routes return JSON. Admin-only routes use `AuthMiddleware::requireAdmin()`.

| Op | Method | Controller | Auth | Description |
|----|--------|------------|------|-------------|
| `state` | GET | SettingsApiController | No | Public state (theme, site name, CSRF, admin status) |
| `event` | POST | AnalyticsApiController | No | Record analytics event |
| `heatmap` | POST | AnalyticsApiController | No | Record video engagement heatmap |
| `insights` | GET | AnalyticsApiController | Admin | Get analytics dashboard data |
| `heatmap_data` | GET | AnalyticsApiController | Admin | Get per-video heatmap |
| `theme` | POST | SettingsApiController | Admin | ~~Change theme~~ (deprecated — dark mode is permanent) |
| `watermark_get` | GET | SettingsApiController | Admin | Get watermark settings |
| `watermark` | POST | SettingsApiController | Admin | Save watermark settings |
| `upload_limit` | POST | SettingsApiController | Admin | Save upload limit |
| `maintenance` | POST | SettingsApiController | Admin | Toggle maintenance mode |
| `cache_bust` | POST | SettingsApiController | Admin | Increment cache version |
| `backup` | POST | SettingsApiController | Admin | Create database backup |
| `backup_list` | GET | SettingsApiController | Admin | List all backups |
| `backup_download` | GET | SettingsApiController | Admin | Download backup file |
| `telegram_get` | GET | TelegramApiController | Admin | Get Telegram config |
| `telegram_save` | POST | TelegramApiController | Admin | Save Telegram settings |
| `telegram_test` | POST | TelegramApiController | Admin | Send test message |
| `telegram_updates` | GET | TelegramApiController | Admin | Fetch chat ID candidates |
| `token_list` | GET | TokenManager | Admin | List all tokens |
| `token_create` | POST | TokenManager | Admin | Create token (JSON body) |
| `token_toggle` | POST | TokenManager | Admin | Toggle token status |
| `token_edit` | POST | TokenManager | Admin | Update token info |
| `token_delete` | POST | TokenManager | Admin | Delete token |
| `2fa_setup` | GET | Auth | Admin | Generate 2FA secret + QR |
| `2fa_enable` | POST | Auth | Admin | Enable 2FA with code |
| `2fa_disable` | POST | Auth | Admin | Disable 2FA |
| `activity` | GET | ActivityLog + LoginAttempt | Admin | Get activity log |
| `upload_init` | POST | VideoController | Admin | Initialize chunked upload session |
| `upload_chunk` | POST | VideoController | Admin | Upload a single 5MB chunk |
| `upload_complete` | POST | VideoController | Admin | Assemble chunks and process video |
| `upload_status` | GET | VideoController | Admin | Check uploaded chunks (for resume) |
| `upload_abort` | POST | VideoController | Admin | Cancel upload and cleanup temp files |
| `midtrans_checkout` | POST | MidtransPayment | No | Create Midtrans Snap checkout |
| `midtrans_orders` | GET | MidtransPayment | Admin | List payment orders |
| `payment_status` | GET | PaymentController | No | Check payment status |
| `process_webhook_retries` | POST | MidtransPayment | Admin | Retry failed webhooks |

### Webhook Routes

| Page | Method | Handler | Description |
|------|--------|---------|-------------|
| `midtrans-notify` | POST | MidtransPayment::handleWebhook | Midtrans payment notification callback |

---

## 7. Architecture Details

### 7.1 MVC Pattern (No Framework)

- **Models** (`app/Models/`): Thin DB wrappers. Each model holds a `Connection` instance and provides CRUD methods. Models use `$this->conn->selectOne()`, `$this->conn->insert()`, `$this->conn->execute()` with explicit type strings for `bind_param`.
- **Views** (`views/`): Plain PHP templates. No template engine. Use `e()` for HTML escaping.
- **Controllers** (`controllers/`): Handle HTTP logic. Call middleware for auth/CSRF. Delegate to services.
- **Services** (`app/Services/`): Business logic. Compose models. Handle validation, external APIs, file operations.

### 7.2 Database Layer

The `Connection` class is a singleton wrapping `mysqli`:

```php
$conn = Connection::getInstance();

// Simple queries
$row = $conn->selectOne('SELECT * FROM admins WHERE id=?', [$id], 'i');
$rows = $conn->selectAll('SELECT * FROM categories ORDER BY name');
$id = $conn->insert('INSERT INTO categories(name) VALUES(?)', [$name], 's');
$affected = $conn->execute('DELETE FROM categories WHERE id=?', [$id], 'i');

// Transactions
$conn->beginTransaction();
// ... queries ...
$conn->commit(); // or $conn->rollback();

// Raw mysqli (escape hatch)
$db = $conn->db();
$result = $db->query('SELECT ...');
```

**Type string rules for `bind_param`:**
- `s` = string (also used for NULL)
- `i` = integer
- `d` = float
- Type string length MUST match parameter count exactly

### 7.3 Session Security

| Feature | Implementation |
|---------|---------------|
| Cookie | SameSite=Strict, HttpOnly, Secure (on HTTPS) |
| Session name | `ARSIP_SID` |
| UA binding | `$_SESSION['_ua_bind']` = hash(UA + seed) — session destroyed if UA changes |
| Idle timeout | Admin sessions expire after 30 minutes of inactivity |
| CSRF | `$_SESSION['csrf']` = random 48-char hex, validated on all POST requests |
| Regeneration | `session_regenerate_id(true)` on login |

### 7.4 Authentication

- **Password hashing:** Argon2id (memory_cost=65536, time_cost=4, threads=2)
- **Auto-upgrade:** If password hash uses weaker algo, it's rehashed on successful login
- **2FA:** TOTP (RFC 6238) compatible with Google Authenticator, Authy, etc.
- **Rate limiting:** 8 failed logins per 15 minutes per IP
- **Login attempts:** Logged to `login_attempts` table with IP, email, success, reason

### 7.5 Access Token System

Tokens follow the format `XXXX-XXXX-XXXX` (12 chars, no ambiguous chars I/O/0/1).

- Tokens have a **30-day expiry** (`expires_at` column)
- Admin can create, toggle (active/suspended), delete tokens
- Token verification checks: valid token string + not expired + status=active
- On verification: `grant_access_with_token()` sets `$_SESSION['access_granted'] = true` + stores token metadata (ID, label, created_at, expires_at) in session
- Token info is displayed in mobile burger menu dropdown (label + creation date)
- Midtrans payments **auto-issue tokens** on settlement

### 7.6 Video Upload Pipeline

#### Traditional Upload (single request)
```
1. User selects MP4 file(s) via <input type="file" multiple>
2. VideoController::upload() → CsrfMiddleware::validate() → AuthMiddleware::requireAdmin()
3. VideoUpload::processOne() per file:
   a. Validate: extension (.mp4), size, MIME (finfo + ftyp header check)
   b. Generate slug: sanitize(title) with leading/trailing hyphens stripped, fallback 'video' for empty slugs
   c. Create directory: media/{slug}/
   d. move_uploaded_file → media/{slug}/source.mp4
   e. Probe duration with ffprobe
   f. Insert DB record via Video::create() (status=processing)
   g. Fire arsip-hls-worker via setsid nohup (background, fire-and-forget)
      → Worker handles: poster → preview.mp4 → HLS transcode → status='ready' → Telegram notify
4. Redirect to admin panel
```

#### Bulk Chunked Upload (resumable, up to 4 files)
```
1. User selects 1-4 MP4 files via <input type="file" multiple>
2. Frontend calls op=upload_init per file → gets upload_id + chunk_size (5MB)
3. Frontend calls op=upload_status → detects already-uploaded chunks (resume)
4. Frontend uploads remaining chunks sequentially via XHR:
   a. Each chunk: 5MB blob → POST api.php?op=upload_chunk (FormData + CSRF)
   b. Server saves to: storage/uploads/{upload_id}/chunk_NNNNNN
   c. Progress tracked per-file, per-chunk
5. On last chunk: frontend calls op=upload_complete
   a. Backend: concatenate chunks → media/{slug}/source.mp4
   b. Same pipeline as traditional: ffprobe → DB insert → HLS worker
   c. Cleanup: storage/uploads/{upload_id}/ removed
6. Upload state persisted to localStorage for resume across page reloads
```

**Note:** The job queue (`job_queue` table + `cli/run_jobs.php`) exists as a secondary consumer but is NOT the primary processing path. See `audit.md` Section 9 for why the job queue approach was deprecated as the primary path.

### 7.7 Video Playback

**Unlocked (token verified or admin):**
- Plyr.js player with HLS.js adaptive streaming
- Quality picker: Auto / 720p / 360p
- Watermark overlay (configurable text, position, opacity)
- Download button

**Preview (guest, no token):**
- 15-second preview clip (`preview.mp4`)
- Auto-pauses at 15 seconds
- Overlay appears with "Masukkan Token" + "Beli Akses Token" buttons
- Token modal for verification or Midtrans purchase

### 7.8 Payment Flow (Midtrans)

```
1. User clicks "Beli via Midtrans" in token modal
2. JS calls api.php?op=midtrans_checkout (POST, name + contact)
3. MidtransPayment::createCheckout():
   a. Create payment_orders record (status=pending)
   b. Call Midtrans Snap API → get snap_token
   c. Return snap_token to JS
4. JS opens Midtrans Snap popup
5. User completes payment
6. Midtrans sends webhook to ?page=midtrans-notify
7. MidtransPayment::handleWebhook():
   a. Verify signature (SHA512)
   b. Update order status
   c. On settlement: auto-issue access_token (30-day expiry)
   d. Return 200 OK
8. On failure: webhook_retry table + exponential backoff (5m → 15m → 45m → 2h → 6h)
```

### 7.9 Analytics System

- **Events:** page_view, video_start, video_progress, video_complete
- **Heatmap:** Per-second tracking of which video seconds are watched
- **Retention:** Average watch time per video / total duration
- **Metrics:** Unique visitors, page views, video plays, device breakdown
- **Insights dashboard:** 7/30/90 day views with heatmap grid (hour × day-of-week)

### 7.10 Frontend Architecture

- **Vue 3 (CDN):** Used for reactive admin panels (analytics, security, system, watermark, telegram, tokens)
- **Plyr.js:** Media player with custom controls
- **HLS.js:** Adaptive bitrate streaming for HLS playlists
- **Tailwind CSS (CDN):** Utility-first CSS
- **Shadcn-Vue Design System:** Dark-first Zinc theme (New York style), single permanent dark mode
- **Service Worker:** PWA caching (offline support)
- **No build step:** All JS is vanilla + Vue CDN, loaded directly

### 7.11 UI Theme (Shadcn Dark-First)

**Single permanent dark theme** using Shadcn-Vue Zinc color palette (New York style).

- `html.dark` class applied at root for dark mode
- CSS custom properties in `:root` define the Shadcn Zinc tokens (`--canvas`, `--surface`, `--card`, `--accent`, etc.)
- All 4 legacy themes (ivory, obsidian, emerald, prestige) have been **removed**
- Theme switcher UI (`.vue-atelier`) has been **removed**
- Accent color is now Shadcn Blue (`#3b82f6`) for primary actions

| Token | Value | Purpose |
|-------|-------|---------|
| `--canvas` | `#09090b` | Page background |
| `--surface` / `--card` | `#18181b` | Card/panel backgrounds |
| `--elev` | `#27272a` | Elevated surfaces |
| `--accent` | `#3b82f6` | Primary action color |
| `--destructive` | `#ef4444` | Error/danger actions |
| `--muted` | `#a1a1aa` | Secondary text |

To add new Shadcn-Vue components, create the appropriate HTML structure using these CSS variables. No npm/build step required — all styling is via CSS custom properties in `style.css`.

### 7.12 Telegram CLI Notifier

The `cli/notify_video_ready.php` script is called by the external `arsip-hls-worker` binary after HLS transcoding completes. It:
1. Loads `lib/Telegram.php` (standalone Telegram Bot API wrapper)
2. Queries video info from DB
3. Sends poster photo + caption with watch link via Telegram Bot API
4. Logs the notification to `activity_log`

This is a **fire-and-forget CLI script** — not a web request, not queued.

---

## 8. Key Functions Reference

### `app/helpers.php` — Pure Functions

| Function | Purpose |
|----------|---------|
| `e(string)` | HTML escape (`htmlspecialchars`) |
| `csrf()` | Get/generate CSRF token |
| `check_csrf()` | Validate CSRF from POST (exit 419 on fail) |
| `admin()` | Check if admin is logged in |
| `need_admin()` | Redirect to login if not admin |
| `go(url)` | Redirect and exit |
| `client_ip()` | Get client IP (truncated to 64 chars) |
| `setting(db, key, fallback)` | Get setting value (in-memory cached) |
| `set_setting(db, key, value)` | Upsert setting value |
| `log_activity(db, admin_id, action, detail)` | Log admin activity |
| `log_login_attempt(db, email, ok, reason)` | Log login attempt |
| `recent_failed_logins(db, ip, minutes)` | Count recent failed logins |
| `password_new(plain)` | Hash with Argon2id |
| `password_needs_upgrade(hash)` | Check if hash needs rehash |
| `generate_token(length)` | Generate random token string |
| `has_access()` | Check session access grant |
| `grant_access()` | Set session access grant |
| `grant_access_with_token(db, tokenId)` | Set session access grant + store token metadata (label, dates) |
| `revoke_access()` | Remove session access grant + clear token metadata |
| `protected_media_url(path)` | Generate /protected-media/ URL |
| `poster_url(path)` | Generate ?page=poster URL |
| `preview_url(path)` | Generate ?page=preview URL |
| `midtrans_endpoint(mode)` | Get Midtrans API URL |
| `midtrans_snap_url(mode)` | Get Midtrans Snap JS URL |
| `maintenance_guard(db)` | Block non-admins during maintenance |
| `rate_limit(key, max, window)` | File-based rate limiter |
| `csp_nonce()` | Get CSP nonce for inline scripts |
| `totp_code(secret, time, period, digits)` | Generate TOTP code |
| `totp_verify(secret, code, window)` | Verify TOTP code |
| `base32_encode/decode(string)` | Base32 for TOTP secrets |

### URL Generators

```php
protected_media_url('media/slug/source.mp4')
// → /protected-media/slug/source.mp4

poster_url('media/slug/poster.jpg')
// → ?page=poster&path=slug%2Fposter.jpg

preview_url('media/slug/source.mp4')
// → ?page=preview&path=slug%2Fpreview.mp4
```

---

## 9. Security Measures

| Layer | Implementation |
|-------|---------------|
| **CSP** | Strict Content-Security-Policy header (self, specific CDNs) |
| **HSTS** | Strict-Transport-Security (HTTPS only) |
| **X-Frame-Options** | DENY (no iframe embedding) |
| **CSRF** | Token per-session, validated on all POST forms and API calls |
| **XSS** | `e()` function for all HTML output, `htmlspecialchars` |
| **SQL Injection** | Prepared statements with `bind_param` throughout |
| **Session Hijack** | UA-binding hash, session regeneration on login |
| **Brute Force** | Rate limiting (8 failed/15min/IP), login attempt logging |
| **2FA** | TOTP (RFC 6238) optional per admin |
| **Password** | Argon2id with auto-upgrade |
| **Upload** | Extension check, MIME validation, ftyp header check, size limit |
| **Media** | /media/ denied by nginx, served through PHP with access checks |
| **Maintenance** | Mode toggle blocks non-admins with 503 |
| **File Permissions** | Media dirs 0750, backups 0640 |

---

## 10. Known Issues & Gotchas

### 10.1 Entry Point

The project has ONE entry point:
- **`public/index.php`** (55 lines) — MVC entry point. This is what nginx uses.

**When modifying routes or controllers, only modify the `public/` + `app/` + `controllers/` + `routes/` + `views/` files.**

### 10.2 Bootstrap

- **`app/bootstrap.php`** — Used by the MVC architecture
- **`app/helpers.php`** — 30+ pure functions: `e()`, `csrf()`, `setting()`, `totp`, `rate_limit`

### 10.3 Media Serving

Media files are **never served directly by nginx**. All media requests go through PHP:
- `/protected-media/*` → nginx rewrites to `?page=media&path=...`
- `?page=poster&path=...` → PHP serves poster images (public)
- `?page=preview&path=...` → PHP serves preview clips (public)
- `?page=media&path=...` → PHP serves protected media (token/admin required)

**Slug format constraint:** Media delivery regex (`MediaService::ALLOWED_PATTERN`) requires slugs to match `^[a-z0-9-]+/...`. Slug generation now strips leading/trailing hyphens and collapses consecutive hyphens to ensure compatibility. See `audit.md` Section 13 for the full investigation.

### 10.4 Background Jobs

**Primary path (current):** `VideoUpload::processOne()` fires `arsip-hls-worker` directly via `setsid nohup`:
- The worker handles the entire pipeline: poster → preview clip → HLS transcode → status update (`processing→ready`) → Telegram notification
- Runs as an orphaned background process (fire-and-forget)
- The systemd service `arsip-hls-worker.service` provides a secondary job queue consumer for any remaining queue jobs

**Health check cron:** The `arsip-health-check.timer` runs `cli/health_check.php` every 10 minutes to detect and auto-fix video issues:
- H1: Stuck processing (>30 min) → re-fire worker
- H2: Invalid slugs (leading hyphens) → auto-rename
- H3: Missing HLS files → re-fire worker
- H4: Missing poster → re-fire worker
- H5: Unplayable video (slug fails regex) → auto-rename
- H6: Low disk space (<1GB) → log warning
- H7: Stale upload sessions (>24h) → auto-cleanup
- Alerts via Telegram for critical issues, logs to `/var/log/arsip-health-check.log` and `activity_log` table

**Legacy path (deprecated):** The `job_queue` table and `cli/run_jobs.php` exist as a backup mechanism but are NOT the primary processing path. The job queue previously caused videos to get stuck at `processing` because no consumer was running — see `audit.md` Section 9 for the full investigation.

### 10.5 CSRF for API Calls

API calls from `vue_enhance.js` use `FormData` with a `csrf` field. The CSRF token is fetched via `api.php?op=state` on page load and stored in JS state.

### 10.6 public/api.php — Composer Autoloader Dependency

`public/api.php` loads `vendor/autoload.php`, but **no `composer.json` or `vendor/` directory exists at the project root**. This file may fail if called directly without a pre-existing vendor directory. The main `public/index.php` does NOT use Composer.

### 10.7 Sandbox Directory

The `sandbox/` directory contains a Laravel project scaffold (artisan, composer.json, app/, routes/, etc.). This is **completely unused** — likely an experiment or migration plan that was never completed. It can be safely ignored or deleted.

### 10.8 Dual Token API Paths

Token management has two API paths:
1. **`?op=token_list/create/toggle/delete`** → routes directly to `TokenManager` service in `routes/api.php`
2. **`?page=token-create/toggle/delete`** → routes to `TokenController` in `routes/web.php` (form-based fallback)

The `vue_enhance.js` token manager uses the `?op=` JSON API path.

### 10.9 Telegram Notification — Two Implementations

There are two separate Telegram notification implementations:
1. **`app/Services/TelegramNotifier.php`** — Used by the admin panel API (test messages, config, updates detection)
2. **`cli/notify_video_ready.php` + `lib/Telegram.php`** — Standalone CLI script called by the HLS worker after transcode

They use different code but target the same Telegram Bot API. `lib/Telegram.php` is a simpler, more focused wrapper.

### 10.10 Rate Limiting — Two Approaches

1. **`app/Middleware/RateLimitMiddleware.php`** — Class-based, used by `AnalyticsApiController` for event recording
2. **`rate_limit()` function in `app/helpers.php`** — Function-based, identical logic, available globally

Both use the same file-based approach (`storage/cache/ratelimit/*.json`).

### 10.11 Chunked Upload — Temp Storage & Resume

The bulk upload system uses temporary chunk storage in `storage/uploads/{upload_id}/`.

- **Chunk size:** 5MB (defined as `VideoUpload::CHUNK_SIZE`)
- **Max files:** 4 per batch (frontend enforcement)
- **Resume:** Upload state saved to `localStorage` (key: `arsip_upload_queue`). On page reload, `upload_status` API checks which chunks exist on server, skips already-uploaded ones. **Note:** `File` objects cannot be serialized to localStorage, so after page reload, the file must be re-selected by the user. Restored items show a "needs file" indicator.
- **Cleanup:** Temp directory removed automatically on `upload_complete` or `upload_abort`. Stale uploads (>24h) are auto-cleaned by health check H7.
- **Retry limit:** Max 3 retries per file. After max retries, the item is removed from the queue with a clear error message.
- **Abort protection:** Server writes `.aborted` flag file before cleanup. `saveChunk()` rejects writes if session was aborted, preventing race conditions.
- **Assembly validation:** Assembled file is validated (min 1KB, size match ±1KB tolerance) BEFORE DB insert to prevent corrupt files from entering the processing pipeline.
- **PHP limits:** The 5MB chunk size is well within PHP's `upload_max_filesize = 2G` and `post_max_size = 2G`. Each chunk upload completes in seconds even on slow connections.

### 10.12 CSS Load Order — Tailwind Forms Plugin Override

**Problem:** Tailwind CDN `<script>` dynamically injects a `<style>` tag at the END of `<head>`. This `<style>` has the same CSS specificity as our `<link>` stylesheet, but comes later in the DOM, so Tailwind's `@tailwind base` (with `forms` plugin) wins the cascade and resets all input colors to light-theme defaults.

**Why moving `<link>` doesn't help:** Even if `style.css` loads after the Tailwind `<script>`, the `<script>` injects `<style>` at runtime, always placing it AFTER any `<link>` tags in the DOM.

**Fix:** Add `!important` to input override rules at the end of `style.css`. This is the standard CSS reset approach to override framework defaults.

**Rule:** When using Tailwind CDN with custom CSS, always use `!important` on critical input/color overrides to break the DOM cascade tie.

---

## 11. Deployment

The `deploy.sh` script handles VPS deployment:
1. Install packages (nginx, PHP-FPM, ffmpeg, MySQL)
2. Configure UFW firewall (22, 80, 443)
3. Configure fail2ban
4. Create database + user
5. Deploy app to `/var/www/arsip-layar`
6. Configure nginx
7. Run migrations
8. Create admin user

### Manual Nginx Reload

```bash
nginx -t && systemctl reload nginx
```

### Database Access (CLI)

```bash
php -r "
\$db = new mysqli('127.0.0.1', 'arsip', 'PASSWORD', 'arsip_layar');
\$db->set_charset('utf8mb4');
// queries here...
"
```

---

## 12. File Permissions

| Path | Owner | Permissions | Purpose |
|------|-------|-------------|---------|
| `/var/www/arsip-layar` | www-data | 750 | App root |
| `media/*` | www-data | 750 | Video storage |
| `media/{slug}/*` | www-data | 644 | Video files |
| `storage/backups/*` | www-data | 640 | Backup files |
| `storage/cache/ratelimit/*` | www-data | 644 | Rate limit files |

---

## 13. CDN Dependencies

| Library | Version | Purpose |
|---------|---------|---------|
| Vue.js | 3.4.38 | Reactive admin panels |
| Plyr.js | 3.7.8 | Video player |
| HLS.js | 1.5.13 | Adaptive bitrate streaming |
| Tailwind CSS | latest (CDN) | Utility CSS |
| Material Symbols | latest | Icon font |
| Fraunces | Google Fonts | Display typography |
| Geist | Google Fonts | UI typography |
| JetBrains Mono | Google Fonts | Monospace (eyebrows) |

---

## 14. Frontend Linting (ESLint)

**Tool:** ESLint v10.8.1 (flat config format)
**Config:** `eslint.config.js`

| Plugin | Purpose |
|--------|---------|
| `eslint-plugin-vue` | Vue template rules for inline string templates |
| `eslint-plugin-vuejs-accessibility` | A11y rules (aria, keyboard, forms) |
| `eslint-plugin-tailwindcss` | Tailwind CSS class ordering |

**Commands:**
```bash
npm run lint          # Check for errors/warnings
npm run lint:fix      # Auto-fix fixable issues
npm run lint:report   # Generate JSON report to storage/
```

**Note:** The project has no `.vue` SFC files — all Vue components are inline string templates inside `public/assets/js/vue_enhance.js`. The ESLint config is tailored for this CDN-based architecture.

---

## 15. Quick Reference for AI Agents

> **Also read `rules.md`** for complete protocols (ESLint, CSS, Git, DB, Communication).
> **Also read `AUTODEPLOY.md`** for server setup and deployment instructions.

### Common Tasks

| Task | Files to Modify |
|------|-----------------|
| Add new admin tab | `views/admin/index.php` + `vue_enhance.js` + `routes/api.php` + new Controller |
| Add new page | `routes/web.php` + new Controller + new View |
| Add new API endpoint | `routes/api.php` + new Controller method |
| Add new model | `app/Models/NewModel.php` (auto-loaded by bootstrap glob) |
| Add new service | `app/Services/NewService.php` (auto-loaded by bootstrap glob) |
| Modify video upload | `app/Services/VideoUpload.php` + `controllers/VideoController.php` |
| Modify chunked upload | `routes/api.php` (API endpoints), `controllers/VideoController.php` (methods), `app/Services/VideoUpload.php` (chunk logic) |
| Modify video player | `views/pages/watch.php` + `public/assets/js/vue_enhance.js` |
| Modify theme colors | `public/assets/css/style.css` (Shadcn CSS custom properties in `:root`) |
| Add Shadcn component | Create HTML using CSS vars (`--surface`, `--accent`, etc.) in relevant view |
| Add database column | `schema.sql` + `ALTER TABLE` in migration |
| Add new unit test | `tests/Unit/NewTest.php` (extends PHPUnit TestCase) |

### Important Patterns

1. **All new files in `app/Models/`, `app/Services/`, `controllers/` are auto-loaded** via `glob()` in `bootstrap.php`. No manual registration needed.

2. **Type strings must match param count exactly.** When using `$conn->insert()` or `$conn->execute()` with explicit types, verify the type string character count equals the parameter array count.

3. **Controllers should call `AuthMiddleware::requireAdmin()` first** for admin-only actions, and `CsrfMiddleware::validate()` for POST requests.

4. **Services should use the `Connection` singleton** (`Connection::getInstance()`) and pass it to model constructors.

5. **Views are plain PHP** — use `e()` for escaping, `csrf()` for CSRF tokens, `setting()` for config values.

6. **Background tasks** fire `arsip-hls-worker` directly via `setsid nohup`. The worker handles the entire pipeline end-to-end (poster, preview, HLS, status update, Telegram). The `job_queue` table and `cli/run_jobs.php` exist as a secondary consumer but are NOT the primary path. See `audit.md` Section 9 for why the job queue approach failed.

7. **API responses always use `Response::json()`** — never `echo json_encode()` directly.

8. **Settings are cached in-memory** via the global `$_settings_cache` — calling `set_setting()` updates the cache immediately, so subsequent `setting()` calls within the same request return the fresh value.

9. **Unit tests use PHPUnit** and are located in `tests/Unit/`. Run with `vendor/bin/phpunit`. Tests do NOT access production database — they use mock data and static assertions.

10. **Adding new tests:** Create `tests/Unit/NewTest.php` with class extending `PHPUnit\Framework\TestCase`. Follow existing patterns in `tests/Unit/AuthTest.php` for reference.

---

## 15. Changelog

See [changelog.md](changelog.md) for a detailed history of all changes made to this codebase.

---

## 16. CI/CD Pipeline

GitHub Actions CI pipeline runs on every push to master and pull requests.

### Pipeline Jobs

| Job | Command | Rules.md Reference |
|-----|---------|-------------------|
| PHP Syntax | `php -l` on all files | 🔒3 Syntax Verification |
| ESLint | `npm run lint` | ESLint Protocol |
| PHPUnit Tests | `vendor/bin/phpunit` | Post-Task Checklist |
| Final Verification | Aggregates all checks | — |

### Commands

```bash
# Run PHP syntax check
find app/ controllers/ routes/ cli/ tests/ -name "*.php" -exec php -l {} \;

# Run ESLint
npm run lint

# Run PHPUnit tests
vendor/bin/phpunit
```

---

## 17. Static Analysis

### PHPStan

PHPStan performs static analysis on PHP code to find bugs and type errors.

```bash
# Run PHPStan analysis
vendor/bin/phpstan analyse --level=0
```

### PHP-CS-Fixer

PHP-CS-Fixer automatically formats code to follow PER-CS and PSR-12 standards.

```bash
# Check formatting (dry-run)
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run --diff

# Fix formatting
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php
```

---

## 18. Audit Report

See [audit.md](audit.md) for a comprehensive code audit covering security, workflows, and code quality.

**Latest audit:** August 21, 2026 — Security: 🟢 PASS (Score: 92/100) | UI/UX: 🟢 PASS (Score: 100/100) | Bulk Upload: 🟢 PASS

---

## 17. Cross-Reference Index

> Every finding, fix, and change is cross-referenced across all three documentation files.
> **AI agents MUST maintain these cross-references when making changes.**

| Topic | README.md | audit.md | changelog.md |
|-------|-----------|----------|-------------|
| HLS Status Bug | Section 7.6, 10.4, 15 (pattern 6) | Section 9 (full investigation) | 2026-08-20 (HLS Fix) |
| Upload Pipeline | Section 7.6 | Section 4 (workflow) | 2026-08-20 (HLS Fix) |
| Background Jobs | Section 10.4 | Section 9.6 (lessons), 13 | 2026-08-20 (HLS Fix), 2026-08-21 (Health Check) |
| Systemd Service | Section 2, 3 | Section 9.4 (fix #4) | 2026-08-20 (HLS Fix) |
| Post-Fix Verification | Section 1 (Rule 8) | Section 9.5 (verification) | 2026-08-20 (HLS Fix) |
| Security Hardening | Section 9 | Section 3 (Fix #1–#12) | 2026-08-20 (Security) |
| ESLint | Section 14 | Section 8 | 2026-08-20 (ESLint) |
| UI Overhaul | Section 7.11 | Section 7 | 2026-08-20 (UI) |
| UI/UX Feasibility Audit | Section 7.11, 14 | Section 11 (full audit) | 2026-08-20 (UI/UX Audit) |
| UI/UX Fix (F10–F16) | Section 7.11, 14 | Section 11 (score 100) | 2026-08-20 (UI/UX Fix) |
| Enterprise P1–P4 | Section 7.6, 10.4 | Section 3 | 2026-08-20 (Enterprise) |
| Token User Menu | Section 7.5, 8, 15 | Section 14 (feature audit) | 2026-08-21 (Token User Menu) |
| Bulk Chunked Upload | Section 7.6, 10.11, 15 | Section 12 (feature audit) | 2026-08-21 (Bulk Upload) |
| Chunk Upload API | Section 6 (API Routes) | Section 12.2–12.3 | 2026-08-21 (Bulk Upload) |
| Upload Resume Mechanism | Section 7.6, 10.11 | Section 12.3 (workflow) | 2026-08-21 (Bulk Upload) |
| Bulk Upload Bug Fixes | Section 7.6 | Section 12.6 (B1-B3) | 2026-08-21 (Bulk Upload Audit) |
| Slug Validation Bug | Section 7.6, 10.3 | Section 13 (investigation) | 2026-08-21 (Slug Fix) |
| Health Check Cron | Section 2, 10.4 | Section 13 | 2026-08-21 (Health Check) |

### Documentation Maintenance Rules

1. **Every bug fix** must have entries in all three files: audit.md (investigation), changelog.md (change record), README.md (architecture update)
2. **Every feature** must have entries in changelog.md and README.md; audit.md if it affects security/workflows
3. **Every UI/UX change** must be verified against: ESLint (0 errors), WCAG AA contrast, keyboard navigation, responsive breakpoints
4. **Cross-references** must be bidirectional — audit references changelog, changelog references audit, README references both
5. **Section numbers** in audit.md and changelog.md must be stable — use new section numbers, never renumber existing sections
