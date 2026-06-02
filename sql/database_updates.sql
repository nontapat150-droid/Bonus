-- ==========================================================
-- SQL Update Script สำหรับระบบจัดการคลังสินค้าและกระเป๋าช่าง
-- ==========================================================

-- 1. อัปเดต ENUM สำหรับตาราง inventory_items ให้รองรับสถานะ 'used' (กดใช้งานแล้ว)
ALTER TABLE `inventory_items` MODIFY COLUMN `status` ENUM('in_stock','outbound','used') NOT NULL DEFAULT 'in_stock';

-- 2. อัปเดต ENUM สำหรับตาราง inventory_logs ให้รองรับ action 'used'
ALTER TABLE `inventory_logs` MODIFY COLUMN `action` ENUM('in','out','transfer','used') NOT NULL;

-- 3. เพิ่มคอลัมน์ user_id สำหรับเก็บ ID ของช่างคนที่กดใช้สินค้า (แบบมี Serial Number)
ALTER TABLE `inventory_logs` ADD COLUMN `user_id` INT DEFAULT NULL AFTER `target_user_id`;

-- 4. อัปเดต ENUM สำหรับตาราง inventory_consumable_logs ให้รองรับ action 'used' (ถ้ามี)
ALTER TABLE `inventory_consumable_logs` MODIFY COLUMN `action` ENUM('in','out','transfer','used') NOT NULL;

-- 5. เพิ่มคอลัมน์ user_id สำหรับเก็บ ID ของช่างคนที่กดใช้วัสดุสิ้นเปลือง
ALTER TABLE `inventory_consumable_logs` ADD COLUMN `user_id` INT DEFAULT NULL AFTER `target_user_id`;

-- ==========================================================
-- (ส่วนเสริม) สำหรับตาราง Announcements หากฐานข้อมูลเก่าไม่มีคอลัมน์เหล่านี้
-- ==========================================================
-- สร้างตาราง announcements ถ้ายังไม่มี
CREATE TABLE IF NOT EXISTS `announcements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `type` VARCHAR(50) DEFAULT 'popup',
    `title` VARCHAR(255) DEFAULT NULL,
    `message` TEXT NOT NULL,
    `image_url` VARCHAR(255) DEFAULT NULL,
    `expires_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- เพิ่มคอลัมน์ type และ title (กรณีที่ตารางนี้มีอยู่แล้วแต่เป็นเวอร์ชันเก่า)
-- หมายเหตุ: หากเพิ่งสร้างตารางใหม่ด้านบน โค้ด 2 บรรทัดนี้อาจจะเกิด Error Duplicate Column ซึ่งสามารถข้ามได้
ALTER TABLE `announcements` ADD COLUMN IF NOT EXISTS `type` VARCHAR(50) DEFAULT 'popup' AFTER `id`;
ALTER TABLE `announcements` ADD COLUMN IF NOT EXISTS `title` VARCHAR(255) DEFAULT NULL AFTER `type`;

-- แก้ไขประเภท type กลับเป็น popup หากของเดิมเป็นค่าว่าง
UPDATE `announcements` SET `type` = 'popup' WHERE `type` IS NULL OR `type` = '';
