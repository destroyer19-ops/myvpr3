-- KingsChat OAuth Integration Migration
-- Run this against your VPR database

-- Links external OAuth providers (KingsChat) to local praise_users
CREATE TABLE IF NOT EXISTS `user_oauth_providers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `provider` varchar(50) NOT NULL DEFAULT 'kingschat',
  `provider_user_id` varchar(255) NOT NULL,
  `access_token` text,
  `refresh_token` text,
  `token_expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `provider_user` (`provider`, `provider_user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_oauth_user` FOREIGN KEY (`user_id`) REFERENCES `praise_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Temporary state tokens for OAuth CSRF protection (auto-cleaned after 10 minutes)
CREATE TABLE IF NOT EXISTS `oauth_states` (
  `state` varchar(64) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
