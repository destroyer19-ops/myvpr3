ALTER TABLE praise_crusade_stats ADD COLUMN visitor_session_id VARCHAR(100) NULL AFTER user_agent;
ALTER TABLE praise_crusade_stats ADD COLUMN visitor_name VARCHAR(255) NULL AFTER visitor_session_id;
ALTER TABLE praise_crusade_stats ADD COLUMN visitor_email VARCHAR(255) NULL AFTER visitor_name;
ALTER TABLE praise_crusade_stats ADD COLUMN city VARCHAR(100) NULL AFTER country;
