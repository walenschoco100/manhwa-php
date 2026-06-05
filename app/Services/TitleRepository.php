<?php

declare(strict_types=1);

namespace ManhwaPortal\Services;

use ManhwaPortal\Core\Database;
use PDO;

final class TitleRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    public function stats(): array
    {
        return [
            'titles' => (int) $this->db->query('SELECT COUNT(*) FROM titles')->fetchColumn(),
            'chapters' => (int) $this->db->query('SELECT COUNT(*) FROM chapters')->fetchColumn(),
            'genres' => (int) $this->db->query('SELECT COUNT(*) FROM genres')->fetchColumn(),
        ];
    }

    public function latest(int $limit = 24): array
    {
        $stmt = $this->db->prepare('SELECT * FROM titles ORDER BY updated_at DESC, id DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function popular(int $limit = 10): array
    {
        $stmt = $this->db->prepare('SELECT * FROM titles ORDER BY views DESC, updated_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function search(string $query, ?string $genreSlug = null, int $limit = 48): array
    {
        $sql = 'SELECT DISTINCT t.* FROM titles t';
        $params = [];

        if ($genreSlug) {
            $sql .= ' JOIN title_genres tg ON tg.title_id = t.id JOIN genres g ON g.id = tg.genre_id';
            $params['genre'] = $genreSlug;
        }

        $where = [];
        if ($query !== '') {
            $where[] = '(t.title LIKE :query OR t.synopsis LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($genreSlug) {
            $where[] = 'g.slug = :genre';
        }

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY t.updated_at DESC, t.id DESC LIMIT :limit';
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function allForAdmin(): array
    {
        return $this->db
            ->query('SELECT t.*, COUNT(c.id) AS chapter_count FROM titles t LEFT JOIN chapters c ON c.title_id = t.id GROUP BY t.id ORDER BY t.updated_at DESC, t.id DESC')
            ->fetchAll();
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM titles WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $title = $stmt->fetch();
        return $title ?: null;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM titles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $title = $stmt->fetch();
        return $title ?: null;
    }

    public function chapters(int $titleId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM chapters WHERE title_id = :title_id ORDER BY COALESCE(number_value, id) DESC, id DESC');
        $stmt->execute(['title_id' => $titleId]);
        return $stmt->fetchAll();
    }

    public function chapter(string $titleSlug, string $chapterSlug): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, t.slug AS title_slug, t.title AS manga_title
             FROM chapters c
             JOIN titles t ON t.id = c.title_id
             WHERE t.slug = :title_slug AND c.slug = :chapter_slug
             LIMIT 1'
        );
        $stmt->execute(['title_slug' => $titleSlug, 'chapter_slug' => $chapterSlug]);
        $chapter = $stmt->fetch();
        return $chapter ?: null;
    }

    public function chapterImages(int $chapterId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM chapter_images WHERE chapter_id = :chapter_id ORDER BY sort_order ASC, id ASC');
        $stmt->execute(['chapter_id' => $chapterId]);
        return $stmt->fetchAll();
    }

    public function genres(): array
    {
        return $this->db->query('SELECT g.*, COUNT(tg.title_id) AS title_count FROM genres g LEFT JOIN title_genres tg ON tg.genre_id = g.id GROUP BY g.id ORDER BY g.name ASC')->fetchAll();
    }

    public function saveManual(array $payload): int
    {
        $id = (int) ($payload['id'] ?? 0);
        $slugInput = trim((string) ($payload['slug'] ?? ''));
        $slug = slugify($slugInput !== '' ? $slugInput : (string) $payload['title']);

        if ($id > 0) {
            $stmt = $this->db->prepare(
                'UPDATE titles SET slug = :slug, title = :title, cover = :cover, synopsis = :synopsis, status = :status, type = :type, rating = :rating, updated_at = NOW() WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'slug' => $slug,
                'title' => $payload['title'],
                'cover' => $payload['cover'] ?: null,
                'synopsis' => $payload['synopsis'] ?: null,
                'status' => $payload['status'] ?: null,
                'type' => $payload['type'] ?: null,
                'rating' => (float) ($payload['rating'] ?: 0),
            ]);
        } else {
            $stmt = $this->db->prepare(
                'INSERT INTO titles (slug, title, cover, synopsis, status, type, rating, updated_at) VALUES (:slug, :title, :cover, :synopsis, :status, :type, :rating, NOW())'
            );
            $stmt->execute([
                'slug' => $slug,
                'title' => $payload['title'],
                'cover' => $payload['cover'] ?: null,
                'synopsis' => $payload['synopsis'] ?: null,
                'status' => $payload['status'] ?: null,
                'type' => $payload['type'] ?: null,
                'rating' => (float) ($payload['rating'] ?: 0),
            ]);
            $id = (int) $this->db->lastInsertId();
        }

        $this->syncGenres($id, $payload['genres'] ?? '');
        return $id;
    }

    public function upsertScraped(array $comic): int
    {
        $slug = slugify((string) ($comic['slug'] ?? $comic['title']));
        $now = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'CURRENT_TIMESTAMP' : 'NOW()';
        $sourceUrl = trim((string) ($comic['source_url'] ?? ''));
        if ($sourceUrl !== '') {
            $stmt = $this->db->prepare('SELECT id FROM titles WHERE slug = :slug OR source_url = :source_url LIMIT 1');
            $stmt->execute(['slug' => $slug, 'source_url' => $sourceUrl]);
        } else {
            $stmt = $this->db->prepare('SELECT id FROM titles WHERE slug = :slug LIMIT 1');
            $stmt->execute(['slug' => $slug]);
        }
        $existing = $stmt->fetch();

        if ($existing) {
            $titleId = (int) $existing['id'];
            $stmt = $this->db->prepare(
                "UPDATE titles SET slug = :slug, title = :title, cover = :cover, synopsis = :synopsis, status = :status, type = :type, source_url = :source_url, updated_at = {$now} WHERE id = :id"
            );
            $stmt->execute([
                'id' => $titleId,
                'slug' => $slug,
                'title' => $comic['title'],
                'cover' => $comic['cover'] ?? null,
                'synopsis' => $comic['synopsis'] ?? null,
                'status' => $comic['status'] ?? null,
                'type' => $comic['type'] ?? null,
                'source_url' => $comic['source_url'] ?? null,
            ]);
        } else {
            $stmt = $this->db->prepare(
                "INSERT INTO titles (slug, title, cover, synopsis, status, type, source_url, updated_at) VALUES (:slug, :title, :cover, :synopsis, :status, :type, :source_url, {$now})"
            );
            $stmt->execute([
                'slug' => $slug,
                'title' => $comic['title'],
                'cover' => $comic['cover'] ?? null,
                'synopsis' => $comic['synopsis'] ?? null,
                'status' => $comic['status'] ?? null,
                'type' => $comic['type'] ?? null,
                'source_url' => $comic['source_url'] ?? null,
            ]);
            $titleId = (int) $this->db->lastInsertId();
        }

        $this->syncGenres($titleId, implode(',', $comic['genres'] ?? []));

        foreach ($comic['chapters'] ?? [] as $chapter) {
            if (empty($chapter['images'])) {
                continue;
            }

            $chapterSlug = slugify((string) ($chapter['slug'] ?? $chapter['title']));
            $stmt = $this->db->prepare('SELECT id FROM chapters WHERE title_id = :title_id AND slug = :slug LIMIT 1');
            $stmt->execute(['title_id' => $titleId, 'slug' => $chapterSlug]);
            $chapterId = (int) ($stmt->fetch()['id'] ?? 0);

            if ($chapterId === 0) {
                $stmt = $this->db->prepare(
                    'INSERT INTO chapters (title_id, slug, title, number_value, source_url, published_at) VALUES (:title_id, :slug, :title, :number_value, :source_url, :published_at)'
                );
                $stmt->execute([
                    'title_id' => $titleId,
                    'slug' => $chapterSlug,
                    'title' => $chapter['title'],
                    'number_value' => $chapter['number'] ?? null,
                    'source_url' => $chapter['source_url'] ?? null,
                    'published_at' => $chapter['date'] ?? null,
                ]);
                $chapterId = (int) $this->db->lastInsertId();
            }

            $this->db->prepare('DELETE FROM chapter_images WHERE chapter_id = :chapter_id')->execute(['chapter_id' => $chapterId]);
            $stmt = $this->db->prepare('INSERT INTO chapter_images (chapter_id, sort_order, image_url, local_path) VALUES (:chapter_id, :sort_order, :image_url, :local_path)');
            foreach (array_values($chapter['images']) as $index => $image) {
                $stmt->execute([
                    'chapter_id' => $chapterId,
                    'sort_order' => $index,
                    'image_url' => $image['url'],
                    'local_path' => $image['local_path'] ?? null,
                ]);
            }
        }

        return $titleId;
    }

    private function syncGenres(int $titleId, string $genres): void
    {
        $this->db->prepare('DELETE FROM title_genres WHERE title_id = :title_id')->execute(['title_id' => $titleId]);
        $items = array_filter(array_unique(array_map(static fn ($item) => trim($item), explode(',', $genres))));

        foreach ($items as $name) {
            $slug = slugify($name);
            $sql = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                ? 'INSERT OR IGNORE INTO genres (slug, name) VALUES (:slug, :name)'
                : 'INSERT IGNORE INTO genres (slug, name) VALUES (:slug, :name)';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['slug' => $slug, 'name' => $name]);

            $stmt = $this->db->prepare('SELECT id FROM genres WHERE slug = :slug LIMIT 1');
            $stmt->execute(['slug' => $slug]);
            $genreId = (int) $stmt->fetchColumn();

            $sql = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                ? 'INSERT OR IGNORE INTO title_genres (title_id, genre_id) VALUES (:title_id, :genre_id)'
                : 'INSERT IGNORE INTO title_genres (title_id, genre_id) VALUES (:title_id, :genre_id)';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['title_id' => $titleId, 'genre_id' => $genreId]);
        }
    }
}
