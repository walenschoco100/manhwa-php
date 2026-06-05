<article class="comic-card">
  <a href="<?= app_url('/manga/' . $comic['slug']) ?>">
    <div class="cover">
      <?php if (!empty($comic['cover'])): ?>
        <img src="<?= e($comic['cover']) ?>" alt="<?= e($comic['title']) ?>" loading="lazy">
      <?php else: ?>
        <span><?= e(substr($comic['title'], 0, 1)) ?></span>
      <?php endif; ?>
    </div>
    <div class="comic-meta">
      <h3><?= e($comic['title']) ?></h3>
      <p><?= e($comic['status'] ?? 'Unknown') ?> · <?= e($comic['type'] ?? 'Manhwa') ?></p>
    </div>
  </a>
</article>

