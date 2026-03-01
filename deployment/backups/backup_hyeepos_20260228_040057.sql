-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: hyeepos
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

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
-- Table structure for table `akun_coa`
--

DROP TABLE IF EXISTS `akun_coa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `akun_coa` (
  `akun_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `toko_id` bigint(20) NOT NULL,
  `kode_akun` varchar(30) NOT NULL,
  `nama_akun` varchar(120) NOT NULL,
  `tipe` enum('asset','liability','equity','revenue','expense') NOT NULL,
  `parent_id` bigint(20) DEFAULT NULL,
  `is_header` tinyint(1) NOT NULL DEFAULT 0,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `system_flag` tinyint(1) NOT NULL DEFAULT 1,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`akun_id`),
  UNIQUE KEY `uq_coa_toko_kode` (`toko_id`,`kode_akun`),
  KEY `idx_coa_toko` (`toko_id`),
  KEY `idx_coa_parent` (`parent_id`),
  CONSTRAINT `fk_coa_parent` FOREIGN KEY (`parent_id`) REFERENCES `akun_coa` (`akun_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_coa_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1369 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akun_coa`
--

LOCK TABLES `akun_coa` WRITE;
/*!40000 ALTER TABLE `akun_coa` DISABLE KEYS */;
INSERT INTO `akun_coa` VALUES (1,3,'1101','Kas','asset',NULL,0,1,1,'2026-02-26 18:19:56'),(2,3,'1102','Bank/QRIS','asset',NULL,0,1,1,'2026-02-26 18:19:56'),(3,3,'1103','Piutang Usaha','asset',NULL,0,1,1,'2026-02-26 18:19:56'),(4,3,'1201','Persediaan','asset',NULL,0,1,1,'2026-02-26 18:19:56'),(5,3,'2101','Hutang Dagang','liability',NULL,0,1,1,'2026-02-26 18:19:56'),(6,3,'4101','Penjualan','revenue',NULL,0,1,1,'2026-02-26 18:19:56'),(7,3,'4201','Pendapatan Selisih Kas','revenue',NULL,0,1,1,'2026-02-26 18:19:56'),(8,3,'5101','Beban Selisih Kas','expense',NULL,0,1,1,'2026-02-26 18:19:56');
/*!40000 ALTER TABLE `akun_coa` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_log` (
  `audit_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `toko_id` bigint(20) NOT NULL,
  `pengguna_id` bigint(20) DEFAULT NULL,
  `aksi` enum('insert','update','delete','login','logout') NOT NULL,
  `tabel` varchar(100) NOT NULL,
  `record_id` bigint(20) DEFAULT NULL,
  `data_lama` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data_lama`)),
  `data_baru` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data_baru`)),
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `device_id` bigint(20) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`audit_id`),
  KEY `fk_audit_toko` (`toko_id`),
  KEY `fk_audit_pengguna` (`pengguna_id`),
  CONSTRAINT `fk_audit_pengguna` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`pengguna_id`),
  CONSTRAINT `fk_audit_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_log`
--

LOCK TABLES `audit_log` WRITE;
/*!40000 ALTER TABLE `audit_log` DISABLE KEYS */;
INSERT INTO `audit_log` VALUES (1,3,8,'insert','penjualan',1,NULL,'{\"penjualan_id\":1,\"nomor_invoice\":\"POS-20260223-7171\"}','2026-02-23 04:59:41',32,'::1'),(2,3,8,'insert','penjualan',2,NULL,'{\"penjualan_id\":2,\"nomor_invoice\":\"POS-20260225-2180\"}','2026-02-25 08:32:50',32,'::1'),(3,3,8,'insert','penjualan',3,NULL,'{\"penjualan_id\":3,\"nomor_invoice\":\"POS-20260225-4618\"}','2026-02-25 08:33:35',32,'::1'),(4,3,8,'insert','penjualan',4,NULL,'{\"penjualan_id\":4,\"nomor_invoice\":\"POS-20260225-0041\"}','2026-02-25 08:38:55',32,'::1'),(5,3,6,'insert','penjualan',5,NULL,'{\"penjualan_id\":5,\"nomor_invoice\":\"POS-20260225-4565\"}','2026-02-25 12:02:49',32,'::1'),(6,3,6,'insert','penjualan',6,NULL,'{\"penjualan_id\":6,\"nomor_invoice\":\"POS-20260225-8746\"}','2026-02-25 12:11:33',32,'::1'),(7,3,6,'insert','penjualan',7,NULL,'{\"penjualan_id\":7,\"nomor_invoice\":\"POS-20260225-7748\"}','2026-02-25 12:18:57',32,'::1'),(8,3,6,'insert','penjualan',8,NULL,'{\"penjualan_id\":8,\"nomor_invoice\":\"POS-20260226-2101\"}','2026-02-26 04:54:30',32,'::1'),(9,3,6,'insert','penjualan',9,NULL,'{\"penjualan_id\":9,\"nomor_invoice\":\"POS-20260226-5283\"}','2026-02-26 05:03:57',32,'::1'),(10,3,6,'insert','penjualan',10,NULL,'{\"penjualan_id\":10,\"nomor_invoice\":\"POS-20260226-9966\"}','2026-02-26 05:07:25',32,'::1'),(11,3,6,'insert','penjualan',11,NULL,'{\"penjualan_id\":11,\"nomor_invoice\":\"POS-20260226-6709\"}','2026-02-26 05:09:40',32,'::1'),(12,3,8,'insert','penjualan',12,NULL,'{\"penjualan_id\":12,\"nomor_invoice\":\"POS-20260226-5884\"}','2026-02-26 06:17:34',32,'::1'),(13,3,8,'insert','penjualan',13,NULL,'{\"penjualan_id\":13,\"nomor_invoice\":\"POS-20260226-5754\"}','2026-02-26 06:17:34',32,'::1'),(14,3,8,'insert','kasir_tutup',1,NULL,'{\"shift_id\":1,\"kas_sistem\":0,\"kas_fisik\":500000,\"selisih\":500000}','2026-02-26 18:24:03',32,'::1'),(15,3,8,'insert','penjualan',14,NULL,'{\"penjualan_id\":14,\"nomor_invoice\":\"POS-20260226-5827\"}','2026-02-26 18:29:32',32,'::1'),(16,3,8,'insert','penjualan',15,NULL,'{\"penjualan_id\":15,\"nomor_invoice\":\"POS-20260226-3134\"}','2026-02-26 18:30:04',32,'::1'),(17,3,8,'update','proses_data',0,NULL,'{\"proses\":\"sync_piutang_status\",\"hasil\":{\"affected_rows\":0}}','2026-02-26 18:30:44',32,'::1'),(18,3,8,'update','proses_data',0,NULL,'{\"proses\":\"rebuild_piutang_sisa\",\"hasil\":{\"affected_rows\":0}}','2026-02-26 18:30:50',32,'::1'),(19,3,8,'update','proses_data',0,NULL,'{\"proses\":\"sync_poin_member\",\"hasil\":{\"affected_rows\":1}}','2026-02-26 18:30:53',32,'::1'),(20,3,8,'insert','penjualan',16,NULL,'{\"penjualan_id\":16,\"nomor_invoice\":\"POS-20260226-4973\"}','2026-02-26 18:31:36',32,'::1'),(21,3,8,'update','proses_data',0,NULL,'{\"proses\":\"sync_piutang_status\",\"hasil\":{\"affected_rows\":0}}','2026-02-26 18:32:09',32,'::1'),(22,3,8,'update','proses_data',0,NULL,'{\"proses\":\"rebuild_piutang_sisa\",\"hasil\":{\"affected_rows\":0}}','2026-02-26 18:32:11',32,'::1'),(23,3,8,'update','proses_data',0,NULL,'{\"proses\":\"sync_poin_member\",\"hasil\":{\"affected_rows\":0}}','2026-02-26 18:32:12',32,'::1'),(24,3,8,'insert','penjualan',17,NULL,'{\"penjualan_id\":17,\"nomor_invoice\":\"POS-20260226-3326\"}','2026-02-26 18:33:24',32,'::1'),(25,3,8,'insert','penjualan',18,NULL,'{\"penjualan_id\":18,\"nomor_invoice\":\"POS-20260226-4641\"}','2026-02-26 18:50:18',32,'::1'),(26,3,8,'update','proses_data',0,NULL,'{\"proses\":\"sync_piutang_status\",\"hasil\":{\"affected_rows\":0}}','2026-02-26 18:50:50',32,'::1'),(27,3,8,'update','proses_data',0,NULL,'{\"proses\":\"rebuild_piutang_sisa\",\"hasil\":{\"affected_rows\":0}}','2026-02-26 18:50:51',32,'::1'),(28,3,8,'update','proses_data',0,NULL,'{\"proses\":\"sync_poin_member\",\"hasil\":{\"affected_rows\":0}}','2026-02-26 18:50:52',32,'::1'),(29,3,8,'insert','penjualan',19,NULL,'{\"penjualan_id\":19,\"nomor_invoice\":\"POS-20260226-5801\"}','2026-02-26 18:52:23',32,'::1'),(30,3,8,'insert','penjualan',20,NULL,'{\"penjualan_id\":20,\"nomor_invoice\":\"POS-20260226-7575\"}','2026-02-26 19:03:28',32,'::1'),(31,3,8,'insert','penjualan',21,NULL,'{\"penjualan_id\":21,\"nomor_invoice\":\"POS-20260226-3339\"}','2026-02-26 19:13:26',32,'::1'),(32,3,8,'insert','penjualan',22,NULL,'{\"penjualan_id\":22,\"nomor_invoice\":\"POS-20260226-1592\"}','2026-02-26 19:14:32',32,'::1'),(33,3,8,'update','proses_data',0,NULL,'{\"proses\":\"sync_piutang_status\",\"hasil\":{\"affected_rows\":0}}','2026-02-26 19:15:17',32,'::1'),(34,3,8,'update','proses_data',0,NULL,'{\"proses\":\"rebuild_piutang_sisa\",\"hasil\":{\"affected_rows\":0}}','2026-02-26 19:15:19',32,'::1'),(35,3,8,'update','proses_data',0,NULL,'{\"proses\":\"sync_poin_member\",\"hasil\":{\"affected_rows\":0}}','2026-02-26 19:15:20',32,'::1'),(36,3,8,'insert','penjualan',23,NULL,'{\"penjualan_id\":23,\"nomor_invoice\":\"POS-20260226-7030\"}','2026-02-26 19:19:06',32,'::1'),(37,3,8,'insert','penjualan',24,NULL,'{\"penjualan_id\":24,\"nomor_invoice\":\"POS-20260226-4424\"}','2026-02-26 19:26:20',32,'::1'),(38,3,8,'insert','penjualan',25,NULL,'{\"penjualan_id\":25,\"nomor_invoice\":\"POS-20260226-1446\"}','2026-02-26 19:26:20',32,'::1'),(39,3,8,'insert','penjualan',28,NULL,'{\"penjualan_id\":28,\"nomor_invoice\":\"POS-20260227-8356\"}','2026-02-27 17:04:57',32,'::1'),(40,3,8,'insert','penjualan',29,NULL,'{\"penjualan_id\":29,\"nomor_invoice\":\"POS-20260227-9196\"}','2026-02-27 18:48:10',32,'::1'),(41,3,8,'update','proses_data',0,NULL,'{\"proses\":\"sync_piutang_status\",\"hasil\":{\"affected_rows\":0}}','2026-02-27 19:12:58',32,'::1'),(42,3,8,'update','proses_data',0,NULL,'{\"proses\":\"rebuild_piutang_sisa\",\"hasil\":{\"affected_rows\":0}}','2026-02-27 19:13:00',32,'::1'),(43,3,8,'update','proses_data',0,NULL,'{\"proses\":\"sync_poin_member\",\"hasil\":{\"affected_rows\":0}}','2026-02-27 19:13:01',32,'::1');
/*!40000 ALTER TABLE `audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cash_movement`
--

DROP TABLE IF EXISTS `cash_movement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cash_movement` (
  `movement_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `toko_id` bigint(20) NOT NULL,
  `shift_id` bigint(20) NOT NULL,
  `kasir_id` bigint(20) NOT NULL,
  `tipe` enum('in','out') NOT NULL,
  `kategori` varchar(100) NOT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `catatan` varchar(255) DEFAULT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`movement_id`),
  KEY `idx_cash_move_shift` (`shift_id`),
  KEY `idx_cash_move_toko` (`toko_id`,`dibuat_pada`),
  KEY `fk_cash_move_kasir` (`kasir_id`),
  CONSTRAINT `fk_cash_move_kasir` FOREIGN KEY (`kasir_id`) REFERENCES `pengguna` (`pengguna_id`),
  CONSTRAINT `fk_cash_move_shift` FOREIGN KEY (`shift_id`) REFERENCES `kasir_shift` (`shift_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cash_move_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cash_movement`
--

LOCK TABLES `cash_movement` WRITE;
/*!40000 ALTER TABLE `cash_movement` DISABLE KEYS */;
INSERT INTO `cash_movement` VALUES (1,3,11,8,'out','coba',500.00,'coba1','2026-02-27 19:01:34');
/*!40000 ALTER TABLE `cash_movement` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `device`
--

DROP TABLE IF EXISTS `device`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `device` (
  `device_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `toko_id` bigint(20) NOT NULL,
  `nama_device` varchar(100) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `tipe` enum('kasir','admin','gudang') DEFAULT 'kasir',
  `aktif` tinyint(1) DEFAULT 1,
  `terakhir_login` timestamp NULL DEFAULT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`device_id`),
  UNIQUE KEY `uq_device_ip` (`toko_id`,`ip_address`),
  CONSTRAINT `fk_device_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `device`
--

LOCK TABLES `device` WRITE;
/*!40000 ALTER TABLE `device` DISABLE KEYS */;
INSERT INTO `device` VALUES (32,3,'AlbaOne31','::1','admin',1,NULL,'2026-02-17 12:28:24','2026-02-17 19:28:24','2026-02-19 05:12:53',NULL);
/*!40000 ALTER TABLE `device` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gudang`
--

DROP TABLE IF EXISTS `gudang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gudang` (
  `gudang_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `toko_id` bigint(20) NOT NULL,
  `nama_gudang` varchar(100) NOT NULL,
  `aktif` tinyint(1) DEFAULT 1,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`gudang_id`),
  UNIQUE KEY `uq_gudang_toko` (`toko_id`,`nama_gudang`),
  CONSTRAINT `fk_gudang_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gudang`
--

LOCK TABLES `gudang` WRITE;
/*!40000 ALTER TABLE `gudang` DISABLE KEYS */;
INSERT INTO `gudang` VALUES (1,3,'Gudang Utama',1,NULL);
/*!40000 ALTER TABLE `gudang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hutang_supplier`
--

DROP TABLE IF EXISTS `hutang_supplier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hutang_supplier` (
  `hutang_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `toko_id` bigint(20) NOT NULL DEFAULT 0,
  `supplier_id` bigint(20) NOT NULL,
  `supplier` varchar(150) NOT NULL,
  `invoice` varchar(80) NOT NULL,
  `sisa` decimal(15,2) NOT NULL DEFAULT 0.00,
  `due_date` date DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'tercatat',
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`hutang_id`),
  KEY `idx_toko` (`toko_id`),
  KEY `idx_supplier` (`supplier_id`),
  CONSTRAINT `fk_hutang_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hutang_supplier`
--

LOCK TABLES `hutang_supplier` WRITE;
/*!40000 ALTER TABLE `hutang_supplier` DISABLE KEYS */;
INSERT INTO `hutang_supplier` VALUES (1,3,2,'PT SETIA ABADI','RC-20260220-4172',0.00,'2026-03-12','lunas','2026-02-20 01:30:23'),(2,3,1,'PT COBA COBA','PO-20260220-193',0.00,'2026-03-22','lunas','2026-02-20 02:04:01'),(3,3,2,'PT SETIA ABADI','PO-20260220-425',0.00,'2026-02-21','lunas','2026-02-20 12:19:50'),(4,3,1,'PT COBA COBA','PO-20260220-156',0.00,'2026-03-04','lunas','2026-02-20 21:34:24'),(5,3,1,'PT COBA COBA','PO-20260220-751',0.00,'2026-03-04','lunas','2026-02-23 04:24:58');
/*!40000 ALTER TABLE `hutang_supplier` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_hutang_status_auto_ins` BEFORE INSERT ON `hutang_supplier` FOR EACH ROW BEGIN
  IF NEW.sisa <= 0 THEN
    SET NEW.sisa = 0;
    SET NEW.status = 'lunas';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_hutang_status_auto` BEFORE UPDATE ON `hutang_supplier` FOR EACH ROW BEGIN
  IF NEW.sisa <= 0 THEN
    SET NEW.sisa = 0;
    SET NEW.status = 'lunas';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `jurnal_counter`
--

DROP TABLE IF EXISTS `jurnal_counter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jurnal_counter` (
  `toko_id` bigint(20) NOT NULL,
  `tanggal` date NOT NULL,
  `last_seq` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`toko_id`,`tanggal`),
  CONSTRAINT `fk_jcounter_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jurnal_counter`
--

LOCK TABLES `jurnal_counter` WRITE;
/*!40000 ALTER TABLE `jurnal_counter` DISABLE KEYS */;
INSERT INTO `jurnal_counter` VALUES (3,'2026-02-27',1,'2026-02-28 02:02:11');
/*!40000 ALTER TABLE `jurnal_counter` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jurnal_detail`
--

DROP TABLE IF EXISTS `jurnal_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jurnal_detail` (
  `detail_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `jurnal_id` bigint(20) NOT NULL,
  `akun_id` bigint(20) NOT NULL,
  `deskripsi` varchar(255) DEFAULT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `kredit` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`detail_id`),
  KEY `idx_detail_jurnal` (`jurnal_id`),
  KEY `idx_detail_akun` (`akun_id`),
  CONSTRAINT `fk_detail_akun` FOREIGN KEY (`akun_id`) REFERENCES `akun_coa` (`akun_id`),
  CONSTRAINT `fk_detail_jurnal` FOREIGN KEY (`jurnal_id`) REFERENCES `jurnal_umum` (`jurnal_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jurnal_detail`
--

LOCK TABLES `jurnal_detail` WRITE;
/*!40000 ALTER TABLE `jurnal_detail` DISABLE KEYS */;
INSERT INTO `jurnal_detail` VALUES (32,15,1,'Penjualan tunai',38850.00,0.00),(33,15,6,'Pendapatan penjualan',0.00,38850.00),(34,16,1,'Penjualan tunai',9607.37,0.00),(35,16,2,'Penjualan non tunai',128811.63,0.00),(36,16,6,'Pendapatan penjualan',0.00,138419.00),(37,17,1,'Penjualan tunai',2093415.70,0.00),(38,17,6,'Pendapatan penjualan',0.00,2093415.70),(39,18,4,'Pembelian barang',2013147.70,0.00),(40,18,1,'Pembelian tunai',0.00,71077.70),(41,18,5,'Pembelian tempo',0.00,1942070.00),(42,19,4,'Pembelian barang',744810.00,0.00),(43,19,1,'Pembelian tunai',0.00,1110.00),(44,19,5,'Pembelian tempo',0.00,743700.00),(45,20,4,'Pembelian barang',77700.00,0.00),(46,20,5,'Pembelian tempo',0.00,77700.00),(47,21,5,'Pembayaran hutang supplier',1420000.00,0.00),(48,21,1,'Kas keluar pembayaran hutang supplier',0.00,1420000.00),(49,22,5,'Pembayaran hutang supplier',2191700.00,0.00),(50,22,1,'Kas keluar pembayaran hutang supplier',0.00,2191700.00),(55,27,8,'Beban selisih kas',1459.00,0.00),(56,27,1,'Selisih kas kurang',0.00,1459.00);
/*!40000 ALTER TABLE `jurnal_detail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jurnal_umum`
--

DROP TABLE IF EXISTS `jurnal_umum`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jurnal_umum` (
  `jurnal_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `toko_id` bigint(20) NOT NULL,
  `tanggal` date NOT NULL,
  `nomor_jurnal` varchar(50) NOT NULL,
  `sumber` enum('penjualan','pembelian','piutang_pembayaran','hutang_pembayaran','manual','closing_kasir') NOT NULL,
  `referensi_tabel` varchar(100) DEFAULT NULL,
  `referensi_id` bigint(20) DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `total_debit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_kredit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `dibuat_oleh` bigint(20) DEFAULT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`jurnal_id`),
  UNIQUE KEY `uq_jurnal_toko_nomor` (`toko_id`,`nomor_jurnal`),
  KEY `idx_jurnal_tanggal` (`toko_id`,`tanggal`),
  KEY `idx_jurnal_referensi` (`referensi_tabel`,`referensi_id`),
  KEY `fk_jurnal_pengguna` (`dibuat_oleh`),
  CONSTRAINT `fk_jurnal_pengguna` FOREIGN KEY (`dibuat_oleh`) REFERENCES `pengguna` (`pengguna_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_jurnal_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jurnal_umum`
--

LOCK TABLES `jurnal_umum` WRITE;
/*!40000 ALTER TABLE `jurnal_umum` DISABLE KEYS */;
INSERT INTO `jurnal_umum` VALUES (15,3,'2026-02-23','JRN-20260223-0001','penjualan','generated_pos',NULL,'0',38850.00,38850.00,8,'2026-02-26 18:32:23'),(16,3,'2026-02-25','JRN-20260225-0001','penjualan','generated_pos',NULL,'0',138419.00,138419.00,8,'2026-02-26 18:32:23'),(17,3,'2026-02-26','JRN-20260226-0002','penjualan','generated_pos',NULL,'0',2093415.70,2093415.70,8,'2026-02-26 18:32:23'),(18,3,'2026-02-20','JRN-20260220-0001','pembelian','generated_pos',NULL,'0',2013147.70,2013147.70,8,'2026-02-26 18:32:23'),(19,3,'2026-02-21','JRN-20260221-0001','pembelian','generated_pos',NULL,'0',744810.00,744810.00,8,'2026-02-26 18:32:23'),(20,3,'2026-02-23','JRN-20260223-0002','pembelian','generated_pos',NULL,'0',77700.00,77700.00,8,'2026-02-26 18:32:23'),(21,3,'2026-02-20','JRN-20260220-0002','hutang_pembayaran','generated_pos',NULL,'0',1420000.00,1420000.00,8,'2026-02-26 18:32:23'),(22,3,'2026-02-24','JRN-20260224-0001','hutang_pembayaran','generated_pos',NULL,'0',2191700.00,2191700.00,8,'2026-02-26 18:32:23'),(27,3,'2026-02-27','JRN-20260227-0001','closing_kasir','kasir_shift',11,'Posting selisih kas shift #11',1459.00,1459.00,8,'2026-02-27 19:02:11');
/*!40000 ALTER TABLE `jurnal_umum` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kasir_shift`
--

DROP TABLE IF EXISTS `kasir_shift`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kasir_shift` (
  `shift_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `toko_id` bigint(20) NOT NULL,
  `kasir_id` bigint(20) NOT NULL,
  `device_id` bigint(20) DEFAULT NULL,
  `shift_template_id` bigint(20) DEFAULT NULL,
  `tanggal_shift` date NOT NULL,
  `jam_buka_real` datetime DEFAULT NULL,
  `jam_tutup_real` datetime DEFAULT NULL,
  `jam_buka` datetime NOT NULL DEFAULT current_timestamp(),
  `jam_tutup` datetime DEFAULT NULL,
  `modal_awal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `kas_sistem` decimal(15,2) NOT NULL DEFAULT 0.00,
  `kas_fisik` decimal(15,2) NOT NULL DEFAULT 0.00,
  `selisih` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `catatan` text DEFAULT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`shift_id`),
  KEY `idx_shift_toko_tanggal` (`toko_id`,`tanggal_shift`),
  KEY `idx_shift_status` (`status`),
  KEY `fk_shift_kasir` (`kasir_id`),
  KEY `fk_shift_device` (`device_id`),
  KEY `idx_shift_template` (`shift_template_id`),
  CONSTRAINT `fk_kasir_shift_template` FOREIGN KEY (`shift_template_id`) REFERENCES `shift_template` (`template_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_shift_device` FOREIGN KEY (`device_id`) REFERENCES `device` (`device_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_shift_kasir` FOREIGN KEY (`kasir_id`) REFERENCES `pengguna` (`pengguna_id`),
  CONSTRAINT `fk_shift_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kasir_shift`
--

LOCK TABLES `kasir_shift` WRITE;
/*!40000 ALTER TABLE `kasir_shift` DISABLE KEYS */;
INSERT INTO `kasir_shift` VALUES (1,3,8,32,79,'2026-02-27','2026-02-27 01:23:06','2026-02-27 02:28:11','2026-02-27 01:23:06','2026-02-27 02:28:11',0.00,23409.00,23409.00,0.00,'closed','','2026-02-26 18:23:06'),(3,3,8,32,1,'2026-02-28','2026-02-28 00:04:19','2026-02-28 00:12:14','2026-02-28 00:04:19','2026-02-28 00:12:14',2000.00,3959.00,3959.00,0.00,'closed','','2026-02-27 17:04:19'),(4,3,6,NULL,79,'2026-02-25','2026-02-25 19:02:49','2026-02-25 19:18:57','2026-02-25 19:02:49','2026-02-25 19:18:57',0.00,0.00,0.00,0.00,'closed','Backfill legacy from penjualan','2026-02-27 18:45:46'),(5,3,6,NULL,79,'2026-02-26','2026-02-26 11:54:30','2026-02-26 12:09:40','2026-02-26 11:54:30','2026-02-26 12:09:40',0.00,0.00,0.00,0.00,'closed','Backfill legacy from penjualan','2026-02-27 18:45:46'),(6,3,8,NULL,79,'2026-02-23','2026-02-23 11:59:41','2026-02-23 11:59:41','2026-02-23 11:59:41','2026-02-23 11:59:41',0.00,0.00,0.00,0.00,'closed','Backfill legacy from penjualan','2026-02-27 18:45:46'),(7,3,8,NULL,79,'2026-02-25','2026-02-25 15:32:50','2026-02-25 15:38:55','2026-02-25 15:32:50','2026-02-25 15:38:55',0.00,0.00,0.00,0.00,'closed','Backfill legacy from penjualan','2026-02-27 18:45:46'),(8,3,8,NULL,79,'2026-02-26','2026-02-26 13:17:34','2026-02-26 13:17:34','2026-02-26 13:17:34','2026-02-26 13:17:34',0.00,0.00,0.00,0.00,'closed','Backfill legacy from penjualan','2026-02-27 18:45:46'),(11,3,8,32,1,'2026-02-28','2026-02-28 01:46:19','2026-02-28 02:02:11','2026-02-28 01:46:19','2026-02-28 02:02:11',5000.00,6459.00,5000.00,-1459.00,'closed','coba9','2026-02-27 18:46:19');
/*!40000 ALTER TABLE `kasir_shift` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kategori_produk`
--

DROP TABLE IF EXISTS `kategori_produk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kategori_produk` (
  `kategori_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `toko_id` bigint(20) NOT NULL,
  `nama_kategori` varchar(100) NOT NULL,
  `induk_id` bigint(20) DEFAULT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`kategori_id`),
  UNIQUE KEY `uq_kategori_toko` (`toko_id`,`nama_kategori`),
  KEY `fk_kategori_induk` (`induk_id`),
  CONSTRAINT `fk_kategori_induk` FOREIGN KEY (`induk_id`) REFERENCES `kategori_produk` (`kategori_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_kategori_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kategori_produk`
--

LOCK TABLES `kategori_produk` WRITE;
/*!40000 ALTER TABLE `kategori_produk` DISABLE KEYS */;
INSERT INTO `kategori_produk` VALUES (1,3,'MINUMAN1',NULL,'2026-02-17 12:40:21','2026-02-20 20:41:26'),(2,3,'MAKANAN',NULL,'2026-02-17 12:50:49',NULL),(6,3,'minuman',NULL,'2026-02-20 20:42:15',NULL);
/*!40000 ALTER TABLE `kategori_produk` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `master_license`
--

DROP TABLE IF EXISTS `master_license`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `master_license` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `license_key` varchar(255) NOT NULL,
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `expired_at` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `license_key` (`license_key`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `master_license`
--

LOCK TABLES `master_license` WRITE;
/*!40000 ALTER TABLE `master_license` DISABLE KEYS */;
/*!40000 ALTER TABLE `master_license` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `member_level`
--

DROP TABLE IF EXISTS `member_level`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `member_level` (
  `level_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `toko_id` bigint(20) NOT NULL,
  `nama_level` varchar(50) NOT NULL,
  `minimal_poin` int(11) NOT NULL,
  `diskon_persen` decimal(5,2) DEFAULT 0.00,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`level_id`,`toko_id`),
  UNIQUE KEY `uq_level_toko` (`toko_id`,`nama_level`),
  CONSTRAINT `fk_level_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `member_level`
--

LOCK TABLES `member_level` WRITE;
/*!40000 ALTER TABLE `member_level` DISABLE KEYS */;
INSERT INTO `member_level` VALUES (1,3,'Bronze',130000,1.00,NULL),(2,3,'Silver',1000000,2.00,NULL),(3,3,'Gold',4000000,30.00,NULL),(4,3,'Platinum',5000000,20.00,NULL);
/*!40000 ALTER TABLE `member_level` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pajak`
--

DROP TABLE IF EXISTS `pajak`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pajak` (
  `pajak_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  `persen` decimal(5,2) NOT NULL DEFAULT 0.00,
  `deskripsi` varchar(255) DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `diupdate_pada` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`pajak_id`),
  UNIQUE KEY `nama` (`nama`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pajak`
--

LOCK TABLES `pajak` WRITE;
/*!40000 ALTER TABLE `pajak` DISABLE KEYS */;
INSERT INTO `pajak` VALUES (1,'PPN',11.00,'PAJAK',1,'2026-02-19 12:34:18','2026-02-19 12:34:18'),(2,'NON PAJAK',0.00,'TIDAK ADA PAJAK',1,'2026-02-19 12:34:54','2026-02-19 12:34:54');
/*!40000 ALTER TABLE `pajak` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pelanggan`
--

DROP TABLE IF EXISTS `pelanggan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pelanggan` (
  `pelanggan_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `toko_id` bigint(20) NOT NULL,
  `nama_pelanggan` varchar(150) NOT NULL,
  `telepon` varchar(50) DEFAULT NULL,
  `flat_diskon` decimal(5,2) DEFAULT 0.00,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `kode_pelanggan` varchar(50) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `jenis_customer` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`pelanggan_id`),
  KEY `fk_pelanggan_toko` (`toko_id`),
  KEY `idx_pelanggan_nama` (`nama_pelanggan`),
  CONSTRAINT `fk_pelanggan_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pelanggan`
--

LOCK TABLES `pelanggan` WRITE;
/*!40000 ALTER TABLE `pelanggan` DISABLE KEYS */;
INSERT INTO `pelanggan` VALUES (1,3,'mahi','085756541254',1.00,'2026-02-17 15:20:36',NULL,'mh01','ponorogi','Member'),(2,3,'joko','0857512154',1.00,'2026-02-24 14:34:14','2026-02-25 07:58:11','','kolo','Member');
/*!40000 ALTER TABLE `pelanggan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pelanggan_toko`
--

DROP TABLE IF EXISTS `pelanggan_toko`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pelanggan_toko` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `pelanggan_id` bigint(20) NOT NULL,
  `toko_id` bigint(20) NOT NULL,
  `level_id` bigint(20) DEFAULT NULL,
  `poin` int(11) DEFAULT 0,
  `limit_kredit` decimal(15,2) DEFAULT 0.00,
  `poin_akhir` int(11) DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `tanggal_daftar` date DEFAULT NULL,
  `masa_berlaku` int(11) DEFAULT 0 COMMENT 'hari',
  `exp` date DEFAULT NULL,
  `masa_tenggang` int(11) DEFAULT 0 COMMENT 'hari',
  `exp_poin` date DEFAULT NULL,
  `poin_awal` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pelanggan_toko` (`pelanggan_id`,`toko_id`),
  KEY `fk_pt_toko` (`toko_id`),
  KEY `fk_pt_level` (`level_id`,`toko_id`),
  CONSTRAINT `fk_pt_level` FOREIGN KEY (`level_id`, `toko_id`) REFERENCES `member_level` (`level_id`, `toko_id`),
  CONSTRAINT `fk_pt_pelanggan` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`pelanggan_id`),
  CONSTRAINT `fk_pt_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pelanggan_toko`
--

LOCK TABLES `pelanggan_toko` WRITE;
/*!40000 ALTER TABLE `pelanggan_toko` DISABLE KEYS */;
INSERT INTO `pelanggan_toko` VALUES (1,1,3,2,1,10.00,1,NULL,'2026-02-26',0,'2028-02-25',2,'0000-00-00',6000),(2,2,3,NULL,0,1.00,500,'2026-02-25 07:58:11','0000-00-00',1,'2027-02-24',3,'0000-00-00',1000);
/*!40000 ALTER TABLE `pelanggan_toko` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pembayaran`
--

DROP TABLE IF EXISTS `pembayaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pembayaran` (
  `pembayaran_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `penjualan_id` bigint(20) NOT NULL,
  `metode` enum('cash','transfer','qris','hutang') NOT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `uang_diterima` decimal(15,2) NOT NULL DEFAULT 0.00,
  `kembalian` decimal(15,2) NOT NULL DEFAULT 0.00,
  `dibayar_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`pembayaran_id`),
  KEY `fk_pembayaran_penjualan` (`penjualan_id`),
  CONSTRAINT `fk_pembayaran_penjualan` FOREIGN KEY (`penjualan_id`) REFERENCES `penjualan` (`penjualan_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pembayaran`
--

LOCK TABLES `pembayaran` WRITE;
/*!40000 ALTER TABLE `pembayaran` DISABLE KEYS */;
INSERT INTO `pembayaran` VALUES (1,1,'cash',38850.00,38850.00,0.00,'2026-02-23 04:59:41'),(2,2,'qris',3885.00,3885.00,0.00,'2026-02-25 08:32:50'),(3,3,'qris',3885.00,3885.00,0.00,'2026-02-25 08:33:35'),(4,4,'qris',33300.00,33300.00,0.00,'2026-02-25 08:38:55'),(5,5,'transfer',91464.00,91464.00,0.00,'2026-02-25 12:02:49'),(6,6,'cash',3885.00,3885.00,0.00,'2026-02-25 12:11:33'),(7,7,'cash',2000.00,6000.00,4000.00,'2026-02-25 12:18:57'),(8,8,'cash',670329.00,670329.00,0.00,'2026-02-26 04:54:30'),(9,10,'cash',80119.70,90000.00,9880.30,'2026-02-26 05:07:25'),(10,11,'cash',1980.00,2000.00,20.00,'2026-02-26 05:09:40'),(11,12,'cash',669447.00,700000.00,30553.00,'2026-02-26 06:17:34'),(12,13,'cash',669660.00,700000.00,30340.00,'2026-02-26 06:17:34'),(13,14,'cash',2000.00,3000.00,1000.00,'2026-02-26 18:29:32'),(14,15,'cash',2000.00,5000.00,3000.00,'2026-02-26 18:30:04'),(15,16,'cash',2000.00,2000.00,0.00,'2026-02-26 18:31:36'),(16,17,'cash',2000.00,10000.00,8000.00,'2026-02-26 18:33:24'),(17,18,'cash',1910.00,2000.00,90.00,'2026-02-26 18:50:18'),(18,19,'cash',1540.00,6000.00,4460.00,'2026-02-26 18:52:23'),(19,20,'cash',1959.00,5000.00,3041.00,'2026-02-26 19:03:28'),(20,21,'cash',2000.00,3000.00,1000.00,'2026-02-26 19:13:26'),(21,22,'cash',2000.00,5000.00,3000.00,'2026-02-26 19:14:32'),(22,23,'cash',2000.00,3000.00,1000.00,'2026-02-26 19:19:06'),(23,24,'cash',2000.00,3000.00,1000.00,'2026-02-26 19:26:20'),(24,25,'cash',2000.00,3000.00,1000.00,'2026-02-26 19:26:20'),(27,28,'cash',1959.00,3000.00,1041.00,'2026-02-27 17:04:57'),(28,29,'cash',1959.00,3000.00,1041.00,'2026-02-27 18:48:10');
/*!40000 ALTER TABLE `pembayaran` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_pembayaran_hutang_wajib_pelanggan` BEFORE INSERT ON `pembayaran` FOR EACH ROW BEGIN
    IF NEW.metode = 'hutang' THEN
        IF (
            SELECT pelanggan_id
            FROM penjualan
            WHERE penjualan_id = NEW.penjualan_id
        ) IS NULL THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Pembayaran hutang wajib memiliki pelanggan';
        END IF;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `pembayaran_hutang`
--

DROP TABLE IF EXISTS `pembayaran_hutang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pembayaran_hutang` (
  `bayar_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `toko_id` bigint(20) NOT NULL DEFAULT 0,
  `supplier_id` bigint(20) NOT NULL,
  `hutang_id` bigint(20) DEFAULT NULL,
  `supplier` varchar(150) NOT NULL,
  `referensi` varchar(120) DEFAULT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `kelebihan` decimal(15,2) NOT NULL DEFAULT 0.00,
  `catatan` varchar(255) DEFAULT NULL,
  `dibayar_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`bayar_id`),
  KEY `idx_toko` (`toko_id`),
  KEY `idx_supplier` (`supplier_id`),
  KEY `idx_hutang` (`hutang_id`),
  CONSTRAINT `fk_bayar_hutang` FOREIGN KEY (`hutang_id`) REFERENCES `hutang_supplier` (`hutang_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bayar_hutang_link` FOREIGN KEY (`hutang_id`) REFERENCES `hutang_supplier` (`hutang_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bayar_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pembayaran_hutang`
--

LOCK TABLES `pembayaran_hutang` WRITE;
/*!40000 ALTER TABLE `pembayaran_hutang` DISABLE KEYS */;
INSERT INTO `pembayaran_hutang` VALUES (1,3,1,1,'PT COBA COBA','',25000.00,0.00,'dikit dikit','2026-02-20 01:31:47'),(2,3,2,1,'PT SETIA ABADI','213',45000.00,0.00,'ada','2026-02-20 01:38:23'),(3,3,2,1,'PT SETIA ABADI','2131',700000.00,0.00,'adaa','2026-02-20 01:40:49'),(4,3,1,2,'PT COBA COBA','PO-20260220-193',650000.00,48930.00,'dikit dikit','2026-02-20 02:06:00'),(5,3,2,3,'PT SETIA ABADI','PO-20260220-425',671000.00,0.00,'','2026-02-24 13:48:20'),(6,3,1,4,'PT COBA COBA','PO-20260220-156',743700.00,0.00,'','2026-02-24 13:48:35'),(7,3,1,5,'PT COBA COBA','PO-20260220-751',777000.00,699300.00,'','2026-02-24 13:48:51');
/*!40000 ALTER TABLE `pembayaran_hutang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pembelian`
--

DROP TABLE IF EXISTS `pembelian`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pembelian` (
  `pembelian_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `supplier_id` bigint(20) NOT NULL,
  `toko_id` bigint(20) NOT NULL,
  `gudang_id` bigint(20) NOT NULL,
  `total` decimal(15,2) NOT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `tanggal` date DEFAULT NULL,
  `jatuh_tempo` date DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `pajak` decimal(15,2) NOT NULL DEFAULT 0.00,
  `diskon` decimal(15,2) NOT NULL DEFAULT 0.00,
  `ongkir` decimal(15,2) NOT NULL DEFAULT 0.00,
  `catatan` varchar(255) DEFAULT NULL,
  `tipe_faktur` enum('cash','tempo') NOT NULL DEFAULT 'cash',
  `salesman` varchar(100) DEFAULT NULL,
  `tempo_hari` int(11) DEFAULT NULL,
  `jenis_ppn` varchar(20) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `nomor_faktur` varchar(80) NOT NULL,
  `po_id` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`pembelian_id`),
  KEY `fk_pembelian_supplier` (`supplier_id`),
  KEY `fk_pembelian_toko` (`toko_id`),
  KEY `fk_pembelian_gudang` (`gudang_id`),
  CONSTRAINT `fk_pembelian_gudang` FOREIGN KEY (`gudang_id`) REFERENCES `gudang` (`gudang_id`),
  CONSTRAINT `fk_pembelian_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`),
  CONSTRAINT `fk_pembelian_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pembelian`
--

LOCK TABLES `pembelian` WRITE;
/*!40000 ALTER TABLE `pembelian` DISABLE KEYS */;
INSERT INTO `pembelian` VALUES (6,1,3,1,71000.00,'2026-02-20 00:41:23','2026-02-20',NULL,71000.00,0.00,0.00,0.00,'','cash',NULL,NULL,NULL,'posted','RC-20260220-9287',NULL),(7,2,3,1,670000.00,'2026-02-20 01:30:23','2026-02-20','2026-03-12',670000.00,0.00,0.00,0.00,'','tempo',NULL,20,NULL,'posted','RC-20260220-4172',13),(8,1,3,1,601070.00,'2026-02-20 02:04:01','2026-02-20','2026-03-22',601070.00,0.00,0.00,0.00,'','tempo',NULL,30,NULL,'posted','PO-20260220-193',14),(9,2,3,1,671000.00,'2026-02-20 12:19:50','2026-02-20','2026-02-21',671000.00,0.00,0.00,0.00,'','tempo',NULL,1,NULL,'posted','PO-20260220-425',15),(10,1,3,1,77.70,'2026-02-20 13:42:24','2026-02-20',NULL,70.00,7.70,0.00,0.00,'','cash',NULL,0,NULL,'posted','PO-20260220-091',23),(11,1,3,1,743700.00,'2026-02-20 21:34:24','2026-02-20','2026-03-04',670000.00,73700.00,0.00,0.00,'a','tempo',NULL,12,NULL,'posted','PO-20260220-156',16),(12,2,3,1,1110.00,'2026-02-20 21:37:04','2026-02-20',NULL,1000.00,110.00,0.00,0.00,'','cash',NULL,0,NULL,'posted','PO-20260220-518',17),(13,1,3,1,77700.00,'2026-02-23 04:24:58','2026-02-23','2026-03-04',70000.00,7700.00,0.00,0.00,'','tempo',NULL,12,NULL,'posted','PO-20260220-751',24);
/*!40000 ALTER TABLE `pembelian` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pembelian_detail`
--

DROP TABLE IF EXISTS `pembelian_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pembelian_detail` (
  `detail_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `pembelian_id` bigint(20) NOT NULL,
  `produk_id` bigint(20) NOT NULL,
  `qty` int(11) NOT NULL,
  `harga_beli` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `nama_barang` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`detail_id`),
  KEY `fk_detail_pembelian` (`pembelian_id`),
  KEY `fk_detail_produk_beli` (`produk_id`),
  CONSTRAINT `fk_detail_pembelian` FOREIGN KEY (`pembelian_id`) REFERENCES `pembelian` (`pembelian_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_detail_produk_beli` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`produk_id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pembelian_detail`
--

LOCK TABLES `pembelian_detail` WRITE;
/*!40000 ALTER TABLE `pembelian_detail` DISABLE KEYS */;
INSERT INTO `pembelian_detail` VALUES (3,6,4,1,70000.00,70000.00,'SGM 3PLUS MADU 800GR + 8g'),(4,6,3,1,1000.00,1000.00,'SOFTEX 2PH'),(5,6,4,0,0.00,0.00,'SGM 3PLUS MADU 800GR + 8g'),(6,6,3,0,0.00,0.00,'SOFTEX 2PH'),(7,7,2,1,600000.00,600000.00,'jamu 1'),(8,7,4,1,70000.00,70000.00,'SGM 3PLUS MADU 800GR + 8g'),(9,7,2,0,0.00,0.00,'jamu 1'),(10,7,4,0,0.00,0.00,'SGM 3PLUS MADU 800GR + 8g'),(11,8,2,1,600000.00,600000.00,'jamu 1'),(12,8,1,1,70.00,70.00,'SGM 3PLUS MADU 800GR'),(13,8,3,1,1000.00,1000.00,'SOFTEX 2PH'),(14,9,2,1,600000.00,600000.00,'jamu 1'),(15,9,4,1,70000.00,70000.00,'SGM 3PLUS MADU 800GR + 8g'),(16,9,3,1,1000.00,1000.00,'SOFTEX 2PH'),(17,10,1,1,70.00,70.00,'SGM 3PLUS MADU 800GR'),(18,11,4,1,70000.00,70000.00,'SGM 3PLUS MADU 800GR + 8g'),(19,11,2,1,600000.00,600000.00,'jamu 1'),(20,12,3,1,1000.00,1000.00,'SOFTEX 2PH'),(21,13,4,1,70000.00,70000.00,'SGM 3PLUS MADU 800GR + 8g');
/*!40000 ALTER TABLE `pembelian_detail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pengguna`
--

DROP TABLE IF EXISTS `pengguna`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pengguna` (
  `pengguna_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `toko_id` bigint(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `peran` enum('owner','manager','kasir','gudang') NOT NULL,
  `aktif` tinyint(1) DEFAULT 1,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`pengguna_id`),
  UNIQUE KEY `uq_email_toko` (`toko_id`,`email`),
  CONSTRAINT `fk_pengguna_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengguna`
--

LOCK TABLES `pengguna` WRITE;
/*!40000 ALTER TABLE `pengguna` DISABLE KEYS */;
INSERT INTO `pengguna` VALUES (5,3,'muhyi31','admin_3@local','$2y$10$j699qVY9C8tyTYXFrAfYheXAjNX13Zi4KHDKJWAHp9JgdEPUW9s5i','owner',1,'2026-02-17 12:28:04',NULL),(6,3,'muhyi31','www.sergiomuhye@gmail.com','$2y$10$/SzjxWhOvd6okx/gMPkC0.kxLrWB/QmlCSSco0qDYPz618yq/kgEm','owner',1,'2026-02-17 12:28:24',NULL),(7,3,'admin asli','hyeecode@gmail.com','$2y$10$fnJQErsZOVhNd5TcBOr5Huz.t2zTtSdnT76sWWepZoRwPKxLkPwuO','owner',1,'2026-02-18 21:31:09',NULL),(8,3,'Kasir1','kasir1@gmail.com','$2y$10$CCHqwQP3vxlG6h40rngDEe1WokHB02dE82ufBt5rB1B9lqAc6xcki','kasir',1,'2026-02-18 22:31:42',NULL),(9,3,'manager','manager@gmail.com','$2y$10$BrBX2NsNJG0C3yMDn2opteLjOI5ZeMfZ6rmbNB0UmhCNQqCbNyYSG','manager',1,'2026-02-18 22:34:45',NULL),(10,3,'Mas gudang','gudang@gmail.com','$2y$10$hJe3WoJTB7KivsrF5qU5yOX13ir2fQegjx8.tEofnZWOFzlcQAjW2','gudang',1,'2026-02-18 22:35:59',NULL);
/*!40000 ALTER TABLE `pengguna` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `penjualan`
--

DROP TABLE IF EXISTS `penjualan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `penjualan` (
  `penjualan_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `nomor_invoice` varchar(100) NOT NULL,
  `kasir_id` bigint(20) NOT NULL,
  `pelanggan_id` bigint(20) DEFAULT NULL,
  `toko_id` bigint(20) NOT NULL,
  `gudang_id` bigint(20) NOT NULL,
  `shift_id` bigint(20) DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `diskon` decimal(15,2) DEFAULT 0.00,
  `total_akhir` decimal(15,2) NOT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`penjualan_id`),
  UNIQUE KEY `uq_invoice_toko` (`toko_id`,`nomor_invoice`),
  KEY `fk_penjualan_kasir` (`kasir_id`),
  KEY `fk_penjualan_pelanggan` (`pelanggan_id`),
  KEY `fk_penjualan_gudang` (`gudang_id`),
  KEY `idx_penjualan_tanggal` (`toko_id`,`dibuat_pada`),
  KEY `idx_penjualan_shift` (`shift_id`),
  CONSTRAINT `fk_penjualan_gudang` FOREIGN KEY (`gudang_id`) REFERENCES `gudang` (`gudang_id`),
  CONSTRAINT `fk_penjualan_kasir` FOREIGN KEY (`kasir_id`) REFERENCES `pengguna` (`pengguna_id`),
  CONSTRAINT `fk_penjualan_pelanggan` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`pelanggan_id`),
  CONSTRAINT `fk_penjualan_shift` FOREIGN KEY (`shift_id`) REFERENCES `kasir_shift` (`shift_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_penjualan_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `penjualan`
--

LOCK TABLES `penjualan` WRITE;
/*!40000 ALTER TABLE `penjualan` DISABLE KEYS */;
INSERT INTO `penjualan` VALUES (1,'POS-20260223-7171',8,NULL,3,1,6,35000.00,0.00,38850.00,'2026-02-23 04:59:41'),(2,'POS-20260225-2180',8,1,3,1,7,3500.00,0.00,3885.00,'2026-02-25 08:32:50'),(3,'POS-20260225-4618',8,1,3,1,7,3500.00,0.00,3885.00,'2026-02-25 08:33:35'),(4,'POS-20260225-0041',8,1,3,1,7,30000.00,0.00,33300.00,'2026-02-25 08:38:55'),(5,'POS-20260225-4565',6,1,3,1,4,103000.00,20600.00,91464.00,'2026-02-25 12:02:49'),(6,'POS-20260225-8746',6,NULL,3,1,4,3500.00,0.00,3885.00,'2026-02-25 12:11:33'),(7,'POS-20260225-7748',6,NULL,3,1,4,2000.00,0.00,2000.00,'2026-02-25 12:18:57'),(8,'POS-20260226-2101',6,1,3,1,5,610000.00,6100.00,670329.00,'2026-02-26 04:54:30'),(9,'POS-20260226-5283',6,1,3,1,5,2000.00,120.00,1880.00,'2026-02-26 05:03:57'),(10,'POS-20260226-9966',6,1,3,1,5,73000.00,830.00,80119.70,'2026-02-26 05:07:25'),(11,'POS-20260226-6709',6,1,3,1,5,2000.00,20.00,1980.00,'2026-02-26 05:09:40'),(12,'POS-20260226-5884',8,1,3,1,8,610000.00,6982.00,669447.00,'2026-02-26 06:17:34'),(13,'POS-20260226-5754',8,1,3,1,8,610000.00,6769.00,669660.00,'2026-02-26 06:17:34'),(14,'POS-20260226-5827',8,NULL,3,1,1,2000.00,0.00,2000.00,'2026-02-26 18:29:32'),(15,'POS-20260226-3134',8,NULL,3,1,1,2000.00,0.00,2000.00,'2026-02-26 18:30:04'),(16,'POS-20260226-4973',8,NULL,3,1,1,2000.00,0.00,2000.00,'2026-02-26 18:31:36'),(17,'POS-20260226-3326',8,NULL,3,1,1,2000.00,0.00,2000.00,'2026-02-26 18:33:24'),(18,'POS-20260226-4641',8,1,3,1,1,2000.00,90.00,1910.00,'2026-02-26 18:50:18'),(19,'POS-20260226-5801',8,1,3,1,1,2000.00,460.00,1540.00,'2026-02-26 18:52:23'),(20,'POS-20260226-7575',8,1,3,1,1,2000.00,41.00,1959.00,'2026-02-26 19:03:28'),(21,'POS-20260226-3339',8,NULL,3,1,1,2000.00,0.00,2000.00,'2026-02-26 19:13:26'),(22,'POS-20260226-1592',8,NULL,3,1,1,2000.00,0.00,2000.00,'2026-02-26 19:14:32'),(23,'POS-20260226-7030',8,NULL,3,1,1,2000.00,0.00,2000.00,'2026-02-26 19:19:06'),(24,'POS-20260226-4424',8,NULL,3,1,1,2000.00,0.00,2000.00,'2026-02-26 19:26:20'),(25,'POS-20260226-1446',8,NULL,3,1,1,2000.00,0.00,2000.00,'2026-02-26 19:26:20'),(28,'POS-20260227-8356',8,1,3,1,3,2000.00,41.00,1959.00,'2026-02-27 17:04:57'),(29,'POS-20260227-9196',8,1,3,1,11,2000.00,41.00,1959.00,'2026-02-27 18:48:10');
/*!40000 ALTER TABLE `penjualan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `penjualan_detail`
--

DROP TABLE IF EXISTS `penjualan_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `penjualan_detail` (
  `detail_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `penjualan_id` bigint(20) NOT NULL,
  `produk_id` bigint(20) NOT NULL,
  `qty` int(11) NOT NULL,
  `tipe_harga` enum('ecer','grosir','member','reseller') NOT NULL,
  `harga_jual` decimal(15,2) NOT NULL,
  `harga_modal_snapshot` decimal(15,2) NOT NULL,
  `diskon` decimal(15,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) NOT NULL,
  PRIMARY KEY (`detail_id`),
  KEY `fk_detail_penjualan` (`penjualan_id`),
  KEY `fk_detail_produk` (`produk_id`),
  CONSTRAINT `fk_detail_penjualan` FOREIGN KEY (`penjualan_id`) REFERENCES `penjualan` (`penjualan_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_detail_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`produk_id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `penjualan_detail`
--

LOCK TABLES `penjualan_detail` WRITE;
/*!40000 ALTER TABLE `penjualan_detail` DISABLE KEYS */;
INSERT INTO `penjualan_detail` VALUES (1,1,8,10,'ecer',3500.00,2500.00,0.00,38850.00),(2,2,8,1,'ecer',3500.00,2500.00,0.00,3885.00),(3,3,8,1,'ecer',3500.00,2500.00,0.00,3885.00),(4,4,1,1,'ecer',30000.00,70.00,0.00,33300.00),(5,5,1,1,'ecer',30000.00,70.00,6000.00,26640.00),(6,5,7,1,'ecer',73000.00,70000.00,14600.00,64824.00),(7,6,8,1,'ecer',3500.00,2500.00,0.00,3885.00),(8,7,3,1,'ecer',2000.00,1000.00,0.00,2000.00),(9,8,2,1,'ecer',610000.00,600000.00,6100.00,670329.00),(10,9,3,1,'ecer',2000.00,1000.00,20.00,1980.00),(11,10,7,1,'ecer',73000.00,70000.00,730.00,80219.70),(12,11,3,1,'ecer',2000.00,1000.00,20.00,1980.00),(13,12,2,1,'ecer',610000.00,600000.00,6100.00,670329.00),(14,13,2,1,'ecer',610000.00,600000.00,6100.00,670329.00),(15,14,3,1,'ecer',2000.00,1000.00,0.00,2000.00),(16,15,3,1,'ecer',2000.00,1000.00,0.00,2000.00),(17,16,3,1,'ecer',2000.00,1000.00,0.00,2000.00),(18,17,3,1,'ecer',2000.00,1000.00,0.00,2000.00),(19,18,3,1,'ecer',2000.00,1000.00,40.00,1960.00),(20,19,3,1,'ecer',2000.00,1000.00,40.00,1960.00),(21,20,3,1,'ecer',2000.00,1000.00,40.00,1960.00),(22,21,3,1,'ecer',2000.00,1000.00,0.00,2000.00),(23,22,3,1,'ecer',2000.00,1000.00,0.00,2000.00),(24,23,3,1,'ecer',2000.00,1000.00,0.00,2000.00),(25,24,3,1,'ecer',2000.00,1000.00,0.00,2000.00),(26,25,3,1,'ecer',2000.00,1000.00,0.00,2000.00),(27,28,3,1,'ecer',2000.00,1000.00,40.00,1960.00),(28,29,3,1,'ecer',2000.00,1000.00,40.00,1960.00);
/*!40000 ALTER TABLE `penjualan_detail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `piutang`
--

DROP TABLE IF EXISTS `piutang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `piutang` (
  `piutang_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `pelanggan_id` bigint(20) NOT NULL,
  `penjualan_id` bigint(20) NOT NULL,
  `total` decimal(15,2) NOT NULL,
  `sisa` decimal(15,2) NOT NULL,
  `status` enum('lunas','belum') DEFAULT 'belum',
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`piutang_id`),
  UNIQUE KEY `uq_piutang_penjualan` (`penjualan_id`),
  KEY `fk_piutang_pelanggan` (`pelanggan_id`),
  KEY `idx_piutang_status` (`status`),
  CONSTRAINT `fk_piutang_pelanggan` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`pelanggan_id`),
  CONSTRAINT `fk_piutang_penjualan` FOREIGN KEY (`penjualan_id`) REFERENCES `penjualan` (`penjualan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `piutang`
--

LOCK TABLES `piutang` WRITE;
/*!40000 ALTER TABLE `piutang` DISABLE KEYS */;
INSERT INTO `piutang` VALUES (1,1,9,1880.00,1880.00,'belum','2026-02-26 05:03:57');
/*!40000 ALTER TABLE `piutang` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_piutang_status_auto` BEFORE UPDATE ON `piutang` FOR EACH ROW BEGIN
    IF NEW.sisa <= 0 THEN
        SET NEW.status = 'lunas';
    ELSE
        SET NEW.status = 'belum';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `piutang_pembayaran`
--

DROP TABLE IF EXISTS `piutang_pembayaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `piutang_pembayaran` (
  `pembayaran_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `piutang_id` bigint(20) NOT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `metode` enum('cash','transfer','qris') NOT NULL,
  `dibayar_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`pembayaran_id`),
  KEY `fk_pembayaran_piutang` (`piutang_id`),
  CONSTRAINT `fk_pembayaran_piutang` FOREIGN KEY (`piutang_id`) REFERENCES `piutang` (`piutang_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `piutang_pembayaran`
--

LOCK TABLES `piutang_pembayaran` WRITE;
/*!40000 ALTER TABLE `piutang_pembayaran` DISABLE KEYS */;
/*!40000 ALTER TABLE `piutang_pembayaran` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `poin_member`
--

DROP TABLE IF EXISTS `poin_member`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `poin_member` (
  `poin_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `pelanggan_id` bigint(20) NOT NULL,
  `toko_id` bigint(20) NOT NULL,
  `sumber` enum('penjualan','promo','manual') NOT NULL,
  `referensi_id` bigint(20) DEFAULT NULL,
  `poin` int(11) NOT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`poin_id`),
  KEY `idx_poin_pelanggan` (`pelanggan_id`,`toko_id`),
  KEY `fk_poin_toko` (`toko_id`),
  CONSTRAINT `fk_poin_pelanggan` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`pelanggan_id`),
  CONSTRAINT `fk_poin_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `poin_member`
--

LOCK TABLES `poin_member` WRITE;
/*!40000 ALTER TABLE `poin_member` DISABLE KEYS */;
INSERT INTO `poin_member` VALUES (1,1,3,'penjualan',2,3,'2026-02-25 08:32:50'),(2,1,3,'penjualan',3,3,'2026-02-25 08:33:35'),(3,1,3,'penjualan',4,33,'2026-02-25 08:38:55'),(4,1,3,'penjualan',5,91,'2026-02-25 12:02:49'),(5,1,3,'penjualan',8,670,'2026-02-26 04:54:30'),(6,1,3,'manual',9,-100,'2026-02-26 05:03:57'),(7,1,3,'penjualan',9,1,'2026-02-26 05:03:57'),(8,1,3,'manual',10,-100,'2026-02-26 05:07:25'),(9,1,3,'penjualan',10,80,'2026-02-26 05:07:25'),(10,1,3,'penjualan',11,1,'2026-02-26 05:09:40'),(11,1,3,'manual',12,-882,'2026-02-26 06:17:34'),(12,1,3,'penjualan',12,669,'2026-02-26 06:17:34'),(13,1,3,'manual',13,-669,'2026-02-26 06:17:34'),(14,1,3,'penjualan',13,669,'2026-02-26 06:17:34'),(15,1,3,'manual',18,-50,'2026-02-26 18:50:18'),(16,1,3,'penjualan',18,1,'2026-02-26 18:50:18'),(17,1,3,'manual',19,-420,'2026-02-26 18:52:23'),(18,1,3,'penjualan',19,1,'2026-02-26 18:52:23'),(19,1,3,'manual',20,-1,'2026-02-26 19:03:28'),(20,1,3,'penjualan',20,1,'2026-02-26 19:03:28'),(21,1,3,'manual',28,-1,'2026-02-27 17:04:57'),(22,1,3,'penjualan',28,1,'2026-02-27 17:04:57'),(23,1,3,'manual',29,-1,'2026-02-27 18:48:10'),(24,1,3,'penjualan',29,1,'2026-02-27 18:48:10');
/*!40000 ALTER TABLE `poin_member` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_poin_penjualan_valid` BEFORE INSERT ON `poin_member` FOR EACH ROW BEGIN
    IF NEW.sumber = 'penjualan' THEN
        IF NOT EXISTS (
            SELECT 1 FROM penjualan
            WHERE penjualan_id = NEW.referensi_id
        ) THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Referensi penjualan tidak valid untuk poin member';
        END IF;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `printer_setting`
--

DROP TABLE IF EXISTS `printer_setting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `printer_setting` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `device_id` varchar(100) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `nama` varchar(100) NOT NULL,
  `jenis` enum('network','usb','bluetooth') NOT NULL,
  `alamat` varchar(255) NOT NULL,
  `lebar` enum('58','80') NOT NULL DEFAULT '80',
  `driver` enum('escpos','star') NOT NULL DEFAULT 'escpos',
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_device_nama` (`device_id`,`nama`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `printer_setting`
--

LOCK TABLES `printer_setting` WRITE;
/*!40000 ALTER TABLE `printer_setting` DISABLE KEYS */;
INSERT INTO `printer_setting` VALUES (1,'32',NULL,'EPSON L1110 Series','usb','USB001','80','escpos',1,'2026-02-19 10:55:55');
/*!40000 ALTER TABLE `printer_setting` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `produk`
--

DROP TABLE IF EXISTS `produk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `produk` (
  `produk_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `toko_id` bigint(20) NOT NULL,
  `kategori_id` bigint(20) NOT NULL,
  `supplier_id` bigint(20) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `sku` varchar(100) NOT NULL,
  `merk` varchar(100) DEFAULT NULL,
  `nama_produk` varchar(200) NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `harga_modal` decimal(15,2) NOT NULL,
  `satuan` varchar(50) NOT NULL,
  `aktif` tinyint(1) DEFAULT 1,
  `min_stok` int(11) DEFAULT 0,
  `max_stok` int(11) NOT NULL DEFAULT 0,
  `pajak_persen` decimal(5,2) DEFAULT 0.00,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `is_jasa` tinyint(1) NOT NULL DEFAULT 0,
  `is_konsinyasi` tinyint(1) NOT NULL DEFAULT 0,
  `last_harga_beli` decimal(15,2) NOT NULL DEFAULT 0.00,
  `hpp_aktif` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`produk_id`),
  UNIQUE KEY `uq_sku_toko` (`toko_id`,`sku`),
  UNIQUE KEY `uq_barcode_toko` (`toko_id`,`barcode`),
  KEY `fk_produk_kategori` (`kategori_id`),
  KEY `idx_produk_aktif` (`toko_id`,`aktif`,`deleted_at`),
  KEY `idx_produk_supplier` (`supplier_id`),
  CONSTRAINT `fk_produk_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `kategori_produk` (`kategori_id`),
  CONSTRAINT `fk_produk_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`),
  CONSTRAINT `fk_produk_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `produk`
--

LOCK TABLES `produk` WRITE;
/*!40000 ALTER TABLE `produk` DISABLE KEYS */;
INSERT INTO `produk` VALUES (1,3,1,2,'8991906101019','SGM','','SGM 3PLUS MADU 800GR','p_6996bd7ae9fce.webp',70.00,'PCS',1,10,0,11.00,'2026-02-17 12:54:56',NULL,0,0,0.00,0.00),(2,3,1,1,'8999909000773','JAMU1278514','','jamu 1','p_6996bc9b00ba5.jpeg',600000.00,'PCS',1,3,0,11.00,'2026-02-19 07:01:14',NULL,0,0,60000.00,46.22),(3,3,1,1,'8998127912363','SOFTEX474294','','SOFTEX 2PH','p_699719b85142f.webp',1000.00,'PCS',1,2000,0,0.00,'2026-02-19 14:09:42',NULL,0,0,0.00,0.00),(4,3,2,2,'8997217370311','325','','SGM 3PLUS MADU 800GR + 8g','p_69979fccb5442.webp',70000.00,'PCS',1,3,0,11.00,'2026-02-19 23:42:04',NULL,0,0,70000.00,69.93),(7,3,1,2,'8999909003439','325a','','SURYA','p_6998c072e2bf5.webp',70000.00,'PCS',1,10,0,11.00,'2026-02-20 20:13:38',NULL,0,0,0.00,0.00),(8,3,6,1,'8999090501035','PRD927992','','BENG BENG MAX','p_3084e136-6c8f-4342-a48f-24f3230624fa.webp',3000.00,'pcs',1,10,3000,11.00,'2026-02-22 09:20:20',NULL,0,0,3000.00,2500.00);
/*!40000 ALTER TABLE `produk` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `produk_harga`
--

DROP TABLE IF EXISTS `produk_harga`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `produk_harga` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `produk_id` bigint(20) NOT NULL,
  `tipe` enum('ecer','grosir','member','reseller') NOT NULL DEFAULT 'ecer',
  `harga_jual` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_produk_tipe` (`produk_id`,`tipe`),
  CONSTRAINT `produk_harga_ibfk_1` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`produk_id`)
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `produk_harga`
--

LOCK TABLES `produk_harga` WRITE;
/*!40000 ALTER TABLE `produk_harga` DISABLE KEYS */;
INSERT INTO `produk_harga` VALUES (1,2,'ecer',610000.00),(2,2,'grosir',610000.00),(3,1,'ecer',30000.00),(9,3,'ecer',2000.00),(14,4,'ecer',78000.00),(15,7,'ecer',73000.00),(16,8,'ecer',3500.00),(17,8,'grosir',3400.00),(18,8,'reseller',3300.00),(19,8,'member',3200.00),(26,2,'reseller',610000.00),(27,2,'member',610000.00),(33,7,'grosir',72500.00),(34,7,'reseller',72000.00),(35,7,'member',71000.00),(37,4,'grosir',77000.00),(38,4,'reseller',76000.00),(39,4,'member',75000.00),(41,3,'grosir',2000.00),(42,3,'reseller',2000.00),(43,3,'member',1500.00),(49,1,'grosir',2000.00),(50,1,'reseller',3000.00),(51,1,'member',1500.00);
/*!40000 ALTER TABLE `produk_harga` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `produk_satuan`
--

DROP TABLE IF EXISTS `produk_satuan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `produk_satuan` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `produk_id` bigint(20) NOT NULL,
  `nama_satuan` varchar(50) NOT NULL,
  `qty_dasar` decimal(15,4) NOT NULL DEFAULT 1.0000,
  `urutan` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_produk_satuan` (`produk_id`,`nama_satuan`),
  KEY `idx_produk` (`produk_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `produk_satuan`
--

LOCK TABLES `produk_satuan` WRITE;
/*!40000 ALTER TABLE `produk_satuan` DISABLE KEYS */;
INSERT INTO `produk_satuan` VALUES (7,8,'PCS',1.0000,1),(8,8,'BOX',24.0000,2),(9,8,'KARTON',1024.0000,3),(11,1,'PCS',1.0000,1),(12,4,'PCS',1.0000,1),(13,3,'PCS',1.0000,1),(14,7,'PCS',1.0000,1),(15,2,'KARTON',124.0000,1),(16,2,'PCS',1.0000,2),(17,2,'BOX',24.0000,3);
/*!40000 ALTER TABLE `produk_satuan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promo`
--

DROP TABLE IF EXISTS `promo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `promo` (
  `promo_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `toko_id` bigint(20) NOT NULL,
  `nama_promo` varchar(100) NOT NULL,
  `tipe` enum('persen','nominal','gratis') NOT NULL,
  `nilai` decimal(15,2) NOT NULL,
  `minimal_belanja` decimal(15,2) DEFAULT 0.00,
  `berlaku_dari` datetime NOT NULL,
  `berlaku_sampai` datetime NOT NULL,
  `aktif` tinyint(1) DEFAULT 1,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`promo_id`),
  KEY `fk_promo_toko` (`toko_id`),
  CONSTRAINT `fk_promo_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promo`
--

LOCK TABLES `promo` WRITE;
/*!40000 ALTER TABLE `promo` DISABLE KEYS */;
/*!40000 ALTER TABLE `promo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promo_produk`
--

DROP TABLE IF EXISTS `promo_produk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `promo_produk` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `promo_id` bigint(20) NOT NULL,
  `produk_id` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pp_promo` (`promo_id`),
  KEY `fk_pp_produk` (`produk_id`),
  CONSTRAINT `fk_pp_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`produk_id`),
  CONSTRAINT `fk_pp_promo` FOREIGN KEY (`promo_id`) REFERENCES `promo` (`promo_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promo_produk`
--

LOCK TABLES `promo_produk` WRITE;
/*!40000 ALTER TABLE `promo_produk` DISABLE KEYS */;
/*!40000 ALTER TABLE `promo_produk` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_order`
--

DROP TABLE IF EXISTS `purchase_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_order` (
  `po_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `supplier` varchar(150) NOT NULL,
  `nomor` varchar(60) NOT NULL,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `supplier_id` bigint(20) DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `jatuh_tempo` date DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `pajak` decimal(15,2) NOT NULL DEFAULT 0.00,
  `diskon` decimal(15,2) NOT NULL DEFAULT 0.00,
  `ongkir` decimal(15,2) NOT NULL DEFAULT 0.00,
  `catatan` varchar(255) DEFAULT NULL,
  `tipe_faktur` enum('cash','tempo') NOT NULL DEFAULT 'cash',
  `salesman` varchar(100) DEFAULT NULL,
  `tempo_hari` int(11) DEFAULT NULL,
  `jenis_ppn` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`po_id`),
  UNIQUE KEY `nomor` (`nomor`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_order`
--

LOCK TABLES `purchase_order` WRITE;
/*!40000 ALTER TABLE `purchase_order` DISABLE KEYS */;
INSERT INTO `purchase_order` VALUES (12,'','PO-20260220-340',79809.00,'received','2026-02-20 00:40:52',1,'2026-02-20','2026-03-02',71000.00,7810.00,1.00,1000.00,'ada','tempo','NIAN',10,'PPN 11%'),(13,'','PO-20260220-849',748699.00,'received','2026-02-20 01:17:57',2,'2026-02-20','2026-03-12',670000.00,73700.00,1.00,5000.00,'ada','tempo','NIAN',20,'PPN 11%'),(14,'','PO-20260220-193',667286.70,'received','2026-02-20 01:54:43',1,'2026-02-20','2026-03-22',601070.00,66117.70,1.00,100.00,'ADA','tempo','NIAN',30,'PPN 11%'),(15,'','PO-20260220-425',745809.00,'received','2026-02-20 01:58:05',2,'2026-02-20','2026-02-21',671000.00,73810.00,1.00,1000.00,'ADA','tempo','NIAN',1,'PPN 11%'),(16,'','PO-20260220-156',755695.00,'received','2026-02-20 12:26:57',1,'2026-02-20','2026-03-04',670000.00,73700.00,5.00,12000.00,'ADS','tempo','AS',12,'PPN 11%'),(17,'','PO-20260220-518',1055.00,'received','2026-02-20 12:34:52',2,'2026-02-20',NULL,1000.00,110.00,55.00,0.00,'','cash','NIAN',0,'PPN 11%'),(23,'','PO-20260220-091',74.70,'received','2026-02-20 13:16:56',1,'2026-02-20',NULL,70.00,7.70,3.00,0.00,'','cash','AS',0,'PPN 11%'),(24,'','PO-20260220-751',79699.00,'received','2026-02-20 21:30:26',1,'2026-02-20','2026-03-04',70000.00,7700.00,1.00,2000.00,'d','tempo','',12,'PPN 11%');
/*!40000 ALTER TABLE `purchase_order` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_order_detail`
--

DROP TABLE IF EXISTS `purchase_order_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_order_detail` (
  `detail_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `po_id` bigint(20) NOT NULL,
  `produk_id` bigint(20) DEFAULT NULL,
  `nama_barang` varchar(200) NOT NULL,
  `qty` decimal(15,2) NOT NULL DEFAULT 0.00,
  `harga` decimal(15,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `satuan` varchar(50) DEFAULT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`detail_id`),
  KEY `idx_po` (`po_id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_order_detail`
--

LOCK TABLES `purchase_order_detail` WRITE;
/*!40000 ALTER TABLE `purchase_order_detail` DISABLE KEYS */;
INSERT INTO `purchase_order_detail` VALUES (7,12,4,'SGM 3PLUS MADU 800GR + 8g',1.00,70000.00,70000.00,NULL,'2026-02-20 00:40:52'),(8,12,3,'SOFTEX 2PH',1.00,1000.00,1000.00,NULL,'2026-02-20 00:40:52'),(9,12,4,'SGM 3PLUS MADU 800GR + 8g',0.00,0.00,0.00,NULL,'2026-02-20 00:40:52'),(10,12,3,'SOFTEX 2PH',0.00,0.00,0.00,NULL,'2026-02-20 00:40:52'),(11,13,2,'jamu 1',1.00,600000.00,600000.00,NULL,'2026-02-20 01:17:57'),(12,13,4,'SGM 3PLUS MADU 800GR + 8g',1.00,70000.00,70000.00,NULL,'2026-02-20 01:17:57'),(13,13,2,'jamu 1',0.00,0.00,0.00,NULL,'2026-02-20 01:17:57'),(14,13,4,'SGM 3PLUS MADU 800GR + 8g',0.00,0.00,0.00,NULL,'2026-02-20 01:17:57'),(15,14,2,'jamu 1',1.00,600000.00,600000.00,NULL,'2026-02-20 01:54:43'),(16,14,1,'SGM 3PLUS MADU 800GR',1.00,70.00,70.00,NULL,'2026-02-20 01:54:43'),(17,14,3,'SOFTEX 2PH',1.00,1000.00,1000.00,NULL,'2026-02-20 01:54:43'),(18,14,2,'jamu 1',0.00,0.00,0.00,NULL,'2026-02-20 01:54:43'),(19,14,1,'SGM 3PLUS MADU 800GR',0.00,0.00,0.00,NULL,'2026-02-20 01:54:43'),(20,14,3,'SOFTEX 2PH',0.00,0.00,0.00,NULL,'2026-02-20 01:54:43'),(21,15,2,'jamu 1',1.00,600000.00,600000.00,NULL,'2026-02-20 01:58:05'),(22,15,4,'SGM 3PLUS MADU 800GR + 8g',1.00,70000.00,70000.00,NULL,'2026-02-20 01:58:05'),(23,15,3,'SOFTEX 2PH',1.00,1000.00,1000.00,NULL,'2026-02-20 01:58:05'),(24,16,4,'SGM 3PLUS MADU 800GR + 8g',1.00,70000.00,70000.00,NULL,'2026-02-20 12:26:57'),(25,16,2,'jamu 1',1.00,600000.00,600000.00,NULL,'2026-02-20 12:26:57'),(26,17,3,'SOFTEX 2PH',1.00,1000.00,1000.00,NULL,'2026-02-20 12:34:52'),(27,23,1,'SGM 3PLUS MADU 800GR',1.00,70.00,70.00,'PCS','2026-02-20 13:16:56'),(28,24,4,'SGM 3PLUS MADU 800GR + 8g',1.00,70000.00,70000.00,'PCS','2026-02-20 21:30:26');
/*!40000 ALTER TABLE `purchase_order_detail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `retur`
--

DROP TABLE IF EXISTS `retur`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `retur` (
  `retur_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `toko_id` bigint(20) NOT NULL,
  `jenis` enum('penjualan','pembelian') NOT NULL,
  `referensi_id` bigint(20) NOT NULL,
  `alasan` text DEFAULT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`retur_id`),
  KEY `fk_retur_toko` (`toko_id`),
  CONSTRAINT `fk_retur_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `retur`
--

LOCK TABLES `retur` WRITE;
/*!40000 ALTER TABLE `retur` DISABLE KEYS */;
/*!40000 ALTER TABLE `retur` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `retur_detail`
--

DROP TABLE IF EXISTS `retur_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `retur_detail` (
  `detail_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `retur_id` bigint(20) NOT NULL,
  `produk_id` bigint(20) NOT NULL,
  `qty` int(11) NOT NULL,
  PRIMARY KEY (`detail_id`),
  KEY `fk_retur_detail` (`retur_id`),
  KEY `fk_retur_produk` (`produk_id`),
  CONSTRAINT `fk_retur_detail` FOREIGN KEY (`retur_id`) REFERENCES `retur` (`retur_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_retur_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`produk_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `retur_detail`
--

LOCK TABLES `retur_detail` WRITE;
/*!40000 ALTER TABLE `retur_detail` DISABLE KEYS */;
/*!40000 ALTER TABLE `retur_detail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `satuan`
--

DROP TABLE IF EXISTS `satuan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `satuan` (
  `satuan_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `nama` varchar(50) NOT NULL,
  PRIMARY KEY (`satuan_id`),
  UNIQUE KEY `nama` (`nama`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `satuan`
--

LOCK TABLES `satuan` WRITE;
/*!40000 ALTER TABLE `satuan` DISABLE KEYS */;
/*!40000 ALTER TABLE `satuan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shift_template`
--

DROP TABLE IF EXISTS `shift_template`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shift_template` (
  `template_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `toko_id` bigint(20) NOT NULL,
  `nama_shift` varchar(80) NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `urutan` int(11) NOT NULL DEFAULT 1,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`template_id`),
  UNIQUE KEY `uq_shift_template` (`toko_id`,`nama_shift`),
  KEY `idx_shift_template_toko` (`toko_id`,`aktif`),
  CONSTRAINT `fk_shift_template_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=336 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shift_template`
--

LOCK TABLES `shift_template` WRITE;
/*!40000 ALTER TABLE `shift_template` DISABLE KEYS */;
INSERT INTO `shift_template` VALUES (1,3,'Pagi','08:00:00','13:00:00',1,1),(2,3,'Siang','13:00:00','17:00:00',2,1),(3,3,'Malam','17:00:00','21:00:00',3,1),(79,3,'Legacy','00:00:00','23:59:59',99,1);
/*!40000 ALTER TABLE `shift_template` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shift_template_assignment`
--

DROP TABLE IF EXISTS `shift_template_assignment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shift_template_assignment` (
  `assignment_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `toko_id` bigint(20) NOT NULL,
  `kasir_id` bigint(20) NOT NULL,
  `template_id` bigint(20) NOT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`assignment_id`),
  UNIQUE KEY `uq_shift_assignment` (`toko_id`,`kasir_id`,`template_id`),
  KEY `idx_shift_assignment_kasir` (`toko_id`,`kasir_id`,`aktif`),
  KEY `fk_shift_assignment_kasir` (`kasir_id`),
  KEY `fk_shift_assignment_template` (`template_id`),
  CONSTRAINT `fk_shift_assignment_kasir` FOREIGN KEY (`kasir_id`) REFERENCES `pengguna` (`pengguna_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_shift_assignment_template` FOREIGN KEY (`template_id`) REFERENCES `shift_template` (`template_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_shift_assignment_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shift_template_assignment`
--

LOCK TABLES `shift_template_assignment` WRITE;
/*!40000 ALTER TABLE `shift_template_assignment` DISABLE KEYS */;
/*!40000 ALTER TABLE `shift_template_assignment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stok_gudang`
--

DROP TABLE IF EXISTS `stok_gudang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stok_gudang` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `gudang_id` bigint(20) NOT NULL,
  `produk_id` bigint(20) NOT NULL,
  `stok` int(11) NOT NULL DEFAULT 0,
  `min_stok` int(11) DEFAULT 0,
  `toko_id` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stok` (`gudang_id`,`produk_id`),
  KEY `fk_stok_produk` (`produk_id`),
  CONSTRAINT `fk_stok_gudang` FOREIGN KEY (`gudang_id`) REFERENCES `gudang` (`gudang_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stok_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`produk_id`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stok_gudang`
--

LOCK TABLES `stok_gudang` WRITE;
/*!40000 ALTER TABLE `stok_gudang` DISABLE KEYS */;
INSERT INTO `stok_gudang` VALUES (1,1,8,1187,0,3),(3,1,2,1298,3,3),(4,1,7,998,10,3),(5,1,4,1001,3,3),(6,1,3,14284,2000,3),(8,1,1,98,10,3);
/*!40000 ALTER TABLE `stok_gudang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stok_mutasi`
--

DROP TABLE IF EXISTS `stok_mutasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stok_mutasi` (
  `mutasi_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `toko_id` bigint(20) NOT NULL,
  `gudang_id` bigint(20) NOT NULL,
  `produk_id` bigint(20) NOT NULL,
  `qty` int(11) NOT NULL,
  `stok_sebelum` int(11) NOT NULL,
  `stok_sesudah` int(11) NOT NULL,
  `tipe` enum('masuk','keluar') NOT NULL,
  `referensi` varchar(100) DEFAULT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`mutasi_id`),
  KEY `fk_mutasi_gudang` (`gudang_id`),
  KEY `fk_mutasi_produk` (`produk_id`),
  KEY `fk_mutasi_toko` (`toko_id`),
  CONSTRAINT `fk_mutasi_gudang` FOREIGN KEY (`gudang_id`) REFERENCES `gudang` (`gudang_id`),
  CONSTRAINT `fk_mutasi_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`produk_id`),
  CONSTRAINT `fk_mutasi_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stok_mutasi`
--

LOCK TABLES `stok_mutasi` WRITE;
/*!40000 ALTER TABLE `stok_mutasi` DISABLE KEYS */;
INSERT INTO `stok_mutasi` VALUES (1,3,1,8,100,0,100,'masuk','SALDO AWAL HM:2000.00','2026-02-22 09:20:20'),(2,3,1,8,100,100,200,'masuk','SALDO AWAL HM:2000.00','2026-02-22 09:21:08'),(3,3,1,2,1300,0,1300,'masuk','SALDO AWAL HM:600000.00','2026-02-22 09:26:05'),(4,3,1,7,1000,0,1000,'masuk','SALDO AWAL HM:70000.00','2026-02-22 09:27:07'),(5,3,1,4,1000,0,1000,'masuk','SALDO AWAL HM:70000.00','2026-02-22 09:27:47'),(6,3,1,3,1301,0,1301,'masuk','SALDO AWAL HM:1000.00','2026-02-22 09:28:21'),(7,3,1,3,13000,1301,14301,'masuk','SALDO AWAL HM:1000.00','2026-02-22 09:28:42'),(8,3,1,1,100,0,100,'masuk','SALDO AWAL HM:70.00','2026-02-22 09:29:07'),(9,3,1,8,1000,200,1200,'masuk','SALDO AWAL HM:3000.00','2026-02-22 10:10:11'),(10,3,1,4,1,1000,1001,'masuk','PO-20260220-751','2026-02-23 04:24:58'),(11,3,1,8,10,1200,1190,'keluar','POS-20260223-7171','2026-02-23 04:59:41'),(12,3,1,8,1,1190,1189,'keluar','POS-20260225-2180','2026-02-25 08:32:50'),(13,3,1,8,1,1189,1188,'keluar','POS-20260225-4618','2026-02-25 08:33:35'),(14,3,1,1,1,100,99,'keluar','POS-20260225-0041','2026-02-25 08:38:55'),(15,3,1,1,1,99,98,'keluar','POS-20260225-4565','2026-02-25 12:02:49'),(16,3,1,7,1,1000,999,'keluar','POS-20260225-4565','2026-02-25 12:02:49'),(17,3,1,8,1,1188,1187,'keluar','POS-20260225-8746','2026-02-25 12:11:33'),(18,3,1,3,1,14301,14300,'keluar','POS-20260225-7748','2026-02-25 12:18:57'),(19,3,1,2,1,1300,1299,'keluar','POS-20260226-2101','2026-02-26 04:54:30'),(20,3,1,3,1,14300,14299,'keluar','POS-20260226-5283','2026-02-26 05:03:57'),(21,3,1,7,1,999,998,'keluar','POS-20260226-9966','2026-02-26 05:07:25'),(22,3,1,3,1,14299,14298,'keluar','POS-20260226-6709','2026-02-26 05:09:40'),(23,3,1,2,1,1299,1298,'keluar','POS-20260226-5884','2026-02-26 06:17:34'),(24,3,1,2,1,1298,1297,'keluar','POS-20260226-5754','2026-02-26 06:17:34'),(25,3,1,2,1,1297,1298,'masuk','SALDO AWAL HM:60000.00','2026-02-26 06:20:17'),(26,3,1,3,1,14298,14297,'keluar','POS-20260226-5827','2026-02-26 18:29:32'),(27,3,1,3,1,14297,14296,'keluar','POS-20260226-3134','2026-02-26 18:30:04'),(28,3,1,3,1,14296,14295,'keluar','POS-20260226-4973','2026-02-26 18:31:36'),(29,3,1,3,1,14295,14294,'keluar','POS-20260226-3326','2026-02-26 18:33:24'),(30,3,1,3,1,14294,14293,'keluar','POS-20260226-4641','2026-02-26 18:50:18'),(31,3,1,3,1,14293,14292,'keluar','POS-20260226-5801','2026-02-26 18:52:23'),(32,3,1,3,1,14292,14291,'keluar','POS-20260226-7575','2026-02-26 19:03:28'),(33,3,1,3,1,14291,14290,'keluar','POS-20260226-3339','2026-02-26 19:13:26'),(34,3,1,3,1,14290,14289,'keluar','POS-20260226-1592','2026-02-26 19:14:32'),(35,3,1,3,1,14289,14288,'keluar','POS-20260226-7030','2026-02-26 19:19:06'),(36,3,1,3,1,14288,14287,'keluar','POS-20260226-4424','2026-02-26 19:26:20'),(37,3,1,3,1,14287,14286,'keluar','POS-20260226-1446','2026-02-26 19:26:20'),(38,3,1,3,1,14286,14285,'keluar','POS-20260227-8356','2026-02-27 17:04:57'),(39,3,1,3,1,14285,14284,'keluar','POS-20260227-9196','2026-02-27 18:48:10');
/*!40000 ALTER TABLE `stok_mutasi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier`
--

DROP TABLE IF EXISTS `supplier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supplier` (
  `supplier_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `toko_id` bigint(20) NOT NULL DEFAULT 0,
  `nama_supplier` varchar(150) NOT NULL,
  `telepon` varchar(50) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`supplier_id`),
  KEY `idx_supplier_toko` (`toko_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier`
--

LOCK TABLES `supplier` WRITE;
/*!40000 ALTER TABLE `supplier` DISABLE KEYS */;
INSERT INTO `supplier` VALUES (1,3,'PT COBA COBA','0857565412543','AA','2026-02-19 14:16:16',NULL),(2,3,'PT SETIA ABADI','085745445119','demak','2026-02-19 23:01:31',NULL),(3,0,'cv mitra alam','08574544511244','lpg','2026-02-20 20:48:48',NULL);
/*!40000 ALTER TABLE `supplier` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `toko`
--

DROP TABLE IF EXISTS `toko`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `toko` (
  `toko_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `nama_toko` varchar(100) NOT NULL,
  `license_key` varchar(100) NOT NULL,
  `alamat` text DEFAULT NULL,
  `aktif` tinyint(1) DEFAULT 1,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `db_host` varchar(255) DEFAULT NULL,
  `db_name` varchar(100) DEFAULT NULL,
  `db_user` varchar(50) DEFAULT NULL,
  `db_pass` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`toko_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `toko`
--

LOCK TABLES `toko` WRITE;
/*!40000 ALTER TABLE `toko` DISABLE KEYS */;
INSERT INTO `toko` VALUES (3,'AlbaOne31','','lampung',1,'2026-02-17 12:28:04',NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `toko` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `toko_config`
--

DROP TABLE IF EXISTS `toko_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `toko_config` (
  `config_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `toko_id` bigint(20) NOT NULL,
  `nama_konfigurasi` varchar(100) NOT NULL,
  `nilai` varchar(255) NOT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`config_id`),
  KEY `fk_config_toko` (`toko_id`),
  CONSTRAINT `fk_config_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `toko_config`
--

LOCK TABLES `toko_config` WRITE;
/*!40000 ALTER TABLE `toko_config` DISABLE KEYS */;
INSERT INTO `toko_config` VALUES (35,3,'ppn_persen','11.00','2026-02-19 06:52:36'),(36,3,'ppn_mode','exclude','2026-02-19 06:52:36'),(37,3,'timezone','Asia/Jakarta','2026-02-20 14:16:34'),(38,3,'language','id','2026-02-20 14:16:34'),(39,3,'currency','IDR','2026-02-20 14:16:34'),(40,3,'number_format','1.234,56','2026-02-20 14:16:34'),(41,3,'date_format','m/d/Y','2026-02-20 14:16:34'),(42,3,'phone','','2026-02-20 14:16:34'),(43,3,'email_cs','albaone01@gmail.com','2026-02-20 14:16:34'),(44,3,'npwp','321123','2026-02-20 14:16:34'),(45,3,'kota','Bintoro','2026-02-20 14:16:34'),(46,3,'provinsi','Jawa Tengah','2026-02-20 14:16:34'),(47,3,'kode_pos','59511','2026-02-20 14:16:34'),(48,3,'logo_path','assets/uploads/logo_toko_3.png','2026-02-20 14:16:34');
/*!40000 ALTER TABLE `toko_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_filter_preset`
--

DROP TABLE IF EXISTS `user_filter_preset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_filter_preset` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `toko_id` bigint(20) NOT NULL,
  `pengguna_id` bigint(20) NOT NULL,
  `kunci` varchar(100) NOT NULL,
  `nilai` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_filter_user` (`toko_id`,`pengguna_id`,`kunci`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_filter_preset`
--

LOCK TABLES `user_filter_preset` WRITE;
/*!40000 ALTER TABLE `user_filter_preset` DISABLE KEYS */;
INSERT INTO `user_filter_preset` VALUES (1,3,6,'laporan_stok_periode','{\"from\":\"2026-02-01\",\"to\":\"2026-02-26\",\"gudang_id\":\"\",\"produk_id\":\"\"}','2026-02-26 05:07:53'),(16,3,8,'laporan_stok_periode','{\"from\":\"2026-02-01\",\"to\":\"2026-02-26\",\"gudang_id\":\"\",\"produk_id\":\"\"}','2026-02-26 06:18:00');
/*!40000 ALTER TABLE `user_filter_preset` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-28  4:00:58
