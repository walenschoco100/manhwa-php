<?php

declare(strict_types=1);

namespace ManhwaPortal\Controllers;

use ManhwaPortal\Core\Database;
use ManhwaPortal\Services\ScraperService;
use ManhwaPortal\Services\TitleRepository;
use PDO;
use Throwable;

final class AdminController
{
    private TitleRepository $titles;
    private PDO $db;

    public function __construct()
    {
        $this->titles = new TitleRepository();
        $this->db = Database::pdo();
    }

    public function loginForm(): void
    {
        if (admin_is_logged_in()) {
            redirect('/admin');
        }

        render('admin/login', ['title' => 'Login Admin'], 'admin-auth');
    }

    public function login(): void
    {
        verify_csrf();
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $stmt = $this->db->prepare('SELECT * FROM admins WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            flash('Username atau password salah.', 'danger');
            redirect('/admin/login');
        }

        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        redirect('/admin');
    }

    public function logout(): void
    {
        verify_csrf();
        unset($_SESSION['admin_id'], $_SESSION['admin_username']);
        flash('Kamu sudah logout.');
        redirect('/admin/login');
    }

    public function dashboard(): void
    {
        require_admin();
        render('admin/dashboard', [
            'title' => 'Dashboard',
            'stats' => $this->titles->stats(),
            'latest' => $this->titles->latest(8),
            'logs' => $this->recentLogs(8),
        ], 'admin');
    }

    public function settings(): void
    {
        require_admin();
        render('admin/settings', [
            'title' => 'Settings',
            'settings' => $this->settingsMap(),
        ], 'admin');
    }

    public function saveSettings(): void
    {
        require_admin();
        verify_csrf();

        $allowed = ['site_name', 'site_description', 'canonical_url', 'source_url', 'default_cookie', 'download_assets'];
        $sql = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = CURRENT_TIMESTAMP'
            : 'INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)';
        $stmt = $this->db->prepare($sql);
        foreach ($allowed as $key) {
            $value = $key === 'download_assets' ? (isset($_POST[$key]) ? '1' : '0') : (string) ($_POST[$key] ?? '');
            $stmt->execute(['key' => $key, 'value' => trim($value)]);
        }

        flash('Settings tersimpan.');
        redirect('/admin/settings');
    }

    public function titles(): void
    {
        require_admin();
        render('admin/titles', [
            'title' => 'Data Judul',
            'titles' => $this->titles->allForAdmin(),
        ], 'admin');
    }

    public function titleForm(?int $id = null): void
    {
        require_admin();
        $titleRow = $id ? $this->titles->find($id) : null;
        render('admin/title-form', [
            'title' => $id ? 'Edit Judul' : 'Tambah Judul',
            'titleRow' => $titleRow,
            'genresText' => $titleRow ? $this->genresText((int) $titleRow['id']) : '',
        ], 'admin');
    }

    public function saveTitle(): void
    {
        require_admin();
        verify_csrf();

        if (trim((string) ($_POST['title'] ?? '')) === '') {
            flash('Judul wajib diisi.', 'danger');
            redirect('/admin/titles/new');
        }

        $id = $this->titles->saveManual($_POST);
        flash('Judul tersimpan.');
        redirect('/admin/titles/' . $id . '/edit');
    }

    public function scrape(): void
    {
        require_admin();
        render('admin/scrape', [
            'title' => 'Scrape Fresh',
            'settings' => $this->settingsMap(),
            'logs' => $this->recentLogs(30),
        ], 'admin');
    }

    public function runScrape(): void
    {
        require_admin();
        verify_csrf();

        $sourceUrl = trim((string) ($_POST['source_url'] ?? ''));
        $cookie = trim((string) ($_POST['cookie'] ?? ''));
        $limit = (int) ($_POST['limit'] ?? 10);
        $download = isset($_POST['download_assets']);

        try {
            $result = (new ScraperService())->scrape($sourceUrl, $limit, $cookie, $download);
            flash(sprintf('Scrape selesai: %d link, %d masuk, %d gagal.', $result['links'], $result['imported'], $result['failed']));
        } catch (Throwable $error) {
            flash('Scrape gagal: ' . $error->getMessage(), 'danger');
        }

        redirect('/admin/scrape');
    }

    public function logs(): void
    {
        require_admin();
        render('admin/logs', [
            'title' => 'Logs',
            'logs' => $this->recentLogs(120),
        ], 'admin');
    }

    public function notFound(): void
    {
        require_admin();
        http_response_code(404);
        render('public/404', ['title' => 'Tidak ditemukan']);
    }

    private function settingsMap(): array
    {
        $rows = $this->db->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return $settings;
    }

    private function recentLogs(int $limit): array
    {
        $stmt = $this->db->prepare('SELECT * FROM scrape_logs ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function genresText(int $titleId): string
    {
        $stmt = $this->db->prepare('SELECT g.name FROM genres g JOIN title_genres tg ON tg.genre_id = g.id WHERE tg.title_id = :title_id ORDER BY g.name ASC');
        $stmt->execute(['title_id' => $titleId]);
        return implode(', ', array_column($stmt->fetchAll(), 'name'));
    }
}
