-- Add status to praise_crusades if not exists
ALTER TABLE praise_crusades 
ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'draft';

-- Ensure praise_crusade_stats can track sessions, countries, etc.
ALTER TABLE praise_crusade_stats
ADD COLUMN IF NOT EXISTS session_id VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS city VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS is_unique_visit TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS attendee_name VARCHAR(255) NULL,
ADD COLUMN IF NOT EXISTS attendee_email VARCHAR(255) NULL;

-- Ensure testimonies has published status (already has 'pending', 'approved', 'rejected')
