<section class="admin-section">
  <div class="section-title">
    <h2>Semua judul</h2>
    <a class="primary" href="<?= app_url('/admin/titles/new') ?>">Tambah Manual</a>
  </div>
  <?php if (!$titles): ?>
    <div class="empty-state">Belum ada judul. Jalankan scrape fresh dulu.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Judul</th><th>Chapter</th><th>Status</th><th>Views</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($titles as $row): ?>
            <tr>
              <td><?= e($row['title']) ?></td>
              <td><?= e((string) $row['chapter_count']) ?></td>
              <td><?= e($row['status'] ?? '-') ?></td>
              <td><?= e((string) $row['views']) ?></td>
              <td><a href="<?= app_url('/admin/titles/' . $row['id'] . '/edit') ?>">Edit</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

