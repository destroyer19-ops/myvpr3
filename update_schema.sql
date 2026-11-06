ALTER TABLE `praise_crusades`
ADD COLUMN `status` ENUM('draft', 'scheduled', 'live', 'ended', 'cancelled') NOT NULL DEFAULT 'draft' AFTER `is_active`;
