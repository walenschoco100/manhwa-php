<form method="post" action="<?= app_url('/admin/settings') ?>" class="admin-section form-grid">
  <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
  <label>
    Nama website
    <input name="site_name" value="<?= e($settings['site_name'] ?? '') ?>">
  </label>
  <label>
    Canonical URL
    <input name="canonical_url" value="<?= e($settings['canonical_url'] ?? '') ?>" placeholder="https://domain-kamu.com">
  </label>
  <label class="span-2">
    Meta description
    <textarea name="site_description"><?= e($settings['site_description'] ?? '') ?></textarea>
  </label>
  <label>
    Source URL default
    <input name="source_url" value="<?= e($settings['source_url'] ?? '') ?>">
  </label>
  <label>
    Cookie default
    <input name="default_cookie" value="<?= e($settings['default_cookie'] ?? '') ?>">
  </label>
  <label class="check-row span-2">
    <input type="checkbox" name="download_assets" value="1" <?= ($settings['download_assets'] ?? '') === '1' ? 'checked' : '' ?>>
    Download cover dan gambar chapter ke hosting
  </label>
  <button class="primary" type="submit">Simpan Settings</button>
</form>

