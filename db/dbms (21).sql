-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 30, 2025 at 02:37 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dbms`
--

-- --------------------------------------------------------

--
-- Table structure for table `adminusers`
--

CREATE TABLE `adminusers` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `admin_email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `status_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `first_name` varchar(50) DEFAULT '',
  `last_name` varchar(50) DEFAULT '',
  `last_logged_in` datetime DEFAULT NULL,
  `last_logged_out` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `adminusers`
--

INSERT INTO `adminusers` (`admin_id`, `username`, `admin_email`, `password_hash`, `role_id`, `status_id`, `created_at`, `first_name`, `last_name`, `last_logged_in`, `last_logged_out`) VALUES
(1, 'Eya', 'nicholedeguzman@yahoo.com', '$2y$10$ENseQNg1WhLbfCjBEi3P4ezFAjuxciD8TWR/KoKqSUAKRJAR8HiKu', 1, 1, '2025-03-30 04:35:12', 'Nichole', 'De Guzman', '2025-11-30 21:00:59', '2025-11-27 22:21:50'),
(3, 'admin2', 'lilysmith1@email.com', '$2y$10$nO07giUvM0zjpiUREi6chOSWSRxuqRqKgT2ds6sPY0EyE93x1c6Mm', 2, 1, '2025-08-21 20:35:19', 'Lily', 'Smith', '2025-11-27 16:41:19', '2025-11-27 18:23:30'),
(5, 'admin1', 'doejohn@gmail.com', '$2y$10$Y73IRDzJXTmdDB5cz50JzOaLtoKmGVifpEKtcGF3ehFywtzqAq/IG', 2, 1, '2025-10-11 19:43:57', 'John', 'Doe', '2025-11-30 21:05:55', '2025-11-30 21:00:54'),
(6, 'Aisha', 'aishacayago@email.com', '$2y$10$/onU1AGHLfZfPRSS7KK2iuTNsUG5oEfh.ewlIWEpRDQXNQqy99/6q', 1, 1, '2025-10-14 04:22:08', 'Aisha', 'Cayago', '2025-10-14 18:26:04', '2025-10-14 18:50:30');

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `cart_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cart_status` enum('active','inactive') DEFAULT 'active',
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `color` varchar(50) DEFAULT NULL,
  `size` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `carts`
--

INSERT INTO `carts` (`cart_id`, `customer_id`, `created_at`, `cart_status`, `product_id`, `quantity`, `color`, `size`) VALUES
(2, 1, '2025-05-05 16:56:11', '', 3, 10, NULL, NULL),
(3, 1, '2025-05-05 17:06:38', '', 2, 13, NULL, NULL),
(6, 1, '2025-05-06 01:57:39', 'active', 2, 4, NULL, NULL),
(10, 3, '2025-10-06 13:40:42', '', 3, 1, NULL, NULL),
(13, 3, '2025-10-08 02:51:20', '', 3, 1, 'Yellow', 'S'),
(15, 3, '2025-10-08 03:21:51', '', 7, 1, 'Pink', 'M'),
(16, 3, '2025-10-09 07:16:40', '', 3, 1, 'Yellow', 'S');

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `cart_item_id` int(11) NOT NULL,
  `cart_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_code` varchar(10) DEFAULT NULL,
  `category_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_code`, `category_name`) VALUES
(1, '001', 'Blouse'),
(2, '002', 'Dress'),
(3, '003', 'Shorts'),
(4, '004', 'Skirt'),
(5, '005', 'Trouser'),
(6, '006', 'Bathing Suit'),
(7, '007', 'Coordinates'),
(8, '008', 'Shoes'),
(9, '009', 'Preloved'),
(11, '0011', 'Bags'),
(14, '012', 'Tote Bag'),
(15, '013', 'Gowns'),
(16, '014', 'Umbrella'),
(17, '015', 'Baby Shirt'),
(18, '016', 'Purse');

-- --------------------------------------------------------

--
-- Table structure for table `colors`
--

CREATE TABLE `colors` (
  `color_id` int(11) NOT NULL,
  `color` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `colors`
--

INSERT INTO `colors` (`color_id`, `color`) VALUES
(1, 'Pink'),
(2, 'Red'),
(3, 'White'),
(4, 'Blue'),
(5, 'Black'),
(6, 'Green'),
(7, 'Yellow'),
(8, 'Violet'),
(9, 'Brown'),
(10, 'Orange'),
(11, 'Any');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `status_id` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `first_name`, `last_name`, `email`, `phone`, `password_hash`, `address`, `status_id`, `created_at`, `profile_picture`) VALUES
(1, 'Jane', 'Smith', 'janesmith@email.com', '0987654321', '$2y$10$wwJN5JbK5dyz5VJH7jcgkuaII8ytWuWRJ2YpVgiIoR6Px6qd9IIBK', 'Manila', 1, '2025-04-28 13:15:37', NULL),
(2, 'Lily', 'White', 'whitelily@gmail.com', '0987654321', '$2y$10$UFGysWcd5tyteTyN7qM6zO.e0Vip6hBU0N8gS.a0iOkm3daO0YUTS', 'San Francisco, California', 1, '2025-10-03 13:03:34', NULL),
(3, 'Eya Nichole', 'Barcena', 'email@gmail.com', '099887766554', '$2y$10$nfaZ1PAqAZnlRxfnmipnKON/BSs9AZ3heP1O9flBZhweDU/dzSVLe', 'Bayambang, Pangasinan', 1, '2025-10-05 14:47:33', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `admin_id` int(10) UNSIGNED DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `cash_given` decimal(10,2) DEFAULT NULL,
  `changes` decimal(10,2) DEFAULT 0.00,
  `order_status_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `quantity` int(11) NOT NULL DEFAULT 1,
  `payment_method_id` int(10) UNSIGNED DEFAULT NULL,
  `refund_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `admin_id`, `customer_id`, `total_amount`, `discount_amount`, `cash_given`, `changes`, `order_status_id`, `created_at`, `quantity`, `payment_method_id`, `refund_id`) VALUES
(5, 1, NULL, 350.00, 0.00, 500.00, 150.00, 0, '2025-08-19 10:42:32', 1, 3, NULL),
(6, 1, NULL, 350.00, 0.00, 500.00, 150.00, 0, '2025-08-19 10:43:48', 1, 3, NULL),
(7, 1, NULL, 350.00, 0.00, 500.00, 150.00, 0, '2025-08-19 10:45:37', 1, 3, NULL),
(11, 1, NULL, 1000.00, 0.00, 1000.00, 0.00, 0, '2025-08-20 11:45:33', 1, 3, NULL),
(15, NULL, NULL, 850.00, 0.00, 1000.00, 150.00, 0, '2025-09-08 13:18:13', 1, 3, NULL),
(17, 1, NULL, 350.00, 0.00, 500.00, 150.00, 4, '2025-09-29 13:40:25', 1, 3, NULL),
(18, 1, NULL, 500.00, 0.00, 1000.00, 500.00, 4, '2025-09-29 13:45:13', 1, 3, NULL),
(20, 1, NULL, 1050.00, 0.00, 1100.00, 50.00, 0, '2025-10-03 03:44:44', 1, NULL, NULL),
(24, 1, NULL, 700.00, 0.00, 1000.00, 300.00, 4, '2025-10-03 05:10:55', 1, 3, NULL),
(25, 1, NULL, 1100.00, 0.00, 1500.00, 400.00, 4, '2025-10-03 05:33:23', 1, 3, NULL),
(26, 1, NULL, 1400.00, 0.00, 1500.00, 100.00, 4, '2025-10-03 05:39:09', 1, 3, NULL),
(31, NULL, 3, 350.00, 0.00, NULL, 0.00, 1, '2025-10-06 13:44:03', 1, 3, NULL),
(32, NULL, 3, 350.00, 0.00, NULL, 0.00, 1, '2025-10-08 02:55:07', 1, 3, NULL),
(41, NULL, 3, 869.00, 0.00, NULL, 0.00, 1, '2025-10-08 04:15:08', 1, 1, NULL),
(46, NULL, 3, 419.00, 0.00, NULL, 0.00, 1, '2025-10-09 07:29:06', 1, 3, NULL),
(48, 1, NULL, 350.00, 0.00, 500.00, 150.00, 0, '2025-10-09 07:41:49', 1, 3, NULL),
(49, 3, NULL, 750.00, 0.00, 1000.00, 250.00, 0, '2025-10-09 08:35:03', 1, 3, NULL),
(50, 1, NULL, 1050.00, 0.00, 1100.00, 50.00, 0, '2025-10-10 12:53:07', 1, 3, NULL),
(51, 1, NULL, 1250.00, 0.00, 1500.00, 250.00, 0, '2025-10-11 12:26:07', 1, 3, NULL),
(52, 1, NULL, 700.00, 0.00, 1000.00, 300.00, 0, '2025-10-11 12:28:07', 1, 3, NULL),
(53, 1, NULL, 1050.00, 0.00, 1100.00, 50.00, 0, '2025-10-12 12:14:59', 1, 3, NULL),
(54, 6, NULL, 1250.00, 0.00, 1500.00, 250.00, 0, '2025-10-14 10:23:11', 1, 3, NULL),
(55, 6, NULL, 700.00, 0.00, 1000.00, 300.00, 0, '2025-10-14 10:23:25', 1, 3, NULL),
(60, 6, NULL, 750.00, 0.00, 1000.00, 250.00, 0, '2025-10-14 10:40:20', 1, 3, NULL),
(62, 1, NULL, 750.00, 0.00, 1000.00, 250.00, 0, '2025-11-20 09:47:39', 1, 3, NULL),
(63, 1, NULL, 640.00, 0.00, 1000.00, 360.00, 0, '2025-11-21 01:27:57', 1, 3, NULL),
(64, 1, NULL, 670.00, 0.00, 1000.00, 330.00, 0, '2025-11-21 02:21:40', 1, 3, NULL),
(67, 1, NULL, 800.00, 0.00, 1000.00, 200.00, 0, '2025-11-21 09:25:20', 1, 3, NULL),
(68, 1, NULL, 640.00, 0.00, 1000.00, 360.00, 2, '2025-11-22 09:13:25', 1, 3, NULL),
(69, 1, NULL, -320.00, 0.00, 0.00, 0.00, 2, '2025-11-22 10:20:46', 1, 3, NULL),
(70, 1, NULL, 750.00, 0.00, 1100.00, 350.00, 4, '2025-11-22 11:16:41', 1, 3, NULL),
(71, 1, NULL, 640.00, 0.00, 1000.00, 360.00, 4, '2025-11-23 09:35:49', 1, 3, NULL),
(72, 1, NULL, 500.00, 0.00, 2000.00, 500.00, 4, '2025-11-23 09:54:21', 1, 3, NULL),
(73, 1, NULL, 1050.00, 0.00, 1750.00, 0.00, 4, '2025-11-24 08:15:53', 1, 1, NULL),
(74, 1, NULL, 665.00, 35.00, 665.00, 0.00, 0, '2025-11-24 08:50:19', 1, 1, NULL),
(75, 1, NULL, 300.00, 50.00, 1000.00, 350.00, 4, '2025-11-24 08:52:39', 1, 3, NULL),
(76, 1, NULL, 350.00, 0.00, 500.00, 150.00, 0, '2025-11-24 09:01:44', 1, 3, NULL),
(77, 1, NULL, 320.00, 0.00, 320.00, 0.00, 0, '2025-11-25 03:51:20', 1, 1, NULL),
(78, 1, NULL, 630.00, 70.00, 1000.00, 370.00, 0, '2025-11-25 13:58:13', 1, 3, NULL),
(79, 1, NULL, -85.00, 85.00, 1615.00, 0.00, 4, '2025-11-27 11:09:06', 1, 1, NULL),
(82, 1, NULL, 612.00, 0.00, 1000.00, 388.00, 0, '2025-11-30 13:04:20', 1, 3, NULL),
(83, 1, NULL, 324.00, 0.00, 1000.00, 343.50, 4, '2025-11-30 13:04:31', 1, 3, NULL),
(84, 1, NULL, 636.50, 0.00, 1000.00, 363.50, 4, '2025-11-30 13:23:29', 1, 3, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `size` varchar(50) DEFAULT NULL,
  `stock_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `price` double NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `color`, `size`, `stock_id`, `qty`, `price`, `discount_amount`) VALUES
