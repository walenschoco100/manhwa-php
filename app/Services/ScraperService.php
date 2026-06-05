<?php

declare(strict_types=1);

namespace ManhwaPortal\Services;

use DOMDocument;
use DOMXPath;
use ManhwaPortal\Core\Database;
use Throwable;

final class ScraperService
{
    private TitleRepository $titles;

    public function __construct()
    {
        $this->titles = new TitleRepository();
    }

    public function scrape(string $sourceUrl, int $limit, string $cookie = '', bool $downloadAssets = false): array
    {
        $sourceUrl = trim($sourceUrl);
        if (!filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException('Source URL tidak valid.');
        }

        $limit = max(1, min(50, $limit));
        $links = array_slice($this->discoverLinks($sourceUrl, $cookie), 0, $limit);
        $imported = 0;
        $failed = 0;

        foreach ($links as $link) {
            try {
                $comic = $this->scrapeDetail($link, $cookie, $downloadAssets);
                if (!$comic) {
                    $failed++;
                    continue;
                }
                $this->titles->upsertScraped($comic);
                $this->log('info', 'Imported ' . $comic['title'], ['url' => $link]);
                $imported++;
            } catch (Throwable $error) {
                $this->log('error', 'Gagal scrape detail: ' . $error->getMessage(), ['url' => $link]);
                $failed++;
            }
        }

        return ['links' => count($links), 'imported' => $imported, 'failed' => $failed];
    }

