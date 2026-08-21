# AUTODEPLOY.md — VPS Server Disaster Recovery & Deployment Guide

> **Purpose:** Complete documentation of the Arsip Layar VPS server environment.
> **Use case:** If the server dies, use this document to rebuild everything from a new VPS.
> **Audience:** AI agents, developers, system administrators.

---

## ⚡ Quick Start (AI Agent Summary)

> For experienced AI agents. Full details in sections below.

### Prerequisites
- Fresh Ubuntu 26.04 LTS VPS (8 CPU, 8GB RAM, 300GB disk minimum)
- Root access
- GitHub SSH key configured

### One-Shot Setup

```bash
# 1. Update system
apt-get update && apt-get upgrade -y

# 2. Install packages
apt-get install -y nginx php8.5-fpm php8.5-mysql php8.5-mbstring \
  php8.5-xml php8.5-curl php8.5-gd php8.5-zip \
  mariadb-server ffmpeg git ufw fail2ban nodejs npm

# 3. Setup SSH key
ssh-keygen -t ed25519 -C "alvin.krisdianto69@gmail.com" -f ~/.ssh/id_ed25519 -N ""
cat ~/.ssh/id_ed25519.pub  # Add to GitHub Settings → SSH Keys

# 4. Clone repository
git clone git@github.com:frnshaxor/Xboxchillz.git /var/www/arsip-layar
cd /var/www/arsip-layar

# 5. Setup database
mysql -e "CREATE DATABASE arsip_layar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER 'arsip'@'127.0.0.1' IDENTIFIED BY 'ArsipDb_2026_xK7';"
mysql -e "GRANT ALL PRIVILEGES ON arsip_layar.* TO 'arsip'@'127.0.0.1';"
mysql -e "FLUSH PRIVILEGES;"
mysql arsip_layar < schema.sql

# 6. Setup environment
mkdir -p /etc/arsip-layar
cat > /etc/arsip-layar/env << 'EOF'
DB_HOST=127.0.0.1
DB_USER=arsip
DB_PASS=ArsipDb_2026_xK7
DB_NAME=arsip_layar
EOF
chmod 600 /etc/arsip-layar/env

# 7. Deploy configs
cp server-config/nginx/arsip-layar /etc/nginx/sites-enabled/
cp server-config/php-fpm/www.conf /etc/php/8.5/fpm/pool.d/
cp server-config/fail2ban/arsip.local /etc/fail2ban/jail.d/

# 8. Setup systemd
cp systemd/arsip-hls-worker.service /etc/systemd/system/
cp systemd/arsip-health-check.service /etc/systemd/system/
cp systemd/arsip-health-check.timer /etc/systemd/system/
systemctl daemon-reload

# 9. Setup firewall
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

# 10. Setup file permissions
chown -R www-data:www-data /var/www/arsip-layar
chmod 750 /var/www/arsip-layar

# 11. Start services
systemctl restart nginx
systemctl restart php8.5-fpm
systemctl restart mariadb
systemctl enable --now arsip-hls-worker
systemctl enable --now arsip-health-check.timer

# 12. Install Node.js dependencies
cd /var/www/arsip-layar && npm install

# 13. Setup Git config
git config user.name "Xboxchillz"
git config user.email "alvin.krisdianto69@gmail.com"

# 14. Verify
curl -s -o /dev/null -w "%{http_code}" http://localhost/
# Should return: 200
```

---

## 🖥️ Server Specifications

### Current VPS Configuration

| Component | Value |
|-----------|-------|
| **OS** | Ubuntu 26.04 LTS (Resolute Raccoon) |
| **Kernel** | 7.0.0-27-generic |
| **CPU** | Intel Xeon (SapphireRapids), 8 cores |
| **RAM** | 9.2 GB |
| **Disk** | 296 GB (9.6 GB used, 274 GB free) |
| **Hostname** | mvpsrv |
| **Architecture** | x86_64 |

### Recommended VPS Specs

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| **CPU** | 4 cores | 8 cores |
| **RAM** | 4 GB | 8 GB |
| **Disk** | 50 GB | 300 GB |
| **OS** | Ubuntu 22.04 LTS | Ubuntu 26.04 LTS |

---

## 📦 Package Installation

### Required Packages