(2, 5, NULL, NULL, NULL, 1, 1, 350, 0.00),
(3, 6, NULL, NULL, NULL, 1, 1, 350, 0.00),
(4, 7, NULL, NULL, NULL, 1, 1, 350, 0.00),
(8, 11, NULL, NULL, NULL, 8, 2, 500, 0.00),
(15, 15, NULL, NULL, NULL, 8, 1, 500, 0.00),
(16, 15, NULL, NULL, NULL, 14, 1, 350, 0.00),
(18, 17, NULL, NULL, NULL, 14, 1, 350, 0.00),
(19, 18, NULL, NULL, NULL, 8, 1, 500, 0.00),
(22, 20, NULL, NULL, NULL, 1, 3, 350, 0.00),
(25, 24, NULL, NULL, NULL, 1, 2, 350, 0.00),
(26, 25, NULL, NULL, NULL, 12, 1, 350, 0.00),
(29, 26, NULL, NULL, NULL, 8, 1, 500, 0.00),
(30, 26, NULL, NULL, NULL, 14, 1, 350, 0.00),
(32, 41, NULL, NULL, NULL, 16, 1, 400, 0.00),
(33, 41, NULL, NULL, NULL, 17, 1, 400, 0.00),
(38, 46, 3, 'Yellow', 'S', 1, 1, 350, 0.00),
(40, 48, 3, NULL, NULL, 1, 1, 350, 0.00),
(41, 49, 3, NULL, NULL, 1, 1, 350, 0.00),
(42, 49, 7, NULL, NULL, 16, 1, 400, 0.00),
(43, 50, 3, NULL, NULL, 1, 3, 350, 0.00),
(44, 51, 6, NULL, NULL, 18, 1, 400, 0.00),
(45, 51, 3, NULL, NULL, 1, 1, 350, 0.00),
(46, 51, 2, NULL, NULL, 8, 1, 500, 0.00),
(47, 52, 3, NULL, NULL, 1, 2, 350, 0.00),
(48, 53, 9, NULL, NULL, 22, 3, 350, 0.00),
(49, 54, 6, NULL, NULL, 18, 1, 400, 0.00),
(50, 54, 2, NULL, NULL, 8, 1, 500, 0.00),
(51, 54, 3, NULL, NULL, 1, 1, 350, 0.00),
(52, 55, 9, NULL, NULL, 22, 1, 350, 0.00),
(53, 55, 3, NULL, NULL, 1, 1, 350, 0.00),
(54, 60, 3, 'Green', 'S', 12, 1, 350, 0.00),
(55, 60, 6, 'Brown', 'L', 20, 1, 400, 0.00),
(56, 62, 6, 'Black', 'M', 18, 1, 400, 0.00),
(57, 62, 9, 'Orange', 'M', 27, 1, 350, 0.00),
(58, 63, 10, 'Blue', 'L', 28, 2, 320, 0.00),
(59, 64, 10, 'Blue', 'L', 28, 1, 320, 0.00),
(60, 64, 7, 'Pink', 'M', 16, 1, 350, 0.00),
(61, 67, 6, 'Black', 'M', 18, 1, 400, 0.00),
(62, 67, 6, 'Brown', 'L', 20, 1, 400, 0.00),
(63, 68, 10, 'Red', 'L', 29, 1, 320, 0.00),
(64, 68, 10, 'Blue', 'L', 28, 1, 320, 0.00),
(65, 69, 0, NULL, NULL, 28, -1, 320, 0.00),
(66, 70, 6, 'Brown', 'L', 20, 1, 400, 0.00),
(67, 70, 9, 'Black', 'M', 24, 1, 350, 0.00),
(68, 71, 10, 'Blue', 'L', 28, 1, 320, 0.00),
(69, 71, 10, 'Green', 'L', 30, 1, 320, 0.00),
(70, 72, 2, 'Red', 'M', 8, 1, 500, 0.00),
(71, 72, 2, 'Green', 'M', 25, 1, 500, 0.00),
(72, 72, 2, 'Orange', 'M', 26, 1, 500, 0.00),
(73, 73, 3, 'Yellow', 'S', 1, 5, 350, 0.00),
(74, 74, 3, 'Yellow', 'S', 1, 2, 350, 0.00),
(75, 75, 3, 'Violet', 'S', 14, 2, 350, 0.00),
(76, 76, 3, 'Violet', 'S', 14, 1, 350, 0.00),
(77, 77, 10, 'Blue', 'L', 28, 1, 320, 0.00),
(78, 78, 3, 'Yellow', 'S', 1, 2, 350, 0.00),
(79, 79, 14, 'Pink', 'M', 34, 1, 1700, 0.00),
(80, 82, 10, 'Green', 'L', 30, 1, 342, 18.00),
(81, 82, 11, 'White', 'M', 32, 1, 270, 0.00),
(82, 83, 9, 'Black', 'M', 24, 1, 332.5, 17.50),
(83, 83, 10, 'Green', 'L', 30, 1, 324, 36.00),
(84, 84, 11, 'Brown', 'M', 31, 1, 256.5, 13.50),
(85, 84, 6, 'Brown', 'M', 19, 1, 380, 20.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_status`
--

CREATE TABLE `order_status` (
  `order_status_id` int(11) NOT NULL,
  `order_status_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_status`
--

INSERT INTO `order_status` (`order_status_id`, `order_status_name`) VALUES
(3, 'Cancelled'),
(0, 'Completed'),
(1, 'Pending'),
(4, 'Refunded'),
(2, 'Return');

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `payment_method_id` int(10) UNSIGNED NOT NULL,
  `payment_method_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`payment_method_id`, `payment_method_name`) VALUES
