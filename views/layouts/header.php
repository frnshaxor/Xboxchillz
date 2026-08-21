<?php
/**
 * Header layout — navigation bar.
 * Expected variables: $site
 */
$site = $site ?? 'Arsip Layar';
?>
<body>
<a href="#main-content" class="skip-link">Langsung ke konten utama</a>
<header class="top">
  <a class="brand" href="."><?= e($site) ?><span>&nbsp;·&nbsp;arsip</span></a>
  <button class="burger" aria-label="Menu" aria-expanded="false" data-testid="nav-burger" type="button">
    <span class="material-symbols-rounded" aria-hidden="true">menu</span>
  </button>
  <nav class="nav" id="nav">
    <a href="."><span class="material-symbols-rounded">home</span>Beranda</a>
    <a href="?page=contact"><span class="material-symbols-rounded">support_agent</span>Kontak</a>
    <?php if (admin()): ?>
      <a href="?page=admin" data-testid="nav-admin"><span class="material-symbols-rounded">tune</span>Panel</a>
      <a href="?page=logout" data-testid="nav-logout"><span class="material-symbols-rounded">logout</span>Keluar</a>
    <?php elseif (has_access()): ?>
      <div class="nav-token-info">
        <div class="nav-token-label">
          <span class="material-symbols-rounded">vpn_key</span>
          <span><?= e($_SESSION['access_token_label'] ?? 'Token Aktif') ?></span>
        </div>
        <div class="nav-token-date">Dibuat: <?= e($_SESSION['access_token_created_at'] ?? '') ?></div>
        <div class="nav-token-actions">
          <?php if (($_SESSION['access_token_expires_at'] ?? '') && strtotime($_SESSION['access_token_expires_at']) < time()): ?>
            <span class="nav-token-expired-badge">
              <span class="material-symbols-rounded">error</span> Kedaluwarsa
            </span>
            <a href="?page=contact" class="button ghost small"><span class="material-symbols-rounded">support_agent</span> Hubungi Admin</a>
          <?php endif; ?>
          <form method="post" action="?page=revoke-access" style="margin:0;display:inline">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <button type="submit" class="button ghost small"><span class="material-symbols-rounded">logout</span> Keluar</button>
          </form>
        </div>
      </div>
    <?php else: ?>
      <a href="?page=login" data-testid="nav-login"><span class="material-symbols-rounded">login</span>Masuk</a>
    <?php endif; ?>
  </nav>
</header>
