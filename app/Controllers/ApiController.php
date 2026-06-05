<?php

declare(strict_types=1);

namespace ManhwaPortal\Controllers;

use ManhwaPortal\Core\Database;
use ManhwaPortal\Services\ScraperService;
use ManhwaPortal\Services\TitleRepository;
use PDO;
use Throwable;

final class ApiController
{
    private PDO $db;
    private TitleRepository $titles;

    public function __construct()
    {
        $this->db = Database::pdo();
        $this->titles = new TitleRepository();
    }

    public function handle(string $path, string $method): void
    {
        ob_start();
        try {
            match (true) {
                $path === '/api/admin-login' && $method === 'POST' => $this->adminLogin(),
                $path === '/api/admin-logout' && $method === 'POST' => $this->adminLogout(),
                $path === '/api/admin-session' => $this->json(['ok' => true, 'authenticated' => admin_is_logged_in()]),
                $path === '/api/settings' && $method === 'GET' => $this->settings(),
                $path === '/api/settings' && $method === 'POST' => $this->saveSettings(),
                $path === '/api/stats' => $this->stats(),
                $path === '/api/comics' => $this->comics(),
                $path === '/api/source-list' && $method === 'POST' => $this->sourceList(),
                $path === '/api/source-catalog' => $this->json(['ok' => true, 'catalog' => ['items' => [], 'total' => 0, 'newCount' => 0, 'updateCount' => 0]]),
                $path === '/api/source-scan' && $method === 'POST' => $this->sourceScan(),
                $path === '/api/scrape-selected' && $method === 'POST' => $this->scrapeSelected(),
                $path === '/api/scrape-update-all' && $method === 'POST' => $this->scrapeUpdateAll(),
                $path === '/api/scrape-job' => $this->scrapeJob(),
                $path === '/api/scrape-job-control' && $method === 'POST' => $this->scrapeJobControl(),
                $path === '/api/scrape-retry-failed' && $method === 'POST' => $this->scrapeSelected(),
                $path === '/api/scheduler' && $method === 'GET' => $this->scheduler(),
                $path === '/api/scheduler' && $method === 'POST' => $this->saveScheduler(),
                $path === '/api/scheduler-run-now' && $method === 'POST' => $this->scrapeUpdateAll(),
                $path === '/api/delete-comics' && $method === 'POST' => $this->deleteComics(),
                $path === '/api/bulk-update-comics' && $method === 'POST' => $this->bulkUpdate(),
                $path === '/api/rebuild-thumbnails' && $method === 'POST' => $this->json(['ok' => true, 'thumbnails' => 0]),
                $path === '/api/scan-broken-images' => $this->json(['ok' => true, 'checked' => 0, 'brokenCount' => 0, 'broken' => []]),
                $path === '/api/scrape-logs' => $this->scrapeLogs(),
                $path === '/api/update-slug' && $method === 'POST' => $this->updateSlug(),
                $path === '/data/manhwa.json' => $this->manhwaJson(),
                default => $this->json(['ok' => false, 'error' => 'Endpoint tidak ditemukan.'], 404),
            };
        } catch (Throwable $error) {
            if (ob_get_length()) {
                ob_clean();
            }
            $this->json(['ok' => false, 'error' => $error->getMessage()], 500);
        } finally {
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
        }
    }

    public function manhwaJson(): void
    {
        $this->json($this->legacyComics(), 200, false);
    }

    public function settings(): void
    {
        $this->json(['ok' => true, 'settings' => $this->legacySettings()]);
    }

    public function saveSettings(): void
    {
        $this->requireAdminJson();
        $payload = $this->input();
        $settings = array_merge($this->legacySettings(), $payload);
        $this->saveSettingsArray($settings);
        $this->json(['ok' => true, 'settings' => $this->legacySettings()]);
    }

    public function stats(): void
    {
        $comics = $this->legacyComics();
        $chapters = 0;
        $totalImages = 0;
        $coverCount = 0;

        foreach ($comics as $comic) {
            if (!empty($comic['cover']) || !empty($comic['image'])) {
                $coverCount++;
            }
            foreach ($comic['chapters'] ?? [] as $chapter) {
                $chapters++;
                $totalImages += count($chapter['images'] ?? []);
            }
        }

        $this->json([
            'ok' => true,
            'total' => count($comics),
            'chapters' => $chapters,
            'savedImages' => $coverCount,
            'totalImages' => $totalImages,
            'storageLabel' => $this->storageLabel(),
        ]);
    }

