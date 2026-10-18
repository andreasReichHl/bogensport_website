CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(190) NOT NULL UNIQUE,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('superadmin', 'admin', 'member') NOT NULL DEFAULT 'member',
  name VARCHAR(190) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  token_version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  INDEX idx_users_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_module_permissions (
  user_id INT UNSIGNED NOT NULL,
  module_key VARCHAR(80) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, module_key),
  CONSTRAINT fk_user_module_permissions_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identifier_hash CHAR(64) NOT NULL,
  ip_hash CHAR(64) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_attempts_identifier (identifier_hash, attempted_at),
  INDEX idx_login_attempts_ip (ip_hash, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS easyverein_tokens (
  id TINYINT UNSIGNED PRIMARY KEY,
  token VARCHAR(255) NOT NULL,
  refreshed_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS membership_applications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(20) NOT NULL UNIQUE,
  status ENUM('pending', 'confirmed', 'synced', 'sync_failed') NOT NULL DEFAULT 'pending',
  data_encrypted MEDIUMBLOB NOT NULL,
  email_hash CHAR(64) NOT NULL,
  person_hash CHAR(64) NOT NULL,
  confirm_token_hash CHAR(64) NULL UNIQUE,
  confirm_expires_at DATETIME NOT NULL,
  submitted_at DATETIME NOT NULL,
  submitted_ip VARCHAR(45) NOT NULL,
  submitted_user_agent VARCHAR(255) NOT NULL DEFAULT '',
  confirmed_at DATETIME NULL,
  confirmed_ip VARCHAR(45) NULL,
  confirmed_user_agent VARCHAR(255) NULL,
  signature CHAR(64) NULL,
  applicant_mailed_at DATETIME NULL,
  board_mailed_at DATETIME NULL,
  easyverein_contact_id BIGINT UNSIGNED NULL,
  easyverein_member_id BIGINT UNSIGNED NULL,
  easyverein_login VARCHAR(190) NULL,
  easyverein_group_assigned_at DATETIME NULL,
  easyverein_files_uploaded_at DATETIME NULL,
  sync_note VARCHAR(500) NULL,
  sync_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error VARCHAR(500) NULL,
  synced_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_membership_applications_status (status, confirm_expires_at),
  INDEX idx_membership_applications_ip (submitted_ip, submitted_at),
  INDEX idx_membership_applications_email (email_hash, submitted_at),
  INDEX idx_membership_applications_person (person_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
