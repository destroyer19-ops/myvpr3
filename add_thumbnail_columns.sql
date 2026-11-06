ALTER TABLE `praise_videos` ADD COLUMN `thumbnail_url` VARCHAR(255) NULL AFTER `video_url`;
ALTER TABLE `praise_live_tv` ADD COLUMN `thumbnail_url` VARCHAR(255) NULL AFTER `stream_url`;
