<?php $flash = flash(); ?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'Login') ?></title>
  <link rel="stylesheet" href="<?= app_url('/assets/css/app.css') ?>">
</head>
<body class="install-body">
  <main class="install-shell">
    <?php if ($flash): ?>
      <div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <?= $content ?>
  </main>
</body>
</html>

