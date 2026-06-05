CREATE TABLE IF NOT EXISTS admins (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS settings (
  setting_key TEXT PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS titles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  slug TEXT NOT NULL UNIQUE,
  title TEXT NOT NULL,
  cover TEXT NULL,
  synopsis TEXT NULL,
  status TEXT NULL,
  type TEXT NULL,
  rating REAL DEFAULT 0,
  views INTEGER DEFAULT 0,
  source_url TEXT NULL,
  updated_at TEXT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_titles_updated ON titles(updated_at);
CREATE INDEX IF NOT EXISTS idx_titles_status ON titles(status);

CREATE TABLE IF NOT EXISTS genres (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  slug TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS title_genres (
  title_id INTEGER NOT NULL,
  genre_id INTEGER NOT NULL,
  PRIMARY KEY (title_id, genre_id),
  FOREIGN KEY (title_id) REFERENCES titles(id) ON DELETE CASCADE,
  FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS chapters (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title_id INTEGER NOT NULL,
  slug TEXT NOT NULL,
  title TEXT NOT NULL,
  number_value REAL DEFAULT NULL,
  source_url TEXT NULL,
  published_at TEXT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (title_id, slug),
  FOREIGN KEY (title_id) REFERENCES titles(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_chapters_title ON chapters(title_id);

CREATE TABLE IF NOT EXISTS chapter_images (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  chapter_id INTEGER NOT NULL,
  sort_order INTEGER NOT NULL DEFAULT 0,
  image_url TEXT NOT NULL,
  local_path TEXT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_chapter_images_chapter ON chapter_images(chapter_id);

CREATE TABLE IF NOT EXISTS scrape_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  level TEXT NOT NULL DEFAULT 'info',
  message TEXT NOT NULL,
  context TEXT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_scrape_logs_created ON scrape_logs(created_at);

