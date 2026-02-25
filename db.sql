-- =========================================================
-- DFPS FULL DATABASE RESET + CREATE (MySQL)
-- Start to End - aligned with your Registration Form
-- =========================================================

CREATE DATABASE IF NOT EXISTS dfps
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE dfps;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS sms_queue;
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS post_interests;
DROP TABLE IF EXISTS notifications;

DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS conversation_participants;
DROP TABLE IF EXISTS conversations;

DROP TABLE IF EXISTS post_images;
DROP TABLE IF EXISTS posts;

DROP TABLE IF EXISTS price_rules;
DROP TABLE IF EXISTS produce;

DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS areas;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- AREAS
-- =========================================================
CREATE TABLE areas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_areas_name (name)
) ENGINE=InnoDB;

-- =========================================================
-- USERS (Aligned with Registration Form)
-- roles: FARMER, BUYER, DA
-- =========================================================
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,

  first_name VARCHAR(100) NOT NULL,
  last_name  VARCHAR(100) NOT NULL,
  username   VARCHAR(100) NOT NULL,
  address    VARCHAR(255) NOT NULL,

  email VARCHAR(200) NOT NULL,
  phone VARCHAR(30) NOT NULL,

  password_hash VARCHAR(255) NOT NULL,

  role ENUM('FARMER','BUYER','DA') NOT NULL,
  area_id INT NULL,

  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_username (username),
  KEY idx_users_role (role),
  KEY idx_users_area (area_id),

  CONSTRAINT fk_users_area
    FOREIGN KEY (area_id) REFERENCES areas(id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- PRODUCE (Master list)
-- =========================================================
CREATE TABLE produce (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  unit VARCHAR(30) NOT NULL DEFAULT 'kg',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_produce_name (name)
) ENGINE=InnoDB;

-- =========================================================
-- PRICE RULES (DA sets min/max)
-- area_id can be NULL for global rule
-- =========================================================
CREATE TABLE price_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  produce_id INT NOT NULL,
  area_id INT NULL,
  min_price DECIMAL(10,2) NOT NULL,
  max_price DECIMAL(10,2) NOT NULL,
  effective_from DATE NOT NULL,
  effective_to DATE NULL,
  created_by_da_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  KEY idx_price_rules_produce (produce_id),
  KEY idx_price_rules_area (area_id),
  KEY idx_price_rules_dates (effective_from, effective_to),

  CONSTRAINT fk_price_rules_produce
    FOREIGN KEY (produce_id) REFERENCES produce(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,

  CONSTRAINT fk_price_rules_area
    FOREIGN KEY (area_id) REFERENCES areas(id)
    ON UPDATE CASCADE
    ON DELETE SET NULL,

  CONSTRAINT fk_price_rules_da
    FOREIGN KEY (created_by_da_id) REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =========================================================
-- POSTS (Farmer listings)
-- status: ACTIVE, SOLD, HIDDEN, FLAGGED
-- =========================================================
CREATE TABLE posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  farmer_id INT NOT NULL,
  produce_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  price DECIMAL(10,2) NOT NULL,
  quantity DECIMAL(12,2) NULL,
  unit VARCHAR(30) NOT NULL DEFAULT 'kg',
  area_id INT NULL,
  status ENUM('ACTIVE','SOLD','HIDDEN','FLAGGED') NOT NULL DEFAULT 'ACTIVE',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  KEY idx_posts_farmer (farmer_id),
  KEY idx_posts_produce (produce_id),
  KEY idx_posts_area (area_id),
  KEY idx_posts_status (status),
  KEY idx_posts_created (created_at),

  CONSTRAINT fk_posts_farmer
    FOREIGN KEY (farmer_id) REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,

  CONSTRAINT fk_posts_produce
    FOREIGN KEY (produce_id) REFERENCES produce(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,

  CONSTRAINT fk_posts_area
    FOREIGN KEY (area_id) REFERENCES areas(id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- POST IMAGES
-- =========================================================
CREATE TABLE post_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  KEY idx_post_images_post (post_id),

  CONSTRAINT fk_post_images_post
    FOREIGN KEY (post_id) REFERENCES posts(id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- CONVERSATIONS (message threads)
-- =========================================================
CREATE TABLE conversations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE conversation_participants (
  conversation_id INT NOT NULL,
  user_id INT NOT NULL,
  joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,

  PRIMARY KEY (conversation_id, user_id),
  KEY idx_cp_user (user_id),

  CONSTRAINT fk_cp_conversation
    FOREIGN KEY (conversation_id) REFERENCES conversations(id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,

  CONSTRAINT fk_cp_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE messages (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT NOT NULL,
  sender_id INT NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at TIMESTAMP NULL DEFAULT NULL,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,

  KEY idx_messages_conversation (conversation_id, created_at),
  KEY idx_messages_sender (sender_id),

  CONSTRAINT fk_messages_conversation
    FOREIGN KEY (conversation_id) REFERENCES conversations(id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,

  CONSTRAINT fk_messages_sender
    FOREIGN KEY (sender_id) REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =========================================================
-- NOTIFICATIONS (in-app)
-- =========================================================
CREATE TABLE notifications (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type VARCHAR(50) NOT NULL,
  title VARCHAR(150) NOT NULL,
  body TEXT NOT NULL,
  link VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  KEY idx_notif_user (user_id, created_at),
  KEY idx_notif_unread (user_id, is_read),

  CONSTRAINT fk_notifications_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- POST INTERESTS (buyer expresses interest)
-- =========================================================
CREATE TABLE post_interests (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  buyer_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY uq_interest_once (post_id, buyer_id),
  KEY idx_interests_post (post_id),
  KEY idx_interests_buyer (buyer_id),

  CONSTRAINT fk_interests_post
    FOREIGN KEY (post_id) REFERENCES posts(id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,

  CONSTRAINT fk_interests_buyer
    FOREIGN KEY (buyer_id) REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- ANNOUNCEMENTS (DA posts to all or area-based)
-- area_id NULL = global
-- =========================================================
CREATE TABLE announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  da_id INT NOT NULL,
  area_id INT NULL,
  title VARCHAR(200) NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  KEY idx_announce_da (da_id),
  KEY idx_announce_area (area_id),
  KEY idx_announce_created (created_at),

  CONSTRAINT fk_announce_da
    FOREIGN KEY (da_id) REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,

  CONSTRAINT fk_announce_area
    FOREIGN KEY (area_id) REFERENCES areas(id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- SMS QUEUE (DA notifications via SMS)
-- =========================================================
CREATE TABLE sms_queue (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  to_phone VARCHAR(30) NOT NULL,
  message VARCHAR(500) NOT NULL,
  status ENUM('PENDING','SENT','FAILED') NOT NULL DEFAULT 'PENDING',
  provider_response TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at TIMESTAMP NULL DEFAULT NULL,

  KEY idx_sms_status (status, created_at),
  KEY idx_sms_phone (to_phone)
) ENGINE=InnoDB;

-- =========================================================
-- Optional seed data (safe)
-- =========================================================
INSERT INTO areas (name) VALUES ('Default Area')
  ON DUPLICATE KEY UPDATE name=name;

INSERT INTO produce (name, unit) VALUES
  ('Tomato','kg'),
  ('Onion','kg'),
  ('Rice','kg')
ON DUPLICATE KEY UPDATE name=name;

-- =========================================================
-- DONE
-- =========================================================
