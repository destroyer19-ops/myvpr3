<?php
require_once __DIR__ . '/helpers.php';

// Optimization: Avoid running 30+ SHOW COLUMNS queries on every HTTP request.
// Only runs if CLI, explicit ?migrate=1, AUTO_MIGRATE=true, or marker file missing.
$marker_file = __DIR__ . '/.migrated_vpr_schema';
$should_run = (php_sapi_name() === 'cli')
    || (!empty($_GET['migrate']))
    || (getenv('AUTO_MIGRATE') === 'true' || ($_ENV['AUTO_MIGRATE'] ?? '') === 'true')
    || (!file_exists($marker_file));

if (!$should_run) {
    return;
}

// Ensure praise_crusades has status and timezone
$check = $conn->query("SHOW COLUMNS FROM praise_crusades LIKE 'status'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE praise_crusades ADD COLUMN status VARCHAR(20) DEFAULT 'draft'");
}
$check = $conn->query("SHOW COLUMNS FROM praise_crusades LIKE 'timezone'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE praise_crusades ADD COLUMN timezone VARCHAR(100) DEFAULT 'UTC'");
}

// Ensure praise_crusade_stats has tracking columns
$stats_cols = [
    'visitor_session_id' => "VARCHAR(255) NULL",
    'visitor_name' => "VARCHAR(255) NULL",
    'visitor_email' => "VARCHAR(255) NULL",
    'country' => "VARCHAR(100) NULL",
    'city' => "VARCHAR(100) NULL",
    'last_seen_at' => "DATETIME NULL"
];

foreach ($stats_cols as $col => $def) {
    $check = $conn->query("SHOW COLUMNS FROM praise_crusade_stats LIKE '$col'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE praise_crusade_stats ADD COLUMN $col $def");
    }
}

// Ensure streaming_plans table exists
$check = $conn->query("SHOW TABLES LIKE 'streaming_plans'");
if ($check->num_rows == 0) {
    $conn->query("CREATE TABLE `streaming_plans` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(100) NOT NULL,
      `description` text,
      `price` decimal(10,2) NOT NULL DEFAULT 0.00,
      `billing_period` varchar(20) NOT NULL DEFAULT 'monthly',
      `max_duration_hours` int(11) DEFAULT NULL,
      `max_viewers` int(11) DEFAULT NULL,
      `resolution` varchar(20) DEFAULT '1080p',
      `is_active` tinyint(1) NOT NULL DEFAULT 1,
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Insert defaults
    $conn->query("INSERT INTO `streaming_plans` (`name`, `description`, `price`, `billing_period`, `max_duration_hours`, `max_viewers`, `resolution`) VALUES 
    ('Basic Streamer', 'Perfect for small meetings and personal broadcasts', 10.00, 'monthly', 4, 100, '720p'),
    ('Pro Broadcaster', 'Professional streaming with high resolution', 30.00, 'monthly', 12, 1000, '1080p')");
}

// Ensure subscriptions has streaming columns
$check = $conn->query("SHOW COLUMNS FROM subscriptions LIKE 'streaming_plan_id'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE `subscriptions` ADD COLUMN `streaming_plan_id` int(11) DEFAULT NULL AFTER `subscription_type`");
}
$check = $conn->query("SHOW COLUMNS FROM subscriptions LIKE 'streaming_status'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE `subscriptions` ADD COLUMN `streaming_status` varchar(20) DEFAULT 'inactive' AFTER `streaming_plan_id`");
}

