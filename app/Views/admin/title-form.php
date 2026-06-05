<?php $row = $titleRow ?? []; ?>
<form method="post" action="<?= app_url('/admin/titles/save') ?>" class="admin-section form-grid">
  <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="id" value="<?= e((string) ($row['id'] ?? 0)) ?>">
  <label>
    Judul
    <input name="title" value="<?= e($row['title'] ?? '') ?>" required>
  </label>
  <label>
    Slug
    <input name="slug" value="<?= e($row['slug'] ?? '') ?>">
  </label>
  <label>
    Cover URL/path
    <input name="cover" value="<?= e($row['cover'] ?? '') ?>">
  </label>
  <label>
    Genre, pisahkan koma
    <input name="genres" value="<?= e($genresText ?? '') ?>">
  </label>
  <label>
    Status
    <input name="status" value="<?= e($row['status'] ?? '') ?>">
  </label>
  <label>
    Type
    <input name="type" value="<?= e($row['type'] ?? 'Manhwa') ?>">
  </label>
  <label>
    Rating
    <input name="rating" type="number" min="0" max="10" step="0.1" value="<?= e((string) ($row['rating'] ?? 0)) ?>">
  </label>
  <label class="span-2">
    Synopsis
    <textarea name="synopsis" rows="8"><?= e($row['synopsis'] ?? '') ?></textarea>
  </label>
  <button class="primary" type="submit">Simpan Judul</button>
</form>

