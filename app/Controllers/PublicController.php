<?php

declare(strict_types=1);

namespace ManhwaPortal\Controllers;

use ManhwaPortal\Core\Database;
use ManhwaPortal\Services\TitleRepository;

final class PublicController
{
    private TitleRepository $titles;

    public function __construct()
    {
        $this->titles = new TitleRepository();
    }

    public function home(): void
    {
        render('public/home', [
            'title' => setting('site_name', 'ManhwaLanded'),
            'latest' => $this->titles->latest(24),
            'popular' => $this->titles->popular(8),
            'genres' => $this->titles->genres(),
            'stats' => $this->titles->stats(),
        ]);
    }

    public function search(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        render('public/search', [
            'title' => 'Search',
            'query' => $query,
            'results' => $this->titles->search($query, null, 60),
            'genres' => $this->titles->genres(),
        ]);
    }

    public function genre(string $slug): void
    {
        render('public/search', [
            'title' => 'Genre',
            'query' => '',
            'genreSlug' => $slug,
            'results' => $this->titles->search('', $slug, 60),
            'genres' => $this->titles->genres(),
        ]);
    }

    public function detail(string $slug): void
    {
        $title = $this->titles->findBySlug($slug);
        if (!$title) {
            http_response_code(404);
            render('public/404', ['title' => 'Tidak ditemukan']);
            return;
        }

        Database::pdo()->prepare('UPDATE titles SET views = views + 1 WHERE id = :id')->execute(['id' => $title['id']]);

        render('public/detail', [
            'title' => $title['title'],
            'comic' => $title,
            'chapters' => $this->titles->chapters((int) $title['id']),
        ]);
    }

    public function reader(string $titleSlug, string $chapterSlug): void
    {
        $chapter = $this->titles->chapter($titleSlug, $chapterSlug);
        if (!$chapter) {
            http_response_code(404);
            render('public/404', ['title' => 'Tidak ditemukan']);
            return;
        }

        render('public/reader', [
            'title' => $chapter['manga_title'] . ' - ' . $chapter['title'],
            'chapter' => $chapter,
            'images' => $this->titles->chapterImages((int) $chapter['id']),
        ]);
    }

    public function sitemap(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        $settings = $this->siteSettings();
        $base = $this->canonicalBase($settings);
        $titles = $this->titles->latest(10000);
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        $this->sitemapUrl($base, date('Y-m-d'), 'daily', '1.0');
        foreach ($titles as $title) {
            $lastmod = $this->sitemapDate((string) ($title['updated_at'] ?? '')) ?: date('Y-m-d');
            $this->sitemapUrl($base . '/manga/' . rawurlencode((string) $title['slug']) . '/', $lastmod, 'daily', '0.8');
            foreach ($this->titles->chaptersWithImageCounts((int) $title['id']) as $chapter) {
                if ((int) ($chapter['image_count'] ?? 0) === 0) {
                    continue;
                }
                $chapterSlug = $this->chapterPublicSlug((string) $title['slug'], (string) $chapter['title'], (string) ($chapter['source_url'] ?? ''));
                $chapterDate = $this->sitemapDate((string) ($chapter['published_at'] ?? '')) ?: $lastmod;
                $this->sitemapUrl($base . '/' . rawurlencode($chapterSlug) . '/', $chapterDate, 'weekly', '0.7');
            }
        }
        echo "</urlset>\n";
    }

    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        $settings = $this->siteSettings();
        $custom = trim((string) ($settings['robotsText'] ?? ''));
        if ($custom !== '') {
            echo $custom . "\n";
            return;
        }

        $base = $this->canonicalBase($settings);
        echo "User-agent: *\n";
        echo "Allow: /\n";
        echo "Disallow: /admin\n";
        echo "Disallow: /api/\n";
        echo "Disallow: /install\n";
        echo "Sitemap: {$base}/sitemap.xml\n";
    }

    public function notFound(): void
    {
        http_response_code(404);
        render('public/404', ['title' => 'Tidak ditemukan']);
    }

    private function sitemapUrl(string $loc, string $lastmod = '', string $changefreq = '', string $priority = ''): void
    {
        echo "  <url>\n";
        echo '    <loc>' . e($loc) . "</loc>\n";
        if ($lastmod !== '') {
            echo '    <lastmod>' . e($lastmod) . "</lastmod>\n";
        }
        if ($changefreq !== '') {
            echo '    <changefreq>' . e($changefreq) . "</changefreq>\n";
        }
        if ($priority !== '') {
            echo '    <priority>' . e($priority) . "</priority>\n";
        }
        echo "  </url>\n";
    }

    private function sitemapDate(string $value): string
    {
        $timestamp = strtotime($value);
        return $timestamp ? date('Y-m-d', $timestamp) : '';
    }

    private function chapterPublicSlug(string $titleSlug, string $chapterTitle, string $sourceUrl): string
    {
        $sourceSlug = $this->sourceChapterSlug($sourceUrl);
        if ($sourceSlug !== '') {
            return $sourceSlug;
        }

        return $titleSlug . '-' . slugify($this->cleanChapterTitle($chapterTitle));
    }

    private function sourceChapterSlug(string $value): string
    {
        $path = (string) parse_url($value, PHP_URL_PATH);
        $parts = array_values(array_filter(explode('/', trim($path, '/'))));
        $slug = (string) end($parts);
        return preg_replace('/-end$/i', '', $slug) ?? $slug;
    }

    private function cleanChapterTitle(string $value): string
    {
        $text = trim(preg_replace('/^Latest:\s*/i', '', $value) ?? $value);
        if (preg_match('/\b(Chapter\s+\d+(?:\.\d+)?)/i', $text, $match)) {
            return $match[1];
        }

        $text = preg_replace('/\s+end\b/i', '', $text) ?? $text;
        $text = preg_replace('/\s+(?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Mei|Jun(?:e|i)?|Jul(?:y|i)?|Aug(?:ust)?|Agu(?:stus)?|Sep(?:tember)?|Oct(?:ober)?|Okt(?:ober)?|Nov(?:ember)?|Dec(?:ember)?|Des(?:ember)?)\s+\d{1,2},?\s+\d{4}$/i', '', $text) ?? $text;
        $text = preg_replace('/\s+\d{1,2}\s+(?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Mei|Jun(?:e|i)?|Jul(?:y|i)?|Aug(?:ust)?|Agu(?:stus)?|Sep(?:tember)?|Oct(?:ober)?|Okt(?:ober)?|Nov(?:ember)?|Dec(?:ember)?|Des(?:ember)?)\s+\d{4}$/i', '', $text) ?? $text;
        return trim($text);
    }

    private function siteSettings(): array
    {
        $raw = setting('_legacy_settings', '');
        $settings = json_decode((string) $raw, true);
        return is_array($settings) ? $settings : [];
    }

    private function canonicalBase(array $settings): string
    {
        $base = trim((string) ($settings['canonicalUrl'] ?? ''));
        if ($base === '') {
            $base = trim((string) (setting('canonical_url', app_url('/')) ?? app_url('/')));
        }

        return rtrim($base !== '' ? $base : app_url('/'), '/');
    }
}