    public function comics(): void
    {
        $results = array_map(fn (array $comic) => $this->adminComic($comic), $this->legacyComics());
        $this->json(['ok' => true, 'total' => count($results), 'results' => $results]);
    }

    public function sourceList(): void
    {
        $this->requireAdminJson();
        $payload = $this->input();
        $source = (string) ($payload['source'] ?? 'https://komiktap.info/');
        $limit = max(1, min(50, (int) ($payload['limit'] ?? 25)));
        $page = max(1, (int) ($payload['page'] ?? 1));
        $cookie = (string) ($payload['cookie'] ?? '');

        $items = (new ScraperService())->sourceItems($source, $limit, $page, $cookie, $this->savedSlugs());
        $this->json([
            'ok' => true,
            'results' => $items,
            'count' => count($items),
            'page' => $page,
            'listingUrl' => $source,
        ]);
    }

    public function sourceScan(): void
    {
        $this->requireAdminJson();
        $payload = $this->input();
        $source = (string) ($payload['source'] ?? 'https://komiktap.info/');
        $maxPages = max(1, min(10, (int) ($payload['maxPages'] ?? 1)));
        $cookie = (string) ($payload['cookie'] ?? '');
        $items = [];
        $saved = $this->savedSlugs();

        for ($page = 1; $page <= $maxPages; $page++) {
            $items = array_merge($items, (new ScraperService())->sourceItems($source, 25, $page, $cookie, $saved));
        }

        $newCount = count(array_filter($items, static fn (array $item) => empty($item['alreadySaved'])));
        $catalog = [
            'items' => $items,
            'total' => count($items),
            'newCount' => $newCount,
            'updateCount' => count($items) - $newCount,
            'scannedAt' => date(DATE_ATOM),
        ];
        $this->json(['ok' => true, 'catalog' => $catalog]);
    }

    public function scrapeSelected(): void
    {
        $this->extendScrapeRuntime();
        $this->requireAdminJson();
        $payload = $this->input();
        $urls = array_values(array_filter((array) ($payload['urls'] ?? []), 'is_string'));
        if (!$urls && !empty($payload['chapterUrl'])) {
            $urls = [(string) $payload['chapterUrl']];
        }
        if (!$urls) {
            throw new \RuntimeException('Tidak ada URL yang dipilih.');
        }

        $jobId = 'job-' . time() . '-' . bin2hex(random_bytes(3));
        $job = $this->makeJob($jobId, count($urls), 'Scrape job dibuat. Menunggu proses bertahap...');
        $job['urls'] = $urls;
        $job['cookie'] = (string) ($payload['cookie'] ?? '');
        $job['saveImages'] = (bool) ($payload['saveImages'] ?? true);
        $job['downloadConcurrency'] = max(1, min(80, (int) ($payload['downloadConcurrency'] ?? 6)));
        $job['chapterConcurrency'] = max(1, min(12, (int) ($payload['chapterConcurrency'] ?? 1)));
        $job['currentUrlIndex'] = 0;
        $job['currentChapterIndex'] = 0;
        $job['pendingChapters'] = [];
        $job['currentComic'] = null;
        $job['workerMode'] = 'poll';
        $this->saveJob($job);
        if ($this->startJobWorker($jobId)) {
            $job['workerMode'] = 'background';
            $job['message'] = 'Job dibuat. Worker background mulai berjalan...';
            $this->saveJob($job);
        }
        $this->json(['ok' => true, 'jobId' => $jobId, 'job' => $job]);
    }

    public function scrapeUpdateAll(): void
    {
        $this->extendScrapeRuntime();
        $this->requireAdminJson();
        $urls = array_values(array_filter(array_map(static fn ($comic) => $comic['sourceUrl'] ?? '', $this->legacyComics())));
        if (!$urls) {
            throw new \RuntimeException('Belum ada koleksi dengan source URL.');
        }
        $_POST = [];
        $payload = $this->input();
        $payload['urls'] = $urls;
        $this->scrapeSelectedFromPayload($payload);
    }