    public function scrapeUrls(array $urls, string $cookie = '', bool $downloadAssets = false): array
    {
        $imported = 0;
        $failed = 0;
        $chapters = 0;
        $images = 0;

        foreach ($urls as $url) {
            try {
                if (!is_string($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                    $failed++;
                    continue;
                }
                $comic = $this->scrapeDetail($url, $cookie, false);
                if (!$comic) {
                    $failed++;
                    continue;
                }
                if ($downloadAssets && !empty($comic['cover'])) {
                    $comic['cover'] = $this->download((string) $comic['cover'], 'covers', slugify((string) $comic['title'])) ?: $comic['cover'];
                }
                $this->titles->upsertScraped($comic);
                $imported++;
                $chapters += count($comic['chapters'] ?? []);

                if ($downloadAssets) {
                    foreach ($comic['chapters'] ?? [] as $chapter) {
                        try {
                            $chapter['images'] = $this->scrapeChapterImages(
                                (string) ($chapter['source_url'] ?? ''),
                                $cookie,
                                true,
                                slugify((string) $comic['title']),
                                (string) ($chapter['slug'] ?? slugify((string) $chapter['title']))
                            );
                            $images += count($chapter['images'] ?? []);
                            $chapterComic = $comic;
                            $chapterComic['chapters'] = [$chapter];
                            $this->titles->upsertScraped($chapterComic);
                        } catch (Throwable $error) {
                            $this->log('warning', 'Gagal ambil gambar chapter: ' . $error->getMessage(), ['url' => $chapter['source_url'] ?? '']);
                        }
                    }
                }

                foreach ($comic['chapters'] ?? [] as $chapter) {
                    if (!$downloadAssets) {
                        $images += count($chapter['images'] ?? []);
                    }
                }
                $this->log('info', 'Imported ' . $comic['title'], ['url' => $url]);
            } catch (Throwable $error) {
                $this->log('error', 'Gagal scrape detail: ' . $error->getMessage(), ['url' => $url]);
                $failed++;
            }
        }

        return ['imported' => $imported, 'failed' => $failed, 'chapters' => $chapters, 'images' => $images];
    }

    public function scrapeComicMetadata(string $url, string $cookie = '', bool $downloadCover = true): array
    {
        $comic = $this->scrapeDetail($url, $cookie, false);
        if (!$comic) {
            throw new \RuntimeException('Detail komik kosong.');
        }

        if ($downloadCover && !empty($comic['cover'])) {
            $comic['cover'] = $this->download((string) $comic['cover'], 'covers', slugify((string) $comic['title'])) ?: $comic['cover'];
        }

        return $comic;
    }

    public function scrapeChapterForComic(array $chapter, string $cookie, string $title, int $downloadConcurrency = 6): array
    {
        $chapter['images'] = $this->scrapeChapterImages(
            (string) ($chapter['source_url'] ?? ''),
            $cookie,
            true,
            slugify($title),
            (string) ($chapter['slug'] ?? slugify((string) ($chapter['title'] ?? 'chapter'))),
            $downloadConcurrency
        );

        return $chapter;
    }

    public function scrapeChaptersForComic(array $chapters, string $cookie, string $title, int $downloadConcurrency = 12, int $chapterConcurrency = 3): array
    {
        $chapterConcurrency = max(1, min(12, $chapterConcurrency));
        $downloadConcurrency = max(1, min(80, $downloadConcurrency));
        $titleSlug = slugify($title);
        $urls = array_map(static fn (array $chapter): string => (string) ($chapter['source_url'] ?? ''), $chapters);
        $htmlByIndex = $this->fetchMany($urls, $cookie, $chapterConcurrency);
        $results = [];
        $downloadSets = [];

        foreach (array_values($chapters) as $index => $chapter) {
            $url = (string) ($chapter['source_url'] ?? '');
            $html = $htmlByIndex[$index] ?? null;
            if (!$html) {
                $chapter['images'] = [];
                $results[$index] = $chapter;
                continue;
            }

            $chapterSlug = (string) ($chapter['slug'] ?? slugify((string) ($chapter['title'] ?? 'chapter')));
            $sources = $this->extractChapterImageSources($html, $url);
            $downloadSets[$index] = [
                'sources' => $sources,
                'folder' => 'chapters/' . $titleSlug . '/' . $chapterSlug,
            ];
            $chapter['images'] = [];
            $results[$index] = $chapter;
        }

        $localSets = $this->downloadImageSets($downloadSets, $downloadConcurrency);
        foreach ($downloadSets as $index => $set) {
            $sources = $set['sources'];
            $locals = $localSets[$index] ?? [];
            $results[$index]['images'] = array_values(array_filter(array_map(static function (string $src, int $imageIndex) use ($locals): ?array {
                $local = $locals[$imageIndex] ?? null;
                return $local ? ['url' => $src, 'local_path' => $local] : null;
            }, $sources, array_keys($sources))));
        }

        ksort($results);
        return array_values($results);
    }

    public function sourceItems(string $sourceUrl, int $limit, int $page, string $cookie = '', array $savedSlugs = [], string $comicType = 'all'): array
    {
        $listingUrl = $this->listingUrl($sourceUrl, $page);
        $html = $this->fetch($listingUrl, $cookie);
        $cards = $this->parseListingCards($html, $listingUrl, $savedSlugs, $comicType);
        $items = $this->filterItemsByType($cards, $comicType);
        $items = array_slice($items, 0, $limit);

        if ($cards) {
            return $items;
        }

        $fallbackItems = array_map(function (string $link) use ($savedSlugs, $comicType): array {
            $path = trim((string) parse_url($link, PHP_URL_PATH), '/');
            $slug = basename($path);
            $title = ucwords(str_replace('-', ' ', $slug));
            $alreadySaved = isset($savedSlugs[$slug]);
            $type = $this->typeFromRequestedFilter($comicType) ?? 'Manhwa';

            return [
                'title' => $title,
                'slug' => $slug,
                'type' => $type,
                'status' => '',
                'rating' => 0,
                'image' => '',
                'cover' => '',
                'chapter' => 'Detail belum discan',
                'chapterCount' => 0,
                'sourceUrl' => $link,
                'alreadySaved' => $alreadySaved,
                'scrapeStatus' => $alreadySaved ? 'saved' : 'new',
                'scrapeStatusLabel' => $alreadySaved ? 'Sudah ada' : 'Belum ada',
                'localChapterCount' => 0,
                'sourceChapterCount' => 0,
                'pendingChapterCount' => 0,
                'incompleteChapterCount' => 0,
            ];
        }, $this->discoverLinks($listingUrl, $cookie));

        return array_slice($this->filterItemsByType($fallbackItems, $comicType), 0, $limit);
    }

    public function discoverLinks(string $sourceUrl, string $cookie = ''): array
    {
        $html = $this->fetch($sourceUrl, $cookie);
        $xpath = $this->xpath($html);
        $links = [];

        foreach ($xpath->query('//a[@href]') as $node) {
            $href = $this->absoluteUrl($node->getAttribute('href'), $sourceUrl);
            if (!$href) {
                continue;
            }

            if (preg_match('#/(manga|komik|manhwa)/[a-z0-9][a-z0-9-]*/?$#i', parse_url($href, PHP_URL_PATH) ?: '')) {
                $links[$href] = $href;
            }
        }

        return array_values($links);
    }

    private function parseListingCards(string $html, string $baseUrl, array $savedSlugs, string $requestedType = 'all'): array
    {
        $cards = [];

        if (!preg_match_all('/<div class="bsx"[^>]*>([\s\S]*?)(?=<div class="bsx"|<div class="hpage"|<div id="sidebar"|<div class="clear"|$)/i', $html, $matches)) {
            return [];
        }

        foreach ($matches[1] as $cardHtml) {
            $href = $this->attr($cardHtml, 'href');
            $sourceUrl = $href ? $this->absoluteUrl($href, $baseUrl) : null;
            if (!$sourceUrl || !preg_match('#/(manga|komik|manhwa)/[a-z0-9][a-z0-9-]*/?$#i', parse_url($sourceUrl, PHP_URL_PATH) ?: '')) {
                continue;
            }

            $slug = basename(trim((string) parse_url($sourceUrl, PHP_URL_PATH), '/'));
            $title = trim(strip_tags($this->match('/<div class="tt"[^>]*>([\s\S]*?)<\/div>/i', $cardHtml) ?: ''));
            if ($title === '') {
                $title = html_entity_decode($this->attr($cardHtml, 'title') ?: ucwords(str_replace('-', ' ', $slug)), ENT_QUOTES, 'UTF-8');
            }

            $image = $this->attr($cardHtml, 'src');
            $image = $image ? $this->absoluteUrl(html_entity_decode($image, ENT_QUOTES, 'UTF-8'), $baseUrl) : '';
            $chapter = trim(strip_tags($this->match('/<div class="epxs"[^>]*>([\s\S]*?)<\/div>/i', $cardHtml) ?: ''));
            $chapterCount = $this->chapterNumber($chapter);
            $type = $this->typeFromRequestedFilter($requestedType) ?? $this->typeFromCard($cardHtml);
            $status = trim(strip_tags($this->match('/<span class="status[^"]*"[^>]*>([\s\S]*?)<\/span>/i', $cardHtml) ?: ''));
            $rating = 0.0;
            if (preg_match('/style=["\'][^"\']*width\s*:\s*(\d+(?:\.\d+)?)%/i', $cardHtml, $ratingMatch)) {
                $rating = round(((float) $ratingMatch[1]) / 10, 1);
            }

            $alreadySaved = isset($savedSlugs[$slug]);
            $cards[] = [
                'title' => html_entity_decode($title, ENT_QUOTES, 'UTF-8'),
                'slug' => $slug,
                'type' => $type,
                'status' => $status,
                'rating' => $rating,
                'image' => $image,
                'cover' => '',
                'chapter' => $chapter,
                'chapterCount' => $chapterCount ? (int) floor($chapterCount) : 0,
                'sourceUrl' => $sourceUrl,
                'alreadySaved' => $alreadySaved,
                'scrapeStatus' => $alreadySaved ? 'saved' : 'new',
                'scrapeStatusLabel' => $alreadySaved ? 'Sudah ada' : 'Belum ada',
                'localChapterCount' => 0,
                'sourceChapterCount' => $chapterCount ? (int) floor($chapterCount) : 0,
                'pendingChapterCount' => $chapterCount ? (int) floor($chapterCount) : 0,
                'incompleteChapterCount' => 0,
            ];
        }

        return $cards;
    }

    private function listingUrl(string $sourceUrl, int $page): string
    {
        if ($page <= 1) {
            return $sourceUrl;
        }

        $parts = parse_url($sourceUrl);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return $sourceUrl;
        }

        $root = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $root .= ':' . $parts['port'];
        }