(3, 'Cash'),
(1, 'Gcash');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price_id` int(11) DEFAULT NULL,
  `stocks` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `image_url` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `supplier_id` int(11) DEFAULT NULL,
  `supplier_price` decimal(10,2) NOT NULL,
  `revenue` decimal(10,2) GENERATED ALWAYS AS (`price_id` - `supplier_price`) STORED,
  `sizes` varchar(255) DEFAULT NULL,
  `colors` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `product_name`, `description`, `price_id`, `stocks`, `category_id`, `image_url`, `created_at`, `supplier_id`, `supplier_price`, `sizes`, `colors`) VALUES
(2, '002', 'Sizes:  | Colors: ', 500, 15, 1, 'uploads/products/68e5a7012c3d3_blouse3.jpg,uploads/products/68e5a7012c5eb_blouse2.jpg,uploads/products/68e5a7012c7b1_blouse1.jpg', '2025-05-01 13:25:54', 2, 250.00, NULL, NULL),
(3, '6776', '', 350, NULL, 7, '[\"uploads/products/68e5aa731bec3_6776.jpg\"]', '2025-05-01 13:50:35', 2, 3000.00, NULL, NULL),
(6, '9619', '', 400, 40, 6, '[\"uploads/products/prod_68e5b157d80e1.jpg\",\"uploads/products/prod_68e5b157d8372.jpg\"]', '2025-10-08 00:33:14', 2, 300.00, NULL, NULL),
(7, '6868', '', 350, 30, 1, '[\"uploads/products/68e5c96927865_newblouse.jpg\"]', '2025-10-08 02:14:56', 2, 300.00, NULL, NULL),
(9, 'ZF1569', '', 350, 40, 1, '[\"uploads/products/68eb80cff3163_blouse4.jpg\",\"uploads/products/68eb80cff348d_blouse3 (2).jpg\",\"uploads/products/68eb80cff377f_blouse1 (2).jpg\"]', '2025-10-12 10:15:21', 1, 250.00, NULL, NULL),
(10, 'New Blouse', '', 360, 30, 2, '[\"uploads/products/prod_691fb376a6b7c.jpg\",\"uploads/products/prod_691fb376a6f9e.jpg\",\"uploads/products/prod_691fb376a71d6.jpg\"]', '2025-11-21 00:33:58', 1, 220.00, NULL, NULL),
(11, 'New Dress', NULL, 270, 10, 2, '[\"uploads/products/prod_69280f3f13341.jpg\",\"uploads/products/prod_69280f3f13577.jpg\"]', '2025-11-27 08:43:43', 5, 170.00, NULL, NULL),
(14, 'New Gown', NULL, 1700, 20, 15, '[\"uploads/products/prod_69281aa2b2b18.jpg\",\"uploads/products/prod_69281aa2b3050.jpg\"]', '2025-11-27 09:32:18', 5, 700.00, NULL, NULL),
(15, 'New Purse', NULL, 3500, 2, 18, '[\"uploads/products/prod_69281e300d0bd.jpg\",\"uploads/products/prod_69281e300d455.jpg\"]', '2025-11-27 09:47:28', 5, 2500.00, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_colors`
--

CREATE TABLE `product_colors` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `color_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_colors`
--

INSERT INTO `product_colors` (`id`, `product_id`, `color_id`) VALUES
(1, 2, 1),
(2, 3, 1),
(3, 2, 2),
(4, 2, 3),
(5, 3, 3),
(7, 3, 5),
(9, 3, 6),
(10, 3, 7);

-- --------------------------------------------------------

--
-- Table structure for table `product_sizes`
--

CREATE TABLE `product_sizes` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `size_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_sizes`
--

INSERT INTO `product_sizes` (`id`, `product_id`, `size_id`) VALUES
(1, 2, 1),
(2, 2, 2),
(3, 2, 3),
(4, 3, 1),
(5, 3, 2),
(6, 3, 3),
(7, 3, 4);

-- --------------------------------------------------------

--
-- Table structure for table `refunds`
--

