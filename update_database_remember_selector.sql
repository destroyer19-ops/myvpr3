-- Add remember_selector for "remember me" functionality
ALTER TABLE `praise_users`
ADD `remember_selector` VARCHAR(32) NULL DEFAULT NULL AFTER `remember_token`;
