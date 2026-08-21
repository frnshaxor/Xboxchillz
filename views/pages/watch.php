<?php
/**
 * Watch page — player (unlocked) or preview + token gate.
 * Expected variables: $v (video row), $base (media base path), $hlsInfo (array), $userHasAccess (bool),
 *   $tokenError (string), $currentUrl (string), $watermark_text, $watermark_position, $watermark_opacity,
 *   $midtransEnabled, $midtransClientKey, $midtransPrice, $site
 */
$v                = $v ?? [];
$base             = $base ?? '';
$hlsInfo          = $hlsInfo ?? ['master' => false, '720p' => false, '360p' => false, 'preview' => false];
$userHasAccess    = $userHasAccess ?? false;
$tokenError       = $tokenError ?? '';
$currentUrl       = $currentUrl ?? '';
$watermark_text     = $watermark_text ?? 'Codename F';
$watermark_position = $watermark_position ?? 'br';
$watermark_opacity  = $watermark_opacity ?? 60;
$midtransEnabled   = $midtransEnabled ?? false;
$midtransClientKey = $midtransClientKey ?? '';
$midtransPrice     = $midtransPrice ?? 50000;
$site              = $site ?? 'Arsip Layar';

require VIEWS_DIR . '/layouts/head.php';
require VIEWS_DIR . '/layouts/header.php';
?>

