# Spec: Slug Validation Bug Fix + Background Health Check Cron

**Date:** August 21, 2026
**Status:** Draft (pending implementation)
**Severity:** 🔴 Critical — Video playback broken for affected uploads
**Author:** Buffy (Freebuff AI Agent)

---

## 1. Problem Statement

When uploading a video with a title starting with special characters (e.g., `(2014) German - VHC Morningsex...`), the slug generation produces a slug starting with a hyphen (`-2014-german-vhc-...-56900b`). While the HLS worker accepts and successfully transcodes the video (status becomes `ready`), the media delivery regex in `MediaService` requires the first character to be `[a-z0-9]`, rejecting all media file requests with a 404. The video is effectively unplayable despite being fully processed.

### Root Cause Chain

```
Title: "(2014) German - VHC Morningsex (Morning Fuck)"
  → strtolower: "(2014) german - vhc morningsex (morning fuck)"
  → preg_replace(/[^a-z0-9]+/i, '-'): "-2014-german-vhc-morningsex-morning-fuck-"
  → append hex: "-2014-german-vhc-morningsex-morning-fuck--56900b"
  → HLS worker accepts (regex: ^[a-z0-9-]+$) → transcodes successfully
  → MediaService rejects (regex: ^[a-z0-9][a-z0-9-]*/...) → 404 on all .m3u8/.ts
  → Video status=ready but unplayable
```

### Current Affected Videos

| ID | Title | Slug | Status | Issue |
|----|-------|------|--------|-------|
| 24 | (2014) German - VHC Morningsex (Morning Fuck) | `-2014-german-vhc-morningsex-morning-fuck--56900b` | ready | Slug starts with `-`, media files 404 |

---

## 2. Scope of Changes

### 2.1 Fix Slug Generation (Prevention)

**Goal:** Ensure slugs always start with `[a-z0-9]` so they are valid for media delivery.

**Files to modify:**

| File | Location | Current Code | Fix |
|------|----------|-------------|-----|
| `app/Services/VideoUpload.php` | `processOne()` line 58 | `$slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($fileTitle)) . '-' . bin2hex(random_bytes(3));` | Strip leading/trailing hyphens from slug before appending hex |
| `app/Services/VideoUpload.php` | `assembleAndProcess()` line 195 | Same slug generation pattern | Same fix |
| `index.php` (legacy) | line 187 | Same slug generation pattern | Same fix (legacy file, for consistency) |

**Fix logic:**
```php
$slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($fileTitle));
$slug = trim($slug, '-');          // strip leading/trailing hyphens
$slug = preg_replace('/-+/', '-', $slug);  // collapse multiple hyphens
if ($slug === '') $slug = 'video'; // fallback for titles with no alphanumeric chars
$slug .= '-' . bin2hex(random_bytes(3));
```

### 2.2 Fix Media Delivery Regex (Defense in Depth)

**Goal:** Make the media delivery regex more lenient to accept slugs with leading hyphens, as a safety net against future edge cases.

**Files to modify:**

| File | Location | Current Regex | Fixed Regex |
|------|----------|---------------|-------------|
| `app/Services/MediaService.php` | `ALLOWED_PATTERN` const (line 10) | `#^[a-z0-9][a-z0-9-]*/...$#i` | `#^[a-z0-9-]+/[a-z0-9-]*/...$#i` — but actually just change first char class to allow `-` |
| `index.php` (legacy) | media delivery section (line 282) | `#^[a-z0-9][a-z0-9-]*/...$#i` | Same fix for consistency |

**Revised regex:**
```
#^[a-z0-9][a-z0-9-]*/(?:poster\.jpg|preview\.mp4|source\.mp4|master\.m3u8|(?:360p|720p)\.m3u8|(?:360p|720p)_\d{3}\.ts)$#i
```
Change to:
```
#^[a-z0-9-]+/[a-z0-9-]*/(?:poster\.jpg|preview\.mp4|source\.mp4|master\.m3u8|(?:360p|720p)\.m3u8|(?:360p|720p)_\d{3}\.ts)$#i
```

Wait — the regex pattern is `{slug}/{filename}`. The current pattern is `^[a-z0-9][a-z0-9-]*/...` which means "first char must be alphanumeric". The fix should change to `^[a-z0-9-]+/...` (the slug portion allows hyphens anywhere, and the filename part after `/` is already well-constrained).

**Actually the simplest and safest fix:**
```
#^[a-z0-9-]+/[a-z0-9-]*/(?:poster\.jpg|preview\.mp4|source\.mp4|master\.m3u8|(?:360p|720p)\.m3u8|(?:360p|720p)_\d{3}\.ts)$#i
```

