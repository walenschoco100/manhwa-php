<section class="section-title">
  <div>
    <p class="eyebrow">Catalog</p>
    <h1><?= !empty($genreSlug) ? 'Genre: ' . e($genreSlug) : 'Search' ?></h1>
  </div>
</section>

<div class="genre-cloud wide-cloud">
  <?php foreach ($genres as $genre): ?>
    <a href="<?= app_url('/genre/' . $genre['slug']) ?>"><?= e($genre['name']) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$results): ?>
  <div class="empty-state">Tidak ada hasil.</div>
<?php else: ?>
  <div class="comic-grid">
    <?php foreach ($results as $comic): ?>
      <?php require APP_ROOT . '/Views/public/partials/comic-card.php'; ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

