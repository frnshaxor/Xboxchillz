# Spec: AUTODEPLOY.md — VPS Server Disaster Recovery & Deployment Guide

**Purpose:** Create a comprehensive `AUTODEPLOY.md` file that documents the entire VPS server environment and provides step-by-step instructions for AI agents to rebuild the server from scratch.

---

## 1. Overview

### 1.1 What This File Does
- Documents the complete VPS server environment
- Provides disaster recovery instructions for rebuilding from a new VPS
- Serves as a reference for AI agents working on this codebase
- Integrates with README.md, audit.md, changelog.md, and rules.md

### 1.2 Target Audience
- AI agents that need to rebuild the server
- Developers setting up the server for the first time
- System administrators reviewing the infrastructure

### 1.3 File Name
`AUTODEPLOY.md`

### 1.4 Integration with Existing Files

| File | Relationship |
|------|-------------|
| `README.md` | Technical reference for the codebase |
| `audit.md` | Investigation reports and findings |
| `changelog.md` | Change history |
| `rules.md` | AI agent guidelines and protocols |
| `AUTODEPLOY.md` | **NEW** — Server environment + deployment instructions |

---

## 2. Document Structure

### 2.1 Top-Level Sections

```
# AUTODEPLOY.md

## ⚡ Quick Start (AI Agent Summary)
## 🖥️ Server Specifications
## 📦 Package Installation
## ⚙️ Service Configuration
  ### Nginx
  ### PHP-FPM
  ### MySQL/MariaDB
  ### Systemd Services
## 🔐 Security Setup
  ### UFW Firewall
  ### fail2ban
  ### SSH Keys
  ### File Permissions
## 🗄️ Database Setup
  ### Create Database & User
  ### Schema Setup
  ### Data Restore
## 📱 Application Setup
  ### Clone Repository
  ### Environment Configuration
  ### Dependencies
## 💾 Backup & Restore
  ### Database Backup
  ### Media Backup
  ### Full Server Backup
## ✅ Health Check
  ### Service Verification
  ### Application Verification
  ### Troubleshooting
## 🔗 Cross-References
```

---

## 3. Content Requirements

### 3.1 Quick Start Section

A concise summary for experienced AI agents. Should be copy-paste ready.

**Format:**
```markdown
## ⚡ Quick Start (AI Agent Summary)

> For experienced AI agents. Full details in sections below.

### Prerequisites
- Fresh Ubuntu 26.04 LTS VPS
- Root access
- GitHub access (SSH key)

### One-Shot Setup
\`\`\`bash
# 1. Update system
apt-get update && apt-get upgrade -y

# 2. Install packages
apt-get install -y nginx php8.5-fpm php8.5-mysql php8.5-mbstring php8.5-xml php8.5-curl php8.5-gd php8.5-zip mariadb-server ffmpeg git ufw fail2ban

# 3. Clone repository
git clone git@github.com:frnshaxor/Xboxchillz.git /var/www/arsip-layar

# 4. Run deployment script
cd /var/www/arsip-layar && bash deploy.sh

# 5. Restore database
mysql arsip_layar < /path/to/backup.sql

# 6. Verify
systemctl status nginx php8.5-fpm mariadb arsip-hls-worker
\`\`\`
```

### 3.2 Server Specifications

**Current VPS configuration (from live server):**

| Component | Value |
|-----------|-------|
| OS | Ubuntu 26.04 LTS (Resolute Raccoon) |
| Kernel | 7.0.0-27-generic |
| CPU | Intel Xeon (SapphireRapids), 8 cores |
| RAM | 9.2 GB |
| Disk | 296 GB (9.6 GB used, 274 GB free) |
| Hostname | mvpsrv |
| Uptime | 1 day, 12 hours |

### 3.3 Package Installation

**Required packages with exact versions:**

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
| ffmpeg | 8.0.1 | Video processing |
| git | 2.53.0 | Version control |
| ufw | (latest) | Firewall |
| fail2ban | 1.1.0 | Brute-force protection |
| nodejs | 22.23.2 | JavaScript runtime (for ESLint) |
| npm | 10.9.8 | Node package manager |

