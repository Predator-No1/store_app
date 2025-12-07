-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.0.30 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.1.0.6537
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for store_management
DROP DATABASE IF EXISTS `store_management`;
CREATE DATABASE IF NOT EXISTS `store_management` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `store_management`;

-- Dumping structure for table store_management.activity_logs
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `action_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_action_type` (`action_type`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table store_management.activity_logs: ~0 rows (approximately)

-- Dumping structure for table store_management.categories
DROP TABLE IF EXISTS `categories`;
CREATE TABLE IF NOT EXISTS `categories` (
  `category_id` int NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `category_name` (`category_name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table store_management.categories: ~5 rows (approximately)
INSERT INTO `categories` (`category_id`, `category_name`, `description`, `is_active`, `created_at`) VALUES
	(1, 'Électronique', 'Produits électroniques et gadgets', 1, '2025-12-04 14:09:26'),
	(2, 'Alimentation', 'Produits alimentaires et boissons', 1, '2025-12-04 14:09:26'),
	(3, 'Vêtements', 'Habillement et accessoires', 1, '2025-12-04 14:09:26'),
	(4, 'Maison', 'Articles pour la maison', 1, '2025-12-04 14:09:26'),
	(5, 'Santé', 'Produits de santé et hygiène', 1, '2025-12-04 14:09:26');

-- Dumping structure for view store_management.daily_sales
DROP VIEW IF EXISTS `daily_sales`;
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `daily_sales` (
	`sale_date` DATE NULL,
	`total_orders` BIGINT(19) NOT NULL,
	`total_revenue` DECIMAL(34,2) NULL
) ENGINE=MyISAM;

-- Dumping structure for table store_management.invoices
DROP TABLE IF EXISTS `invoices`;
CREATE TABLE IF NOT EXISTS `invoices` (
  `invoice_id` int NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` int NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `issue_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`invoice_id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `idx_invoice_number` (`invoice_number`),
  KEY `idx_issue_date` (`issue_date`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table store_management.invoices: ~0 rows (approximately)

-- Dumping structure for view store_management.low_stock_products
DROP VIEW IF EXISTS `low_stock_products`;
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `low_stock_products` (
	`product_id` INT(10) NOT NULL,
	`product_name` VARCHAR(200) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`quantity_in_stock` INT(10) NOT NULL,
	`price` DECIMAL(12,2) NOT NULL,
	`category` VARCHAR(100) NULL COLLATE 'utf8mb4_unicode_ci'
) ENGINE=MyISAM;

-- Dumping structure for table store_management.orders
DROP TABLE IF EXISTS `orders`;
CREATE TABLE IF NOT EXISTS `orders` (
  `order_id` int NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `payment_method` enum('cash','mobile_money','bank_transfer','card') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `order_status` enum('pending','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`order_id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `idx_order_number` (`order_number`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_order_status` (`order_status`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table store_management.orders: ~0 rows (approximately)

-- Dumping structure for table store_management.order_items
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE IF NOT EXISTS `order_items` (
  `order_item_id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `product_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  PRIMARY KEY (`order_item_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_product_id` (`product_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table store_management.order_items: ~0 rows (approximately)

-- Dumping structure for table store_management.products
DROP TABLE IF EXISTS `products`;
CREATE TABLE IF NOT EXISTS `products` (
  `product_id` int NOT NULL AUTO_INCREMENT,
  `product_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(12,2) NOT NULL,
  `quantity_in_stock` int NOT NULL DEFAULT '0',
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barcode` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`product_id`),
  UNIQUE KEY `barcode` (`barcode`),
  KEY `idx_product_name` (`product_name`),
  KEY `idx_category` (`category`),
  KEY `idx_barcode` (`barcode`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table store_management.products: ~5 rows (approximately)
INSERT INTO `products` (`product_id`, `product_name`, `description`, `price`, `quantity_in_stock`, `category`, `barcode`, `image_url`, `is_active`, `created_at`, `updated_at`, `created_by`) VALUES
	(1, 'Smartphone Samsung Galaxy', 'Téléphone intelligent 64GB', 150000.00, 25, 'Électronique', NULL, NULL, 1, '2025-12-04 14:09:26', '2025-12-04 14:09:26', 1),
	(2, 'Riz local 50kg', 'Sac de riz produit localement', 25000.00, 100, 'Alimentation', NULL, NULL, 1, '2025-12-04 14:09:26', '2025-12-04 14:09:26', 1),
	(3, 'Chemise homme', 'Chemise en coton taille M', 8500.00, 50, 'Vêtements', NULL, NULL, 1, '2025-12-04 14:09:26', '2025-12-04 14:09:26', 1),
	(4, 'Savon de Marseille', 'Pain de savon 200g', 1500.00, 200, 'Santé', NULL, NULL, 1, '2025-12-04 14:09:26', '2025-12-04 14:09:26', 1),
	(5, 'Casserole inox', 'Casserole 5 litres', 12000.00, 30, 'Maison', NULL, NULL, 1, '2025-12-04 14:09:26', '2025-12-04 14:09:26', 1);

-- Dumping structure for view store_management.top_products
DROP VIEW IF EXISTS `top_products`;
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `top_products` (
	`product_id` INT(10) NOT NULL,
	`product_name` VARCHAR(200) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`category` VARCHAR(100) NULL COLLATE 'utf8mb4_unicode_ci',
	`times_sold` BIGINT(19) NOT NULL,
	`total_quantity_sold` DECIMAL(32,0) NULL,
	`total_revenue` DECIMAL(34,2) NULL
) ENGINE=MyISAM;

-- Dumping structure for table store_management.users
DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','employee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'employee',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_username` (`username`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table store_management.users: ~1 rows (approximately)
INSERT INTO `users` (`user_id`, `username`, `password_hash`, `full_name`, `role`, `is_active`, `created_at`, `last_login`) VALUES
	(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', 1, '2025-12-04 14:09:26', NULL);

-- Dumping structure for view store_management.user_performance
DROP VIEW IF EXISTS `user_performance`;
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `user_performance` (
	`user_id` INT(10) NOT NULL,
	`username` VARCHAR(50) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`full_name` VARCHAR(100) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`role` ENUM('admin','employee') NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`total_orders` BIGINT(19) NOT NULL,
	`total_sales` DECIMAL(34,2) NULL
) ENGINE=MyISAM;

-- Dumping structure for view store_management.daily_sales
DROP VIEW IF EXISTS `daily_sales`;
-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `daily_sales`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `daily_sales` AS select cast(`orders`.`created_at` as date) AS `sale_date`,count(0) AS `total_orders`,sum(`orders`.`total_amount`) AS `total_revenue` from `orders` where (`orders`.`order_status` = 'completed') group by cast(`orders`.`created_at` as date);

-- Dumping structure for view store_management.low_stock_products
DROP VIEW IF EXISTS `low_stock_products`;
-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `low_stock_products`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `low_stock_products` AS select `products`.`product_id` AS `product_id`,`products`.`product_name` AS `product_name`,`products`.`quantity_in_stock` AS `quantity_in_stock`,`products`.`price` AS `price`,`products`.`category` AS `category` from `products` where ((`products`.`quantity_in_stock` < 10) and (`products`.`is_active` = true)) order by `products`.`quantity_in_stock`;

-- Dumping structure for view store_management.top_products
DROP VIEW IF EXISTS `top_products`;
-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `top_products`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `top_products` AS select `p`.`product_id` AS `product_id`,`p`.`product_name` AS `product_name`,`p`.`category` AS `category`,count(`oi`.`order_item_id`) AS `times_sold`,sum(`oi`.`quantity`) AS `total_quantity_sold`,sum(`oi`.`subtotal`) AS `total_revenue` from ((`products` `p` join `order_items` `oi` on((`p`.`product_id` = `oi`.`product_id`))) join `orders` `o` on((`oi`.`order_id` = `o`.`order_id`))) where (`o`.`order_status` = 'completed') group by `p`.`product_id`,`p`.`product_name`,`p`.`category` order by `total_revenue` desc;

-- Dumping structure for view store_management.user_performance
DROP VIEW IF EXISTS `user_performance`;
-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `user_performance`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `user_performance` AS select `u`.`user_id` AS `user_id`,`u`.`username` AS `username`,`u`.`full_name` AS `full_name`,`u`.`role` AS `role`,count(`o`.`order_id`) AS `total_orders`,sum(`o`.`total_amount`) AS `total_sales` from (`users` `u` left join `orders` `o` on(((`u`.`user_id` = `o`.`created_by`) and (`o`.`order_status` = 'completed')))) group by `u`.`user_id`,`u`.`username`,`u`.`full_name`,`u`.`role`;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
