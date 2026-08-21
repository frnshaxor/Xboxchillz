-- Add audit diff columns to activity_log for before/after tracking
ALTER TABLE activity_log ADD COLUMN IF NOT EXISTS old_values TEXT DEFAULT NULL AFTER ip;
ALTER TABLE activity_log ADD COLUMN IF NOT EXISTS new_values TEXT DEFAULT NULL AFTER old_values;