<main id="main-content" class="watch"><div class="wrap">
  <div class="eyebrow"><span class="material-symbols-rounded" style="font-size:14px">play_circle</span> Now Playing · <?= e($v['category'] ?? 'Video') ?></div>
  <h1><?= e($v['title']) ?></h1>

  <?php if ($userHasAccess): ?>
  <!-- ── UNLOCKED: player normal ── -->
  <div class="player-wrap" data-video-id="<?= (int)$v['id'] ?>"
       data-hls="<?= $hlsInfo['master'] ? e(protected_media_url($base . '/master.m3u8')) : '' ?>"
       data-hls-720="<?= $hlsInfo['720p'] ? e(protected_media_url($base . '/720p.m3u8')) : '' ?>"
       data-hls-360="<?= $hlsInfo['360p'] ? e(protected_media_url($base . '/360p.m3u8')) : '' ?>"
       data-src="<?= e(protected_media_url($v['source'])) ?>"
       data-poster="<?= e(poster_url($v['poster'])) ?>"
       data-wm-text="<?= e($watermark_text) ?>"
       data-wm-pos="<?= e($watermark_position) ?>"
       data-wm-opacity="<?= (int)$watermark_opacity ?>">
    <video id="plyr-player" data-testid="video-player" playsinline crossorigin poster="<?= e(poster_url($v['poster'])) ?>">
      <?php if ($hlsInfo['720p']): ?><source src="<?= e(protected_media_url($base . '/720p.m3u8')) ?>" type="application/x-mpegURL" size="720"><?php endif; ?>
      <?php if ($hlsInfo['360p']): ?><source src="<?= e(protected_media_url($base . '/360p.m3u8')) ?>" type="application/x-mpegURL" size="360"><?php endif; ?>
      <source src="<?= e(protected_media_url($v['source'])) ?>" type="video/mp4">
    </video>
    <div class="watermark wm-<?= e($watermark_position) ?>" data-testid="watermark" style="opacity:<?= number_format($watermark_opacity / 100, 2) ?>"><?= e($watermark_text) ?></div>
  </div>
  <div class="watch-actions" aria-label="Pilihan video">
    <?php if ($hlsInfo['master']): ?>
    <div class="quality-picker"><span>Kualitas</span>
      <button class="ghost small active" type="button" data-quality="0">Auto</button>
      <?php if ($hlsInfo['720p']): ?><button class="ghost small" type="button" data-quality="720">720p</button><?php endif; ?>
      <?php if ($hlsInfo['360p']): ?><button class="ghost small" type="button" data-quality="360">360p</button><?php endif; ?>
    </div>
    <?php endif; ?>
    <a class="button ghost small" href="?page=download&id=<?= (int)$v['id'] ?>"><span class="material-symbols-rounded">download</span> Unduh MP4</a>
  </div>
  <div class="video-meta">
    <div><span class="material-symbols-rounded">schedule</span><?= gmdate('i:s', (int)$v['duration_sec']) ?></div>
    <div><span class="material-symbols-rounded">visibility</span><?= (int)$v['views'] ?> tayang</div>
    <div><span class="material-symbols-rounded">save</span><?= number_format(((int)$v['size_bytes']) / 1048576, 1) ?> MB</div>
    <?php if ($hlsInfo['master']): ?><div><span class="material-symbols-rounded">high_quality</span>HLS adaptive</div><?php endif; ?>
  </div>

  <?php else: ?>
  <!-- ── PREVIEW: 15s preview + token gate ── -->
  <div class="player-wrap preview-player" id="preview-player"
       data-video-id="<?= (int)$v['id'] ?>"
       data-preview-url="<?= $hlsInfo['preview'] ? e(preview_url($v['source'])) : '' ?>"
       data-src="<?= e(protected_media_url($v['source'])) ?>"
       data-poster="<?= e(poster_url($v['poster'])) ?>"
       data-preview-sec="15"
       data-wm-text="<?= e($watermark_text) ?>"
       data-wm-pos="<?= e($watermark_position) ?>"
       data-wm-opacity="<?= (int)$watermark_opacity ?>">
    <video id="preview-video" data-testid="preview-player" playsinline crossorigin
           poster="<?= e(poster_url($v['poster'])) ?>">
      <?php if ($hlsInfo['preview']): ?>
        <source src="<?= e(preview_url($v['source'])) ?>" type="video/mp4">
      <?php endif; ?>
    </video>
    <div class="watermark wm-<?= e($watermark_position) ?>" style="opacity:<?= number_format($watermark_opacity / 100, 2) ?>"><?= e($watermark_text) ?></div>
    <div class="preview-overlay" id="preview-overlay">
      <div class="preview-overlay-content">
        <span class="material-symbols-rounded" style="font-size:42px;color:var(--accent);opacity:0.85">lock</span>
        <p class="preview-msg">Preview berakhir. Masukkan token untuk menonton penuh.</p>
        <div class="preview-actions">
          <button class="button" id="open-token-modal" type="button">
            <span class="material-symbols-rounded">vpn_key</span> Masukkan Token
          </button>
          <a class="button ghost" href="?page=contact">
            <span class="material-symbols-rounded">support_agent</span> Beli Akses Token
          </a>
        </div>
      </div>
    </div>
  </div>
  <div class="video-meta">
    <div><span class="material-symbols-rounded">schedule</span><?= gmdate('i:s', (int)$v['duration_sec']) ?></div>
    <div><span class="material-symbols-rounded">play_circle</span>Preview 15 detik</div>
  </div>

  <!-- ── TOKEN MODAL ── -->
  <div class="token-modal-overlay" id="token-modal" role="dialog" aria-modal="true" aria-labelledby="token-modal-title">
    <div class="token-modal-card">
      <button class="token-modal-close" id="close-token-modal" type="button" aria-label="Tutup">&times;</button>
      <div class="token-modal-icon"><span class="material-symbols-rounded">vpn_key</span></div>
      <h2 id="token-modal-title">Token Akses Diperlukan</h2>
      <p class="token-modal-desc">Masukkan token yang diberikan untuk mengakses seluruh koleksi video.</p>
      <?php if ($tokenError): ?>
        <p class="token-modal-error"><?= e($tokenError) ?></p>
      <?php endif; ?>
      <form method="post" action="?page=verify-token" class="token-modal-form">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="redirect" value="<?= e($currentUrl) ?>">
        <input
          type="text"
          name="token"
          id="token-input"
          placeholder="XXXX-XXXX-XXXX"
          autocomplete="off"
          autocapitalize="characters"
          spellcheck="false"
          maxlength="14"
          required
          class="token-input"
        >
        <button type="submit" class="button token-submit">
          <span class="material-symbols-rounded">login</span> Verifikasi Token
        </button>
      </form>
      <p class="token-modal-hint">Belum punya token? Beli atau hubungi admin untuk mendapatkan akses.</p>
      <a class="token-contact-btn" href="?page=contact">
        <span class="material-symbols-rounded">support_agent</span> Beli Akses Token
      </a>
      <?php if ($midtransEnabled && $midtransClientKey): ?>
      <div class="token-purchase" id="token-purchase" data-price="<?= $midtransPrice ?>">
        <strong>Beli token — Rp<?= number_format($midtransPrice, 0, ',', '.') ?></strong>
        <p>Bayar aman melalui Midtrans. Token ditampilkan otomatis setelah pembayaran terkonfirmasi.</p>
        <input id="buyer-name" maxlength="120" placeholder="Nama Anda" autocomplete="name">
        <input id="buyer-contact" maxlength="200" placeholder="Username / nomor Telegram atau WhatsApp">
        <button class="button" id="buy-token" type="button"><span class="material-symbols-rounded">payments</span> Beli via Midtrans</button>
        <p class="token-purchase-status" id="purchase-status" aria-live="polite"></p>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

</div></main>

<?php require VIEWS_DIR . '/layouts/footer.php'; ?>