| Package | Version | Purpose |
|---------|---------|---------|
| nginx | 1.28.3 | Web server |
| php8.5-fpm | 8.5.4 | PHP FastCGI Process Manager |
| php8.5-mysql | 8.5.4 | MySQL database driver |
| php8.5-mbstring | 8.5.4 | Multibyte string support |
| php8.5-xml | 8.5.4 | XML parsing |
| php8.5-curl | 8.5.4 | HTTP client |
| php8.5-gd | 8.5.4 | Image processing |
| php8.5-zip | 8.5.4 | ZIP archive support |
| mariadb-server | 11.8.6 | Database server |
| ffmpeg | 8.0.1 | Video processing (HLS transcoding) |
| git | 2.53.0 | Version control |
| ufw | (latest) | Firewall |
| fail2ban | 1.1.0 | Brute-force protection |
| nodejs | 22.23.2 | JavaScript runtime (ESLint) |
| npm | 10.9.8 | Node package manager |

### Installation Command

```bash
apt-get update && apt-get upgrade -y
apt-get install -y \
  nginx \
  php8.5-fpm php8.5-mysql php8.5-mbstring php8.5-xml \
  php8.5-curl php8.5-gd php8.5-zip \
  mariadb-server \
  ffmpeg \
  git \
  ufw \
  fail2ban \
  nodejs npm
```

---

## ⚙️ Service Configuration

### Nginx

**Config file:** `/etc/nginx/sites-enabled/arsip-layar`
**Source:** `server-config/nginx/arsip-layar` (in this repo)

Key settings:
- Listen on port 80 (HTTP)
- Document root: `/var/www/arsip-layar/public`
- PHP-FPM via unix socket: `/run/php/php8.5-fpm.sock`
- Client max body size: 2GB
- Security headers: X-Content-Type-Options, X-Frame-Options, Referrer-Policy
- Media protection: `/media/*` denied, `/protected-media/*` rewritten to PHP
- Rate limiting: Login 6 req/min, API 30 req/sec

### PHP-FPM

**Config file:** `/etc/php/8.5/fpm/pool.d/www.conf`
**Source:** `server-config/php-fpm/www.conf` (in this repo)

Key settings:
- User/Group: www-data
- Listen: `/run/php/php8.5-fpm.sock`
- Database env vars: DB_HOST, DB_USER, DB_PASS, DB_NAME

**PHP settings (php.ini):**
```ini
upload_max_filesize = 2G
post_max_size = 2G
memory_limit = 512M
max_execution_time = 0
```

### MySQL/MariaDB

**Database:** `arsip_layar`
**User:** `arsip`
**Password:** `ArsipDb_2026_xK7`
**Host:** `127.0.0.1`

**Tables (14):**
```
_migrations, access_tokens, activity_log, admins, analytics_events,
backups, categories, job_queue, login_attempts, payment_orders,
settings, video_heatmap, videos, webhook_retry
```

### Systemd Services

**Files in repo:** `systemd/`

| Service | Type | Purpose |
|---------|------|---------|
| `arsip-hls-worker.service` | simple (daemon) | HLS transcoding job runner |
| `arsip-health-check.service` | oneshot | Video integrity monitor |
| `arsip-health-check.timer` | timer | Triggers health check every 10 min |

**Environment file:** `/etc/arsip-layar/env`
**Source:** `server-config/arsip-layar/env` (in this repo)

```
DB_HOST=127.0.0.1
DB_USER=arsip
DB_PASS=ArsipDb_2026_xK7
DB_NAME=arsip_layar
```

---

## 🔐 Security Setup

### UFW Firewall

```bash
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp    # SSH
ufw allow 80/tcp    # HTTP
ufw allow 443/tcp   # HTTPS
ufw --force enable
```

**Current rules:**
| Port | Protocol | Action | Source |
|------|----------|--------|--------|
| 22 | TCP | ALLOW | Anywhere |
| 80 | TCP | ALLOW | Anywhere |
| 443 | TCP | ALLOW | Anywhere |

### fail2ban

**Config file:** `/etc/fail2ban/jail.d/arsip.local`
**Source:** `server-config/fail2ban/arsip.local` (in this repo)

**Jails:**
| Jail | Port | Max Retry | Ban Time |
|------|------|-----------|----------|
| sshd | ssh | 4 | 1 hour |
| nginx-limit-req | http,https | 10 | 1 hour |
| nginx-badbots | http,https | 2 | 1 hour |

### SSH Keys

**Public key (add to GitHub):**
```
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIBUMTnFLjBVObEeei45msILGhCdSFsgHFCiMsYUfGETH alvin.krisdianto69@gmail.com
```

