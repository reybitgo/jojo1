-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Aug 22, 2025 at 06:29 AM
-- Server version: 10.11.10-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u302958998_mine_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `packages`
--

CREATE TABLE `packages` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `description` text DEFAULT NULL,
  `features` text DEFAULT NULL,
  `order_index` int(11) DEFAULT 0,
  `image_path` varchar(255) DEFAULT NULL,
  `referral_bonus_enabled` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `mode` enum('monthly','daily') DEFAULT 'monthly',
  `daily_percentage` decimal(5,2) DEFAULT 0.00,
  `target_value` decimal(15,2) DEFAULT 0.00,
  `maturity_period` int(11) DEFAULT 90
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `packages`
--

INSERT INTO `packages` (`id`, `name`, `price`, `status`, `description`, `features`, `order_index`, `image_path`, `referral_bonus_enabled`, `created_at`, `updated_at`, `mode`, `daily_percentage`, `target_value`, `maturity_period`) VALUES
(1, 'Starter Plan', 20.00, 'active', 'Perfect for beginners', '• 20 USDT minimum\n• 50% monthly bonus\n• 3-month cycle\n• Referral bonuses', 0, NULL, 1, '2025-08-05 06:37:09', '2025-08-06 04:05:47', 'monthly', 0.00, 0.00, 90),
(2, 'Bronze Plan', 100.00, 'active', 'Good starting investment', '• 100 USDT package\n• 50% monthly bonus\n• 3-month cycle\n• Referral bonuses', 0, NULL, 1, '2025-08-05 06:37:09', '2025-08-05 06:37:09', 'monthly', 0.00, 0.00, 90),
(3, 'Test Plan', 500.00, 'inactive', 'Balanced investment', '• 500 USDT package\r\n• 50% monthly bonus\r\n• 3-month cycle\r\n• Advanced features', 0, NULL, 1, '2025-08-05 06:37:09', '2025-08-10 07:22:05', 'monthly', 0.00, 0.00, 90),
(4, 'Gold Plan', 1000.00, 'active', 'Premium package', '• 1000 USDT\n• 50% monthly bonus\n• 3-month cycle\n• Priority support', 0, NULL, 1, '2025-08-05 06:37:09', '2025-08-05 06:37:09', 'monthly', 0.00, 0.00, 90),
(6, 'Diamond Plan', 10000.00, 'active', 'Ultimate', '• 10000 USDT\n• 50% monthly bonus\n• 3-month cycle\n• Exclusive benefits', 0, NULL, 1, '2025-08-05 06:37:09', '2025-08-05 06:37:09', 'monthly', 0.00, 0.00, 90),
(7, 'Jojo velasco', 100.00, 'inactive', '', '', 0, NULL, 1, '2025-08-06 03:11:27', '2025-08-06 04:18:06', 'monthly', 0.00, 0.00, 90),
(8, 'Silver Plan', 500.00, 'active', '', '', 0, NULL, 1, '2025-08-06 04:21:48', '2025-08-06 04:21:48', 'monthly', 0.00, 0.00, 90),
(11, 'Test Daily', 10.00, 'inactive', 'Test package for daily', '• 20 USDT minimum withdrawal\r\n• 1% daily bonus\r\n• 90-day cycle\r\n• Referral bonuses', 0, NULL, 1, '2025-08-10 08:18:01', '2025-08-11 14:00:32', 'daily', 1.00, 20.00, 50),
(12, 'Test2 Daily', 2000.00, 'inactive', 'sdsdsd', 'sdsdsd', 0, NULL, 1, '2025-08-10 17:31:29', '2025-08-10 17:31:37', 'daily', 2.00, 0.00, 90),
(13, 'Test3 Daily', 3000.00, 'inactive', '333', '333', 0, NULL, 1, '2025-08-10 17:41:24', '2025-08-10 17:41:54', 'daily', 3.00, 5000.00, 90),
(15, 'White package', 10.00, 'active', 'maturity period upon double your capital.', 'compensation plan based on unilevel down to 3rd. level.', 0, NULL, 1, '2025-08-11 14:55:14', '2025-08-11 14:55:56', 'daily', 2.00, 20.00, 50),
(16, 'Black Package', 100.00, 'active', 'mature upon double your capital', 'compensation plan based on 10% first level and 1% down to 5th. level', 0, NULL, 1, '2025-08-11 16:18:37', '2025-08-11 16:18:37', 'daily', 2.00, 200.00, 50),
(17, 'Blue Package', 1000.00, 'active', 'maturity period upon double your capital', 'compensation plan based on unilevel down to 3rd. level.', 0, NULL, 1, '2025-08-11 16:19:54', '2025-08-11 16:19:54', 'daily', 2.00, 2000.00, 50),
(18, 'Red Package', 10000.00, 'active', 'maturity period upon double your capital', 'compensation plan based on unilevel down to 3rd. level.', 0, NULL, 1, '2025-08-11 16:20:51', '2025-08-11 16:20:51', 'daily', 2.00, 20000.00, 50);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `packages`
--
ALTER TABLE `packages`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `packages`
--
ALTER TABLE `packages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