**Installation command:**
```bash
apt-get update && apt-get install -y \
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

### 3.4 Service Configuration

#### Nginx Configuration

**File:** `/etc/nginx/sites-enabled/arsip-layar`

```nginx
server {
  listen 80 default_server;
  listen [::]:80 default_server;
  server_name _;
  root /var/www/arsip-layar/public;
  index index.php;
  client_max_body_size 2G;

  # Security headers
  add_header X-Content-Type-Options "nosniff" always;
  add_header X-Frame-Options "DENY" always;
  add_header Referrer-Policy "strict-origin-when-cross-origin" always;
  add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;

  # Media protection
  location ^~ /media/ { deny all; }
  location ^~ /protected-media/ {
    set $protected_media_path $uri;
    rewrite ^ /index.php?page=media&path=$protected_media_path last;
  }

  # Cache static assets
  location ~* \.(jpg|jpeg|png|webp|css|js)$ {
    add_header Cache-Control "public,max-age=86400";
    try_files $uri =404;
  }

  # Deny hidden files
  location ~ /\. { deny all; }
  location ~ ^/media/.*\.(php|phtml|phar)$ { deny all; }
  location ~ ^/(storage|\.git|\.env|app|routes|controllers|views) { deny all; }

  # Front controller
  location / { try_files $uri $uri/ /index.php?$query_string; }

  # PHP-FPM
  location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php8.5-fpm.sock;
    fastcgi_read_timeout 300;
    fastcgi_buffers 16 16k;
    fastcgi_buffer_size 32k;
    limit_req zone=login burst=10 nodelay;
    limit_req_status 429;
  }

  # Block exploits
  location ~* (wp-login|xmlrpc\.php|/\.env|/phpmyadmin) { return 444; }
}
```

**Rate limiting:** `/var/www/arsip-layar/nginx-arsip-limits.conf`
```nginx
map $arg_page $login_key {
    default "";
    login   $binary_remote_addr;
}
limit_req_zone $login_key          zone=login:10m rate=6r/m;
limit_req_zone $binary_remote_addr zone=api:10m   rate=30r/s;
```

#### PHP-FPM Configuration

**File:** `/etc/php/8.5/fpm/pool.d/www.conf`

Key settings:
```ini
user = www-data
group = www-data
listen = /run/php/php8.5-fpm.sock

; Database credentials (from env file)
env[DB_HOST] = 127.0.0.1
env[DB_USER] = arsip
env[DB_PASS] = ArsipDb_2026_xK7
env[DB_NAME] = arsip_layar
```

**PHP settings (php.ini):**
```ini
upload_max_filesize = 2G
post_max_size = 2G
memory_limit = 512M
max_execution_time = 0
```

#### MySQL/MariaDB Configuration

**Database:** `arsip_layar`
**User:** `arsip`
**Password:** `ArsipDb_2026_xK7`
**Host:** `127.0.0.1`

**Tables (14 total):**
```
_migrations, access_tokens, activity_log, admins, analytics_events,
backups, categories, job_queue, login_attempts, payment_orders,
settings, video_heatmap, videos, webhook_retry
```

#### Systemd Services

**arsip-hls-worker.service:**
```ini
[Unit]
Description=Arsip Layar Job Runner (HLS transcode queue)
After=network.target mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/arsip-layar
EnvironmentFile=-/etc/arsip-layar/env
ExecStart=/usr/bin/php /var/www/arsip-layar/cli/run_jobs.php --daemon
Restart=on-failure
RestartSec=10

[Install]
WantedBy=multi-user.target
```

**arsip-health-check.service:**
```ini
[Unit]
Description=Arsip Layar Health Check (video integrity monitor)
After=network.target mysql.service

[Service]
Type=oneshot
User=www-data
Group=www-data
WorkingDirectory=/var/www/arsip-layar
EnvironmentFile=-/etc/arsip-layar/env
ExecStart=/usr/bin/php /var/www/arsip-layar/cli/health_check.php
StandardOutput=append:/var/log/arsip-health-check.log

[Install]
WantedBy=multi-user.target
```

**arsip-health-check.timer:**
```ini
[Unit]
Description=Arsip Layar Health Check Timer (every 10 min)

[Timer]
OnBootSec=2min
OnUnitActiveSec=10min
AccuracySec=1min
Persistent=true

[Install]
WantedBy=timers.target
```

**Environment file:** `/etc/arsip-layar/env`
```
DB_HOST=127.0.0.1
DB_USER=arsip
DB_PASS=ArsipDb_2026_xK7
DB_NAME=arsip_layar
```

### 3.5 Security Setup

#### UFW Firewall

```bash
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
```

**Current rules:**
| Port | Protocol | Action | Source |
|------|----------|--------|--------|
| 22 | TCP | ALLOW | Anywhere |
| 80 | TCP | ALLOW | Anywhere |
| 443 | TCP | ALLOW | Anywhere |

#### fail2ban

**Jails:**
| Jail | Port | Max Retry | Ban Time |
|------|------|-----------|----------|
| sshd | ssh | 4 | 1 hour |
| nginx-limit-req | http,https | 10 | 1 hour |
| nginx-badbots | http,https | 2 | 1 hour |

**Configuration:** `/etc/fail2ban/jail.d/arsip.local`
```ini
[DEFAULT]
bantime  = 1h
findtime = 15m
maxretry = 5
backend  = systemd