    public function scrapeJob(): void
    {
        $this->extendScrapeRuntime();
        $this->requireAdminJson();
        $id = (string) ($_GET['id'] ?? '');
        $job = $id ? $this->readJob($id) : null;
        if (!$job) {
            $this->json(['ok' => false, 'error' => 'Job tidak ditemukan.'], 404);
            return;
        }
        if (($job['workerMode'] ?? 'poll') === 'background' && $this->jobIsStale($job, 600)) {
            $job['workerMode'] = 'poll';
            $job['message'] = 'Worker background tidak merespons, lanjut via polling aman...';
            $this->saveJob($job);
        }
        if (($job['workerMode'] ?? 'poll') !== 'background') {
            $job = $this->processJobStep($job);
        }
        $this->json(['ok' => true, 'job' => $job]);
    }

    public function scrapeJobControl(): void
    {
        $this->requireAdminJson();
        $payload = $this->input();
        $job = $this->readJob((string) ($payload['id'] ?? '')) ?: [];
        if (!$job) {
            throw new \RuntimeException('Job tidak ditemukan.');
        }
        if (($payload['action'] ?? '') === 'cancel') {
            $job['status'] = 'cancelled';
            $job['message'] = 'Scrape dibatalkan.';
            $this->saveJob($job);
        }
        $this->json(['ok' => true, 'job' => $job]);
    }

    public function scheduler(): void
    {
        $this->json(['ok' => true, 'scheduler' => $this->getJsonSetting('_scheduler', [
            'enabled' => false,
            'dailyTime' => '03:00',
            'intervalHours' => 0,
            'lastRunAt' => null,
        ])]);
    }

    public function saveScheduler(): void
    {
        $this->requireAdminJson();
        $scheduler = array_merge(['enabled' => false, 'dailyTime' => '03:00', 'intervalHours' => 0], $this->input());
        $this->setSetting('_scheduler', json_encode($scheduler, JSON_UNESCAPED_SLASHES));
        $this->json(['ok' => true, 'scheduler' => $scheduler]);
    }

    public function deleteComics(): void
    {
        $this->requireAdminJson();
        $slugs = array_values(array_filter((array) ($this->input()['slugs'] ?? []), 'is_string'));
        if (!$slugs) {
            throw new \RuntimeException('Tidak ada slug dipilih.');
        }

        $stmt = $this->db->prepare('DELETE FROM titles WHERE slug = :slug');
        $deleted = 0;
        foreach ($slugs as $slug) {
            $stmt->execute(['slug' => $slug]);
            $deleted += $stmt->rowCount();
        }
        $this->json(['ok' => true, 'deleted' => $deleted, 'totalSaved' => count($this->legacyComics())]);
    }

    public function bulkUpdate(): void
    {
        $this->requireAdminJson();
        $payload = $this->input();
        $slugs = array_values(array_filter((array) ($payload['slugs'] ?? []), 'is_string'));
        $updated = 0;
        foreach ($slugs as $slug) {
            $fields = [];
            $params = ['slug' => $slug];
            if (!empty($payload['type'])) {
                $fields[] = 'type = :type';
                $params['type'] = $payload['type'];
            }
            if (!empty($payload['status'])) {
                $fields[] = 'status = :status';
                $params['status'] = $payload['status'];
            }
            if ($fields) {
                $stmt = $this->db->prepare('UPDATE titles SET ' . implode(', ', $fields) . ' WHERE slug = :slug');
                $stmt->execute($params);
                $updated += $stmt->rowCount();
            }
        }
        $this->json(['ok' => true, 'updated' => $updated]);
    }

    public function scrapeLogs(): void
    {
        $stmt = $this->db->query('SELECT * FROM scrape_logs ORDER BY id DESC LIMIT 40');
        $logs = array_map(static fn (array $row) => [
            'status' => $row['level'],
            'message' => $row['message'],
            'startedAt' => $row['created_at'],
            'doneChapters' => 0,
            'totalChapters' => 0,
            'doneImages' => 0,
            'errors' => $row['level'] === 'error' ? [$row['message']] : [],
        ], $stmt->fetchAll());
        $this->json(['ok' => true, 'logs' => $logs]);
    }

