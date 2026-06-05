<section class="reader-head">
  <a href="<?= app_url('/manga/' . $chapter['title_slug']) ?>">Kembali</a>
  <div>
    <p class="eyebrow"><?= e($chapter['manga_title']) ?></p>
    <h1><?= e($chapter['title']) ?></h1>
  </div>
</section>

<section class="reader-images">
  <?php if (!$images): ?>
    <div class="empty-state">Chapter ini belum punya gambar reader. Jalankan scrape dengan opsi gambar, atau edit data chapter nanti.</div>
  <?php endif; ?>
  <?php foreach ($images as $image): ?>
    <img src="<?= e($image['local_path'] ?: $image['image_url']) ?>" alt="<?= e($chapter['title']) ?>" loading="lazy">
  <?php endforeach; ?>
</section>

