/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `acc_ap_pay_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_ap_pay_det` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `inv_id` int NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_apd_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `acc_ap_pay_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_ap_pay_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `ven_id` int NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `user_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `acc_ar_rec_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_ar_rec_det` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `inv_id` int NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ard_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `acc_ar_rec_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_ar_rec_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `cus_id` int NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `user_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `acc_coa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_coa` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `acc_group` varchar(30) NOT NULL COMMENT 'ASSET|LIABILITY|EQUITY|REVENUE|COGS|EXPENSE',
  `parent_id` int DEFAULT NULL,
  `postable` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `acc_journal_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_journal_det` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `coa_id` int NOT NULL,
  `debit` decimal(18,2) DEFAULT '0.00',
  `credit` decimal(18,2) DEFAULT '0.00',
  `memo` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_jd_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `acc_journal_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_journal_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `period` char(6) NOT NULL,
  `jrn_type` varchar(10) DEFAULT NULL,
  `ref_type` varchar(30) DEFAULT NULL,
  `ref_id` int DEFAULT NULL,
  `descrip` varchar(300) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|POSTED|REVERSED',
  `user_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_jrn_period` (`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `acc_period`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_period` (
  `id` int NOT NULL AUTO_INCREMENT,
  `period` char(6) NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'OPEN' COMMENT 'OPEN|CLOSED|LOCKED',
  PRIMARY KEY (`id`),
  UNIQUE KEY `period` (`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `approvals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `doc_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `doc_id` bigint unsigned NOT NULL,
  `level` tinyint NOT NULL,
  `required_role` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'permission key, e.g. po.approve',
  `status` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `acted_by` bigint unsigned DEFAULT NULL,
  `acted_at` datetime DEFAULT NULL,
  `note` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `approvals_doc_type_doc_id_index` (`doc_type`,`doc_id`),
  KEY `approvals_status_required_role_index` (`status`,`required_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ast_depre`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ast_depre` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ast_id` int NOT NULL,
  `period` char(6) NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `journal_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dep` (`ast_id`,`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ast_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ast_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `categ_id` int NOT NULL,
  `name` varchar(150) NOT NULL,
  `acq_date` date NOT NULL,
  `acq_cost` decimal(18,2) NOT NULL,
  `useful_life` smallint NOT NULL,
  `po_id` int DEFAULT NULL COMMENT 'asset dari pembelian',
  `gr_detail_id` int DEFAULT NULL,
  `machine_id` int DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'ACTIVE' COMMENT 'ACTIVE|DISPOSED|TRANSFERRED',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cst_cogm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cst_cogm` (
  `id` int NOT NULL AUTO_INCREMENT,
  `period` char(6) NOT NULL,
  `wo_id` int NOT NULL,
  `material_cost` decimal(18,2) DEFAULT '0.00',
  `labor_cost` decimal(18,2) DEFAULT '0.00',
  `foh_cost` decimal(18,2) DEFAULT '0.00',
  `subcont_cost` decimal(18,2) DEFAULT '0.00',
  `scrap_recovery` decimal(18,2) DEFAULT '0.00',
  `total` decimal(18,2) DEFAULT '0.00',
  `unit_cost` decimal(18,4) DEFAULT '0.0000',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cogm` (`period`,`wo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cst_rate`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cst_rate` (
  `id` int NOT NULL AUTO_INCREMENT,
  `period` char(6) NOT NULL,
  `rate_type` varchar(10) NOT NULL COMMENT 'LABOR|FOH',
  `process_id` int DEFAULT NULL,
  `rate_per_hour` decimal(18,4) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `doc_numberings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doc_numberings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `doc_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prefix` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `period` char(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_number` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `doc_numberings_doc_type_period_unique` (`doc_type`,`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `eng_ecn_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `eng_ecn_det` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `action` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UPDATE',
  `target_table` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` bigint unsigned DEFAULT NULL,
  `field` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_value` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_value` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `eng_ecn_det_main_id_index` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `eng_ecn_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `eng_ecn_main` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `change_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `npd_project_id` bigint unsigned DEFAULT NULL,
  `reason` varchar(400) COLLATE utf8mb4_unicode_ci NOT NULL,
  `impact` varchar(400) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `effective_date` date NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `user_id` bigint unsigned DEFAULT NULL,
  `applied_by` bigint unsigned DEFAULT NULL,
  `applied_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `eng_ecn_main_code_unique` (`code`),
  KEY `eng_ecn_main_status_effective_date_index` (`status`,`effective_date`),
  KEY `eng_ecn_main_item_id_index` (`item_id`),
  KEY `eng_ecn_main_npd_project_id_index` (`npd_project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `log_prc`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `log_prc` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code_tr` varchar(50) NOT NULL,
  `date` datetime NOT NULL,
  `user_id` int NOT NULL,
  `ip_user` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `hostname` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `action` varchar(300) NOT NULL,
  `created_at` timestamp NOT NULL,
  `updated_at` timestamp NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_asset_categ`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_asset_categ` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `useful_life` smallint NOT NULL DEFAULT '48' COMMENT 'bulan',
  `depr_method` varchar(15) NOT NULL DEFAULT 'STRAIGHT',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_bom`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_bom` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rev` smallint unsigned NOT NULL DEFAULT '0',
  `rev_date` date DEFAULT NULL,
  `item_id` int NOT NULL,
  `active` tinyint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'APPROVED',
  PRIMARY KEY (`id`),
  KEY `item_FG` (`item_id`) USING BTREE,
  KEY `m_bom_status_index` (`status`),
  CONSTRAINT `item_FG` FOREIGN KEY (`item_id`) REFERENCES `m_item` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_bom_det_pm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_bom_det_pm` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_prim` int NOT NULL,
  `pm_id` int NOT NULL,
  `qty` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pm_id` (`pm_id`) USING BTREE,
  KEY `id_prim` (`id_prim`) USING BTREE,
  CONSTRAINT `bom_main` FOREIGN KEY (`id_prim`) REFERENCES `m_bom` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `pm_id` FOREIGN KEY (`pm_id`) REFERENCES `m_item` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_bom_det_rm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_bom_det_rm` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_prim` int NOT NULL,
  `mat_id` int NOT NULL,
  `length_cut` double NOT NULL,
  `length_use` double NOT NULL,
  `priority` tinyint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `main_bom` (`id_prim`),
  KEY `mat_item` (`mat_id`),
  CONSTRAINT `main_bom` FOREIGN KEY (`id_prim`) REFERENCES `m_bom` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `mat_item` FOREIGN KEY (`mat_id`) REFERENCES `m_item` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_bom_pro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_bom_pro` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `process_main_id` int unsigned DEFAULT NULL,
  `priority` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `item_id` FOREIGN KEY (`item_id`) REFERENCES `m_item` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_bom_pro_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_bom_pro_det` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_prim` int NOT NULL,
  `proc_id` int NOT NULL,
  `sequence` tinyint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `main_bom_pro` (`id_prim`),
  KEY `m_process` (`proc_id`),
  CONSTRAINT `m_process` FOREIGN KEY (`proc_id`) REFERENCES `m_process` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `main_bom_pro` FOREIGN KEY (`id_prim`) REFERENCES `m_bom_pro` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_cont_categ`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_cont_categ` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_contacts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `u_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `initial` varchar(50) DEFAULT NULL,
  `nick_n` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `company_n` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `address` varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `phone` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `email` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `category_id` int NOT NULL DEFAULT '0',
  `identity` varchar(10) DEFAULT NULL,
  `npwp` varchar(25) DEFAULT NULL,
  `nik` varchar(20) DEFAULT NULL,
  `active` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'APPROVED',
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_code` (`u_code`),
  KEY `FK_contacts_cont_categ` (`category_id`) USING BTREE,
  KEY `m_contacts_status_index` (`status`),
  CONSTRAINT `FK_contacts_cont_categ` FOREIGN KEY (`category_id`) REFERENCES `m_cont_categ` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_currency`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_currency` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` char(3) NOT NULL,
  `name` varchar(50) NOT NULL,
  `is_base` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_defective`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_defective` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` varchar(15) DEFAULT 'PROCESS',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_fg_downgrade_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_fg_downgrade_map` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fg_item_id` bigint unsigned NOT NULL,
  `material_item_id` bigint unsigned NOT NULL,
  `note` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_function_m`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_function_m` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(150) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_holiday`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_holiday` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NASIONAL',
  `is_working` tinyint(1) NOT NULL DEFAULT '0',
  `hours` decimal(5,2) NOT NULL DEFAULT '0.00',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `m_holiday_date_name_unique` (`date`,`name`),
  KEY `m_holiday_date_index` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_i_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_i_category` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name_c` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_i_pm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_i_pm` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `active` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_inspection_param`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_inspection_param` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uom` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `method` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `m_inspection_param_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_item` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rev` smallint unsigned NOT NULL DEFAULT '0',
  `rev_date` date DEFAULT NULL,
  `code` varchar(50) NOT NULL,
  `part_name` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL,
  `shape` varchar(20) DEFAULT NULL,
  `lifecycle` varchar(10) NOT NULL DEFAULT 'MASSPRO',
  `descrip` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `category_id` int NOT NULL,
  `family_id` bigint unsigned DEFAULT NULL,
  `o_d` double(8,2) DEFAULT NULL,
  `i_d` double(8,2) DEFAULT NULL,
  `thick` double(8,2) DEFAULT NULL,
  `width` double(8,2) DEFAULT NULL,
  `height` double(8,2) DEFAULT NULL,
  `length` double(8,2) DEFAULT NULL,
  `length_cut` double(8,2) DEFAULT NULL,
  `weight` double(8,2) NOT NULL DEFAULT '0.00',
  `tolerance` varchar(50) DEFAULT NULL,
  `min_stock` int NOT NULL,
  `max_stock` int NOT NULL,
  `moq` int unsigned NOT NULL DEFAULT '0',
  `order_lot` int unsigned NOT NULL DEFAULT '0',
  `lead_time_days` smallint unsigned NOT NULL DEFAULT '0',
  `pm` tinyint NOT NULL DEFAULT '0',
  `active` tinyint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'APPROVED',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `categ` (`category_id`),
  KEY `m_item_status_index` (`status`),
  KEY `m_item_lifecycle_index` (`lifecycle`),
  KEY `m_item_family_id_index` (`family_id`),
  CONSTRAINT `categ` FOREIGN KEY (`category_id`) REFERENCES `m_i_category` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_item_customer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_item_customer` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `cus_id` int NOT NULL COMMENT 'ref m_contacts',
  `priority` int NOT NULL DEFAULT '0',
  `active` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ic` (`item_id`,`cus_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_item_inspection`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_item_inspection` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned NOT NULL,
  `param_id` bigint unsigned NOT NULL,
  `nominal` decimal(14,4) DEFAULT NULL,
  `min_value` decimal(14,4) DEFAULT NULL,
  `max_value` decimal(14,4) DEFAULT NULL,
  `mandatory` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `m_item_inspection_item_id_param_id_unique` (`item_id`,`param_id`),
  KEY `m_item_inspection_item_id_index` (`item_id`),
  KEY `m_item_inspection_param_id_index` (`param_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_machine`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_machine` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(50) NOT NULL,
  `model` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `categ` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `line_id` bigint unsigned DEFAULT NULL,
  `maker_id` int NOT NULL,
  `min_d` double NOT NULL,
  `max_d` double NOT NULL,
  `func_id` int NOT NULL,
  `serial` varchar(50) NOT NULL,
  `y_made` year NOT NULL,
  `etd` date NOT NULL,
  `pic_jp_id` int NOT NULL,
  `pic_local_id` int NOT NULL,
  `book_y_local` date NOT NULL,
  `asset_id` varchar(50) DEFAULT NULL,
  `deps_m` bigint NOT NULL,
  `deps_exp` date NOT NULL,
  `kwh` double NOT NULL,
  `daily_hours` decimal(5,2) NOT NULL DEFAULT '0.00',
  `active` tinyint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `m_machine_line_id_index` (`line_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_maker_m`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_maker_m` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `address` varchar(150) DEFAULT NULL,
  `active` tinyint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_p_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_p_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name_ty` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_pal_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_pal_item` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_pal_item_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_pal_item_det` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_prim` int NOT NULL,
  `pal_id` int NOT NULL,
  `qty` int NOT NULL,
  `priority` tinyint NOT NULL,
  `created_at` timestamp NOT NULL,
  `updated_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `pal_id` (`pal_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_pallet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_pallet` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(6) NOT NULL,
  `name_pa` varchar(50) NOT NULL,
  `type_id` int NOT NULL,
  `size` double NOT NULL,
  `cap_kg` double NOT NULL,
  `cap_m3` double NOT NULL,
  `note` varchar(50) NOT NULL,
  `active` tinyint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `code` (`code`) USING BTREE,
  KEY `type_id` (`type_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_pic`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_pic` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pic_id` int NOT NULL,
  `type` varchar(5) NOT NULL,
  `active` tinyint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_pricelist_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_pricelist_det` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `item_id` int NOT NULL,
  `price` decimal(18,4) NOT NULL,
  `currency_id` int NOT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date NOT NULL,
  `min_qty` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_pl_item` (`item_id`,`valid_from`,`valid_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_pricelist_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_pricelist_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `cus_id` int DEFAULT NULL COMMENT 'null=umum',
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_process`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_process` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name_p` varchar(50) NOT NULL,
  `descript` varchar(100) DEFAULT NULL,
  `active` tinyint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_process_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_process_main` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `rev` smallint unsigned NOT NULL DEFAULT '0',
  `rev_date` date DEFAULT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'APPROVED',
  PRIMARY KEY (`id`),
  UNIQUE KEY `m_process_main_code_unique` (`code`),
  KEY `m_process_main_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_process_main_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_process_main_det` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `main_id` int unsigned NOT NULL,
  `proc_id` int unsigned NOT NULL,
  `sequence` tinyint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `m_process_main_det_main_id_index` (`main_id`),
  KEY `m_process_main_det_proc_id_index` (`proc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_product_family`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_product_family` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descrip` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `m_product_family_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_production_line`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_production_line` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descrip` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `daily_hours` decimal(6,2) NOT NULL DEFAULT '16.00',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `m_production_line_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_quota`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_quota` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL COMMENT 'kode kuota/PI',
  `descrip` varchar(300) DEFAULT NULL,
  `hs_code` varchar(20) DEFAULT NULL,
  `total_ton` decimal(12,3) NOT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date NOT NULL,
  `active` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'APPROVED',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `m_quota_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_quota_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_quota_item` (
  `id` int NOT NULL AUTO_INCREMENT,
  `quota_id` int NOT NULL,
  `item_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_qi` (`quota_id`,`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_rack`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_rack` (
  `id` int NOT NULL AUTO_INCREMENT,
  `location` varchar(50) NOT NULL,
  `descriptions` varchar(50) DEFAULT NULL,
  `height` double NOT NULL,
  `width` double NOT NULL,
  `area` double NOT NULL,
  `rem_rack` tinyint NOT NULL,
  `active` tinyint NOT NULL,
  `depth` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_rate`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_rate` (
  `id` int NOT NULL AUTO_INCREMENT,
  `currency_id` int NOT NULL,
  `rate_type` varchar(15) NOT NULL COMMENT 'TRANSACTION|KMK',
  `valid_date` date NOT NULL,
  `rate` decimal(15,6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rate` (`currency_id`,`rate_type`,`valid_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_region`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_region` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '0',
  `created_at` varchar(50) NOT NULL DEFAULT '0',
  `updated_at` varchar(50) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_route_time`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_route_time` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int unsigned NOT NULL,
  `proc_id` int unsigned NOT NULL,
  `machine_id` int unsigned DEFAULT NULL,
  `cycle_sec` double NOT NULL DEFAULT '0',
  `setup_min` double NOT NULL DEFAULT '0',
  `priority` tinyint unsigned NOT NULL DEFAULT '1',
  `active` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `m_route_time_item_id_index` (`item_id`),
  KEY `m_route_time_item_id_proc_id_index` (`item_id`,`proc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_shift`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_shift` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(5) NOT NULL,
  `name` varchar(10) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_subcont_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_subcont_item` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ven_id` int unsigned NOT NULL,
  `item_id` int unsigned NOT NULL,
  `process_id` int unsigned DEFAULT NULL,
  `price` decimal(18,2) NOT NULL DEFAULT '0.00',
  `active` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subcont_vi` (`ven_id`,`item_id`,`process_id`),
  KEY `m_subcont_item_ven_id_index` (`ven_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_supplier_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_supplier_item` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ven_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `priority` smallint unsigned NOT NULL DEFAULT '1',
  `price` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `currency_id` bigint unsigned DEFAULT NULL,
  `moq` int unsigned NOT NULL DEFAULT '0',
  `order_lot` int unsigned NOT NULL DEFAULT '0',
  `lead_time_days` smallint unsigned NOT NULL DEFAULT '0',
  `supplier_part_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quot_det_id` bigint unsigned DEFAULT NULL,
  `contract_id` bigint unsigned DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `m_supplier_item_ven_id_item_id_unique` (`ven_id`,`item_id`),
  KEY `m_supplier_item_ven_id_index` (`ven_id`),
  KEY `m_supplier_item_item_id_index` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_tax`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_tax` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `rate_pct` decimal(8,4) NOT NULL COMMENT '12.0000',
  `dpp_factor` decimal(8,6) NOT NULL DEFAULT '1.000000' COMMENT '11/12 utk non-mewah (PMK 131/2024)',
  `is_luxury` tinyint NOT NULL DEFAULT '0',
  `effective_from` date NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_team`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_team` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descript` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_uom`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_uom` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(10) NOT NULL,
  `name` varchar(50) NOT NULL,
  `uom_type` varchar(15) DEFAULT 'QTY',
  `active` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_whs_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_whs_item` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `whs_type` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categ` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uom_id` bigint unsigned DEFAULT NULL,
  `brand` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spec` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rack_loc` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `min_stock` int NOT NULL DEFAULT '0',
  `max_stock` int NOT NULL DEFAULT '0',
  `standard_cost` decimal(18,2) NOT NULL DEFAULT '0.00',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `m_whs_item_code_unique` (`code`),
  KEY `m_whs_item_whs_type_active_index` (`whs_type`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `m_work_calendar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `m_work_calendar` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `is_working` tinyint(1) NOT NULL DEFAULT '1',
  `hours` decimal(5,2) NOT NULL DEFAULT '16.00',
  `note` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `m_work_calendar_date_unique` (`date`),
  KEY `m_work_calendar_date_is_working_index` (`date`,`is_working`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menus` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '0',
  `link` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT '0',
  `parent_id` int NOT NULL,
  `sort` smallint unsigned NOT NULL DEFAULT '0',
  `icon` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mes_exec`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mes_exec` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `wo_id` int unsigned NOT NULL,
  `seq` tinyint unsigned NOT NULL,
  `proc_id` int unsigned NOT NULL,
  `machine_id` int unsigned DEFAULT NULL,
  `pallet_id` bigint unsigned DEFAULT NULL,
  `in_type` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `in_ref` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date NOT NULL,
  `shift` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operator` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty_good` int NOT NULL DEFAULT '0',
  `qty_ng` int NOT NULL DEFAULT '0',
  `length_used` double NOT NULL DEFAULT '0',
  `note` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mes_exec_wo_id_seq_index` (`wo_id`,`seq`),
  KEY `mes_exec_machine_id_date_index` (`machine_id`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mes_oplog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mes_oplog` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `method` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'POST',
  `url` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `table_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `row_id` bigint unsigned DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OK',
  `error` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mes_oplog_client_uuid_unique` (`client_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mes_pallet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mes_pallet` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `wo_id` int unsigned NOT NULL,
  `item_id` int unsigned NOT NULL,
  `seq` tinyint unsigned NOT NULL DEFAULT '0',
  `proc_id` int unsigned DEFAULT NULL,
  `qty` int NOT NULL DEFAULT '0',
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OPEN',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mes_pallet_code_unique` (`code`),
  KEY `mes_pallet_wo_id_status_index` (`wo_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `npd_bom_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `npd_bom_det` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `new_item_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_item_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RM',
  `qty` decimal(12,3) NOT NULL DEFAULT '1.000',
  `length_use` decimal(12,2) DEFAULT NULL,
  `uom_id` bigint unsigned DEFAULT NULL,
  `ven_id` bigint unsigned DEFAULT NULL,
  `unit_cost` decimal(18,2) NOT NULL DEFAULT '0.00',
  `cost_source` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MANUAL',
  `note` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `npd_bom_det_main_id_index` (`main_id`),
  KEY `npd_bom_det_item_id_index` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `npd_bom_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `npd_bom_main` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `version` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'v1',
  `status` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `effective_date` date DEFAULT NULL,
  `note` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `npd_bom_main_main_id_version_unique` (`main_id`,`version`),
  KEY `npd_bom_main_main_id_index` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `npd_cost_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `npd_cost_det` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `cost_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `proc_id` bigint unsigned DEFAULT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `descrip` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty` decimal(12,3) NOT NULL DEFAULT '1.000',
  `cycle_sec` decimal(10,2) DEFAULT NULL,
  `rate` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `note` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `npd_cost_det_main_id_index` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `npd_cost_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `npd_cost_main` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `bom_id` bigint unsigned DEFAULT NULL,
  `version` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'v1',
  `period` char(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `material_cost` decimal(18,2) NOT NULL DEFAULT '0.00',
  `process_cost` decimal(18,2) NOT NULL DEFAULT '0.00',
  `tooling_cost` decimal(18,2) NOT NULL DEFAULT '0.00',
  `overhead` decimal(18,2) NOT NULL DEFAULT '0.00',
  `margin_pct` decimal(6,2) NOT NULL DEFAULT '0.00',
  `total_cost` decimal(18,2) NOT NULL DEFAULT '0.00',
  `quoted_price` decimal(18,2) NOT NULL DEFAULT '0.00',
  `status` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `approved_by` bigint unsigned DEFAULT NULL,
  `pricelist_det_id` bigint unsigned DEFAULT NULL,
  `note` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `npd_cost_main_main_id_version_unique` (`main_id`,`version`),
  KEY `npd_cost_main_main_id_index` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `npd_cp_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `npd_cp_det` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `seq` smallint unsigned NOT NULL DEFAULT '1',
  `proc_id` bigint unsigned DEFAULT NULL,
  `param_id` bigint unsigned DEFAULT NULL,
  `nominal` decimal(12,4) DEFAULT NULL,
  `min_value` decimal(12,4) DEFAULT NULL,
  `max_value` decimal(12,4) DEFAULT NULL,
  `method` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sample_size` smallint unsigned NOT NULL DEFAULT '1',
  `frequency` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `control_method` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reaction_plan` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fmea_det_id` bigint unsigned DEFAULT NULL,
  `to_item_inspection` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `npd_cp_det_main_id_index` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `npd_cp_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `npd_cp_main` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `revision` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rev A',
  `cp_type` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PROTOTYPE',
  `date` date NOT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `npd_cp_main_code_unique` (`code`),
  KEY `npd_cp_main_main_id_index` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `npd_deliverable`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `npd_deliverable` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `std_id` bigint unsigned DEFAULT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OPEN',
  `resp_user_id` bigint unsigned DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `submitted_date` date DEFAULT NULL,
  `doc_id` bigint unsigned DEFAULT NULL,
  `note` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `npd_deliverable_main_id_index` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `npd_deliverable_std`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `npd_deliverable_std` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `phase_id` bigint unsigned NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mandatory` tinyint(1) NOT NULL DEFAULT '1',
  `sort` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `npd_deliverable_std_phase_id_code_unique` (`phase_id`,`code`),
  KEY `npd_deliverable_std_phase_id_index` (`phase_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `npd_doc`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `npd_doc` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `ref_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref_id` bigint unsigned DEFAULT NULL,
  `doc_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OTHER',
  `file_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size_kb` int unsigned NOT NULL DEFAULT '0',
  `version` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rev A',
  `is_current` tinyint(1) NOT NULL DEFAULT '1',
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `npd_doc_ref_type_ref_id_index` (`ref_type`,`ref_id`),
  KEY `npd_doc_main_id_index` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `npd_feasibility`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `npd_feasibility` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rfq_id` bigint unsigned NOT NULL,
  `main_id` bigint unsigned DEFAULT NULL,
  `tech_ok` tinyint(1) NOT NULL DEFAULT '0',
  `capacity_ok` tinyint(1) NOT NULL DEFAULT '0',
  `cost_ok` tinyint(1) NOT NULL DEFAULT '0',
  `material_avail` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conclusion` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CONDITIONAL',
  `note` varchar(400) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `evaluated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `npd_feasibility_rfq_id_index` (`rfq_id`),
  KEY `npd_feasibility_main_id_index` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `npd_fmea_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `npd_fmea_det` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `proc_id` bigint unsigned DEFAULT NULL,
  `item_function` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `failure_mode` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `effect` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` tinyint unsigned NOT NULL,
  `cause` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `occurrence` tinyint unsigned NOT NULL,
  `current_control` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detection` tinyint unsigned NOT NULL,
  `rpn` smallint unsigned NOT NULL,
  `recommended_action` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_taken` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resp_user_id` bigint unsigned DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OPEN',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `npd_fmea_det_main_id_rpn_index` (`main_id`,`rpn`),
  KEY `npd_fmea_det_main_id_index` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `npd_fmea_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `npd_fmea_main` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `fmea_type` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `revision` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rev A',
  `team` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date NOT NULL,
  `rpn_threshold` smallint unsigned NOT NULL DEFAULT '100',
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `npd_fmea_main_code_unique` (`code`),
  KEY `npd_fmea_main_main_id_fmea_type_index` (`main_id`,`fmea_type`),
  KEY `npd_fmea_main_main_id_index` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `npd_member`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `npd_member` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `role` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VIEWER',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `npd_member_main_id_user_id_unique` (`main_id`,`user_id`),
  KEY `npd_member_main_id_index` (`main_id`),
  KEY `npd_member_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `npd_milestone`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `npd_milestone` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `planned_date` date NOT NULL,
  `actual_date` date DEFAULT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PLANNED',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `npd_milestone_main_id_index` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `npd_phase`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `npd_phase` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `phase_no` tinyint unsigned NOT NULL,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descrip` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `npd_phase_phase_no_unique` (`phase_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `npd_ppap_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `npd_ppap_det` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `std_id` bigint unsigned NOT NULL,
  `status` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OPEN',
  `doc_id` bigint unsigned DEFAULT NULL,
  `auto_source` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `npd_ppap_det_main_id_std_id_unique` (`main_id`,`std_id`),
  KEY `npd_ppap_det_main_id_index` (`main_id`),
  KEY `npd_ppap_det_std_id_index` (`std_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `npd_ppap_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `npd_ppap_main` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ppap_level` tinyint unsigned NOT NULL DEFAULT '3',
  `psw_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submission_date` date DEFAULT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `approval_date` date DEFAULT NULL,
  `customer_pic` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(400) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `npd_ppap_main_code_unique` (`code`),
  KEY `npd_ppap_main_main_id_status_index` (`main_id`,`status`),
  KEY `npd_ppap_main_main_id_index` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `npd_ppap_std`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `npd_ppap_std` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `element_no` tinyint unsigned NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descrip` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `level_required` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '3,5',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `npd_ppap_std_element_no_unique` (`element_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `npd_project`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `npd_project` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cus_id` bigint unsigned NOT NULL,
  `parent_item_id` bigint unsigned DEFAULT NULL,
  `part_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `drawing_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `project_type` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NEW',
  `current_phase_no` tinyint unsigned NOT NULL DEFAULT '1',
  `status` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `target_sop` date DEFAULT NULL,
  `pm_user_id` bigint unsigned DEFAULT NULL,
  `priority` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NORMAL',
  `item_id` bigint unsigned DEFAULT NULL,
  `bom_id` bigint unsigned DEFAULT NULL,
  `process_main_id` bigint unsigned DEFAULT NULL,
  `handover_date` date DEFAULT NULL,
  `note` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `npd_project_code_unique` (`code`),
  KEY `npd_project_status_current_phase_no_index` (`status`,`current_phase_no`),
  KEY `npd_project_cus_id_index` (`cus_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `npd_project_phase`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `npd_project_phase` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `phase_id` bigint unsigned NOT NULL,
  `phase_no` tinyint unsigned NOT NULL,
  `planned_start` date DEFAULT NULL,
  `planned_end` date DEFAULT NULL,
  `actual_start` date DEFAULT NULL,
  `actual_end` date DEFAULT NULL,
  `status` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PLANNED',
  `pic_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `npd_project_phase_main_id_phase_no_unique` (`main_id`,`phase_no`),
  KEY `npd_project_phase_main_id_index` (`main_id`),
  KEY `npd_project_phase_phase_id_index` (`phase_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `npd_rfq`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `npd_rfq` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned DEFAULT NULL,
  `cus_id` bigint unsigned NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `part_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `drawing_ref` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty` int NOT NULL DEFAULT '0',
  `target_price` decimal(18,2) NOT NULL DEFAULT '0.00',
  `due_date` date DEFAULT NULL,
  `status` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OPEN',
  `note` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `npd_rfq_main_id_index` (`main_id`),
  KEY `npd_rfq_cus_id_index` (`cus_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `npd_task`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `npd_task` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descrip` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_to` bigint unsigned DEFAULT NULL,
  `planned_start` date DEFAULT NULL,
  `planned_end` date DEFAULT NULL,
  `actual_start` date DEFAULT NULL,
  `actual_end` date DEFAULT NULL,
  `progress_pct` tinyint unsigned NOT NULL DEFAULT '0',
  `predecessor_id` bigint unsigned DEFAULT NULL,
  `status` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OPEN',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `npd_task_main_id_index` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `npd_trial_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `npd_trial_det` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `param_id` bigint unsigned NOT NULL,
  `sample_no` smallint unsigned NOT NULL DEFAULT '1',
  `nominal` decimal(12,4) DEFAULT NULL,
  `min_value` decimal(12,4) DEFAULT NULL,
  `max_value` decimal(12,4) DEFAULT NULL,
  `measured` decimal(12,4) NOT NULL,
  `judgement` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `instrument` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inspector_id` bigint unsigned DEFAULT NULL,
  `note` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `npd_trial_det_main_id_param_id_index` (`main_id`,`param_id`),
  KEY `npd_trial_det_main_id_index` (`main_id`),
  KEY `npd_trial_det_param_id_index` (`param_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `npd_trial_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `npd_trial_main` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `phase_id` bigint unsigned DEFAULT NULL,
  `wo_id` bigint unsigned NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trial_type` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PROTOTYPE',
  `date` date NOT NULL,
  `machine_id` bigint unsigned DEFAULT NULL,
  `planned_qty` int NOT NULL DEFAULT '0',
  `produced_qty` int NOT NULL DEFAULT '0',
  `ok_qty` int NOT NULL DEFAULT '0',
  `ng_qty` int NOT NULL DEFAULT '0',
  `conclusion` varchar(400) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `npd_trial_main_code_unique` (`code`),
  KEY `npd_trial_main_main_id_trial_type_index` (`main_id`,`trial_type`),
  KEY `npd_trial_main_main_id_index` (`main_id`),
  KEY `npd_trial_main_wo_id_index` (`wo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prc_contract_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prc_contract_det` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `price` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `moq` int unsigned NOT NULL DEFAULT '0',
  `order_lot` int unsigned NOT NULL DEFAULT '0',
  `lead_time_days` smallint unsigned NOT NULL DEFAULT '0',
  `commit_qty` int unsigned NOT NULL DEFAULT '0',
  `note` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prc_contract_det_main_id_item_id_unique` (`main_id`,`item_id`),
  KEY `prc_contract_det_main_id_index` (`main_id`),
  KEY `prc_contract_det_item_id_index` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prc_contract_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prc_contract_main` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `ven_id` bigint unsigned NOT NULL,
  `ref_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quot_id` bigint unsigned DEFAULT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date NOT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `note` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prc_contract_main_code_unique` (`code`),
  KEY `prc_contract_main_status_valid_to_index` (`status`,`valid_to`),
  KEY `prc_contract_main_ven_id_index` (`ven_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prc_cost_alloc`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prc_cost_alloc` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `gr_detail_id` int NOT NULL,
  `serial_id` varchar(50) DEFAULT NULL COMMENT 'ref prc_gr_serial.serial_id',
  `amount` decimal(18,2) NOT NULL,
  `unit_cost_kg` decimal(18,4) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pca_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prc_cost_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prc_cost_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `cost_type` varchar(30) NOT NULL COMMENT 'FREIGHT|INSURANCE|DUTY|VAT_IMPORT|PPH22|PIB|EMKL|FORWARDER|UNLOADING|TRUCKING|OTHER',
  `descrip` varchar(200) DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL,
  `currency_id` int DEFAULT NULL,
  `rate` decimal(15,6) DEFAULT '1.000000',
  `amount_idr` decimal(18,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pcd_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prc_cost_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prc_cost_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `po_id` int NOT NULL,
  `gr_id` int DEFAULT NULL,
  `inv_id` int DEFAULT NULL,
  `alloc_basis` varchar(15) NOT NULL DEFAULT 'WEIGHT',
  `pph22_base` decimal(18,2) NOT NULL DEFAULT '0.00',
  `pph22_rate` decimal(6,2) NOT NULL DEFAULT '0.00',
  `pph22_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `has_api` tinyint(1) NOT NULL DEFAULT '1',
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|FINAL|POSTED',
  `user_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prc_gr_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prc_gr_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_prim` int NOT NULL,
  `po_id` int DEFAULT NULL,
  `item_id` int NOT NULL,
  `quota_id` int DEFAULT NULL COMMENT 'kuota impor per baris',
  `hs_code` varchar(20) DEFAULT NULL COMMENT 'snapshot HS code saat GR',
  `qty` int DEFAULT NULL,
  `length` int DEFAULT NULL,
  `weight` double(8,2) DEFAULT NULL,
  `w_total` double(8,2) DEFAULT NULL,
  `note` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_prim` (`id_prim`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `item` FOREIGN KEY (`item_id`) REFERENCES `m_item` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `main` FOREIGN KEY (`id_prim`) REFERENCES `prc_gr_main` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prc_gr_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prc_gr_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `ven_id` int NOT NULL,
  `date` date NOT NULL,
  `user_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `po_no` varchar(255) NOT NULL,
  `import_doc_no` varchar(50) DEFAULT NULL COMMENT 'no PIB (impor)',
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|SERIAL_GENERATED|PRINTED|CHECKING|CONFIRMED|INVOICED',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `customer` (`ven_id`) USING BTREE,
  CONSTRAINT `customer` FOREIGN KEY (`ven_id`) REFERENCES `m_contacts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prc_gr_reject`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prc_gr_reject` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `gr_id` int NOT NULL,
  `ven_id` int NOT NULL,
  `item_id` int NOT NULL,
  `qty` int NOT NULL,
  `reason` varchar(300) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|RETURNED|CLAIMED',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prc_gr_serial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prc_gr_serial` (
  `id` int NOT NULL AUTO_INCREMENT,
  `det_id` int NOT NULL,
  `serial_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `millsheet` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `qty` int NOT NULL,
  `length` int NOT NULL,
  `weight` double(8,2) DEFAULT NULL COMMENT 'berat aktual kg (aktualisasi)',
  `status` varchar(15) NOT NULL DEFAULT 'GENERATED' COMMENT 'GENERATED|OK|NG|VOID',
  `ng_reason` varchar(200) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `det_id` (`det_id`),
  CONSTRAINT `detail` FOREIGN KEY (`det_id`) REFERENCES `prc_gr_detail` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prc_inv_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prc_inv_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `gr_detail_id` bigint unsigned DEFAULT NULL,
  `qty` int NOT NULL,
  `price` decimal(18,4) NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pid_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prc_inv_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prc_inv_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `ven_id` int NOT NULL,
  `po_id` int DEFAULT NULL,
  `inv_no` varchar(50) NOT NULL COMMENT 'no invoice VENDOR — diinput DI SINI, bukan di GR',
  `dpp` decimal(18,2) DEFAULT '0.00',
  `vat` decimal(18,2) DEFAULT '0.00',
  `wht23` decimal(18,2) DEFAULT '0.00' COMMENT 'PPh23 jasa/subcont',
  `wht23_code` varchar(10) DEFAULT NULL,
  `wht23_rate` decimal(6,2) NOT NULL DEFAULT '0.00',
  `total` decimal(18,2) DEFAULT '0.00',
  `tax_inv_no` varchar(30) DEFAULT NULL COMMENT 'faktur pajak masukan (Coretax)',
  `tax_inv_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `user_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|MATCHED|POSTED|PAID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prc_po_att`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prc_po_att` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `file_type` varchar(15) DEFAULT 'QUOTATION',
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(150) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_poa_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prc_po_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prc_po_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `pr_detail_id` int DEFAULT NULL,
  `item_id` int NOT NULL,
  `qty` int NOT NULL,
  `uom_id` int DEFAULT NULL,
  `price` decimal(18,4) NOT NULL,
  `price_kg` decimal(18,4) DEFAULT NULL,
  `est_weight_unit` double DEFAULT NULL,
  `est_length_unit` double DEFAULT NULL,
  `tax_id` int DEFAULT NULL,
  `est_weight` double(10,2) DEFAULT NULL COMMENT 'estimasi kg (reservasi kuota)',
  `est_length` double DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `qty_received` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_pod_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prc_po_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prc_po_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `po_type` varchar(15) NOT NULL COMMENT 'RM|GENERAL|NPD|SERVICE|SUBCONT|ASSET',
  `source` varchar(10) NOT NULL DEFAULT 'LOCAL' COMMENT 'LOCAL|IMPORT',
  `ven_id` int NOT NULL COMMENT 'ref m_contacts',
  `quota_id` int DEFAULT NULL COMMENT 'wajib bila IMPORT+RM',
  `currency_id` int DEFAULT NULL,
  `rate` decimal(15,6) DEFAULT '1.000000',
  `top_days` smallint DEFAULT '30',
  `eta` date DEFAULT NULL,
  `user_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_po_ven` (`ven_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prc_po_schedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prc_po_schedule` (
  `id` int NOT NULL AUTO_INCREMENT,
  `po_detail_id` int NOT NULL,
  `plan_date` date NOT NULL,
  `qty` int NOT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pos_det` (`po_detail_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prc_pr_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prc_pr_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `item_id` int NOT NULL,
  `ven_id` bigint unsigned DEFAULT NULL,
  `est_price` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `qty` int NOT NULL,
  `uom_id` int DEFAULT NULL,
  `need_date` date DEFAULT NULL,
  `wo_id` int DEFAULT NULL,
  `note` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_prd_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prc_pr_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prc_pr_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `pr_type` varchar(15) NOT NULL DEFAULT 'MANUAL' COMMENT 'MRP|ADDITIONAL|NON_RM|NPD',
  `user_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prc_quot_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prc_quot_det` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `price` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `moq` int unsigned NOT NULL DEFAULT '0',
  `order_lot` int unsigned NOT NULL DEFAULT '0',
  `lead_time_days` smallint unsigned NOT NULL DEFAULT '0',
  `note` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `selected` tinyint(1) NOT NULL DEFAULT '0',
  `selected_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prc_quot_det_main_id_item_id_unique` (`main_id`,`item_id`),
  KEY `prc_quot_det_main_id_index` (`main_id`),
  KEY `prc_quot_det_item_id_index` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prc_quot_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prc_quot_main` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `ven_id` bigint unsigned NOT NULL,
  `ref_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency_id` bigint unsigned DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `note` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prc_quot_main_code_unique` (`code`),
  KEY `prc_quot_main_status_date_index` (`status`,`date`),
  KEY `prc_quot_main_ven_id_index` (`ven_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prc_quota_txn`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prc_quota_txn` (
  `id` int NOT NULL AUTO_INCREMENT,
  `quota_id` int NOT NULL,
  `ref_type` varchar(15) NOT NULL COMMENT 'PO_RESERVE|GR_ACTUAL|RELEASE|ADJUST',
  `ref_id` int NOT NULL,
  `ton` decimal(12,3) NOT NULL,
  `sign` tinyint NOT NULL COMMENT '-1/+1',
  `note` varchar(200) DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_qt_quota` (`quota_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prd_crp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prd_crp` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mrp_id` int DEFAULT NULL,
  `basis` varchar(10) DEFAULT 'MPS',
  `process_id` int NOT NULL,
  `machine_id` int NOT NULL,
  `period` char(6) NOT NULL,
  `load_hours` double(10,2) NOT NULL,
  `capacity_hours` double(10,2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prd_cut_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prd_cut_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `wip_id` int NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `item_id` int NOT NULL,
  `wo_id` int NOT NULL,
  `operator` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `machine_id` int NOT NULL,
  `date` date NOT NULL,
  `shift` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prd_cut_serial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prd_cut_serial` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `serial_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `qty` int NOT NULL,
  `length_rem` double(8,2) NOT NULL DEFAULT '0.00',
  `finish` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prd_fcs_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prd_fcs_main` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `wo_id` bigint unsigned NOT NULL,
  `fg_item_id` bigint unsigned NOT NULL,
  `qty_planned` int NOT NULL DEFAULT '0',
  `qty_good` int NOT NULL DEFAULT '0',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `traceability` json DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prd_kanban`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prd_kanban` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `wo_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `rm_detail_id` bigint unsigned DEFAULT NULL,
  `qty_planned` int NOT NULL DEFAULT '0',
  `qty_issued` int NOT NULL DEFAULT '0',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `created_by` bigint unsigned DEFAULT NULL,
  `issued_by` bigint unsigned DEFAULT NULL,
  `issued_at` timestamp NULL DEFAULT NULL,
  `closed_by` bigint unsigned DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prd_kanban_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prd_mpp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prd_mpp` (
  `id` int NOT NULL AUTO_INCREMENT,
  `period` char(6) NOT NULL,
  `item_id` int NOT NULL,
  `plan_qty` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mpp` (`period`,`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prd_mps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prd_mps` (
  `id` int NOT NULL AUTO_INCREMENT,
  `plan_date` date NOT NULL,
  `item_id` int NOT NULL,
  `proc_id` int unsigned DEFAULT NULL,
  `qty` int NOT NULL,
  `machine_id` int DEFAULT NULL COMMENT 'alokasi prioritas mesin dari m_bom_pro',
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mps` (`plan_date`,`item_id`),
  KEY `prd_mps_item_id_proc_id_index` (`item_id`,`proc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prd_mps_resched`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prd_mps_resched` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mps_id` int unsigned NOT NULL,
  `from_date` date NOT NULL,
  `from_machine_id` int unsigned DEFAULT NULL,
  `to_date` date NOT NULL,
  `to_machine_id` int unsigned DEFAULT NULL,
  `reason` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `requested_by` int unsigned NOT NULL,
  `approved_by` int unsigned DEFAULT NULL,
  `decision_note` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decided_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prd_mps_resched_mps_id_status_index` (`mps_id`,`status`),
  KEY `prd_mps_resched_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prd_mrp_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prd_mrp_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `item_id` int NOT NULL,
  `period` char(6) NOT NULL,
  `gross_req` int DEFAULT '0',
  `demand_src` varchar(12) DEFAULT NULL,
  `demand_mpp` int NOT NULL DEFAULT '0',
  `demand_fc` int NOT NULL DEFAULT '0',
  `demand_so` int NOT NULL DEFAULT '0',
  `onhand` int DEFAULT '0',
  `open_po` int DEFAULT '0',
  `open_wo` int DEFAULT '0',
  `net_req` int DEFAULT '0',
  `net_req_kg` double(12,2) DEFAULT NULL COMMENT 'RM: cek saldo kuota',
  `suggestion` varchar(10) DEFAULT NULL COMMENT 'PR|WO|RESCHED',
  PRIMARY KEY (`id`),
  KEY `idx_mrpd_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prd_mrp_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prd_mrp_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `run_date` datetime NOT NULL,
  `user_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'QUEUED',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prd_scrap_decisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prd_scrap_decisions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `serial_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `wo_serial_rm_id` bigint unsigned NOT NULL,
  `wo_id` bigint unsigned DEFAULT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `length_rem` decimal(12,2) NOT NULL DEFAULT '0.00',
  `min_bom_length` decimal(12,2) DEFAULT NULL,
  `decision` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auto_flag` tinyint(1) NOT NULL DEFAULT '0',
  `decided_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prd_scrap_decisions_serial_id_index` (`serial_id`),
  KEY `prd_scrap_decisions_wo_serial_rm_id_index` (`wo_serial_rm_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prd_wip`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prd_wip` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `no_dp` varchar(40) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `wo_id` int NOT NULL,
  `item_id` int NOT NULL,
  `user_id` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prd_wip_no_dp_index` (`no_dp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prd_wo_detail_pm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prd_wo_detail_pm` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `code_tr` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `pm_id` int NOT NULL,
  `note` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wo_main_pm` (`main_id`),
  CONSTRAINT `wo_main_pm` FOREIGN KEY (`main_id`) REFERENCES `prd_wo_main` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prd_wo_detail_rm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prd_wo_detail_rm` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `rm_id` int NOT NULL,
  `note` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wo_main_rm` (`main_id`),
  CONSTRAINT `wo_main_rm` FOREIGN KEY (`main_id`) REFERENCES `prd_wo_main` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prd_wo_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prd_wo_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `wo_kind` varchar(12) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PROD',
  `npd_project_id` bigint unsigned DEFAULT NULL,
  `date` date NOT NULL,
  `customer_id` int NOT NULL,
  `so_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `fg_id` int NOT NULL,
  `process_main_id` int unsigned DEFAULT NULL,
  `mps_id` int DEFAULT NULL,
  `user_id` int NOT NULL,
  `qty` int NOT NULL,
  `status` int NOT NULL,
  `no_cut` tinyint DEFAULT NULL,
  `for_pm` tinyint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prd_wo_main_wo_kind_index` (`wo_kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prd_wo_serial_pm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prd_wo_serial_pm` (
  `id` int NOT NULL AUTO_INCREMENT,
  `detail_id` int NOT NULL,
  `serial_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `qty` int NOT NULL,
  `note` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL,
  `updated_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_wo_pm` (`detail_id`),
  CONSTRAINT `detail_wo_pm` FOREIGN KEY (`detail_id`) REFERENCES `prd_wo_detail_pm` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prd_wo_serial_rm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prd_wo_serial_rm` (
  `id` int NOT NULL AUTO_INCREMENT,
  `detail_id` int NOT NULL,
  `serial_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `length_asal` double(8,2) NOT NULL,
  `length_book` double(8,2) NOT NULL,
  `qty_per_serial` int NOT NULL,
  `length_rem` double(8,2) NOT NULL,
  `qty_serial` int NOT NULL,
  `scrap` tinyint NOT NULL,
  `version` int unsigned NOT NULL DEFAULT '0',
  `note` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_wo_rm` (`detail_id`),
  CONSTRAINT `detail_wo_rm` FOREIGN KEY (`detail_id`) REFERENCES `prd_wo_detail_rm` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `qc_incoming_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qc_incoming_det` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `param` varchar(100) NOT NULL,
  `standard` varchar(50) DEFAULT NULL,
  `actual` varchar(50) DEFAULT NULL,
  `judge` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_qid_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `qc_incoming_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qc_incoming_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `gr_id` int NOT NULL,
  `result` varchar(15) DEFAULT NULL COMMENT 'ACCEPT|REJECT|DEVIATION',
  `inspector_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sls_do_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sls_do_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `so_detail_id` int NOT NULL,
  `item_id` int NOT NULL,
  `qty` int NOT NULL,
  `fg_code` varchar(20) DEFAULT NULL COMMENT 'ref tr_inc_fg_det.code',
  PRIMARY KEY (`id`),
  KEY `idx_dod_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sls_do_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sls_do_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL COMMENT 'ref tr_out_fg_main.code_do',
  `date` date NOT NULL,
  `so_id` int NOT NULL,
  `user_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|PICKED|SHIPPED|RECEIVED|INVOICED',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sls_forecast`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sls_forecast` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cus_id` int NOT NULL,
  `item_id` int NOT NULL,
  `period` char(6) NOT NULL COMMENT 'YYYYMM',
  `version` varchar(10) NOT NULL DEFAULT 'FINAL',
  `qty` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'APPROVED',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fc` (`cus_id`,`item_id`,`period`,`version`),
  KEY `sls_forecast_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sls_inv_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sls_inv_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `do_detail_id` int NOT NULL,
  `item_id` int NOT NULL,
  `qty` int NOT NULL,
  `price` decimal(18,4) NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sid_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sls_inv_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sls_inv_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `cus_id` int NOT NULL,
  `dpp` decimal(18,2) DEFAULT '0.00',
  `dpp_nilai_lain` decimal(18,2) DEFAULT '0.00' COMMENT '11/12 x DPP (PMK 131/2024)',
  `vat` decimal(18,2) DEFAULT '0.00' COMMENT '12% x dpp_nilai_lain',
  `total` decimal(18,2) DEFAULT '0.00',
  `tax_inv_no` varchar(30) DEFAULT NULL COMMENT 'faktur pajak keluaran (Coretax)',
  `due_date` date DEFAULT NULL,
  `user_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sls_pack_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sls_pack_det` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `box_no` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `do_detail_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `qty` int NOT NULL,
  `net_weight` decimal(12,2) NOT NULL DEFAULT '0.00',
  `gross_weight` decimal(12,2) NOT NULL DEFAULT '0.00',
  `dimension` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sls_pack_det_main_id_index` (`main_id`),
  KEY `sls_pack_det_do_detail_id_index` (`do_detail_id`),
  KEY `sls_pack_det_item_id_index` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sls_pack_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sls_pack_main` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `do_id` bigint unsigned NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `note` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sls_pack_main_code_unique` (`code`),
  KEY `sls_pack_main_do_id_index` (`do_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sls_return`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sls_return` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `do_id` int NOT NULL,
  `item_id` int NOT NULL,
  `qty` int NOT NULL,
  `reason` varchar(300) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sls_ship_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sls_ship_det` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `do_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ship_do` (`do_id`),
  KEY `sls_ship_det_main_id_index` (`main_id`),
  KEY `sls_ship_det_do_id_index` (`do_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sls_ship_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sls_ship_main` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `carrier_id` bigint unsigned DEFAULT NULL,
  `vehicle_no` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `driver` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `driver_phone` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destination` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plan_depart` datetime DEFAULT NULL,
  `departed_at` datetime DEFAULT NULL,
  `arrived_at` datetime DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `note` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sls_ship_main_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sls_so_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sls_so_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `item_id` int NOT NULL,
  `po_detail_code` varchar(50) DEFAULT NULL,
  `qty` int NOT NULL,
  `price` decimal(18,4) NOT NULL,
  `pricelist_det_id` int DEFAULT NULL,
  `tax_id` int DEFAULT NULL,
  `pph_tax_id` int unsigned DEFAULT NULL,
  `local_mat` tinyint NOT NULL DEFAULT '0',
  `ppn` tinyint NOT NULL DEFAULT '0',
  `pph` tinyint NOT NULL DEFAULT '0',
  `dpp` decimal(18,2) NOT NULL DEFAULT '0.00',
  `ppn_value` decimal(18,2) NOT NULL DEFAULT '0.00',
  `pph_value` decimal(18,2) NOT NULL DEFAULT '0.00',
  `due_date` date DEFAULT NULL,
  `note` varchar(200) DEFAULT NULL,
  `qty_delivered` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_sod_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sls_so_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sls_so_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `cus_id` int NOT NULL COMMENT 'ref m_contacts; item wajib terdaftar di m_item_customer',
  `cus_po_no` varchar(50) DEFAULT NULL,
  `po_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `currency_id` int DEFAULT NULL,
  `user_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `note` varchar(200) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `status_id`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `status_id` (
  `id` int NOT NULL AUTO_INCREMENT,
  `status` varchar(12) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_check`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_check` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `date` datetime NOT NULL,
  `item_id` int NOT NULL,
  `rack_id` int NOT NULL,
  `length` int NOT NULL,
  `match` tinyint NOT NULL,
  `qty_data` int NOT NULL,
  `qty_actual` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sub_dn_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sub_dn_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `wo_id` int DEFAULT NULL,
  `item_id` int NOT NULL,
  `serial_id` varchar(50) DEFAULT NULL,
  `pallet_code` varchar(50) DEFAULT NULL,
  `qty` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sdd_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sub_dn_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sub_dn_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `po_id` int NOT NULL,
  `ven_id` int NOT NULL,
  `user_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|SENT|PARTIAL|COMPLETED',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sub_gr_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sub_gr_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `po_id` int NOT NULL,
  `dn_id` int DEFAULT NULL,
  `ven_dn_no` varchar(50) DEFAULT NULL,
  `qty_ok` int DEFAULT '0',
  `qty_ng` int DEFAULT '0',
  `user_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sub_po_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sub_po_detail` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `main_id` int unsigned NOT NULL,
  `item_id` int unsigned NOT NULL,
  `process_id` int unsigned DEFAULT NULL,
  `qty` int NOT NULL,
  `price` decimal(18,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `sub_po_detail_main_id_index` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sub_po_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sub_po_main` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `ven_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `note` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sub_po_main_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sub_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sub_progress` (
  `id` int NOT NULL AUTO_INCREMENT,
  `po_detail_id` int NOT NULL,
  `progress_pct` double(5,2) NOT NULL,
  `note` varchar(300) DEFAULT NULL,
  `user_id` int NOT NULL COMMENT 'user portal vendor',
  `reported_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sum_stock_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sum_stock_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sum_stock_pm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sum_stock_pm` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `item_id` int NOT NULL,
  `qty` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sum_stock_rm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sum_stock_rm` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `item_id` int NOT NULL,
  `qty` int NOT NULL,
  `length_serial` int NOT NULL,
  `length_total` int NOT NULL,
  `rack_id` int DEFAULT NULL,
  `weight_base` double(8,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sys_alert`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sys_alert` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'WARNING',
  `ref_type` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref_id` bigint unsigned DEFAULT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` varchar(400) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` decimal(18,3) DEFAULT NULL,
  `threshold` decimal(18,3) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_alert_condition` (`type`,`ref_type`,`ref_id`),
  KEY `sys_alert_resolved_at_severity_index` (`resolved_at`,`severity`),
  KEY `sys_alert_type_index` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_ab_cut_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_ab_cut_det` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `serial_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `qty` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_ab_cut_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_ab_cut_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `wip_id` int NOT NULL,
  `user_id` varchar(5) NOT NULL,
  `cut_id` int NOT NULL,
  `det_cut_id` int NOT NULL,
  `process_id` int NOT NULL,
  `date` date NOT NULL,
  `machine_id` int NOT NULL,
  `item_id` int NOT NULL,
  `note` varchar(150) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_ab_pro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_ab_pro` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pro_id` int NOT NULL,
  `wip_id` int NOT NULL,
  `cut_id` int NOT NULL,
  `det_pro_id` int NOT NULL,
  `process_id` int NOT NULL,
  `pallet_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `qty` int NOT NULL DEFAULT '0',
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `date` datetime NOT NULL,
  `user_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `item_id` int NOT NULL,
  `machine_id` int NOT NULL,
  `note` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_cut_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_cut_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `machine_id` int NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time DEFAULT NULL,
  `finish` tinyint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_cut_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_cut_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `user_id` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `wip_id` int NOT NULL,
  `no_dp` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `item_id` int NOT NULL,
  `process_id` int NOT NULL,
  `date` date NOT NULL,
  `shift_id` int unsigned DEFAULT NULL,
  `subcont` tinyint DEFAULT NULL,
  `sub_code` varchar(20) DEFAULT NULL,
  `repair` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `client_uuid` char(36) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `code` (`code`),
  UNIQUE KEY `uq_tcm_uuid` (`client_uuid`),
  UNIQUE KEY `tr_cut_main_client_uuid_unique` (`client_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_cut_pal_pr`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_cut_pal_pr` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `cut_id` int NOT NULL,
  `qty` int NOT NULL,
  `process_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_cut_serial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_cut_serial` (
  `id` int NOT NULL AUTO_INCREMENT,
  `detail_id` int NOT NULL,
  `serial_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `qty` int NOT NULL,
  `length_rem` double NOT NULL,
  `finish` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_dt_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_dt_category` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name_c_dt` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `descriptions` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_dt_cut_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_dt_cut_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `serial_item` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `tools_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_dt_cut_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_dt_cut_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `user_id` varchar(5) NOT NULL,
  `cut_id` int DEFAULT NULL,
  `det_cut_id` int NOT NULL,
  `machine_id` int DEFAULT NULL,
  `no_dp` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `cat_id` int NOT NULL,
  `descriptions` varchar(100) DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `finish` tinyint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_dt_pro_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_dt_pro_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `serial_tool` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tools_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_dt_pro_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_dt_pro_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pro_id` int NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `machine_id` int NOT NULL,
  `det_pro_id` int NOT NULL,
  `cut_id` int NOT NULL,
  `cat_id` int NOT NULL,
  `note` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `start_time` time NOT NULL,
  `end_time` time DEFAULT NULL,
  `created_at` time DEFAULT NULL,
  `updated_at` time DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_inc_fg_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_inc_fg_det` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `main_id` int NOT NULL,
  `pal_pro_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `item_id` int NOT NULL,
  `cut_id` int NOT NULL,
  `qty` int DEFAULT NULL,
  `unit_cost` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `source` varchar(20) NOT NULL DEFAULT 'FG_IN',
  `parent_lot_id` bigint unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'ACTIVE',
  `wip_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_inc_fg_det_udf`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_inc_fg_det_udf` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `pal_pro_code` varchar(20) NOT NULL,
  `item_id` int NOT NULL,
  `no_lot` varchar(30) DEFAULT NULL,
  `qty` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_inc_fg_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_inc_fg_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `date` date NOT NULL,
  `user_id` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_inc_fg_main_udf`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_inc_fg_main_udf` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `user_id` varchar(10) NOT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_ng_cut`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_ng_cut` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `user_id` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `serial_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `qty` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_ng_pro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_ng_pro` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `user_id` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `qty` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `update_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_out_fg_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_out_fg_det` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `item_id` int NOT NULL,
  `fg_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `code` varchar(20) NOT NULL,
  `qty` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_out_fg_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_out_fg_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `code_do` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `date` date NOT NULL,
  `user_id` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_pro_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_pro_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `machine_id` int NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `qty_half` int DEFAULT NULL,
  `qty_full` int DEFAULT NULL,
  `finish` tinyint NOT NULL DEFAULT '0',
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `created_at` time DEFAULT NULL,
  `updated_at` time DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_pro_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_pro_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `wip_id` int NOT NULL,
  `cut_id` int DEFAULT NULL,
  `item_id` int NOT NULL,
  `user_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `process_id` int NOT NULL,
  `pallet_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `no_dp` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `qty_half` int DEFAULT NULL,
  `qty_full` int DEFAULT NULL,
  `subcon_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time DEFAULT NULL,
  `finish` tinyint NOT NULL DEFAULT '0',
  `cont_pro` tinyint NOT NULL DEFAULT '0',
  `sq_process` tinyint unsigned DEFAULT NULL,
  `subcont` tinyint NOT NULL DEFAULT '0',
  `repair` tinyint NOT NULL DEFAULT '0',
  `shift_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `client_uuid` char(36) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tpm_uuid` (`client_uuid`),
  UNIQUE KEY `tr_pro_main_client_uuid_unique` (`client_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_pro_pal_pr`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_pro_pal_pr` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `pro_id` int NOT NULL,
  `cut_id` int NOT NULL,
  `qty` int NOT NULL,
  `pal_code_bf` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `status` varchar(10) NOT NULL,
  `process_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_pro_pallet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_pro_pallet` (
  `id` int NOT NULL AUTO_INCREMENT,
  `detail_id` int NOT NULL,
  `cut_id` int DEFAULT NULL,
  `pallet_code` varchar(50) NOT NULL,
  `qty_half` int DEFAULT NULL,
  `qty_full` int DEFAULT NULL,
  `finish` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_repair_cut`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_repair_cut` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `user_id` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `serial_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `qty` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tr_repair_pro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tr_repair_pro` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` varchar(5) NOT NULL,
  `main_id` int NOT NULL,
  `pallet_code` varchar(50) NOT NULL,
  `qty` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_menu_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_menu_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `menu_id` int NOT NULL,
  `can_view` tinyint NOT NULL DEFAULT '0',
  `can_create` tinyint NOT NULL DEFAULT '0',
  `can_edit` tinyint NOT NULL DEFAULT '0',
  `can_delete` tinyint NOT NULL DEFAULT '0',
  `can_download` tinyint NOT NULL DEFAULT '0',
  `can_import` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `menu_id` (`menu_id`),
  CONSTRAINT `FK_user_menu_permissions_menus` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`),
  CONSTRAINT `FK_user_menu_permissions_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identity` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `two_factor_secret` text COLLATE utf8mb4_unicode_ci,
  `two_factor_recovery_codes` text COLLATE utf8mb4_unicode_ci,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `status_id` int DEFAULT NULL,
  `ven_id` bigint unsigned DEFAULT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `NIK` (`identity`) USING BTREE,
  UNIQUE KEY `email` (`email`),
  KEY `status_id` (`status_id`),
  KEY `users_ven_id_index` (`ven_id`),
  CONSTRAINT `status` FOREIGN KEY (`status_id`) REFERENCES `status_id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warehouse_layout_positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warehouse_layout_positions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `element_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `position_x` decimal(10,2) NOT NULL,
  `position_y` decimal(10,2) NOT NULL,
  `width` decimal(10,2) NOT NULL,
  `height` decimal(10,2) NOT NULL,
  `color` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#FF69B4',
  `rack_ref_id` int DEFAULT NULL,
  `text_orientation` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'horizontal',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `warehouse_layout_positions_type_active_index` (`type`,`active`),
  KEY `warehouse_layout_positions_element_id_index` (`element_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wh_adj_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wh_adj_detail` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `serial_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty_system` decimal(14,2) NOT NULL DEFAULT '0.00',
  `qty_counted` decimal(14,2) NOT NULL DEFAULT '0.00',
  `qty_diff` decimal(14,2) NOT NULL DEFAULT '0.00',
  `unit_cost` decimal(18,2) NOT NULL DEFAULT '0.00',
  `note` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wh_adj_detail_main_id_index` (`main_id`),
  KEY `wh_adj_detail_item_id_index` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wh_adj_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wh_adj_main` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `adj_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RM',
  `reason` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `user_id` bigint unsigned DEFAULT NULL,
  `posted_by` bigint unsigned DEFAULT NULL,
  `posted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wh_adj_main_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wh_inc_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wh_inc_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_prim` int NOT NULL,
  `serial_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `length` int DEFAULT NULL,
  `qty` int NOT NULL,
  `item_id` int NOT NULL,
  `rack_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `main_inc` (`id_prim`),
  KEY `item_inc` (`item_id`),
  KEY `rack_inc` (`rack_id`),
  CONSTRAINT `item_inc` FOREIGN KEY (`item_id`) REFERENCES `m_item` (`id`),
  CONSTRAINT `main_inc` FOREIGN KEY (`id_prim`) REFERENCES `wh_inc_main` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rack_inc` FOREIGN KEY (`rack_id`) REFERENCES `m_rack` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wh_inc_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wh_inc_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int NOT NULL,
  `gr_id` int NOT NULL,
  `date` date NOT NULL,
  `shift_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_wh_inc` (`user_id`),
  KEY `gr_main` (`gr_id`),
  CONSTRAINT `gr_main` FOREIGN KEY (`gr_id`) REFERENCES `prc_gr_main` (`id`),
  CONSTRAINT `user_wh_inc` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wh_layout`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wh_layout` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rack_id` bigint unsigned DEFAULT NULL,
  `x_position` int NOT NULL DEFAULT '0',
  `y_position` int NOT NULL DEFAULT '0',
  `width` int NOT NULL DEFAULT '100',
  `height` int NOT NULL DEFAULT '100',
  `rotation` int NOT NULL DEFAULT '0',
  `type` enum('rack','machine') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rack',
  `label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wh_out_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wh_out_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_prim` int NOT NULL,
  `serial_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `qty` int NOT NULL,
  `pm` int NOT NULL,
  `length_serial` double(8,2) NOT NULL,
  `length_used` double(8,2) NOT NULL,
  `length_rem` double(8,2) NOT NULL,
  `weight_used` double(8,2) DEFAULT NULL,
  `rem_data` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `main_out_mat` (`id_prim`),
  CONSTRAINT `main_out_mat` FOREIGN KEY (`id_prim`) REFERENCES `wh_out_main` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wh_out_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wh_out_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `wo_id` int DEFAULT NULL,
  `item_id` int NOT NULL,
  `user_id` int NOT NULL,
  `date` date NOT NULL,
  `cus_id` int DEFAULT NULL,
  `shift_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wh_rem_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wh_rem_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_prim` int NOT NULL,
  `serial_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `wo_id` int NOT NULL,
  `length` double(8,2) NOT NULL,
  `weight` double(8,2) DEFAULT NULL,
  `rem_count` int NOT NULL,
  `rack_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `main_rem` (`id_prim`),
  CONSTRAINT `main_rem` FOREIGN KEY (`id_prim`) REFERENCES `wh_rem_main` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wh_rem_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wh_rem_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `out_id` int NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `date` date NOT NULL,
  `user_id` int NOT NULL,
  `item_id` int NOT NULL,
  `shift_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `out_id_main` (`out_id`),
  CONSTRAINT `out_id_main` FOREIGN KEY (`out_id`) REFERENCES `wh_out_main` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whs_inc_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whs_inc_det` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `po_det_id` bigint unsigned DEFAULT NULL,
  `item_id` bigint unsigned NOT NULL,
  `serial_code` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty` int NOT NULL,
  `unit_cost` decimal(18,2) NOT NULL DEFAULT '0.00',
  `note` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `whs_inc_det_serial_code_unique` (`serial_code`),
  KEY `whs_inc_det_main_id_index` (`main_id`),
  KEY `whs_inc_det_po_det_id_index` (`po_det_id`),
  KEY `whs_inc_det_item_id_index` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whs_inc_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whs_inc_main` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `po_id` bigint unsigned DEFAULT NULL,
  `ven_id` bigint unsigned DEFAULT NULL,
  `do_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `posted_at` timestamp NULL DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `whs_inc_main_code_unique` (`code`),
  KEY `whs_inc_main_po_id_index` (`po_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whs_out_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whs_out_det` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `serial_code` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty` int NOT NULL,
  `unit_cost` decimal(18,2) NOT NULL DEFAULT '0.00',
  `cost_center` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `machine_id` bigint unsigned DEFAULT NULL,
  `asset_id` bigint unsigned DEFAULT NULL,
  `qty_returned` int NOT NULL DEFAULT '0',
  `note` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `whs_out_det_main_id_index` (`main_id`),
  KEY `whs_out_det_item_id_index` (`item_id`),
  KEY `whs_out_det_serial_code_index` (`serial_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whs_out_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whs_out_main` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `dept` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receiver` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_center` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `posted_at` timestamp NULL DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `whs_out_main_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whs_po_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whs_po_det` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `qty` int NOT NULL,
  `qty_received` int NOT NULL DEFAULT '0',
  `price` decimal(18,2) NOT NULL DEFAULT '0.00',
  `note` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `whs_po_det_main_id_index` (`main_id`),
  KEY `whs_po_det_item_id_index` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whs_po_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whs_po_main` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `ven_id` bigint unsigned NOT NULL,
  `currency_id` bigint unsigned DEFAULT NULL,
  `rate` decimal(18,6) NOT NULL DEFAULT '1.000000',
  `top_days` smallint unsigned NOT NULL DEFAULT '0',
  `eta` date DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `note` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `whs_po_main_code_unique` (`code`),
  KEY `whs_po_main_status_date_index` (`status`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whs_ret_det`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whs_ret_det` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `main_id` bigint unsigned NOT NULL,
  `out_det_id` bigint unsigned DEFAULT NULL,
  `tool_unit_id` bigint unsigned NOT NULL,
  `condition` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'GOOD',
  `note` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `whs_ret_det_main_id_index` (`main_id`),
  KEY `whs_ret_det_out_det_id_index` (`out_det_id`),
  KEY `whs_ret_det_tool_unit_id_index` (`tool_unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whs_ret_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whs_ret_main` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `returner` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `posted_at` timestamp NULL DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `whs_ret_main_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whs_tool_unit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whs_tool_unit` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `inc_det_id` bigint unsigned DEFAULT NULL,
  `status` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'IN_STOCK',
  `holder` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `out_det_id` bigint unsigned DEFAULT NULL,
  `unit_cost` decimal(18,2) NOT NULL DEFAULT '0.00',
  `note` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `whs_tool_unit_code_unique` (`code`),
  KEY `whs_tool_unit_item_id_status_index` (`item_id`,`status`),
  KEY `whs_tool_unit_item_id_index` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wip`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wip` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `user_id` varchar(5) DEFAULT NULL,
  `no_dp` varchar(40) NOT NULL,
  `item_id` int NOT NULL,
  `date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2026_07_15_000001_create_doc_numberings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2026_07_15_090018_create_personal_access_tokens_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2026_07_16_000001_rename_heigh_to_height_on_m_item',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_07_16_000002_simplify_m_item_customer',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_07_16_000003_add_gr_id_to_prc_cost_main',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_07_16_000004_add_est_length_to_prc_po_detail',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_07_16_000005_add_est_unit_to_prc_po_detail',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_07_16_000006_add_eta_and_price_kg_to_po',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_07_17_000001_multi_po_gr_and_wms_tweaks',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_07_20_000001_add_note_to_wo_serials',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_07_20_000002_add_mps_id_to_wo',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_07_21_000001_create_m_route_time_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_07_21_000002_add_proc_id_to_prd_mps',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_07_21_000003_create_prd_mps_resched_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_07_22_000001_create_mes_tables',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_07_22_000002_align_tr_pro_with_reference_app',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_07_22_000003_add_no_dp_to_prd_wip',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_07_22_000004_add_qty_to_tr_ab_pro',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_07_22_000005_add_repair_to_tr_pro_main',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_07_22_000006_add_sort_to_menus',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_07_22_000007_extend_sales_order_fields',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2026_07_22_000008_add_po_detail_code_to_so_detail',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2026_07_22_000009_add_tax_values_to_so_detail',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2026_07_23_000001_create_process_main_tables',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2026_07_23_000002_add_process_main_id_to_m_bom_pro',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2026_07_23_000003_multi_routing_priority',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2026_07_24_000001_create_subcont_po_and_master',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2026_07_28_000001_create_approvals_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2026_07_28_114409_create_prd_crp_result_table',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2026_07_28_114546_create_prd_kanban_table',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2026_07_28_114721_create_fg_transfer_downgrade_tables',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2026_07_28_115240_create_prd_fcs_tables',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2026_07_28_130502_refactor_fg_lot_use_tr_inc_fg_det',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2026_07_28_131021_create_prd_crp_table',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2026_07_28_131148_drop_prd_crp_result_table',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2026_07_28_132000_create_mes_oplog_table',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2026_07_28_150000_phase3_offline_vendor_tax',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2026_07_28_160000_create_scrap_decisions_table',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2026_07_28_170000_add_approval_status_to_masters',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2026_07_28_180000_add_pph22_to_cost_sheet',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2026_07_28_190000_create_inspection_params',39);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2026_07_28_200000_add_version_to_wo_serial',40);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2026_07_28_210000_create_stock_adjustment',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2026_07_29_090000_allow_service_ap_invoice_lines',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2026_07_29_100000_add_purchasing_lot_to_item',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2026_07_29_110000_add_demand_source_to_mrp',44);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2026_07_29_120000_create_work_calendar',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2026_07_29_130000_create_holiday_master',46);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2026_07_29_140000_create_supplier_item_price',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2026_07_29_150000_create_alerts',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2026_07_31_100000_create_ecn',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2026_07_31_110000_create_packing_and_shipping',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2026_08_03_100000_create_whs_module',50);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2026_08_03_120000_add_whs_serials',51);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2026_08_04_100000_create_npd_stage1',52);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2026_08_04_120000_create_npd_stage2',53);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2026_08_04_140000_add_item_lifecycle',54);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2026_08_04_160000_create_npd_stage3',55);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2026_08_04_180000_create_npd_stage4',56);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2026_08_04_200000_create_npd_stage5',57);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2026_08_04_220000_add_item_shape',58);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2026_08_06_100000_create_vendor_quotation_contract',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2026_08_06_120000_create_product_family_production_line',60);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2026_08_06_140000_fix_delivery_order_status',61);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2026_08_06_150000_retire_legacy_consumable_items',62);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2026_08_06_160000_add_two_factor_to_users',63);
