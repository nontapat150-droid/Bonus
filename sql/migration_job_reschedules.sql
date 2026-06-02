-- ประวัติการเลื่อนนัดติดตั้ง (ช่างแจ้งจากระบบแจกจ่ายงาน)
-- รันครั้งเดียวบน phpMyAdmin หรือ MySQL client

CREATE TABLE IF NOT EXISTS `job_reschedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `job_log_id` int(11) DEFAULT NULL,
  `tech_id` int(11) NOT NULL,
  `team_id` int(11) DEFAULT NULL,
  `previous_plan_date` date DEFAULT NULL,
  `new_plan_date` date NOT NULL,
  `remark` text DEFAULT NULL,
  `notification_id` int(11) DEFAULT NULL,
  `acknowledged_by` int(11) DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_job_reschedules_job` (`job_id`),
  KEY `idx_job_reschedules_team` (`team_id`),
  KEY `idx_job_reschedules_new_date` (`new_plan_date`),
  KEY `idx_job_reschedules_ack` (`acknowledged_at`),
  CONSTRAINT `fk_job_reschedules_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_job_reschedules_tech` FOREIGN KEY (`tech_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_job_reschedules_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_job_reschedules_ack_user` FOREIGN KEY (`acknowledged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
