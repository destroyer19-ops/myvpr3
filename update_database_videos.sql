-- Align praise_videos with production structure
ALTER TABLE `praise_videos`
  ADD COLUMN `video_url` VARCHAR(255) NOT NULL AFTER `title`;

ALTER TABLE `praise_videos`
  ADD COLUMN `language` VARCHAR(50) NULL DEFAULT NULL AFTER `created_at`;

ALTER TABLE `praise_videos`
  ADD COLUMN `category_children_outreach` TINYINT(1) NULL DEFAULT 0 AFTER `language`;

ALTER TABLE `praise_videos`
  ADD COLUMN `category_movie_outreach` TINYINT(1) NULL DEFAULT 0 AFTER `category_children_outreach`;

ALTER TABLE `praise_videos`
  ADD COLUMN `category_ministrations` TINYINT(1) NULL DEFAULT 0 AFTER `category_movie_outreach`;
