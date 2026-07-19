CREATE TABLE IF NOT EXISTS news_posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  author_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  image_path VARCHAR(255) NULL,
  show_public TINYINT(1) NOT NULL DEFAULT 0,
  show_members TINYINT(1) NOT NULL DEFAULT 1,
  visible_until DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  INDEX idx_news_visibility (visible_until, show_public, show_members),
  INDEX idx_news_author (author_id),
  CONSTRAINT fk_news_posts_author
    FOREIGN KEY (author_id) REFERENCES users (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
