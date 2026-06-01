-- Migration: เพิ่มฟิลด์ title สำหรับประกาศ
ALTER TABLE announcements
ADD COLUMN title VARCHAR(255) DEFAULT NULL AFTER id;