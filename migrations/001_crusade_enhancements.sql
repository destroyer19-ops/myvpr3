-- ============================================================
-- Migration: Crusade & Event Enhancements
-- Adds timezone support, proper status enum, and viewer session columns
-- ============================================================

-- Add timezone column to praise_crusades if it doesn't exist
ALTER TABLE `praise_crusades`
  ADD COLUMN IF NOT EXISTS `timezone` VARCHAR(100) DEFAULT 'UTC' AFTER `end_time`,
  ADD COLUMN IF NOT EXISTS `thumbnail_url` VARCHAR(512) DEFAULT NULL AFTER `timezone`,
  ADD COLUMN IF NOT EXISTS `status` ENUM('draft','scheduled','live','ended','cancelled') DEFAULT 'live' AFTER `is_active`,
  ADD COLUMN IF NOT EXISTS `cancelled_at` DATETIME DEFAULT NULL AFTER `status`;

-- Add visitor tracking columns to praise_crusade_stats
ALTER TABLE `praise_crusade_stats`
  ADD COLUMN IF NOT EXISTS `visitor_session_id` VARCHAR(128) DEFAULT NULL AFTER `user_agent`,
  ADD COLUMN IF NOT EXISTS `visitor_name` VARCHAR(255) DEFAULT NULL AFTER `visitor_session_id`,
  ADD COLUMN IF NOT EXISTS `visitor_email` VARCHAR(255) DEFAULT NULL AFTER `visitor_name`,
  ADD COLUMN IF NOT EXISTS `country` VARCHAR(100) DEFAULT NULL AFTER `visitor_email`,
  ADD COLUMN IF NOT EXISTS `city` VARCHAR(100) DEFAULT NULL AFTER `country`,
  ADD COLUMN IF NOT EXISTS `stayed_for_a_minute` TINYINT(1) DEFAULT 0 AFTER `city`,
  ADD COLUMN IF NOT EXISTS `last_seen_at` DATETIME DEFAULT NULL AFTER `stayed_for_a_minute`;

-- Add index on visitor_session_id for faster lookup
ALTER TABLE `praise_crusade_stats`
  ADD INDEX IF NOT EXISTS `idx_visitor_session` (`visitor_session_id`),
  ADD INDEX IF NOT EXISTS `idx_crusade_id` (`crusade_id`);

-- Ensure praise_testimonies has all required columns
ALTER TABLE `praise_testimonies`
  ADD COLUMN IF NOT EXISTS `status` ENUM('pending','approved','published','rejected') DEFAULT 'pending' AFTER `testimony_text`,
  ADD COLUMN IF NOT EXISTS `reviewed_at` DATETIME DEFAULT NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `created_at` DATETIME DEFAULT NOW() AFTER `reviewed_at`;
