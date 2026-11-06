-- add_daily_room_column.sql
ALTER TABLE `praise_meetings` ADD COLUMN `daily_room_url` VARCHAR(255) DEFAULT NULL;
