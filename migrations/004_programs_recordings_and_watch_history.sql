-- Migration 004: Programs, Recorded Content CMS, and User Watch History
-- Adds support for separated live broadcast sources, scheduled programs, recordings, and viewing progress.

CREATE TABLE IF NOT EXISTS `praise_programs` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `praise_program_recordings` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `praise_watch_history` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default initial programs if table is empty
INSERT INTO `praise_programs` (`id`, `title`, `description`, `thumbnail_url`, `category`, `schedule_time`, `is_live_now`)
SELECT 1, 'Healing Streams Live Healing Services', 'Global live healing service with Pastor Chris broadcasting live to millions worldwide.', 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=1200&q=80', 'Live Service', NOW(), 1
WHERE NOT EXISTS (SELECT 1 FROM `praise_programs` WHERE `id` = 1);

INSERT INTO `praise_programs` (`id`, `title`, `description`, `thumbnail_url`, `category`, `schedule_time`, `is_live_now`)
SELECT 2, 'Praise Room 24/7', 'Continuous worship, adoration, and music ministry around the clock.', 'https://images.unsplash.com/photo-1465847899084-d164df4dedc6?auto=format&fit=crop&w=1200&q=80', 'Worship', DATE_ADD(NOW(), INTERVAL 2 HOUR), 0
WHERE NOT EXISTS (SELECT 2 FROM `praise_programs` WHERE `id` = 2);

INSERT INTO `praise_programs` (`id`, `title`, `description`, `thumbnail_url`, `category`, `schedule_time`, `is_live_now`)
SELECT 3, 'Children\'s Praise & Faith Stories', 'Inspiring Bible lessons, joyful songs, and animated stories for children and youth.', 'https://images.unsplash.com/photo-1485546246426-74dc88dec4d9?auto=format&fit=crop&w=1200&q=80', 'Children', DATE_ADD(NOW(), INTERVAL 1 DAY), 0
WHERE NOT EXISTS (SELECT 3 FROM `praise_programs` WHERE `id` = 3);

INSERT INTO `praise_programs` (`id`, `title`, `description`, `thumbnail_url`, `category`, `schedule_time`, `is_live_now`)
SELECT 4, 'Global Prayer & Intercession Service', 'United fellowship in prayer with ministers and brethren across nations.', 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?auto=format&fit=crop&w=1200&q=80', 'Prayer', DATE_ADD(NOW(), INTERVAL 2 DAY), 0
WHERE NOT EXISTS (SELECT 4 FROM `praise_programs` WHERE `id` = 4);

-- Seed initial recordings if table is empty
INSERT INTO `praise_program_recordings` (`id`, `program_id`, `title`, `description`, `thumbnail_url`, `video_url`, `duration_seconds`, `program_date`, `category`, `status`)
SELECT 1, 1, 'A Day of Bliss with Pastor Chris (Samoa)', 'Relive the glorious moments of worship, miraculous demonstrations of the Spirit, and insightful revelations.', 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=1200&q=80', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', 3600, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Ministration', 'published'
WHERE NOT EXISTS (SELECT 1 FROM `praise_program_recordings` WHERE `id` = 1);

INSERT INTO `praise_program_recordings` (`id`, `program_id`, `title`, `description`, `thumbnail_url`, `video_url`, `duration_seconds`, `program_date`, `category`, `status`)
SELECT 2, 4, 'Teaching Series on Prayer — Part 2', 'In-depth biblical exposition on persistent prayer, intercession, and making tremendous power available.', 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?auto=format&fit=crop&w=1200&q=80', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4', 2880, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'Teaching', 'published'
WHERE NOT EXISTS (SELECT 2 FROM `praise_program_recordings` WHERE `id` = 2);

INSERT INTO `praise_program_recordings` (`id`, `program_id`, `title`, `description`, `thumbnail_url`, `video_url`, `duration_seconds`, `program_date`, `category`, `status`)
SELECT 3, 2, 'Praise and Worship Medley Highlights', 'An atmosphere of heavenly melodies, vibrant praises, and heartfelt thanksgiving.', 'https://images.unsplash.com/photo-1465847899084-d164df4dedc6?auto=format&fit=crop&w=1200&q=80', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4', 1800, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'Worship', 'published'
WHERE NOT EXISTS (SELECT 3 FROM `praise_program_recordings` WHERE `id` = 3);

INSERT INTO `praise_program_recordings` (`id`, `program_id`, `title`, `description`, `thumbnail_url`, `video_url`, `duration_seconds`, `program_date`, `category`, `status`)
SELECT 4, 3, 'Children\'s Bible Stories — Faith Over Fear', 'David and Goliath and other beloved accounts brought to life for kids.', 'https://images.unsplash.com/photo-1485546246426-74dc88dec4d9?auto=format&fit=crop&w=1200&q=80', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerEscapes.mp4', 1500, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'Children', 'published'
WHERE NOT EXISTS (SELECT 4 FROM `praise_program_recordings` WHERE `id` = 4);
