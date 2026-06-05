# ManhwaLanded PHP CMS

Fresh PHP CMS untuk portal manhwa tanpa WordPress dan tanpa data bawaan.

## Konsep

- Install pertama membuat database kosong.
- Setelah login admin, jalankan scrape fresh dari sumber yang kamu punya izin pakai.
- Tidak ada file hasil scrape, cover, atau chapter yang dibundel di repo.
- Target hosting: PHP 8.1+ dan MySQL/MariaDB di cPanel/shared hosting.

## Deploy cPanel

1. Buat database MySQL dan user database di cPanel.
2. Upload isi folder ini ke hosting.
3. Arahkan document root domain/subdomain ke folder `public`.
4. Pastikan folder berikut writable:
   - `storage/`
   - `public/assets/covers/`
   - `public/assets/chapters/`
5. Buka domain, lalu isi installer.
6. Login ke `/admin`.
7. Buka Settings, isi canonical URL dan source default.
8. Buka Scrape Fresh, scrape 1-3 judul dulu untuk test.

## Jika document root tidak bisa diarahkan ke public

Upload isi `public/` ke `public_html/`, lalu upload folder `app/`, `database/`, dan `storage/` satu level di atas `public_html/`. Jika struktur hosting tidak mengizinkan itu, gunakan subfolder dan sesuaikan path di `public/index.php`.

## Scraper

Scraper generik mencari link detail dengan pola:

- `/manga/slug`
- `/komik/slug`
- `/manhwa/slug`

Lalu mencoba mengambil:

- judul dari `h1`, `og:title`, atau `title`
- cover dari `og:image`, `wp-post-image`, atau image pertama
- genre dari link `/genre` atau `/genres`
- chapter dari link yang mengandung `chapter`, `ch`, `episode`, atau `eps`
- gambar reader dari `ts_reader.run(...)` atau tag `img`

Situs dengan Cloudflare/login mungkin perlu Cookie opsional dari browser yang sudah kamu izinkan.

## Keamanan

- Admin memakai `password_hash`.
- Query database memakai PDO prepared statements.
- Form admin memakai CSRF token.
- Folder `public` adalah satu-satunya document root yang disarankan.

## Local test

Kalau PHP tersedia:

```bash
php -S 127.0.0.1:8090 -t public public/router.php
```

Buka `http://127.0.0.1:8090`.

Untuk simulasi lokal tanpa MySQL aktif, pilih `SQLite lokal untuk simulasi` di installer. Untuk hosting cPanel, pilih `MySQL / MariaDB untuk hosting`.
