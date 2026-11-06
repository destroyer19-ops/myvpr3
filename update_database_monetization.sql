-- Add subscription_status to praise_users table
ALTER TABLE `praise_users` ADD `subscription_status` VARCHAR(20) NOT NULL DEFAULT 'inactive';

-- Create subscriptions table
CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subscription_type` varchar(50) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `praise_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create transactions table
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `kc_handle` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `proof_of_transaction` varchar(255) NOT NULL,
  `subscription_type` varchar(50) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `praise_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add columns for scheduled crusades and is_active flag
ALTER TABLE `praise_crusades`
ADD `is_active` TINYINT(1) NOT NULL DEFAULT 1,
ADD `start_time` DATETIME NULL DEFAULT NULL,
ADD `end_time` DATETIME NULL DEFAULT NULL;

-- Create testimonies table
CREATE TABLE `praise_testimonies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `country` varchar(100) DEFAULT NULL,
  `testimony_text` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `praise_testimonies_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `praise_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;