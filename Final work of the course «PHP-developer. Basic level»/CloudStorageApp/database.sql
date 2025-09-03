-- Создание базы данных с нужной кодировкой и сортировкой
DROP DATABASE IF EXISTS `cloud_storage`;

CREATE DATABASE `cloud_storage` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `cloud_storage`;

-- Создание таблицы users
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) NOT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) NOT NULL,
  `gender` enum('male', 'female') NOT NULL,
  `age` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin', 'user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  `is_banned` tinyint(1) DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Создание таблицы directories с внешними ключами и ON DELETE CASCADE
DROP TABLE IF EXISTS `directories`;

CREATE TABLE `directories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT 'Корневая папка',
  `user_id` int(11) NOT NULL,
  `directory_name` varchar(255) NOT NULL DEFAULT 'Корневая папка',
  `parent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `directories_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `directories_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `directories` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Создание таблицы files с внешними ключами и ON DELETE CASCADE / SET NULL
DROP TABLE IF EXISTS `files`;

CREATE TABLE `files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `size` int(11) NOT NULL DEFAULT 0,
  `filepath` text DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `directory_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `mime_type` varchar(255) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `directory_id` (`directory_id`),
  CONSTRAINT `files_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_files_directory` FOREIGN KEY (`directory_id`) REFERENCES `directories` (`id`) ON DELETE
  SET
    NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Создание таблицы shared_items с внешними ключами и ON DELETE CASCADE
DROP TABLE IF EXISTS `shared_items`;

CREATE TABLE `shared_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_type` varchar(50) NOT NULL,
  `item_id` int(11) NOT NULL,
  `shared_by_user_id` int(11) NOT NULL,
  `shared_with_user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `item_type` (`item_type`),
  KEY `shared_by_user_id` (`shared_by_user_id`),
  KEY `shared_with_user_id` (`shared_with_user_id`),
  CONSTRAINT `shared_items_ibfk_1` FOREIGN KEY (`shared_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `shared_items_ibfk_2` FOREIGN KEY (`shared_with_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `password_reset_tokens` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `token` VARCHAR(255) NOT NULL UNIQUE,
  `expires_at` INT(11) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`),
  CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

ALTER TABLE
  `password_reset_tokens`
MODIFY
  COLUMN `expires_at` datetime;

-- ===== ДВУХФАКТОРНАЯ АУТЕНТИФИКАЦИЯ (2FA) =====
-- 1. Добавляем поля в таблицу users для 2FA
ALTER TABLE
  users
ADD
  COLUMN two_factor_enabled TINYINT(1) DEFAULT 0 COMMENT 'Включена ли 2FA для пользователя',
ADD
  COLUMN two_factor_method ENUM('email', 'totp') DEFAULT 'email' COMMENT 'Метод 2FA: email или TOTP',
ADD
  COLUMN two_factor_secret VARCHAR(32) DEFAULT NULL COMMENT 'Секретный ключ для TOTP',
ADD
  COLUMN two_factor_backup_codes TEXT DEFAULT NULL COMMENT 'Резервные коды для восстановления доступа',
ADD
  COLUMN two_factor_setup_completed TINYINT(1) DEFAULT 0 COMMENT 'Завершена ли настройка 2FA';

-- 2. Создаем таблицу для временных кодов 2FA
CREATE TABLE two_factor_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  code VARCHAR(10) NOT NULL COMMENT 'Код подтверждения',
  type ENUM('email', 'totp') NOT NULL COMMENT 'Тип кода',
  expires_at TIMESTAMP NOT NULL COMMENT 'Время истечения кода',
  used TINYINT(1) DEFAULT 0 COMMENT 'Использован ли код',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_code (user_id, code),
  INDEX idx_expires (expires_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 3. Создаем таблицу для системных настроек 2FA
CREATE TABLE system_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT NOT NULL,
  description TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 4. Добавляем системную настройку для принудительной 2FA
INSERT INTO
  system_settings (setting_key, setting_value, description)
VALUES
  (
    'force_two_factor_auth',
    '0',
    'Принудительная двухфакторная аутентификация для всех пользователей (0 - выключено, 1 - включено)'
  );

-- 5. Создаем таблицу для логов 2FA (для безопасности)
CREATE TABLE two_factor_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  action ENUM(
    'setup',
    'login_attempt',
    'login_success',
    'login_failed',
    'code_generated',
    'code_used',
    'admin_toggle_forced'
  ) NOT NULL,
  method ENUM('email', 'totp', 'backup_code', 'system') NOT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent TEXT DEFAULT NULL,
  details JSON DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_action (user_id, action),
  INDEX idx_created_at (created_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 6. Создаем таблицу для доверенных устройств (для будущих функций)
CREATE TABLE trusted_devices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  device_name VARCHAR(255) NOT NULL,
  device_fingerprint VARCHAR(255) NOT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent TEXT DEFAULT NULL,
  trusted_until TIMESTAMP NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_device (user_id, device_fingerprint),
  INDEX idx_trusted_until (trusted_until)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Чтобы назначить пользователя администратором по email:
-- UPDATE users SET role = 'admin', is_admin = 1 WHERE email = 'your@email.com';
-- Чтобы назначить пользователя администратором по id:
-- UPDATE users SET role = 'admin', is_admin = 1 WHERE id = 123;