**Setup:**
```bash
ssh-keygen -t ed25519 -C "alvin.krisdianto69@gmail.com" -f ~/.ssh/id_ed25519 -N ""
cat ~/.ssh/id_ed25519.pub  # Add to GitHub Settings → SSH Keys
```

### File Permissions

```bash
# App root
chown -R www-data:www-data /var/www/arsip-layar
chmod 750 /var/www/arsip-layar

# Media directory
chmod 750 /var/www/arsip-layar/media
find /var/www/arsip-layar/media -name "*.mp4" -exec chmod 644 {} \;

# Storage
chmod 750 /var/www/arsip-layar/storage
chmod 640 /var/www/arsip-layar/storage/backups/*

# Git
chmod 750 /var/www/arsip-layar/.git

# Environment file
chmod 600 /etc/arsip-layar/env
```

---

## 🗄️ Database Setup

### Create Database & User

```sql
CREATE DATABASE arsip_layar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'arsip'@'127.0.0.1' IDENTIFIED BY 'ArsipDb_2026_xK7';
GRANT ALL PRIVILEGES ON arsip_layar.* TO 'arsip'@'127.0.0.1';
FLUSH PRIVILEGES;
```

Or using bash:
```bash
mysql -e "CREATE DATABASE arsip_layar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER 'arsip'@'127.0.0.1' IDENTIFIED BY 'ArsipDb_2026_xK7';"
mysql -e "GRANT ALL PRIVILEGES ON arsip_layar.* TO 'arsip'@'127.0.0.1';"
mysql -e "FLUSH PRIVILEGES;"
```

### Schema Setup

```bash
cd /var/www/arsip-layar
mysql arsip_layar < schema.sql
```

Or run migrations:
```bash
cd /var/www/arsip-layar
php migrate.php
```

### Data Restore

```bash
# From backup file
mysql arsip_layar < /path/to/arsip_layar_backup_YYYYMMDD_HHMMSS.sql.gz

# From backup directory
ls /var/www/arsip-layar/storage/backups/
mysql arsip_layar < /var/www/arsip-layar/storage/backups/arsip_layar_YYYYMMDD_HHMMSS.sql.gz
```

---

## 📱 Application Setup

### Clone Repository

```bash
# Setup SSH key first (see Security Setup)
git clone git@github.com:frnshaxor/Xboxchillz.git /var/www/arsip-layar
cd /var/www/arsip-layar
```

### Environment Configuration

```bash
mkdir -p /etc/arsip-layar
cat > /etc/arsip-layar/env << 'EOF'
DB_HOST=127.0.0.1
DB_USER=arsip
DB_PASS=ArsipDb_2026_xK7
DB_NAME=arsip_layar
EOF
chmod 600 /etc/arsip-layar/env
```

### Install Dependencies

```bash
cd /var/www/arsip-layar
npm install  # For ESLint
```

### Setup Git Config

```bash
cd /var/www/arsip-layar
git config user.name "Xboxchillz"
git config user.email "alvin.krisdianto69@gmail.com"
git config credential.helper store
```

### Setup Systemd Services

```bash
cp /var/www/arsip-layar/systemd/arsip-hls-worker.service /etc/systemd/system/
cp /var/www/arsip-layar/systemd/arsip-health-check.service /etc/systemd/system/
cp /var/www/arsip-layar/systemd/arsip-health-check.timer /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now arsip-hls-worker
systemctl enable --now arsip-health-check.timer
```

### Start Services

```bash
systemctl restart nginx
systemctl restart php8.5-fpm
systemctl restart mariadb
systemctl restart arsip-hls-worker
systemctl restart arsip-health-check.timer
```

---

## 💾 Backup & Restore

### Database Backup

```bash
# Manual backup
mysqldump -u arsip -p'ArsipDb_2026_xK7' arsip_layar | \
  gzip > /var/www/arsip-layar/storage/backups/arsip_layar_$(date +%Y%m%d_%H%M%S).sql.gz

# List backups
ls -la /var/www/arsip-layar/storage/backups/
```

### Media Backup

```bash
# Backup media files (excluding large video files)
tar czf /var/www/arsip-layar/storage/backups/media_$(date +%Y%m%d).tar.gz \
  --exclude='*/source.mp4' \
  --exclude='*/720p_*' \
  --exclude='*/360p_*' \
  --exclude='*/master.m3u8' \
  /var/www/arsip-layar/media/

# Full media backup (WARNING: very large)
tar czf /var/www/arsip-layar/storage/backups/media_full_$(date +%Y%m%d).tar.gz \
  /var/www/arsip-layar/media/
```

### Full Server Backup

