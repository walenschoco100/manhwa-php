<?php $flash = flash(); ?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'Admin') ?> - CMS</title>
  <meta name="robots" content="noindex,nofollow">
  <link rel="stylesheet" href="<?= app_url('/assets/css/app.css') ?>">
</head>
<body class="admin-body">
  <aside class="admin-sidebar">
    <a class="brand" href="<?= app_url('/admin') ?>">
      <span class="brand-mark">M</span>
      <span>CMS</span>
    </a>
    <nav>
      <a href="<?= app_url('/admin') ?>">Dashboard</a>
      <a href="<?= app_url('/admin/scrape') ?>">Scrape Fresh</a>
      <a href="<?= app_url('/admin/titles') ?>">Data Judul</a>
      <a href="<?= app_url('/admin/settings') ?>">Settings</a>
      <a href="<?= app_url('/admin/logs') ?>">Logs</a>
      <a href="<?= app_url('/') ?>" target="_blank">Lihat Website</a>
    </nav>
    <form method="post" action="<?= app_url('/admin/logout') ?>">
      <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
      <button class="ghost wide" type="submit">Logout</button>
    </form>
  </aside>

  <main class="admin-main">
    <header class="admin-topbar">
      <div>
        <p class="eyebrow">ManhwaLanded PHP</p>
        <h1><?= e($title ?? 'Admin') ?></h1>
      </div>
    </header>
    <?php if ($flash): ?>
      <div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <?= $content ?>
  </main>
</body>
</html>

