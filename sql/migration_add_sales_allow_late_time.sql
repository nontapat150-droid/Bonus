-- Migration: เพิ่มคอลัมน์ allow_late_time และ role 'sales' ในตาราง users
ALTER TABLE `users`
  MODIFY `role` enum('super_admin','admin','technician','sales') NOT NULL DEFAULT 'technician';

ALTER TABLE `users`
  ADD COLUMN `allow_late_time` time NOT NULL DEFAULT '08:30:00' AFTER `team_id`;