CREATE TABLE `refunds` (
  `refund_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `order_item_id` int(11) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `stock_id` int(11) DEFAULT NULL,
  `size_id` int(11) DEFAULT NULL,
  `color_id` int(11) DEFAULT NULL,
  `refund_amount` decimal(10,2) NOT NULL,
  `refunded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `refunded_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `refunds`
--

INSERT INTO `refunds` (`refund_id`, `order_id`, `order_item_id`, `product_id`, `stock_id`, `size_id`, `color_id`, `refund_amount`, `refunded_at`, `refunded_by`) VALUES
(1, 18, NULL, 2, 8, 2, 2, 500.00, '2025-09-29 14:24:34', 1),
(2, 17, NULL, 3, 14, 1, 8, 350.00, '2025-09-29 14:49:21', 1),
(10, 24, 25, 3, 1, 1, 7, 700.00, '2025-10-03 05:32:56', 1),
(11, 25, 26, 3, 12, 1, 6, 350.00, '2025-10-03 05:33:41', 1),
(14, 26, 30, 3, 14, 1, 8, 350.00, '2025-10-03 05:47:55', 1),
(15, 70, NULL, 9, 24, NULL, NULL, 350.00, '2025-11-22 11:44:42', 1),
(16, 71, NULL, 10, 30, NULL, NULL, 320.00, '2025-11-23 09:36:18', 1),
(17, 72, 71, 2, 25, 2, 6, 500.00, '2025-11-23 09:54:44', 1),
(18, 72, 72, 2, 26, 2, 10, 500.00, '2025-11-23 09:54:44', 1),
(19, 73, 73, 3, 1, 1, 7, 700.00, '2025-11-24 08:19:23', 1),
(20, 75, 75, 3, 14, 1, 8, 350.00, '2025-11-24 08:53:18', 1),
(21, 79, 79, 14, 34, 2, 1, 1700.00, '2025-11-27 13:31:08', 1),
(22, 83, 82, 9, 24, 2, 5, 332.50, '2025-11-30 13:05:11', 1),
(23, 84, 85, 6, 19, 2, 9, 0.00, '2025-11-30 13:25:24', 1);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`) VALUES
(2, 'Admin'),
(1, 'Cashier');

-- --------------------------------------------------------

--
-- Table structure for table `sizes`
--