Wait, re-reading the regex more carefully:
- `^[a-z0-9]` — first char of slug
- `[a-z0-9-]*` — rest of slug
- `/` — separator
- `(?:poster\.jpg|...)` — filename

So it's `{slug}/{filename}` where slug is one directory level. The fix:
```
^[a-z0-9-]+/(?:poster\.jpg|...)
```
This allows slugs starting with `-` but still constrains them to `[a-z0-9-]` characters. Security is maintained because:
1. `realpath()` check prevents path traversal
2. Only allowed filenames are served
3. The slash separation ensures directory/file structure

### 2.3 Fix HLS Worker Regex (Consistency)

**File:** `/usr/local/sbin/arsip-hls-worker` line 8

**Current:**
```bash
if [[ ! "$slug" =~ ^[a-z0-9-]+$ ]]; then
```

**Assessment:** This regex already allows hyphens anywhere. No change needed. However, we should verify it remains consistent with the new slug generation rules.

### 2.4 Delete Broken Video

**Action:** Delete video ID 24 (slug `-2014-german-vhc-morningsex-morning-fuck--56900b`) from both database and filesystem.

**Steps:**
1. Delete media directory: `rm -rf /var/www/arsip-layar/media/-2014-german-vhc-morningsex-morning-fuck--56900b/`
2. Delete DB record: `DELETE FROM videos WHERE id=24`
3. Log the deletion as activity

**User will re-upload manually** after the slug generation fix is deployed.

### 2.5 Background Health Check Cron Job

**Goal:** A systemd timer + service that periodically checks video health and reports/fixes issues.

#### Architecture (matching existing patterns)

The project already uses:
- `systemd/arsip-hls-worker.service` — for HLS worker daemon
- `cli/run_jobs.php` — for job queue processing
- `app/Services/TelegramNotifier.php` — for Telegram notifications
- `app/bootstrap.php` — for app initialization

The health check should follow these patterns.

#### New Files

| File | Purpose |
|------|---------|
| `cli/health_check.php` | CLI script that performs all health checks |
| `systemd/arsip-health-check.service` | Systemd service unit |
| `systemd/arsip-health-check.timer` | Systemd timer unit (every 10 minutes) |

#### Health Checks (Full)

| # | Check | Detection | Action |
|---|-------|-----------|--------|
| H1 | **Stuck processing** | `status='processing'` AND `created_at < NOW() - INTERVAL 30 MINUTE` | Auto-retry: re-fire `arsip-hls-worker {slug}` |
| H2 | **Invalid slug** | `slug REGEXP '^-' OR slug REGEXP '^_' OR slug MATCHES '/[^a-z0-9-]/'` | Log warning. Auto-fix if possible (rename slug in DB + filesystem) |
| H3 | **Missing HLS files** | `status='ready'` but `master.m3u8` missing from disk | Auto-retry: re-fire `arsip-hls-worker {slug}` (worker checks status before re-processing) |
| H4 | **Missing poster** | `status='ready'` but `poster.jpg` missing from disk | Auto-retry: re-fire `arsip-hls-worker {slug}` |
| H5 | **Unplayable video** | `status='ready'` but slug fails the `ALLOWED_PATTERN` regex (defense in depth) | Log critical alert. Auto-fix: rename slug to valid format |
| H6 | **Disk space** | Available disk space < 1GB | Log warning only (no auto-fix) |

#### Output

| Channel | Content |
|---------|---------|
| **Log file** | `/var/log/arsip-health-check.log` — all checks with timestamps |
| **Database** | `activity_log` table — `action='health_check'` with details |
| **Telegram** | Alert via `TelegramNotifier` only for critical issues (H1, H5) or if > 0 issues found |

#### Cron Schedule

```ini
# /etc/systemd/system/arsip-health-check.timer
[Timer]
OnBootSec=2min
OnUnitActiveSec=10min
Unit=arsip-health-check.service

[Install]
WantedBy=timers.target
```

#### Service

```ini
# /etc/systemd/system/arsip-health-check.service
[Service]
Type=oneshot
ExecStart=/usr/bin/php /var/www/arsip-layar/cli/health_check.php
EnvironmentFile=/etc/arsip-layar/env
StandardOutput=append:/var/log/arsip-health-check.log
StandardError=append:/var/log/arsip-health-check.log
```

---

## 3. Implementation Plan

### Phase 1: Fix Slug Generation + Media Regex

