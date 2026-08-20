-- Arsip Layar schema v2 (idempotent)
CREATE TABLE IF NOT EXISTS admins(
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  totp_secret VARCHAR(64) DEFAULT NULL,
  totp_enabled TINYINT DEFAULT 0,
  last_login_at TIMESTAMP NULL,
  last_login_ip VARCHAR(64) NULL,
  active TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE admins ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(64) DEFAULT NULL;
ALTER TABLE admins ADD COLUMN IF NOT EXISTS totp_enabled TINYINT DEFAULT 0;
ALTER TABLE admins ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMP NULL;
ALTER TABLE admins ADD COLUMN IF NOT EXISTS last_login_ip VARCHAR(64) NULL;

CREATE TABLE IF NOT EXISTS categories(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(120) UNIQUE NOT NULL);

CREATE TABLE IF NOT EXISTS videos(
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) UNIQUE NOT NULL,
  category_id INT DEFAULT 0,
  poster VARCHAR(500),
  source VARCHAR(500),
  duration_sec INT DEFAULT 0,
  size_bytes BIGINT DEFAULT 0,
  views INT DEFAULT 0,
  status VARCHAR(30) DEFAULT 'processing',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE videos ADD COLUMN IF NOT EXISTS duration_sec INT DEFAULT 0;
ALTER TABLE videos ADD COLUMN IF NOT EXISTS size_bytes BIGINT DEFAULT 0;
ALTER TABLE videos ADD COLUMN IF NOT EXISTS views INT DEFAULT 0;

CREATE TABLE IF NOT EXISTS settings(name VARCHAR(100) PRIMARY KEY,value TEXT NOT NULL);
INSERT IGNORE INTO settings(name,value) VALUES
 ('site_name','Arsip Layar'),
 ('site_description','Koleksi video pilihan, diputar dengan nyaman di setiap koneksi.'),
 ('accent','#d96b45'),
 ('theme_key','obsidian'),
 ('maintenance_mode','0'),
 ('upload_max_mb','2048'),
 ('watermark_text','Codename F'),
 ('watermark_position','br'),
 ('watermark_opacity','60'),
 ('telegram_bot_token',''),
 ('telegram_chat_id',''),
 ('telegram_enabled','0'),
 ('midtrans_enabled','0'),
 ('midtrans_mode','sandbox'),
 ('midtrans_client_key',''),
 ('midtrans_server_key',''),
 ('midtrans_token_price','50000'),
 ('contact_url',''),
 ('contact_label','Hubungi admin'),
 ('cache_ver','1');

CREATE TABLE IF NOT EXISTS analytics_events(
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  event VARCHAR(50) NOT NULL,
  path VARCHAR(255) NOT NULL,
  visitor_hash CHAR(64) NOT NULL,
  video_id INT DEFAULT NULL,
  progress_sec INT DEFAULT NULL,
  device VARCHAR(40),
  browser VARCHAR(80),
  referrer VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(event),INDEX(created_at),INDEX(visitor_hash),INDEX(video_id)
);
ALTER TABLE analytics_events ADD COLUMN IF NOT EXISTS video_id INT DEFAULT NULL;
ALTER TABLE analytics_events ADD COLUMN IF NOT EXISTS progress_sec INT DEFAULT NULL;

CREATE TABLE IF NOT EXISTS login_attempts(
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(64) NOT NULL,
  email VARCHAR(190),
  success TINYINT DEFAULT 0,
  reason VARCHAR(60) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(ip),INDEX(created_at)
);

CREATE TABLE IF NOT EXISTS activity_log(
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT DEFAULT NULL,
  action VARCHAR(80) NOT NULL,
  detail VARCHAR(500),
  ip VARCHAR(64),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(admin_id),INDEX(created_at)
);

CREATE TABLE IF NOT EXISTS backups(
  id INT AUTO_INCREMENT PRIMARY KEY,
  file VARCHAR(255) NOT NULL,
  size_bytes BIGINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS access_tokens(
  id INT AUTO_INCREMENT PRIMARY KEY,
  token VARCHAR(64) UNIQUE NOT NULL,
  label VARCHAR(120) NOT NULL,
  contact_type VARCHAR(20) NOT NULL DEFAULT 'telegram',
  contact_value VARCHAR(200) NOT NULL DEFAULT '',
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_by INT DEFAULT NULL,
  use_count INT DEFAULT 0,
  last_used_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(token),
  INDEX(status)
);

CREATE TABLE IF NOT EXISTS payment_orders(
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  order_id VARCHAR(50) UNIQUE NOT NULL,
  buyer_name VARCHAR(120) NOT NULL,
  buyer_contact VARCHAR(200) NOT NULL,
  amount INT NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'pending',
  snap_token VARCHAR(100) DEFAULT NULL,
  access_secret_hash CHAR(64) NOT NULL,
  token_id INT DEFAULT NULL,
  midtrans_transaction_id VARCHAR(100) DEFAULT NULL,
  payment_type VARCHAR(60) DEFAULT NULL,
  notification_json TEXT DEFAULT NULL,
  client_ip VARCHAR(64) DEFAULT NULL,
  paid_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(status), INDEX(created_at), INDEX(token_id)
);

CREATE TABLE IF NOT EXISTS video_heatmap(
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  video_id INT NOT NULL,
  viewer_hash CHAR(64) NOT NULL,
  second_index SMALLINT UNSIGNED NOT NULL,
  view_count SMALLINT UNSIGNED DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_video_viewer_sec (video_id, viewer_hash, second_index),
  INDEX(video_id), INDEX(created_at)
);

CREATE TABLE IF NOT EXISTS webhook_retry(
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  source VARCHAR(30) NOT NULL DEFAULT 'midtrans',
  payload TEXT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  attempts TINYINT DEFAULT 0,
  max_attempts TINYINT DEFAULT 5,
  next_retry_at TIMESTAMP NULL,
  last_error TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(status), INDEX(next_retry_at)
);
