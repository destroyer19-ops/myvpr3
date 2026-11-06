-- ============================================================================
-- Migration: 003_kingschat_oauth.sql
-- Description: Database schema for KingsChat OAuth 2.0 / SSO integration
-- ============================================================================

-- 1. Table for tracking transient OAuth states (CSRF protection with 10m TTL)
CREATE TABLE IF NOT EXISTS `oauth_states` (
  `state` varchar(128) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`state`),
  KEY `idx_state_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table for linking external OAuth providers (KingsChat) to local users
CREATE TABLE IF NOT EXISTS `user_oauth_providers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `provider` varchar(50) NOT NULL,
  `provider_user_id` varchar(191) NOT NULL,
  `access_token` text,
  `refresh_token` text,
  `token_expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_provider_user` (`provider`, `provider_user_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_user_oauth_user_id` FOREIGN KEY (`user_id`) REFERENCES `praise_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Ensure legacy praise_users table has kingschat_id column and index (if not already present)
-- Note: If praise_users already has kingschat_id, ignore duplicate column notice.
-- ALTER TABLE `praise_users` ADD COLUMN `kingschat_id` VARCHAR(100) DEFAULT NULL;
-- ALTER TABLE `praise_users` ADD UNIQUE KEY `kingschat_id` (`kingschat_id`);
