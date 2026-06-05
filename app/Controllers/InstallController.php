<?php

declare(strict_types=1);

namespace ManhwaPortal\Controllers;

use PDO;
use Throwable;

final class InstallController
{
    public function show(): void
    {
        if (is_installed()) {
            redirect('/admin');
        }

        render('install', [
            'title' => 'Install ManhwaLanded PHP',
            'error' => null,
        ], 'install');
    }

    public function run(): void
    {
        if (is_installed()) {
            redirect('/admin');
        }

        verify_csrf();

        $payload = [
            'db_driver' => trim((string) ($_POST['db_driver'] ?? 'mysql')),
            'app_name' => trim((string) ($_POST['app_name'] ?? 'ManhwaLanded')),
            'app_url' => rtrim(trim((string) ($_POST['app_url'] ?? '')), '/'),
            'db_host' => trim((string) ($_POST['db_host'] ?? 'localhost')),
            'db_port' => trim((string) ($_POST['db_port'] ?? '3306')),
            'db_name' => trim((string) ($_POST['db_name'] ?? '')),
            'db_user' => trim((string) ($_POST['db_user'] ?? '')),
            'db_password' => (string) ($_POST['db_password'] ?? ''),
            'admin_user' => trim((string) ($_POST['admin_user'] ?? 'admin')),
            'admin_password' => (string) ($_POST['admin_password'] ?? ''),
        ];

        try {
            $this->validate($payload);
            $pdo = $this->connect($payload);
            $this->migrate($pdo);
            $this->seed($pdo, $payload);
            $this->writeConfig($payload);
            flash('CMS berhasil di-install. Silakan login.');
            redirect('/admin/login');
        } catch (Throwable $error) {
            render('install', [
                'title' => 'Install ManhwaLanded PHP',
                'error' => $error->getMessage(),
                'old' => $payload,
            ], 'install');
        }
    }

    private function validate(array $payload): void
    {
        foreach (['app_name', 'db_host', 'db_name', 'db_user', 'admin_user', 'admin_password'] as $field) {
            if ($payload['db_driver'] === 'sqlite' && in_array($field, ['db_host', 'db_name', 'db_user'], true)) {
                continue;
            }
            if ($payload[$field] === '') {
                throw new \RuntimeException('Field wajib belum lengkap.');
            }
        }

        if (strlen($payload['admin_password']) < 8) {
            throw new \RuntimeException('Password admin minimal 8 karakter.');
        }
    }

    private function connect(array $payload): PDO
    {
        if ($payload['db_driver'] === 'sqlite') {
            if (!is_dir(STORAGE_PATH)) {
                mkdir(STORAGE_PATH, 0755, true);
            }

            $path = STORAGE_PATH . '/local.sqlite';
            $pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON');
            return $pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $payload['db_host'],
            $payload['db_port'],
            $payload['db_name']
        );

        return new PDO($dsn, $payload['db_user'], $payload['db_password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    private function migrate(PDO $pdo): void
    {
        $schemaFile = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? BASE_PATH . '/database/schema-sqlite.sql'
            : BASE_PATH . '/database/schema.sql';
        $schema = file_get_contents($schemaFile);
        if ($schema === false) {
            throw new \RuntimeException('Schema database tidak ditemukan.');
        }

        foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
            $pdo->exec($statement);
        }
    }

    private function seed(PDO $pdo, array $payload): void
    {
        $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (:username, :password_hash)');
        $stmt->execute([
            'username' => $payload['admin_user'],
            'password_hash' => password_hash($payload['admin_password'], PASSWORD_DEFAULT),
        ]);

        $settings = [
            'site_name' => $payload['app_name'],
            'site_description' => 'Portal katalog manhwa fresh tanpa WordPress.',
            'canonical_url' => $payload['app_url'],
            'source_url' => '',
            'default_cookie' => '',
            'download_assets' => '0',
        ];

        $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)');
        foreach ($settings as $key => $value) {
            $stmt->execute(['key' => $key, 'value' => $value]);
        }
    }

    private function writeConfig(array $payload): void
    {
        if (!is_dir(STORAGE_PATH)) {
            mkdir(STORAGE_PATH, 0755, true);
        }

        $config = [
            'app_name' => $payload['app_name'],
            'app_url' => $payload['app_url'],
            'database' => [
                'driver' => $payload['db_driver'],
                'host' => $payload['db_host'],
                'port' => $payload['db_port'],
                'name' => $payload['db_name'],
                'user' => $payload['db_user'],
                'password' => $payload['db_password'],
                'path' => STORAGE_PATH . '/local.sqlite',
            ],
        ];

        $encoded = var_export($config, true);
        $contents = "<?php\n\nreturn {$encoded};\n";
        if (file_put_contents(CONFIG_FILE, $contents, LOCK_EX) === false) {
            throw new \RuntimeException('Gagal menulis storage/config.php. Cek permission folder storage.');
        }
    }
}
