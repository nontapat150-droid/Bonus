-- Migration: Create job_logs table for tracking job completion history
-- Date: 2026-05-30

CREATE TABLE `job_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `tech_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL COMMENT 'completed, failed, pending',
  `remark` text COMMENT 'Notes or reason if job failed',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `job_id` (`job_id`),
  KEY `tech_id` (`tech_id`),
  KEY `status` (`status`),
  CONSTRAINT `job_logs_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `job_logs_ibfk_2` FOREIGN KEY (`tech_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes for performance
CREATE INDEX `idx_tech_date` ON `job_logs` (`tech_id`, `timestamp`);
CREATE INDEX `idx_job_status` ON `job_logs` (`job_id`, `status`);
