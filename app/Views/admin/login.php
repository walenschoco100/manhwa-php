<section class="install-panel compact">
  <p class="eyebrow">Admin Area</p>
  <h1>Login CMS</h1>
  <form method="post" action="<?= app_url('/admin/login') ?>" class="form-grid single">
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
    <label>
      Username
      <input name="username" required autofocus>
    </label>
    <label>
      Password
      <input name="password" type="password" required>
    </label>
    <button class="primary wide" type="submit">Login</button>
  </form>
</section>

