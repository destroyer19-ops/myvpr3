CREATE TABLE `praise_salvation_clicks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `crusade_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `crusade_id` (`crusade_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `praise_salvation_clicks_ibfk_1` FOREIGN KEY (`crusade_id`) REFERENCES `praise_crusades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `praise_salvation_clicks_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `praise_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `praise_crusade_stats` ADD `country` VARCHAR(100) NULL DEFAULT NULL AFTER `user_agent`;