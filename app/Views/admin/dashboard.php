<section class="stat-grid">
  <article><strong><?= e((string) $stats['titles']) ?></strong><span>Judul</span></article>
  <article><strong><?= e((string) $stats['chapters']) ?></strong><span>Chapter</span></article>
  <article><strong><?= e((string) $stats['genres']) ?></strong><span>Genre</span></article>
</section>

<section class="admin-section">
  <div class="section-title">
    <h2>Judul terbaru</h2>
    <a class="secondary" href="<?= app_url('/admin/scrape') ?>">Scrape Baru</a>
  </div>
  <?php if (!$latest): ?>
    <div class="empty-state">CMS masih kosong. Jalankan scrape fresh dari dashboard.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Judul</th><th>Status</th><th>Update</th></tr></thead>
        <tbody>
          <?php foreach ($latest as $row): ?>
            <tr>
              <td><a href="<?= app_url('/admin/titles/' . $row['id'] . '/edit') ?>"><?= e($row['title']) ?></a></td>
              <td><?= e($row['status'] ?? '-') ?></td>
              <td><?= e(human_date($row['updated_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="admin-section">
  <h2>Log terakhir</h2>
  <?php foreach ($logs as $log): ?>
    <p class="log-line <?= e($log['level']) ?>"><?= e($log['created_at']) ?> - <?= e($log['message']) ?></p>
  <?php endforeach; ?>
</section>