1. Modify `app/Services/VideoUpload.php` — `processOne()` and `assembleAndProcess()` slug generation
2. Modify `app/Services/MediaService.php` — `ALLOWED_PATTERN` regex
3. Modify `index.php` (legacy) — slug generation and media regex for consistency
4. Syntax check all modified PHP files with `php -l`

### Phase 2: Delete Broken Video

1. Confirm video ID 24 exists and is the only broken one
2. Delete media directory
3. Delete DB record
4. Log the deletion

### Phase 3: Background Health Check

1. Create `cli/health_check.php` with all 6 health checks
2. Create `systemd/arsip-health-check.service`
3. Create `systemd/arsip-health-check.timer`
4. Enable and start the timer
5. Verify it runs correctly

### Phase 4: Documentation

1. Update `changelog.md` with full change record
2. Update `audit.md` with investigation section
3. Update `README.md` if architecture changes (cron service)

---

## 4. Detailed Implementation: cli/health_check.php

### Structure

```php
#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Health Check — runs periodically to detect and fix video issues.
 * 
 * Checks:
 *   H1: Stuck processing (processing > 30 min)
 *   H2: Invalid slugs (starts with -/_ or contains invalid chars)
 *   H3: Missing HLS files (status=ready but master.m3u8 missing)
 *   H4: Missing poster (status=ready but poster.jpg missing)
 *   H5: Unplayable video (slug fails ALLOWED_PATTERN)
 *   H6: Low disk space (< 1GB)
 */

require __DIR__ . '/../app/bootstrap.php';

// Skip session for CLI
// (bootstrap already handles this via php_sapi_name check)

$conn = Connection::getInstance();
$db = $conn->db();
$telegram = new TelegramNotifier($conn);

$log = [];
$issues = 0;
$fixes = 0;

// H1: Stuck processing
// H2: Invalid slugs
// H3: Missing HLS
// H4: Missing poster
// H5: Unplayable
// H6: Disk space

// ... (implementation details)

// Report
$logFile = '/var/log/arsip-health-check.log';
// ... write log
// ... send Telegram if issues found
```

### Health Check H1: Stuck Processing

```sql
SELECT id, title, slug, created_at 
FROM videos 
WHERE status = 'processing' 
AND created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
```

**Auto-fix:** Re-fire `arsip-hls-worker {slug}` for each stuck video. Log the retry. Only retry once per 24 hours (track last retry in activity_log).

### Health Check H2: Invalid Slugs

```sql
SELECT id, title, slug 
FROM videos 
WHERE slug REGEXP '^[-_]' 
   OR slug REGEXP '[^a-z0-9-]'
```

**Auto-fix:** Rename slug in DB and filesystem. New slug = same slug with leading/trailing `-`/`_` stripped and consecutive hyphens collapsed.

```php
$newSlug = trim($slug, '-_');
$newSlug = preg_replace('/-+/', '-', $newSlug);
if ($newSlug === '') $newSlug = 'video-' . bin2hex(random_bytes(3));
$newSlug .= '-' . bin2hex(random_bytes(3)); // new random suffix to avoid collision

// Rename filesystem
rename(MEDIA_ROOT . '/' . $slug, MEDIA_ROOT . '/' . $newSlug);

// Update DB
$conn->execute('UPDATE videos SET slug=?, poster=REPLACE(poster,?,?), source=REPLACE(source,?,?) WHERE id=?',
    [$newSlug, $slug, $newSlug, $slug, $newSlug, $id], 'sssssi');
```

### Health Check H3: Missing HLS Files

```sql
SELECT v.id, v.title, v.slug, v.status
FROM videos v
WHERE v.status = 'ready'
AND NOT EXISTS (
    SELECT 1 FROM DUAL WHERE ISFILE(CONCAT('/var/www/arsip-layar/media/', v.slug, '/master.m3u8')) = 1
)
```

Actually, since we can't use ISFILE in MySQL, do this in PHP:

```php
$readyVideos = $conn->selectAll("SELECT id, slug FROM videos WHERE status='ready'");
foreach ($readyVideos as $v) {
    $masterPath = MEDIA_ROOT . '/' . $v['slug'] . '/master.m3u8';
    if (!is_file($masterPath)) {
        // Auto-fix: re-fire worker
        shell_exec('setsid nohup /usr/local/sbin/arsip-hls-worker ' . escapeshellarg($v['slug']) . ' > /dev/null 2>&1 < /dev/null &');
        $issues++;
    }
}
```

### Health Check H4: Missing Poster

Same pattern as H3 but checking for `poster.jpg`.

### Health Check H5: Unplayable Video

