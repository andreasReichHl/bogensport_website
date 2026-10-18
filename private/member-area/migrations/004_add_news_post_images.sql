CREATE TABLE IF NOT EXISTS news_post_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_news_post_images_post (post_id, sort_order, id),
  CONSTRAINT fk_news_post_images_post
    FOREIGN KEY (post_id) REFERENCES news_posts (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO news_post_images (post_id, image_path, sort_order, created_at)
SELECT news_posts.id, news_posts.image_path, 0, news_posts.created_at
FROM news_posts
WHERE news_posts.image_path IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM news_post_images
    WHERE news_post_images.post_id = news_posts.id
      AND news_post_images.image_path = news_posts.image_path
  );
