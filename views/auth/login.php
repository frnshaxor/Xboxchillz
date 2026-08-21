<?php
/**
 * Login page template.
 * Expected variables: $error (string, optional)
 */
$error = $error ?? '';
$site = setting(Connection::getInstance()->db(), 'site_name', 'Arsip Layar');
$desc = setting(Connection::getInstance()->db(), 'site_description', '');
$midtransEnabled = false;
$midtransClientKey = '';
$cache_ver = setting(Connection::getInstance()->db(), 'cache_ver', '1');

require VIEWS_DIR . '/layouts/head.php';
require VIEWS_DIR . '/layouts/header.php';
?>

<main id="main-content" class="wrap"><div class="auth">
  <div class="eyebrow">Ruang Kerja / 2026</div>
  <h1>Masuk ke panel.</h1>
  <?php if (!empty($error)): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
  <form method="post" data-testid="login-form">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <label>Email<input name="email" type="email" autocomplete="username" required data-testid="login-email"></label>
    <label>Kata sandi<input name="password" type="password" autocomplete="current-password" required data-testid="login-password"></label>
    <label>Kode 2FA (opsional)<input name="totp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="6 digit" data-testid="login-totp"></label>
    <button data-testid="login-submit">Masuk</button>
  </form>
</div></main>

<?php require VIEWS_DIR . '/layouts/footer.php'; ?>