```bash
# Backup app (excluding media and node_modules)
tar czf /var/www/arsip-layar/storage/backups/full_$(date +%Y%m%d).tar.gz \
  --exclude='node_modules' \
  --exclude='media/*' \
  --exclude='storage/uploads/*' \
  --exclude='storage/backups/*' \
  /var/www/arsip-layar/

# Backup database
mysqldump -u root arsip_layar | \
  gzip > /root/arsip_layar_db_$(date +%Y%m%d).sql.gz

# Backup configs
tar czf /root/arsip_configs_$(date +%Y%m%d).tar.gz \
  /etc/nginx/sites-enabled/arsip-layar \
  /etc/php/8.5/fpm/pool.d/www.conf \
  /etc/fail2ban/jail.d/arsip.local \
  /etc/systemd/system/arsip-*.service \
  /etc/systemd/system/arsip-*.timer \
  /etc/arsip-layar/
```

### Restore from GitHub

```bash
# 1. Clone repo
git clone git@github.com:frnshaxor/Xboxchillz.git /var/www/arsip-layar

# 2. Restore database
mysql arsip_layar < /path/to/backup.sql

# 3. Restore media (if backup available)
tar xzf /path/to/media_backup.tar.gz -C /

# 4. Setup configs (see Quick Start)
```

---

## ✅ Health Check

### Service Verification

```bash
# Check all services
systemctl status nginx
systemctl status php8.5-fpm
systemctl status mariadb
systemctl status arsip-hls-worker
systemctl status arsip-health-check.timer

# Check ports
ss -tlnp | grep -E "(80|3306|9000)"

# Check processes
ps aux | grep -E "(nginx|php-fpm|mysql|arsip)"
```

### Application Verification

```bash
# Test HTTP response
curl -s -o /dev/null -w "%{http_code}" http://localhost/
# Should return: 200

# Test PHP processing
curl -s -o /dev/null -w "%{http_code}" http://localhost/?page=login
# Should return: 200

# Test API
curl -s http://localhost/api.php?op=health

# Test database connection
mysql -u arsip -p'ArsipDb_2026_xK7' arsip_layar -e "SELECT COUNT(*) FROM videos;"
```

### Troubleshooting

| Issue | Check | Fix |
|-------|-------|-----|
| 502 Bad Gateway | PHP-FPM status | `systemctl restart php8.5-fpm` |
| 403 Forbidden | File permissions | `chown -R www-data:www-data /var/www/arsip-layar` |
| Database connection failed | MariaDB status | `systemctl restart mariadb` |
| HLS worker not running | Service status | `systemctl restart arsip-hls-worker` |
| Upload fails | PHP limits | Check `upload_max_filesize` in php.ini |
| Health check fails | Log file | `tail -f /var/log/arsip-health-check.log` |
| Nginx config error | Config test | `nginx -t && systemctl restart nginx` |
| SSH connection refused | SSH service | `systemctl restart ssh` |

---

## 🔗 Cross-References

| Document | Purpose | Relationship |
|----------|---------|-------------|
| `README.md` | Technical codebase reference | AUTODEPLOY.md references server setup |
| `audit.md` | Investigation reports | AUTODEPLOY.md references security findings |
| `changelog.md` | Change history | AUTODEPLOY.md documents current state |
| `rules.md` | AI agent guidelines | AUTODEPLOY.md provides deployment instructions |
| `deploy.sh` | Deployment script | AUTODEPLOY.md explains what it does |

---

## 📋 Server Config Files (in this repo)

| File | Destination | Purpose |
|------|-------------|---------|
| `server-config/nginx/arsip-layar` | `/etc/nginx/sites-enabled/arsip-layar` | Nginx site config |
| `server-config/php-fpm/www.conf` | `/etc/php/8.5/fpm/pool.d/www.conf` | PHP-FPM pool config |
| `server-config/arsip-layar/env` | `/etc/arsip-layar/env` | Environment variables |
| `server-config/fail2ban/arsip.local` | `/etc/fail2ban/jail.d/arsip.local` | fail2ban jails |
| `systemd/arsip-hls-worker.service` | `/etc/systemd/system/` | HLS worker service |
| `systemd/arsip-health-check.service` | `/etc/systemd/system/` | Health check service |
| `systemd/arsip-health-check.timer` | `/etc/systemd/system/` | Health check timer |
| `deploy.sh` | (run directly) | Deployment script |

---

*Last updated: August 21, 2026*
*Source: Live VPS server (mvpsrv, Ubuntu 26.04 LTS)*