[sshd]
enabled = true
port    = ssh
maxretry = 4

[nginx-limit-req]
enabled  = true
filter   = nginx-limit-req
port     = http,https
logpath  = /var/log/nginx/error.log
maxretry = 10

[nginx-badbots]
enabled  = true
filter   = nginx-badbots
port     = http,https
logpath  = /var/log/nginx/access.log
maxretry = 2
```

#### SSH Keys

**Public key (add to GitHub):**
```
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIBUMTnFLjBVObEeei45msILGhCdSFsgHFCiMsYUfGETH alvin.krisdianto69@gmail.com
```

**Setup:**
```bash
ssh-keygen -t ed25519 -C "alvin.krisdianto69@gmail.com" -f ~/.ssh/id_ed25519 -N ""
cat ~/.ssh/id_ed25519.pub  # Add to GitHub Settings → SSH Keys
```

#### File Permissions

```bash
# App root
chown -R www-data:www-data /var/www/arsip-layar
chmod 750 /var/www/arsip-layar

# Media directory
chmod 750 /var/www/arsip-layar/media
chmod 644 /var/www/arsip-layar/media/*/source.mp4

# Storage
chmod 750 /var/www/arsip-layar/storage
chmod 640 /var/www/arsip-layar/storage/backups/*

# Git
chmod 750 /var/www/arsip-layar/.git
```

### 3.6 Database Setup

#### Create Database & User

```sql
CREATE DATABASE arsip_layar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'arsip'@'127.0.0.1' IDENTIFIED BY 'ArsipDb_2026_xK7';
GRANT ALL PRIVILEGES ON arsip_layar.* TO 'arsip'@'127.0.0.1';
FLUSH PRIVILEGES;
```

#### Schema Setup

```bash
mysql arsip_layar < /var/www/arsip-layar/schema.sql
```

Or run migrations:
```bash
cd /var/www/arsip-layar
php migrate.php
```

#### Data Restore

```bash
# From backup file
mysql arsip_layar < /path/to/arsip_layar_backup_YYYYMMDD_HHMMSS.sql.gz

# Or from backup directory
ls /var/www/arsip-layar/storage/backups/
mysql arsip_layar < /var/www/arsip-layar/storage/backups/arsip_layar_YYYYMMDD_HHMMSS.sql.gz
```

### 3.7 Application Setup

#### Clone Repository

```bash
# Setup SSH key first (see Security Setup)
git clone git@github.com:frnshaxor/Xboxchillz.git /var/www/arsip-layar
cd /var/www/arsip-layar
```

#### Environment Configuration

Create `/etc/arsip-layar/env`:
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

#### Install Dependencies

```bash
cd /var/www/arsip-layar
npm install  # For ESLint
```

#### Setup Git Config

```bash
cd /var/www/arsip-layar
git config user.name "Xboxchillz"
git config user.email "alvin.krisdianto69@gmail.com"
git config credential.helper store
```

### 3.8 Backup & Restore

#### Database Backup

```bash
# Manual backup
mysqldump -u arsip -p'ArsipDb_2026_xK7' arsip_layar | gzip > /var/www/arsip-layar/storage/backups/arsip_layar_$(date +%Y%m%d_%H%M%S).sql.gz

# Automated backup (via admin panel)
# Access: ?page=admin → System → Backup
```

#### Media Backup

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

#### Full Server Backup

```bash
# Backup entire app (excluding media and node_modules)
tar czf /var/www/arsip-layar/storage/backups/full_$(date +%Y%m%d).tar.gz \
  --exclude='node_modules' \
  --exclude='media/*' \
  --exclude='storage/uploads/*' \
  --exclude='storage/backups/*' \
  /var/www/arsip-layar/

# Backup database
mysqldump -u root arsip_layar | gzip > /root/arsip_layar_db_$(date +%Y%m%d).sql.gz

# Backup configs
tar czf /root/arsip_configs_$(date +%Y%m%d).tar.gz \
  /etc/nginx/sites-enabled/arsip-layar \
  /etc/php/8.5/fpm/pool.d/www.conf \
  /etc/fail2ban/jail.d/arsip.local \
  /etc/systemd/system/arsip-*.service \
  /etc/systemd/system/arsip-*.timer \
  /etc/arsip-layar/
```

### 3.9 Health Check

#### Service Verification

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

#### Application Verification

```bash
# Test HTTP response
curl -s -o /dev/null -w "%{http_code}" http://localhost/

# Test PHP processing
curl -s -o /dev/null -w "%{http_code}" http://localhost/?page=login

# Test API
curl -s http://localhost/api.php?op=health | head -5

# Test database connection
mysql -u arsip -p'ArsipDb_2026_xK7' arsip_layar -e "SELECT COUNT(*) FROM videos;"
```

#### Troubleshooting

| Issue | Check | Fix |
|-------|-------|-----|
| 502 Bad Gateway | PHP-FPM status | `systemctl restart php8.5-fpm` |
| 403 Forbidden | File permissions | `chown -R www-data:www-data /var/www/arsip-layar` |
| Database connection failed | MariaDB status | `systemctl restart mariadb` |
| HLS worker not running | Service status | `systemctl restart arsip-hls-worker` |
| Upload fails | PHP limits | Check `upload_max_filesize` in php.ini |
| Health check fails | Log file | `tail -f /var/log/arsip-health-check.log` |

---

## 4. Integration with Existing Files

### 4.1 Cross-Reference Table

| Topic | AUTODEPLOY.md | README.md | audit.md | rules.md |
|-------|---------------|-----------|----------|----------|
| Server specs | Section: Server Specifications | Section 2 | — | Section: VPS Configuration |
| Package installation | Section: Package Installation | — | — | — |
| Nginx config | Section: Nginx Configuration | Section 2 | — | — |
| PHP config | Section: PHP-FPM Configuration | Section 2 | — | — |
| Database | Section: Database Setup | Section 5 | — | Section: Database Change Protocol |
| Security | Section: Security Setup | Section 9 | Section 6 | Section: Non-Negotiable Rules |
| Git config | Section: Application Setup | — | — | Section: Git Protocol |
| Backup | Section: Backup & Restore | — | — | — |
| Health check | Section: Health Check | Section 10.4 | Section 16 | — |

### 4.2 How Each File References AUTODEPLOY.md

**README.md:**
- Add reference in Section 2 (Server Environment): "See AUTODEPLOY.md for complete setup instructions"

**audit.md:**
- Add reference in Executive Summary: "Server configuration documented in AUTODEPLOY.md"

**changelog.md:**
- Add entry for AUTODEPLOY.md creation

**rules.md:**
- Add reference in VPS Configuration: "Full server documentation in AUTODEPLOY.md"

---

## 5. Implementation Steps

### Step 1: Create AUTODEPLOY.md
Write the complete document with all sections defined above.

### Step 2: Update README.md
Add reference to AUTODEPLOY.md in Section 2 (Server Environment).

### Step 3: Update audit.md
Add reference to AUTODEPLOY.md in Executive Summary.

### Step 4: Update changelog.md
Add entry documenting AUTODEPLOY.md creation.

### Step 5: Update rules.md
Add reference to AUTODEPLOY.md in VPS Configuration section.

### Step 6: Commit and Push
```bash
git add AUTODEPLOY.md README.md audit.md changelog.md rules.md
git commit -m "docs(deploy): tambah AUTODEPLOY.md — server disaster recovery guide"
git push
```

---

## 6. Verification Checklist

- [ ] AUTODEPLOY.md created with all 9 sections
- [ ] Quick Start section is copy-paste ready
- [ ] All secrets included (DB password, SSH key, etc.)
- [ ] All service configs included (nginx, PHP-FPM, systemd)
- [ ] All security configs included (UFW, fail2ban, SSH)
- [ ] Database setup instructions complete
- [ ] Backup & restore instructions complete
- [ ] Health check procedures included
- [ ] Cross-references added to README.md, audit.md, changelog.md, rules.md
- [ ] Document passes markdown linting (if available)
- [ ] All commands tested on fresh Ubuntu 26.04 VPS (if possible)

---

## 7. Data Collected from Live Server

### 7.1 System Information
- OS: Ubuntu 26.04 LTS (Resolute Raccoon)
- Kernel: 7.0.0-27-generic
- CPU: Intel Xeon (SapphireRapids), 8 cores
- RAM: 9.2 GB
- Disk: 296 GB (9.6 GB used)
- Hostname: mvpsrv

### 7.2 Software Versions
- nginx: 1.28.3
- PHP: 8.5.4 (FPM)
- MariaDB: 11.8.6
- ffmpeg: 8.0.1
- Node.js: 22.23.2
- npm: 10.9.8
- Git: 2.53.0

### 7.3 Running Services
- nginx.service
- php8.5-fpm.service
- mariadb.service
- arsip-hls-worker.service
- arsip-health-check.timer (every 10 min)
- ssh.service

### 7.4 Database State
- Videos: 11
- Tokens: 1
- Admins: 1
- Tables: 14

### 7.5 Git Configuration
- User: Xboxchillz
- Email: alvin.krisdianto69@gmail.com
- Remote: git@github.com:frnshaxor/Xboxchillz.git
- Branch: master
- SSH Key: ~/.ssh/id_ed25519

### 7.6 Credentials
- Database password: ArsipDb_2026_xK7
- SSH key: ~/.ssh/id_ed25519 (ed25519)
- GitHub: frnshaxor/Xboxchillz
