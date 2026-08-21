<?php
/**
 * Home page — gallery with hero section and category filters.
 * Expected variables: $site, $desc, $videos (mysqli result), $categories (mysqli result), $cat (int), $cache_ver
 */
$site      = $site      ?? 'Arsip Layar';
$desc      = $desc      ?? '';
$cat       = $cat       ?? 0;
$cache_ver = $cache_ver ?? '1';

require VIEWS_DIR . '/layouts/head.php';
require VIEWS_DIR . '/layouts/header.php';
?>

<main id="main-content" class="home"><div class="wrap">
  <section class="hero">
    <div>
      <span class="eyebrow">Koleksi Video · 2026</span>
      <h1>Layar untuk cerita yang ingin tinggal lebih lama.</h1>
      <p style="margin-top:18px"><?= e($desc) ?></p>
    </div>
  </section>
  <div class="filters" data-testid="filters">
    <a class="<?= $cat === 0 ? 'active' : '' ?>" href=".">Semua</a>
    <?php $categories->data_seek(0); while ($c = $categories->fetch_assoc()): ?>
      <a class="<?= $cat === (int)$c['id'] ? 'active' : '' ?>" href="?category=<?= $c['id'] ?>"><?= e($c['name']) ?></a>
    <?php endwhile; ?>
  </div>
  <?php if ($videos->num_rows === 0): ?>
    <div class="empty-state">
      <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <circle cx="100" cy="100" r="80" stroke="var(--border)" stroke-width="2" stroke-dasharray="6 4"/>
        <polygon points="85,65 85,135 140,100" fill="var(--accent)" opacity="0.3"/>
        <polygon points="85,65 85,135 140,100" stroke="var(--accent)" stroke-width="2" stroke-linejoin="round" fill="none"/>
      </svg>
      <span class="eyebrow">Koleksi kosong</span>
      <h2>Belum ada video.</h2>
      <p>Masuk ke panel admin dan unggah karya pertama Anda untuk memulai arsip.</p>
      <?php if (admin()): ?>
        <a href="?page=admin" class="button"><span class="material-symbols-rounded">cloud_upload</span> Unggah Video</a>
      <?php else: ?>
        <a href="?page=login" class="button"><span class="material-symbols-rounded">login</span> Masuk ke Panel</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
  <section class="gallery" data-testid="gallery">
    <?php while ($v = $videos->fetch_assoc()):
        $posterFs = APP_ROOT . '/' . ltrim($v['poster'], '/');
        $hasPoster = is_file($posterFs);
    ?>
      <article class="card">
        <a href="?page=watch&id=<?= $v['id'] ?>">
          <div class="poster">
            <?php if ($hasPoster): ?>
              <img src="<?= e(poster_url($v['poster'])) ?>" alt="<?= e($v['title']) ?>" loading="lazy">
            <?php else: ?>
              <span><?= str_pad((string)$v['id'], 2, '0', STR_PAD_LEFT) ?></span>
            <?php endif; ?>
          </div>
          <div class="cardmeta">
            <span class="kick"><?= e($v['category'] ?? 'Video') ?></span>
            <h3><?= e($v['title']) ?></h3>
            <small><?= gmdate('i:s', (int)$v['duration_sec']) ?> · <?= (int)$v['views'] ?> tayang</small>
          </div>
        </a>
      </article>
    <?php endwhile; ?>
  </section>
  <?php endif; ?>
</div></main>

<?php require VIEWS_DIR . '/layouts/footer.php'; ?>
