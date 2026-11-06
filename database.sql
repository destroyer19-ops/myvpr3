--
-- Database: `virtual_praise_room`
--

-- --------------------------------------------------------

--
-- Table structure for table `praise_users`
--

CREATE TABLE `praise_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `account_type` varchar(20) NOT NULL DEFAULT 'individual',
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `satellite_campus` varchar(100) DEFAULT NULL,
  `church` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `praise_meetings`
--

CREATE TABLE `praise_meetings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `meeting_code` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `meeting_code` (`meeting_code`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `praise_meeting_sessions`
--

CREATE TABLE `praise_meeting_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `meeting_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `duration` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `meeting_id` (`meeting_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `praise_meetings`
--
ALTER TABLE `praise_meetings`
  ADD CONSTRAINT `praise_meetings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `praise_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `praise_meeting_sessions`
--
ALTER TABLE `praise_meeting_sessions`
  ADD CONSTRAINT `praise_meeting_sessions_ibfk_1` FOREIGN KEY (`meeting_id`) REFERENCES `praise_meetings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `praise_meeting_sessions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `praise_users` (`id`) ON DELETE CASCADE;

-- --------------------------------------------------------

--
-- Table structure for table `praise_crusades`
--

CREATE TABLE `praise_crusades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `crusade_code` varchar(255) NOT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `salvation_clicks` int(11) DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `crusade_code` (`crusade_code`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Constraints for table `praise_crusades`
--
ALTER TABLE `praise_crusades`
  ADD CONSTRAINT `praise_crusades_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `praise_users` (`id`) ON DELETE CASCADE;

-- --------------------------------------------------------

--
-- Table structure for table `praise_crusade_stats`
--

CREATE TABLE `praise_crusade_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `crusade_id` int(11) NOT NULL,
  `stream_key` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `stayed_for_a_minute` tinyint(1) NOT NULL DEFAULT '0',
  `country` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `crusade_id` (`crusade_id`),
  KEY `stream_key` (`stream_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Constraints for table `praise_crusade_stats`
--
ALTER TABLE `praise_crusade_stats`
  ADD CONSTRAINT `praise_crusade_stats_ibfk_1` FOREIGN KEY (`crusade_id`) REFERENCES `praise_crusades` (`id`) ON DELETE CASCADE;

-- --------------------------------------------------------

--
-- Table structure for table `praise_admins`
--

CREATE TABLE `praise_admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `praise_live_tv`
--

CREATE TABLE `praise_live_tv` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text,
  `stream_url` varchar(255) NOT NULL,
  `is_live` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `praise_user_shared_streams`
--

CREATE TABLE `praise_user_shared_streams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `crusade_id` int(11) NOT NULL,
  `stream_key` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stream_key` (`stream_key`),
  KEY `user_id` (`user_id`),
  KEY `crusade_id` (`crusade_id`),
  CONSTRAINT `fk_user_shared_streams_user` FOREIGN KEY (`user_id`) REFERENCES `praise_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_shared_streams_crusade` FOREIGN KEY (`crusade_id`) REFERENCES `praise_crusades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `praise_videos`
--

CREATE TABLE `praise_videos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `video_url` varchar(255) NOT NULL,
  `age_category` varchar(50) NOT NULL,
  `type_category` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `language` varchar(50) DEFAULT NULL,
  `category_children_outreach` tinyint(1) DEFAULT '0',
  `category_movie_outreach` tinyint(1) DEFAULT '0',
  `category_ministrations` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
