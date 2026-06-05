<?php /** @var string $content */ ?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'Install') ?></title>
  <link rel="stylesheet" href="<?= app_url('/assets/css/app.css') ?>">
</head>
<body class="install-body">
  <main class="install-shell">
    <?= $content ?>
  </main>
</body>
</html>

