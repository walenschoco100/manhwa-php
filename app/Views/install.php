<?php $old = $old ?? []; ?>
<section class="install-panel">
  <div>
    <p class="eyebrow">Fresh PHP CMS</p>
    <h1>Install ManhwaLanded</h1>
    <p class="muted">Install ini hanya membuat CMS kosong. Setelah login, kamu scrape sumber baru dari dashboard admin.</p>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert danger"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" action="<?= app_url('/install/run') ?>" class="form-grid">
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">

    <label class="span-2">
      Mode database
      <select name="db_driver">
        <option value="mysql" <?= ($old['db_driver'] ?? 'mysql') === 'mysql' ? 'selected' : '' ?>>MySQL / MariaDB untuk hosting</option>
        <option value="sqlite" <?= ($old['db_driver'] ?? '') === 'sqlite' ? 'selected' : '' ?>>SQLite lokal untuk simulasi</option>
      </select>
    </label>

    <label>
      Nama website
      <input name="app_name" value="<?= e($old['app_name'] ?? 'ManhwaLanded') ?>" required>
    </label>

    <label>
      URL website
      <input name="app_url" value="<?= e($old['app_url'] ?? '') ?>" placeholder="https://domain-kamu.com">
    </label>

    <label>
      DB host
      <input name="db_host" value="<?= e($old['db_host'] ?? 'localhost') ?>" required>
    </label>

    <label>
      DB port
      <input name="db_port" value="<?= e($old['db_port'] ?? '3306') ?>" required>
    </label>

    <label>
      DB name
      <input name="db_name" value="<?= e($old['db_name'] ?? '') ?>" required>
    </label>

    <label>
      DB user
      <input name="db_user" value="<?= e($old['db_user'] ?? '') ?>" required>
    </label>

    <label>
      DB password
      <input name="db_password" type="password">
    </label>

    <label>
      Admin username
      <input name="admin_user" value="<?= e($old['admin_user'] ?? 'admin') ?>" required>
    </label>

    <label>
      Admin password
      <input name="admin_password" type="password" minlength="8" required>
    </label>

    <button class="primary wide span-2" type="submit">Install CMS</button>
  </form>
</section>
