-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 18, 2025 at 02:39 PM
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
-- Database: `u848848112_jhemamain`
--

-- --------------------------------------------------------

--
-- Table structure for table `attributes`
--

CREATE TABLE `attributes` (
  `id` int(11) NOT NULL,
  `type_id` int(11) NOT NULL,
  `value` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attributes`
--

INSERT INTO `attributes` (`id`, `type_id`, `value`) VALUES
(1, 1, 'Casual'),
(2, 1, 'Corporate'),
(4, 1, 'Formal'),
(3, 1, 'Sexy'),
(11, 2, 'Long'),
(10, 2, 'Maxi'),
(9, 2, 'Midi'),
(8, 2, 'Short'),
(18, 3, 'Fitted'),
(19, 3, 'Free'),
(15, 3, 'Pant Set'),
(16, 3, 'Short Set'),
(17, 3, 'Skirt Set');

-- --------------------------------------------------------

--
-- Table structure for table `attribute_types`
--

CREATE TABLE `attribute_types` (
  `id` int(11) NOT NULL,
  `code` varchar(40) NOT NULL,
  `label` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attribute_types`
--

INSERT INTO `attribute_types` (`id`, `code`, `label`) VALUES
(1, 'occasion', 'Occasion'),
(2, 'length', 'Length'),
(3, 'style', 'Style');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(140) NOT NULL,
  `parent_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `parent_id`) VALUES
(1, 'Shoes', 'shoes', NULL),
(2, 'Shirts', 'shirts', NULL),
(3, 'Accessories', 'accessories', NULL),
(4, 'Dresses', 'dresses', NULL),
(5, 'Plain Matching Sets', 'plain-matching-sets', NULL),
(6, 'Print Matching Sets', 'print-matching-sets', NULL),
(7, 'Plain Colour Combo Sets', 'plain-colour-combo-sets', NULL),
(8, 'Pattern & Plain Combo Sets', 'pattern-and-plain-combo-sets', NULL),
(9, 'Jumpsuits', 'jumpsuits', NULL),
(10, 'Playsuits', 'playsuits', NULL),
(11, 'Tops', 'tops', NULL),
(12, 'Pants', 'pants', NULL),
(13, 'Skirts', 'skirts', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `category_attribute_allowed`
--

CREATE TABLE `category_attribute_allowed` (
  `category_id` int(11) NOT NULL,
  `attribute_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `category_attribute_allowed`
--

INSERT INTO `category_attribute_allowed` (`category_id`, `attribute_id`) VALUES
(2, 1),
(2, 2),
(2, 18),
(2, 19),
(4, 1),
(4, 2),
(4, 3),
(4, 4),
(4, 8),
(4, 9),
(4, 10),
(5, 1),
(5, 2),
(5, 3),
(5, 15),
(5, 16),
(5, 17),
(6, 1),
(6, 2),
(6, 3),
(6, 15),
(6, 16),
(6, 17),
(7, 1),
(7, 2),
(7, 3),
(7, 15),
(7, 16),
(7, 17),
(8, 1),
(8, 2),
(8, 3),
(8, 15),
(8, 16),
(8, 17),
(9, 1),
(9, 2),
(9, 3),
(9, 18),
(9, 19),
(10, 1),
(10, 3),
(10, 18),
(10, 19),
(11, 1),
(11, 2),
(11, 3),
(11, 18),
(11, 19),
(12, 1),
(12, 2),
(12, 3),
(12, 18),
(12, 19),
(13, 1),
(13, 2),
(13, 3),
(13, 8),
(13, 9),
(13, 11),
(13, 18),
(13, 19);

-- --------------------------------------------------------

--
-- Table structure for table `currencies`
--

CREATE TABLE `currencies` (
  `code` char(3) NOT NULL,
  `symbol` varchar(8) NOT NULL,
  `is_base` tinyint(1) NOT NULL DEFAULT 0,
  `rate_to_base` decimal(18,8) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `currencies`
--

INSERT INTO `currencies` (`code`, `symbol`, `is_base`, `rate_to_base`) VALUES
('EUR', '€', 0, 1700.00000000),
('NGN', '₦', 1, 1.00000000),
('USD', '$', 0, 1600.00000000);

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(160) NOT NULL,
  `sku` varchar(64) NOT NULL,
  `description` text DEFAULT NULL,
  `base_currency_code` char(3) NOT NULL,
  `base_price` decimal(12,2) NOT NULL,
  `weight_kg` decimal(8,3) NOT NULL DEFAULT 0.000,
  `image_path` varchar(255) DEFAULT NULL,
  `featured_image_id` int(11) DEFAULT NULL,
  `featured_variant_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `sku`, `description`, `base_currency_code`, `base_price`, `weight_kg`, `image_path`, `featured_image_id`, `featured_variant_id`, `created_at`, `updated_at`, `is_active`) VALUES
(2, 13, 'emmanuel Iwok', 'emmanuel-iwok', 'hggyyg', 'uffttftft,iohohi', 'NGN', 777.00, 0.000, NULL, NULL, NULL, '2025-08-10 21:51:25', '2025-08-17 21:34:35', 1),
(5, 4, 'emmanuel Iwok', 'emmanuel-iwok-1', 'ikiiggyg', 'uhjj', 'NGN', 878.00, 0.000, NULL, NULL, NULL, '2025-08-10 23:49:36', '2025-08-17 21:34:35', 1),
(12, 3, 'emmanuel', 'emmanuel', 'pop', 'short, yiygyu', 'NGN', 89.00, 0.000, NULL, NULL, NULL, '2025-08-11 00:11:50', '2025-08-17 21:34:35', 1),
(16, 4, 'Inimfon', 'inimfon', 'opo', 'ghgjgd', 'NGN', 898.00, 0.000, NULL, NULL, NULL, '2025-08-11 00:16:04', '2025-08-17 21:34:35', 1),
(17, 4, 'MARY', 'mary', 'JUSTY', 'ddssds', 'NGN', 34.00, 0.000, NULL, NULL, NULL, '2025-08-11 14:45:44', '2025-08-17 21:34:35', 1),
(18, 4, 'Emman maet', 'emman-maet', 'rrtr', 'ssddds', 'NGN', 344.00, 0.000, 'uploads/20250811_144903_0bf8921d26.png', NULL, NULL, '2025-08-11 14:49:05', '2025-08-17 21:34:35', 1),
(19, 4, 'here', 'here', 'kjahs', 'saddsd', 'NGN', 33.00, 0.000, 'uploads/20250811_192942_8fa0d21ce6.png', NULL, NULL, '2025-08-11 19:29:45', '2025-08-17 21:34:35', 1),
(21, 13, 'Jhema', 'jhema', 'aahgas', '', 'NGN', 5000.00, 0.000, 'uploads/20250815_152346_c24173f88f.png', NULL, NULL, '2025-08-15 15:23:49', '2025-08-17 21:34:35', 1),
(26, 2, 'ClassicFit™ Premium T-Shirt', 'classicfit-premium-t-shirt-ja-001', 'JA-001', 'The ClassicFit™ Premium T-Shirt is designed for everyday comfort and timeless style. Crafted from soft, breathable cotton fabric, this tee offers a perfect balance of durability and lightweight wear. Its tailored fit enhances your silhouette, while the reinforced neckline and hem ensure long-lasting shape retention. Ideal for casual outings, layering, or customization, the ClassicFit™ is a versatile wardrobe essential that embodies simplicity and sophistication.', 'NGN', 6000.00, 0.300, 'uploads/20250817_132932_36acaa9cbd.png', 8, NULL, '2025-08-17 13:29:34', '2025-08-17 21:34:35', 1),
(29, 2, 'ClassicFit™ Premium T-Shirt', 'classicfit-premium-t-shirt-ja-002', 'JA-002', 'The ClassicFit™ Premium T-Shirt is designed for everyday comfort and timeless style. Crafted from soft, breathable cotton fabric, this tee offers a perfect balance of durability and lightweight wear. Its tailored fit enhances your silhouette, while the reinforced neckline and hem ensure long-lasting shape retention. Ideal for casual outings, layering, or customization, the ClassicFit™ is a versatile wardrobe essential that embodies simplicity and sophistication.', 'NGN', 6000.00, 0.300, 'uploads/20250817_152717_592266356d.png', 15, NULL, '2025-08-17 15:27:20', '2025-08-17 21:34:35', 1),
(31, 2, 'ClassicFit™ Premium T-Shirt', 'classicfit-premium-t-shirt-ja-006', 'JA-006', 'The ClassicFit™ Premium T-Shirt is designed for everyday comfort and timeless style. Crafted from soft, breathable cotton fabric, this tee offers a perfect balance of durability and lightweight wear. Its tailored fit enhances your silhouette, while the reinforced neckline and hem ensure long-lasting shape retention. Ideal for casual outings, layering, or customization, the ClassicFit™ is a versatile wardrobe essential that embodies simplicity and sophistication.', 'NGN', 7000.00, 0.003, 'images/products/prod_68a25233db6f57.67780391.png', NULL, NULL, '2025-08-17 22:05:42', '2025-08-17 22:05:42', 1),
(32, 2, 'ClassicFit™ Premium T-Shirt', 'classicfit-premium-t-shirt-ja-007', 'JA-007', 'The ClassicFit™ Premium White T-Shirt is designed for everyday comfort and timeless style. Crafted from soft, breathable cotton fabric, this tee offers a perfect balance of durability and lightweight wear. Its tailored fit enhances your silhouette, while the reinforced neckline and hem ensure long-lasting shape retention. Ideal for casual outings, layering, or customization, the ClassicFit™ is a versatile wardrobe essential that embodies simplicity and sophistication.', 'NGN', 10000.00, 0.300, 'images/products/prod_68a30668d9aed0.91200427.png', NULL, 28, '2025-08-18 10:54:35', '2025-08-18 10:54:37', 1);

-- --------------------------------------------------------

--
-- Table structure for table `product_attribute`
--

CREATE TABLE `product_attribute` (
  `product_id` int(11) NOT NULL,
  `attribute_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_attribute`
--

INSERT INTO `product_attribute` (`product_id`, `attribute_id`) VALUES
(26, 1),
(26, 18),
(29, 1),
(29, 18);

-- --------------------------------------------------------

--
-- Table structure for table `product_attributes`
--

CREATE TABLE `product_attributes` (
  `product_id` int(11) NOT NULL,
  `attribute_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_attributes`
--

INSERT INTO `product_attributes` (`product_id`, `attribute_id`) VALUES
(5, 1),
(5, 8),
(16, 3),
(16, 9),
(17, 1),
(17, 8),
(18, 4),
(18, 9),
(19, 2),
(19, 9),
(21, 1),
(21, 9),
(21, 19),
(31, 1),
(31, 18),
(32, 1),
(32, 18);

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_main` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `image_path`, `is_main`, `sort_order`, `created_at`) VALUES
(8, 26, 'uploads/20250817_132932_36acaa9cbd.png', 1, 0, '2025-08-17 13:29:35'),
(9, 26, 'uploads/20250817_132933_2ee873197e.png', 0, 1, '2025-08-17 13:29:35'),
(10, 26, 'uploads/20250817_132933_71b34f8bb2.png', 0, 2, '2025-08-17 13:29:36'),
(11, 26, 'uploads/20250817_132933_5335dce601.png', 0, 3, '2025-08-17 13:29:36'),
(12, 26, 'uploads/20250817_132933_37d14275f7.png', 0, 4, '2025-08-17 13:29:36'),
(13, 26, 'uploads/20250817_132934_9cdbf1febe.png', 0, 5, '2025-08-17 13:29:36'),
(14, 26, 'uploads/20250817_132934_ea15411e03.png', 0, 6, '2025-08-17 13:29:37'),
(15, 29, 'uploads/20250817_152717_592266356d.png', 1, 0, '2025-08-17 15:27:20'),
(16, 29, 'uploads/20250817_152718_74d6a0cb78.png', 0, 1, '2025-08-17 15:27:20'),
(17, 29, 'uploads/20250817_152718_16fa34579f.png', 0, 2, '2025-08-17 15:27:21'),
(18, 29, 'uploads/20250817_152718_39ac9e8c51.png', 0, 3, '2025-08-17 15:27:21'),
(19, 29, 'uploads/20250817_152719_7610649d01.png', 0, 4, '2025-08-17 15:27:21'),
(20, 29, 'uploads/20250817_152719_b329cbbba5.png', 0, 5, '2025-08-17 15:27:22'),
(21, 29, 'uploads/20250817_152719_c4e49c60b4.png', 0, 6, '2025-08-17 15:27:22'),
(22, 31, 'images/products/prod_68a252342dbb82.93331879.png', 0, 1, '2025-08-17 22:05:42'),
(23, 31, 'images/products/prod_68a252346e1265.19791192.png', 0, 2, '2025-08-17 22:05:43'),
(24, 31, 'images/products/prod_68a25234b02cf7.29508235.png', 0, 3, '2025-08-17 22:05:43'),
(25, 31, 'images/products/prod_68a25234ee7424.62659412.png', 0, 4, '2025-08-17 22:05:43'),
(26, 31, 'images/products/prod_68a252353b46b7.18547329.png', 0, 5, '2025-08-17 22:05:43'),
(27, 31, 'images/products/prod_68a252357c76c5.40110164.png', 0, 6, '2025-08-17 22:05:44'),
(28, 32, 'images/products/prod_68a30669279344.11814150.png', 0, 1, '2025-08-18 10:54:35'),
(29, 32, 'images/products/prod_68a30669686945.13204584.png', 0, 2, '2025-08-18 10:54:36'),
(30, 32, 'images/products/prod_68a30669a91a73.52763166.png', 0, 3, '2025-08-18 10:54:36'),
(31, 32, 'images/products/prod_68a30669e75b59.92310037.png', 0, 4, '2025-08-18 10:54:36'),
(32, 32, 'images/products/prod_68a3066a324737.63891580.png', 0, 5, '2025-08-18 10:54:36'),
(33, 32, 'images/products/prod_68a3066a749f03.74572567.png', 0, 6, '2025-08-18 10:54:37');

-- --------------------------------------------------------

--
-- Table structure for table `product_variants`
--

CREATE TABLE `product_variants` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `type` enum('size','color','combo') NOT NULL,
  `size` varchar(50) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `price` decimal(12,2) DEFAULT NULL,
  `price_override` decimal(12,2) NOT NULL,
  `weight_kg_override` decimal(8,3) DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `image_path` varchar(255) DEFAULT NULL,
  `featured` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_variants`
--

INSERT INTO `product_variants` (`id`, `product_id`, `type`, `size`, `color`, `price`, `price_override`, `weight_kg_override`, `stock`, `image_path`, `featured`) VALUES
(4, 5, 'size', 's', NULL, NULL, 78.00, NULL, 78, 'uploads/20250810_234935_595df81fd2.png', 0),
(5, 5, 'size', NULL, 'red', NULL, 788.00, NULL, 90, 'uploads/20250810_234935_216f3a7989.png', 0),
(6, 12, 'size', 's', NULL, NULL, 565.00, NULL, 88, NULL, 0),
(7, 12, 'size', NULL, 'tomato', NULL, 4565.00, NULL, 88, NULL, 0),
(8, 16, 'size', 'm', NULL, NULL, 6767.00, NULL, 770, 'uploads/20250811_001603_d8fa9d7956.png', 0),
(9, 17, 'size', 's', NULL, NULL, 4.00, NULL, 23, 'uploads/20250811_144543_def52b31cf.png', 0),
(10, 19, 'size', 's', NULL, NULL, 12.00, NULL, 4, 'uploads/20250811_192943_b787874fca.png', 0),
(16, 21, 'size', 's', NULL, NULL, 4000.00, NULL, 56, 'uploads/20250815_152347_badd5e9492.png', 0),
(17, 21, 'size', NULL, 'White', NULL, 6786.00, NULL, 78, 'uploads/20250815_152347_df8f458efc.png', 0),
(18, 21, 'size', 's', 'red', NULL, 4546.00, NULL, 781, 'uploads/20250815_152347_56616d7d39.png', 0),
(19, 21, 'size', 'm', 'white', NULL, 7675.00, NULL, 54, 'uploads/20250815_152347_f6865ea467.png', 0),
(20, 26, 'combo', 'M', 'Yellow', 300.00, 0.00, NULL, 20, 'uploads/20250817_132934_7b99664838.png', 0),
(21, 26, 'combo', 'S', 'White', 6000.00, 0.00, NULL, 13, 'uploads/20250817_132935_297859ffff.png', 0),
(22, 26, 'combo', 'L', 'Pink', 400.00, 0.00, NULL, 10, 'uploads/20250817_132935_f2d38869cf.png', 0),
(23, 29, 'combo', 'M', 'White', 6000.00, 0.00, NULL, 10, 'uploads/20250817_152719_b0c7427643.png', 0),
(24, 29, 'combo', 'S', 'Yellow', 3000.00, 0.00, NULL, 50, 'uploads/20250817_152720_c6a1cc2637.png', 0),
(25, 31, 'combo', 'S', 'White', 700.00, 0.00, NULL, 10, 'images/products/prod_68a25235bae795.54009714.png', 0),
(26, 31, 'combo', 'M', 'Yellow', 1000.00, 0.00, NULL, 10, 'images/products/prod_68a25236067684.88217478.png', 0),
(27, 32, 'combo', 'S', 'Yellow', 600.00, 0.00, NULL, 10, 'images/products/prod_68a3066ab42026.69848797.png', 0),
(28, 32, 'combo', 'M', 'White', 100.00, 0.00, NULL, 10, 'images/products/prod_68a3066b0408f2.97042766.png', 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `remember_token` char(64) DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `password_hash`, `remember_token`, `is_verified`, `created_at`, `updated_at`, `last_login_at`) VALUES
(6, 'Treasure', 'Idem', 'treasurebis@gmail.com', '$argon2id$v=19$m=65536,t=4,p=1$Lm5yeVpuWm92QU0vWjZVcA$ZX6a0kYOxNyjZ41BM5p4de1rfqTIRzuwv9jrRQj1HQk', NULL, 1, '2025-08-18 11:59:09', '2025-08-18 13:17:22', '2025-08-18 13:17:22');

-- --------------------------------------------------------

--
-- Table structure for table `wishlists`
--

CREATE TABLE `wishlists` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wishlists`
--

INSERT INTO `wishlists` (`id`, `user_id`, `product_id`, `created_at`) VALUES
(1, 6, 32, '2025-08-18 14:00:04');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attributes`
--
ALTER TABLE `attributes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_type_val` (`type_id`,`value`);

--
-- Indexes for table `attribute_types`
--
ALTER TABLE `attribute_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `fk_cat_parent` (`parent_id`);

--
-- Indexes for table `category_attribute_allowed`
--
ALTER TABLE `category_attribute_allowed`
  ADD PRIMARY KEY (`category_id`,`attribute_id`),
  ADD KEY `fk_caa_attr` (`attribute_id`);

--
-- Indexes for table `currencies`
--
ALTER TABLE `currencies`
  ADD PRIMARY KEY (`code`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `fk_products_category` (`category_id`),
  ADD KEY `fk_products_currency` (`base_currency_code`),
  ADD KEY `fk_products_featured_image` (`featured_image_id`),
  ADD KEY `fk_products_featured_variant` (`featured_variant_id`);

--
-- Indexes for table `product_attribute`
--
ALTER TABLE `product_attribute`
  ADD PRIMARY KEY (`product_id`,`attribute_id`),
  ADD KEY `fk_pattr_attr` (`attribute_id`);

--
-- Indexes for table `product_attributes`
--
ALTER TABLE `product_attributes`
  ADD PRIMARY KEY (`product_id`,`attribute_id`),
  ADD KEY `fk_pa_a` (`attribute_id`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pi_product` (`product_id`);

--
-- Indexes for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_variant` (`product_id`,`size`,`color`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `email_2` (`email`);

--
-- Indexes for table `wishlists`
--
ALTER TABLE `wishlists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_product` (`user_id`,`product_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_product` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attributes`
--
ALTER TABLE `attributes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `attribute_types`
--
ALTER TABLE `attribute_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `product_variants`
--
ALTER TABLE `product_variants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `wishlists`
--
ALTER TABLE `wishlists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_featured_image` FOREIGN KEY (`featured_image_id`) REFERENCES `product_images` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_products_featured_variant` FOREIGN KEY (`featured_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_attribute`
--
ALTER TABLE `product_attribute`
  ADD CONSTRAINT `fk_pattr_attr` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pattr_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `fk_pi_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