CREATE TABLE `sizes` (
  `size_id` int(11) NOT NULL,
  `size` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sizes`
--

INSERT INTO `sizes` (`size_id`, `size`) VALUES
(1, 'S'),
(2, 'M'),
(3, 'L'),
(4, 'XS'),
(5, 'Free Size');

-- --------------------------------------------------------

--
-- Table structure for table `status`
--

CREATE TABLE `status` (
  `status_id` int(11) NOT NULL,
  `status_name` enum('Active','Inactive','Suspended') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `status`
--

INSERT INTO `status` (`status_id`, `status_name`) VALUES
(1, 'Active'),
(2, 'Inactive'),
(3, 'Suspended');

-- --------------------------------------------------------

--
-- Table structure for table `stock`
--

CREATE TABLE `stock` (
  `stock_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `current_qty` int(11) DEFAULT 0,
  `color_id` int(11) DEFAULT NULL,
  `size_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock`
--

INSERT INTO `stock` (`stock_id`, `product_id`, `current_qty`, `color_id`, `size_id`) VALUES
(1, 3, 13, 7, 1),
(8, 2, 17, 2, 2),
(12, 3, 5, 6, 1),
(14, 3, 0, 8, 1),
(16, 7, 14, 1, 2),
(17, 7, 15, 5, 2),
(18, 6, 5, 5, 2),
(19, 6, 5, 9, 2),
(20, 6, 9, 9, 3),
(21, 6, 20, 5, 3),
(22, 9, 10, 3, 2),
(23, 9, 10, 9, 2),
(24, 9, 9, 5, 2),
(25, 2, 10, 6, 2),
(26, 2, 5, 10, 2),
(27, 9, 10, 10, 2),
(28, 10, 5, 4, 3),
(29, 10, 9, 2, 3),
(30, 10, 8, 6, 3),
(31, 11, 4, 9, 2),
(32, 11, 4, 3, 2),
(34, 14, 5, 1, 2),
(35, 14, 15, 7, 3),
(36, 15, 2, 1, 5);

-- --------------------------------------------------------

--
-- Table structure for table `stock_adjustments`
--

CREATE TABLE `stock_adjustments` (
  `adjustment_id` int(11) NOT NULL,
  `stock_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `type` enum('damaged','lost','return_restock','return_discard') NOT NULL,
  `reason` text DEFAULT NULL,
  `adjusted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_adjustments`
--

INSERT INTO `stock_adjustments` (`adjustment_id`, `stock_id`, `quantity`, `type`, `reason`, `adjusted_by`, `created_at`) VALUES
(1, 24, 1, 'damaged', 'Order #83 Return (Damaged)', 1, '2025-11-30 13:05:11'),
(2, 19, 1, 'return_restock', 'Order #84 Return (Sellable/No Refund)', 1, '2025-11-30 13:25:24');

-- --------------------------------------------------------

--
-- Table structure for table `stock_in`
--

CREATE TABLE `stock_in` (
  `stock_in_id` int(11) NOT NULL,
  `stock_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `date_added` date NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `purchase_price` double DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_in`
--

INSERT INTO `stock_in` (`stock_in_id`, `stock_id`, `quantity`, `date_added`, `supplier_id`, `purchase_price`) VALUES
(1, 1, 30, '2025-08-11', 1, NULL),
(2, 1, 40, '2025-08-11', 1, NULL),
(3, 1, 40, '2025-08-11', 1, NULL),
(5, 8, 20, '2025-08-11', 2, NULL),
(6, 1, 20, '2025-08-11', 1, NULL),
(10, 12, 5, '2025-09-08', 2, NULL),
(12, 14, 3, '2025-09-08', 1, NULL),
(14, 1, 10, '2025-10-03', 2, NULL),
(15, 1, 2, '2025-10-03', NULL, NULL),
(16, 12, 1, '2025-10-03', NULL, NULL),
(19, 14, 1, '2025-10-03', NULL, NULL),
(20, 16, 5, '2025-10-08', 2, NULL),
(21, 17, 5, '2025-10-08', 2, NULL),
(22, 18, 5, '2025-10-08', 1, NULL),
(23, 19, 5, '2025-10-08', 1, NULL),
(24, 20, 10, '2025-10-08', 1, NULL),
(25, 21, 10, '2025-10-08', 1, NULL),
(26, 21, 10, '2025-10-08', 1, NULL),
(27, 17, 10, '2025-10-08', 2, NULL),
(28, 16, 10, '2025-10-08', 2, NULL),
(29, 22, 10, '2025-10-12', 1, NULL),
(30, 23, 10, '2025-10-12', 1, NULL),
(31, 24, 10, '2025-10-12', 1, NULL),
(32, 25, 10, '2025-10-14', 1, NULL),
(33, 26, 5, '2025-10-14', 2, NULL),
(34, 27, 10, '2025-11-19', 1, NULL),
(35, 28, 10, '2025-11-21', 1, NULL),
(36, 29, 10, '2025-11-21', 1, NULL),
(37, 30, 10, '2025-11-21', 1, NULL),
(38, 31, 5, '2025-11-27', 5, NULL),
(39, 32, 5, '2025-11-27', 5, NULL),
(41, 34, 5, '2025-11-27', 5, NULL),
(42, 35, 5, '2025-11-27', 5, NULL),
(43, 36, 2, '2025-11-27', 5, NULL),
(44, 35, 10, '2025-11-30', 5, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `store_settings`
--

CREATE TABLE `store_settings` (
  `id` int(11) NOT NULL,
  `store_name` varchar(255) NOT NULL,
  `store_description` text DEFAULT NULL,
  `store_email` varchar(255) NOT NULL,
  `contact` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `timezone_locale` varchar(100) DEFAULT NULL,
  `theme` varchar(100) DEFAULT NULL,
  `homepage_layout` text DEFAULT NULL,
  `custom_css_html` text DEFAULT NULL,
  `shipping_method` varchar(100) DEFAULT NULL,
  `flat_rate_shipping` decimal(10,2) DEFAULT NULL,
  `delivery_time` varchar(100) DEFAULT NULL,
  `two_factor_auth` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `store_settings`
--

INSERT INTO `store_settings` (`id`, `store_name`, `store_description`, `store_email`, `contact`, `address`, `timezone_locale`, `theme`, `homepage_layout`, `custom_css_html`, `shipping_method`, `flat_rate_shipping`, `delivery_time`, `two_factor_auth`) VALUES
(1, 'Seven Dwarfs Boutique', 'This is a sample store description.', 'sevendwarfsboutique123@email.com', '123-456-7890', 'Bayambang, Pangasinan', 'Philippines', 'dark', '{\"featured_products\": true, \"categories\": true}', '', 'Standard', 5.99, '3-5 business days', 0);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(255) NOT NULL,
  `supplier_email` varchar(100) DEFAULT NULL,
  `supplier_phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `supplier_name`, `supplier_email`, `supplier_phone`, `created_at`) VALUES
(1, 'Supplier 1', 'supplier@email.com', '0987654321', '2025-04-30 13:50:11'),
(2, 'Supplier 2', 'suppliers2@email.com', '0912345678', '2025-05-01 13:25:54'),
(5, 'Supplier 3', 'supplier3@email.com', '0911225344', '2025-11-26 14:38:11');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `role_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`log_id`, `user_id`, `username`, `role_id`, `action`, `created_at`) VALUES
(1, 1, 'Ayesu', 0, 'User logged out', '2025-08-22 04:56:43'),
(2, 1, 'Ayesu', 0, 'User logged out', '2025-08-29 10:45:43'),
(3, 1, 'Ayesu', 0, 'User logged out', '2025-09-07 13:23:49'),
(4, 1, 'Ayesu', 0, 'User logged out', '2025-09-07 13:36:17'),
(5, 1, 'Ayesu', 0, 'User logged out', '2025-09-07 13:50:47'),
(6, 1, 'Ayesu', 0, 'User logged out', '2025-09-08 13:19:07'),
(7, 1, 'Ayesu', 0, 'User logged out', '2025-09-08 13:29:24'),
(10, 1, 'Ayesu', 0, 'User logged out', '2025-09-23 13:55:11'),
(12, 3, 'admin2', 2, 'User logged out', '2025-09-24 02:32:27'),
(14, 1, 'Ayesu', 0, 'User logged out', '2025-09-29 13:11:57'),
(15, 3, 'admin2', 2, 'User logged out', '2025-09-29 13:12:27'),
(16, 1, 'Ayesu', 0, 'User logged out', '2025-09-29 13:40:39'),
(17, 3, 'admin2', 2, 'User logged out', '2025-09-29 13:44:33'),
(18, 1, 'Ayesu', 0, 'User logged out', '2025-09-29 13:45:19'),
(19, 3, 'admin2', 2, 'User logged out', '2025-09-29 14:03:08'),
(20, 1, 'Ayesu', 0, 'User logged out', '2025-09-29 14:29:56'),
(22, 1, 'Ayesu', 0, 'User logged out', '2025-09-29 14:40:04'),
(23, 1, 'Ayesu', 0, 'User logged out', '2025-09-29 14:59:35'),
(24, 3, 'admin2', 2, 'User logged out', '2025-10-03 02:11:17'),
(25, 1, 'Ayesu', 0, 'User logged out', '2025-10-03 02:19:21'),
(26, 3, 'admin2', 2, 'User logged out', '2025-10-03 03:44:21'),
(27, 1, 'Ayesu', 0, 'User logged out', '2025-10-03 03:44:49'),
(28, 3, 'admin2', 2, 'User logged out', '2025-10-03 03:54:35'),
(29, 1, 'Ayesu', 0, 'User logged out', '2025-10-03 03:55:07'),
(30, 3, 'admin2', 2, 'User logged out', '2025-10-03 03:55:29'),
(31, 1, 'Ayesu', 0, 'User logged out', '2025-10-03 03:55:45'),
(32, 3, 'admin2', 2, 'User logged out', '2025-10-03 03:58:21'),
(33, 1, 'Ayesu', 0, 'User logged out', '2025-10-03 05:53:20'),
(34, 3, 'admin2', 2, 'User logged out', '2025-10-03 14:36:33'),
(35, 1, 'Ayesu', 0, 'User logged out', '2025-10-09 07:38:24'),
(36, 1, 'Ayesu', 0, 'User logged out', '2025-10-09 08:02:49'),
(38, 3, 'admin2', 2, 'User logged out', '2025-10-09 09:40:30'),
(39, 1, 'Ayesu', 0, 'User logged out', '2025-10-10 12:54:48'),
(40, 3, 'admin2', 2, 'User logged out', '2025-10-10 13:05:02'),
(42, 3, 'admin2', 2, 'User logged out', '2025-10-11 12:24:39'),
(43, 1, 'Ayesu', 0, 'User logged out', '2025-10-11 12:28:17'),
(44, 3, '', 0, 'Logout', '2025-10-11 13:18:03'),
(45, 1, '', 0, 'Login', '2025-10-11 13:18:27'),
(46, 1, '', 0, 'Logout', '2025-10-11 13:19:02'),
(47, 3, '', 0, 'Login', '2025-10-11 13:19:11'),
(48, 3, '', 0, 'Logout', '2025-10-11 13:19:25'),
(49, 3, '', 0, 'Login', '2025-10-12 01:43:23'),
(50, 3, 'admin2', 2, 'Added a new user: admin1', '2025-10-12 01:43:57'),
(51, 5, '', 0, 'Login', '2025-10-12 10:01:07'),
(52, 5, '', 0, 'Logout', '2025-10-12 10:01:33'),
(53, 3, '', 0, 'Login', '2025-10-12 10:02:04'),
(54, 3, 'admin2', 2, 'Updated category from \'category1\' to \'category\'', '2025-10-12 10:11:35'),
(55, 3, 'admin2', 2, 'Deleted category \'Test 2\' (ID: 12)', '2025-10-12 10:13:10'),
(56, 3, '', 0, 'Logout', '2025-10-12 10:59:47'),
(57, 5, 'admin1', 2, 'Login', '2025-10-12 10:59:57'),
(58, 5, '', 0, 'Logout', '2025-10-12 11:15:20'),
(59, 3, 'admin2', 2, 'Login', '2025-10-12 11:15:26'),
(60, 3, '', 0, 'Logout', '2025-10-12 12:12:40'),
(61, 1, 'Ayesu', 0, 'Login', '2025-10-12 12:12:50'),
(62, 1, '', 0, 'Logout', '2025-10-12 12:15:23'),
(63, 5, 'admin1', 2, 'Login', '2025-10-12 12:15:36'),
(64, 5, '', 0, 'Logout', '2025-10-12 12:33:13'),
(65, 1, 'Ayesu', 0, 'Login', '2025-10-14 10:17:58'),
(66, 1, '', 0, 'Logout', '2025-10-14 10:18:26'),
(67, 5, 'admin1', 2, 'Login', '2025-10-14 10:18:36'),
(68, 5, 'admin1', 2, 'Added a new user: Aisha', '2025-10-14 10:22:08'),
(69, 5, '', 0, 'Logout', '2025-10-14 10:22:14'),
(70, 6, 'Aisha', 0, 'Login', '2025-10-14 10:22:48'),
(71, 6, '', 0, 'Logout', '2025-10-14 10:25:09'),
(72, 3, 'admin2', 2, 'Login', '2025-10-14 10:25:24'),
(73, 3, '', 0, 'Logout', '2025-10-14 10:25:45'),
(74, 6, 'Aisha', 0, 'Login', '2025-10-14 10:26:04'),
(75, 6, '', 0, 'Logout', '2025-10-14 10:50:30'),
(76, 3, 'admin2', 2, 'Login', '2025-10-14 10:50:37'),
(77, 3, '', 0, 'Logout', '2025-10-14 10:59:41'),
(78, 3, 'admin2', 2, 'Login', '2025-10-14 11:50:23'),
(79, 3, 'admin2', 2, 'Deleted category \'category\' (ID: 13)', '2025-10-14 11:51:19'),
(80, 3, 'admin2', 2, 'Updated category from \'Test1\' to \'Test2\'', '2025-10-14 11:51:32'),
(81, 3, '', 0, 'Logout', '2025-10-14 11:52:46'),
(82, 3, 'admin2', 2, 'Login', '2025-10-17 02:08:44'),
(83, 3, '', 0, 'Logout', '2025-10-17 02:15:04'),
(84, 3, 'admin2', 2, 'Login', '2025-10-17 02:15:30'),
(85, 3, '', 0, 'Logout', '2025-10-17 03:10:28'),
(86, 1, 'Ayesu', 0, 'Login', '2025-10-17 03:10:32'),
(87, 1, '', 0, 'Logout', '2025-10-17 03:13:43'),
(88, 5, 'admin1', 2, 'Login', '2025-10-17 03:13:50'),
(89, 3, 'admin2', 2, 'Login', '2025-11-07 10:25:56'),
(90, 5, 'admin1', 2, 'Login', '2025-11-13 11:05:12'),
(91, 5, 'admin1', 2, 'Login', '2025-11-18 09:46:16'),
(92, 5, 'admin1', 2, 'Login', '2025-11-19 09:24:13'),
(93, 5, '', 0, 'Logout', '2025-11-19 10:40:26'),
(94, 1, 'Ayesu', 1, 'Login', '2025-11-19 10:40:32'),
(95, 1, '', 0, 'Logout', '2025-11-19 10:41:26'),
(96, 1, 'Ayesu', 1, 'Login', '2025-11-19 10:57:09'),
(97, 1, '', 0, 'Logout', '2025-11-19 11:04:48'),
(98, 1, 'Ayesu', 1, 'Login', '2025-11-20 00:20:37'),
(99, 1, 'Ayesu', 1, 'Login', '2025-11-20 00:20:56'),
(100, 1, '', 0, 'Logout', '2025-11-20 01:07:46'),
(101, 5, 'admin1', 2, 'Login', '2025-11-20 01:08:03'),
(102, 1, 'Ayesu', 1, 'Login', '2025-11-20 09:07:23'),
(103, 1, '', 0, 'Logout', '2025-11-20 09:15:50'),
(104, 5, 'admin1', 2, 'Login', '2025-11-20 09:15:56'),
(105, 5, '', 0, 'Logout', '2025-11-20 09:45:56'),
(106, 1, 'Ayesu', 1, 'Login', '2025-11-20 09:46:01'),
(107, 1, '', 0, 'Logout', '2025-11-20 09:48:42'),
(108, 5, 'admin1', 2, 'Login', '2025-11-20 09:48:47'),
(109, 1, 'Ayesu', 1, 'Login', '2025-11-21 00:24:20'),
(110, 1, '', 0, 'Logout', '2025-11-21 00:24:26'),
(111, 5, 'admin1', 2, 'Login', '2025-11-21 00:25:05'),
(112, 5, '', 0, 'Logout', '2025-11-21 01:01:03'),
(113, 3, 'admin2', 2, 'Login', '2025-11-21 01:01:17'),
(114, 3, '', 0, 'Logout', '2025-11-21 01:27:30'),
(115, 1, 'Ayesu', 1, 'Login', '2025-11-21 01:27:35'),
(116, 1, '', 0, 'Logout', '2025-11-21 01:28:43'),
(117, 3, 'admin2', 2, 'Login', '2025-11-21 01:29:02'),
(118, 3, '', 0, 'Logout', '2025-11-21 02:20:45'),
(119, 1, 'Ayesu', 1, 'Login', '2025-11-21 02:20:53'),
(120, 1, '', 0, 'Logout', '2025-11-21 02:21:57'),
(121, 5, 'admin1', 2, 'Login', '2025-11-21 02:22:15'),
(122, 1, 'Ayesu', 1, 'Login', '2025-11-21 08:39:08'),
(123, 1, 'Ayesu', 1, 'Login', '2025-11-21 09:00:20'),
(124, 1, '', 0, 'Logout', '2025-11-21 10:38:00'),
(125, 5, 'admin1', 2, 'Login', '2025-11-21 10:38:09'),
(126, 1, 'Ayesu', 1, 'Login', '2025-11-22 08:55:53'),
(127, 1, '', 0, 'Logout', '2025-11-22 09:02:50'),
(128, 5, 'admin1', 2, 'Login', '2025-11-22 09:02:57'),
(129, 5, '', 0, 'Logout', '2025-11-22 09:12:08'),
(130, 1, 'Ayesu', 1, 'Login', '2025-11-22 09:12:12'),
(131, 1, '', 0, 'Logout', '2025-11-22 09:34:51'),
(132, 3, 'admin2', 2, 'Login', '2025-11-22 09:35:29'),
(133, 3, '', 0, 'Logout', '2025-11-22 10:14:50'),
(134, 1, 'Ayesu', 1, 'Login', '2025-11-22 10:14:54'),
(135, 1, '', 0, 'Logout', '2025-11-22 11:45:05'),
(136, 5, 'admin1', 2, 'Login', '2025-11-22 11:45:13'),
(137, 1, 'Ayesu', 1, 'Login', '2025-11-23 09:23:54'),
(138, 1, '', 0, 'Logout', '2025-11-23 09:27:54'),
(139, 3, 'admin2', 2, 'Login', '2025-11-23 09:28:10'),
(140, 3, '', 0, 'Logout', '2025-11-23 09:35:24'),
(141, 1, 'Ayesu', 1, 'Login', '2025-11-23 09:35:31'),
(142, 1, '', 0, 'Logout', '2025-11-23 09:36:42'),
(143, 5, 'admin1', 2, 'Login', '2025-11-23 09:36:50'),
(144, 5, '', 0, 'Logout', '2025-11-23 09:53:53'),
(145, 1, 'Ayesu', 1, 'Login', '2025-11-23 09:53:59'),
(146, 1, '', 0, 'Logout', '2025-11-23 09:54:58'),
(147, 3, 'admin2', 2, 'Login', '2025-11-23 09:55:05'),
(148, 3, '', 0, 'Logout', '2025-11-23 10:12:00'),
(149, 5, 'admin1', 2, 'Login', '2025-11-23 10:14:50'),
(150, 5, '', 0, 'Logout', '2025-11-23 10:23:51'),
(151, 1, 'Ayesu', 1, 'Login', '2025-11-23 10:23:58'),
(152, 1, '', 0, 'Logout', '2025-11-23 10:25:18'),
(153, 5, 'admin1', 2, 'Login', '2025-11-23 10:25:42'),
(154, 5, '', 0, 'Logout', '2025-11-23 10:29:15'),
(155, 1, 'Ayesu', 1, 'Login', '2025-11-23 10:29:20'),
(156, 5, 'admin1', 2, 'Login', '2025-11-23 13:52:09'),
(157, 5, '', 0, 'Logout', '2025-11-23 14:43:26'),
(158, 1, 'Ayesu', 1, 'Login', '2025-11-23 14:43:32'),
(159, 1, '', 0, 'Logout', '2025-11-23 14:56:12'),
(160, 3, 'admin2', 2, 'Login', '2025-11-23 14:56:30'),
(161, 1, 'Ayesu', 1, 'Login', '2025-11-23 15:25:38'),
(162, 1, '', 0, 'Logout', '2025-11-23 15:26:08'),
(163, 5, 'admin1', 2, 'Login', '2025-11-24 08:00:12'),
(164, 5, 'admin1', 2, 'Updated category from \'Perfume\' to \'Preloved\'', '2025-11-24 08:02:20'),
(165, 5, 'admin1', 2, 'Updated category from \'Pants\' to \'Bathing Suit\'', '2025-11-24 08:02:44'),
(166, 5, 'admin1', 2, 'Updated category from \'Category1\' to \'Tote Bag\'', '2025-11-24 08:03:18'),
(167, 5, 'admin1', 2, 'Updated category from \'Category2\' to \'Gowns\'', '2025-11-24 08:03:32'),
(168, 5, '', 0, 'Logout', '2025-11-24 08:05:19'),
(169, 1, 'Ayesu', 1, 'Login', '2025-11-24 08:05:24'),
(170, 1, '', 0, 'Logout', '2025-11-24 08:29:34'),
(171, 5, 'admin1', 2, 'Login', '2025-11-24 08:29:51'),
(172, 5, '', 0, 'Logout', '2025-11-24 08:36:09'),
(173, 1, 'Ayesu', 1, 'Login', '2025-11-24 08:36:13'),
(174, 1, '', 0, 'Logout', '2025-11-24 09:04:27'),
(175, 5, 'admin1', 2, 'Login', '2025-11-24 09:04:39'),
(176, 5, 'admin1', 2, 'Login', '2025-11-24 13:24:03'),
(177, 5, '', 0, 'Logout', '2025-11-24 14:53:24'),
(178, 5, 'admin1', 2, 'Login', '2025-11-25 01:35:01'),
(179, 5, 'admin1', 2, 'Updated category from \'Purse\' to \'Purse1\'', '2025-11-25 02:18:37'),
(180, 5, 'admin1', 2, 'Updated category from \'Purse1\' to \'Purse\'', '2025-11-25 02:18:43'),
(181, 5, 'admin1', 2, 'Deleted category \'Test2\' (ID: 10)', '2025-11-25 02:18:53'),
(182, 1, 'Ayesu', 1, 'Login', '2025-11-25 03:50:49'),
(183, 1, '', 0, 'Logout', '2025-11-25 04:08:10'),
(184, 1, 'Ayesu', 1, 'Login', '2025-11-25 13:32:16'),
(185, 1, '', 0, 'Logout', '2025-11-25 14:17:02'),
(186, 5, 'admin1', 2, 'Login', '2025-11-25 14:17:12'),
(187, 5, 'admin1', 2, 'Updated category from \'Purse\' to \'Purse1\'', '2025-11-25 14:21:19'),
(188, 5, 'admin1', 2, 'Updated category from \'Purse1\' to \'Purse\'', '2025-11-25 14:21:26'),
(189, 5, '', 0, 'Logout', '2025-11-25 14:22:55'),
(190, 5, 'admin1', 2, 'Login', '2025-11-26 09:40:26'),
(191, 5, 'admin1', 2, 'Login', '2025-11-26 14:11:05'),
(192, 5, '', 0, 'Updated Stock ID: 30 and Prices for Product ID: 10', '2025-11-26 14:45:40'),
(193, 3, 'admin2', 2, 'Login', '2025-11-27 08:41:19'),
(194, 3, 'admin2', 2, 'Added a new user: EyaLee', '2025-11-27 08:41:48'),
(195, 3, '', 0, 'Updated Stock ID: 31 and Prices for Product ID: 11', '2025-11-27 09:02:51'),
(196, 3, '', 0, 'Updated Stock ID: 36 and Prices for Product ID: 15', '2025-11-27 09:50:53'),
(197, 3, '', 0, 'Logout', '2025-11-27 10:23:30'),
(198, 5, 'Unknown', 2, 'Logout', '2025-11-27 11:08:39'),
(199, 1, 'Unknown', 1, 'Logout', '2025-11-27 11:09:40'),
(200, 1, 'Unknown', 1, 'Logout', '2025-11-27 14:21:50'),
(201, 5, 'Unknown', 2, 'Logout', '2025-11-30 11:58:06'),
(202, 5, 'Unknown', 2, 'Logout', '2025-11-30 12:11:12'),
(203, 5, 'admin1', 2, 'Login', '2025-11-30 12:13:50'),
(204, 5, 'Unknown', 2, 'Logout', '2025-11-30 13:00:54'),
(205, 1, 'Eya', 1, 'Login', '2025-11-30 13:00:59'),
(206, 5, 'admin1', 2, 'Login', '2025-11-30 13:05:55');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `payment_method_id` int(10) UNSIGNED DEFAULT NULL,
  `total` decimal(10,2) NOT NULL,
  `order_status_id` int(10) UNSIGNED NOT NULL,
  `date_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`transaction_id`, `order_id`, `customer_id`, `payment_method_id`, `total`, `order_status_id`, `date_time`) VALUES
