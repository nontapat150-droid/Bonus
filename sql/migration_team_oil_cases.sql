-- ตารางนับเคสปิดงานสำเร็จรายทีม-รายเดือน (เชื่อมระบบแจกจ่ายงาน → ระบบน้ำมัน)
-- หมายเหตุ: คอลัมน์ year_month ต้องใส่ backtick ใน SQL เพราะชนกับฟังก์ชัน YEAR_MONTH() ของ MariaDB
-- รันครั้งเดียวบน phpMyAdmin หรือ MySQL client

CREATE TABLE IF NOT EXISTS `team_oil_cases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `year_month` varchar(7) NOT NULL COMMENT 'รูปแบบ YYYY-MM',
  `case_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_team_month` (`team_id`, `year_month`),
  KEY `idx_year_month` (`year_month`),
  CONSTRAINT `fk_team_oil_cases_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
