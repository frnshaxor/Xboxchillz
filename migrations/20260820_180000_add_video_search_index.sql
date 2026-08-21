-- Index for Video Library search and pagination
ALTER TABLE videos ADD INDEX idx_videos_created_at (created_at);
ALTER TABLE videos ADD INDEX idx_videos_category_id (category_id);
ALTER TABLE videos ADD FULLTEXT INDEX ft_videos_title (title);
