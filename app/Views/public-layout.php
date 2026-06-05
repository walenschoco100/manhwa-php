<?php $siteName = setting('site_name', 'ManhwaLanded'); ?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? $siteName) ?> - <?= e($siteName) ?></title>
  <meta name="description" content="<?= e(setting('site_description', 'Portal katalog manhwa.')) ?>">
  <link rel="stylesheet" href="<?= app_url('/assets/css/app.css') ?>">
</head>
<body>
  <div class="site-shell">
    <header class="site-header">
      <a class="brand" href="<?= app_url('/') ?>">
        <span class="brand-mark">M</span>
        <span><?= e($siteName) ?></span>
      </a>
      <form class="search-box" action="<?= app_url('/search') ?>" method="get">
        <input name="q" value="<?= e($query ?? '') ?>" placeholder="Cari judul manhwa">
        <button type="submit">Cari</button>
      </form>
      <a class="admin-link" href="<?= app_url('/admin') ?>">Admin</a>
    </header>

    <?= $content ?>

    <footer class="site-footer">
      <span><?= e($siteName) ?></span>
      <span><?= e(setting('site_description', 'Fresh PHP CMS')) ?></span>
    </footer>
  </div>
</body>
</html>

