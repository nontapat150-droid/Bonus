-- Migration: เพิ่มฟิลด์ image_url สำหรับประกาศ
ALTER TABLE announcements
ADD COLUMN image_url VARCHAR(255) DEFAULT NULL AFTER message;
