<section class="admin-section">
  <h2>Scrape fresh dari source</h2>
  <p class="muted">Install baru tidak membawa data. Form ini mengambil listing source, membuka detail, lalu menyimpan judul, genre, chapter, dan gambar reader yang ditemukan.</p>
  <form method="post" action="<?= app_url('/admin/scrape') ?>" class="form-grid">
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
    <label class="span-2">
      Source listing URL
      <input name="source_url" value="<?= e($settings['source_url'] ?? '') ?>" placeholder="https://source-kamu.com/" required>
    </label>
    <label>
      Limit judul sekali scrape
      <input name="limit" type="number" min="1" max="50" value="10">
    </label>
    <label>
      Cookie opsional
      <input name="cookie" value="<?= e($settings['default_cookie'] ?? '') ?>">
    </label>
    <label class="check-row span-2">
      <input type="checkbox" name="download_assets" value="1" <?= ($settings['download_assets'] ?? '') === '1' ? 'checked' : '' ?>>
      Simpan gambar ke hosting
    </label>
    <button class="primary" type="submit">Mulai Scrape</button>
  </form>
</section>

<section class="admin-section">
  <h2>Scrape logs</h2>
  <?php if (!$logs): ?>
    <div class="empty-state">Belum ada log.</div>
  <?php endif; ?>
  <?php foreach ($logs as $log): ?>
    <p class="log-line <?= e($log['level']) ?>"><?= e($log['created_at']) ?> - <?= e($log['message']) ?></p>
  <?php endforeach; ?>
</section>

