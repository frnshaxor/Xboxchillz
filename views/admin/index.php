<?php
/**
 * Admin panel — sidebar + 7 tabs.
 * Expected variables: $me, $cats, $vids, $site, $upload_mb, $maintenance, $tab,
 *   $watermark_text, $watermark_position, $watermark_opacity, $cache_ver,
 *   $midtransEnabled, $midtransClientKey, $midtransMode, $midtransPrice
 */
$me    = $me ?? [];
$site  = $site ?? 'Arsip Layar';
$tab   = $tab ?? 'content';

$midtransEnabled   = $midtransEnabled   ?? false;
$midtransClientKey = $midtransClientKey ?? '';
$midtransMode      = $midtransMode      ?? 'sandbox';
$midtransPrice     = $midtransPrice     ?? 50000;

require VIEWS_DIR . '/layouts/head.php';
?>

<main id="main-content" class="admin"><div class="wrap admin-grid">
  <aside class="side">
    <div class="eyebrow">Control Panel</div>
    <h2><?= e($site) ?></h2>
    <p class="who">Halo, <b><?= e($me['name']) ?></b></p>
    <div class="kv">
      <div>Login terakhir</div>
      <span><?= e($me['last_login_at'] ?? '—') ?></span>
      <span><?= e($me['last_login_ip'] ?? '') ?></span>
    </div>
    <a class="button ghost" href="?page=logout" data-testid="admin-logout">Keluar</a>
  </aside>

  <section>
    <div class="eyebrow">Penerbitan · <?= date('d M Y') ?></div>
    <h1 class="pagetitle">Ruang kendali.</h1>

    <div class="tabs" role="tablist" data-initial="<?= e($tab) ?>" data-testid="admin-tabs">
      <button class="tab" data-tab="content">Konten</button>
      <button class="tab" data-tab="analytics">Analytics</button>
      <button class="tab" data-tab="security">Keamanan</button>
      <button class="tab" data-tab="account">Akun</button>
      <button class="tab" data-tab="system">Sistem</button>
      <button class="tab" data-tab="library">Perpustakaan</button>
      <button class="tab" data-tab="tokens">Akses Token</button>
      <button class="tab" data-tab="payments">Pembayaran</button>
    </div>

    <!-- ============ CONTENT ============ -->
    <div class="tabpane hidden" data-pane="content">
      <?php if (isset($_GET['uploaded'])): ?><p class="notice">Upload diterima. Transcode HLS jalan di background — refresh sebentar lagi.</p><?php endif; ?>
      <div class="grid2">
        <div class="panel">
          <h3><span class="material-symbols-rounded">cloud_upload</span> Unggah video MP4</h3>
          <form id="upload-form" method="post" enctype="multipart/form-data" action="?page=upload" data-testid="upload-form" class="bulk-upload-form">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <label>Judul<input name="title" placeholder="Kosongkan untuk auto-generate dari nama file" data-testid="upload-title"></label>
            <label>Kategori<select name="category_id" data-testid="upload-category">
              <option value="0">Tanpa kategori</option>
              <?php $cats->data_seek(0); while ($c = $cats->fetch_assoc()): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endwhile; ?>
            </select></label>
            <label>File MP4 (sampai 4 file sekaligus, maks <?= $upload_mb ?> MB per file)<input type="file" name="video[]" accept="video/mp4" multiple required data-testid="upload-file" id="upload-file-input"></label>
            <div id="upload-queue" class="upload-queue hidden" data-testid="upload-queue"></div>
            <button type="submit" data-testid="upload-submit" id="upload-submit-btn"><span class="material-symbols-rounded">upload</span> Mulai unggah &amp; transcode</button>
          </form>
        </div>
        <div class="panel">
          <h3><span class="material-symbols-rounded">brush</span> Identitas website</h3>
          <form method="post" action="?page=save-settings" data-testid="identity-form">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <label>Nama website<input name="site_name" value="<?= e($site) ?>" data-testid="setting-site-name"></label>
            <label>Deskripsi<textarea name="site_description" data-testid="setting-desc"><?= e(setting(Connection::getInstance()->db(), 'site_description', '')) ?></textarea></label>
            <button data-testid="identity-submit"><span class="material-symbols-rounded">save</span> Simpan pengaturan</button>
          </form>
        </div>
      </div>

      <div class="panel">
        <h3><span class="material-symbols-rounded">branding_watermark</span> Watermark video</h3>
        <div id="watermark-mount" data-testid="watermark-panel"
             data-text="<?= e($watermark_text) ?>"
             data-position="<?= e($watermark_position) ?>"
             data-opacity="<?= (int)$watermark_opacity ?>"></div>
      </div>

      <div class="panel">
        <div class="panel-header">
          <h3 style="margin:0"><span class="material-symbols-rounded">category</span> Kategori</h3>
          <span class="muted" style="font-size:12px"><?= (int)$cats2->num_rows ?> kategori</span>
        </div>
        <?php $cats2 = Connection::getInstance()->db()->query('SELECT * FROM categories ORDER BY name'); ?>
        <form class="cat-add-form" method="post" action="?page=add-category">
          <input type="hidden" name="csrf" value="<?= csrf() ?>">
          <input name="name" placeholder="Nama kategori baru" required data-testid="category-name" style="flex:1;min-width:0">
          <button data-testid="category-add" type="submit"><span class="material-symbols-rounded">add</span> Tambah</button>
        </form>
        <div class="chips">
          <?php while ($c = $cats2->fetch_assoc()): ?>
            <form method="post" action="?page=delete-category" class="chip" onsubmit="return confirm('Hapus kategori ini?')">
              <input type="hidden" name="csrf" value="<?= csrf() ?>">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <span class="chip-label"><?= e($c['name']) ?></span>
              <button class="chip-x" type="submit" aria-label="Hapus">×</button>
            </form>
          <?php endwhile; ?>
        </div>
      </div>

      <div class="panel">
        <div class="panel-header">
          <h3 style="margin:0"><span class="material-symbols-rounded">video_library</span> Perpustakaan video</h3>
          <span class="muted" style="font-size:12px"><?= (int)$vids->num_rows ?> video</span>
        </div>
        <?php if ($vids->num_rows > 0): ?>
        <div class="lib-grid">
          <?php $vids->data_seek(0); while ($v = $vids->fetch_assoc()): ?>
            <div class="lib-card">
              <a href="?page=watch&id=<?= $v['id'] ?>" class="lib-thumb">
                <?php $posterFs = APP_ROOT . '/' . ltrim($v['poster'], '/'); if (is_file($posterFs)): ?>
                  <img src="<?= e(poster_url($v['poster'])) ?>" alt="<?= e($v['title']) ?>" loading="lazy">
                <?php else: ?>
                  <span class="lib-thumb-id"><?= str_pad((string)$v['id'], 2, '0', STR_PAD_LEFT) ?></span>
                <?php endif; ?>
                <span class="lib-duration"><?= gmdate('i:s', (int)$v['duration_sec']) ?></span>
              </a>
              <div class="lib-info">
                <a href="?page=watch&id=<?= $v['id'] ?>" class="lib-title" title="<?= e($v['title']) ?>"><?= e($v['title']) ?></a>
                <span class="lib-meta">
                  <span class="badge <?= $v['status'] === 'ready' ? 'badge-success badge-dot' : 'badge-warning badge-dot' ?>"><?= e($v['status']) ?></span>
                  <span class="lib-views"><?= (int)$v['views'] ?> views</span>
                </span>
              </div>
              <div class="lib-actions">
                <a href="?page=watch&id=<?= $v['id'] ?>" class="ghost small" title="Tonton"><span class="material-symbols-rounded">play_arrow</span></a>
                <form method="post" action="?page=delete-video" onsubmit="return confirm('Hapus video ini beserta file?')" style="margin:0;display:inline">
                  <input type="hidden" name="csrf" value="<?= csrf() ?>">
                  <input type="hidden" name="id" value="<?= $v['id'] ?>">
                  <button class="ghost small" type="submit" title="Hapus" style="color:var(--danger,#e05252)"><span class="material-symbols-rounded">delete</span></button>
                </form>
              </div>
            </div>
          <?php endwhile; ?>
        </div>
        <?php else: ?>
          <div class="empty-state">
            <span class="material-symbols-rounded" style="font-size:40px;opacity:.25">videocam_off</span>
            <p class="muted" style="margin-top:8px">Belum ada video. Unggah satu di atas.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ============ LIBRARY ============ -->
    <div class="tabpane hidden" data-pane="library"><div id="library-mount" data-testid="library-panel"></div></div>

    <!-- ============ ANALYTICS ============ -->
    <div class="tabpane hidden" data-pane="analytics"><div id="analytics-mount" data-testid="analytics-panel"></div></div>

    <!-- ============ SECURITY ============ -->
    <div class="tabpane hidden" data-pane="security"><div id="security-mount" data-testid="security-panel" data-totp-enabled="<?= (int)$me['totp_enabled'] ?>"></div></div>

    <!-- ============ ACCOUNT ============ -->
    <div class="tabpane hidden" data-pane="account">
      <?php
        $err = $_GET['err'] ?? '';
        $errmap = ['pw' => 'Password saat ini salah.', 'input' => 'Nama minimal 2 karakter dan email harus valid.', 'dupe' => 'Email tersebut sudah dipakai.', 'short' => 'Password baru minimal 10 karakter.', 'mismatch' => 'Konfirmasi password tidak sama.'];
      ?>
      <?php if (isset($_GET['saved'])): ?><p class="notice">Profil disimpan.</p><?php endif; ?>
      <?php if (isset($_GET['pwsaved'])): ?><p class="notice">Password diperbarui. Gunakan password baru saat login berikutnya.</p><?php endif; ?>
      <?php if ($err && isset($errmap[$err])): ?><p class="notice err"><?= e($errmap[$err]) ?></p><?php endif; ?>

      <div class="grid2">
        <div class="panel">
          <h3>Profil admin</h3>
          <form method="post" action="?page=account-update" data-testid="account-form">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <label>Nama tampilan<input name="name" value="<?= e($me['name']) ?>" required data-testid="account-name"></label>
            <label>Email (username login)<input name="email" type="email" value="<?= e($me['email']) ?>" required data-testid="account-email"></label>
            <label>Konfirmasi dengan password saat ini<input name="current_password" type="password" autocomplete="current-password" required data-testid="account-current"></label>
            <button data-testid="account-submit">Simpan profil</button>
          </form>
        </div>
        <div class="panel">
          <h3>Ubah password</h3>
          <form method="post" action="?page=password-change" data-testid="password-form">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <label>Password saat ini<input name="current_password" type="password" autocomplete="current-password" required data-testid="pw-current"></label>
            <label>Password baru (min 10 karakter)<input name="new_password" type="password" autocomplete="new-password" required minlength="10" data-testid="pw-new"></label>
            <label>Ulangi password baru<input name="confirm_password" type="password" autocomplete="new-password" required minlength="10" data-testid="pw-confirm"></label>
            <button data-testid="pw-submit">Ubah password</button>
          </form>
          <p class="muted" style="margin-top:14px;font-size:12px">Gunakan password acak &amp; unik. Simpan di manager password (Bitwarden, 1Password).</p>
        </div>
      </div>
    </div>

    <!-- ============ SYSTEM ============ -->
    <div class="tabpane hidden" data-pane="system">
      <div id="system-mount" data-testid="system-panel" data-upload-mb="<?= $upload_mb ?>" data-maintenance="<?= (int)$maintenance ?>"></div>
      <div id="telegram-mount" data-testid="telegram-panel"></div>

      <div class="panel" style="margin-top:22px">
        <h3><span class="material-symbols-rounded">contact_mail</span> Halaman Kontak</h3>
        <p class="muted" style="margin-bottom:14px;font-size:13px">Atur link kontak yang tampil di halaman <a href="?page=contact" style="color:var(--accent)">/contact</a> dan modal token.</p>
        <form method="post" action="?page=save-contact" class="grid2">
          <input type="hidden" name="csrf" value="<?= csrf() ?>">
          <label>Judul Halaman
            <input name="contact_title" value="<?= e(setting(Connection::getInstance()->db(), 'contact_title', 'Hubungi Admin')) ?>" placeholder="Hubungi Admin">
          </label>
          <label>Subtitle
            <input name="contact_subtitle" value="<?= e(setting(Connection::getInstance()->db(), 'contact_subtitle', 'Pilih platform yang paling nyaman untuk Anda.')) ?>" placeholder="Pilih platform yang paling nyaman...">
          </label>
          <label>Link Telegram
            <input name="contact_telegram" type="url" value="<?= e(setting(Connection::getInstance()->db(), 'contact_telegram', '')) ?>" placeholder="https://t.me/username">
          </label>
          <label>Link WhatsApp
            <input name="contact_whatsapp" type="url" value="<?= e(setting(Connection::getInstance()->db(), 'contact_whatsapp', '')) ?>" placeholder="https://wa.me/62812...">
          </label>
          <label>Email
            <input name="contact_email" type="email" value="<?= e(setting(Connection::getInstance()->db(), 'contact_email', '')) ?>" placeholder="email@domain.com">
          </label>
          <div><button type="submit"><span class="material-symbols-rounded">save</span> Simpan Kontak</button></div>
        </form>
        <?php if (isset($_GET['contact_saved'])): ?><p class="notice">Pengaturan kontak disimpan.</p><?php endif; ?>
      </div>
    </div>

    <!-- ============ TOKENS ============ -->
    <div class="tabpane hidden" data-pane="tokens">
      <div id="tokens-mount" data-testid="tokens-panel"></div>
    </div>

    <!-- ============ PAYMENTS ============ -->
    <div class="tabpane hidden" data-pane="payments">
      <?php $serverConfigured = setting(Connection::getInstance()->db(), 'midtrans_server_key', '') !== ''; $clientConfigured = setting(Connection::getInstance()->db(), 'midtrans_client_key', '') !== ''; ?>
      <div class="panel">
        <h3><span class="material-symbols-rounded">payments</span> Midtrans Snap</h3>
        <p class="muted">Gunakan Sandbox untuk pengujian. Sebelum mode Production, set Notification URL Midtrans ke <code>https://DOMAIN-ANDA/?page=midtrans-notify</code>.</p>
        <?php if (isset($_GET['saved'])): ?><p class="notice">Pengaturan Midtrans disimpan.</p><?php endif; ?>
        <?php if (($_GET['midtrans_err'] ?? '') === 'https'): ?><p class="notice err">Mode Production membutuhkan HTTPS aktif. Gunakan Sandbox sampai sertifikat TLS terpasang.</p><?php endif; ?>
        <form method="post" action="?page=save-midtrans" class="grid2">
          <input type="hidden" name="csrf" value="<?= csrf() ?>">
          <label>Mode<select name="mode"><option value="sandbox" <?= $midtransMode === 'sandbox' ? 'selected' : '' ?>>Sandbox (uji coba)</option><option value="production" <?= $midtransMode === 'production' ? 'selected' : '' ?>>Production (pembayaran nyata)</option></select></label>
          <label>Harga satu token (IDR)<input type="number" name="price" min="1000" step="1000" value="<?= $midtransPrice ?>" required></label>
          <label>Client Key <small class="muted"><?= $clientConfigured ? '(tersimpan; kosongkan untuk mempertahankan)' : '' ?></small><input type="text" name="client_key" autocomplete="off" placeholder="<?= $clientConfigured ? 'Client Key tersimpan' : 'SB-Mid-client-…' ?>"></label>
          <label>Server Key <small class="muted"><?= $serverConfigured ? '(tersimpan; kosongkan untuk mempertahankan)' : '' ?></small><input type="password" name="server_key" autocomplete="new-password" placeholder="<?= $serverConfigured ? 'Server Key tersimpan' : 'SB-Mid-server-…' ?>"></label>
          <label class="switch"><input type="checkbox" name="enabled" value="1" <?= $midtransEnabled ? 'checked' : '' ?>><span class="track"><span class="knob"></span></span><span>Aktifkan checkout Midtrans</span></label>
          <div><button type="submit"><span class="material-symbols-rounded">save</span> Simpan pembayaran</button></div>
        </form>
      </div>
      <div class="panel"><h3>Order terbaru</h3><div id="payments-orders" class="muted">Memuat order…</div></div>
    </div>
  </section>
</div></main>

<?php require VIEWS_DIR . '/layouts/footer.php'; ?>
