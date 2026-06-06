/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.14-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: edts_local
-- ------------------------------------------------------
-- Server version	10.11.14-MariaDB-0ubuntu0.24.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admins`
--

-- DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `admins` (
  `id_admin` int(11) NOT NULL AUTO_INCREMENT,
  `email_admin` text DEFAULT NULL,
  `password_admin` text DEFAULT NULL,
  `rol_admin` text DEFAULT NULL,
  `id_role` int(10) unsigned DEFAULT NULL,
  `token_admin` text DEFAULT NULL,
  `token_exp_admin` text DEFAULT NULL,
  `status_admin` int(11) DEFAULT 1,
  `date_created_admin` date DEFAULT NULL,
  `date_updated_admin` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_admin`)
) ENGINE=InnoDB AUTO_INCREMENT=102 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `audit_logs`
--

-- DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id_audit` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `action_audit` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(10) unsigned DEFAULT NULL,
  `old_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_data`)),
  `new_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_data`)),
  `created_at` timestamp(3) NOT NULL DEFAULT current_timestamp(3),
  PRIMARY KEY (`id_audit`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  KEY `idx_audit_admin` (`admin_id`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_action` (`action_audit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `customers`
--

-- DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `customers` (
  `id_customer` int(11) NOT NULL AUTO_INCREMENT,
  `name_customer` varchar(100) NOT NULL,
  `lastname_customer` varchar(100) NOT NULL,
  `phone_customer` varchar(20) DEFAULT NULL,
  `email_customer` varchar(150) DEFAULT NULL,
  `department_customer` varchar(100) DEFAULT NULL,
  `city_customer` varchar(100) DEFAULT NULL,
  `status_customer` tinyint(1) DEFAULT 1,
  `date_created_customer` timestamp NULL DEFAULT current_timestamp(),
  `date_updated_customer` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_customer`),
  UNIQUE KEY `phone_customer` (`phone_customer`),
  KEY `idx_email` (`email_customer`),
  KEY `idx_phone` (`phone_customer`)
) ENGINE=InnoDB AUTO_INCREMENT=23603 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `migrations`
--

-- DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(10) unsigned NOT NULL DEFAULT 1,
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_migration` (`migration`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payment_backup_tickets`
--