(3, 15, 1, 3, 850.00, 0, '2025-09-08 21:18:13'),
(5, 17, 1, 3, 350.00, 1, '2025-09-29 21:40:25'),
(6, 18, 1, 3, 500.00, 2, '2025-09-29 21:45:13'),
(7, 18, 1, 3, 500.00, 1, '2025-09-29 21:45:13'),
(10, 20, 1, NULL, 1050.00, 2, '2025-10-03 11:44:44'),
(11, 20, 1, NULL, 1050.00, 1, '2025-10-03 11:44:44'),
(17, 24, 1, 3, 700.00, 2, '2025-10-03 13:10:55'),
(18, 24, 1, 3, 700.00, 1, '2025-10-03 13:10:55'),
(19, 25, 1, 3, 1100.00, 2, '2025-10-03 13:33:23'),
(20, 25, 1, 3, 1100.00, 1, '2025-10-03 13:33:23'),
(21, 26, 1, 3, 1400.00, 2, '2025-10-03 13:39:09'),
(22, 26, 1, 3, 1400.00, 1, '2025-10-03 13:39:10'),
(24, 48, NULL, 3, 350.00, 0, '2025-10-09 15:41:49'),
(25, 49, NULL, 3, 750.00, 0, '2025-10-09 16:35:03'),
(26, 50, NULL, 3, 1050.00, 0, '2025-10-10 20:53:07'),
(27, 51, NULL, 3, 1250.00, 0, '2025-10-11 20:26:07'),
(28, 52, NULL, 3, 700.00, 0, '2025-10-11 20:28:07'),
(29, 53, NULL, 3, 1050.00, 0, '2025-10-12 20:14:59'),
(30, 54, NULL, 3, 1250.00, 0, '2025-10-14 18:23:11'),
(31, 55, NULL, 3, 700.00, 0, '2025-10-14 18:23:25'),
(32, 60, NULL, 3, 750.00, 0, '2025-10-14 18:40:20'),
(33, 62, NULL, 3, 750.00, 0, '2025-11-20 17:47:39'),
(34, 63, NULL, 3, 640.00, 0, '2025-11-21 09:27:57'),
(35, 64, NULL, 3, 670.00, 0, '2025-11-21 10:21:40'),
(36, 67, NULL, 3, 800.00, 0, '2025-11-21 17:25:20'),
(37, 67, NULL, NULL, -800.00, 2, '2025-11-21 18:37:19'),
(38, 68, NULL, 3, 640.00, 0, '2025-11-22 17:13:25'),
(39, 69, NULL, 3, -320.00, 2, '2025-11-22 18:20:46'),
(40, 70, NULL, 3, 750.00, 0, '2025-11-22 19:16:41'),
(41, 71, NULL, 3, 640.00, 0, '2025-11-23 17:35:49'),
(42, 72, NULL, 3, 1500.00, 0, '2025-11-23 17:54:21'),
(43, 72, NULL, NULL, -1000.00, 2, '2025-11-23 17:54:44'),
(44, 73, NULL, 1, 1750.00, 0, '2025-11-24 16:15:53'),
(45, 73, NULL, NULL, -700.00, 2, '2025-11-24 16:19:23'),
(46, 74, NULL, 1, 665.00, 0, '2025-11-24 16:50:19'),
(47, 75, NULL, 3, 650.00, 0, '2025-11-24 16:52:39'),
(48, 75, NULL, NULL, -350.00, 2, '2025-11-24 16:53:18'),
(49, 76, NULL, 3, 350.00, 0, '2025-11-24 17:01:44'),
(50, 77, NULL, 1, 320.00, 0, '2025-11-25 11:51:20'),
(51, 78, NULL, 3, 630.00, 0, '2025-11-25 21:58:13'),
(52, 79, NULL, 1, 1615.00, 0, '2025-11-27 19:09:06'),
(53, 79, NULL, NULL, -1700.00, 2, '2025-11-27 21:31:08'),
(54, 82, NULL, 3, 612.00, 0, '2025-11-30 21:04:20'),
(55, 83, NULL, 3, 656.50, 0, '2025-11-30 21:04:31'),
(56, 83, NULL, NULL, -332.50, 4, '2025-11-30 21:05:11'),
(57, 84, NULL, 3, 636.50, 0, '2025-11-30 21:23:29');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `adminusers`
--
ALTER TABLE `adminusers`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `admin_email` (`admin_email`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `status_id` (`status_id`);

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`cart_id`),
  ADD KEY `fk_carts_customer_id` (`customer_id`),
  ADD KEY `fk_carts_product_id` (`product_id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`cart_item_id`),
  ADD KEY `fk_cart_items_cart_id` (`cart_id`),
  ADD KEY `fk_cart_items_product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `colors`
--
ALTER TABLE `colors`
  ADD PRIMARY KEY (`color_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `status_id` (`status_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `fk_orders_status` (`order_status_id`),
  ADD KEY `fk_orders_payment_method` (`payment_method_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `stock_id` (`stock_id`);

--
-- Indexes for table `order_status`
--
ALTER TABLE `order_status`
  ADD PRIMARY KEY (`order_status_id`),
  ADD UNIQUE KEY `order_status_name` (`order_status_name`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`payment_method_id`),
  ADD UNIQUE KEY `payment_method_name` (`payment_method_name`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `fk_supplier` (`supplier_id`),
  ADD KEY `fk_products_category` (`category_id`);

--
-- Indexes for table `product_colors`
--
ALTER TABLE `product_colors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `color_id` (`color_id`);

--
-- Indexes for table `product_sizes`
--
ALTER TABLE `product_sizes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `size_id` (`size_id`);

--
-- Indexes for table `refunds`
--
ALTER TABLE `refunds`
  ADD PRIMARY KEY (`refund_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `order_item_id` (`order_item_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `stock_id` (`stock_id`),
  ADD KEY `size_id` (`size_id`),
  ADD KEY `color_id` (`color_id`),
  ADD KEY `refunded_by` (`refunded_by`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `sizes`
--
ALTER TABLE `sizes`
  ADD PRIMARY KEY (`size_id`);

--
-- Indexes for table `status`
--
ALTER TABLE `status`
  ADD PRIMARY KEY (`status_id`);

--
-- Indexes for table `stock`
--
ALTER TABLE `stock`
  ADD PRIMARY KEY (`stock_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `fk_stock_color` (`color_id`),
  ADD KEY `fk_stock_size` (`size_id`);

--
-- Indexes for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  ADD PRIMARY KEY (`adjustment_id`),
  ADD KEY `stock_id` (`stock_id`);

--
-- Indexes for table `stock_in`
--
ALTER TABLE `stock_in`
  ADD PRIMARY KEY (`stock_in_id`),
  ADD KEY `stock_id` (`stock_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `store_settings`
--
ALTER TABLE `store_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `fk_transactions_orders` (`order_id`),
  ADD KEY `fk_transactions_customers` (`customer_id`),
  ADD KEY `fk_transactions_payment_method` (`payment_method_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `adminusers`
--
ALTER TABLE `adminusers`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `cart_item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `colors`
--
ALTER TABLE `colors`
  MODIFY `color_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `payment_method_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `product_colors`
--
ALTER TABLE `product_colors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `product_sizes`
--
ALTER TABLE `product_sizes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `refunds`
--
ALTER TABLE `refunds`
  MODIFY `refund_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sizes`
--
ALTER TABLE `sizes`
  MODIFY `size_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `status`
--
ALTER TABLE `status`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `stock`
--
ALTER TABLE `stock`
  MODIFY `stock_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  MODIFY `adjustment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `stock_in`
--
ALTER TABLE `stock_in`
  MODIFY `stock_in_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `store_settings`
--
ALTER TABLE `store_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=207;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `adminusers`
--
ALTER TABLE `adminusers`
  ADD CONSTRAINT `adminusers_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `adminusers_ibfk_2` FOREIGN KEY (`status_id`) REFERENCES `status` (`status_id`) ON DELETE CASCADE;

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `fk_carts_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_carts_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `fk_cart_items_cart_id` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`cart_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cart_items_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`status_id`) REFERENCES `status` (`status_id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_payment_method` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`payment_method_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_status` FOREIGN KEY (`order_status_id`) REFERENCES `order_status` (`order_status_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`stock_id`) REFERENCES `stock` (`stock_id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`);

--
-- Constraints for table `product_colors`
--
ALTER TABLE `product_colors`
  ADD CONSTRAINT `product_colors_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_colors_ibfk_2` FOREIGN KEY (`color_id`) REFERENCES `colors` (`color_id`) ON DELETE CASCADE;

--
-- Constraints for table `product_sizes`
--
ALTER TABLE `product_sizes`
  ADD CONSTRAINT `product_sizes_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_sizes_ibfk_2` FOREIGN KEY (`size_id`) REFERENCES `sizes` (`size_id`) ON DELETE CASCADE;

--
-- Constraints for table `refunds`
--
ALTER TABLE `refunds`
  ADD CONSTRAINT `refunds_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `refunds_ibfk_2` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `refunds_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `refunds_ibfk_4` FOREIGN KEY (`stock_id`) REFERENCES `stock` (`stock_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `refunds_ibfk_5` FOREIGN KEY (`size_id`) REFERENCES `sizes` (`size_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `refunds_ibfk_6` FOREIGN KEY (`color_id`) REFERENCES `colors` (`color_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `refunds_ibfk_7` FOREIGN KEY (`refunded_by`) REFERENCES `adminusers` (`admin_id`) ON DELETE SET NULL;

--
-- Constraints for table `stock`
--
ALTER TABLE `stock`
  ADD CONSTRAINT `fk_stock_color` FOREIGN KEY (`color_id`) REFERENCES `colors` (`color_id`),
  ADD CONSTRAINT `fk_stock_size` FOREIGN KEY (`size_id`) REFERENCES `sizes` (`size_id`),
  ADD CONSTRAINT `stock_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  ADD CONSTRAINT `fk_adjustment_stock` FOREIGN KEY (`stock_id`) REFERENCES `stock` (`stock_id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_in`
--
ALTER TABLE `stock_in`
  ADD CONSTRAINT `stock_in_ibfk_1` FOREIGN KEY (`stock_id`) REFERENCES `stock` (`stock_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_in_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`);

--
-- Constraints for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `adminusers` (`admin_id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_transactions_customers` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_transactions_orders` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_transactions_payment_method` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`payment_method_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
