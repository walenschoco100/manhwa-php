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
        $base = rtrim(setting('canonical_url', app_url('/')) ?? app_url('/'), '/');
        $titles = $this->titles->latest(5000);
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        echo '<url><loc>' . e($base) . '</loc></url>';
        foreach ($titles as $title) {
            echo '<url><loc>' . e($base . '/manga/' . $title['slug']) . '</loc></url>';
        }
        echo '</urlset>';
    }

    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nAllow: /\nSitemap: " . rtrim(setting('canonical_url', app_url('/')) ?? app_url('/'), '/') . "/sitemap.xml\n";
    }

    public function notFound(): void
    {
        http_response_code(404);
        render('public/404', ['title' => 'Tidak ditemukan']);
    }
}
