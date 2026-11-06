-- Add remember_token and token_expiry for "remember me" functionality
ALTER TABLE `praise_users`
ADD `remember_token` VARCHAR(255) NULL DEFAULT NULL AFTER `password`,
ADD `token_expiry` DATETIME NULL DEFAULT NULL AFTER `remember_token`;

-- Add reset_token and reset_expiry for password reset functionality
ALTER TABLE `praise_users`
ADD `reset_token` VARCHAR(255) NULL DEFAULT NULL AFTER `token_expiry`,
ADD `reset_expiry` DATETIME NULL DEFAULT NULL AFTER `reset_token`;
