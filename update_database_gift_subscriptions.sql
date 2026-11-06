-- Add gift-related columns to transactions
ALTER TABLE `transactions`
  ADD `gifted_user_id` int(11) NULL AFTER `user_id`,
  ADD `gift_message` varchar(255) DEFAULT NULL AFTER `gifted_user_id`;

ALTER TABLE `transactions`
  ADD KEY `gifted_user_id` (`gifted_user_id`),
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`gifted_user_id`) REFERENCES `praise_users` (`id`) ON DELETE SET NULL;