    public function updateSlug(): void
    {
        $this->requireAdminJson();
        $payload = $this->input();
        $from = slugify((string) ($payload['from'] ?? ''));
        $to = slugify((string) ($payload['to'] ?? ''));
        if (!$from || !$to) {
            throw new \RuntimeException('Slug lama dan baru wajib diisi.');
        }
        $stmt = $this->db->prepare('UPDATE titles SET slug = :to WHERE slug = :from');
        $stmt->execute(['from' => $from, 'to' => $to]);
        $this->json(['ok' => true, 'from' => $from, 'to' => $to]);
    }

    private function scrapeSelectedFromPayload(array $payload): void
    {
        $GLOBALS['api_payload_override'] = $payload;
        $this->scrapeSelected();
    }

    public function runScrapeJob(string $id): void
    {
        $this->extendScrapeRuntime();
        register_shutdown_function(function () use ($id): void {
            $error = error_get_last();
            if (!$error || !in_array($error['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            $job = $this->readJob($id);
            if (!$job || !in_array($job['status'] ?? '', ['running', 'queued'], true)) {
                return;
            }

            $job['workerMode'] = 'poll';
            $job['lastWorkerError'] = (string) ($error['message'] ?? 'Unknown worker error');
            $job['message'] = 'Worker background terhenti, lanjut via polling aman...';
            $this->saveJob($job);
        });

        $job = $this->readJob($id);
        while ($job && in_array($job['status'] ?? '', ['running', 'queued'], true)) {
            $job['workerStartedAt'] ??= date(DATE_ATOM);
            $job['workerHeartbeatAt'] = date(DATE_ATOM);
            $this->saveJob($job);
            $job = $this->processJobStep($job);
            usleep(250000);
        }
    }

    private function adminLogin(): void
    {
        $payload = $this->input();
        $stmt = $this->db->prepare('SELECT * FROM admins WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => trim((string) ($payload['username'] ?? ''))]);
        $admin = $stmt->fetch();
        if (!$admin || !password_verify((string) ($payload['password'] ?? ''), $admin['password_hash'])) {
            $this->json(['ok' => false, 'error' => 'Username atau password salah.'], 401);
            return;
        }

        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $this->json(['ok' => true]);
    }

    private function adminLogout(): void
    {
        unset($_SESSION['admin_id'], $_SESSION['admin_username']);
        $this->json(['ok' => true]);
    }

    private function input(): array
    {
        if (isset($GLOBALS['api_payload_override']) && is_array($GLOBALS['api_payload_override'])) {
            $payload = $GLOBALS['api_payload_override'];
            unset($GLOBALS['api_payload_override']);
            return $payload;
        }

        $raw = file_get_contents('php://input') ?: '';
        $json = json_decode($raw, true);
        return is_array($json) ? $json : [];
    }

    private function requireAdminJson(): void
    {
        if (!admin_is_logged_in()) {
            $this->json(['ok' => false, 'error' => 'Unauthorized'], 401);
            exit;
        }
    }

    private function legacySettings(): array
    {
        $defaults = [
            'siteTitle' => setting('site_name', 'ManhwaLanded - Portal Manhwa'),
            'metaDescription' => setting('site_description', 'Baca manhwa, manga, dan manhua bahasa Indonesia dengan update chapter terbaru.'),
            'metaKeywords' => 'manhwa, manga, manhua, komik',
            'footerText' => 'Copyright ©2026 MANHWALANDED. All rights reserved.',
            'headerLogoText' => 'MANHWALANDED',
            'logoUrl' => '/assets/brand/default-logo.svg',
            'faviconUrl' => '/assets/brand/default-favicon.svg',
            'ogImageUrl' => '',
            'heroMode' => 'auto',
            'heroSlugs' => [],
            'recommendMode' => 'auto',
            'recommendSlugs' => [],
            'popularMode' => 'auto',
            'popularSlugs' => [],
            'canonicalUrl' => setting('canonical_url', app_url('/')),
            'schemaEnabled' => true,
            'robotsText' => '',
            'bannerPlaceholders' => [],
        ];

        $legacy = $this->getJsonSetting('_legacy_settings', []);
        $settings = array_merge($defaults, is_array($legacy) ? $legacy : []);
        foreach (['logoUrl', 'faviconUrl'] as $assetKey) {
            if (trim((string) ($settings[$assetKey] ?? '')) === '') {
                $settings[$assetKey] = $defaults[$assetKey];
            }
        }

        if (trim((string) ($settings['ogImageUrl'] ?? '')) === '') {
            $settings['ogImageUrl'] = $settings['logoUrl'];
        }

        return $settings;
    }

    private function saveSettingsArray(array $settings): void
    {
        $settings['logoUrl'] = trim((string) ($settings['logoUrl'] ?? '')) ?: '/assets/brand/default-logo.svg';
        $settings['faviconUrl'] = trim((string) ($settings['faviconUrl'] ?? '')) ?: '/assets/brand/default-favicon.svg';
        $settings['ogImageUrl'] = trim((string) ($settings['ogImageUrl'] ?? '')) ?: $settings['logoUrl'];
        $this->setSetting('_legacy_settings', json_encode($settings, JSON_UNESCAPED_SLASHES));
        $map = [
            'site_name' => $settings['headerLogoText'] ?? $settings['siteTitle'] ?? 'ManhwaLanded',
            'site_description' => $settings['metaDescription'] ?? '',
            'canonical_url' => $settings['canonicalUrl'] ?? '',
        ];
        foreach ($map as $key => $value) {
            $this->setSetting($key, (string) $value);
        }
    }

    private function legacyComics(): array
    {
        $rows = $this->titles->allForAdmin();
        return array_map(fn (array $title) => $this->legacyComic($title), $rows);
    }

    private function legacyComic(array $title): array
    {
        $chapters = $this->titles->chapters((int) $title['id']);
        $legacyChapters = [];
        foreach ($chapters as $chapter) {
            $images = $this->titles->chapterImages((int) $chapter['id']);
            $legacyImages = array_map(static fn (array $image) => ltrim((string) ($image['local_path'] ?: $image['image_url']), '/'), $images);
            $legacyChapters[] = [
                'title' => $chapter['title'],
                'date' => $chapter['published_at'] ?? '',
                'url' => $chapter['source_url'] ?? '',
                'images' => $legacyImages,
                'thumbnail' => $legacyImages[0] ?? '',
            ];
        }

        $genres = [];
        $stmt = $this->db->prepare('SELECT g.name FROM genres g JOIN title_genres tg ON tg.genre_id = g.id WHERE tg.title_id = :id ORDER BY g.name');
        $stmt->execute(['id' => $title['id']]);
        foreach ($stmt->fetchAll() as $row) {
            $genres[] = $row['name'];
        }

        $cover = (string) ($title['cover'] ?? '');
        $isLocalCover = $cover !== '' && !preg_match('#^https?://#i', $cover);

        return [
            'title' => $title['title'],
            'slug' => $title['slug'],
            'type' => $title['type'] ?: 'Manhwa',
            'status' => $title['status'] ?: 'Ongoing',
            'author' => '',
            'artist' => '',
            'released' => '',
            'rating' => (float) $title['rating'],
            'views' => (int) $title['views'],
            'updatedAt' => $title['updated_at'] ?: date('Y-m-d'),
            'updatedLabel' => '',
            'genres' => $genres,
            'synopsis' => $title['synopsis'] ?: '',
            'sourceUrl' => $title['source_url'] ?: '',
            'image' => $isLocalCover ? '' : $cover,
            'cover' => $isLocalCover ? ltrim($cover, '/') : '',
            'chapter' => $legacyChapters[0]['title'] ?? 'Belum ada chapter',
            'chapters' => $legacyChapters,
        ];
    }

    private function adminComic(array $comic): array
    {
        $chapters = $comic['chapters'] ?? [];
        $imageCount = 0;
        foreach ($chapters as $chapter) {
            $imageCount += count($chapter['images'] ?? []);
        }

        return array_merge($comic, [
            'sourceUrl' => $comic['sourceUrl'] ?? '',
            'chapterCount' => count($chapters),
            'sourceChapterCount' => count($chapters),
            'localChapterCount' => count($chapters),
            'thumbnailCount' => count(array_filter(array_column($chapters, 'thumbnail'))),
            'missingThumbnailCount' => 0,
            'chapterImagesSaved' => $imageCount > 0,
            'imagesSaved' => !empty($comic['cover']) || !empty($comic['image']),
            'dataSaved' => true,
            'alreadySaved' => true,
            'scrapeStatus' => 'saved',
            'scrapeStatusLabel' => 'Sudah ada',
            'previewUrl' => '/manga/' . $comic['slug'],
            'latestReaderUrl' => !empty($chapters[0]) ? '/read/' . $comic['slug'] . '/' . slugify($chapters[0]['title']) : '',
        ]);
    }

    private function savedSlugs(): array
    {
        return array_flip(array_map(static fn (array $comic) => $comic['slug'], $this->legacyComics()));
    }

    private function setSetting(string $key, string $value): void
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? 'INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = CURRENT_TIMESTAMP'
            : 'INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['key' => $key, 'value' => $value]);
    }

    private function getJsonSetting(string $key, array $default): array
    {
        $stmt = $this->db->prepare('SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1');
        $stmt->execute(['key' => $key]);
        $value = $stmt->fetchColumn();
        $json = is_string($value) ? json_decode($value, true) : null;
        return is_array($json) ? $json : $default;
    }

    private function makeJob(string $id, int $total, string $message): array
    {
        return [
            'id' => $id,
            'status' => 'running',
            'message' => $message,
            'totalManga' => $total,
            'doneManga' => 0,
            'totalChapters' => 0,
            'doneChapters' => 0,
            'doneImages' => 0,
            'scraped' => 0,
            'currentTitle' => '',
            'currentChapter' => '',
            'failedChapters' => [],
            'startedAt' => date(DATE_ATOM),
            'results' => [],
        ];
    }

    public function processJobStep(array $job): array
    {
        if (!in_array($job['status'] ?? '', ['running', 'queued'], true)) {
            return $job;
        }

        $urls = array_values(array_filter((array) ($job['urls'] ?? []), 'is_string'));
        if (!$urls) {
            $job['status'] = 'failed';
            $job['message'] = 'Job lama tidak punya antrean URL. Buat scrape baru.';
            $this->saveJob($job);
            return $job;
        }

        $scraper = new ScraperService();
        $cookie = (string) ($job['cookie'] ?? '');
        $saveImages = (bool) ($job['saveImages'] ?? true);
        $downloadConcurrency = max(1, min(80, (int) ($job['downloadConcurrency'] ?? 6)));
        $chapterConcurrency = max(1, min(12, (int) ($job['chapterConcurrency'] ?? 1)));
        if (($job['workerMode'] ?? 'poll') !== 'background') {
            $downloadConcurrency = min($downloadConcurrency, 24);
            $chapterConcurrency = min($chapterConcurrency, 2);
            $job['runtimeMode'] = 'poll-safe';
        } else {
            $job['runtimeMode'] = 'background';
        }
        $job['effectiveDownloadConcurrency'] = $downloadConcurrency;
        $job['effectiveChapterConcurrency'] = $chapterConcurrency;
        $currentUrlIndex = (int) ($job['currentUrlIndex'] ?? 0);

        try {
            if (empty($job['currentComic'])) {
                if ($currentUrlIndex >= count($urls)) {
                    $job['status'] = 'completed';
                    $job['message'] = 'Scrape selesai.';
                    $job['results'] = array_map(fn (array $comic) => $this->adminComic($comic), $this->legacyComics());
                    $job['completedAt'] = date(DATE_ATOM);
                    $this->saveJob($job);
                    $this->logJob($job);
                    return $job;
                }

                $url = $urls[$currentUrlIndex];
                $comic = $scraper->scrapeComicMetadata($url, $cookie, $saveImages);
                $chapters = array_values((array) ($comic['chapters'] ?? []));
                $comicForDb = $comic;
                $comicForDb['chapters'] = [];
                $this->titles->upsertScraped($comicForDb);

                $job['currentComic'] = [
                    'title' => $comic['title'],
                    'slug' => $comic['slug'],
                    'cover' => $comic['cover'] ?? '',
                    'synopsis' => $comic['synopsis'] ?? '',
                    'status' => $comic['status'] ?? '',
                    'type' => $comic['type'] ?? 'Manhwa',
                    'genres' => $comic['genres'] ?? [],
                    'source_url' => $comic['source_url'] ?? $url,
                ];
                $job['pendingChapters'] = $chapters;
                $job['currentChapterIndex'] = 0;
                $job['currentTitle'] = (string) $comic['title'];
                $job['currentChapter'] = '';
                $job['totalChapters'] = (int) ($job['totalChapters'] ?? 0) + count($chapters);
                $job['stage'] = $saveImages ? 'downloading' : 'metadata';
                $job['message'] = 'Metadata tersimpan: ' . $comic['title'];

                if (!$saveImages || count($chapters) === 0) {
                    $job['doneManga'] = (int) ($job['doneManga'] ?? 0) + 1;
                    $job['scraped'] = (int) ($job['scraped'] ?? 0) + 1;
                    $job['currentUrlIndex'] = $currentUrlIndex + 1;
                    $job['currentComic'] = null;
                    $job['pendingChapters'] = [];
                    $job['message'] = 'Judul selesai: ' . $comic['title'];
                }

                $this->saveJob($job);
                return $job;
            }

            $comic = (array) $job['currentComic'];
            $pendingChapters = array_values((array) ($job['pendingChapters'] ?? []));
            $chapterIndex = (int) ($job['currentChapterIndex'] ?? 0);

            if ($chapterIndex < count($pendingChapters)) {
                $batchStartedAt = microtime(true);
                $job['currentTitle'] = (string) ($comic['title'] ?? '');
                $batch = array_slice($pendingChapters, $chapterIndex, $chapterConcurrency);
                $job['currentChapter'] = count($batch) > 1
                    ? 'Batch ' . ($chapterIndex + 1) . '-' . ($chapterIndex + count($batch))
                    : (string) ($batch[0]['title'] ?? '');

                $chapters = $chapterConcurrency > 1
                    ? $scraper->scrapeChaptersForComic($batch, $cookie, (string) ($comic['title'] ?? ''), $downloadConcurrency, $chapterConcurrency)
                    : [$scraper->scrapeChapterForComic($batch[0], $cookie, (string) ($comic['title'] ?? ''), $downloadConcurrency)];

                $savedBatch = 0;
                $imageBatch = 0;
                $startedTransaction = !$this->db->inTransaction();
                if ($startedTransaction) {
                    $this->db->beginTransaction();
                }
                try {
                    foreach ($chapters as $chapter) {
                        if ($saveImages && empty($chapter['images'])) {
                            $job['failedChapters'][] = [
                                'mangaTitle' => $job['currentTitle'] ?? '',
                                'chapterTitle' => $chapter['title'] ?? '',
                                'chapterUrl' => $chapter['source_url'] ?? '',
                                'error' => 'Tidak ada gambar ditemukan.',
                            ];
                            continue;
                        }

                        $chapterComic = $comic;
                        $chapterComic['chapters'] = [$chapter];
                        $this->titles->upsertScraped($chapterComic);
                        $savedBatch++;
                        $imageBatch += count((array) ($chapter['images'] ?? []));
                    }
                    if ($startedTransaction) {
                        $this->db->commit();
                    }
                } catch (Throwable $error) {
                    if ($startedTransaction && $this->db->inTransaction()) {
                        $this->db->rollBack();
                    }
                    throw $error;
                }

                $job['doneChapters'] = (int) ($job['doneChapters'] ?? 0) + count($batch);
                $job['doneImages'] = (int) ($job['doneImages'] ?? 0) + $imageBatch;
                $job['currentChapterIndex'] = $chapterIndex + count($batch);
                $job['lastBatchChapters'] = count($batch);
                $job['lastBatchSaved'] = $savedBatch;
                $job['lastBatchImages'] = $imageBatch;
                $job['lastBatchSeconds'] = round(max(0.001, microtime(true) - $batchStartedAt), 2);
                $job['lastBatchAt'] = date(DATE_ATOM);
                $job['stage'] = 'downloading';
                $job['message'] = count($batch) > 1
                    ? 'Batch chapter tersimpan: ' . $savedBatch . '/' . count($batch) . ' chapter, ' . $imageBatch . ' gambar'
                    : 'Chapter tersimpan: ' . ($chapters[0]['title'] ?? 'Chapter');
            }

            if ((int) ($job['currentChapterIndex'] ?? 0) >= count($pendingChapters)) {
                $job['doneManga'] = (int) ($job['doneManga'] ?? 0) + 1;
                $job['scraped'] = (int) ($job['scraped'] ?? 0) + 1;
                $job['currentUrlIndex'] = $currentUrlIndex + 1;
                $job['currentComic'] = null;
                $job['pendingChapters'] = [];
                $job['currentChapterIndex'] = 0;
                $job['message'] = 'Judul selesai: ' . ($comic['title'] ?? '');
            }
        } catch (Throwable $error) {
            $job['failedChapters'][] = [
                'mangaTitle' => $job['currentTitle'] ?? '',
                'chapterTitle' => $job['currentChapter'] ?? '',
                'chapterUrl' => '',
                'error' => $error->getMessage(),
            ];
            $job['message'] = 'Ada error, lanjut ke item berikutnya: ' . $error->getMessage();
            if (!empty($job['currentComic'])) {
                $pendingCount = count((array) ($job['pendingChapters'] ?? []));
                $chapterIndex = (int) ($job['currentChapterIndex'] ?? 0);
                $advance = max(1, min($chapterConcurrency, max(1, $pendingCount - $chapterIndex)));
                $job['currentChapterIndex'] = $chapterIndex + $advance;
                $job['doneChapters'] = (int) ($job['doneChapters'] ?? 0) + $advance;
            } else {
                $job['currentUrlIndex'] = $currentUrlIndex + 1;
            }
        }

        $this->saveJob($job);
        return $job;
    }

    private function saveJob(array $job): void
    {
        if (!is_dir(STORAGE_PATH . '/jobs')) {
            mkdir(STORAGE_PATH . '/jobs', 0755, true);
        }
        $job['updatedAt'] = date(DATE_ATOM);
        $id = basename((string) $job['id']);
        $file = STORAGE_PATH . '/jobs/' . $id . '.json';
        $tmp = STORAGE_PATH . '/jobs/' . $id . '.' . getmypid() . '.tmp';
        $json = json_encode($job, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            throw new \RuntimeException('Gagal encode progress job.');
        }
        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            throw new \RuntimeException('Gagal menulis progress job.');
        }
        @chmod($tmp, 0644);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            throw new \RuntimeException('Gagal menyimpan progress job.');
        }
    }

    public function readJob(string $id): ?array
    {
        $file = STORAGE_PATH . '/jobs/' . basename($id) . '.json';
        if (!is_file($file)) {
            return null;
        }
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $contents = file_get_contents($file);
            if ($contents === false || trim($contents) === '') {
                usleep(50000);
                continue;
            }
            $json = json_decode($contents, true);
            if (is_array($json)) {
                return $json;
            }
            usleep(50000);
        }
        return null;
    }

