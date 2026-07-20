-- ============================================================================
-- ab-erp — Skema v3.0 (16 Juli 2026)
-- BASIS: struktur aplikasi existing (whs + trans) — tabel & field DIPERTAHANKAN
-- + ALTER seperlunya utk kriteria ERP + pengembangan modul lain (gaya sama)
-- ============================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
CREATE DATABASE IF NOT EXISTS `ab-erp` DEFAULT CHARACTER SET utf8mb4;
USE `ab-erp`;

-- ================== A. TABEL APLIKASI EXISTING (VERBATIM) ==================
CREATE TABLE IF NOT EXISTS `log_prc` (
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

CREATE TABLE IF NOT EXISTS `menus` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '0',
  `link` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT '0',
  `parent_id` int NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `m_bom` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `active` tinyint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_FG` (`item_id`) USING BTREE,
  CONSTRAINT `item_FG` FOREIGN KEY (`item_id`) REFERENCES `m_item` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `m_bom_det_pm` (
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

CREATE TABLE IF NOT EXISTS `m_bom_det_rm` (
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

CREATE TABLE IF NOT EXISTS `m_bom_pro` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `item_id` FOREIGN KEY (`item_id`) REFERENCES `m_item` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `m_bom_pro_det` (
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

CREATE TABLE IF NOT EXISTS `m_contacts` (
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
  `active` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_code` (`u_code`),
  KEY `FK_contacts_cont_categ` (`category_id`) USING BTREE,
  CONSTRAINT `FK_contacts_cont_categ` FOREIGN KEY (`category_id`) REFERENCES `m_cont_categ` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `m_cont_categ` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `m_function_m` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(150) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `m_item` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `part_name` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL,
  `descrip` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `category_id` int NOT NULL,
  `o_d` double(8,2) DEFAULT NULL,
  `i_d` double(8,2) DEFAULT NULL,
  `thick` double(8,2) DEFAULT NULL,
  `width` double(8,2) DEFAULT NULL,
  `heigh` double(8,2) DEFAULT NULL,
  `length` double(8,2) DEFAULT NULL,
  `length_cut` double(8,2) DEFAULT NULL,
  `weight` double(8,2) NOT NULL DEFAULT '0.00',
  `tolerance` varchar(50) DEFAULT NULL,
  `tole_range` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `cus_id` int DEFAULT NULL,
  `mat_cus` tinyint DEFAULT NULL,
  `min_stock` int NOT NULL,
  `max_stock` int NOT NULL,
  `local_mat` tinyint DEFAULT NULL,
  `pm` tinyint NOT NULL DEFAULT '0',
  `active` tinyint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `categ` (`category_id`),
  KEY `customer_id` (`cus_id`),
  CONSTRAINT `categ` FOREIGN KEY (`category_id`) REFERENCES `m_i_category` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `customer_id` FOREIGN KEY (`cus_id`) REFERENCES `m_contacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `m_i_category` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name_c` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `m_machine` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(50) NOT NULL,
  `model` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `categ` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
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
  `active` tinyint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `m_maker_m` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `address` varchar(150) DEFAULT NULL,
  `active` tinyint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `m_pallet` (
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

CREATE TABLE IF NOT EXISTS `m_pal_item` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `m_pal_item_det` (
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

CREATE TABLE IF NOT EXISTS `m_pic` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pic_id` int NOT NULL,
  `type` varchar(5) NOT NULL,
  `active` tinyint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `m_process` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name_p` varchar(50) NOT NULL,
  `descript` varchar(100) DEFAULT NULL,
  `active` tinyint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `m_p_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name_ty` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `m_rack` (
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

CREATE TABLE IF NOT EXISTS `m_region` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '0',
  `created_at` varchar(50) NOT NULL DEFAULT '0',
  `updated_at` varchar(50) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `m_shift` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(5) NOT NULL,
  `name` varchar(10) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `m_team` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `descript` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `prc_gr_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_prim` int NOT NULL,
  `item_id` int NOT NULL,
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

CREATE TABLE IF NOT EXISTS `prc_gr_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `inv_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `ven_id` int NOT NULL,
  `date` date NOT NULL,
  `user_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `po_no` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `customer` (`ven_id`) USING BTREE,
  CONSTRAINT `customer` FOREIGN KEY (`ven_id`) REFERENCES `m_contacts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `prc_gr_serial` (
  `id` int NOT NULL AUTO_INCREMENT,
  `det_id` int NOT NULL,
  `serial_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `millsheet` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `qty` int NOT NULL,
  `length` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `det_id` (`det_id`),
  CONSTRAINT `detail` FOREIGN KEY (`det_id`) REFERENCES `prc_gr_detail` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `prd_cut_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `wip_id` int NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `item_id` int NOT NULL,
  `wo_id` int NOT NULL,
  `operator` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `machine_id` int NOT NULL,
  `date` date NOT NULL,
  `shift` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `prd_cut_serial` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `serial_id` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `qty` int NOT NULL,
  `length_rem` double(8,2) NOT NULL DEFAULT '0.00',
  `finish` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `prd_wip` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `wo_id` int NOT NULL,
  `item_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `prd_wo_detail_pm` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `code_tr` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `pm_id` int NOT NULL,
  `note` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wo_main_pm` (`main_id`),
  CONSTRAINT `wo_main_pm` FOREIGN KEY (`main_id`) REFERENCES `prd_wo_main` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `prd_wo_detail_rm` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `rm_id` int NOT NULL,
  `note` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wo_main_rm` (`main_id`),
  CONSTRAINT `wo_main_rm` FOREIGN KEY (`main_id`) REFERENCES `prd_wo_main` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `prd_wo_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `date` date NOT NULL,
  `customer_id` int NOT NULL,
  `so_id` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `fg_id` int NOT NULL,
  `user_id` int NOT NULL,
  `qty` int NOT NULL,
  `status` int NOT NULL,
  `no_cut` tinyint DEFAULT NULL,
  `for_pm` tinyint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `prd_wo_serial_pm` (
  `id` int NOT NULL AUTO_INCREMENT,
  `detail_id` int NOT NULL,
  `serial_id` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `qty` int NOT NULL,
  `created_at` timestamp NOT NULL,
  `updated_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_wo_pm` (`detail_id`),
  CONSTRAINT `detail_wo_pm` FOREIGN KEY (`detail_id`) REFERENCES `prd_wo_detail_pm` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `prd_wo_serial_rm` (
  `id` int NOT NULL AUTO_INCREMENT,
  `detail_id` int NOT NULL,
  `serial_id` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `length_asal` double(8,2) NOT NULL,
  `length_book` double(8,2) NOT NULL,
  `qty_per_serial` int NOT NULL,
  `length_rem` double(8,2) NOT NULL,
  `qty_serial` int NOT NULL,
  `scrap` tinyint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_wo_rm` (`detail_id`),
  CONSTRAINT `detail_wo_rm` FOREIGN KEY (`detail_id`) REFERENCES `prd_wo_detail_rm` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `status_id` (
  `id` int NOT NULL AUTO_INCREMENT,
  `status` varchar(12) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `stock_check` (
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

CREATE TABLE IF NOT EXISTS `sum_stock_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `sum_stock_pm` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `item_id` int NOT NULL,
  `qty` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `sum_stock_rm` (
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

CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identity` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_id` int DEFAULT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `NIK` (`identity`) USING BTREE,
  UNIQUE KEY `email` (`email`),
  KEY `status_id` (`status_id`),
  CONSTRAINT `status` FOREIGN KEY (`status_id`) REFERENCES `status_id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_menu_permissions` (
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

CREATE TABLE IF NOT EXISTS `warehouse_layout_positions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `element_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position_x` decimal(10,2) NOT NULL,
  `position_y` decimal(10,2) NOT NULL,
  `width` decimal(10,2) NOT NULL,
  `height` decimal(10,2) NOT NULL,
  `color` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#FF69B4',
  `rack_ref_id` int DEFAULT NULL,
  `text_orientation` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'horizontal',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `warehouse_layout_positions_type_active_index` (`type`,`active`),
  KEY `warehouse_layout_positions_element_id_index` (`element_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wh_inc_detail` (
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

CREATE TABLE IF NOT EXISTS `wh_inc_main` (
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

CREATE TABLE IF NOT EXISTS `wh_layout` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rack_id` bigint unsigned DEFAULT NULL,
  `x_position` int NOT NULL DEFAULT '0',
  `y_position` int NOT NULL DEFAULT '0',
  `width` int NOT NULL DEFAULT '100',
  `height` int NOT NULL DEFAULT '100',
  `rotation` int NOT NULL DEFAULT '0',
  `type` enum('rack','machine') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rack',
  `label` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wh_out_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_prim` int NOT NULL,
  `serial_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `qty` int NOT NULL,
  `pm` int NOT NULL,
  `length_serial` double(8,2) NOT NULL,
  `length_used` double(8,2) NOT NULL,
  `length_rem` double(8,2) NOT NULL,
  `rem_data` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `main_out_mat` (`id_prim`),
  CONSTRAINT `main_out_mat` FOREIGN KEY (`id_prim`) REFERENCES `wh_out_main` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `wh_out_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `wo_id` int NOT NULL,
  `item_id` int NOT NULL,
  `user_id` int NOT NULL,
  `date` date NOT NULL,
  `cus_id` int NOT NULL,
  `shift_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `wh_rem_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_prim` int NOT NULL,
  `serial_id` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `wo_id` int NOT NULL,
  `length` double(8,2) NOT NULL,
  `rem_count` int NOT NULL,
  `rack_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `main_rem` (`id_prim`),
  CONSTRAINT `main_rem` FOREIGN KEY (`id_prim`) REFERENCES `wh_rem_main` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `wh_rem_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `out_id` int NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
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

CREATE TABLE IF NOT EXISTS `tr_ab_cut_det` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `serial_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `qty` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `tr_ab_cut_main` (
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

CREATE TABLE IF NOT EXISTS `tr_ab_pro` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pro_id` int NOT NULL,
  `wip_id` int NOT NULL,
  `cut_id` int NOT NULL,
  `det_pro_id` int NOT NULL,
  `process_id` int NOT NULL,
  `pallet_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
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

CREATE TABLE IF NOT EXISTS `tr_cut_detail` (
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

CREATE TABLE IF NOT EXISTS `tr_cut_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `user_id` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `wip_id` int NOT NULL,
  `no_dp` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `item_id` int NOT NULL,
  `process_id` int NOT NULL,
  `date` date NOT NULL,
  `subcont` tinyint DEFAULT NULL,
  `sub_code` varchar(20) DEFAULT NULL,
  `repair` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `tr_cut_pal_pr` (
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

CREATE TABLE IF NOT EXISTS `tr_cut_serial` (
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

CREATE TABLE IF NOT EXISTS `tr_dt_category` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name_c_dt` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `descriptions` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `tr_dt_cut_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `serial_item` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `tools_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `tr_dt_cut_main` (
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

CREATE TABLE IF NOT EXISTS `tr_dt_pro_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `serial_tool` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tools_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tr_dt_pro_main` (
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

CREATE TABLE IF NOT EXISTS `tr_inc_fg_det` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `main_id` int NOT NULL,
  `pal_pro_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `item_id` int NOT NULL,
  `cut_id` int NOT NULL,
  `qty` int DEFAULT NULL,
  `wip_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `tr_inc_fg_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `date` date NOT NULL,
  `user_id` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `tr_ng_cut` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `user_id` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `serial_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `qty` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `tr_ng_pro` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `user_id` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `qty` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `update_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tr_out_fg_det` (
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

CREATE TABLE IF NOT EXISTS `tr_out_fg_main` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `code_do` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `date` date NOT NULL,
  `user_id` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `tr_pro_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `machine_id` int NOT NULL,
  `qty_half` int DEFAULT NULL,
  `qty_full` int DEFAULT NULL,
  `created_at` time DEFAULT NULL,
  `updated_at` time DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `tr_pro_main` (
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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tr_pro_pal_pr` (
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

CREATE TABLE IF NOT EXISTS `tr_repair_cut` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `user_id` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `serial_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `qty` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;


CREATE TABLE IF NOT EXISTS `wip` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `user_id` varchar(5) DEFAULT NULL,
  `no_dp` varchar(40) NOT NULL,
  `item_id` int NOT NULL,
  `date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `m_i_pm` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `active` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tr_pro_pallet` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tr_inc_fg_main_udf` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `user_id` varchar(10) NOT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tr_inc_fg_det_udf` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_id` int NOT NULL,
  `pal_pro_code` varchar(20) NOT NULL,
  `item_id` int NOT NULL,
  `no_lot` varchar(30) DEFAULT NULL,
  `qty` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tr_repair_pro` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` varchar(5) NOT NULL,
  `main_id` int NOT NULL,
  `pallet_code` varchar(50) NOT NULL,
  `qty` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================================
-- PENYESUAIAN KRITERIA ERP (ALTER — field & tabel lama TIDAK diubah/di-rename)
-- ============================================================================

-- GR tidak menyimpan no invoice (invoice = dokumen terpisah setelah GR confirmed)
ALTER TABLE `prc_gr_main` DROP COLUMN `inv_no`;
ALTER TABLE `prc_gr_main`
  ADD COLUMN `import_doc_no` varchar(50) NULL COMMENT 'no PIB (impor)' AFTER `po_no`,
  ADD COLUMN `status` varchar(20) NOT NULL DEFAULT 'DRAFT'
      COMMENT 'DRAFT|SERIAL_GENERATED|PRINTED|CHECKING|CONFIRMED|INVOICED' AFTER `import_doc_no`;

-- HS code & kuota dicatat SAAT GR (bukan saat invoice) — kuota terpakai per kedatangan aktual
ALTER TABLE `prc_gr_detail`
  ADD COLUMN `quota_id` int NULL COMMENT 'kuota impor per baris' AFTER `item_id`,
  ADD COLUMN `hs_code` varchar(20) NULL COMMENT 'snapshot HS code saat GR' AFTER `quota_id`;

-- Serial: generate di awal GR -> print -> aktualisasi (berat + status OK/NG/VOID)
ALTER TABLE `prc_gr_serial`
  ADD COLUMN `weight` double(8,2) NULL COMMENT 'berat aktual kg (aktualisasi)' AFTER `length`,
  ADD COLUMN `status` varchar(15) NOT NULL DEFAULT 'GENERATED'
      COMMENT 'GENERATED|OK|NG|VOID' AFTER `weight`,
  ADD COLUMN `ng_reason` varchar(200) NULL AFTER `status`;

-- Dual UoM: pemakaian & sisa dicatat berat selain panjang
ALTER TABLE `wh_out_detail` ADD COLUMN `weight_used` double(8,2) NULL AFTER `length_rem`;
ALTER TABLE `wh_rem_detail` ADD COLUMN `weight` double(8,2) NULL AFTER `length`;

-- Master item: HS code (kuota impor) & spec group (aturan transfer FG).
-- cus_id DIBIARKAN (deprecated) — kontrol multi-customer pindah ke m_item_customer
ALTER TABLE `m_item`
  ADD COLUMN `hs_code` varchar(20) NULL AFTER `tole_range`,
  ADD COLUMN `spec_group` varchar(30) NULL AFTER `hs_code`;

-- Idempotency scan offline (PWA terminal)
ALTER TABLE `tr_cut_main` ADD COLUMN `client_uuid` char(36) NULL, ADD UNIQUE KEY `uq_tcm_uuid` (`client_uuid`);
ALTER TABLE `tr_pro_main` ADD COLUMN `client_uuid` char(36) NULL, ADD UNIQUE KEY `uq_tpm_uuid` (`client_uuid`);


-- ============================================================================
-- PENGEMBANGAN MODUL LAIN — gaya sama: id int, `code`, `date`, user_id, main_id
-- ============================================================================

-- ---------- MASTER TAMBAHAN ----------
CREATE TABLE IF NOT EXISTS `m_uom` (
  `id` int NOT NULL AUTO_INCREMENT, `code` varchar(10) NOT NULL, `name` varchar(50) NOT NULL,
  `uom_type` varchar(15) DEFAULT 'QTY', `active` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL, `updated_at` timestamp NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `m_currency` (
  `id` int NOT NULL AUTO_INCREMENT, `code` char(3) NOT NULL, `name` varchar(50) NOT NULL,
  `is_base` tinyint NOT NULL DEFAULT '0', PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `m_rate` (
  `id` int NOT NULL AUTO_INCREMENT, `currency_id` int NOT NULL,
  `rate_type` varchar(15) NOT NULL COMMENT 'TRANSACTION|KMK', `valid_date` date NOT NULL,
  `rate` decimal(15,6) NOT NULL, PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rate` (`currency_id`,`rate_type`,`valid_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `m_tax` (
  `id` int NOT NULL AUTO_INCREMENT, `code` varchar(20) NOT NULL, `name` varchar(100) NOT NULL,
  `rate_pct` decimal(8,4) NOT NULL COMMENT '12.0000',
  `dpp_factor` decimal(8,6) NOT NULL DEFAULT '1.000000' COMMENT '11/12 utk non-mewah (PMK 131/2024)',
  `is_luxury` tinyint NOT NULL DEFAULT '0', `effective_from` date NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `m_quota` (
  `id` int NOT NULL AUTO_INCREMENT, `code` varchar(30) NOT NULL COMMENT 'kode kuota/PI',
  `descrip` varchar(300) NULL, `hs_code` varchar(20) NULL,
  `total_ton` decimal(12,3) NOT NULL, `valid_from` date NOT NULL, `valid_to` date NOT NULL,
  `active` tinyint NOT NULL DEFAULT '1', `created_at` timestamp NULL, `updated_at` timestamp NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `m_quota_item` (
  `id` int NOT NULL AUTO_INCREMENT, `quota_id` int NOT NULL, `item_id` int NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_qi` (`quota_id`,`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- pengganti m_item.cus_id: 1 FG bisa ke banyak customer, SO/DO tervalidasi ke mapping ini
CREATE TABLE IF NOT EXISTS `m_item_customer` (
  `id` int NOT NULL AUTO_INCREMENT, `item_id` int NOT NULL,
  `cus_id` int NOT NULL COMMENT 'ref m_contacts',
  `cus_part_no` varchar(50) NULL, `cus_part_name` varchar(100) NULL,
  `qty_per_box` int NULL, `is_default` tinyint NOT NULL DEFAULT '0',
  `active` tinyint NOT NULL DEFAULT '1', `created_at` timestamp NULL, `updated_at` timestamp NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_ic` (`item_id`,`cus_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `m_pricelist_main` (
  `id` int NOT NULL AUTO_INCREMENT, `code` varchar(50) NOT NULL,
  `cus_id` int NULL COMMENT 'null=umum', `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `user_id` int NULL, `created_at` timestamp NULL, `updated_at` timestamp NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `m_pricelist_det` (
  `id` int NOT NULL AUTO_INCREMENT, `main_id` int NOT NULL, `item_id` int NOT NULL,
  `price` decimal(18,4) NOT NULL, `currency_id` int NOT NULL,
  `valid_from` date NOT NULL, `valid_to` date NOT NULL, `min_qty` int DEFAULT '0',
  PRIMARY KEY (`id`), KEY `idx_pl_item` (`item_id`,`valid_from`,`valid_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `m_defective` (
  `id` int NOT NULL AUTO_INCREMENT, `code` varchar(20) NOT NULL, `name` varchar(100) NOT NULL,
  `type` varchar(15) DEFAULT 'PROCESS', PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `m_asset_categ` (
  `id` int NOT NULL AUTO_INCREMENT, `code` varchar(20) NOT NULL, `name` varchar(100) NOT NULL,
  `useful_life` smallint NOT NULL DEFAULT '48' COMMENT 'bulan',
  `depr_method` varchar(15) NOT NULL DEFAULT 'STRAIGHT', PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- PROCUREMENT (PR -> PO -> GR(sudah ada) -> INVOICE -> COST SHEET) ----------
CREATE TABLE IF NOT EXISTS `prc_pr_main` (
  `id` int NOT NULL AUTO_INCREMENT, `code` varchar(50) NOT NULL, `date` date NOT NULL,
  `pr_type` varchar(15) NOT NULL DEFAULT 'MANUAL' COMMENT 'MRP|ADDITIONAL|NON_RM|NPD',
  `user_id` int NOT NULL, `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `created_at` timestamp NULL, `updated_at` timestamp NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prc_pr_detail` (
  `id` int NOT NULL AUTO_INCREMENT, `main_id` int NOT NULL, `item_id` int NOT NULL,
  `qty` int NOT NULL, `uom_id` int NULL, `need_date` date NULL, `wo_id` int NULL,
  `note` varchar(150) NULL, PRIMARY KEY (`id`), KEY `idx_prd_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prc_po_main` (
  `id` int NOT NULL AUTO_INCREMENT, `code` varchar(50) NOT NULL, `date` date NOT NULL,
  `po_type` varchar(15) NOT NULL COMMENT 'RM|GENERAL|NPD|SERVICE|SUBCONT|ASSET',
  `source` varchar(10) NOT NULL DEFAULT 'LOCAL' COMMENT 'LOCAL|IMPORT',
  `ven_id` int NOT NULL COMMENT 'ref m_contacts',
  `quota_id` int NULL COMMENT 'wajib bila IMPORT+RM',
  `currency_id` int NULL, `rate` decimal(15,6) DEFAULT '1.000000',
  `top_days` smallint DEFAULT '30', `user_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `created_at` timestamp NULL, `updated_at` timestamp NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`), KEY `idx_po_ven` (`ven_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prc_po_detail` (
  `id` int NOT NULL AUTO_INCREMENT, `main_id` int NOT NULL, `pr_detail_id` int NULL,
  `item_id` int NOT NULL, `qty` int NOT NULL, `uom_id` int NULL,
  `price` decimal(18,4) NOT NULL, `tax_id` int NULL,
  `est_weight` double(10,2) NULL COMMENT 'estimasi kg (reservasi kuota)',
  `due_date` date NULL, `qty_received` int DEFAULT '0',
  PRIMARY KEY (`id`), KEY `idx_pod_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prc_po_att` (
  `id` int NOT NULL AUTO_INCREMENT, `main_id` int NOT NULL,
  `file_type` varchar(15) DEFAULT 'QUOTATION', `file_path` varchar(255) NOT NULL,
  `file_name` varchar(150) NULL, `created_at` timestamp NULL,
  PRIMARY KEY (`id`), KEY `idx_poa_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prc_po_schedule` (
  `id` int NOT NULL AUTO_INCREMENT, `po_detail_id` int NOT NULL,
  `plan_date` date NOT NULL, `qty` int NOT NULL, `confirmed_at` timestamp NULL,
  PRIMARY KEY (`id`), KEY `idx_pos_det` (`po_detail_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prc_quota_txn` (
  `id` int NOT NULL AUTO_INCREMENT, `quota_id` int NOT NULL,
  `ref_type` varchar(15) NOT NULL COMMENT 'PO_RESERVE|GR_ACTUAL|RELEASE|ADJUST',
  `ref_id` int NOT NULL, `ton` decimal(12,3) NOT NULL, `sign` tinyint NOT NULL COMMENT '-1/+1',
  `note` varchar(200) NULL, `user_id` int NULL, `created_at` timestamp NULL,
  PRIMARY KEY (`id`), KEY `idx_qt_quota` (`quota_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prc_gr_reject` (
  `id` int NOT NULL AUTO_INCREMENT, `code` varchar(50) NOT NULL, `date` date NOT NULL,
  `gr_id` int NOT NULL, `ven_id` int NOT NULL, `item_id` int NOT NULL,
  `qty` int NOT NULL, `reason` varchar(300) NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|RETURNED|CLAIMED',
  `created_at` timestamp NULL, `updated_at` timestamp NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prc_inv_main` (
  `id` int NOT NULL AUTO_INCREMENT, `code` varchar(50) NOT NULL, `date` date NOT NULL,
  `ven_id` int NOT NULL, `po_id` int NULL,
  `inv_no` varchar(50) NOT NULL COMMENT 'no invoice VENDOR — diinput DI SINI, bukan di GR',
  `dpp` decimal(18,2) DEFAULT '0.00', `vat` decimal(18,2) DEFAULT '0.00',
  `wht23` decimal(18,2) DEFAULT '0.00' COMMENT 'PPh23 jasa/subcont',
  `total` decimal(18,2) DEFAULT '0.00',
  `tax_inv_no` varchar(30) NULL COMMENT 'faktur pajak masukan (Coretax)',
  `tax_inv_date` date NULL, `due_date` date NULL, `user_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|MATCHED|POSTED|PAID',
  `created_at` timestamp NULL, `updated_at` timestamp NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prc_inv_detail` (
  `id` int NOT NULL AUTO_INCREMENT, `main_id` int NOT NULL, `gr_detail_id` int NOT NULL,
  `qty` int NOT NULL, `price` decimal(18,4) NOT NULL, `amount` decimal(18,2) NOT NULL,
  PRIMARY KEY (`id`), KEY `idx_pid_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prc_cost_main` (
  `id` int NOT NULL AUTO_INCREMENT, `code` varchar(50) NOT NULL, `date` date NOT NULL,
  `po_id` int NOT NULL, `alloc_basis` varchar(15) NOT NULL DEFAULT 'WEIGHT',
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|FINAL|POSTED',
  `user_id` int NOT NULL, `created_at` timestamp NULL, `updated_at` timestamp NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prc_cost_detail` (
  `id` int NOT NULL AUTO_INCREMENT, `main_id` int NOT NULL,
  `cost_type` varchar(30) NOT NULL COMMENT 'FREIGHT|INSURANCE|DUTY|VAT_IMPORT|PPH22|PIB|EMKL|FORWARDER|UNLOADING|TRUCKING|OTHER',
  `descrip` varchar(200) NULL, `amount` decimal(18,2) NOT NULL,
  `currency_id` int NULL, `rate` decimal(15,6) DEFAULT '1.000000',
  `amount_idr` decimal(18,2) NOT NULL,
  PRIMARY KEY (`id`), KEY `idx_pcd_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prc_cost_alloc` (
  `id` int NOT NULL AUTO_INCREMENT, `main_id` int NOT NULL, `gr_detail_id` int NOT NULL,
  `serial_id` varchar(50) NULL COMMENT 'ref prc_gr_serial.serial_id',
  `amount` decimal(18,2) NOT NULL, `unit_cost_kg` decimal(18,4) NOT NULL,
  PRIMARY KEY (`id`), KEY `idx_pca_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ---------- SALES (SO -> DO -> tr_out_fg existing -> INVOICE AR) ----------
CREATE TABLE IF NOT EXISTS `sls_so_main` (
  `id` int NOT NULL AUTO_INCREMENT, `code` varchar(50) NOT NULL, `date` date NOT NULL,
  `cus_id` int NOT NULL COMMENT 'ref m_contacts; item wajib terdaftar di m_item_customer',
  `cus_po_no` varchar(50) NULL, `currency_id` int NULL, `user_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `created_at` timestamp NULL, `updated_at` timestamp NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sls_so_detail` (
  `id` int NOT NULL AUTO_INCREMENT, `main_id` int NOT NULL, `item_id` int NOT NULL,
  `qty` int NOT NULL, `price` decimal(18,4) NOT NULL, `pricelist_det_id` int NULL,
  `tax_id` int NULL, `due_date` date NULL, `qty_delivered` int DEFAULT '0',
  PRIMARY KEY (`id`), KEY `idx_sod_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sls_do_main` (
  `id` int NOT NULL AUTO_INCREMENT, `code` varchar(50) NOT NULL COMMENT 'ref tr_out_fg_main.code_do',
  `date` date NOT NULL, `so_id` int NOT NULL, `user_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|PICKED|SHIPPED|RECEIVED|INVOICED',
  `created_at` timestamp NULL, `updated_at` timestamp NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sls_do_detail` (
  `id` int NOT NULL AUTO_INCREMENT, `main_id` int NOT NULL, `so_detail_id` int NOT NULL,
  `item_id` int NOT NULL, `qty` int NOT NULL, `fg_code` varchar(20) NULL COMMENT 'ref tr_inc_fg_det.code',
  PRIMARY KEY (`id`), KEY `idx_dod_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sls_inv_main` (
  `id` int NOT NULL AUTO_INCREMENT, `code` varchar(50) NOT NULL, `date` date NOT NULL,
  `cus_id` int NOT NULL, `dpp` decimal(18,2) DEFAULT '0.00',
  `dpp_nilai_lain` decimal(18,2) DEFAULT '0.00' COMMENT '11/12 x DPP (PMK 131/2024)',
  `vat` decimal(18,2) DEFAULT '0.00' COMMENT '12% x dpp_nilai_lain',
  `total` decimal(18,2) DEFAULT '0.00',
  `tax_inv_no` varchar(30) NULL COMMENT 'faktur pajak keluaran (Coretax)',
  `due_date` date NULL, `user_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `created_at` timestamp NULL, `updated_at` timestamp NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sls_inv_detail` (
  `id` int NOT NULL AUTO_INCREMENT, `main_id` int NOT NULL, `do_detail_id` int NOT NULL,
  `item_id` int NOT NULL, `qty` int NOT NULL, `price` decimal(18,4) NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  PRIMARY KEY (`id`), KEY `idx_sid_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sls_return` (
  `id` int NOT NULL AUTO_INCREMENT, `code` varchar(50) NOT NULL, `date` date NOT NULL,
  `do_id` int NOT NULL, `item_id` int NOT NULL, `qty` int NOT NULL,
  `reason` varchar(300) NULL, `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `created_at` timestamp NULL, `updated_at` timestamp NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sls_forecast` (
  `id` int NOT NULL AUTO_INCREMENT, `cus_id` int NOT NULL, `item_id` int NOT NULL,
  `period` char(6) NOT NULL COMMENT 'YYYYMM', `version` varchar(10) NOT NULL DEFAULT 'FINAL',
  `qty` int NOT NULL, `created_at` timestamp NULL, `updated_at` timestamp NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_fc` (`cus_id`,`item_id`,`period`,`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- PLANNING (MPP/MPS/MRP/CRP — WO existing prd_wo_*) ----------
CREATE TABLE IF NOT EXISTS `prd_mpp` (
  `id` int NOT NULL AUTO_INCREMENT, `period` char(6) NOT NULL, `item_id` int NOT NULL,
  `plan_qty` int NOT NULL, `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `created_at` timestamp NULL, `updated_at` timestamp NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_mpp` (`period`,`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prd_mps` (
  `id` int NOT NULL AUTO_INCREMENT, `plan_date` date NOT NULL, `item_id` int NOT NULL,
  `qty` int NOT NULL, `machine_id` int NULL COMMENT 'alokasi prioritas mesin dari m_bom_pro',
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `created_at` timestamp NULL, `updated_at` timestamp NULL,
  PRIMARY KEY (`id`), KEY `idx_mps` (`plan_date`,`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prd_mrp_main` (
  `id` int NOT NULL AUTO_INCREMENT, `run_date` datetime NOT NULL, `user_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'QUEUED', `created_at` timestamp NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prd_mrp_detail` (
  `id` int NOT NULL AUTO_INCREMENT, `main_id` int NOT NULL, `item_id` int NOT NULL,
  `period` char(6) NOT NULL, `gross_req` int DEFAULT '0', `onhand` int DEFAULT '0',
  `open_po` int DEFAULT '0', `open_wo` int DEFAULT '0', `net_req` int DEFAULT '0',
  `net_req_kg` double(12,2) NULL COMMENT 'RM: cek saldo kuota',
  `suggestion` varchar(10) NULL COMMENT 'PR|WO|RESCHED',
  PRIMARY KEY (`id`), KEY `idx_mrpd_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prd_crp` (
  `id` int NOT NULL AUTO_INCREMENT, `mrp_id` int NULL, `basis` varchar(10) DEFAULT 'MPS',
  `process_id` int NOT NULL, `machine_id` int NOT NULL, `period` char(6) NOT NULL,
  `load_hours` double(10,2) NOT NULL, `capacity_hours` double(10,2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- SUBCONT (PO subcont + kirim/terima + portal) ----------
CREATE TABLE IF NOT EXISTS `sub_dn_main` (
  `id` int NOT NULL AUTO_INCREMENT, `code` varchar(50) NOT NULL, `date` date NOT NULL,
  `po_id` int NOT NULL, `ven_id` int NOT NULL, `user_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|SENT|PARTIAL|COMPLETED',
  `created_at` timestamp NULL, `updated_at` timestamp NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sub_dn_detail` (
  `id` int NOT NULL AUTO_INCREMENT, `main_id` int NOT NULL, `wo_id` int NULL,
  `item_id` int NOT NULL, `serial_id` varchar(50) NULL, `pallet_code` varchar(50) NULL,
  `qty` int NOT NULL, PRIMARY KEY (`id`), KEY `idx_sdd_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sub_gr_main` (
  `id` int NOT NULL AUTO_INCREMENT, `code` varchar(50) NOT NULL, `date` date NOT NULL,
  `po_id` int NOT NULL, `dn_id` int NULL, `ven_dn_no` varchar(50) NULL,
  `qty_ok` int DEFAULT '0', `qty_ng` int DEFAULT '0', `user_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `created_at` timestamp NULL, `updated_at` timestamp NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sub_progress` (
  `id` int NOT NULL AUTO_INCREMENT, `po_detail_id` int NOT NULL,
  `progress_pct` double(5,2) NOT NULL, `note` varchar(300) NULL,
  `user_id` int NOT NULL COMMENT 'user portal vendor', `reported_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- QC INCOMING (QAS — hasil dicatat kembali ke prc_gr_serial.status) ----------
CREATE TABLE IF NOT EXISTS `qc_incoming_main` (
  `id` int NOT NULL AUTO_INCREMENT, `code` varchar(50) NOT NULL, `date` date NOT NULL,
  `gr_id` int NOT NULL, `result` varchar(15) NULL COMMENT 'ACCEPT|REJECT|DEVIATION',
  `inspector_id` int NOT NULL, `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `created_at` timestamp NULL, `updated_at` timestamp NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `qc_incoming_det` (
  `id` int NOT NULL AUTO_INCREMENT, `main_id` int NOT NULL, `param` varchar(100) NOT NULL,
  `standard` varchar(50) NULL, `actual` varchar(50) NULL, `judge` varchar(10) NULL,
  PRIMARY KEY (`id`), KEY `idx_qid_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- GENERAL STORE (sparepart & consumable — keluar/masuk terkontrol) ----------
CREATE TABLE IF NOT EXISTS `wh_gen_req_main` (
  `id` int NOT NULL AUTO_INCREMENT, `code` varchar(50) NOT NULL, `date` date NOT NULL,
  `dept` varchar(50) NOT NULL, `user_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|APPROVED|ISSUED|REJECTED',
  `created_at` timestamp NULL, `updated_at` timestamp NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `wh_gen_req_det` (
  `id` int NOT NULL AUTO_INCREMENT, `main_id` int NOT NULL, `item_id` int NOT NULL,
  `qty` int NOT NULL, `uom_id` int NULL, `machine_id` int NULL,
  `asset_id` int NULL COMMENT 'riwayat sparepart per asset', `qty_issued` int DEFAULT '0',
  PRIMARY KEY (`id`), KEY `idx_grd_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `wh_gen_out_main` (
  `id` int NOT NULL AUTO_INCREMENT, `code` varchar(50) NOT NULL, `date` date NOT NULL,
  `req_id` int NULL, `user_id` int NOT NULL, `receiver` varchar(100) NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `created_at` timestamp NULL, `updated_at` timestamp NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `wh_gen_out_det` (
  `id` int NOT NULL AUTO_INCREMENT, `main_id` int NOT NULL, `req_det_id` int NULL,
  `item_id` int NOT NULL, `qty` int NOT NULL, `unit_cost` decimal(18,4) DEFAULT '0.0000',
  `cost_center` varchar(30) NULL, `machine_id` int NULL, `asset_id` int NULL,
  PRIMARY KEY (`id`), KEY `idx_god_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- ACCOUNTING ----------
CREATE TABLE IF NOT EXISTS `acc_coa` (
  `id` int NOT NULL AUTO_INCREMENT, `code` varchar(20) NOT NULL, `name` varchar(150) NOT NULL,
  `acc_group` varchar(30) NOT NULL COMMENT 'ASSET|LIABILITY|EQUITY|REVENUE|COGS|EXPENSE',
  `parent_id` int NULL, `postable` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `acc_period` (
  `id` int NOT NULL AUTO_INCREMENT, `period` char(6) NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'OPEN' COMMENT 'OPEN|CLOSED|LOCKED',
  PRIMARY KEY (`id`), UNIQUE KEY `period` (`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `acc_journal_main` (
  `id` int NOT NULL AUTO_INCREMENT, `code` varchar(50) NOT NULL, `date` date NOT NULL,
  `period` char(6) NOT NULL, `jrn_type` varchar(10) NULL,
  `ref_type` varchar(30) NULL, `ref_id` int NULL, `descrip` varchar(300) NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|POSTED|REVERSED',
  `user_id` int NOT NULL, `created_at` timestamp NULL, `updated_at` timestamp NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`), KEY `idx_jrn_period` (`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `acc_journal_det` (
  `id` int NOT NULL AUTO_INCREMENT, `main_id` int NOT NULL, `coa_id` int NOT NULL,
  `debit` decimal(18,2) DEFAULT '0.00', `credit` decimal(18,2) DEFAULT '0.00',
  `memo` varchar(200) NULL, PRIMARY KEY (`id`), KEY `idx_jd_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `acc_ap_pay_main` (
  `id` int NOT NULL AUTO_INCREMENT, `code` varchar(50) NOT NULL, `date` date NOT NULL,
  `ven_id` int NOT NULL, `amount` decimal(18,2) NOT NULL, `user_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `created_at` timestamp NULL, `updated_at` timestamp NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `acc_ap_pay_det` (
  `id` int NOT NULL AUTO_INCREMENT, `main_id` int NOT NULL, `inv_id` int NOT NULL,
  `amount` decimal(18,2) NOT NULL, PRIMARY KEY (`id`), KEY `idx_apd_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `acc_ar_rec_main` (
  `id` int NOT NULL AUTO_INCREMENT, `code` varchar(50) NOT NULL, `date` date NOT NULL,
  `cus_id` int NOT NULL, `amount` decimal(18,2) NOT NULL, `user_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `created_at` timestamp NULL, `updated_at` timestamp NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `acc_ar_rec_det` (
  `id` int NOT NULL AUTO_INCREMENT, `main_id` int NOT NULL, `inv_id` int NOT NULL,
  `amount` decimal(18,2) NOT NULL, PRIMARY KEY (`id`), KEY `idx_ard_main` (`main_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- COSTING & ASSET ----------
CREATE TABLE IF NOT EXISTS `cst_rate` (
  `id` int NOT NULL AUTO_INCREMENT, `period` char(6) NOT NULL,
  `rate_type` varchar(10) NOT NULL COMMENT 'LABOR|FOH', `process_id` int NULL,
  `rate_per_hour` decimal(18,4) NOT NULL, PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cst_cogm` (
  `id` int NOT NULL AUTO_INCREMENT, `period` char(6) NOT NULL, `wo_id` int NOT NULL,
  `material_cost` decimal(18,2) DEFAULT '0.00', `labor_cost` decimal(18,2) DEFAULT '0.00',
  `foh_cost` decimal(18,2) DEFAULT '0.00', `subcont_cost` decimal(18,2) DEFAULT '0.00',
  `scrap_recovery` decimal(18,2) DEFAULT '0.00', `total` decimal(18,2) DEFAULT '0.00',
  `unit_cost` decimal(18,4) DEFAULT '0.0000',
  PRIMARY KEY (`id`), UNIQUE KEY `uq_cogm` (`period`,`wo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ast_main` (
  `id` int NOT NULL AUTO_INCREMENT, `code` varchar(30) NOT NULL, `categ_id` int NOT NULL,
  `name` varchar(150) NOT NULL, `acq_date` date NOT NULL, `acq_cost` decimal(18,2) NOT NULL,
  `useful_life` smallint NOT NULL, `po_id` int NULL COMMENT 'asset dari pembelian',
  `gr_detail_id` int NULL, `machine_id` int NULL,
  `status` varchar(20) NOT NULL DEFAULT 'ACTIVE' COMMENT 'ACTIVE|DISPOSED|TRANSFERRED',
  `created_at` timestamp NULL, `updated_at` timestamp NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ast_depre` (
  `id` int NOT NULL AUTO_INCREMENT, `ast_id` int NOT NULL, `period` char(6) NOT NULL,
  `amount` decimal(18,2) NOT NULL, `journal_id` int NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_dep` (`ast_id`,`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
