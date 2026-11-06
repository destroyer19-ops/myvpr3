-- Add columns to praise_crusade_stats for shared streams and engagement tracking
ALTER TABLE `praise_crusade_stats`
  ADD COLUMN `stream_key` VARCHAR(255) NULL DEFAULT NULL AFTER `crusade_id`,
  ADD COLUMN `stayed_for_a_minute` TINYINT(1) NOT NULL DEFAULT 0 AFTER `user_agent`;

-- Create praise_admins table if it does not exist
CREATE TABLE `praise_admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
