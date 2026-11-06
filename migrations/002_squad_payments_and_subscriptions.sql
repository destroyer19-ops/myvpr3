-- ==============================================================================
-- 002_squad_payments_and_subscriptions.sql
-- Migration for Squad Payment Gateway integration, Subscription Lifecycle,
-- Dynamic Subscription Plans, and Payment Audit Logging.
-- ==============================================================================

-- 1. Create authoritative subscription_plans table
CREATE TABLE IF NOT EXISTS `subscription_plans` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `plan_code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `category` VARCHAR(30) NOT NULL DEFAULT 'membership', -- 'membership', 'streaming'
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'NGN',
  `interval_unit` VARCHAR(20) NOT NULL DEFAULT 'month', -- 'day', 'month', 'year'
  `interval_count` INT(11) NOT NULL DEFAULT 1,
  `features_json` TEXT NULL, -- JSON array of bullet points
  `limits_json` TEXT NULL,   -- JSON object for tier limits
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `badge_text` VARCHAR(50) NULL,
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default plans if not already populated
INSERT IGNORE INTO `subscription_plans` 
  (`plan_code`, `name`, `description`, `category`, `price`, `currency`, `interval_unit`, `interval_count`, `features_json`, `limits_json`, `is_active`, `is_featured`, `badge_text`, `sort_order`) 
VALUES 
  ('member_free', 'Free Worship', 'Basic access to daily praise broadcasts and public crusade viewings.', 'membership', 0.00, 'NGN', 'month', 1, 
   '["Unlimited public live broadcasts", "Access to open crusade rooms", "Standard video quality (720p)", "Community chat participation"]', 
   '{"max_crusade_attendees": 0, "can_host_crusade": false, "can_host_meetings": false, "resolution": "720p"}', 1, 0, NULL, 1),
  
  ('member_monthly', 'Premium Member', 'Complete individual access with HD streams, digital crusade hosting, and full VOD catalog.', 'membership', 3000.00, 'NGN', 'month', 1, 
   '["Full 1080p Full HD praise broadcasts", "Host digital crusades with up to 5,000 attendees", "Create praise meeting rooms & watch parties", "Complete on-demand video & ministration library", "Exclusive children outreach series & teachings", "No interruptions or stream cooldowns"]', 
   '{"max_crusade_attendees": 5000, "can_host_crusade": true, "can_host_meetings": true, "resolution": "1080p"}', 1, 1, 'Most Popular', 2),
  
  ('member_annual', 'Premium Annual', 'Full premium access for a full year with 2 months free savings.', 'membership', 30000.00, 'NGN', 'year', 1, 
   '["Everything in Premium Member plan", "Save ₦6,000 annually (2 months free)", "Priority access to special praise festivals", "Dedicated prayer & testimony spotlight eligibility", "Early access to upcoming ministration releases"]', 
   '{"max_crusade_attendees": 5000, "can_host_crusade": true, "can_host_meetings": true, "resolution": "1080p"}', 1, 0, 'Best Value (Save 17%)', 3),

  ('studio_basic', 'Studio Basic', 'Broadcasting starter tier for worship leaders and small fellowship streams.', 'streaming', 15000.00, 'NGN', 'month', 1, 
   '["Dedicated AWS IVS live channel", "Up to 100 concurrent viewers", "4 hours max broadcast duration", "720p HD streaming with OBS / Browser Studio", "Real-time viewer engagement analytics"]', 
   '{"max_viewers": 100, "max_duration_hours": 4, "resolution": "720p"}', 1, 0, NULL, 4),

  ('studio_pro', 'Studio Pro Broadcaster', 'Professional studio suite with high capacity and low latency for ministries.', 'streaming', 45000.00, 'NGN', 'month', 1, 
   '["Dedicated high-capacity AWS IVS channel", "Up to 1,000 concurrent viewers", "12 hours max broadcast duration", "1080p Full HD 60fps streaming", "Remote guest cards & multi-presenter feeds", "Priority media encoding & archival"]', 
   '{"max_viewers": 1000, "max_duration_hours": 12, "resolution": "1080p"}', 1, 0, 'Ministry Favorite', 5);

-- 2. Extend transactions table with gateway fields
-- Note: MySQL 5.7+ / 8.0 compatibility
ALTER TABLE `transactions` 
  ADD COLUMN `transaction_ref` VARCHAR(100) NULL UNIQUE AFTER `id`,
  ADD COLUMN `gateway` VARCHAR(30) NOT NULL DEFAULT 'manual' AFTER `proof_of_transaction`,
  ADD COLUMN `currency` VARCHAR(10) NOT NULL DEFAULT 'NGN' AFTER `amount`,
  ADD COLUMN `channel` VARCHAR(50) NULL AFTER `gateway`,
  ADD COLUMN `gateway_response` TEXT NULL AFTER `channel`,
  ADD COLUMN `metadata` TEXT NULL AFTER `gateway_response`,
  ADD COLUMN `is_recurring` TINYINT(1) NOT NULL DEFAULT 0 AFTER `metadata`,
  ADD COLUMN `card_token` VARCHAR(255) NULL AFTER `is_recurring`,
  ADD COLUMN `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- 3. Extend subscriptions table with plan and renewal tracking
ALTER TABLE `subscriptions`
  ADD COLUMN `plan_id` INT(11) NULL AFTER `user_id`,
  ADD COLUMN `plan_code` VARCHAR(50) NULL AFTER `subscription_type`,
  ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'active' AFTER `streaming_status`,
  ADD COLUMN `auto_renew` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
  ADD COLUMN `renewal_count` INT(11) NOT NULL DEFAULT 0 AFTER `auto_renew`,
  ADD COLUMN `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- 4. Create payment audit logs table
CREATE TABLE IF NOT EXISTS `payment_audit_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` INT(11) NULL,
  `transaction_ref` VARCHAR(100) NULL,
  `user_id` INT(11) NULL,
  `action` VARCHAR(50) NOT NULL, -- INITIATED, VERIFIED, WEBHOOK_RECEIVED, MANUAL_APPROVE, MANUAL_REJECT, CANCELLED, STATUS_OVERRIDE
  `actor_type` VARCHAR(30) NOT NULL DEFAULT 'system', -- 'user', 'system', 'webhook', 'admin'
  `actor_id` INT(11) NULL,
  `reason` TEXT NULL,
  `payload` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_txn` (`transaction_id`),
  KEY `idx_audit_ref` (`transaction_ref`),
  KEY `idx_audit_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
