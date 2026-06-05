<section class="detail-hero">
  <div class="detail-cover cover">
    <?php if (!empty($comic['cover'])): ?>
      <img src="<?= e($comic['cover']) ?>" alt="<?= e($comic['title']) ?>">
    <?php else: ?>
      <span><?= e(substr($comic['title'], 0, 1)) ?></span>
    <?php endif; ?>
  </div>
  <div>
    <p class="eyebrow"><?= e($comic['type'] ?? 'Manhwa') ?></p>
    <h1><?= e($comic['title']) ?></h1>
    <div class="pill-row">
      <span><?= e($comic['status'] ?? 'Unknown') ?></span>
      <span><?= e((string) $comic['views']) ?> views</span>
      <span>Update <?= e(human_date($comic['updated_at'])) ?></span>
    </div>
    <p><?= nl2br(e($comic['synopsis'] ?? 'Belum ada synopsis.')) ?></p>
    <?php if (!empty($chapters)): ?>
      <a class="primary" href="<?= app_url('/read/' . $comic['slug'] . '/' . $chapters[0]['slug']) ?>">Baca Chapter Terbaru</a>
    <?php endif; ?>
  </div>
</section>

<section class="chapter-list">
  <div class="section-title">
    <h2>Daftar chapter</h2>
    <span><?= e((string) count($chapters)) ?> chapter</span>
  </div>
  <?php if (!$chapters): ?>
    <div class="empty-state">Belum ada chapter tersimpan.</div>
  <?php endif; ?>
  <?php foreach ($chapters as $chapter): ?>
    <a class="chapter-row" href="<?= app_url('/read/' . $comic['slug'] . '/' . $chapter['slug']) ?>">
      <strong><?= e($chapter['title']) ?></strong>
      <span><?= e(human_date($chapter['published_at'])) ?></span>
    </a>
  <?php endforeach; ?>
</section>