    private function extendScrapeRuntime(): void
    {
        @ignore_user_abort(true);
        if (function_exists('set_time_limit') && !$this->functionDisabled('set_time_limit')) {
            @set_time_limit(0);
        }
    }

    private function jobIsStale(array $job, int $seconds): bool
    {
        $timestamp = strtotime((string) ($job['workerHeartbeatAt'] ?? $job['updatedAt'] ?? ''));
        return $timestamp > 0 && (time() - $timestamp) > $seconds;
    }

    private function startJobWorker(string $jobId): bool
    {
        if (!function_exists('exec') || $this->functionDisabled('exec')) {
            return false;
        }

        $worker = BASE_PATH . '/worker/scrape-job.php';
        if (!is_file($worker)) {
            return false;
        }

        $log = STORAGE_PATH . '/worker.log';
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($worker) . ' ' . escapeshellarg($jobId)
            . ' >> ' . escapeshellarg($log) . ' 2>&1 &';
        @exec($command, $output, $code);
        return $code === 0;
    }

    private function functionDisabled(string $function): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return in_array($function, $disabled, true);
    }

    private function logJob(array $job): void
    {
        $stmt = $this->db->prepare('INSERT INTO scrape_logs (level, message, context) VALUES (:level, :message, :context)');
        $stmt->execute([
            'level' => $job['status'],
            'message' => $job['message'],
            'context' => json_encode($job, JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function storageLabel(): string
    {
        $bytes = 0;
        foreach (['covers', 'chapters'] as $dir) {
            $path = PUBLIC_PATH . '/assets/' . $dir;
            if (!is_dir($path)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $bytes += $file->getSize();
            }
        }
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / 1048576, 1) . ' MB';
    }

    private function json(array $payload, int $status = 200, bool $object = true): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        echo $json === false ? '{"ok":false,"error":"Gagal encode JSON."}' : $json;
    }
}
