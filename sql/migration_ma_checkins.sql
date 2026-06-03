-- Migration: ระบบเช็คอิน MA แยกจากเช็คอินทั่วไป
-- เวลาเข้างาน MA ตั้งได้เฉพาะผู้ดูแลระบบ (super_admin) ผ่าน system_settings

CREATE TABLE IF NOT EXISTS `ma_checkins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `checkin_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_late` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=มาสาย (เช็คอินหลังเวลาที่กำหนด)',
  PRIMARY KEY (`id`),
  KEY `idx_ma_checkin_user` (`user_id`),
  KEY `idx_ma_checkin_time` (`checkin_time`),
  CONSTRAINT `ma_checkins_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`) VALUES ('late_time_ma_technician', '08:30:00');
