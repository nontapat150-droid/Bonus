-- 1. สร้างฐานข้อมูลและเลือกใช้งาน
CREATE DATABASE IF NOT EXISTS `smart_business_suite` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `smart_business_suite`;

-- 2. โมดูลจัดการทีม (ต้องสร้างก่อนผู้ใช้งานและงาน)
CREATE TABLE IF NOT EXISTS `teams` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `team_name` VARCHAR(100) NOT NULL UNIQUE
);

-- 3. โมดูลผู้ใช้งานและสิทธิ์ (RBAC)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('super_admin', 'admin', 'technician') NOT NULL DEFAULT 'technician',
  `full_name` VARCHAR(100) NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved',
  `team_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE SET NULL
);

-- เพิ่มบัญชี Super Admin เริ่มต้น (รหัสผ่าน: password123)
INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`, `full_name`) VALUES 
('superadmin', '$2y$10$77pZhvEFEBMZB/iXgLHAGO5sAZ506MRnmu7odUicNn0Wy4.pGfjqG', 'super_admin', 'System Administrator'),
('admin', '$2y$10$77pZhvEFEBMZB/iXgLHAGO5sAZ506MRnmu7odUicNn0Wy4.pGfjqG', 'admin', 'General Admin'),
('tech1', '$2y$10$77pZhvEFEBMZB/iXgLHAGO5sAZ506MRnmu7odUicNn0Wy4.pGfjqG', 'technician', 'John Technician');

-- 4. โมดูลน้ำมันและยานพาหนะ
CREATE TABLE IF NOT EXISTS `vehicles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `license_plate` VARCHAR(20) NOT NULL UNIQUE,
  `last_tech_id` INT DEFAULT NULL,
  FOREIGN KEY (`last_tech_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

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

-- 5. โมดูลคลังสินค้า
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(150) NOT NULL
);

CREATE TABLE IF NOT EXISTS `product_models` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `model_name` VARCHAR(100) NOT NULL,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
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
  `action` ENUM('in', 'out') NOT NULL,
  `admin_id` INT NOT NULL,
  `receiver_id` INT DEFAULT NULL,
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`item_id`) REFERENCES `inventory_items`(`id`),
  FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`)
);

-- 6. โมดูลแจกจ่ายงาน
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

-- 7. โมดูลระบบลงเวลาเข้างาน (Check-in)
CREATE TABLE IF NOT EXISTS `checkins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `checkin_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);
