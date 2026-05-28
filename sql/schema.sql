-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql207.infinityfree.com
-- Generation Time: May 28, 2026 at 12:42 AM
-- Server version: 11.4.11-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_42036532_ro`
--

-- --------------------------------------------------------

--
-- Table structure for table `checkins`
--

CREATE TABLE `checkins` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `checkin_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `checkins`
--

INSERT INTO `checkins` (`id`, `user_id`, `image_path`, `checkin_time`) VALUES
(1, 2, 'checkin_2_1779856005_6a167285080e0.png', '2026-05-27 04:26:45');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_consumable`
--

CREATE TABLE `inventory_consumable` (
  `id` varchar(50) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `qty` decimal(10,2) DEFAULT 0.00,
  `unit` varchar(50) DEFAULT 'ชิ้น'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_consumable_logs`
--

CREATE TABLE `inventory_consumable_logs` (
  `id` int(11) NOT NULL,
  `consumable_id` varchar(50) NOT NULL,
  `action` enum('in','out','transfer') NOT NULL,
  `qty` decimal(10,2) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL,
  `model_id` int(11) NOT NULL,
  `sn` varchar(100) NOT NULL,
  `status` enum('in_stock','outbound') NOT NULL DEFAULT 'in_stock',
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_logs`
--

CREATE TABLE `inventory_logs` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `action` enum('in','out','transfer') NOT NULL,
  `admin_id` int(11) NOT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `receiver_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` int(11) NOT NULL,
  `plan_arrival_date` date DEFAULT NULL,
  `access_no` varchar(50) NOT NULL,
  `customer` varchar(150) DEFAULT NULL,
  `phone` varchar(100) DEFAULT NULL,
  `package` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `product` varchar(150) DEFAULT NULL,
  `lat` decimal(10,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL,
  `order_no` varchar(50) DEFAULT NULL,
  `task_order` varchar(50) DEFAULT NULL,
  `task_type` varchar(50) DEFAULT NULL,
  `remark` text DEFAULT NULL,
  `seq` int(11) DEFAULT NULL,
  `map_link` text DEFAULT NULL,
  `team_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `oil_images`
--

CREATE TABLE `oil_images` (
  `id` int(11) NOT NULL,
  `record_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `oil_records`
--

CREATE TABLE `oil_records` (
  `id` int(11) NOT NULL,
  `tech_id` int(11) NOT NULL,
  `license_plate` varchar(20) NOT NULL,
  `liters` decimal(10,2) NOT NULL,
  `mileage` int(11) NOT NULL,
  `price_per_liter` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `distance` decimal(10,2) DEFAULT 0,
  `baht_per_km` decimal(10,2) DEFAULT 0,
  `filler_name` varchar(150) DEFAULT NULL,
  `date_recorded` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `oil_records`
--

INSERT INTO `oil_records` (`id`, `tech_id`, `license_plate`, `liters`, `mileage`, `price_per_liter`, `total_price`, `date_recorded`) VALUES
(1, 2, 'ผผ 3605', '33.00', 70277, '30.30', '1000.00', '2026-03-02 12:00:00'),
(2, 2, 'ผผ 3605', '33.03', 70490, '30.28', '1000.00', '2026-03-04 12:00:00'),
(3, 2, 'ผผ 3605', '33.01', 70718, '30.29', '1000.00', '2026-03-05 12:00:00'),
(4, 2, 'ผผ 3605', '33.02', 71108, '30.28', '1000.00', '2026-03-07 12:00:00'),
(5, 2, 'ผผ 3605', '33.03', 71433, '30.28', '1000.00', '2026-03-10 12:00:00'),
(6, 2, 'ผผ 3605', '33.02', 71553, '30.28', '1000.00', '2026-03-11 12:00:00'),
(7, 2, 'ผผ 3605', '16.52', 72258, '30.28', '500.00', '2026-03-16 12:00:00'),
(8, 2, 'ผผ 3605', '16.52', 72401, '30.28', '500.00', '2026-03-16 12:00:00'),
(9, 2, 'ผผ 3605', '22.28', 72517, '44.89', '1000.00', '2026-03-18 12:00:00'),
(10, 2, 'ผผ 3605', '22.28', 72745, '44.89', '1000.00', '2026-03-19 12:00:00'),
(11, 2, 'ผผ 3605', '16.25', 72936, '30.78', '500.00', '2026-03-20 12:00:00'),
(12, 2, 'ผผ 3605', '31.46', 73134, '31.79', '1000.00', '2026-03-21 12:00:00'),
(13, 2, 'ผผ 3605', '15.87', 73471, '31.49', '500.00', '2026-03-22 12:00:00'),
(14, 2, 'ผผ 3605', '15.87', 73529, '31.50', '500.00', '2026-03-23 12:00:00'),
(15, 2, 'ผผ 3605', '29.61', 73767, '33.78', '1000.00', '2026-03-24 12:00:00'),
(16, 2, 'ผผ 3605', '56.44', 74004, '33.31', '1880.00', '2026-03-25 12:00:00'),
(17, 2, 'ผผ 3605', '25.45', 74388, '39.28', '1000.00', '2026-03-28 12:00:00'),
(18, 2, 'ผผ 3605', '24.23', 74586, '41.28', '1000.00', '2026-03-31 12:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `product_code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_models`
--

CREATE TABLE `product_models` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `model_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('late_time', '08:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `teams`
--

CREATE TABLE `teams` (
  `id` int(11) NOT NULL,
  `team_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teams`
--

INSERT INTO `teams` (`id`, `team_name`) VALUES
(1, 'กก 1234');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','technician','sales') NOT NULL DEFAULT 'technician',
  `full_name` varchar(100) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  `team_id` int(11) DEFAULT NULL,
  `allow_late_time` time NOT NULL DEFAULT '08:30:00',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `full_name`, `status`, `team_id`, `created_at`) VALUES
(1, 'superadmin', '$2y$10$77pZhvEFEBMZB/iXgLHAGO5sAZ506MRnmu7odUicNn0Wy4.pGfjqG', 'super_admin', 'System Administrator', 'approved', NULL, '2026-05-27 04:26:39'),
(2, 'admin', '$2y$10$77pZhvEFEBMZB/iXgLHAGO5sAZ506MRnmu7odUicNn0Wy4.pGfjqG', 'admin', 'General Admin', 'approved', NULL, '2026-05-27 04:26:39'),
(3, 'tech1', '$2y$10$77pZhvEFEBMZB/iXgLHAGO5sAZ506MRnmu7odUicNn0Wy4.pGfjqG', 'technician', 'John Technician', 'approved', NULL, '2026-05-27 04:26:39'),
(4, 'mmm', '$2y$10$Vi3ys.st4O5c7lyHVcijL.nyUwWTi7qB9syaqYjlkml9830bGewk.', 'super_admin', 'Stang', 'approved', NULL, '2026-05-28 04:02:07'),
(5, 'renji', '$2y$10$OaOKb4eNOLhmiDQos5BNLO71C3956yvGQcqHHAjFJGMrZJi8miw5i', 'super_admin', 'Nattanon', 'approved', NULL, '2026-05-28 04:03:04');

-- --------------------------------------------------------

--
-- Table structure for table `user_consumables`
--

CREATE TABLE `user_consumables` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `consumable_id` varchar(50) NOT NULL,
  `qty` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL,
  `license_plate` varchar(20) NOT NULL,
  `last_tech_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `license_plate`, `last_tech_id`) VALUES
(1, 'กก 1234', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `checkins`
--
ALTER TABLE `checkins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `inventory_consumable`
--
ALTER TABLE `inventory_consumable`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_consumable_logs`
--
ALTER TABLE `inventory_consumable_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `consumable_id` (`consumable_id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `target_user_id` (`target_user_id`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `model_id` (`model_id`);

--
-- Indexes for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `fk_target_user` (`target_user_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `team_id` (`team_id`);

--
-- Indexes for table `oil_images`
--
ALTER TABLE `oil_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `record_id` (`record_id`);

--
-- Indexes for table `oil_records`
--
ALTER TABLE `oil_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tech_id` (`tech_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_code` (`product_code`);

--
-- Indexes for table `product_models`
--
ALTER TABLE `product_models`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `teams`
--
ALTER TABLE `teams`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `team_name` (`team_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `team_id` (`team_id`);

--
-- Indexes for table `user_consumables`
--
ALTER TABLE `user_consumables`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_cons_unique` (`user_id`,`consumable_id`),
  ADD KEY `consumable_id` (`consumable_id`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license_plate` (`license_plate`),
  ADD KEY `last_tech_id` (`last_tech_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `checkins`
--
ALTER TABLE `checkins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `inventory_consumable_logs`
--
ALTER TABLE `inventory_consumable_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `oil_images`
--
ALTER TABLE `oil_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `oil_records`
--
ALTER TABLE `oil_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_models`
--
ALTER TABLE `product_models`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teams`
--
ALTER TABLE `teams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user_consumables`
--
ALTER TABLE `user_consumables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `checkins`
--
ALTER TABLE `checkins`
  ADD CONSTRAINT `checkins_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_consumable_logs`
--
ALTER TABLE `inventory_consumable_logs`
  ADD CONSTRAINT `inventory_consumable_logs_ibfk_1` FOREIGN KEY (`consumable_id`) REFERENCES `inventory_consumable` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_consumable_logs_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `inventory_consumable_logs_ibfk_3` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`model_id`) REFERENCES `product_models` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD CONSTRAINT `fk_target_user` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_logs_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `inventory_logs_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `inventory_logs_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `jobs`
--
ALTER TABLE `jobs`
  ADD CONSTRAINT `jobs_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `oil_images`
--
ALTER TABLE `oil_images`
  ADD CONSTRAINT `oil_images_ibfk_1` FOREIGN KEY (`record_id`) REFERENCES `oil_records` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `oil_records`
--
ALTER TABLE `oil_records`
  ADD CONSTRAINT `oil_records_ibfk_1` FOREIGN KEY (`tech_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `product_models`
--
ALTER TABLE `product_models`
  ADD CONSTRAINT `product_models_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_consumables`
--
ALTER TABLE `user_consumables`
  ADD CONSTRAINT `user_consumables_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_consumables_ibfk_2` FOREIGN KEY (`consumable_id`) REFERENCES `inventory_consumable` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`last_tech_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