```php
$readyVideos = $conn->selectAll("SELECT id, slug FROM videos WHERE status='ready'");
$pattern = MediaService::ALLOWED_PATTERN; // Use the same regex
foreach ($readyVideos as $v) {
    $testPath = $v['slug'] . '/master.m3u8';
    if (!preg_match($pattern, $testPath)) {
        // Slug is unplayable — needs rename
        // Same auto-fix as H2
    }
}
```

### Health Check H6: Low Disk Space

```php
$freeSpace = disk_free_space(MEDIA_ROOT);
if ($freeSpace < 1024 * 1024 * 1024) { // < 1GB
    // Log warning
    $log[] = "CRITICAL: Low disk space: " . round($freeSpace / 1024 / 1024) . " MB free";
}
```

---

## 5. Telegram Notification Format

When issues are found, send a formatted message:

```
🔍 Health Check Report — {timestamp}

Found {N} issue(s):
{H1}: {count} video(s) stuck processing (>30 min)
{H2}: {count} invalid slug(s) detected
{H3}: {count} missing HLS files
{H4}: {count} missing poster(s)
{H5}: {count} unplayable video(s)
{H6}: Disk space: {free_mb} MB

Auto-fixed: {fixes} issue(s)
Manual review needed: {manual} issue(s)
```

---

## 6. Files Summary

### Modified Files

| File | Change Type | Description |
|------|------------|-------------|
| `app/Services/VideoUpload.php` | Fix | Slug generation: strip leading/trailing hyphens |
| `app/Services/MediaService.php` | Fix | `ALLOWED_PATTERN` regex: allow leading hyphens in slug |
| `index.php` (legacy) | Fix | Same slug + regex fixes for consistency |
| `schema.sql` | Update | Add comment about slug format constraint |

### New Files

| File | Purpose |
|------|---------|
| `cli/health_check.php` | Health check script (6 checks + auto-fix) |
| `systemd/arsip-health-check.service` | Systemd service unit |
| `systemd/arsip-health-check.timer` | Systemd timer (every 10 min) |

### Documentation Updates

| File | Change |
|------|--------|
| `changelog.md` | Add entry: Slug Fix + Health Check Cron (2026-08-21) |
| `audit.md` | Add Section 13: Slug Validation Bug Investigation |
| `README.md` | Update Section 10.4 (Background Jobs), add health check cron to Section 2 |

---

## 7. Post-Fix Verification Checklist

Following Rule 8 from README.md:

- [ ] All modified PHP files pass `php -l`
- [ ] DB state verified: video ID 24 deleted
- [ ] Filesystem verified: `-2014-german-...` directory removed
- [ ] Systemd timer `arsip-health-check.timer` active and running
- [ ] Health check runs successfully: `php cli/health_check.php`
- [ ] End-to-end test: upload a video with special-char title, verify slug is valid and video plays
- [ ] Telegram notification received when health check finds issues
- [ ] `changelog.md` updated
- [ ] `audit.md` updated
- [ ] `README.md` updated

---

## 8. Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Slug rename breaks existing links/bookmarks | Low | Medium | Only affects broken videos (unplayable anyway) |
| Health check auto-fix creates new issues | Low | Medium | Auto-retry only fires worker once; rename uses collision-safe hex suffix |
| Cron job uses too much CPU | Very Low | Low | Runs in <5s for <100 videos; uses simple SQL queries |
| Health check deletes wrong video | Very Low | Critical | Only deletes via explicit ID; health check only renames, never deletes |
| Telegram spam from repeated alerts | Low | Low | Deduplicate: only alert once per issue per 24h |

---

## 9. Edge Cases to Handle

1. **Title is entirely special characters** (e.g., `???`): Slug becomes `video-{hex}` as fallback
2. **Slug collision after rename**: Append new random hex suffix
3. **Concurrent health check runs**: Use file lock (`flock`) to prevent duplicate execution
4. **Worker not available**: Health check logs warning, does not crash
5. **Database connection failure**: Health check logs to file only, exits gracefully
6. **Very old videos with `processing` status**: Health check retries worker, but if worker fails 3x, mark as `failed` with log

---

## 10. Success Criteria

1. ✅ New uploads with special-character titles produce valid, playable slugs
2. ✅ Existing broken video (ID 24) is deleted
3. ✅ Health check runs every 10 minutes via systemd timer
4. ✅ Stuck processing videos are auto-retried
5. ✅ Invalid slugs are auto-detected and renamed
6. ✅ Missing HLS files trigger re-transcoding
7. ✅ Telegram alerts sent for critical issues
8. ✅ All logs written to `/var/log/arsip-health-check.log` and `activity_log` table
9. ✅ All documentation updated (changelog, audit, README)
10. ✅ Zero breaking changes to existing functionality
