-- Future Skills - Robotics & AI : MySQL schema (Railway MySQL / MariaDB / XAMPP)
-- The app creates these tables automatically on first visit. You can also run this file
-- manually (Railway: MySQL service -> Data -> Query, or any MySQL client).

CREATE TABLE IF NOT EXISTS site_content (
    id         TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    data       MEDIUMTEXT NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    phone         VARCHAR(20)  NOT NULL,
    name          VARCHAR(100) NOT NULL DEFAULT '',
    school        VARCHAR(150) NOT NULL DEFAULT '',
    email         VARCHAR(150) NOT NULL DEFAULT '',
    created_at    DATETIME NOT NULL,
    last_login_at DATETIME NULL,
    UNIQUE KEY uq_users_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS otp_requests (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    phone      VARCHAR(20)  NOT NULL,
    ip         VARCHAR(45)  NOT NULL DEFAULT '',
    code_hash  VARCHAR(255) NOT NULL,
    attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    used       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at INT UNSIGNED NOT NULL,
    expires_at INT UNSIGNED NOT NULL,
    KEY idx_otp_phone (phone, created_at),
    KEY idx_otp_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    sender     VARCHAR(5)   NOT NULL,            -- 'user' or 'admin'
    body       TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    admin_read TINYINT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_chat_user (user_id, id),
    CONSTRAINT fk_chat_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inquiries (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    school     VARCHAR(150) NOT NULL DEFAULT '',
    email      VARCHAR(150) NOT NULL,
    phone      VARCHAR(30)  NOT NULL,
    phone_norm VARCHAR(20)  NOT NULL DEFAULT '', -- normalised +91XXXXXXXXXX, links an inquiry to a user account
    message    TEXT NOT NULL,
    status     VARCHAR(10)  NOT NULL DEFAULT 'Pending',   -- 'Pending' or 'Replied'
    reply      TEXT NULL,
    created_at DATETIME NOT NULL,
    replied_at DATETIME NULL,
    KEY idx_inq_phone (phone_norm),
    KEY idx_inq_status (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inquiry_replies (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    inquiry_id INT UNSIGNED NOT NULL,
    sender     VARCHAR(5)   NOT NULL,            -- 'user' or 'admin'
    body       TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_reply_inquiry (inquiry_id, id),
    CONSTRAINT fk_reply_inquiry FOREIGN KEY (inquiry_id) REFERENCES inquiries (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