        $path = trim((string) ($parts['path'] ?? ''), '/');
        $path = preg_replace('#/page/\d+/?$#', '', $path) ?: '';
        $pagePath = ($path ? '/' . $path : '') . '/page/' . $page . '/';
        $query = !empty($parts['query']) ? '?' . $parts['query'] : '';

        return $root . $pagePath . $query;
    }

    private function filterItemsByType(array $items, string $comicType): array
    {
        $comicType = strtolower(trim($comicType));
        if (!in_array($comicType, ['manhwa', 'manga', 'manhua'], true)) {
            return array_values($items);
        }

        return array_values(array_filter($items, static function (array $item) use ($comicType): bool {
            return strtolower((string) ($item['type'] ?? '')) === $comicType;
        }));
    }

    private function attr(string $html, string $name): ?string
    {
        if (preg_match('/\s' . preg_quote($name, '/') . '=["\']([^"\']+)["\']/i', $html, $match)) {
            return $match[1];
        }

        return null;
    }

    private function match(string $pattern, string $html): ?string
    {
        return preg_match($pattern, $html, $match) ? $match[1] : null;
    }

    private function typeFromCard(string $html): string
    {
        if (preg_match('/<span[^>]*class=["\'][^"\']*\btype\b[^"\']*["\'][^>]*>([\s\S]*?)<\/span>/i', $html, $match)) {
            $type = $this->normalizeComicType(strip_tags($match[1]));
            if ($type) {
                return $type;
            }
        }

        if (preg_match('/\b(Manhwa|Manhua|Manga)\b/i', strip_tags($html), $match)) {
            $type = $this->normalizeComicType($match[1]);
            if ($type) {
                return $type;
            }
        }

        if (preg_match('/<span[^>]*class=["\'][^"\']*\btype\b([^"\']*)["\']/i', $html, $match)) {
            $type = $this->normalizeComicType($match[1]);
            if ($type && $type !== 'Manga') {
                return $type;
            }
        }

        return 'Manhwa';
    }

    private function typeFromDetail(string $html): string
    {
        foreach (['type', 'jenis'] as $label) {
            $type = $this->normalizeComicType($this->matchLabel($html, $label) ?? '');
            if ($type) {
                return $type;
            }
        }

        if (preg_match_all('#<a[^>]+href=["\'][^"\']*/(?:genre|genres|tag)/[^"\']*["\'][^>]*>([^<]+)</a>#i', $html, $matches)) {
            foreach ($matches[1] as $text) {
                $type = $this->normalizeComicType($text);
                if ($type) {
                    return $type;
                }
            }
        }

        if (preg_match('/\b(Manhwa|Manhua|Manga)\b/i', strip_tags($html), $match)) {
            $type = $this->normalizeComicType($match[1]);
            if ($type) {
                return $type;
            }
        }

        return 'Manhwa';
    }

    private function typeFromRequestedFilter(string $comicType): ?string
    {
        return $this->normalizeComicType($comicType);
    }

    private function normalizeComicType(string $value): ?string
    {
        $value = strtolower(trim(strip_tags(html_entity_decode($value, ENT_QUOTES, 'UTF-8'))));
        if ($value === '') {
            return null;
        }
        if (preg_match('/\bmanhwa\b/', $value)) {
            return 'Manhwa';
        }
        if (preg_match('/\bmanhua\b/', $value)) {
            return 'Manhua';
        }
        if (preg_match('/\bmanga\b/', $value)) {
            return 'Manga';
        }

        return null;
    }

    private function scrapeDetail(string $url, string $cookie, bool $downloadAssets): ?array
    {
        $html = $this->fetch($url, $cookie);
        $xpath = $this->xpath($html);
        $title = $this->firstText($xpath, ['//h1', '//meta[@property="og:title"]/@content', '//title']);
        $title = preg_replace('/\s*[-|].*$/', '', trim((string) $title)) ?: 'Untitled';
        $cover = $this->firstText($xpath, [
            '//meta[@property="og:image"]/@content',
            '//img[contains(@class,"wp-post-image")]/@src',
            '//img[contains(@class,"cover")]/@src',
            '//img/@src',
        ]);
        $cover = $cover ? $this->absoluteUrl($cover, $url) : null;

        if ($downloadAssets && $cover) {
            $cover = $this->download($cover, 'covers', slugify($title));
        }

        $genres = [];
        foreach ($xpath->query('//a[contains(@href,"/genre") or contains(@href,"/genres")]') as $node) {
            $text = trim($node->textContent);
            if ($text !== '') {
                $genres[$text] = $text;
            }
        }

        $chapterLinks = [];
        foreach ($xpath->query('//a[@href]') as $node) {
            $href = $this->absoluteUrl($node->getAttribute('href'), $url);
            $text = trim(preg_replace('/\s+/', ' ', $node->textContent));
            $path = (string) parse_url((string) $href, PHP_URL_PATH);
            $looksLikeChapter = preg_match('/\bchapter\b|\bepisode\b|\beps\b|-[ck]?hapter-|chapter-\d|\/[^\/]*-chapter-[^\/]*\/?$/i', $text . ' ' . $path);
            $isTaxonomy = preg_match('#/(genre|genres|tag|project|bookmark|a-z-list|manga|komik|manhwa)/#i', $path);
            if ($href && $text !== '' && $looksLikeChapter && !$isTaxonomy) {
                $chapterLinks[$href] = [
                    'title' => $text,
                    'source_url' => $href,
                    'slug' => slugify($text),
                    'number' => $this->chapterNumber($text),
                    'images' => [],
                ];
            }
        }

        $chapters = array_slice(array_values($chapterLinks), 0, 80);
        if ($downloadAssets) {
            foreach ($chapters as &$chapter) {
                try {
                    $chapter['images'] = $this->scrapeChapterImages($chapter['source_url'], $cookie, true, slugify($title), $chapter['slug']);
                } catch (Throwable $error) {
                    $this->log('warning', 'Gagal ambil gambar chapter: ' . $error->getMessage(), ['url' => $chapter['source_url']]);
                }
            }
            unset($chapter);
        }

        return [
            'title' => $title,
            'slug' => slugify($title),
            'cover' => $cover,
            'synopsis' => $this->synopsis($xpath),
            'status' => $this->matchLabel($html, 'status'),
            'type' => $this->typeFromDetail($html),
            'genres' => array_values($genres),
            'chapters' => $chapters,
            'source_url' => $url,
        ];
    }

    private function scrapeChapterImages(string $url, string $cookie, bool $downloadAssets, string $titleSlug, string $chapterSlug, int $downloadConcurrency = 6): array
    {
        $html = $this->fetch($url, $cookie);
        $sources = $this->extractChapterImageSources($html, $url);
        $locals = $downloadAssets
            ? $this->downloadMany($sources, 'chapters/' . $titleSlug . '/' . $chapterSlug, $downloadConcurrency)
            : [];

        $result = [];
        foreach ($sources as $index => $src) {
            $result[] = ['url' => $src, 'local_path' => $locals[$index] ?? null];
        }

        return $result;
    }

    private function extractChapterImageSources(string $html, string $url): array
    {
        $images = [];

        if (preg_match('/ts_reader\.run\((\{.*?\})\);/s', $html, $match)) {
            $json = json_decode($match[1], true);
            foreach (($json['sources'][0]['images'] ?? []) as $src) {
                if (is_string($src)) {
                    $images[] = $this->absoluteUrl($src, $url);
                }
            }
        }

        if (!$images) {
            $xpath = $this->xpath($html);
            foreach ($xpath->query('//img[@src]') as $node) {
                $src = $this->absoluteUrl($node->getAttribute('src'), $url);
                if ($src && preg_match('/\.(jpe?g|png|webp)(\?|$)/i', $src)) {
                    $images[] = $src;
                }
            }
        }

        return array_values(array_unique(array_filter($images)));
    }

    private function fetch(string $url, string $cookie): string
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_ENCODING => '',
                CURLOPT_HTTPHEADER => array_filter([
                    'User-Agent: Mozilla/5.0 (compatible; ManhwaLandedPHP/1.0)',
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                    $cookie !== '' ? 'Cookie: ' . $cookie : '',
                ]),
            ]);
            $html = curl_exec($curl);
            $error = curl_error($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);

            if (is_string($html) && trim($html) !== '' && $status < 400) {
                return $html;
            }

            if ($error !== '') {
                throw new \RuntimeException('Tidak bisa mengambil URL: ' . $error);
            }
        }

        $headers = [
            'User-Agent: Mozilla/5.0 (compatible; ManhwaLandedPHP/1.0)',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Encoding: identity',
        ];
        if ($cookie !== '') {
            $headers[] = 'Cookie: ' . $cookie;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 25,
            ],
        ]);

        $html = @file_get_contents($url, false, $context);
        if ($html === false || trim($html) === '') {
            throw new \RuntimeException('Tidak bisa mengambil URL.');
        }

        if (substr($html, 0, 2) === "\x1f\x8b") {
            $decoded = @gzdecode($html);
            if (is_string($decoded) && $decoded !== '') {
                $html = $decoded;
            }
        }

        return $html;
    }

    private function fetchMany(array $urls, string $cookie, int $concurrency): array
    {
        $concurrency = max(1, min(12, $concurrency));
        $results = [];
        $pending = [];

        foreach (array_values($urls) as $index => $url) {
            if (!is_string($url) || $url === '') {
                $results[$index] = null;
                continue;
            }
            $pending[] = ['index' => $index, 'url' => $url];
        }

        if (!$pending) {
            return $results;
        }

        if ($concurrency <= 1 || !function_exists('curl_multi_init')) {
            foreach ($pending as $item) {
                try {
                    $results[$item['index']] = $this->fetch($item['url'], $cookie);
                } catch (Throwable) {
                    $results[$item['index']] = null;
                }
            }
            ksort($results);
            return $results;
        }

        $multi = curl_multi_init();
        $active = [];
        $queue = array_values($pending);
        $headers = array_filter([
            'User-Agent: Mozilla/5.0 (compatible; ManhwaLandedPHP/1.0)',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            $cookie !== '' ? 'Cookie: ' . $cookie : '',
        ]);

        $enqueue = function () use (&$queue, &$active, $multi, $concurrency, $headers): void {
            while (count($active) < $concurrency && $queue) {
                $item = array_shift($queue);
                $curl = curl_init($item['url']);
                curl_setopt_array($curl, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_CONNECTTIMEOUT => 12,
                    CURLOPT_ENCODING => '',
                    CURLOPT_HTTPHEADER => $headers,
                ]);
                $key = spl_object_id($curl);
                $active[$key] = ['curl' => $curl, 'item' => $item];
                curl_multi_add_handle($multi, $curl);
            }
        };

        $enqueue();
        do {
            do {
                $status = curl_multi_exec($multi, $running);
            } while ($status === CURLM_CALL_MULTI_PERFORM);

            while ($done = curl_multi_info_read($multi)) {
                $curl = $done['handle'];
                $key = spl_object_id($curl);
                $meta = $active[$key] ?? null;
                if ($meta) {
                    $html = curl_multi_getcontent($curl);
                    $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                    $results[$meta['item']['index']] = $done['result'] === CURLE_OK && is_string($html) && trim($html) !== '' && $statusCode < 400 ? $html : null;
                    unset($active[$key]);
                }
                curl_multi_remove_handle($multi, $curl);
                curl_close($curl);
                $enqueue();
            }

            if ($running) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running || $active || $queue);

        curl_multi_close($multi);
        ksort($results);
        return $results;
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        return new DOMXPath($dom);
    }

    private function firstText(DOMXPath $xpath, array $queries): ?string
    {
        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes && $nodes->length > 0) {
                $value = trim((string) $nodes->item(0)->nodeValue);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function synopsis(DOMXPath $xpath): ?string
    {
        $queries = [
            '//*[contains(@class,"synopsis")]',
            '//*[contains(@class,"summary")]',
            '//*[contains(@class,"entry-content")]//p',
            '//meta[@name="description"]/@content',
        ];
        return $this->firstText($xpath, $queries);
    }

    private function matchLabel(string $html, string $label): ?string
    {
        if (preg_match('/<b[^>]*>\s*' . preg_quote($label, '/') . '\s*<\/b>\s*:?<\/?[^>]*>\s*([^<\r\n]+)/i', $html, $match)) {
            return trim(strip_tags($match[1]));
        }

        if (preg_match('/<span[^>]*class=["\'][^"\']*' . preg_quote($label, '/') . '[^"\']*["\'][^>]*>([^<]+)<\/span>/i', $html, $match)) {
            return trim(strip_tags($match[1]));
        }

        return null;
    }

    private function chapterNumber(string $text): ?float
    {
        if (preg_match('/(\d+(?:\.\d+)?)/', $text, $match)) {
            return (float) $match[1];
        }

        return null;
    }

    private function absoluteUrl(string $href, string $base): ?string
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, 'data:') || str_starts_with($href, 'javascript:')) {
            return null;
        }

        if (filter_var($href, FILTER_VALIDATE_URL)) {
            return $href;
        }

        $parts = parse_url($base);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        if (str_starts_with($href, '//')) {
            return $parts['scheme'] . ':' . $href;
        }

        $root = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $root .= ':' . $parts['port'];
        }

        if (str_starts_with($href, '/')) {
            return $root . $href;
        }

        $dir = rtrim(dirname($parts['path'] ?? '/'), '/');
        return $root . ($dir ? $dir . '/' : '/') . $href;
    }

    private function download(string $url, string $folder, string $name): ?string
    {
        $target = $this->downloadTarget($url, $folder, $name);
        if (is_file($target['absolute']) && filesize($target['absolute']) > 0) {
            return $target['relative'];
        }

        $bytes = $this->downloadBytes($url);
        if ($bytes === null) {
            return null;
        }

        file_put_contents($target['absolute'], $bytes, LOCK_EX);
        return $target['relative'];
    }

    private function downloadMany(array $urls, string $folder, int $concurrency): array
    {
        $concurrency = max(1, min(80, $concurrency));
        $results = [];
        $pending = [];

        foreach (array_values($urls) as $index => $url) {
            if (!is_string($url) || $url === '') {
                $results[$index] = null;
                continue;
            }

            $target = $this->downloadTarget($url, $folder, sprintf('%03d', $index + 1));
            if (is_file($target['absolute']) && filesize($target['absolute']) > 0) {
                $results[$index] = $target['relative'];
                continue;
            }

            $pending[] = ['index' => $index, 'url' => $url, 'target' => $target];
        }

        if (!$pending) {
            ksort($results);
            return $results;
        }

        if ($concurrency <= 1 || !function_exists('curl_multi_init')) {
            foreach ($pending as $item) {
                $bytes = $this->downloadBytes($item['url']);
                if ($bytes !== null) {
                    file_put_contents($item['target']['absolute'], $bytes, LOCK_EX);
                    $results[$item['index']] = $item['target']['relative'];
                } else {
                    $results[$item['index']] = null;
                }
            }
            ksort($results);
            return $results;
        }

        $multi = curl_multi_init();
        $active = [];
        $queue = array_values($pending);

        $enqueue = function () use (&$queue, &$active, $multi, $concurrency): void {
            while (count($active) < $concurrency && $queue) {
                $item = array_shift($queue);
                $curl = curl_init($item['url']);
                curl_setopt_array($curl, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 45,
                    CURLOPT_CONNECTTIMEOUT => 15,
                    CURLOPT_ENCODING => '',
                    CURLOPT_HTTPHEADER => [
                        'User-Agent: Mozilla/5.0 (compatible; ManhwaLandedPHP/1.0)',
                        'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                    ],
                ]);
                $key = spl_object_id($curl);
                $active[$key] = ['curl' => $curl, 'item' => $item];
                curl_multi_add_handle($multi, $curl);
            }
        };

        $enqueue();
        do {
            do {
                $status = curl_multi_exec($multi, $running);
            } while ($status === CURLM_CALL_MULTI_PERFORM);

            while ($done = curl_multi_info_read($multi)) {
                $curl = $done['handle'];
                $key = spl_object_id($curl);
                $meta = $active[$key] ?? null;
                if ($meta) {
                    $bytes = curl_multi_getcontent($curl);
                    $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                    if ($done['result'] === CURLE_OK && is_string($bytes) && $bytes !== '' && $statusCode < 400) {
                        file_put_contents($meta['item']['target']['absolute'], $bytes, LOCK_EX);
                        $results[$meta['item']['index']] = $meta['item']['target']['relative'];
                    } else {
                        $results[$meta['item']['index']] = null;
                    }
                    unset($active[$key]);
                }
                curl_multi_remove_handle($multi, $curl);
                curl_close($curl);
                $enqueue();
            }

            if ($running) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running || $active || $queue);

        curl_multi_close($multi);
        ksort($results);
        return $results;
    }

    private function downloadImageSets(array $sets, int $concurrency): array
    {
        $concurrency = max(1, min(80, $concurrency));
        $results = [];
        $pending = [];

        foreach ($sets as $setIndex => $set) {
            $sources = array_values((array) ($set['sources'] ?? []));
            $folder = (string) ($set['folder'] ?? '');
            $results[$setIndex] = [];

            foreach ($sources as $imageIndex => $url) {
                if (!is_string($url) || $url === '' || $folder === '') {
                    $results[$setIndex][$imageIndex] = null;
                    continue;
                }

                $target = $this->downloadTarget($url, $folder, sprintf('%03d', $imageIndex + 1));
                if (is_file($target['absolute']) && filesize($target['absolute']) > 0) {
                    $results[$setIndex][$imageIndex] = $target['relative'];
                    continue;
                }

                $pending[] = [
                    'set' => $setIndex,
                    'image' => $imageIndex,
                    'url' => $url,
                    'target' => $target,
                ];
            }
        }

        if (!$pending) {
            return $results;
        }

        if ($concurrency <= 1 || !function_exists('curl_multi_init')) {
            foreach ($pending as $item) {
                $bytes = $this->downloadBytes($item['url']);
                if ($bytes !== null) {
                    file_put_contents($item['target']['absolute'], $bytes, LOCK_EX);
                    $results[$item['set']][$item['image']] = $item['target']['relative'];
                } else {
                    $results[$item['set']][$item['image']] = null;
                }
            }
            return $results;
        }

        $multi = curl_multi_init();
        $active = [];
        $queue = array_values($pending);

        $enqueue = function () use (&$queue, &$active, $multi, $concurrency): void {
            while (count($active) < $concurrency && $queue) {
                $item = array_shift($queue);
                $curl = curl_init($item['url']);
                curl_setopt_array($curl, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 45,
                    CURLOPT_CONNECTTIMEOUT => 15,
                    CURLOPT_ENCODING => '',
                    CURLOPT_HTTPHEADER => [
                        'User-Agent: Mozilla/5.0 (compatible; ManhwaLandedPHP/1.0)',
                        'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                    ],
                ]);
                $key = spl_object_id($curl);
                $active[$key] = ['curl' => $curl, 'item' => $item];
                curl_multi_add_handle($multi, $curl);
            }
        };

        $enqueue();
        do {
            do {
                $status = curl_multi_exec($multi, $running);
            } while ($status === CURLM_CALL_MULTI_PERFORM);

            while ($done = curl_multi_info_read($multi)) {
                $curl = $done['handle'];
                $key = spl_object_id($curl);
                $meta = $active[$key] ?? null;
                if ($meta) {
                    $bytes = curl_multi_getcontent($curl);
                    $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                    $set = $meta['item']['set'];
                    $image = $meta['item']['image'];
                    if ($done['result'] === CURLE_OK && is_string($bytes) && $bytes !== '' && $statusCode < 400) {
                        file_put_contents($meta['item']['target']['absolute'], $bytes, LOCK_EX);
                        $results[$set][$image] = $meta['item']['target']['relative'];
                    } else {
                        $results[$set][$image] = null;
                    }
                    unset($active[$key]);
                }
                curl_multi_remove_handle($multi, $curl);
                curl_close($curl);
                $enqueue();
            }

            if ($running) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running || $active || $queue);

        curl_multi_close($multi);
        return $results;
    }

    private function downloadTarget(string $url, string $folder, string $name): array
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $ext = 'jpg';
        }

        $targetDir = PUBLIC_PATH . '/assets/' . trim($folder, '/');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $relative = '/assets/' . trim($folder, '/') . '/' . $name . '.' . $ext;
        return [
            'relative' => $relative,
            'absolute' => PUBLIC_PATH . $relative,
        ];
    }

    private function downloadBytes(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 45,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_ENCODING => '',
                CURLOPT_HTTPHEADER => [
                    'User-Agent: Mozilla/5.0 (compatible; ManhwaLandedPHP/1.0)',
                    'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                ],
            ]);
            $bytes = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);
            return is_string($bytes) && $bytes !== '' && $status < 400 ? $bytes : null;
        }

        $bytes = @file_get_contents($url);
        return is_string($bytes) && $bytes !== '' ? $bytes : null;
    }

    private function log(string $level, string $message, array $context = []): void
    {
        $stmt = Database::pdo()->prepare('INSERT INTO scrape_logs (level, message, context) VALUES (:level, :message, :context)');
        $stmt->execute([
            'level' => $level,
            'message' => $message,
            'context' => $context ? json_encode($context, JSON_UNESCAPED_SLASHES) : null,
        ]);
    }
}
