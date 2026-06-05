<section class="admin-section">
  <?php if (!$logs): ?>
    <div class="empty-state">Belum ada log.</div>
  <?php endif; ?>
  <?php foreach ($logs as $log): ?>
    <article class="log-card">
      <strong><?= e(strtoupper($log['level'])) ?></strong>
      <span><?= e($log['created_at']) ?></span>
      <p><?= e($log['message']) ?></p>
      <?php if (!empty($log['context'])): ?>
        <code><?= e($log['context']) ?></code>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</section>