// Ensure streaming_channels table exists
$check = $conn->query("SHOW TABLES LIKE 'streaming_channels'");
if ($check->num_rows == 0) {
    $conn->query("CREATE TABLE `streaming_channels` (
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
      UNIQUE KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Ensure streaming_sessions table exists
$check = $conn->query("SHOW TABLES LIKE 'streaming_sessions'");
if ($check->num_rows == 0) {
    $conn->query("CREATE TABLE `streaming_sessions` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `channel_id` int(11) NOT NULL,
      `aws_stream_id` varchar(255) DEFAULT NULL,
      `start_time` datetime NOT NULL,
      `end_time` datetime DEFAULT NULL,
      `status` varchar(20) NOT NULL DEFAULT 'live',
      `viewer_count_peak` int(11) DEFAULT 0,
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `channel_id` (`channel_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Ensure realtime_stream_sessions table exists for experimental WebRTC
$check = $conn->query("SHOW TABLES LIKE 'realtime_stream_sessions'");
if ($check && $check->num_rows == 0) {
    $conn->query("CREATE TABLE `realtime_stream_sessions` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `stage_arn` varchar(255) NOT NULL,
      `session_id` varchar(100) NOT NULL,
      `status` varchar(20) NOT NULL DEFAULT 'provisioned',
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      `ended_at` datetime DEFAULT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `session_id` (`session_id`),
      KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ── Squad Payments & Subscriptions Auto-Migration ─────────────────────────

// 1. Ensure subscription_plans table exists
$check = $conn->query("SHOW TABLES LIKE 'subscription_plans'");
if ($check && $check->num_rows == 0) {
    $conn->query("CREATE TABLE `subscription_plans` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `plan_code` varchar(50) NOT NULL,
      `name` varchar(100) NOT NULL,
      `description` text,
      `category` varchar(30) NOT NULL DEFAULT 'membership',
      `price` decimal(10,2) NOT NULL DEFAULT 0.00,
      `currency` varchar(10) NOT NULL DEFAULT 'NGN',
      `interval_unit` varchar(20) NOT NULL DEFAULT 'month',
      `interval_count` int(11) NOT NULL DEFAULT 1,
      `features_json` text,
      `limits_json` text,
      `is_active` tinyint(1) NOT NULL DEFAULT 1,
      `is_featured` tinyint(1) NOT NULL DEFAULT 0,
      `badge_text` varchar(50) DEFAULT NULL,
      `sort_order` int(11) NOT NULL DEFAULT 0,
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `plan_code` (`plan_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed defaults
    $conn->query("INSERT IGNORE INTO `subscription_plans` 
      (`plan_code`, `name`, `description`, `category`, `price`, `currency`, `interval_unit`, `interval_count`, `features_json`, `limits_json`, `is_active`, `is_featured`, `badge_text`, `sort_order`) 
    VALUES 
      ('member_free', 'Free Worship', 'Basic access to daily praise broadcasts and public crusade viewings.', 'membership', 0.00, 'NGN', 'month', 1, 
       '[\"Unlimited public live broadcasts\", \"Access to open crusade rooms\", \"Standard video quality (720p)\", \"Community chat participation\"]', 
       '{\"max_crusade_attendees\": 0, \"can_host_crusade\": false, \"can_host_meetings\": false, \"resolution\": \"720p\"}', 1, 0, NULL, 1),
      
      ('member_monthly', 'Premium Member', 'Complete individual access with HD streams, digital crusade hosting, and full VOD catalog.', 'membership', 3000.00, 'NGN', 'month', 1, 
       '[\"Full 1080p Full HD praise broadcasts\", \"Host digital crusades with up to 5,000 attendees\", \"Create praise meeting rooms & watch parties\", \"Complete on-demand video & ministration library\", \"Exclusive children outreach series & teachings\", \"No interruptions or stream cooldowns\"]', 
       '{\"max_crusade_attendees\": 5000, \"can_host_crusade\": true, \"can_host_meetings\": true, \"resolution\": \"1080p\"}', 1, 1, 'Most Popular', 2),
      
      ('member_annual', 'Premium Annual', 'Full premium access for a full year with 2 months free savings.', 'membership', 30000.00, 'NGN', 'year', 1, 
       '[\"Everything in Premium Member plan\", \"Save ₦6,000 annually (2 months free)\", \"Priority access to special praise festivals\", \"Dedicated prayer & testimony spotlight eligibility\", \"Early access to upcoming ministration releases\"]', 
       '{\"max_crusade_attendees\": 5000, \"can_host_crusade\": true, \"can_host_meetings\": true, \"resolution\": \"1080p\"}', 1, 0, 'Best Value (Save 17%)', 3),

      ('studio_basic', 'Studio Basic', 'Broadcasting starter tier for worship leaders and small fellowship streams.', 'streaming', 15000.00, 'NGN', 'month', 1, 
       '[\"Dedicated AWS IVS live channel\", \"Up to 100 concurrent viewers\", \"4 hours max broadcast duration\", \"720p HD streaming with OBS / Browser Studio\", \"Real-time viewer engagement analytics\"]', 
       '{\"max_viewers\": 100, \"max_duration_hours\": 4, \"resolution\": \"720p\"}', 1, 0, NULL, 4),

      ('studio_pro', 'Studio Pro Broadcaster', 'Professional studio suite with high capacity and low latency for ministries.', 'streaming', 45000.00, 'NGN', 'month', 1, 
       '[\"Dedicated high-capacity AWS IVS channel\", \"Up to 1,000 concurrent viewers\", \"12 hours max broadcast duration\", \"1080p Full HD 60fps streaming\", \"Remote guest cards & multi-presenter feeds\", \"Priority media encoding & archival\"]', 
       '{\"max_viewers\": 1000, \"max_duration_hours\": 12, \"resolution\": \"1080p\"}', 1, 0, 'Ministry Favorite', 5)");
}

// 2. Ensure transactions table has Squad columns
$txn_cols = [
    'transaction_ref'  => "VARCHAR(100) NULL UNIQUE",
    'gateway'          => "VARCHAR(30) NOT NULL DEFAULT 'manual'",
    'currency'         => "VARCHAR(10) NOT NULL DEFAULT 'NGN'",
    'channel'          => "VARCHAR(50) NULL",
    'gateway_response' => "TEXT NULL",
    'metadata'         => "TEXT NULL",
    'is_recurring'     => "TINYINT(1) NOT NULL DEFAULT 0",
    'card_token'       => "VARCHAR(255) NULL"
];
foreach ($txn_cols as $col => $def) {
    $check = $conn->query("SHOW COLUMNS FROM transactions LIKE '$col'");
    if ($check && $check->num_rows == 0) {
        $conn->query("ALTER TABLE transactions ADD COLUMN $col $def");
    }
}

// 3. Ensure subscriptions table has plan_code, status, auto_renew
$sub_cols = [
    'plan_id'       => "INT(11) NULL",
    'plan_code'     => "VARCHAR(50) NULL",
    'status'        => "VARCHAR(20) NOT NULL DEFAULT 'active'",
    'auto_renew'    => "TINYINT(1) NOT NULL DEFAULT 0",
    'renewal_count' => "INT(11) NOT NULL DEFAULT 0"
];
foreach ($sub_cols as $col => $def) {
    $check = $conn->query("SHOW COLUMNS FROM subscriptions LIKE '$col'");
    if ($check && $check->num_rows == 0) {
        $conn->query("ALTER TABLE subscriptions ADD COLUMN $col $def");
    }
}

// 4. Ensure payment_audit_logs table exists
$check = $conn->query("SHOW TABLES LIKE 'payment_audit_logs'");
if ($check && $check->num_rows == 0) {
    $conn->query("CREATE TABLE `payment_audit_logs` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `transaction_id` int(11) DEFAULT NULL,
      `transaction_ref` varchar(100) DEFAULT NULL,
      `user_id` int(11) DEFAULT NULL,
      `action` varchar(50) NOT NULL,
      `actor_type` varchar(30) NOT NULL DEFAULT 'system',
      `actor_id` int(11) DEFAULT NULL,
      `reason` text,
      `payload` text,
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_audit_txn` (`transaction_id`),
      KEY `idx_audit_ref` (`transaction_ref`),
      KEY `idx_audit_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// 5. Ensure oauth_states table exists for KingsChat OAuth
$check = $conn->query("SHOW TABLES LIKE 'oauth_states'");
if ($check && $check->num_rows == 0) {
    $conn->query("CREATE TABLE `oauth_states` (
      `state` varchar(128) NOT NULL,
      `created_at` datetime NOT NULL,
      PRIMARY KEY (`state`),
      KEY `idx_state_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// 6. Ensure user_oauth_providers table exists for KingsChat SSO
$check = $conn->query("SHOW TABLES LIKE 'user_oauth_providers'");
if ($check && $check->num_rows == 0) {
    $conn->query("CREATE TABLE `user_oauth_providers` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `provider` varchar(50) NOT NULL,
      `provider_user_id` varchar(191) NOT NULL,
      `access_token` text,
      `refresh_token` text,
      `token_expires_at` datetime DEFAULT NULL,
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `idx_provider_user` (`provider`, `provider_user_id`),
      KEY `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// 7. Ensure praise_users has kingschat_id column for legacy and backward compatibility
$check = $conn->query("SHOW COLUMNS FROM praise_users LIKE 'kingschat_id'");
if ($check && $check->num_rows == 0) {
    $conn->query("ALTER TABLE praise_users ADD COLUMN kingschat_id VARCHAR(100) DEFAULT NULL, ADD UNIQUE KEY kingschat_id (kingschat_id)");
}

// 8. Ensure praise_programs table exists
$check = $conn->query("SHOW TABLES LIKE 'praise_programs'");
if ($check && $check->num_rows == 0) {
    $conn->query("CREATE TABLE `praise_programs` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `title` varchar(255) NOT NULL,
      `description` text NULL,
      `thumbnail_url` varchar(255) NULL,
      `category` varchar(100) DEFAULT 'Worship',
      `schedule_time` datetime NULL,
      `is_live_now` tinyint(1) NOT NULL DEFAULT 0,
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("INSERT INTO `praise_programs` (`id`, `title`, `description`, `thumbnail_url`, `category`, `schedule_time`, `is_live_now`) VALUES
      (1, 'Healing Streams Live Healing Services', 'Global live healing service with Pastor Chris broadcasting live to millions worldwide.', 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=1200&q=80', 'Live Service', NOW(), 1),
      (2, 'Praise Room 24/7', 'Continuous worship, adoration, and music ministry around the clock.', 'https://images.unsplash.com/photo-1465847899084-d164df4dedc6?auto=format&fit=crop&w=1200&q=80', 'Worship', DATE_ADD(NOW(), INTERVAL 2 HOUR), 0),
      (3, 'Children\'s Praise & Faith Stories', 'Inspiring Bible lessons, joyful songs, and animated stories for children and youth.', 'https://images.unsplash.com/photo-1485546246426-74dc88dec4d9?auto=format&fit=crop&w=1200&q=80', 'Children', DATE_ADD(NOW(), INTERVAL 1 DAY), 0),
      (4, 'Global Prayer & Intercession Service', 'United fellowship in prayer with ministers and believers across nations.', 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?auto=format&fit=crop&w=1200&q=80', 'Prayer', DATE_ADD(NOW(), INTERVAL 2 DAY), 0)");
}

// 9. Ensure praise_program_recordings table exists
$check = $conn->query("SHOW TABLES LIKE 'praise_program_recordings'");
if ($check && $check->num_rows == 0) {
    $conn->query("CREATE TABLE `praise_program_recordings` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `program_id` int(11) NULL,
      `title` varchar(255) NOT NULL,
      `description` text NULL,
      `thumbnail_url` varchar(255) NULL,
      `video_url` varchar(500) NOT NULL,
      `duration_seconds` int(11) NOT NULL DEFAULT 0,
      `program_date` date NULL,
      `category` varchar(100) NULL,
      `status` enum('draft', 'published', 'processing') NOT NULL DEFAULT 'draft',
      `created_by` int(11) NULL,
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `program_id` (`program_id`),
      KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("INSERT INTO `praise_program_recordings` (`id`, `program_id`, `title`, `description`, `thumbnail_url`, `video_url`, `duration_seconds`, `program_date`, `category`, `status`) VALUES
      (1, 1, 'A Day of Bliss with Pastor Chris (Samoa)', 'Relive the glorious moments of worship, miraculous demonstrations of the Spirit, and insightful revelations.', 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=1200&q=80', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', 3600, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Ministration', 'published'),
      (2, 4, 'Teaching Series on Prayer — Part 2', 'In-depth biblical exposition on persistent prayer, intercession, and making tremendous power available.', 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?auto=format&fit=crop&w=1200&q=80', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4', 2880, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'Teaching', 'published'),
      (3, 2, 'Praise and Worship Medley Highlights', 'An atmosphere of heavenly melodies, vibrant praises, and heartfelt thanksgiving.', 'https://images.unsplash.com/photo-1465847899084-d164df4dedc6?auto=format&fit=crop&w=1200&q=80', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4', 1800, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'Worship', 'published'),
      (4, 3, 'Children\'s Bible Stories — Faith Over Fear', 'David and Goliath and other beloved accounts brought to life for kids.', 'https://images.unsplash.com/photo-1485546246426-74dc88dec4d9?auto=format&fit=crop&w=1200&q=80', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerEscapes.mp4', 1500, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'Children', 'published')");
}

// 10. Ensure praise_watch_history table exists
$check = $conn->query("SHOW TABLES LIKE 'praise_watch_history'");
if ($check && $check->num_rows == 0) {
    $conn->query("CREATE TABLE `praise_watch_history` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `recording_id` int(11) NOT NULL,
      `progress_seconds` int(11) NOT NULL DEFAULT 0,
      `duration_seconds` int(11) NOT NULL DEFAULT 0,
      `completed` tinyint(1) NOT NULL DEFAULT 0,
      `last_watched_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `user_recording` (`user_id`, `recording_id`),
      KEY `user_id` (`user_id`),
      KEY `recording_id` (`recording_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Write migration marker file so subsequent HTTP requests are instantaneous
@touch($marker_file);
if (php_sapi_name() === 'cli') {
    echo "Database migrations and schema checks completed successfully.\n";
}



