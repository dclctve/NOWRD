CREATE DATABASE IF NOT EXISTS nowrd CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nowrd;
CREATE TABLE IF NOT EXISTS users (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS posts (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(220) NOT NULL, slug VARCHAR(240) NOT NULL UNIQUE, excerpt TEXT NULL, seo_title VARCHAR(220) NULL, seo_description VARCHAR(320) NULL, canonical_url VARCHAR(500) NULL, content LONGTEXT NOT NULL, cover_image VARCHAR(500) NULL, status ENUM('draft','in_review','approved','published') NOT NULL DEFAULT 'draft', author_id INT UNSIGNED NULL, published_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX(status,published_at), CONSTRAINT fk_posts_author FOREIGN KEY(author_id) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT NOT NULL) ENGINE=InnoDB;
INSERT IGNORE INTO settings(setting_key,setting_value) VALUES ('site_tagline','A quieter space for ideas.'),('site_description',''),('site_url',''),('accent_color','#b7f36b');

CREATE TABLE IF NOT EXISTS media (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, original_name VARCHAR(255) NOT NULL, file_name VARCHAR(100) NOT NULL UNIQUE, file_path VARCHAR(500) NOT NULL, mime_type VARCHAR(100) NOT NULL, file_size BIGINT UNSIGNED NOT NULL, width INT UNSIGNED NOT NULL, height INT UNSIGNED NOT NULL, alt_text VARCHAR(300) NOT NULL DEFAULT '', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_media_created (created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, slug VARCHAR(140) NOT NULL UNIQUE, description VARCHAR(500) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS tags (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL, slug VARCHAR(100) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS post_categories (post_id INT UNSIGNED NOT NULL, category_id INT UNSIGNED NOT NULL, PRIMARY KEY(post_id,category_id), INDEX idx_pc_category(category_id), CONSTRAINT fk_pc_post FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE, CONSTRAINT fk_pc_category FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS post_tags (post_id INT UNSIGNED NOT NULL, tag_id INT UNSIGNED NOT NULL, PRIMARY KEY(post_id,tag_id), INDEX idx_pt_tag(tag_id), CONSTRAINT fk_pt_post FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE, CONSTRAINT fk_pt_tag FOREIGN KEY(tag_id) REFERENCES tags(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS post_revisions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 post_id INT UNSIGNED NOT NULL,
 title VARCHAR(220) NOT NULL,
 slug VARCHAR(240) NOT NULL,
 excerpt TEXT NULL,
 seo_title VARCHAR(220) NULL,
 seo_description VARCHAR(320) NULL,
 canonical_url VARCHAR(500) NULL,
 content LONGTEXT NOT NULL,
 cover_image VARCHAR(500) NULL,
 status ENUM('draft','in_review','approved','published') NOT NULL DEFAULT 'draft',
 published_at DATETIME NULL,
 created_by INT UNSIGNED NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_revisions_post_created (post_id, created_at),
 CONSTRAINT fk_revisions_post FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE,
 CONSTRAINT fk_revisions_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_workflow_events (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, post_id INT UNSIGNED NOT NULL, actor_id INT UNSIGNED NULL, from_status VARCHAR(24) NOT NULL DEFAULT '', to_status VARCHAR(24) NOT NULL, note VARCHAR(500) NOT NULL DEFAULT '', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_workflow_post(post_id,created_at), CONSTRAINT fk_workflow_post FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE, CONSTRAINT fk_workflow_actor FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- v27: privacy-conscious aggregate page views (no raw IP/user-agent/cookie)
CREATE TABLE IF NOT EXISTS analytics_daily (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, day DATE NOT NULL, page_type VARCHAR(20) NOT NULL DEFAULT 'page', post_id INT UNSIGNED NULL, views INT UNSIGNED NOT NULL DEFAULT 0, UNIQUE KEY uq_analytics_day_type_post(day,page_type,post_id), INDEX idx_analytics_day(day), INDEX idx_analytics_post(post_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
