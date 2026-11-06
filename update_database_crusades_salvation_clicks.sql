-- Add salvation_clicks to praise_crusades if missing
ALTER TABLE `praise_crusades`
  ADD COLUMN `salvation_clicks` INT(11) NULL DEFAULT 0 AFTER `created_at`;
