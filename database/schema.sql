-- MOM Writer database schema
-- MySQL 5.7.8+ / 8.x or MariaDB 10.3+
-- Usage: mysql -u root -p < database/schema.sql

CREATE DATABASE IF NOT EXISTS mom_writer
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE mom_writer;

-- One row per meeting. The full MOM (sections, attendees, signatures...) is
-- stored as JSON in `data`; the other columns are copies used for listing
-- and searching, refreshed by the API on every save.
CREATE TABLE IF NOT EXISTS moms (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title         VARCHAR(255) NOT NULL,
  meeting_date  DATE         NULL,
  location      VARCHAR(255) NULL,
  recorder      VARCHAR(255) NULL,
  open_actions  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  search_text   TEXT         NULL,
  data          LONGTEXT     NOT NULL,
  version       INT UNSIGNED NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at    DATETIME     NULL,
  PRIMARY KEY (id),
  KEY idx_moms_date (deleted_at, meeting_date),
  KEY idx_moms_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Address book used to fill in attendees quickly.
CREATE TABLE IF NOT EXISTS contacts (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(255) NOT NULL,
  email       VARCHAR(255) NULL,
  role        VARCHAR(255) NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_contacts_email (email),
  KEY idx_contacts_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Application user (change the password before running in production):
-- CREATE USER IF NOT EXISTS 'mom'@'%' IDENTIFIED BY 'change-me';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON mom_writer.* TO 'mom'@'%';
