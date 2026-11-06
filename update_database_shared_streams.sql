-- Add is_active to praise_user_shared_streams
ALTER TABLE `praise_user_shared_streams`
  ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `stream_key`;
