-- 1. สร้างฐานข้อมูล
CREATE DATABASE IF NOT EXISTS `smart_business_suite` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `smart_business_suite`;

-- 2. ตารางผู้ใช้งาน (Users)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('super_admin', 'admin', 'technician') NOT NULL DEFAULT 'technician',
  `full_name` VARCHAR(100) NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved',
  `team_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. ตารางทีม (Teams)
CREATE TABLE IF NOT EXISTS `teams` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `team_name` VARCHAR(100) NOT NULL UNIQUE
);

-- (เพิ่ม Foreign Key หลังจากสร้างตาราง team แล้ว)
ALTER TABLE `users` ADD CONSTRAINT `fk_user_team` FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE SET NULL;

-- 4. ตารางยานพาหนะ (Vehicles)
CREATE TABLE IF NOT EXISTS `vehicles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `license_plate` VARCHAR(20) NOT NULL UNIQUE,
  `last_tech_id` INT DEFAULT NULL,
  FOREIGN KEY (`last_tech_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- 5. ตารางน้ำมัน (Oil Module)
CREATE TABLE IF NOT EXISTS `oil_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tech_id` INT NOT NULL,
  `license_plate` VARCHAR(20) NOT NULL,
  `liters` DECIMAL(10,2) NOT NULL,
  `mileage` INT NOT NULL,
  `price_per_liter` DECIMAL(10,2) NOT NULL,
  `total_price` DECIMAL(10,2) NOT NULL,
  `date_recorded` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`tech_id`) REFERENCES `users`(`id`)
);

CREATE TABLE IF NOT EXISTS `oil_images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `record_id` INT NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  FOREIGN KEY (`record_id`) REFERENCES `oil_records`(`id`) ON DELETE CASCADE
);

-- 6. ตารางสินค้าและคลังสินค้า (Inventory Module)
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(150) NOT NULL
);

CREATE TABLE IF NOT EXISTS `product_models` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `model_name` VARCHAR(100) NOT NULL,
  `FOREIGN KEY` (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `inventory_consumable` (
  `id` VARCHAR(50) PRIMARY KEY,
  `product_name` VARCHAR(150) NOT NULL,
  `qty` DECIMAL(10,2) DEFAULT 0,
  `unit` VARCHAR(50) DEFAULT 'ชิ้น'
);

CREATE TABLE IF NOT EXISTS `user_consumables` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `consumable_id` VARCHAR(50) NOT NULL,
  `qty` DECIMAL(10,2) DEFAULT 0,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`consumable_id`) REFERENCES `inventory_consumable`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `user_cons_unique` (`user_id`, `consumable_id`)
);

CREATE TABLE IF NOT EXISTS `inventory_consumable_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `consumable_id` VARCHAR(50) NOT NULL,
  `action` ENUM('in', 'out', 'transfer') NOT NULL,
  `qty` DECIMAL(10,2) NOT NULL,
  `admin_id` INT NOT NULL,
  `target_user_id` INT DEFAULT NULL,
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`consumable_id`) REFERENCES `inventory_consumable`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`target_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS `inventory_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `model_id` INT NOT NULL,
  `sn` VARCHAR(100) NOT NULL,
  `status` ENUM('in_stock', 'outbound') NOT NULL DEFAULT 'in_stock',
  `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`model_id`) REFERENCES `product_models`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `inventory_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `item_id` INT NOT NULL,
  `action` ENUM('in', 'out', 'transfer') NOT NULL,
  `admin_id` INT NOT NULL,
  `target_user_id` INT DEFAULT NULL,
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`item_id`) REFERENCES `inventory_items`(`id`),
  FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`target_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- 7. ตารางงานและการจัดส่ง (Smart Dispatch Module)
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plan_arrival_date` DATE DEFAULT NULL,
  `access_no` VARCHAR(50) NOT NULL,
  `customer` VARCHAR(150) DEFAULT NULL,
  `phone` VARCHAR(100) DEFAULT NULL,
  `package` VARCHAR(150) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `status` VARCHAR(50) DEFAULT NULL,
  `product` VARCHAR(150) DEFAULT NULL,
  `lat` DECIMAL(10,8) DEFAULT NULL,
  `lng` DECIMAL(11,8) DEFAULT NULL,
  `order_no` VARCHAR(50) DEFAULT NULL,
  `task_order` VARCHAR(50) DEFAULT NULL,
  `task_type` VARCHAR(50) DEFAULT NULL,
  `remark` TEXT DEFAULT NULL,
  `seq` INT DEFAULT NULL,
  `map_link` TEXT DEFAULT NULL,
  `team_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE SET NULL
);

-- 8. ตารางเช็คอิน (Check-in Module)
CREATE TABLE IF NOT EXISTS `checkins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `checkin_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- 9. ตารางตั้งค่าระบบ (System Settings - เพิ่มเติมสำหรับฟีเจอร์เวลาสาย)
CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key` VARCHAR(50) PRIMARY KEY,
  `setting_value` VARCHAR(255) NOT NULL
);

INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`) VALUES ('late_time', '08:30:00');

-- 10. ข้อมูลเริ่มต้น (Default Admin)
INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`, `full_name`) VALUES 
('superadmin', '$2y$10$77pZhvEFEBMZB/iXgLHAGO5sAZ506MRnmu7odUicNn0Wy4.pGfjqG', 'super_admin', 'System Administrator'),
('admin', '$2y$10$77pZhvEFEBMZB/iXgLHAGO5sAZ506MRnmu7odUicNn0Wy4.pGfjqG', 'admin', 'General Admin');