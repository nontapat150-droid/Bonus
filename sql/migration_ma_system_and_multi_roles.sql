-- Migration: ระบบงาน MA + บทบาทหลายตำแหน่ง + ข้อมูลลูกค้า MA
-- รันไฟล์นี้บนฐานข้อมูล Bonus ก่อนใช้งานฟีเจอร์ MA ใหม่

-- 1) เพิ่มบทบาท ma_technician (ช่าง MA) ในตาราง users
ALTER TABLE `users`
  MODIFY COLUMN `role` ENUM(
    'super_admin',
    'admin',
    'technician',
    'ma_technician',
    'sales',
    'intern'
  ) NOT NULL DEFAULT 'technician';

-- 2) ตารางบทบาทหลายตำแหน่งต่อผู้ใช้ (1 คนมีได้หลายบทบาท)
CREATE TABLE IF NOT EXISTS `user_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `role` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_role_unique` (`user_id`, `role`),
  KEY `idx_user_roles_user` (`user_id`),
  CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) ขยาย ma_jobs สำหรับนำเข้า Excel แบบใหม่
-- (หากคอลัมน์มีอยู่แล้วให้ข้าม statement ที่ error)
ALTER TABLE `ma_jobs` ADD COLUMN `job_time` varchar(20) DEFAULT NULL COMMENT 'เวลานัดหมาย';
ALTER TABLE `ma_jobs` ADD COLUMN `symptoms` text DEFAULT NULL COMMENT 'อาการ';
ALTER TABLE `ma_jobs` ADD COLUMN `area_provider` enum('AIS','3BB') DEFAULT NULL COMMENT 'พื้นที่ AIS/3BB';
ALTER TABLE `ma_jobs` ADD COLUMN `team_name_import` varchar(100) DEFAULT NULL COMMENT 'ชื่อทีมจาก Excel';
ALTER TABLE `ma_jobs` ADD COLUMN `team_match_status` enum('matched','unmatched') DEFAULT NULL;
ALTER TABLE `ma_jobs` ADD COLUMN `assigned_user_id` int(11) DEFAULT NULL;
ALTER TABLE `ma_jobs` ADD COLUMN `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp();

-- 4) ข้อมูลลูกค้า MA (อ้างอิงด้วย NON)
CREATE TABLE IF NOT EXISTS `ma_customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `non_number` varchar(50) NOT NULL,
  `customer_name` varchar(150) DEFAULT NULL,
  `phone` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `non_number` (`non_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) ประวัติงาน MA ต่อลูกค้า
CREATE TABLE IF NOT EXISTS `ma_customer_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `ma_job_id` int(11) DEFAULT NULL,
  `non_number` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL COMMENT 'imported, completed, failed, rescheduled',
  `symptoms` text DEFAULT NULL,
  `area_provider` varchar(10) DEFAULT NULL,
  `remark` text DEFAULT NULL,
  `tech_id` int(11) DEFAULT NULL,
  `team_id` int(11) DEFAULT NULL,
  `action_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ma_hist_customer` (`customer_id`),
  KEY `idx_ma_hist_non` (`non_number`),
  KEY `idx_ma_hist_job` (`ma_job_id`),
  CONSTRAINT `ma_customer_history_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `ma_customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6) รูปหลักฐานปิดงาน MA
CREATE TABLE IF NOT EXISTS `ma_job_completion_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ma_job_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ma_img_job` (`ma_job_id`),
  CONSTRAINT `ma_job_completion_images_ibfk_1` FOREIGN KEY (`ma_job_id`) REFERENCES `ma_jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7) ประวัติเลื่อนนัดงาน MA
CREATE TABLE IF NOT EXISTS `ma_job_reschedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ma_job_id` int(11) NOT NULL,
  `previous_plan_date` date DEFAULT NULL,
  `new_plan_date` date NOT NULL,
  `remark` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ma_reschedule_job` (`ma_job_id`),
  CONSTRAINT `ma_job_reschedules_ibfk_1` FOREIGN KEY (`ma_job_id`) REFERENCES `ma_jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8) ย้ายบทบาทเดิมจาก users.role ไป user_roles (รันครั้งเดียว)
INSERT IGNORE INTO `user_roles` (`user_id`, `role`)
SELECT `id`, `role` FROM `users` WHERE `role` IS NOT NULL AND `role` != '';
