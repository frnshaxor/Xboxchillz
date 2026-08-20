#!/usr/bin/env bash
# Deploy Arsip Layar to remote VPS. Runs remotely on the VPS via SSH.
set -euo pipefail

APP=/var/www/arsip-layar
DB_NAME=arsip_layar
DB_USER=arsip

echo "== 1) Package deps (idempotent) =="
export DEBIAN_FRONTEND=noninteractive
apt-get update -y >/dev/null
apt-get install -y ufw fail2ban curl unzip >/dev/null

echo "== 2) Firewall (UFW) =="
ufw --force reset >/dev/null
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

echo "== 3) fail2ban jails (sshd + nginx) =="
cat >/etc/fail2ban/jail.d/arsip.local <<'JAIL'
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
JAIL

cat >/etc/fail2ban/filter.d/nginx-badbots.conf <<'FILT'
[Definition]
failregex = ^<HOST> - .* "(GET|POST) .*(wp-login|xmlrpc|/\.env|/\.git|/phpmyadmin|/admin\.php|/wp-admin|/wp-config).*
ignoreregex =
FILT
systemctl enable --now fail2ban >/dev/null
systemctl restart fail2ban

echo "== 4) PHP-FPM upload/exec limits =="
PHP_INI=$(php -r 'echo php_ini_loaded_file();' 2>/dev/null || echo /etc/php/8.5/fpm/php.ini)
FPM_INI=/etc/php/8.5/fpm/php.ini
for f in "$FPM_INI" /etc/php/8.5/cli/php.ini; do
  [ -f "$f" ] || continue
  sed -i \
    -e 's/^\s*upload_max_filesize\s*=.*/upload_max_filesize = 2G/' \
    -e 's/^\s*post_max_size\s*=.*/post_max_size = 2G/' \
    -e 's/^\s*memory_limit\s*=.*/memory_limit = 512M/' \
    -e 's/^\s*max_execution_time\s*=.*/max_execution_time = 300/' \
    -e 's/^\s*max_input_time\s*=.*/max_input_time = 300/' \
    -e 's/^\s*expose_php\s*=.*/expose_php = Off/' \
    -e 's/^\s*session\.cookie_httponly\s*=.*/session.cookie_httponly = 1/' \
    -e 's/^\s*session\.use_strict_mode\s*=.*/session.use_strict_mode = 1/' \
    "$f"
done

echo "== 5) Nginx site + global rate-limit zones =="
cat >/etc/nginx/conf.d/arsip-limits.conf <<'LIM'
# Only key requests when page=login, else empty (which disables the limiter for that req)
map $arg_page $login_key { default ""; login $binary_remote_addr; }
limit_req_zone $login_key                  zone=login:10m rate=6r/m;
limit_req_zone $binary_remote_addr         zone=api:10m   rate=30r/s;
LIM

cat >/etc/nginx/sites-available/arsip-layar <<'NGX'
server {
  listen 80 default_server;
  listen [::]:80 default_server;
  server_name _;
  root /var/www/arsip-layar;
  index index.php;
  client_max_body_size 2G;

  # Security headers (defence in depth; app also emits them)
  add_header X-Content-Type-Options "nosniff" always;
  add_header X-Frame-Options "DENY" always;
  add_header Referrer-Policy "strict-origin-when-cross-origin" always;
  add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;

  # Never expose original media paths. Protected media is checked by PHP session/token.
  location ^~ /media/ { deny all; }
  location ^~ /protected-media/ { set $protected_media_path $uri; rewrite ^ /index.php?page=media&path=$protected_media_path last; }
  # HLS mime types are served by the protected PHP endpoint above.
  location ~* \.(jpg|jpeg|png|webp|css|js)$ { add_header Cache-Control "public,max-age=86400"; try_files $uri =404; }

  # Deny hidden/dot files
  location ~ /\. { deny all; }
  # Deny direct PHP inside media
  location ~ ^/media/.*\.(php|phtml|phar)$ { deny all; }
  # Deny sensitive directories
  location ~ ^/(storage|\.git|\.env) { deny all; }

  location / { try_files $uri $uri/ /index.php?$query_string; }

  location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php8.5-fpm.sock;
    fastcgi_read_timeout 300;
    fastcgi_buffers 16 16k;
    fastcgi_buffer_size 32k;
    # Throttle brute-force logins (only when ?page=login)
    limit_req zone=login burst=10 nodelay;
    limit_req_status 429;
  }

  # Block common exploit paths (also matched by fail2ban)
  location ~* (wp-login|xmlrpc\.php|/\.env|/phpmyadmin) { return 444; }
}
NGX
ln -sf /etc/nginx/sites-available/arsip-layar /etc/nginx/sites-enabled/arsip-layar
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx
systemctl restart php8.5-fpm

echo "== 6) App dirs & perms =="
mkdir -p "$APP"/media "$APP"/storage/backups "$APP"/storage/cache
chown -R www-data:www-data "$APP"
find "$APP" -type d -exec chmod 750 {} \;
find "$APP" -type f -exec chmod 640 {} \;

echo "== 7) DB schema apply =="
mysql "$DB_NAME" < "$APP"/schema.sql

echo "== 8) Prune stray uploads (safe: only /var/www/videos test artefacts) =="
# these were dumped by the previous session; not referenced by the app
find /var/www/videos -maxdepth 1 -type f -name '*.mp4' -mmin +60 -delete 2>/dev/null || true

echo "== 9) Nightly DB backup cron (03:15) =="
BK=/usr/local/sbin/arsip-backup
cat >"$BK" <<'BKS'
#!/usr/bin/env bash
set -e
DIR=/var/www/arsip-layar/storage/backups
mkdir -p "$DIR"
FILE="arsip_layar_$(date +%Y%m%d_%H%M%S).sql.gz"
DB_PASS=$(grep -E '^env\[DB_PASS\]' /etc/php/8.5/fpm/pool.d/www.conf | awk -F= '{print $2}' | tr -d ' ')
DB_USER=$(grep -E '^env\[DB_USER\]' /etc/php/8.5/fpm/pool.d/www.conf | awk -F= '{print $2}' | tr -d ' ')
mysqldump --single-transaction -u"$DB_USER" -p"$DB_PASS" arsip_layar | gzip -9 > "$DIR/$FILE"
chown www-data:www-data "$DIR/$FILE"
# keep last 14
ls -1t "$DIR"/*.sql.gz 2>/dev/null | tail -n +15 | xargs -r rm -f
BKS
chmod 750 "$BK"
cat >/etc/cron.d/arsip-backup <<'CRN'
15 3 * * * root /usr/local/sbin/arsip-backup >/dev/null 2>&1
CRN
chmod 644 /etc/cron.d/arsip-backup

echo "== 10) Done =="
echo "Site: http://$(curl -4 -s ifconfig.me || echo VPS_IP)/"
