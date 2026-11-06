-- Add KingsChat ID column to praise_users table
ALTER TABLE `praise_users` ADD `kingschat_id` VARCHAR(100) DEFAULT NULL;
ALTER TABLE `praise_users` ADD UNIQUE KEY `kingschat_id` (`kingschat_id`);
