-- Phase 1: Self-Service Live Streaming Database Tables

-- 1. Streaming Plans Table
CREATE TABLE `streaming_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `billing_period` varchar(20) NOT NULL DEFAULT 'monthly', -- 'monthly', 'yearly', 'one-time'
  `max_duration_hours` int(11) DEFAULT NULL, -- NULL means unlimited
  `max_viewers` int(11) DEFAULT NULL, -- NULL means unlimited
  `resolution` varchar(20) DEFAULT '1080p', -- '720p', '1080p'
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert some default plans based on typical AWS IVS usage
INSERT INTO `streaming_plans` (`name`, `description`, `price`, `billing_period`, `max_duration_hours`, `max_viewers`, `resolution`) VALUES 
('Basic Streamer', 'Perfect for small meetings and personal broadcasts', 10.00, 'monthly', 4, 100, '720p'),
('Pro Broadcaster', 'Professional streaming with high resolution', 30.00, 'monthly', 12, 1000, '1080p');

-- 2. Modify Subscriptions Table
-- We add streaming_plan_id to link to the new plans
ALTER TABLE `subscriptions` 
ADD COLUMN `streaming_plan_id` int(11) DEFAULT NULL AFTER `subscription_type`,
ADD COLUMN `streaming_status` varchar(20) DEFAULT 'inactive' AFTER `streaming_plan_id`, -- 'active', 'expired', 'suspended'
ADD CONSTRAINT `fk_subscriptions_plan` FOREIGN KEY (`streaming_plan_id`) REFERENCES `streaming_plans` (`id`) ON DELETE SET NULL;

-- 3. Streaming Channels Table
-- Stores the AWS IVS channel details for a user
CREATE TABLE `streaming_channels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `aws_channel_arn` varchar(255) NOT NULL,
  `aws_stream_key_arn` varchar(255) NOT NULL,
  `ingest_endpoint` varchar(255) NOT NULL,
  `playback_url` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `fk_streaming_channels_user` FOREIGN KEY (`user_id`) REFERENCES `praise_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Streaming Sessions Table
-- Tracks historical broadcasts
CREATE TABLE `streaming_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `channel_id` int(11) NOT NULL,
  `aws_stream_id` varchar(255) DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'live', -- 'live', 'ended'
  `viewer_count_peak` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `channel_id` (`channel_id`),
  CONSTRAINT `fk_streaming_sessions_channel` FOREIGN KEY (`channel_id`) REFERENCES `streaming_channels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
