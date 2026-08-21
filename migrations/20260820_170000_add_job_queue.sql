-- Job queue table for managing background tasks
CREATE TABLE IF NOT EXISTS job_queue (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  job_type VARCHAR(50) NOT NULL,
  payload TEXT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  attempts TINYINT DEFAULT 0,
  max_attempts TINYINT DEFAULT 3,
  next_run_at TIMESTAMP NULL,
  last_error TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(status, next_run_at),
  INDEX(job_type)
);
