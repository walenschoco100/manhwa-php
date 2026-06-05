<section class="hero-band">
  <div>
    <p class="eyebrow">Fresh catalog</p>
    <h1><?= e(setting('site_name', 'ManhwaLanded')) ?></h1>
    <p><?= e(setting('site_description', 'Portal katalog manhwa.')) ?></p>
  </div>
  <div class="hero-stats">
    <span><strong><?= e((string) $stats['titles']) ?></strong> Judul</span>
    <span><strong><?= e((string) $stats['chapters']) ?></strong> Chapter</span>
    <span><strong><?= e((string) $stats['genres']) ?></strong> Genre</span>
  </div>
</section>

<section class="content-grid">
  <main>
    <div class="section-title">
      <h2>Update terbaru</h2>
      <a href="<?= app_url('/search') ?>">Lihat semua</a>
    </div>
    <?php if (!$latest): ?>
      <div class="empty-state">Belum ada data. Admin perlu menjalankan scrape fresh setelah install.</div>
    <?php else: ?>
      <div class="comic-grid">
        <?php foreach ($latest as $comic): ?>
          <?php require APP_ROOT . '/Views/public/partials/comic-card.php'; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>

  <aside class="side-panel">
    <h2>Popular</h2>
    <?php foreach ($popular as $index => $comic): ?>
      <a class="rank-row" href="<?= app_url('/manga/' . $comic['slug']) ?>">
        <span><?= e((string) ($index + 1)) ?></span>
        <strong><?= e($comic['title']) ?></strong>
      </a>
    <?php endforeach; ?>
    <h2>Genre</h2>
    <div class="genre-cloud">
      <?php foreach ($genres as $genre): ?>
        <a href="<?= app_url('/genre/' . $genre['slug']) ?>"><?= e($genre['name']) ?></a>
      <?php endforeach; ?>
    </div>
  </aside>
</section>