-- DROP TABLE IF EXISTS `payment_backup_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `payment_backup_tickets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_payment_backup` int(11) NOT NULL,
  `id_ticket` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pbt_ticket` (`id_ticket`),
  KEY `idx_pbt_backup` (`id_payment_backup`),
  CONSTRAINT `fk_pbt_backup` FOREIGN KEY (`id_payment_backup`) REFERENCES `payment_backups` (`id_payment_backup`) ON DELETE CASCADE,
  CONSTRAINT `fk_pbt_ticket` FOREIGN KEY (`id_ticket`) REFERENCES `tickets` (`id_ticket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payment_backups`
--

-- DROP TABLE IF EXISTS `payment_backups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `payment_backups` (
  `id_payment_backup` int(11) NOT NULL AUTO_INCREMENT,
  `code_payment_backup` varchar(50) NOT NULL,
  `id_raffle_payment_backup` int(11) NOT NULL,
  `id_customer_payment_backup` int(11) NOT NULL,
  `quantity_payment_backup` int(11) NOT NULL,
  `ticket_ids_payment_backup` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ticket_ids_payment_backup`)),
  `amount_payment_backup` decimal(12,2) NOT NULL,
  `currency_payment_backup` varchar(10) DEFAULT 'COP',
  `openpay_id_payment_backup` varchar(100) DEFAULT NULL,
  `openpay_status_payment_backup` varchar(30) DEFAULT 'pending',
  `openpay_response_payment_backup` longtext DEFAULT NULL,
  `status_payment_backup` tinyint(1) DEFAULT 1 COMMENT '1=pending,2=approved,3=rejected,4=cancelled',
  `expires_at_payment_backup` datetime DEFAULT NULL,
  `source_payment_backup` varchar(10) DEFAULT NULL,
  `date_created_payment_backup` timestamp NULL DEFAULT current_timestamp(),
  `date_updated_payment_backup` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_payment_backup`),
  UNIQUE KEY `uk_code_payment_backup` (`code_payment_backup`),
  KEY `fk_payment_backup_raffle` (`id_raffle_payment_backup`),
  KEY `fk_payment_backup_customer` (`id_customer_payment_backup`),
  KEY `code_payment_backup` (`code_payment_backup`),
  CONSTRAINT `fk_payment_backup_customer` FOREIGN KEY (`id_customer_payment_backup`) REFERENCES `customers` (`id_customer`) ON UPDATE CASCADE,
  CONSTRAINT `fk_payment_backup_raffle` FOREIGN KEY (`id_raffle_payment_backup`) REFERENCES `raffles` (`id_raffle`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `permissions`
--

-- DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `permissions` (
  `id_permission` int(11) NOT NULL AUTO_INCREMENT,
  `module_permission` varchar(50) NOT NULL,
  `action_permission` varchar(50) NOT NULL,
  `description_permission` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_permission`),
  UNIQUE KEY `uk_module_action` (`module_permission`,`action_permission`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `raffles`
--

-- DROP TABLE IF EXISTS `raffles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `raffles` (
  `id_raffle` int(11) NOT NULL AUTO_INCREMENT,
  `title_raffle` varchar(255) NOT NULL COMMENT 'Título comercial de la rifa',
  `description_raffle` text DEFAULT NULL COMMENT 'Detalles del premio',
  `price_raffle` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Precio por cada número',
  `digits_raffle` int(11) NOT NULL DEFAULT 4 COMMENT 'Define si es de 2, 3, 4 o 5 cifras',
  `date_raffle` datetime NOT NULL COMMENT 'Fecha y hora del sorteo',
  `status_raffle` int(11) NOT NULL DEFAULT 1 COMMENT '1: Activa, 0: Inactiva/Finalizada',
  `type_raffle` enum('manual','automatic') NOT NULL DEFAULT 'automatic',
  `min_quantity_raffle` int(10) unsigned NOT NULL DEFAULT 1,
  `sales_blocked_raffle` tinyint(1) NOT NULL DEFAULT 0,
  `hidden_raffle` tinyint(1) NOT NULL DEFAULT 0,
  `reservation_minutes_raffle` int(10) unsigned NOT NULL DEFAULT 15,
  `date_created_raffle` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_updated_raffle` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_raffle`),
  KEY `idx_raffle_status` (`status_raffle`,`hidden_raffle`,`sales_blocked_raffle`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_permissions`
--

-- DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id_role` int(11) NOT NULL,
  `id_permission` int(11) NOT NULL,
  PRIMARY KEY (`id_role`,`id_permission`),
  KEY `fk_rp_permission` (`id_permission`),
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`id_permission`) REFERENCES `permissions` (`id_permission`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`id_role`) REFERENCES `roles` (`id_role`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roles`
--

-- DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `roles` (
  `id_role` int(11) NOT NULL AUTO_INCREMENT,
  `name_role` varchar(50) NOT NULL,
  `slug_role` varchar(50) NOT NULL,
  `description_role` varchar(255) DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_role`),
  UNIQUE KEY `uk_slug` (`slug_role`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sale_items`
--

-- DROP TABLE IF EXISTS `sale_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `sale_items` (
  `id_sale_item` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_sale` int(11) NOT NULL,
  `id_ticket` int(11) NOT NULL,
  `number_ticket` varchar(10) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status_item` enum('active','cancelled') NOT NULL DEFAULT 'active',
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_sale_item`),
  UNIQUE KEY `uk_si_ticket` (`id_ticket`),
  KEY `idx_si_sale` (`id_sale`),
  CONSTRAINT `fk_si_sale` FOREIGN KEY (`id_sale`) REFERENCES `sales` (`id_sale`) ON DELETE CASCADE,
  CONSTRAINT `fk_si_ticket` FOREIGN KEY (`id_ticket`) REFERENCES `tickets` (`id_ticket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sales`
--

-- DROP TABLE IF EXISTS `sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `sales` (
  `id_sale` int(11) NOT NULL AUTO_INCREMENT,
  `id_customer_sale` int(11) NOT NULL,
  `id_raffle_sale` int(11) NOT NULL,
  `code_sale` varchar(20) NOT NULL,
  `quantity_sale` int(11) NOT NULL,
  `total_sale` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_method_sale` varchar(50) DEFAULT 'Efectivo',
  `status_sale` int(11) NOT NULL DEFAULT 1 COMMENT '1: Pagada, 0: Anulada',
  `id_admin_sale` int(11) DEFAULT NULL,
  `source_sale` varchar(10) DEFAULT NULL,
  `date_created_sale` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_updated_sale` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cancelled_at_sale` datetime DEFAULT NULL,
  `cancelled_by_sale` int(11) DEFAULT NULL,
  `cancellation_type_sale` enum('none','total','partial') NOT NULL DEFAULT 'none',
  `notes_sale` text DEFAULT NULL,
  PRIMARY KEY (`id_sale`),
  UNIQUE KEY `idx_code_sale_unique` (`code_sale`),
  KEY `fk_sales_customers` (`id_customer_sale`),
  KEY `fk_sales_raffles` (`id_raffle_sale`),
  KEY `idx_sales_created` (`date_created_sale`),
  KEY `idx_sales_raffle_created` (`id_raffle_sale`,`date_created_sale`),
  KEY `idx_sales_payment` (`payment_method_sale`),
  CONSTRAINT `fk_sales_customers` FOREIGN KEY (`id_customer_sale`) REFERENCES `customers` (`id_customer`) ON UPDATE CASCADE,
  CONSTRAINT `fk_sales_raffles` FOREIGN KEY (`id_raffle_sale`) REFERENCES `raffles` (`id_raffle`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `saved_reports`
--

-- DROP TABLE IF EXISTS `saved_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `saved_reports` (
  `id_saved_report` int(11) NOT NULL AUTO_INCREMENT,
  `name_report` varchar(180) NOT NULL,
  `spec_report` longtext NOT NULL COMMENT 'JSON constructor visual',
  `id_admin_created` int(11) DEFAULT NULL,
  `date_created_report` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_updated_report` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_saved_report`),
  KEY `idx_saved_reports_admin` (`id_admin_created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `settings`
--

-- DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `settings` (
  `id_setting` int(11) NOT NULL AUTO_INCREMENT,
  `key_setting` varchar(100) DEFAULT NULL,
  `value_setting` text DEFAULT NULL,
  `date_created_setting` date DEFAULT NULL,
  `date_updated_setting` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_setting`),
  UNIQUE KEY `key_setting` (`key_setting`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `site_images`
--

-- DROP TABLE IF EXISTS `site_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `site_images` (
  `id_site_image` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `key_image` varchar(100) NOT NULL,
  `url_image` varchar(500) NOT NULL,
  `fallback_url` varchar(500) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_site_image`),
  UNIQUE KEY `uk_key_image` (`key_image`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tickets`
--

-- DROP TABLE IF EXISTS `tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `tickets` (
  `id_ticket` int(11) NOT NULL AUTO_INCREMENT,
  `number_ticket` varchar(10) NOT NULL,
  `status_ticket` int(11) NOT NULL DEFAULT 0,
  `expires_at_ticket` datetime DEFAULT NULL,
  `is_winner_ticket` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = ganador',
  `is_premium_ticket` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = premium',
  `id_raffle_ticket` int(11) NOT NULL,
  `id_customer_ticket` int(11) DEFAULT NULL,
  `id_sale_ticket` int(11) DEFAULT NULL,
  `date_created_ticket` timestamp NULL DEFAULT current_timestamp(),
  `date_updated_ticket` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_ticket`),
  UNIQUE KEY `idx_unique_number_raffle` (`number_ticket`,`id_raffle_ticket`),
  UNIQUE KEY `uk_raffle_number` (`id_raffle_ticket`,`number_ticket`),
  KEY `fk_tickets_raffles` (`id_raffle_ticket`),
  KEY `fk_tickets_customers` (`id_customer_ticket`),
  KEY `fk_tickets_sales` (`id_sale_ticket`),
  KEY `idx_tickets_raffle_status` (`id_raffle_ticket`,`status_ticket`),
  KEY `idx_ticket_raffle_status` (`id_raffle_ticket`,`status_ticket`,`expires_at_ticket`),
  KEY `idx_ticket_expires` (`status_ticket`,`expires_at_ticket`),
  CONSTRAINT `fk_tickets_customers` FOREIGN KEY (`id_customer_ticket`) REFERENCES `customers` (`id_customer`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_tickets_raffles` FOREIGN KEY (`id_raffle_ticket`) REFERENCES `raffles` (`id_raffle`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tickets_sales` FOREIGN KEY (`id_sale_ticket`) REFERENCES `sales` (`id_sale`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10001 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transfers`
--

-- DROP TABLE IF EXISTS `transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `transfers` (
  `id_transfer` int(11) NOT NULL AUTO_INCREMENT,
  `code_transfer` varchar(100) NOT NULL,
  `id_raffle_transfer` int(11) NOT NULL,
  `id_customer_transfer` int(11) NOT NULL,
  `quantity_transfer` int(11) NOT NULL,
  `ticket_ids_transfer` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ticket_ids_transfer`)),
  `expires_at_transfer` datetime DEFAULT NULL,
  `amount_transfer` decimal(10,2) NOT NULL,
  `currency_transfer` varchar(10) DEFAULT 'COP',
  `url_transfer` text DEFAULT NULL,
  `status_transfer` tinyint(1) DEFAULT 1,
  `source_transfer` varchar(50) DEFAULT NULL,
  `date_created_transfer` datetime DEFAULT current_timestamp(),
  `date_updated_transfer` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_transfer`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `webhook_events`
--

-- DROP TABLE IF EXISTS `webhook_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `webhook_events` (
  `id_webhook` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid_webhook` char(36) NOT NULL,
  `source_webhook` varchar(50) NOT NULL DEFAULT 'openpay',
  `event_type_webhook` varchar(100) DEFAULT NULL,
  `payload_webhook` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload_webhook`)),
  `status_webhook` enum('pending','processing','processed','error') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp(3) NOT NULL DEFAULT current_timestamp(3),
  PRIMARY KEY (`id_webhook`),
  UNIQUE KEY `uk_uuid` (`uuid_webhook`),
  KEY `idx_webhook_status` (`status_webhook`),
  KEY `idx_webhook_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-29 22:55:02
