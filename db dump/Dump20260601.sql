-- MySQL dump 10.13  Distrib 8.0.42, for Win64 (x86_64)
--
-- Host: oralsync-db.mysql.database.azure.com    Database: oral
-- ------------------------------------------------------
-- Server version	8.0.44-azure

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admin_logs`
--

DROP TABLE IF EXISTS `admin_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `admin_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT 'Admin',
  `activity_type` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `action_details` text COLLATE utf8mb4_general_ci,
  `username` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_role` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `log_date` date NOT NULL,
  `log_time` time DEFAULT NULL,
  PRIMARY KEY (`log_id`),
  KEY `fk_logs_tenant` (`tenant_id`),
  CONSTRAINT `fk_logs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_logs`
--

LOCK TABLES `admin_logs` WRITE;
/*!40000 ALTER TABLE `admin_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `announcements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `category` varchar(255) DEFAULT 'General',
  `image_path` varchar(511) DEFAULT NULL,
  `status` enum('active','archived') DEFAULT 'active',
  `publish_date` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_tenant_announcement` (`tenant_id`),
  CONSTRAINT `fk_tenant_announcement` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcements`
--

LOCK TABLES `announcements` WRITE;
/*!40000 ALTER TABLE `announcements` DISABLE KEYS */;
/*!40000 ALTER TABLE `announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointment`
--

DROP TABLE IF EXISTS `appointment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointment` (
  `appointment_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `patient_id` int NOT NULL,
  `dentist_id` int NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `service_id` int DEFAULT NULL,
  `status` enum('pending','confirmed','reschedule_pending','completed','cancelled','no_show','declined','In Progress') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `procedure_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_appointment_request` tinyint(1) DEFAULT '1',
  `requested_by` enum('patient','receptionist','dentist') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'patient',
  `total_duration_minutes` int NOT NULL DEFAULT '30' COMMENT 'Sum of selected service durations at time of booking',
  `policy_agreed` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = patient agreed to cancellation policy at booking',
  `rescheduled_from_id` int DEFAULT NULL COMMENT 'Points to the original appointment_id if this is a reschedule',
  `reschedule_requested_at` datetime DEFAULT NULL COMMENT 'Timestamp when patient submitted reschedule request',
  PRIMARY KEY (`appointment_id`),
  KEY `fk_appt_tenant` (`tenant_id`),
  KEY `fk_appt_service` (`service_id`),
  CONSTRAINT `fk_appt_service` FOREIGN KEY (`service_id`) REFERENCES `service` (`service_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_appt_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointment`
--

LOCK TABLES `appointment` WRITE;
/*!40000 ALTER TABLE `appointment` DISABLE KEYS */;
INSERT INTO `appointment` VALUES (1,1,2,2,'2026-05-04','09:00:00',NULL,NULL,'declined',NULL,0,'patient',30,0,NULL,NULL),(2,1,2,2,'2026-05-01','12:30:00','TESTING LANG IF NAGANA ',NULL,'declined',NULL,0,'patient',30,0,NULL,NULL),(3,1,2,2,'2026-05-04','13:00:00','BOMBOLEYO',NULL,'completed',NULL,0,'patient',30,0,NULL,NULL),(4,1,2,2,'2026-05-01','15:00:00',NULL,NULL,'declined',NULL,0,'patient',30,0,NULL,NULL),(5,1,2,2,'2026-05-01','15:30:00',NULL,NULL,'declined',NULL,0,'patient',30,0,NULL,NULL),(6,1,2,2,'2026-05-01','16:00:00',NULL,NULL,'declined',NULL,0,'patient',30,0,NULL,NULL),(7,1,2,2,'2026-05-04','10:00:00','Kingina\n',NULL,'declined',NULL,0,'patient',30,0,NULL,NULL),(8,1,5,2,'2026-05-06','09:00:00','Testing 1',NULL,'cancelled',NULL,0,'patient',30,0,NULL,NULL),(9,1,5,2,'2026-05-06','09:30:00','2',NULL,'cancelled',NULL,0,'patient',30,0,NULL,NULL),(10,1,5,2,'2026-05-06','10:30:00','3',NULL,'cancelled',NULL,0,'patient',30,0,NULL,NULL),(11,1,5,2,'2026-05-06','10:00:00','Ewan na',NULL,'cancelled',NULL,0,'patient',30,0,NULL,NULL),(12,1,6,2,'2026-05-08','09:00:00','Bigyan moko bill isa',NULL,'completed',NULL,0,'patient',30,0,NULL,NULL),(13,1,6,2,'2026-05-08','10:00:00','bigyan',NULL,'no_show',NULL,0,'patient',30,0,NULL,NULL),(14,1,6,2,'2026-05-08','11:00:00','Miko bilat',NULL,'completed',NULL,0,'patient',30,0,NULL,NULL),(15,1,5,2,'2026-05-13','10:00:00',NULL,NULL,'declined',NULL,0,'patient',30,0,NULL,NULL),(16,1,1,2,'2026-05-04','10:30:00',NULL,NULL,'In Progress',NULL,0,'patient',30,0,NULL,NULL),(17,1,5,2,'2026-05-29','16:30:00',NULL,NULL,'pending',NULL,0,'patient',30,0,NULL,NULL),(18,1,5,2,'2026-05-04','12:30:00',NULL,NULL,'pending',NULL,0,'patient',30,0,NULL,NULL),(19,1,8,2,'2026-05-04','11:30:00','Sakit ipin\n',NULL,'cancelled',NULL,0,'patient',30,0,NULL,NULL),(20,1,8,2,'2026-05-08','12:30:00',NULL,NULL,'pending',NULL,1,'patient',30,0,NULL,NULL),(21,1,8,2,'2026-05-08','16:30:00',NULL,NULL,'pending',NULL,1,'patient',30,0,NULL,NULL),(22,1,6,2,'2026-05-25','09:00:00',NULL,NULL,'no_show',NULL,1,'patient',90,1,NULL,NULL),(23,1,6,2,'2026-05-29','12:30:00',NULL,NULL,'cancelled',NULL,1,'patient',90,1,NULL,NULL),(24,1,11,2,'2026-05-25','16:00:00',NULL,NULL,'no_show',NULL,1,'patient',45,1,NULL,NULL),(25,1,11,2,'2026-05-29','14:30:00',NULL,NULL,'cancelled',NULL,1,'patient',90,1,NULL,NULL),(26,1,11,2,'2026-05-29','10:30:00',NULL,NULL,'cancelled',NULL,1,'patient',90,1,25,'2026-05-27 14:21:12'),(27,1,6,2,'2026-05-29','14:30:00',NULL,NULL,'reschedule_pending',NULL,1,'patient',90,1,23,'2026-05-28 11:29:35'),(28,17,13,30,'2026-06-01','09:00:00',NULL,NULL,'pending',NULL,0,'patient',30,0,NULL,NULL),(29,1,6,2,'2026-05-29','09:00:00',NULL,NULL,'pending',NULL,1,'patient',90,1,NULL,NULL);
/*!40000 ALTER TABLE `appointment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointment_services`
--

DROP TABLE IF EXISTS `appointment_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointment_services` (
  `id` int NOT NULL AUTO_INCREMENT,
  `appointment_id` int NOT NULL,
  `service_id` int NOT NULL,
  `service_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Snapshot of name at booking time in case service is renamed later',
  `duration_minutes` int NOT NULL DEFAULT '30' COMMENT 'Snapshot of duration at booking time',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Snapshot of price at booking time',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_appt_service` (`appointment_id`,`service_id`),
  KEY `fk_appt_svc_service` (`service_id`),
  CONSTRAINT `fk_appt_svc_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointment` (`appointment_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appt_svc_service` FOREIGN KEY (`service_id`) REFERENCES `service` (`service_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Cart: services selected per appointment booking';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointment_services`
--

LOCK TABLES `appointment_services` WRITE;
/*!40000 ALTER TABLE `appointment_services` DISABLE KEYS */;
INSERT INTO `appointment_services` VALUES (1,22,49,'Wisdom Tooth Removal',90,5000.00),(2,23,42,'Teeth Whitening',90,8000.00),(3,24,48,'Tooth Extraction',45,1500.00),(4,25,42,'Teeth Whitening',90,8000.00),(5,26,42,'Teeth Whitening',90,8000.00),(6,27,42,'Teeth Whitening',90,8000.00),(7,29,42,'Teeth Whitening',90,8000.00);
/*!40000 ALTER TABLE `appointment_services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `billing`
--

DROP TABLE IF EXISTS `billing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing` (
  `billing_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `appointment_id` int NOT NULL,
  `patient_id` int NOT NULL,
  `service_id` int DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `amount_paid` decimal(10,2) DEFAULT '0.00',
  `payment_status` enum('unpaid','partial','paid') DEFAULT 'unpaid',
  `billing_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `original_amount` decimal(10,2) DEFAULT NULL COMMENT 'Amount before discount',
  `discount_type` varchar(20) DEFAULT NULL COMMENT 'PWD | Senior | NULL',
  `discount_amount` decimal(10,2) DEFAULT '0.00' COMMENT 'Amount deducted',
  `is_installment` tinyint(1) DEFAULT '0' COMMENT '1 if this is a monthly installment bill',
  `installment_plan_id` int DEFAULT NULL COMMENT 'FK to installment_plan',
  `paymongo_session_id` varchar(255) DEFAULT NULL COMMENT 'PayMongo checkout_session ID for reconciliation',
  `paymongo_payment_id` varchar(255) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL COMMENT 'Human-readable ref shown to patient',
  `payment_type` enum('full','deposit') DEFAULT 'full',
  `mode` varchar(50) DEFAULT 'Cash',
  `procedures_json` text,
  `source` varchar(50) DEFAULT 'web',
  PRIMARY KEY (`billing_id`),
  KEY `fk_bill_tenant` (`tenant_id`),
  KEY `fk_bill_appt` (`appointment_id`),
  KEY `fk_bill_patient` (`patient_id`),
  KEY `fk_bill_service` (`service_id`),
  CONSTRAINT `fk_bill_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointment` (`appointment_id`),
  CONSTRAINT `fk_bill_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_id`),
  CONSTRAINT `fk_bill_service` FOREIGN KEY (`service_id`) REFERENCES `service` (`service_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_bill_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `billing`
--

LOCK TABLES `billing` WRITE;
/*!40000 ALTER TABLE `billing` DISABLE KEYS */;
INSERT INTO `billing` VALUES (1,1,2,2,NULL,500.00,500.00,'paid','2026-05-01 10:25:21',NULL,NULL,0.00,0,NULL,'cs_a8a6085e3c88c80e65c2c4b5','pay_cVdvhBd7haVqtNYC9S8vbYSo','MOB-2-1777717760','deposit','Gcash',NULL,'web'),(2,1,3,2,NULL,500.00,500.00,'paid','2026-05-01 10:26:38',NULL,NULL,0.00,0,NULL,'cs_775a3cd20ffe6ccc3879f4ba',NULL,'CASH-2-1777710132380','deposit','Cash',NULL,'web'),(3,1,4,2,NULL,500.00,500.00,'paid','2026-05-01 12:46:47',NULL,NULL,0.00,0,NULL,'cs_4fc2b1f9c42a667982cc2393',NULL,'CASH-2-1777710127796','deposit','Cash',NULL,'web'),(4,1,5,2,NULL,500.00,500.00,'paid','2026-05-01 12:50:22',NULL,NULL,0.00,0,NULL,'cs_94168c5dad1099bac7736349',NULL,'CASH-2-1777710123383','deposit','Cash',NULL,'web'),(5,1,6,2,NULL,500.00,500.00,'paid','2026-05-01 13:20:15',NULL,NULL,0.00,0,NULL,'cs_7c10deabb5a713196fda2d95',NULL,'CASH-2-1777646816350','deposit','Cash',NULL,'web'),(6,1,7,2,NULL,200.00,200.00,'paid','2026-05-02 08:30:02',NULL,NULL,0.00,0,NULL,NULL,NULL,'CASH-2-1777710940831','deposit','Cash',NULL,'web'),(7,1,3,2,NULL,600.00,600.00,'paid','2026-05-02 09:42:02',NULL,NULL,0.00,0,NULL,NULL,NULL,'CASH-2-1777731254558','full','Cash','[{\"service_id\":\"58\",\"name\":\"Basic Cleaning\",\"price\":800}]','web'),(8,1,8,5,NULL,200.00,200.00,'paid','2026-05-02 14:32:20',NULL,NULL,0.00,0,NULL,'cs_d50ba3778f27cde64a950dee','pay_LxaiVoMqB5bt6Rj8F8PQjBU7','MOB-5-1777732342','deposit','Card',NULL,'web'),(9,1,9,5,NULL,200.00,200.00,'paid','2026-05-02 14:43:19',NULL,NULL,0.00,0,NULL,'cs_06d8e041ff59d249628c9f0c','pay_PduoZc57xDXrAzAi4UxMTZ6D','MOB-5-1777733120','deposit','Card',NULL,'web'),(10,1,10,5,NULL,200.00,200.00,'paid','2026-05-02 14:49:17',NULL,NULL,0.00,0,NULL,'cs_85a761c0e42992f1c781e804','pay_cQdjJyePWQ7eGTt5LbEUg1GD','MOB-5-1777733358','deposit','Card',NULL,'web'),(11,1,11,5,NULL,200.00,200.00,'paid','2026-05-02 15:04:05',NULL,NULL,0.00,0,NULL,'cs_239c575700dcfd87679a19ee','pay_gZh2qxb5KzFBLYtQMXCQ6km8','MOB-5-1777734246','deposit','Card',NULL,'web'),(12,1,8,5,NULL,93800.00,93800.00,'unpaid','2026-05-02 16:08:19',NULL,NULL,0.00,0,NULL,NULL,NULL,'WEB-20260502-5927','full','','[{\"service_id\":\"50\",\"name\":\"Apicoectomy\",\"price\":8000},{\"service_id\":\"58\",\"name\":\"Basic Cleaning\",\"price\":800},{\"service_id\":\"46\",\"name\":\"Braces (Ceramic)\",\"price\":50000},{\"service_id\":\"45\",\"name\":\"Braces (Metal)\",\"price\":35000}]','web'),(13,1,12,6,NULL,200.00,200.00,'paid','2026-05-03 04:30:59',NULL,NULL,0.00,0,NULL,'cs_54d0aa4a4767e5b399d19b05','pay_UrhcKwACeGAvm6mJMSncE6EH','MOB-6-1777782661','deposit','Card',NULL,'web'),(14,1,13,6,NULL,200.00,200.00,'paid','2026-05-03 04:33:58',NULL,NULL,0.00,0,NULL,'cs_2d081108f3c97d4c26100b03','pay_SmrrQZCR4vam8jsiCM4XiNm5','MOB-6-1777782841','deposit','Card',NULL,'web'),(15,1,14,6,NULL,200.00,200.00,'paid','2026-05-03 04:36:10',NULL,NULL,0.00,0,NULL,'cs_a750b69920f957529a1f8084','pay_v1qBng9uKr7hjkMvjKLR2mhU','MOB-6-1777782972','deposit','Card',NULL,'web'),(16,1,12,6,NULL,12800.00,12800.00,'unpaid','2026-05-03 05:09:43',NULL,NULL,0.00,0,NULL,NULL,NULL,'WEB-20260503-4303','full','GCash','[{\"service_id\":\"47\",\"name\":\"Retainer\",\"price\":5000},{\"service_id\":\"54\",\"name\":\"Root Canal Treatment\",\"price\":8000}]','web'),(17,1,12,6,NULL,58600.00,58600.00,'unpaid','2026-05-03 05:22:55',NULL,NULL,0.00,0,NULL,NULL,NULL,'WEB-20260503-9275','full','GCash','[{\"service_id\":\"50\",\"name\":\"Apicoectomy\",\"price\":8000},{\"service_id\":\"58\",\"name\":\"Basic Cleaning\",\"price\":800},{\"service_id\":\"46\",\"name\":\"Braces (Ceramic)\",\"price\":50000}]','web'),(18,1,12,6,NULL,43600.00,43600.00,'unpaid','2026-05-03 08:14:58',NULL,NULL,0.00,0,NULL,NULL,NULL,'WEB-20260503-0391','full','Mobile App','[{\"service_id\":\"50\",\"name\":\"Apicoectomy\",\"price\":8000},{\"service_id\":\"58\",\"name\":\"Basic Cleaning\",\"price\":800},{\"service_id\":\"45\",\"name\":\"Braces (Metal)\",\"price\":35000}]','web'),(19,1,12,6,NULL,2800.00,2240.00,'partial','2026-05-03 08:50:16',NULL,NULL,0.00,0,NULL,NULL,NULL,'CASH-6-1777799320836','full','Cash','[{\"service_id\":\"51\",\"name\":\"Dental Filling (Amalgam)\",\"price\":1200},{\"service_id\":\"52\",\"name\":\"Dental Filling (Composite)\",\"price\":1800}]','web'),(20,1,12,6,NULL,1300.00,1300.00,'unpaid','2026-05-03 08:55:16',NULL,NULL,0.00,0,NULL,NULL,NULL,'WEB-20260503-1098','full','Mobile App','[{\"service_id\":\"33\",\"name\":\"Oral Prophylaxis\",\"price\":1500}]','web'),(21,1,12,6,NULL,2200.00,0.00,'unpaid','2026-05-03 09:13:43',NULL,NULL,0.00,0,NULL,NULL,NULL,'WEB-20260503-9602','full',NULL,'[{\"service_id\":\"52\",\"name\":\"Dental Filling (Composite)\",\"price\":1800},{\"service_id\":\"35\",\"name\":\"Dental Sealants\",\"price\":600}]','web'),(22,1,12,6,NULL,7300.00,0.00,'unpaid','2026-05-03 09:15:02',NULL,NULL,0.00,0,NULL,NULL,NULL,'WEB-20260503-7548','full',NULL,'[{\"service_id\":\"53\",\"name\":\"Inlay/Onlay\",\"price\":6000},{\"service_id\":\"33\",\"name\":\"Oral Prophylaxis\",\"price\":1500}]','web'),(23,1,13,6,NULL,8600.00,0.00,'unpaid','2026-05-03 09:16:33',NULL,NULL,0.00,0,NULL,'cs_f8c35a3f0563bf1b16cb2a31',NULL,'MOB-6-1777801414','full',NULL,'[{\"service_id\":\"50\",\"name\":\"Apicoectomy\",\"price\":8000},{\"service_id\":\"58\",\"name\":\"Basic Cleaning\",\"price\":800}]','web'),(24,1,13,6,NULL,50600.00,0.00,'unpaid','2026-05-03 09:16:59',NULL,NULL,0.00,0,NULL,'cs_3ac277cfd80669fa3c80751d',NULL,'MOB-6-1777801264','full',NULL,'[{\"service_id\":\"58\",\"name\":\"Basic Cleaning\",\"price\":800},{\"service_id\":\"46\",\"name\":\"Braces (Ceramic)\",\"price\":50000}]','web'),(25,1,13,6,NULL,8600.00,8600.00,'paid','2026-05-03 09:17:13',NULL,NULL,0.00,0,NULL,'cs_336df51e2f1f11c95cb3d67c','pay_zPnKGHBKu7u8k3NRX6W1uEt3','MOB-6-1777801199','full','Gcash','[{\"service_id\":\"50\",\"name\":\"Apicoectomy\",\"price\":8000},{\"service_id\":\"58\",\"name\":\"Basic Cleaning\",\"price\":800}]','web'),(26,1,16,1,NULL,200.00,200.00,'paid','2026-05-03 15:15:44',NULL,NULL,0.00,0,NULL,'cs_d9283e78904fd9f67ab01965','pay_zHcawqDkGvHptFhKAjEHAB7f','MOB-1-1777821345','deposit','Card',NULL,'web'),(27,1,16,1,NULL,35600.00,35600.00,'paid','2026-05-03 15:24:30',NULL,NULL,0.00,0,NULL,'cs_49e922728030dc080d61ac29','pay_pqTbbggRcy2u6vivxt1skc2r','MOB-1-1777821920','full','Card','[{\"service_id\":\"58\",\"name\":\"Basic Cleaning\",\"price\":800},{\"service_id\":\"45\",\"name\":\"Braces (Metal)\",\"price\":35000}]','web'),(28,1,19,8,NULL,200.00,200.00,'paid','2026-05-04 02:07:09',NULL,NULL,0.00,0,NULL,'cs_624f06ae6fe4b0ad707b65a9','pay_9Ko7BKHVwWK5yhD914FfbVtw','MOB-8-1777860536','deposit','Card',NULL,'web'),(29,1,19,8,NULL,8600.00,8600.00,'paid','2026-05-04 02:24:11',NULL,NULL,0.00,0,NULL,'cs_7a73d021ff571eb4493c52ea','pay_NpEen4vAV2YAHwijhLdsAvKk','MOB-8-1777861784','full','Gcash','[{\"service_id\":\"50\",\"name\":\"Apicoectomy\",\"price\":8000},{\"service_id\":\"58\",\"name\":\"Basic Cleaning\",\"price\":800}]','web'),(30,1,20,8,NULL,200.00,200.00,'paid','2026-05-05 21:30:03',NULL,NULL,0.00,0,NULL,'cs_fe19d31d4989544421a8d9a8','pay_xMHszdNkexsEZ79HeMKxk6sJ','MOB-8-1778016608','deposit','Card',NULL,'web'),(31,1,21,8,NULL,200.00,200.00,'paid','2026-05-05 21:32:02',NULL,NULL,0.00,0,NULL,'cs_ef148cdfd5cd219e5fb47489','pay_oxvhW6AtiCwDAzspHijVTukj','MOB-8-1778016725','deposit','Card',NULL,'web');
/*!40000 ALTER TABLE `billing` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clinic_schedules`
--

DROP TABLE IF EXISTS `clinic_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinic_schedules` (
  `schedule_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `opening_time` time DEFAULT NULL,
  `closing_time` time DEFAULT NULL,
  `is_closed` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`schedule_id`),
  UNIQUE KEY `unique_tenant_day` (`tenant_id`,`day_of_week`),
  CONSTRAINT `fk_schedule_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=120 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clinic_schedules`
--

LOCK TABLES `clinic_schedules` WRITE;
/*!40000 ALTER TABLE `clinic_schedules` DISABLE KEYS */;
INSERT INTO `clinic_schedules` VALUES (1,1,'Monday','09:00:00','17:00:00',0),(2,1,'Tuesday','09:00:00','17:00:00',0),(3,1,'Wednesday','09:00:00','17:00:00',0),(4,1,'Thursday','09:00:00','17:00:00',0),(5,1,'Friday','09:00:00','17:00:00',0),(6,1,'Saturday','09:00:00','17:00:00',0),(7,1,'Sunday','09:00:00','17:00:00',1),(22,6,'Monday','09:00:00','17:00:00',0),(23,6,'Tuesday','09:00:00','17:00:00',0),(24,6,'Wednesday','09:00:00','17:00:00',0),(25,6,'Thursday','09:00:00','17:00:00',0),(26,6,'Friday','09:00:00','17:00:00',0),(27,6,'Saturday','09:00:00','13:00:00',0),(28,6,'Sunday','09:00:00','17:00:00',1),(29,7,'Monday','09:00:00','17:00:00',0),(30,7,'Tuesday','09:00:00','17:00:00',0),(31,7,'Wednesday','09:00:00','17:00:00',0),(32,7,'Thursday','09:00:00','17:00:00',0),(33,7,'Friday','09:00:00','17:00:00',0),(34,7,'Saturday','09:00:00','13:00:00',0),(35,7,'Sunday','09:00:00','17:00:00',1),(36,8,'Monday','09:00:00','17:00:00',0),(37,8,'Tuesday','09:00:00','17:00:00',0),(38,8,'Wednesday','09:00:00','17:00:00',0),(39,8,'Thursday','09:00:00','17:00:00',0),(40,8,'Friday','09:00:00','17:00:00',0),(41,8,'Saturday','09:00:00','13:00:00',0),(42,8,'Sunday','09:00:00','17:00:00',1),(43,9,'Monday','09:00:00','17:00:00',0),(44,9,'Tuesday','09:00:00','17:00:00',0),(45,9,'Wednesday','09:00:00','17:00:00',0),(46,9,'Thursday','09:00:00','17:00:00',0),(47,9,'Friday','09:00:00','17:00:00',0),(48,9,'Saturday','09:00:00','13:00:00',0),(49,9,'Sunday','09:00:00','17:00:00',1),(64,10,'Monday','09:00:00','17:00:00',0),(65,10,'Tuesday','09:00:00','17:00:00',0),(66,10,'Wednesday','09:00:00','17:00:00',0),(67,10,'Thursday','09:00:00','17:00:00',0),(68,10,'Friday','09:00:00','17:00:00',0),(69,10,'Saturday','09:00:00','13:00:00',0),(70,10,'Sunday','09:00:00','17:00:00',1),(71,11,'Monday','09:00:00','17:00:00',0),(72,11,'Tuesday','09:00:00','17:00:00',0),(73,11,'Wednesday','09:00:00','17:00:00',0),(74,11,'Thursday','09:00:00','17:00:00',0),(75,11,'Friday','09:00:00','17:00:00',0),(76,11,'Saturday','09:00:00','13:00:00',0),(77,11,'Sunday','09:00:00','17:00:00',1),(78,12,'Monday','09:00:00','17:00:00',0),(79,12,'Tuesday','09:00:00','17:00:00',0),(80,12,'Wednesday','09:00:00','17:00:00',0),(81,12,'Thursday','09:00:00','17:00:00',0),(82,12,'Friday','09:00:00','17:00:00',0),(83,12,'Saturday','09:00:00','13:00:00',0),(84,12,'Sunday','09:00:00','17:00:00',1),(85,13,'Monday','09:00:00','17:00:00',0),(86,13,'Tuesday','09:00:00','17:00:00',0),(87,13,'Wednesday','09:00:00','17:00:00',0),(88,13,'Thursday','09:00:00','17:00:00',0),(89,13,'Friday','09:00:00','17:00:00',0),(90,13,'Saturday','09:00:00','13:00:00',0),(91,13,'Sunday','09:00:00','17:00:00',1),(92,14,'Monday','09:00:00','17:00:00',0),(93,14,'Tuesday','09:00:00','17:00:00',0),(94,14,'Wednesday','09:00:00','17:00:00',0),(95,14,'Thursday','09:00:00','17:00:00',0),(96,14,'Friday','09:00:00','17:00:00',0),(97,14,'Saturday','09:00:00','13:00:00',0),(98,14,'Sunday','09:00:00','17:00:00',1),(99,15,'Monday','09:00:00','17:00:00',0),(100,15,'Tuesday','09:00:00','17:00:00',0),(101,15,'Wednesday','09:00:00','17:00:00',0),(102,15,'Thursday','09:00:00','17:00:00',0),(103,15,'Friday','09:00:00','17:00:00',0),(104,15,'Saturday','09:00:00','13:00:00',0),(105,15,'Sunday','09:00:00','17:00:00',1),(106,16,'Monday','09:00:00','17:00:00',0),(107,16,'Tuesday','09:00:00','17:00:00',0),(108,16,'Wednesday','09:00:00','17:00:00',0),(109,16,'Thursday','09:00:00','17:00:00',0),(110,16,'Friday','09:00:00','17:00:00',0),(111,16,'Saturday','09:00:00','13:00:00',0),(112,16,'Sunday','09:00:00','17:00:00',1),(113,17,'Monday','09:00:00','17:00:00',0),(114,17,'Tuesday','09:00:00','17:00:00',0),(115,17,'Wednesday','09:00:00','17:00:00',0),(116,17,'Thursday','09:00:00','17:00:00',0),(117,17,'Friday','09:00:00','17:00:00',0),(118,17,'Saturday','09:00:00','13:00:00',0),(119,17,'Sunday','09:00:00','17:00:00',1);
/*!40000 ALTER TABLE `clinic_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clinic_settings`
--

DROP TABLE IF EXISTS `clinic_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinic_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `hero_title` varchar(255) DEFAULT NULL,
  `hero_description` text,
  `about_title` varchar(255) DEFAULT NULL,
  `about_description` text,
  `contact_address` text,
  `contact_phone` varchar(50) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `clinic_name` varchar(255) DEFAULT NULL,
  `footer_copyright` varchar(255) DEFAULT NULL,
  `badge_visible` varchar(10) DEFAULT '1',
  `badge_text` varchar(255) DEFAULT NULL,
  `stat_number` varchar(50) DEFAULT NULL,
  `stat_label` varchar(255) DEFAULT NULL,
  `checklist_1` varchar(255) DEFAULT NULL,
  `checklist_2` varchar(255) DEFAULT NULL,
  `checklist_3` varchar(255) DEFAULT NULL,
  `cta_primary` varchar(255) DEFAULT NULL,
  `cta_secondary` varchar(255) DEFAULT NULL,
  `accent_color` varchar(20) DEFAULT '#004872',
  `announcements_json` text,
  `team_json` text,
  `hero_image` text,
  `about_image_1` text,
  `about_image_2` text,
  `team_title` varchar(255) DEFAULT NULL,
  `team_subtitle` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_clinic_settings_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clinic_settings`
--

LOCK TABLES `clinic_settings` WRITE;
/*!40000 ALTER TABLE `clinic_settings` DISABLE KEYS */;
INSERT INTO `clinic_settings` VALUES (1,1,'','Welcome to ToothFairy. Experience a new standard of dental care where precision meets serenity.',NULL,'Serving the community in San Rafael. We believe world-class dentistry should never feel clinical.',NULL,NULL,NULL,'2026-05-25 01:23:59','ToothFairy','© 2026 ToothFairy. Professional Dental Serenity.','1','Clinical Serenity','98%','Patient Comfort Index','Curated Acoustic Environments','Bio-compatible Premium Materials','Post-treatment Serenity Lounges','Book Appointment','Explore Services','#004872','[{\"date\":\"5/23/2026\",\"title\":\"yes\",\"description\":\"Yes\"}]','[]','http://oralsync3-g6hpg2fhdyfuagdy.eastasia-01.azurewebsites.net/uploads/homepage/tenant_1_69f805f59b6e9.png','https://lh3.googleusercontent.com/aida-public/AB6AXuAFClTvpcJUUHoP5IhpaOwWPPgz8GG6P7H5xT7efg3XWtfz-01tG_XTvOTItatWorrgb4N4vOlyH9_abFeVbVyQJGmNW8keiVgjd5cguQJCy0fU3FW09mBwcP21Y6w7VyCnogTKiwY544oFdoeIhmszgf3kgTdiX9CQQfXdbVpq1oT2b5F2TXunM1WHN0FRUL_O6ogUn2vj5IwOYtpxCyGNTDYjAUiRkt45GLttu1WNn0z2WHGhjzyFT1ZozeNWmMBLy-L4nPuF-1g','https://lh3.googleusercontent.com/aida-public/AB6AXuDfL5XxGL2fbsN5rWest-yN7ja8_3q1ZbAiT_yuzB2Fgx5ys1N5W9tBmfwFCQkQgHn0cqNxRsnDX-_YPKxO7-X0HSr8Zeodhe9Zg5LM6KuHoBvrxhQMDkb8QovcTugn_OUH1ZqiFfJJQX-PBr6dihZPL6v7Fe1BldTgtYfpdZ3TWsXCvvMjRyqJ3NmzQM1vyhjj3Tb6gFhPhondxzUJqMifmdm-1PgDRq-wq5JS6FjLUZH24CsmKabNUrpikLejFVuUogJWKoJvc10','The Architects of Your Smile','Meet our world-renowned specialists dedicated to the intersection of oral health and aesthetic perfection.');
/*!40000 ALTER TABLE `clinic_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clinical_notes`
--

DROP TABLE IF EXISTS `clinical_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinical_notes` (
  `note_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `patient_id` int NOT NULL,
  `dentist_id` int DEFAULT NULL,
  `service_rendered` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `treatment_notes` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`note_id`),
  KEY `fk_notes_tenant` (`tenant_id`),
  CONSTRAINT `fk_notes_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clinical_notes`
--

LOCK TABLES `clinical_notes` WRITE;
/*!40000 ALTER TABLE `clinical_notes` DISABLE KEYS */;
INSERT INTO `clinical_notes` VALUES (1,1,5,2,NULL,'Diagnosis: test\nTreatment: \nNotes: test'),(2,1,1,2,NULL,'Diagnosis: \nTreatment: \nNotes: test'),(3,17,13,29,NULL,'Diagnosis: asd\nTreatment: asd\nNotes: asd');
/*!40000 ALTER TABLE `clinical_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dental_chart`
--

DROP TABLE IF EXISTS `dental_chart`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dental_chart` (
  `chart_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `patient_id` int NOT NULL,
  `tooth_number` int DEFAULT NULL,
  `condition_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`chart_id`),
  KEY `fk_chart_tenant` (`tenant_id`),
  CONSTRAINT `fk_chart_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dental_chart`
--

LOCK TABLES `dental_chart` WRITE;
/*!40000 ALTER TABLE `dental_chart` DISABLE KEYS */;
/*!40000 ALTER TABLE `dental_chart` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dentist`
--

DROP TABLE IF EXISTS `dentist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dentist` (
  `dentist_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`dentist_id`),
  UNIQUE KEY `username` (`username`),
  KEY `fk_dentist_tenant` (`tenant_id`),
  CONSTRAINT `fk_dentist_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dentist`
--

LOCK TABLES `dentist` WRITE;
/*!40000 ALTER TABLE `dentist` DISABLE KEYS */;
INSERT INTO `dentist` VALUES (2,1,'Michael','Gordon','toothfairy3','toothfairy@sample.com','$2y$12$E2emMTbQ02Coc9OXBPhC8u81ZYfdb5bLUyX5dLNfxoBoxkFCgXf4G'),(6,9,'John','Silvestre','radbud321231@gmail.com','radbud321231@gmail.com','$2y$12$gRg8XRgLt55gIiHL/cf2/.ZLg.39bTTk4iw3FRiWNy01H8X4u.Kza'),(30,17,'Jethro','Silva','historicalcoral@wshu.net','historicalcoral@wshu.net','$2y$12$5AHZlX8NeuAcoG4pgB1nq.qE/3FOWoHDkMgezpWddUiJCJchdLDuK');
/*!40000 ALTER TABLE `dentist` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dentist_schedule`
--

DROP TABLE IF EXISTS `dentist_schedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dentist_schedule` (
  `schedule_id` int NOT NULL AUTO_INCREMENT,
  `dentist_id` int NOT NULL,
  `tenant_id` int NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') COLLATE utf8mb4_general_ci NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_available` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`schedule_id`),
  KEY `dentist_id` (`dentist_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `dentist_schedule_ibfk_1` FOREIGN KEY (`dentist_id`) REFERENCES `dentist` (`dentist_id`) ON DELETE CASCADE,
  CONSTRAINT `dentist_schedule_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dentist_schedule`
--

LOCK TABLES `dentist_schedule` WRITE;
/*!40000 ALTER TABLE `dentist_schedule` DISABLE KEYS */;
INSERT INTO `dentist_schedule` VALUES (1,2,1,'Monday','09:00:00','17:00:00',1),(2,2,1,'Tuesday','09:00:00','17:00:00',0),(3,2,1,'Wednesday','09:00:00','17:00:00',1),(4,2,1,'Thursday','09:00:00','17:00:00',0),(5,2,1,'Friday','09:00:00','17:00:00',1),(6,2,1,'Saturday','09:00:00','17:00:00',0),(7,2,1,'Sunday','09:00:00','17:00:00',0),(8,30,17,'Monday','09:00:00','17:00:00',1),(9,30,17,'Tuesday','09:00:00','17:00:00',0),(10,30,17,'Wednesday','09:00:00','17:00:00',1),(11,30,17,'Thursday','09:00:00','17:00:00',0),(12,30,17,'Friday','09:00:00','17:00:00',1),(13,30,17,'Saturday','09:00:00','17:00:00',0),(14,30,17,'Sunday','09:00:00','17:00:00',0);
/*!40000 ALTER TABLE `dentist_schedule` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `installment_plan`
--

DROP TABLE IF EXISTS `installment_plan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `installment_plan` (
  `plan_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `patient_id` int NOT NULL,
  `billing_id` int NOT NULL COMMENT 'Original billing row this plan is for',
  `total_amount` decimal(10,2) NOT NULL COMMENT 'Total after discount',
  `monthly_amount` decimal(10,2) NOT NULL COMMENT 'total / num_months (rounded)',
  `num_months` int NOT NULL COMMENT '3 | 6 | 12',
  `months_paid` int NOT NULL DEFAULT '0',
  `status` varchar(30) NOT NULL DEFAULT 'pending_verification' COMMENT 'pending_verification | active | completed | cancelled',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`plan_id`),
  KEY `idx_patient` (`patient_id`),
  KEY `idx_billing` (`billing_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `installment_plan`
--

LOCK TABLES `installment_plan` WRITE;
/*!40000 ALTER TABLE `installment_plan` DISABLE KEYS */;
/*!40000 ALTER TABLE `installment_plan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patient`
--

DROP TABLE IF EXISTS `patient`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `patient` (
  `patient_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `contact_number` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `email` text COLLATE utf8mb4_general_ci,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `birthdate` date DEFAULT NULL,
  `gender` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `occupation` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `medical_history` text COLLATE utf8mb4_general_ci,
  `allergies` text COLLATE utf8mb4_general_ci,
  `notes` text COLLATE utf8mb4_general_ci,
  `tenant_patient_id` int NOT NULL,
  `password_reset_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = force password change on next login (for web-created accounts)',
  `id_photo_url` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Path to uploaded ID photo',
  `id_verified` varchar(20) COLLATE utf8mb4_general_ci DEFAULT 'none' COMMENT 'none | pending | verified | rejected',
  `no_show_count` int NOT NULL DEFAULT '0' COMMENT 'Auto-incremented when patient misses appointment without cancelling',
  `email_verified` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = patient has verified their email address',
  `email_verification_token` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Secure hex token sent in verification email',
  `email_verification_expires` datetime DEFAULT NULL COMMENT 'Token expiry — 24 hours from registration',
  PRIMARY KEY (`patient_id`),
  UNIQUE KEY `uq_tenant_patient` (`tenant_id`,`tenant_patient_id`),
  UNIQUE KEY `username` (`username`),
  KEY `fk_patient_tenant` (`tenant_id`),
  KEY `idx_tenant_patient_id` (`tenant_id`,`tenant_patient_id`),
  KEY `idx_patient_reset_token` (`password_reset_token`),
  KEY `idx_email_verification_token` (`email_verification_token`),
  CONSTRAINT `fk_patient_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patient`
--

LOCK TABLES `patient` WRITE;
/*!40000 ALTER TABLE `patient` DISABLE KEYS */;
INSERT INTO `patient` VALUES (1,1,'yes','Silva','09577299828','tfpatient@sample.com','$2y$12$BGNL8v.gavmwJJHcCpR93u2vreWhhZD0OqUKGv/XI8V9UsF8SGB6y','tfpatient','Somewhere, San Miguel, Bulacan','2005-03-09','Male','','','',NULL,1,NULL,NULL,0,NULL,'none',0,1,NULL,NULL),(2,1,'Jack','Coleman','09123456789','tabepak769@inraud.com','$2y$12$yYRPH8EVzHgTvMEX0fF2eeccgF3JoXLiqbnljIlERu5geDfDWEJfu','openeyes','sa bahay','2002-01-01','Prefer not to say','tambay','wala naman','mani',NULL,2,'eaa8b6be4d99d5616d2cb1ccc4993a8573ff8eaf713dba69d750cde1d3a1e988','2026-04-30 13:33:44',0,NULL,'none',0,1,NULL,NULL),(3,1,'Adonis','Hustler','09023456789','yejqaqlgyydqiqnrf@gonrr.net','$2y$12$ZIZ2adTU20d/Z3O5zDy.7u4G/JnMVyhu.PYrP0IVeLVqwULf.JM9m',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,3,NULL,NULL,0,NULL,'none',0,1,NULL,NULL),(4,1,'Adonis','Hassller','09123456789','adonis@email.com','$2y$12$Bhxy8MUGNG5u8FYDNABskuqrBTU3arZdVjMl5yW8RAGfdhhnNUUKa',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,NULL,0,NULL,'none',0,1,NULL,NULL),(5,1,'Adon','Nis','09091231231','donisyyo@email.com','$2y$12$.RneyyJOeEo53z4J4qAyceI5kLlC3bldAsTGEh7O4Or4eX/WTO0l6','adonishasler','sa club','2000-01-01','','adonis dancer','','',NULL,5,NULL,NULL,0,NULL,'none',0,1,NULL,NULL),(6,1,'KItty','Dela Cruz','09087654321','kike@email.com','$2y$12$xX7nWVeX5qrnsL983LRqE.ol1pDA.y1ltQ8VwrFCr.y/s0DCsTgMG','kittycat','tore ng disneyland','2021-04-02','Female','student','meron','meron',NULL,6,NULL,NULL,0,'/uploads/patient_ids/1/patient_6_1777786919.jpg','pending',2,1,NULL,NULL),(7,1,'Amiel','Carl santos','09959079137','amielcarlsantos.basc@gmail.com','$2y$12$FwBjjLUGhxw85HE00OIaf.BHHguARx4SWUYcSHlQcMJN/.3G5fXGy',NULL,NULL,'2003-04-26','Male',NULL,NULL,NULL,NULL,7,NULL,NULL,0,NULL,'none',0,1,NULL,NULL),(8,1,'Miko','Blanie','09086965812','copper@deltajohnson.com','$2y$12$CJ/rL1LmNicCwaBjvbvsQez6ayWcaDAEJQBRe2RYGlxxIymViy9su','delta','bahay','2000-02-03','Male','studen','bali','nuts',NULL,8,NULL,NULL,0,NULL,'none',0,1,NULL,NULL),(9,1,'Bogart','Bogard','09123456789','bogart@example.com','$2y$12$w0ORTr0/vrL3SeCC1uOlJOk8zK7XqNenHw4t4UveBADmSDBT7lA9K',NULL,NULL,'2020-02-02','Prefer not to say',NULL,NULL,NULL,NULL,9,NULL,NULL,0,NULL,'none',0,1,NULL,NULL),(10,1,'K','Picasso','09123456789','kuntilpicasso@gmail.com','$2y$12$FQQWDiRFYFEYJSxWtpTSUOWImda9U8zfODcvSYYU8IsAlOomHknhm',NULL,NULL,'2000-01-01','Male',NULL,NULL,NULL,NULL,10,NULL,NULL,0,NULL,'none',0,1,'c306edba112bb49302ccce4ef347d0404de81200b0ba7d9f22f2229a3798ac68','2026-05-25 12:53:43'),(11,1,'TESTER','TO','09123456789','sesiv21580@nriza.com','$2y$12$Kmzh3QqLlOn797ZT8a9f9.RafwVN6Y2q6hGwypK0d3FvCre4LsAbe','account3','','2000-01-02','Other','','','',NULL,11,'a554a93bba0e3d66e3c5a64ba466dc6e59035c8613a2fbc27a26fc1200a2cb5f','2026-05-26 16:58:10',0,NULL,'none',1,1,NULL,NULL),(12,1,'Jericho','Rosales','09477230297','darkagedbat@gmail.com','$2y$12$13X1WTTPhT.8clE.yV7kA.z9xnFoh2b/v1MAHf/fIeW5yKMCNv9o6','tfpatient4','Taga dyan lang','1994-05-25','Male',NULL,NULL,NULL,NULL,12,NULL,NULL,0,NULL,'none',0,0,NULL,NULL),(13,17,'Carl Micko','Tibay','09477230297','5593developing@wshu.net','$2y$12$4QsRuuvecXMXUia0/8OmdOzsOfI8RUf.UoJIX9s1GUgf9q2gjyr0i','vdcpatient','Dito lang','2005-02-28','Male',NULL,NULL,NULL,NULL,1,NULL,NULL,0,NULL,'none',0,0,NULL,NULL);
/*!40000 ALTER TABLE `patient` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patient_documents`
--

DROP TABLE IF EXISTS `patient_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `patient_documents` (
  `doc_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `patient_id` int NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(511) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`doc_id`),
  KEY `fk_pat_doc_tenant` (`tenant_id`),
  KEY `fk_pat_doc_patient` (`patient_id`),
  CONSTRAINT `fk_pat_doc_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patient_documents`
--

LOCK TABLES `patient_documents` WRITE;
/*!40000 ALTER TABLE `patient_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `patient_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patient_payment`
--

DROP TABLE IF EXISTS `patient_payment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `patient_payment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `patient_id` int NOT NULL,
  `appointment_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_mode` varchar(50) DEFAULT NULL,
  `status` enum('pending','succeeded','failed','refunded') DEFAULT 'pending',
  `paymongo_session_id` varchar(255) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patient_payment`
--

LOCK TABLES `patient_payment` WRITE;
/*!40000 ALTER TABLE `patient_payment` DISABLE KEYS */;
/*!40000 ALTER TABLE `patient_payment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment`
--

DROP TABLE IF EXISTS `payment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment` (
  `payment_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `subscription_tier` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'startup',
  `appointment_id` int DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `billing_period_start` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `billing_period_end` timestamp NULL DEFAULT NULL,
  `mode` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Cash',
  `status` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `procedures_json` text COLLATE utf8mb4_general_ci,
  `source` enum('web','mobile') COLLATE utf8mb4_general_ci DEFAULT 'web',
  `reference_number` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `payment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `payment_type` enum('deposit','full') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'full',
  `paymongo_link_id` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `paymongo_payment_id` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`payment_id`),
  KEY `fk_payment_tenant` (`tenant_id`),
  KEY `idx_payment_date` (`payment_date`),
  KEY `idx_payment_tenant_date` (`tenant_id`,`payment_date`),
  KEY `idx_payment_source` (`source`),
  CONSTRAINT `fk_payment_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment`
--

LOCK TABLES `payment` WRITE;
/*!40000 ALTER TABLE `payment` DISABLE KEYS */;
INSERT INTO `payment` VALUES (1,1,'startup',NULL,100.00,'2026-05-01 13:55:40',NULL,'online','pending',NULL,'web',NULL,'2026-04-28 15:06:08','full','cs_575f581b0a0e9dc8575552a0',NULL),(2,1,'startup',NULL,100.00,'2026-05-01 13:55:40',NULL,'online','pending',NULL,'web',NULL,'2026-04-28 15:08:51','full','cs_c17cb71f2fe4db42d3a2a9f7',NULL),(3,1,'startup',NULL,100.00,'2026-05-01 13:55:40',NULL,'online','pending',NULL,'web',NULL,'2026-04-28 15:33:14','full','cs_c45aed70f9c5cf2663227694',NULL),(4,1,'startup',NULL,23123.00,'2026-05-01 13:55:40',NULL,'Online','pending','{\"plan_name\":\"Tenant Subscription Update\",\"description\":\"Manual subscription adjustment\"}','web',NULL,'2026-04-29 12:39:37','full','cs_49f929e79c577f28d669fe29',NULL),(5,1,'startup',NULL,23123.00,'2026-05-01 13:55:40',NULL,'Online','pending','{\"plan_name\":\"Tenant Subscription Update\",\"description\":\"Manual subscription adjustment\"}','web',NULL,'2026-04-29 12:39:44','full','cs_84cc96792c08a470dbf49a05',NULL),(6,1,'startup',NULL,12121.00,'2026-05-01 13:55:40',NULL,'Online','pending','{\"plan_name\":\"Tenant Subscription Update\",\"description\":\"Manual subscription adjustment\"}','web',NULL,'2026-04-29 12:44:14','full','cs_d191452bf8a7d05a8e35fa91',NULL),(7,1,'startup',NULL,12121.00,'2026-05-01 13:55:40',NULL,'Online','pending','{\"plan_name\":\"Tenant Subscription Update\",\"description\":\"Manual subscription adjustment\"}','web',NULL,'2026-04-29 12:44:15','full','cs_f4e5cc92d444facfd5f7c1be',NULL),(8,1,'startup',NULL,12121.00,'2026-05-01 13:55:40',NULL,'Online','pending','{\"plan_name\":\"Tenant Subscription Update\",\"description\":\"Manual subscription adjustment\"}','web',NULL,'2026-04-29 12:45:18','full','cs_f4017b64a35d0fe5305882ad',NULL),(9,1,'startup',NULL,121.00,'2026-05-01 13:55:40',NULL,'Online','pending','{\"plan_name\":\"Tenant Subscription Update\",\"description\":\"Manual subscription adjustment\"}','web',NULL,'2026-04-29 13:37:05','full','cs_bb7a4bf344f9b740653c12c4',NULL),(10,1,'startup',NULL,3232.00,'2026-05-01 13:55:40',NULL,'Online','pending','{\"plan_name\":\"Tenant Subscription Update\",\"description\":\"Manual subscription adjustment\"}','web',NULL,'2026-04-29 13:37:52','full','cs_4b80ab2f02027d59231382d1',NULL),(11,1,'startup',NULL,1111.00,'2026-05-01 13:55:40',NULL,'Online','pending','{\"plan_name\":\"Tenant Subscription Update\",\"description\":\"Manual subscription adjustment\"}','web',NULL,'2026-04-29 13:45:47','full','cs_045e7da901216530ce051580',NULL),(12,1,'startup',NULL,1500.00,'2026-05-01 13:55:40',NULL,'Online','pending','{\"item\":\"Platform Subscription Renewal\"}','web',NULL,'2026-04-30 14:31:57','full','cs_6f2b9e81c5f0aef3664259e6',NULL),(13,1,'startup',NULL,1500.00,'2026-05-01 13:55:40',NULL,'Online','pending','{\"item\":\"Platform Subscription Renewal\"}','web',NULL,'2026-04-30 14:32:11','full','cs_2872ef2a484719541615e02d',NULL),(14,1,'startup',NULL,1500.00,'2026-05-01 13:55:40',NULL,'Online','pending','{\"item\":\"Platform Subscription Renewal\"}','web',NULL,'2026-04-30 14:33:56','full','cs_a25735a461cc0c4a767b51c4',NULL),(15,1,'startup',NULL,249.00,'2026-05-01 13:55:41',NULL,'Cash','paid','{\"item\": \"Legacy Subscription\", \"tier\": \"professional\"}','web',NULL,'2026-04-28 12:59:43','full',NULL,NULL),(16,2,'startup',NULL,124.00,'2026-05-01 13:55:41',NULL,'Cash','paid','{\"item\": \"Legacy Subscription\", \"tier\": \"startup\"}','web',NULL,'2026-04-29 23:24:01','full',NULL,NULL),(18,1,'startup',NULL,5000.00,'2026-05-02 11:34:39',NULL,'Online','pending','{\"item\":\"Subscription: Multi-Clinic Plan\"}','web',NULL,'2026-05-02 11:34:39','full','cs_9f842d091bcbc62e5f26fe74',NULL),(19,1,'startup',NULL,1500.00,'2026-05-02 11:35:20',NULL,'Online','pending','{\"item\":\"Subscription: Professional Plan\"}','web',NULL,'2026-05-02 11:35:20','full','cs_82a56ad5023d7bee3c70ba83',NULL),(20,1,'startup',NULL,500.00,'2026-05-02 11:35:55',NULL,'Online','pending','{\"item\":\"Subscription: Startup Plan\"}','web',NULL,'2026-05-02 11:35:55','deposit','cs_21e533325cf62cd005c86c4c',NULL),(21,1,'startup',NULL,1500.00,'2026-05-02 11:36:42',NULL,'Online','pending','{\"item\":\"Subscription: Professional Plan\"}','web',NULL,'2026-05-02 11:36:42','full','cs_2bf2faa9ecbc45548c4ce3ca',NULL),(22,3,'startup',NULL,249.00,'2026-05-02 13:09:34',NULL,'Cash','paid','{\"item\":\"Initial Subscription\",\"tier\":\"professional\",\"billing_period_start\":\"2026-05-03 00:00:00\",\"billing_period_end\":\"2027-05-02 23:59:59\"}','web',NULL,'2026-05-02 13:09:34','full',NULL,NULL),(23,6,'startup',NULL,1488.00,'2026-05-03 11:52:40',NULL,'Cash','pending','{\"item\":\"Initial Subscription\",\"tier\":\"startup\",\"billing_period_start\":\"2026-05-03 00:00:00\",\"billing_period_end\":\"2027-05-02 23:59:59\",\"duration\":12}','web',NULL,'2026-05-03 11:52:40','full','cs_85f2af1dad0850a468c622fa',NULL),(24,7,'startup',NULL,0.00,'2026-05-03 11:58:58',NULL,'Cash','paid','{\"item\":\"Initial Subscription\",\"tier\":\"trial\",\"billing_period_start\":\"2026-05-03 00:00:00\",\"billing_period_end\":\"2027-05-02 23:59:59\",\"duration\":12}','web',NULL,'2026-05-03 11:58:58','full',NULL,NULL),(25,8,'startup',NULL,2988.00,'2026-05-03 12:03:21',NULL,'Cash','paid','{\"item\":\"Initial Subscription\",\"tier\":\"professional\",\"billing_period_start\":\"2026-05-03 00:00:00\",\"billing_period_end\":\"2027-05-02 23:59:59\",\"duration\":12}','web',NULL,'2026-05-03 12:03:42','full','cs_943c7358bb4a034a02c81057','pay_r6N4ShbQ5HQNxnRotUmtBX85'),(26,9,'startup',NULL,2988.00,'2026-05-03 14:59:25',NULL,'Cash','paid','{\"item\":\"Initial Subscription\",\"tier\":\"professional\",\"billing_period_start\":\"2026-05-04 00:00:00\",\"billing_period_end\":\"2027-05-03 23:59:59\",\"duration\":12}','web',NULL,'2026-05-03 15:00:45','full','cs_55e3f5b39b1fd4e1a54ba860','pay_CF8fWzo2pMQz42sogbXNDKHQ'),(27,10,'startup',NULL,2988.00,'2026-05-03 23:33:53',NULL,'Cash','pending','{\"item\":\"Initial Subscription\",\"tier\":\"professional\",\"billing_period_start\":\"2026-05-05 00:00:00\",\"billing_period_end\":\"2027-05-04 23:59:59\",\"duration\":12}','web',NULL,'2026-05-03 23:33:53','full','cs_a2a93897063ed7050f7a9b7a',NULL),(28,11,'startup',NULL,1488.00,'2026-05-04 00:04:54',NULL,'Cash','pending','{\"item\":\"Initial Subscription\",\"tier\":\"startup\",\"billing_period_start\":\"2026-05-05 00:00:00\",\"billing_period_end\":\"2027-05-04 23:59:59\",\"duration\":12}','web',NULL,'2026-05-04 00:04:54','full','cs_17de52a34a947857fe25c678',NULL),(29,12,'startup',NULL,2988.00,'2026-05-04 01:24:49',NULL,'Cash','pending','{\"item\":\"Initial Subscription\",\"tier\":\"professional\",\"billing_period_start\":\"2026-05-05 00:00:00\",\"billing_period_end\":\"2027-05-04 23:59:59\",\"duration\":12}','web',NULL,'2026-05-04 01:24:48','full','cs_ae9c9979d1362f8b38500eea',NULL),(30,13,'startup',NULL,2988.00,'2026-05-04 01:29:19',NULL,'Cash','paid','{\"item\":\"Initial Subscription\",\"tier\":\"professional\",\"billing_period_start\":\"2026-05-05 00:00:00\",\"billing_period_end\":\"2027-05-04 23:59:59\",\"duration\":12}','web',NULL,'2026-05-04 01:29:56','full','cs_1eadb8a1a54468559268866a','pay_fE1uRuRTdL5J2xNX6FTaMsM5'),(31,14,'startup',NULL,2988.00,'2026-05-20 17:33:19',NULL,'Cash','pending','{\"item\":\"Initial Subscription\",\"tier\":\"professional\",\"billing_period_start\":\"2026-05-21 00:00:00\",\"billing_period_end\":\"2027-05-20 23:59:59\",\"duration\":12}','web',NULL,'2026-05-20 17:33:18','full','cs_36500fbea39efef18c736f48',NULL),(32,15,'startup',NULL,2988.00,'2026-05-21 08:32:25',NULL,'Cash','pending','{\"item\":\"Initial Subscription\",\"tier\":\"professional\",\"billing_period_start\":\"2026-05-21 00:00:00\",\"billing_period_end\":\"2027-05-20 23:59:59\",\"duration\":12}','web',NULL,'2026-05-21 08:32:25','full','cs_99f278dee11381de3254d206',NULL),(33,16,'startup',NULL,2988.00,'2026-05-21 09:11:55',NULL,'Cash','paid','{\"item\":\"Initial Subscription\",\"tier\":\"professional\",\"billing_period_start\":\"2026-05-21 00:00:00\",\"billing_period_end\":\"2027-05-20 23:59:59\",\"duration\":12}','web',NULL,'2026-05-21 09:12:13','full','cs_bbf0be3673fb2f4d72e82854','pay_JwaaZtCX1Xp88MNvB2xDHAAi'),(34,17,'startup',NULL,2988.00,'2026-05-28 03:53:43',NULL,'Cash','paid','{\"item\":\"Initial Subscription\",\"tier\":\"professional\",\"billing_period_start\":\"2026-05-28 00:00:00\",\"billing_period_end\":\"2027-05-27 23:59:59\",\"duration\":12}','web',NULL,'2026-05-28 03:54:49','full','cs_6ea8973d5022c7d8b8954e86','pay_NJxNRncaDZ4VcmdRPBJ2W5iH');
/*!40000 ALTER TABLE `payment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_methods`
--

DROP TABLE IF EXISTS `payment_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_methods` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `provider` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `brand` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last4` varchar(4) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `exp_month` tinyint DEFAULT NULL,
  `exp_year` smallint DEFAULT NULL,
  `billing_contact` json DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_payment_methods_tenant_id` (`tenant_id`),
  CONSTRAINT `payment_methods_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_methods`
--

LOCK TABLES `payment_methods` WRITE;
/*!40000 ALTER TABLE `payment_methods` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_methods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service`
--

DROP TABLE IF EXISTS `service`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service` (
  `service_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `service_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_general_ci DEFAULT 'General',
  `duration_minutes` int NOT NULL DEFAULT '30' COMMENT 'Estimated procedure duration in minutes, excluding sanitation buffer',
  PRIMARY KEY (`service_id`),
  KEY `fk_service_tenant` (`tenant_id`),
  CONSTRAINT `fk_service_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service`
--

LOCK TABLES `service` WRITE;
/*!40000 ALTER TABLE `service` DISABLE KEYS */;
INSERT INTO `service` VALUES (1,2,'Oral Prophylaxis','Professional cleaning to remove plaque, tartar, and stains. Includes polishing.',1200.00,'Preventive',45),(2,2,'Fluoride Treatment','Concentrated fluoride gel to strengthen enamel and prevent cavities.',600.00,'Preventive',20),(3,2,'Dental Sealants','Protective coating for deep pits and fissures to prevent decay.',500.00,'Preventive',30),(4,2,'Pediatric Checkup','Dental exam for children including caries risk and growth monitoring.',400.00,'Pediatric',30),(5,2,'Pulpotomy','Root canal treatment for primary teeth to preserve them until permanent eruption.',2000.00,'Pediatric',60),(6,2,'Space Maintainer','Custom appliance to maintain space for permanent teeth after premature loss.',2500.00,'Pediatric',45),(7,2,'Complete Dentures','Full arch dentures for patients missing all teeth. Custom-fitted.',12000.00,'Prosthodontics',60),(8,2,'Partial Dentures','Removable partial denture to replace multiple missing teeth.',6500.00,'Prosthodontics',60),(9,2,'Denture Repair','Repair and reline services for broken or ill-fitting dentures.',1500.00,'Prosthodontics',30),(10,2,'Denture Relining','Relining service to improve the fit of existing dentures.',1000.00,'Prosthodontics',45),(11,2,'Teeth Whitening','Professional in-office whitening using LED. Up to 8 shades lighter.',6500.00,'Cosmetic',90),(12,2,'Veneers (per tooth)','Custom porcelain veneers to cover chipped or stained teeth.',4500.00,'Cosmetic',60),(13,2,'Dental Bonding','Composite resin to repair chipped or discolored teeth. Same-day results.',2500.00,'Cosmetic',45),(14,2,'Smile Design','Smile analysis and design consultation using digital imaging.',500.00,'Cosmetic',30),(15,2,'Braces (Metal)','Traditional metal braces for alignment. Includes all adjustments.',30000.00,'Orthodontics',90),(16,2,'Braces (Ceramic)','Discreet tooth-colored ceramic braces for aesthetic alignment.',45000.00,'Orthodontics',90),(17,2,'Retainer','Custom retainers to maintain position after treatment. Hawley or Essix.',4000.00,'Orthodontics',30),(18,2,'Ortho Consultation','Initial consultation for treatment planning and options.',800.00,'Orthodontics',30),(19,2,'Tooth Extraction','Simple extraction for damaged teeth. Includes local anesthesia.',1200.00,'Surgery',45),(20,2,'Wisdom Tooth Removal','Surgical extraction of impacted wisdom teeth. Includes post-op care.',4500.00,'Surgery',90),(21,2,'Apicoectomy','Surgical removal of the tooth root tip when root canal fails.',7000.00,'Surgery',90),(22,2,'Frenectomy','Surgical removal of the frenum to improve mobility and function.',3500.00,'Surgery',45),(23,2,'Filling (Amalgam)','Durable silver amalgam filling for posterior cavities.',1000.00,'Restorative',45),(24,2,'Filling (Composite)','Tooth-colored composite resin filling for a natural appearance.',1500.00,'Restorative',45),(25,2,'Inlay/Onlay','Custom indirect restoration for moderately damaged teeth.',5000.00,'Restorative',60),(26,2,'Root Canal','Endodontic treatment to save infected teeth. Includes cleaning/sealing.',7000.00,'Restorative',90),(27,2,'Dental Crown','Custom porcelain or ceramic crown to restore damaged teeth.',8000.00,'Restorative',60),(28,2,'X-Ray (Periapical)','X-ray showing entire tooth crown to root. Essential for diagnosis.',250.00,'Others',15),(29,2,'Panoramic X-Ray','Full jaw X-ray showing all teeth, sinuses, and joints.',700.00,'Others',20),(30,2,'Dental Consultation','Initial examination, treatment planning, and cost estimate.',400.00,'Others',30),(31,2,'Emergency Visit','Same-day emergency appointment for acute pain or trauma.',800.00,'Others',30),(32,2,'Second Opinion','Comprehensive consultation with assessment and recommendations.',500.00,'Others',30),(33,1,'Oral Prophylaxis','Professional teeth cleaning to remove plaque, tartar, and surface stains. Includes polishing and oral hygiene instructions.',1500.00,'Preventive',45),(34,1,'Fluoride Treatment','Application of concentrated fluoride gel to strengthen tooth enamel and prevent cavities. Recommended every 6 months.',800.00,'Preventive',20),(35,1,'Dental Sealants','Protective coating applied to deep pits and fissures on teeth to prevent decay. Ideal for children and adolescents.',600.00,'Preventive',30),(36,1,'Pediatric Dental Checkup','Comprehensive dental examination for children including caries risk assessment and growth monitoring.',500.00,'Pediatric',30),(37,1,'Pulpotomy','Root canal treatment for primary (milk) teeth where the pulp is infected. Preserves the natural tooth until permanent tooth erupts.',2500.00,'Pediatric',60),(38,1,'Space Maintainer','Custom orthodontic appliance to maintain space for permanent teeth when a primary tooth is lost prematurely.',3000.00,'Pediatric',45),(39,1,'Complete Dentures','Full arch dentures for patients missing all teeth. Custom-fitted for comfort and natural appearance.',15000.00,'Prosthodontics',60),(40,1,'Partial Dentures','Removable partial denture to replace multiple missing teeth while preserving remaining natural teeth.',8000.00,'Prosthodontics',60),(41,1,'Denture Repair','Repair and reline services for broken or ill-fitting dentures. Same-day service available.',2000.00,'Prosthodontics',30),(42,1,'Teeth Whitening','Professional in-office teeth whitening using advanced LED technology. Achieves up to 8 shades lighter in one session.',8000.00,'Cosmetic',90),(43,1,'Veneers (per tooth)','Custom porcelain veneers to cover chipped, stained, or misaligned teeth. Per-tooth pricing.',5000.00,'Cosmetic',60),(44,1,'Dental Bonding','Composite resin application to repair chipped, cracked, or discolored teeth. Same-day results.',3000.00,'Cosmetic',45),(45,1,'Braces (Metal)','Traditional metal braces for comprehensive teeth alignment. Includes all adjustments for the treatment duration.',35000.00,'Orthodontics',90),(46,1,'Braces (Ceramic)','Tooth-colored ceramic braces for discreet teeth alignment. More aesthetic option while maintaining effectiveness.',50000.00,'Orthodontics',90),(47,1,'Retainer','Custom retainers to maintain teeth position after orthodontic treatment. Available in Hawley and Essix styles.',5000.00,'Orthodontics',30),(48,1,'Tooth Extraction','Simple tooth extraction for severely damaged or decayed teeth. Includes local anesthesia and post-op care instructions.',1500.00,'Surgery',45),(49,1,'Wisdom Tooth Removal','Surgical extraction of impacted or partially erupted wisdom teeth. Includes sedation options and post-operative care.',5000.00,'Surgery',90),(50,1,'Apicoectomy','Surgical removal of the tooth root tip and surrounding infected tissue. Performed when conventional root canal fails.',8000.00,'Surgery',90),(51,1,'Dental Filling (Amalgam)','Traditional silver amalgam filling for cavities. Durable and cost-effective for posterior teeth.',1200.00,'Restorative',45),(52,1,'Dental Filling (Composite)','Tooth-colored composite resin filling for a natural appearance. Suitable for front and back teeth.',1800.00,'Restorative',45),(53,1,'Inlay/Onlay','Custom laboratory-made indirect restoration for moderately damaged teeth. More conservative than full crowns.',6000.00,'Restorative',60),(54,1,'Root Canal Treatment','Endodontic treatment to save infected teeth. Includes cleaning, shaping, and sealing of the root canal system.',8000.00,'Restorative',90),(55,1,'Dental X-Ray (Periapical)','Periapical X-ray showing entire tooth from crown to root tip. Essential for diagnosing root infections.',300.00,'Others',15),(56,1,'Panoramic X-Ray','Full jaw panoramic X-ray showing all teeth, sinuses, and Jaw joint. Comprehensive diagnostic imaging.',800.00,'Others',20),(57,1,'Dental Consultation','Initial dental consultation and examination. Includes treatment planning and cost estimate.',500.00,'Others',30),(58,1,'Basic Cleaning','',800.00,'Preventive',30);
/*!40000 ALTER TABLE `service` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'system_name','OralSync','2026-05-03 17:34:37'),(5,'logo_path','','2026-05-03 17:34:37');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_details`
--

DROP TABLE IF EXISTS `staff_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_details` (
  `staff_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role` enum('Dentist','Receptionist','Assistant') COLLATE utf8mb4_general_ci DEFAULT 'Receptionist',
  `public_bio` text COLLATE utf8mb4_general_ci,
  `specialties` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `profile_image_path` varchar(511) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('Active','Inactive') COLLATE utf8mb4_general_ci DEFAULT 'Active',
  `hired_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_public_visible` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`staff_id`),
  UNIQUE KEY `email` (`email`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `staff_details_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_details`
--

LOCK TABLES `staff_details` WRITE;
/*!40000 ALTER TABLE `staff_details` DISABLE KEYS */;
INSERT INTO `staff_details` VALUES (1,1,'Ray','Cenat','partialardith@deltajohnsons.com','09477153245','Dentist','','',NULL,'Active','2026-05-02 13:39:52',0),(2,1,'Lord','Farquad','54belita@deltajohnsons.com','09471923245','Receptionist','','',NULL,'Active','2026-05-02 17:18:47',0);
/*!40000 ALTER TABLE `staff_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscription_plans`
--

DROP TABLE IF EXISTS `subscription_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscription_plans` (
  `plan_id` int NOT NULL AUTO_INCREMENT,
  `plan_name` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration_days` int DEFAULT '30',
  `max_patients` int DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`plan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscription_plans`
--

LOCK TABLES `subscription_plans` WRITE;
/*!40000 ALTER TABLE `subscription_plans` DISABLE KEYS */;
INSERT INTO `subscription_plans` VALUES (1,'Startup Plan',500.00,30,100,'active','2026-04-30 14:46:07'),(2,'Professional Plan',1500.00,30,500,'active','2026-04-30 14:46:07'),(3,'Multi-Clinic Plan',5000.00,30,NULL,'active','2026-04-30 14:46:07');
/*!40000 ALTER TABLE `subscription_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscriptions`
--

DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `plan_id` int NOT NULL,
  `status` enum('trialing','active','past_due','canceled','inactive') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'trialing',
  `trial_starts_at` datetime DEFAULT NULL,
  `trial_ends_at` datetime DEFAULT NULL,
  `current_period_start` datetime DEFAULT NULL,
  `current_period_end` datetime DEFAULT NULL,
  `auto_renew` tinyint(1) NOT NULL DEFAULT '1',
  `payment_method_id` bigint unsigned DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `currency` varchar(8) COLLATE utf8mb4_general_ci DEFAULT 'PHP',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_subscriptions_tenant_id` (`tenant_id`),
  KEY `idx_subscriptions_plan_id` (`plan_id`),
  CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscriptions`
--

LOCK TABLES `subscriptions` WRITE;
/*!40000 ALTER TABLE `subscriptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `super_admins`
--

DROP TABLE IF EXISTS `super_admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `super_admins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `password_reset_token` varchar(255) DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `email` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_superadmin_reset_token` (`password_reset_token`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `super_admins`
--

LOCK TABLES `super_admins` WRITE;
/*!40000 ALTER TABLE `super_admins` DISABLE KEYS */;
INSERT INTO `super_admins` VALUES (1,'admin','$2y$12$PoszD9ADAoR1.Z86Yilhf.qdtTfKRt3F2fCPNf2siYUgNVTN0suBW',NULL,NULL,'2026-05-29 10:34:25','2026-04-28 12:04:27','darkagedbat@gmail.com'),(2,'admin2','$2y$12$m8AzkSow3xZT7.0ZxHQHNuQrX3K4BchcU30hAe8btO8Q5jrI7oQW6',NULL,NULL,'2026-05-03 02:08:25','2026-05-03 00:01:32','8eloise@deltajohnsons.com'),(3,'small2090@deltajohnsons.com','$2y$12$dwDMFi0C3JVJmFQanQD.Bu/2d7WIilBiflnbDi9P72F4RSbPwBOo.',NULL,NULL,'2026-05-03 15:04:25','2026-05-03 15:03:48','small2090@deltajohnsons.com');
/*!40000 ALTER TABLE `super_admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `superadmin_logs`
--

DROP TABLE IF EXISTS `superadmin_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `superadmin_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `activity_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `action_details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'superadmin',
  `admin_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Super Admin',
  `log_date` date NOT NULL,
  `log_time` time DEFAULT NULL,
  PRIMARY KEY (`log_id`),
  KEY `idx_log_date` (`log_date`)
) ENGINE=InnoDB AUTO_INCREMENT=203 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `superadmin_logs`
--

LOCK TABLES `superadmin_logs` WRITE;
/*!40000 ALTER TABLE `superadmin_logs` DISABLE KEYS */;
INSERT INTO `superadmin_logs` VALUES (1,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-28','12:11:50'),(2,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-28','12:12:13'),(3,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-28','12:38:14'),(4,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-28','12:52:22'),(5,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-28','12:58:40'),(6,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-28','12:58:51'),(7,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-28','12:59:08'),(8,'Registration','Registered: ToothFairy (Tier: professional)','sound762@deltajohnsons.com','superadmin','Super Admin','2026-04-28','12:59:43'),(9,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-28','13:00:31'),(10,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-28','14:45:59'),(11,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-28','14:56:42'),(12,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-29','05:24:23'),(13,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-29','05:56:32'),(14,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-29','12:42:34'),(15,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-29','13:55:11'),(16,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-29','14:12:41'),(17,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-29','16:37:09'),(18,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-29','16:42:42'),(19,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-29','23:21:54'),(20,'Registration','Registered: Pearl White Dental Center (Tier: startup)','glorisalmon@deltajohnsons.com','superadmin','Super Admin','2026-04-29','23:24:01'),(21,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-29','23:46:02'),(22,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-30','00:03:15'),(23,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-30','00:27:25'),(24,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-30','00:31:11'),(25,'Upload','Uploaded 1 document(s) for: Pearl White Dental Center','System','superadmin','Super Admin','2026-04-30','00:31:32'),(26,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-30','14:03:47'),(27,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-30','15:56:35'),(28,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-30','16:32:06'),(29,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-01','05:08:35'),(30,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-01','13:41:35'),(31,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-01','13:56:43'),(32,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-01','14:03:14'),(33,'Login','Superadmin logged in','darkagedbat@gmail.com','superadmin','Super Admin','2026-05-01','14:03:19'),(34,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-02','07:23:41'),(35,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-02','07:50:12'),(36,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-02','07:50:15'),(37,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-02','07:50:45'),(38,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-02','08:36:33'),(39,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-02','09:16:52'),(40,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-02','09:40:18'),(41,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-02','10:40:32'),(42,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-02','10:58:08'),(43,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-02','11:00:17'),(44,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-02','11:02:41'),(45,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-02','11:12:51'),(46,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-02','11:33:30'),(47,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-02','11:40:15'),(48,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-02','11:41:11'),(49,'Registration','Registered: Zenith Dental Collective (Tier: professional)','timothea586@deltajohnsons.com','superadmin','Super Admin','2026-05-02','13:09:34'),(50,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-02','13:34:12'),(51,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-02','13:35:49'),(52,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-02','13:36:09'),(53,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-02','13:41:53'),(54,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-02','14:06:35'),(55,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-02','15:26:13'),(56,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-02','15:26:42'),(57,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-02','16:57:52'),(58,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-02','17:16:30'),(59,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-02','23:40:38'),(60,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','00:01:14'),(61,'Login','Superadmin logged in','8eloise@deltajohnsons.com','superadmin','Super Admin','2026-05-03','00:02:27'),(62,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','00:06:21'),(63,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','01:30:59'),(64,'Tenant Status Change','Tenant status changed to inactive',NULL,'superadmin','Super Admin','2026-05-03','01:31:14'),(65,'Tenant Status Change','Tenant status changed to active',NULL,'superadmin','Super Admin','2026-05-03','01:31:18'),(66,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-03','01:33:41'),(67,'Login','Superadmin logged in','8eloise@deltajohnsons.com','superadmin','Super Admin','2026-05-03','01:34:05'),(68,'Logout','Superadmin logged out','8eloise@deltajohnsons.com','superadmin','Super Admin','2026-05-03','01:35:16'),(69,'Login','Superadmin logged in','admin2','superadmin','Super Admin','2026-05-03','01:35:24'),(70,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','01:54:28'),(71,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','01:59:10'),(72,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-03','02:02:02'),(73,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','02:04:23'),(74,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-03','02:04:46'),(75,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','02:04:54'),(76,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-03','02:05:20'),(77,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','02:05:34'),(78,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-03','02:05:59'),(79,'Login','Superadmin logged in','admin2','superadmin','Super Admin','2026-05-03','02:06:22'),(80,'Logout','Superadmin logged out','admin2','superadmin','Super Admin','2026-05-03','02:06:24'),(81,'Login','Superadmin logged in','admin2','superadmin','Super Admin','2026-05-03','02:07:39'),(82,'Logout','Superadmin logged out','admin2','superadmin','Super Admin','2026-05-03','02:08:20'),(83,'Login','Superadmin logged in','admin2','superadmin','Super Admin','2026-05-03','02:08:25'),(84,'Logout','Superadmin logged out','admin2','superadmin','Super Admin','2026-05-03','02:08:29'),(85,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','02:17:10'),(86,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','03:16:25'),(87,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','03:48:32'),(88,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','03:58:52'),(89,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','04:00:43'),(90,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','04:43:22'),(91,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','04:59:21'),(92,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','05:18:53'),(93,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','05:35:56'),(94,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-03','06:01:51'),(95,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','06:02:25'),(96,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','07:57:34'),(97,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','08:14:01'),(98,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','08:49:41'),(99,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','08:54:11'),(100,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','08:58:49'),(101,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-03','09:08:33'),(102,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','09:12:50'),(103,'Registration','Registered: ABD (Tier: startup, Status: inactive)','amielcarlsantos26@gmail.com','superadmin','Super Admin','2026-05-03','09:20:48'),(104,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','09:21:55'),(105,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-03','09:22:57'),(106,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','09:23:04'),(107,'Tenant Status Change','Tenant status changed to active',NULL,'superadmin','Super Admin','2026-05-03','09:25:03'),(108,'Tenant Status Change','Tenant status changed to inactive',NULL,'superadmin','Super Admin','2026-05-03','09:25:04'),(109,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','09:45:20'),(110,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','09:46:09'),(111,'Registration','Registered: Santos (Tier: startup, Status: inactive)','amielcarlsantos.basc@gmail.com','superadmin','Super Admin','2026-05-03','09:46:47'),(112,'Tenant Status Change','Tenant status changed to active',NULL,'superadmin','Super Admin','2026-05-03','09:48:03'),(113,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','09:55:16'),(114,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','10:51:16'),(115,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-03','10:54:31'),(116,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','10:54:35'),(117,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','11:51:18'),(118,'Registration','Registered: bmw (Tier: startup, Status: inactive)','hibabif987@justnapa.com','superadmin','Super Admin','2026-05-03','11:52:40'),(119,'Registration','Registered: toyota (Tier: trial, Status: active)','wecolov551@justnapa.com','superadmin','Super Admin','2026-05-03','11:58:58'),(120,'Registration','Registered: ror (Tier: professional, Status: inactive)','jimek10787@inreur.com','superadmin','Super Admin','2026-05-03','12:03:20'),(121,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','12:12:11'),(122,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','12:21:20'),(123,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-03','12:51:21'),(124,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','12:52:10'),(125,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','13:35:16'),(126,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','14:51:43'),(127,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-03','14:51:47'),(128,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','14:52:11'),(129,'Registration','Registered: MiniACE (Tier: professional, Status: inactive)','small2090@deltajohnsons.com','superadmin','Super Admin','2026-05-03','14:59:25'),(130,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-03','15:04:13'),(131,'Login','Superadmin logged in','small2090@deltajohnsons.com','superadmin','Super Admin','2026-05-03','15:04:25'),(132,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-03','15:05:20'),(133,'Tenant Status Change','Tenant status changed to active',NULL,'superadmin','Super Admin','2026-05-03','15:44:20'),(134,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-04','00:37:43'),(135,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-04','00:42:17'),(136,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-04','00:43:18'),(137,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-04','00:43:27'),(138,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-04','01:11:19'),(139,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-04','01:32:06'),(140,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-04','01:34:28'),(141,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-04','01:43:58'),(142,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-04','02:19:52'),(143,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-04','07:06:09'),(144,'Registration','Registered: Rabid White (Tier: professional, Status: inactive)','grosskalli@deltajohnsons.com','superadmin','Super Admin','2026-05-04','07:33:53'),(145,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-04','07:35:05'),(146,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-04','08:02:46'),(147,'Registration','Registered: ShinyWhite (Tier: startup, Status: inactive)','bronze4887@deltajohnsons.com','superadmin','Super Admin','2026-05-04','08:04:54'),(148,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-04','08:05:29'),(149,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-04','08:05:40'),(150,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-04','08:18:20'),(151,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-04','08:19:06'),(152,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-04','09:16:03'),(153,'Registration','Registered: MiniACE2 (Tier: professional, Status: inactive)','jenifferwhite@deltajohnsons.com','superadmin','Super Admin','2026-05-04','09:24:48'),(154,'Registration','Registered: MiniACE23 (Tier: professional, Status: inactive)','gradual9712@deltajohnsons.com','superadmin','Super Admin','2026-05-04','09:29:18'),(155,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-16','22:52:29'),(156,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-16','22:54:43'),(157,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-16','22:58:22'),(158,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-16','22:58:29'),(159,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-16','22:58:49'),(160,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-17','01:26:11'),(161,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-17','01:26:30'),(162,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-17','01:27:45'),(163,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-17','01:27:53'),(164,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-17','01:58:57'),(165,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-17','22:27:49'),(166,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-19','21:21:39'),(167,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-19','21:40:02'),(168,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-19','21:55:21'),(169,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-19','22:04:27'),(170,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-19','22:07:04'),(171,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-19','22:24:22'),(172,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-20','23:59:32'),(173,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-21','01:06:37'),(174,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-21','01:28:03'),(175,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-21','01:32:25'),(176,'Registration','Registered: DeleteStank (Tier: professional, Status: inactive)','7emmalynne@wshu.net','superadmin','Super Admin','2026-05-21','01:33:18'),(177,'Announcement Added','Published: Sample (System Maintenance)',NULL,'superadmin','Super Admin','2026-05-21','02:09:28'),(178,'Announcement Deleted','Deleted announcement ID: 3',NULL,'superadmin','Super Admin','2026-05-21','02:11:29'),(179,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-21','02:50:38'),(180,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-21','13:07:11'),(181,'Tenant Status Change','Tenant status changed to active',NULL,'superadmin','Super Admin','2026-05-21','13:07:35'),(182,'Tenant Status Change','Tenant status changed to inactive',NULL,'superadmin','Super Admin','2026-05-21','13:07:37'),(183,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-21','16:28:49'),(184,'Registration','Registered: Flossy (Tier: professional, Status: inactive)','aqua1104@wshu.net','superadmin','Super Admin','2026-05-21','16:32:25'),(185,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-21','17:10:27'),(186,'Registration','Registered: Riceberry (Tier: professional, Status: inactive)','6850anallese@wshu.net','superadmin','Super Admin','2026-05-21','17:11:54'),(187,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-23','21:03:23'),(188,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-23','23:18:49'),(189,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-23','23:28:42'),(190,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-23','23:29:10'),(191,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-23','23:36:20'),(192,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-23','23:38:42'),(193,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-24','20:14:22'),(194,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-24','20:28:27'),(195,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-24','22:03:11'),(196,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-25','09:16:59'),(197,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-25','22:15:01'),(198,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-25','23:46:33'),(199,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-05-26','00:22:26'),(200,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-28','11:30:14'),(201,'Registration','Registered: Villangca Dental Center (Tier: professional, Status: inactive)','1025madelin@wshu.net','superadmin','Super Admin','2026-05-28','11:53:42'),(202,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-29','10:34:25');
/*!40000 ALTER TABLE `superadmin_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `team_members`
--

DROP TABLE IF EXISTS `team_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `team_members` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `bio` text,
  `image_url` varchar(255) DEFAULT NULL,
  `specialties` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `team_members`
--

LOCK TABLES `team_members` WRITE;
/*!40000 ALTER TABLE `team_members` DISABLE KEYS */;
/*!40000 ALTER TABLE `team_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_activity_logs`
--

DROP TABLE IF EXISTS `tenant_activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_activity_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `activity_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `activity_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `activity_count` int DEFAULT '1',
  `log_date` date NOT NULL,
  `log_time` time DEFAULT NULL,
  PRIMARY KEY (`log_id`),
  KEY `fk_tenant_activity` (`tenant_id`),
  KEY `idx_activity_date` (`log_date`),
  CONSTRAINT `fk_tenant_activity` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=364 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_activity_logs`
--

LOCK TABLES `tenant_activity_logs` WRITE;
/*!40000 ALTER TABLE `tenant_activity_logs` DISABLE KEYS */;
INSERT INTO `tenant_activity_logs` VALUES (1,1,'Login','Admin logged in',1,'2026-04-28','13:00:51'),(2,1,'Logout','Tenant logged out',1,'2026-04-28','13:01:55'),(3,1,'Login','Receptionist logged in',1,'2026-04-28','13:01:59'),(4,1,'Logout','Receptionist logged out',1,'2026-04-28','13:02:01'),(5,1,'Login','Receptionist logged in',1,'2026-04-28','13:03:38'),(6,1,'Logout','Receptionist logged out',1,'2026-04-28','13:10:30'),(7,1,'Login','Admin logged in',1,'2026-04-28','13:10:45'),(8,1,'Logout','Tenant logged out',1,'2026-04-28','13:11:34'),(9,1,'Login','Dentist logged in',1,'2026-04-28','13:11:43'),(10,1,'Schedule','Dentist updated full weekly schedule',1,'2026-04-28','13:11:57'),(11,1,'Login','Admin logged in',1,'2026-04-28','14:34:58'),(12,1,'Logout','Tenant logged out',1,'2026-04-28','14:35:42'),(13,1,'Login','Dentist logged in',1,'2026-04-28','14:35:52'),(14,1,'Logout','Dentist logged out',1,'2026-04-28','14:36:01'),(15,1,'Login','Admin logged in',1,'2026-04-28','14:39:07'),(16,1,'Login','Admin logged in',1,'2026-04-28','14:46:18'),(17,1,'Logout','Tenant logged out',1,'2026-04-28','14:46:22'),(18,1,'Login','Admin logged in',1,'2026-04-28','14:46:33'),(19,1,'Logout','Tenant logged out',1,'2026-04-28','14:49:14'),(20,1,'Login','Admin logged in',1,'2026-04-28','14:54:56'),(21,1,'Logout','Tenant logged out',1,'2026-04-28','14:55:40'),(22,1,'Login','Admin logged in',1,'2026-04-28','14:55:56'),(23,1,'Login','Receptionist logged in',1,'2026-04-28','15:01:35'),(24,2,'Login','Admin logged in',1,'2026-04-30','00:05:37'),(25,2,'Login','Admin logged in',1,'2026-04-30','00:31:51'),(26,1,'Login','Admin logged in',1,'2026-04-30','14:04:53'),(27,1,'Login','Admin logged in',1,'2026-04-30','15:14:40'),(28,1,'Login','Admin logged in',1,'2026-04-30','15:57:28'),(29,1,'Logout','Tenant logged out',1,'2026-04-30','16:07:18'),(30,1,'Login','Receptionist logged in',1,'2026-04-30','16:07:31'),(31,1,'Login','Admin logged in',1,'2026-04-30','16:15:46'),(32,1,'Login','Admin logged in',1,'2026-04-30','16:30:45'),(33,1,'Login','Admin logged in',1,'2026-05-01','06:20:41'),(34,1,'Login','Admin logged in',1,'2026-05-01','06:27:42'),(35,1,'Logout','Tenant logged out',1,'2026-05-01','09:25:12'),(36,1,'Login','Admin logged in',1,'2026-05-01','14:02:41'),(37,1,'Login','Admin logged in',1,'2026-05-01','14:08:36'),(38,1,'Logout','Tenant logged out',1,'2026-05-01','14:13:51'),(39,1,'Login','Admin logged in',1,'2026-05-01','14:14:17'),(40,1,'Logout','Tenant logged out',1,'2026-05-01','14:14:37'),(41,1,'Login','Admin logged in',1,'2026-05-01','14:14:48'),(42,1,'Logout','Tenant logged out',1,'2026-05-01','14:14:59'),(43,1,'Login','Admin logged in',1,'2026-05-01','14:15:21'),(44,1,'Logout','Tenant logged out',1,'2026-05-01','14:16:34'),(45,1,'Login','Admin logged in',1,'2026-05-01','14:19:31'),(46,1,'Logout','Tenant logged out',1,'2026-05-01','14:28:23'),(47,1,'Login','Dentist logged in',1,'2026-05-01','14:28:33'),(48,1,'Logout','Dentist logged out',1,'2026-05-01','14:46:07'),(49,1,'Login','Receptionist logged in',1,'2026-05-01','14:46:16'),(50,1,'Logout','Receptionist logged out',1,'2026-05-01','14:50:11'),(51,1,'Login','Dentist logged in',1,'2026-05-01','14:50:20'),(52,1,'Logout','Dentist logged out',1,'2026-05-01','14:51:15'),(53,1,'Login','Receptionist logged in',1,'2026-05-01','14:51:27'),(54,1,'Logout','Receptionist logged out',1,'2026-05-01','14:53:20'),(55,1,'Login','Admin logged in',1,'2026-05-01','14:53:33'),(56,1,'Logout','Tenant logged out',1,'2026-05-01','15:03:04'),(57,1,'Login','Dentist logged in',1,'2026-05-01','15:03:24'),(58,1,'Login','Receptionist logged in',1,'2026-05-01','15:04:40'),(59,1,'Logout','Receptionist logged out',1,'2026-05-01','15:20:34'),(60,1,'Login','Admin logged in',1,'2026-05-01','15:20:43'),(61,1,'Logout','Tenant logged out',1,'2026-05-01','15:21:42'),(62,1,'Login','Admin logged in',1,'2026-05-01','15:53:37'),(63,1,'Login','Receptionist logged in',1,'2026-05-02','08:36:56'),(64,1,'Login','Receptionist logged in',1,'2026-05-02','08:39:00'),(65,1,'Logout','Receptionist logged out',1,'2026-05-02','08:39:29'),(66,1,'Login','Admin logged in',1,'2026-05-02','08:39:36'),(67,1,'Logout','Tenant logged out',1,'2026-05-02','08:39:48'),(68,1,'Login','Receptionist logged in',1,'2026-05-02','08:39:56'),(69,1,'Logout','Receptionist logged out',1,'2026-05-02','08:50:35'),(70,1,'Login','Admin logged in',1,'2026-05-02','08:51:00'),(71,1,'Logout','Tenant logged out',1,'2026-05-02','08:51:40'),(72,1,'Login','Receptionist logged in',1,'2026-05-02','08:51:53'),(73,1,'Login','Receptionist logged in',1,'2026-05-02','09:17:17'),(74,1,'Login','Receptionist logged in',1,'2026-05-02','09:40:33'),(75,1,'Login','Receptionist logged in',1,'2026-05-02','09:41:45'),(76,1,'Login','Receptionist logged in',1,'2026-05-02','10:12:50'),(77,1,'Login','Receptionist logged in',1,'2026-05-02','10:32:33'),(78,1,'Login','Receptionist logged in',1,'2026-05-02','10:41:18'),(79,1,'Logout','Receptionist logged out',1,'2026-05-02','10:51:23'),(80,1,'Login','Receptionist logged in',1,'2026-05-02','10:58:23'),(81,1,'Logout','Receptionist logged out',1,'2026-05-02','11:00:14'),(82,1,'Login','Receptionist logged in',1,'2026-05-02','11:03:04'),(83,1,'Login','Receptionist logged in',1,'2026-05-02','11:12:28'),(84,1,'Logout','Receptionist logged out',1,'2026-05-02','11:16:57'),(85,1,'Login','Admin logged in',1,'2026-05-02','11:17:10'),(86,1,'Login','Admin logged in',1,'2026-05-02','12:55:14'),(87,1,'Logout','Tenant logged out',1,'2026-05-02','12:57:03'),(88,1,'Login','Admin logged in',1,'2026-05-02','12:57:25'),(89,1,'Logout','Tenant logged out',1,'2026-05-02','12:57:47'),(90,1,'Login','Admin logged in',1,'2026-05-02','12:57:54'),(91,1,'Logout','Tenant logged out',1,'2026-05-02','12:58:41'),(92,1,'Login','Receptionist logged in',1,'2026-05-02','12:58:58'),(93,1,'Logout','Receptionist logged out',1,'2026-05-02','12:59:29'),(94,1,'Login','Admin logged in',1,'2026-05-02','12:59:40'),(95,1,'Logout','Tenant logged out',1,'2026-05-02','13:08:22'),(96,3,'Login','Admin logged in',1,'2026-05-02','13:13:07'),(97,3,'Logout','Tenant logged out',1,'2026-05-02','13:13:18'),(98,1,'Login','Admin logged in',1,'2026-05-02','13:20:27'),(99,1,'Login','Admin logged in',1,'2026-05-02','13:23:43'),(100,1,'Login','Admin logged in',1,'2026-05-02','13:34:20'),(101,1,'Logout','Tenant logged out',1,'2026-05-02','13:35:46'),(102,1,'Login','Admin logged in',1,'2026-05-02','13:36:25'),(103,1,'Login','Admin logged in',1,'2026-05-02','14:06:48'),(104,1,'Login','Admin logged in',1,'2026-05-02','15:27:15'),(105,1,'Logout','Tenant logged out',1,'2026-05-02','16:06:13'),(106,1,'Login','Receptionist logged in',1,'2026-05-02','16:06:22'),(107,1,'Login','Admin logged in',1,'2026-05-02','16:59:52'),(108,1,'Logout','Tenant logged out',1,'2026-05-02','17:07:48'),(109,1,'Login','Dentist logged in',1,'2026-05-02','17:07:56'),(110,1,'Logout','Dentist logged out',1,'2026-05-02','17:08:11'),(111,1,'Login','Receptionist logged in',1,'2026-05-02','17:08:27'),(112,1,'Logout','Receptionist logged out',1,'2026-05-02','17:09:10'),(113,1,'Login','Admin logged in',1,'2026-05-02','17:09:17'),(114,1,'Login','Admin logged in',1,'2026-05-02','17:17:36'),(115,1,'Logout','Tenant logged out',1,'2026-05-02','17:24:18'),(116,1,'Login','Dentist logged in',1,'2026-05-02','17:24:25'),(117,1,'Login','Admin logged in',1,'2026-05-02','23:45:22'),(118,1,'Logout','Tenant logged out',1,'2026-05-02','23:46:47'),(119,1,'Login','Receptionist logged in',1,'2026-05-02','23:46:57'),(120,1,'Login','Admin logged in',1,'2026-05-02','23:47:59'),(121,1,'Login','Admin logged in',1,'2026-05-03','00:07:04'),(122,1,'Login','Admin logged in',1,'2026-05-03','02:17:59'),(123,1,'Login','Admin logged in',1,'2026-05-03','03:24:28'),(124,1,'Logout','Tenant logged out',1,'2026-05-03','03:40:57'),(125,1,'Login','Receptionist logged in',1,'2026-05-03','03:41:16'),(126,1,'Login','Receptionist logged in',1,'2026-05-03','03:42:57'),(127,1,'Logout','Receptionist logged out',1,'2026-05-03','03:46:58'),(128,1,'Login','Dentist logged in',1,'2026-05-03','03:47:18'),(129,1,'Login','Admin logged in',1,'2026-05-03','04:00:19'),(130,1,'Login','Receptionist logged in',1,'2026-05-03','04:21:30'),(131,1,'Logout','Receptionist logged out',1,'2026-05-03','04:38:51'),(132,1,'Login','Receptionist logged in',1,'2026-05-03','04:39:12'),(133,1,'Logout','Receptionist logged out',1,'2026-05-03','04:54:19'),(134,1,'Login','Admin logged in',1,'2026-05-03','04:59:46'),(135,1,'Login','Dentist logged in',1,'2026-05-03','05:00:35'),(136,1,'Logout','Dentist logged out',1,'2026-05-03','05:05:10'),(137,1,'Login','Receptionist logged in',1,'2026-05-03','05:05:30'),(138,1,'Login','Receptionist logged in',1,'2026-05-03','05:19:42'),(139,1,'Login','Receptionist logged in',1,'2026-05-03','06:06:13'),(140,1,'Logout','Receptionist logged out',1,'2026-05-03','06:40:27'),(141,1,'Login','Admin logged in',1,'2026-05-03','06:40:39'),(142,1,'Login','Receptionist logged in',1,'2026-05-03','07:57:55'),(143,1,'Login','Receptionist logged in',1,'2026-05-03','08:01:52'),(144,1,'Login','Receptionist logged in',1,'2026-05-03','08:14:13'),(145,1,'Login','Receptionist logged in',1,'2026-05-03','08:49:42'),(146,1,'Login','Admin logged in',1,'2026-05-03','08:54:48'),(147,1,'Login','Receptionist logged in',1,'2026-05-03','08:54:55'),(148,1,'Logout','Tenant logged out',1,'2026-05-03','08:58:02'),(149,1,'Login','Admin logged in',1,'2026-05-03','08:58:43'),(150,1,'Login','Receptionist logged in',1,'2026-05-03','09:13:05'),(151,1,'Logout','Receptionist logged out',1,'2026-05-03','09:35:38'),(152,1,'Login','Receptionist logged in',1,'2026-05-03','09:35:55'),(153,1,'Login','Admin logged in',1,'2026-05-03','09:45:49'),(154,1,'Logout','Tenant logged out',1,'2026-05-03','09:53:36'),(155,1,'Login','Admin logged in',1,'2026-05-03','09:55:58'),(156,1,'Login','Admin logged in',1,'2026-05-03','10:54:59'),(157,1,'Appointment','Appointment ID: 14 updated to Cancelled',1,'2026-05-03','10:55:09'),(158,1,'Appointment','Appointment ID: 14 updated to Completed',1,'2026-05-03','10:55:14'),(159,7,'Login','Admin logged in',1,'2026-05-03','11:59:57'),(160,1,'Login','Admin logged in',1,'2026-05-03','12:12:24'),(161,1,'Appointment','Appointment ID: 14 updated to Cancelled',1,'2026-05-03','12:12:38'),(162,1,'Appointment','Appointment ID: 14 updated to Completed',1,'2026-05-03','12:12:43'),(163,1,'Login','Admin logged in',1,'2026-05-03','12:22:32'),(164,1,'Login','Admin logged in',1,'2026-05-03','12:53:14'),(165,1,'Login','Admin logged in',1,'2026-05-03','12:56:45'),(166,1,'Logout','Tenant logged out',1,'2026-05-03','13:01:43'),(167,1,'Login','Admin logged in',1,'2026-05-03','13:35:51'),(168,1,'Logout','Tenant logged out',1,'2026-05-03','13:37:13'),(169,1,'Login','Receptionist logged in',1,'2026-05-03','13:37:24'),(170,1,'Login','Admin logged in',1,'2026-05-03','15:08:50'),(171,2,'Login','Admin logged in',1,'2026-05-03','15:10:07'),(172,1,'Logout','Tenant logged out',1,'2026-05-03','15:16:59'),(173,1,'Login','Admin logged in',1,'2026-05-03','15:17:38'),(174,1,'Logout','Tenant logged out',1,'2026-05-03','15:18:38'),(175,1,'Login','Dentist logged in',1,'2026-05-03','15:19:03'),(176,1,'Logout','Dentist logged out',1,'2026-05-03','15:20:38'),(177,1,'Login','Receptionist logged in',1,'2026-05-03','15:21:01'),(178,1,'Logout','Receptionist logged out',1,'2026-05-03','15:32:25'),(179,9,'Login','Admin logged in',1,'2026-05-03','15:38:28'),(180,9,'Logout','Tenant logged out',1,'2026-05-03','15:39:49'),(181,9,'Login','Admin logged in',1,'2026-05-03','15:42:11'),(182,9,'Logout','Tenant logged out',1,'2026-05-03','15:42:56'),(183,6,'Login','Admin logged in',1,'2026-05-03','15:44:29'),(184,6,'Logout','Tenant logged out',1,'2026-05-03','15:44:39'),(185,6,'Login','Admin logged in',1,'2026-05-03','15:45:11'),(186,6,'Logout','Tenant logged out',1,'2026-05-03','15:46:51'),(187,7,'Login','Admin logged in',1,'2026-05-03','15:46:57'),(188,1,'Login','Admin logged in',1,'2026-05-03','15:53:50'),(189,7,'Login','Admin logged in',1,'2026-05-04','00:28:02'),(190,7,'Login','Admin logged in',1,'2026-05-04','00:35:57'),(191,7,'Logout','Tenant logged out',1,'2026-05-04','00:37:26'),(192,7,'Login','Admin logged in',1,'2026-05-04','00:40:07'),(193,1,'Login','Admin logged in',1,'2026-05-04','00:44:43'),(194,1,'Logout','Tenant logged out',1,'2026-05-04','00:57:21'),(195,7,'Login','Admin logged in',1,'2026-05-04','01:13:03'),(196,1,'Login','Receptionist logged in',1,'2026-05-04','01:13:36'),(197,1,'Logout','Receptionist logged out',1,'2026-05-04','01:13:42'),(198,1,'Login','Admin logged in',1,'2026-05-04','01:13:48'),(199,1,'Login','Admin logged in',1,'2026-05-04','01:34:52'),(200,1,'Logout','Tenant logged out',1,'2026-05-04','01:43:37'),(201,1,'Login','Admin logged in',1,'2026-05-04','02:21:31'),(202,1,'Login','Admin logged in',1,'2026-05-04','07:06:44'),(203,1,'Login','Admin logged in',1,'2026-05-04','07:13:21'),(204,1,'Logout','Tenant logged out',1,'2026-05-04','07:27:27'),(205,1,'Login','Admin logged in',1,'2026-05-04','08:05:52'),(206,1,'Login','Admin logged in',1,'2026-05-04','08:18:33'),(207,1,'Logout','Tenant logged out',1,'2026-05-04','08:19:03'),(208,13,'Login','Admin logged in',1,'2026-05-04','09:30:36'),(209,13,'Logout','Tenant logged out',1,'2026-05-04','09:41:54'),(210,13,'Login','Admin logged in',1,'2026-05-04','09:42:17'),(211,1,'Login','Dentist logged in',1,'2026-05-04','09:43:12'),(212,1,'Logout','Dentist logged out',1,'2026-05-04','09:48:39'),(213,1,'Login','Receptionist logged in',1,'2026-05-04','09:48:46'),(214,1,'Logout','Receptionist logged out',1,'2026-05-04','10:31:42'),(215,1,'Login','Admin logged in',1,'2026-05-04','10:31:58'),(216,1,'Logout','Tenant logged out',1,'2026-05-04','10:32:54'),(217,1,'Login','Admin logged in',1,'2026-05-04','10:34:13'),(218,1,'Logout','Tenant logged out',1,'2026-05-04','10:47:12'),(219,2,'Login','Admin logged in',1,'2026-05-04','10:47:29'),(220,1,'Login','Admin logged in',1,'2026-05-17','01:27:19'),(221,1,'Logout','Tenant logged out',1,'2026-05-17','01:27:34'),(222,1,'Login','Admin logged in',1,'2026-05-17','01:29:57'),(223,1,'Logout','Tenant logged out',1,'2026-05-17','01:41:40'),(224,2,'Login','Admin logged in',1,'2026-05-17','01:42:00'),(225,2,'Logout','Tenant logged out',1,'2026-05-17','01:46:39'),(226,1,'Login','Admin logged in',1,'2026-05-17','01:46:55'),(227,1,'Logout','Tenant logged out',1,'2026-05-17','01:49:45'),(228,1,'Login','Dentist logged in',1,'2026-05-17','01:50:02'),(229,1,'Logout','Dentist logged out',1,'2026-05-17','01:52:53'),(230,1,'Login','Receptionist logged in',1,'2026-05-17','01:53:03'),(231,1,'Logout','Receptionist logged out',1,'2026-05-17','01:55:47'),(232,1,'Login','Admin logged in',1,'2026-05-17','01:56:25'),(233,1,'Logout','Tenant logged out',1,'2026-05-17','01:58:51'),(234,1,'Failed Login','Failed login attempt for username: toothfairy',1,'2026-05-21','01:39:18'),(235,1,'Login','Tenant logged in',1,'2026-05-21','01:39:23'),(236,1,'Logout','Tenant logged out',1,'2026-05-21','01:41:46'),(237,1,'Login','Receptionist logged in',1,'2026-05-21','01:41:57'),(238,1,'Logout','Receptionist logged out',1,'2026-05-21','01:43:36'),(239,1,'Failed Login','Failed login attempt for username: toothfairy3',1,'2026-05-21','01:43:44'),(240,1,'Login','Dentist logged in',1,'2026-05-21','01:43:46'),(241,1,'Logout','Dentist logged out',1,'2026-05-21','01:43:55'),(242,1,'Failed Login','Failed login attempt for username: toothfairy3',1,'2026-05-21','01:43:58'),(243,1,'Login','Dentist logged in',1,'2026-05-21','01:44:06'),(244,1,'Logout','Dentist logged out',1,'2026-05-21','01:46:38'),(245,1,'Login','Receptionist logged in',1,'2026-05-21','01:46:48'),(246,1,'Logout','Receptionist logged out',1,'2026-05-21','02:13:47'),(247,1,'Login','Dentist logged in',1,'2026-05-21','02:13:56'),(248,1,'Logout','Dentist logged out',1,'2026-05-21','02:16:39'),(249,1,'Login','Receptionist logged in',1,'2026-05-21','02:17:03'),(250,1,'Logout','Receptionist logged out',1,'2026-05-21','02:20:35'),(251,1,'Login','Dentist logged in',1,'2026-05-21','02:20:48'),(252,1,'Logout','Dentist logged out',1,'2026-05-21','02:21:40'),(253,1,'Login','Tenant logged in',1,'2026-05-21','02:21:54'),(254,1,'Logout','Tenant logged out',1,'2026-05-21','02:39:09'),(255,1,'Login','Dentist logged in',1,'2026-05-21','02:39:20'),(256,1,'Logout','Dentist logged out',1,'2026-05-21','02:40:47'),(257,1,'Login','Tenant logged in',1,'2026-05-21','02:40:57'),(258,1,'Logout','Tenant logged out',1,'2026-05-21','02:49:23'),(259,1,'Login','Receptionist logged in',1,'2026-05-21','02:49:31'),(260,1,'Logout','Receptionist logged out',1,'2026-05-21','02:50:37'),(261,1,'Login','Tenant logged in',1,'2026-05-21','13:42:33'),(262,1,'Logout','Tenant logged out',1,'2026-05-21','13:51:43'),(263,1,'Login','Dentist logged in',1,'2026-05-21','13:51:53'),(264,1,'Logout','Dentist logged out',1,'2026-05-21','14:01:52'),(265,1,'Login','Receptionist logged in',1,'2026-05-21','14:01:59'),(266,1,'Login','Tenant logged in',1,'2026-05-21','16:29:23'),(267,1,'Logout','Tenant logged out',1,'2026-05-21','16:30:28'),(268,1,'Login','Tenant logged in',1,'2026-05-21','16:34:01'),(269,1,'Logout','Tenant logged out',1,'2026-05-21','16:36:38'),(270,1,'Login','Dentist logged in',1,'2026-05-21','16:36:46'),(271,1,'Logout','Dentist logged out',1,'2026-05-21','16:50:04'),(272,1,'Login','Tenant logged in',1,'2026-05-21','16:50:18'),(273,16,'Login','Tenant logged in',1,'2026-05-21','17:12:39'),(274,1,'Login','Tenant logged in',1,'2026-05-21','17:13:37'),(275,1,'Logout','Tenant logged out',1,'2026-05-21','17:15:46'),(276,1,'Login','Dentist logged in',1,'2026-05-21','17:15:53'),(277,1,'Logout','Dentist logged out',1,'2026-05-21','17:16:08'),(278,1,'Login','Dentist logged in',1,'2026-05-21','17:16:25'),(279,1,'Logout','Dentist logged out',1,'2026-05-21','17:16:51'),(280,1,'Login','Receptionist logged in',1,'2026-05-21','17:17:01'),(281,1,'Logout','Receptionist logged out',1,'2026-05-21','17:20:27'),(282,1,'Login','Tenant logged in',1,'2026-05-21','17:20:37'),(283,1,'Logout','Tenant logged out',1,'2026-05-21','17:22:07'),(284,1,'Login','Receptionist logged in',1,'2026-05-21','17:22:18'),(285,1,'Login','Tenant logged in',1,'2026-05-23','21:04:35'),(286,1,'Logout','Tenant logged out',1,'2026-05-23','21:18:06'),(287,1,'Login','Receptionist logged in',1,'2026-05-23','21:18:17'),(288,1,'Login','Dentist logged in',1,'2026-05-23','21:28:07'),(289,1,'Logout','Receptionist logged out',1,'2026-05-23','21:39:12'),(290,1,'Login','Tenant logged in',1,'2026-05-23','21:39:20'),(291,1,'Login','Tenant logged in',1,'2026-05-23','22:04:15'),(292,1,'Login','Receptionist logged in',1,'2026-05-23','22:05:17'),(293,1,'Logout','Tenant logged out',1,'2026-05-23','23:09:48'),(294,1,'Login','Receptionist logged in',1,'2026-05-23','23:40:53'),(295,1,'Logout','Receptionist logged out',1,'2026-05-23','23:48:36'),(296,1,'Login','Tenant logged in',1,'2026-05-23','23:56:06'),(297,1,'Login','Receptionist logged in',1,'2026-05-23','23:56:24'),(298,1,'Logout','Tenant logged out',1,'2026-05-24','00:05:56'),(299,1,'Login','Dentist logged in',1,'2026-05-24','00:06:07'),(300,1,'Login','Tenant logged in',1,'2026-05-24','20:15:30'),(301,1,'Login','Tenant logged in',1,'2026-05-24','20:28:41'),(302,1,'Login','Dentist logged in',1,'2026-05-24','20:30:56'),(303,1,'Logout','Tenant logged out',1,'2026-05-24','20:54:20'),(304,1,'Login','Receptionist logged in',1,'2026-05-24','20:54:28'),(305,1,'Logout','Receptionist logged out',1,'2026-05-24','21:13:29'),(306,1,'Login','Tenant logged in',1,'2026-05-24','21:13:37'),(307,1,'Logout','Dentist logged out',1,'2026-05-24','21:24:19'),(308,1,'Login','Tenant logged in',1,'2026-05-24','21:24:27'),(309,1,'Logout','Tenant logged out',1,'2026-05-24','21:24:32'),(310,1,'Login','Tenant logged in',1,'2026-05-24','22:06:02'),(311,1,'Login','Receptionist logged in',1,'2026-05-24','22:06:29'),(312,1,'Logout','Tenant logged out',1,'2026-05-24','22:13:01'),(313,1,'Login','Dentist logged in',1,'2026-05-24','22:13:09'),(314,1,'Logout','Dentist logged out',1,'2026-05-24','22:13:35'),(315,1,'Login','Receptionist logged in',1,'2026-05-24','22:13:43'),(316,1,'Logout','Receptionist logged out',1,'2026-05-24','22:20:13'),(317,1,'Login','Tenant logged in',1,'2026-05-24','22:20:25'),(318,1,'Login','Tenant logged in',1,'2026-05-25','09:23:21'),(319,1,'Login','Dentist logged in',1,'2026-05-25','09:25:55'),(320,1,'Logout','Dentist logged out',1,'2026-05-25','09:26:26'),(321,1,'Login','Receptionist logged in',1,'2026-05-25','09:26:37'),(322,1,'Logout','Receptionist logged out',1,'2026-05-25','09:27:25'),(323,1,'Login','Dentist logged in',1,'2026-05-25','09:27:34'),(324,1,'Logout','Dentist logged out',1,'2026-05-25','09:27:52'),(325,1,'Login','Receptionist logged in',1,'2026-05-25','09:28:11'),(326,1,'Login','Receptionist logged in',1,'2026-05-25','22:17:32'),(327,1,'Logout','Receptionist logged out',1,'2026-05-25','22:22:04'),(328,1,'Login','Tenant logged in',1,'2026-05-25','22:22:12'),(329,1,'Logout','Tenant logged out',1,'2026-05-25','22:25:38'),(330,1,'Login','Dentist logged in',1,'2026-05-25','22:25:47'),(331,1,'Logout','Dentist logged out',1,'2026-05-25','22:26:17'),(332,1,'Login','Receptionist logged in',1,'2026-05-25','22:26:24'),(333,1,'Login','Tenant logged in',1,'2026-05-25','23:47:46'),(334,1,'Logout','Tenant logged out',1,'2026-05-25','23:49:44'),(335,1,'Login','Dentist logged in',1,'2026-05-25','23:50:03'),(336,1,'Login','Receptionist logged in',1,'2026-05-25','23:50:33'),(337,1,'Logout','Dentist logged out',1,'2026-05-25','23:51:06'),(338,1,'Login','Tenant logged in',1,'2026-05-25','23:51:15'),(339,1,'Logout','Tenant logged out',1,'2026-05-25','23:59:07'),(340,1,'Login','Dentist logged in',1,'2026-05-25','23:59:16'),(341,1,'Logout','Receptionist logged out',1,'2026-05-26','00:22:23'),(342,1,'Logout','Dentist logged out',1,'2026-05-26','00:22:31'),(343,1,'Appointment','Rescheduled Appointment [id:b7a56873cd] (patient_id:11, patient_name:TESTER_, original_date:2026-05-29, original_time:14:30:00, new_date:2026-05-29, new_time:10:30, new_appointment_id:26)',1,'2026-05-27','14:21:12'),(344,1,'Appointment','Cancellation Appointment [id:5f9c4ab08c] (patient_id:11, patient_name:TESTER_, appointment_date:2026-05-29, appointment_time:10:30:00, reason:Patient_cancelled_appointment)',1,'2026-05-27','06:21:35'),(345,1,'Appointment','Rescheduled Appointment [id:535fa30d7e] (patient_id:6, patient_name:KItty_, original_date:2026-05-29, original_time:12:30:00, new_date:2026-05-29, new_time:14:30, new_appointment_id:27)',1,'2026-05-28','11:29:35'),(346,1,'Login','Tenant logged in',1,'2026-05-28','11:35:24'),(347,1,'Logout','Tenant logged out',1,'2026-05-28','11:37:26'),(348,17,'Login','Tenant logged in',1,'2026-05-28','11:56:53'),(349,17,'Logout','Tenant logged out',1,'2026-05-28','11:57:56'),(350,17,'Failed Login','Failed login attempt (bad_credentials)',1,'2026-05-28','11:58:03'),(351,17,'Login','Tenant logged in',1,'2026-05-28','11:58:16'),(352,17,'Created','User Created [id:35135aaa6c]',1,'2026-05-28','12:07:03'),(353,17,'Login','Receptionist logged in',1,'2026-05-28','12:07:58'),(354,17,'Created','Patient Created [id:3fdba35f04] (tenant_patient_id:1, patient_name:Carl_Micko_Tibay)',1,'2026-05-28','12:09:38'),(355,17,'Created','User Created [id:624b60c58c]',1,'2026-05-28','12:18:45'),(356,17,'Logout','Tenant logged out',1,'2026-05-28','12:18:57'),(357,17,'Login','Dentist logged in',1,'2026-05-28','12:19:16'),(358,17,'Schedule','DentistSchedule Schedule (dentist_id:30)',1,'2026-05-28','12:20:55'),(359,17,'Logout','Receptionist logged out',1,'2026-05-28','12:36:33'),(360,17,'Logout','Dentist logged out',1,'2026-05-28','12:36:44'),(361,1,'Login','Receptionist logged in',1,'2026-05-28','12:37:06'),(362,1,'Login','Tenant logged in',1,'2026-05-28','12:37:37'),(363,1,'Login','Tenant logged in',1,'2026-05-29','10:36:16');
/*!40000 ALTER TABLE `tenant_activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_configs`
--

DROP TABLE IF EXISTS `tenant_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_configs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `config_key` varchar(100) NOT NULL,
  `config_value` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_tenant_key` (`tenant_id`,`config_key`)
) ENGINE=InnoDB AUTO_INCREMENT=116 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_configs`
--

LOCK TABLES `tenant_configs` WRITE;
/*!40000 ALTER TABLE `tenant_configs` DISABLE KEYS */;
INSERT INTO `tenant_configs` VALUES (1,1,'brand_logo_path','','2026-04-30 00:30:07','2026-05-16 17:27:27'),(2,1,'brand_bg_color','#001f3f','2026-04-30 00:30:07','2026-04-30 00:30:07'),(3,1,'brand_subtitle','Powered by OralSync','2026-04-30 00:30:07','2026-04-30 00:30:07'),(4,1,'login_title','Clinic Login','2026-04-30 00:30:07','2026-04-30 00:30:07'),(5,1,'primary_btn_color','#22c55e','2026-04-30 00:30:07','2026-05-03 15:17:44'),(6,1,'link_color','#2563eb','2026-04-30 00:30:07','2026-05-03 15:17:44'),(7,1,'brand_bg_image_path','','2026-04-30 00:30:07','2026-05-03 15:17:44'),(8,1,'brand_text_color','#ffffff','2026-04-30 00:30:07','2026-04-30 00:30:07'),(9,1,'booking_deposit_amount','200.00','2026-05-01 09:31:33','2026-05-01 14:44:51'),(10,1,'cancellation_hours','24','2026-05-01 09:31:33','2026-05-01 09:31:33'),(46,1,'card_bg_color','#ffffff','2026-05-02 12:57:01','2026-05-03 13:02:44');
/*!40000 ALTER TABLE `tenant_configs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_configs_old`
--

DROP TABLE IF EXISTS `tenant_configs_old`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_configs_old` (
  `config_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `brand_logo_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `brand_bg_color` varchar(7) COLLATE utf8mb4_general_ci DEFAULT '#001f3f' COMMENT 'Brand card background - Default Navy Blue',
  `brand_subtitle` varchar(255) COLLATE utf8mb4_general_ci DEFAULT 'Powered by OralSync',
  `login_title` varchar(255) COLLATE utf8mb4_general_ci DEFAULT 'Clinic Login',
  `primary_btn_color` varchar(7) COLLATE utf8mb4_general_ci DEFAULT '#22c55e' COMMENT 'Sign In button - Default Green',
  `link_color` varchar(7) COLLATE utf8mb4_general_ci DEFAULT '#2563eb',
  `brand_bg_image_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `brand_text_color` varchar(7) COLLATE utf8mb4_general_ci DEFAULT '#ffffff',
  `login_description` text COLLATE utf8mb4_general_ci,
  `booking_deposit_amount` decimal(10,2) DEFAULT NULL COMMENT 'Flat deposit required at booking. NULL means no deposit required.',
  PRIMARY KEY (`config_id`),
  UNIQUE KEY `unique_tenant_config` (`tenant_id`),
  CONSTRAINT `fk_config_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_configs_old`
--

LOCK TABLES `tenant_configs_old` WRITE;
/*!40000 ALTER TABLE `tenant_configs_old` DISABLE KEYS */;
INSERT INTO `tenant_configs_old` VALUES (1,1,'','#001f3f','Powered by OralSync','Clinic Login','#22c55e','#2563eb','','2026-04-28 14:55:38','2026-04-28 14:56:04','#ffffff',NULL,NULL);
/*!40000 ALTER TABLE `tenant_configs_old` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_documents`
--

DROP TABLE IF EXISTS `tenant_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_documents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `tenant_documents_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_documents`
--

LOCK TABLES `tenant_documents` WRITE;
/*!40000 ALTER TABLE `tenant_documents` DISABLE KEYS */;
INSERT INTO `tenant_documents` VALUES (1,2,'Pearl_White_Dental_Center_Profile.pdf','uploads/tenant_docs/doc_2_69f293114083c.pdf','application/pdf',18264,'2026-04-29 23:24:01'),(2,2,'Pearl_White_Dental_Center_Profile.pdf','uploads/tenant_docs/doc_2_69f2a2e44c767.pdf','application/pdf',18264,'2026-04-30 00:31:32'),(3,3,'Zenith Dental Collective.pdf','uploads/tenant_docs/doc_3_69f5f78ed4523.pdf','application/pdf',47913,'2026-05-02 13:09:34'),(4,6,'431178760_787556913246906_4503796448622141104_n.jpg','uploads/tenant_docs/doc_6_69f73708e1fcc.jpg','image/jpeg',12405,'2026-05-03 11:52:40'),(5,7,'ARTIFICIAL INTELLIGENCE IN AGRICULTURE.jpg','uploads/tenant_docs/doc_7_69f7388260a58.jpg','image/jpeg',293044,'2026-05-03 11:58:58'),(6,8,'ARTIFICIAL INTELLIGENCE IN AGRICULTURE.jpg','uploads/tenant_docs/doc_8_69f7398923796.jpg','image/jpeg',293044,'2026-05-03 12:03:21'),(7,9,'maintenance_report_2026-01-30.pdf','uploads/tenant_docs/doc_9_69f762cdadcfa.pdf','application/pdf',21468,'2026-05-03 14:59:25'),(8,10,'MouthBreeze.pdf','uploads/tenant_docs/doc_10_69f7db62022d7.pdf','application/pdf',73361,'2026-05-03 23:33:54'),(9,10,'Rabid White.pdf','uploads/tenant_docs/doc_10_69f7db620de47.pdf','application/pdf',73870,'2026-05-03 23:33:54'),(10,11,'MouthBreeze.pdf','uploads/tenant_docs/doc_11_69f7e2a69f45d.pdf','application/pdf',73361,'2026-05-04 00:04:54'),(11,11,'Rabid White.pdf','uploads/tenant_docs/doc_11_69f7e2a6acab7.pdf','application/pdf',73870,'2026-05-04 00:04:54'),(12,11,'Zenith Dental Collective.pdf','uploads/tenant_docs/doc_11_69f7e2a6b6c9a.pdf','application/pdf',47913,'2026-05-04 00:04:54'),(13,11,'Pearl_White_Dental_Center_Profile.pdf','uploads/tenant_docs/doc_11_69f7e2a6c415c.pdf','application/pdf',18264,'2026-05-04 00:04:54'),(14,12,'Rabid White.pdf','uploads/tenant_docs/doc_12_69f7f5611120c.pdf','application/pdf',73870,'2026-05-04 01:24:49'),(15,13,'Rabid White.pdf','uploads/tenant_docs/doc_13_69f7f66f39ae4.pdf','application/pdf',73870,'2026-05-04 01:29:19'),(16,14,'oralsync_sales_report_all_2026-05-16.pdf','uploads/tenant_docs/doc_14_6a0df05f4b34c.pdf','application/pdf',174253,'2026-05-20 17:33:19'),(17,14,'Project Evaluation Report.pdf','uploads/tenant_docs/doc_14_6a0df05f5ada4.pdf','application/pdf',74751,'2026-05-20 17:33:19'),(18,14,'Final Presentation Rubric_ Multi-Tenant Web & Mobile System.pdf','uploads/tenant_docs/doc_14_6a0df05f60c6f.pdf','application/pdf',69302,'2026-05-20 17:33:19'),(19,14,'ce42c267-9f57-47f5-9ac1-57eb606e1738.jpg','uploads/tenant_docs/doc_14_6a0df05f66c3c.jpg','image/jpeg',417421,'2026-05-20 17:33:19'),(20,14,'8c52423e-db54-4d00-a22b-68298dc833c2.jpg','uploads/tenant_docs/doc_14_6a0df05f6dd37.jpg','image/jpeg',464054,'2026-05-20 17:33:19'),(21,14,'Project Participation Evaluation Report.pdf','uploads/tenant_docs/doc_14_6a0df05f774a6.pdf','application/pdf',74751,'2026-05-20 17:33:19'),(22,14,'May 11, 2026 Class 1.png','uploads/tenant_docs/doc_14_6a0df05f7dc2c.png','image/png',377444,'2026-05-20 17:33:19'),(23,14,'DOMAIN-1-DATABASE-DESIGN.pdf','uploads/tenant_docs/doc_14_6a0df05f84715.pdf','application/pdf',127892,'2026-05-20 17:33:19'),(24,14,'CAPLOGISTICs_UseCase.drawio.png','uploads/tenant_docs/doc_14_6a0df05f8c276.png','image/png',164292,'2026-05-20 17:33:19'),(25,14,'17fd5150-3f2e-47a1-82b3-7666a5feda68.jpg','uploads/tenant_docs/doc_14_6a0df05f953c4.jpg','image/jpeg',52337,'2026-05-20 17:33:19'),(26,14,'Empowering+Communication+Exploring+ICT\\\'s+Impact+on+Modern+Skills+Development.pdf','uploads/tenant_docs/doc_14_6a0df05f9be63.pdf','application/pdf',395216,'2026-05-20 17:33:19'),(27,14,'khoirulanwar,+Journal+manager,+vol+5+no+1+54-61.pdf','uploads/tenant_docs/doc_14_6a0df05fa2b1d.pdf','application/pdf',325635,'2026-05-20 17:33:19'),(28,14,'Propulsion_Paper3+(1) (1).pdf','uploads/tenant_docs/doc_14_6a0df05faac27.pdf','application/pdf',326707,'2026-05-20 17:33:19'),(29,14,'Propulsion_Paper3+(1).pdf','uploads/tenant_docs/doc_14_6a0df05fb1ec1.pdf','application/pdf',326707,'2026-05-20 17:33:19'),(30,14,'3-EPI+33(6)2024+Article-08+-+Ready+to+print.pdf','uploads/tenant_docs/doc_14_6a0df05fb9ae9.pdf','application/pdf',657265,'2026-05-20 17:33:19'),(31,15,'oralsync_sales_report_all_2026-05-16.pdf','uploads/tenant_docs/doc_15_6a0ec319b4389.pdf','application/pdf',174253,'2026-05-21 08:32:25'),(32,15,'Project Evaluation Report.pdf','uploads/tenant_docs/doc_15_6a0ec319be4c6.pdf','application/pdf',74751,'2026-05-21 08:32:25'),(33,15,'Final Presentation Rubric_ Multi-Tenant Web & Mobile System.pdf','uploads/tenant_docs/doc_15_6a0ec319c85cc.pdf','application/pdf',69302,'2026-05-21 08:32:25'),(34,15,'ce42c267-9f57-47f5-9ac1-57eb606e1738.jpg','uploads/tenant_docs/doc_15_6a0ec319d05e6.jpg','image/jpeg',417421,'2026-05-21 08:32:25'),(35,15,'8c52423e-db54-4d00-a22b-68298dc833c2.jpg','uploads/tenant_docs/doc_15_6a0ec319d9eb8.jpg','image/jpeg',464054,'2026-05-21 08:32:25'),(36,15,'Project Participation Evaluation Report.pdf','uploads/tenant_docs/doc_15_6a0ec319e17a9.pdf','application/pdf',74751,'2026-05-21 08:32:25'),(37,15,'May 11, 2026 Class 1.png','uploads/tenant_docs/doc_15_6a0ec319e74b5.png','image/png',377444,'2026-05-21 08:32:25'),(38,15,'DOMAIN-1-DATABASE-DESIGN.pdf','uploads/tenant_docs/doc_15_6a0ec319ee7d8.pdf','application/pdf',127892,'2026-05-21 08:32:26'),(39,15,'CAPLOGISTICs_UseCase.drawio.png','uploads/tenant_docs/doc_15_6a0ec31a02295.png','image/png',164292,'2026-05-21 08:32:26'),(40,15,'17fd5150-3f2e-47a1-82b3-7666a5feda68.jpg','uploads/tenant_docs/doc_15_6a0ec31a09a3d.jpg','image/jpeg',52337,'2026-05-21 08:32:26'),(41,15,'Empowering+Communication+Exploring+ICT\\\'s+Impact+on+Modern+Skills+Development.pdf','uploads/tenant_docs/doc_15_6a0ec31a10006.pdf','application/pdf',395216,'2026-05-21 08:32:26'),(42,15,'khoirulanwar,+Journal+manager,+vol+5+no+1+54-61.pdf','uploads/tenant_docs/doc_15_6a0ec31a172cc.pdf','application/pdf',325635,'2026-05-21 08:32:26'),(43,15,'Propulsion_Paper3+(1) (1).pdf','uploads/tenant_docs/doc_15_6a0ec31a1f4b3.pdf','application/pdf',326707,'2026-05-21 08:32:26'),(44,15,'Propulsion_Paper3+(1).pdf','uploads/tenant_docs/doc_15_6a0ec31a2809f.pdf','application/pdf',326707,'2026-05-21 08:32:26'),(45,15,'3-EPI+33(6)2024+Article-08+-+Ready+to+print.pdf','uploads/tenant_docs/doc_15_6a0ec31a2f4e9.pdf','application/pdf',657265,'2026-05-21 08:32:26'),(46,15,'CAPLoLogo2.png','uploads/tenant_docs/doc_15_6a0ec31a38dd8.png','image/png',37228,'2026-05-21 08:32:26'),(47,15,'building.png','uploads/tenant_docs/doc_15_6a0ec31a406c6.png','image/png',2079555,'2026-05-21 08:32:26'),(48,15,'CAPLoLogo.png','uploads/tenant_docs/doc_15_6a0ec31a4b1b3.png','image/png',39766,'2026-05-21 08:32:26'),(49,15,'aa2.pdf','uploads/tenant_docs/doc_15_6a0ec31a58a9a.pdf','application/pdf',59850,'2026-05-21 08:32:26'),(50,16,'DOMAIN-1-DATABASE-DESIGN.pdf','uploads/tenant_docs/doc_16_6a0ecc5b678ee.pdf','application/pdf',127892,'2026-05-21 09:11:55'),(51,17,'Pearl_White_Dental_Center_Profile.pdf','uploads/tenant_docs/doc_17_6a17bc47aed49.pdf','application/pdf',18264,'2026-05-28 03:53:43');
/*!40000 ALTER TABLE `tenant_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_subscription_revenue`
--

DROP TABLE IF EXISTS `tenant_subscription_revenue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_subscription_revenue` (
  `revenue_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `subscription_tier` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'startup',
  `amount` decimal(10,2) NOT NULL,
  `billing_period_start` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `billing_period_end` timestamp NULL DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'paid',
  `payment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`revenue_id`),
  KEY `fk_revenue_tenant` (`tenant_id`),
  KEY `idx_revenue_date` (`payment_date`),
  CONSTRAINT `fk_revenue_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_subscription_revenue`
--

LOCK TABLES `tenant_subscription_revenue` WRITE;
/*!40000 ALTER TABLE `tenant_subscription_revenue` DISABLE KEYS */;
INSERT INTO `tenant_subscription_revenue` VALUES (1,1,'professional',249.00,'2026-04-29 00:00:00','2027-04-28 23:59:59','paid','2026-04-28 12:59:43','2026-04-28 12:59:43'),(2,2,'startup',124.00,'2026-04-30 00:00:00','2027-04-29 23:59:59','paid','2026-04-29 23:24:01','2026-04-29 23:24:01');
/*!40000 ALTER TABLE `tenant_subscription_revenue` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenants`
--

DROP TABLE IF EXISTS `tenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenants` (
  `tenant_id` int NOT NULL AUTO_INCREMENT,
  `company_name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `owner_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contact_email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `password_reset_token` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `city` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `barangay` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `zip_code` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `province` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `subdomain_slug` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `homepage_url` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tenant_code` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('active','inactive','suspended') COLLATE utf8mb4_general_ci DEFAULT 'active',
  `subscription_tier` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'startup',
  `subscription_start_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `subscription_duration` int DEFAULT '12' COMMENT 'Duration in months',
  `trial_start_date` timestamp NULL DEFAULT NULL,
  `trial_end_date` timestamp NULL DEFAULT NULL,
  `must_change_password` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tenant_id`),
  UNIQUE KEY `subdomain_slug` (`subdomain_slug`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `idx_tenant_code` (`tenant_code`),
  KEY `idx_trial_end_date` (`trial_end_date`),
  KEY `idx_tenant_reset_token` (`password_reset_token`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenants`
--

LOCK TABLES `tenants` WRITE;
/*!40000 ALTER TABLE `tenants` DISABLE KEYS */;
INSERT INTO `tenants` VALUES (1,'ToothFairy','Carl Micko T. Tibay','sound762@deltajohnsons.com','$2y$12$wIGzlzWMFJRKfgQlOnYJJub9nY3ig2mbulCXuuqBSfZQv08yqAfNK','$2y$12$9wtzexJse/wFkI51hK5i6eqbSAP6lBKChpOiqbhani1zrzRenobHO','2026-05-03 03:19:50','09477230297','Mabalas-balas','San Rafael',NULL,NULL,'Bulacan','toothfairy-73d1','Landing Page/tenant_homepage.php?tenant=toothfairy-73d1','A2GNMEVT','toothfairy','active','professional','2026-04-29 00:00:00',12,NULL,NULL,1,'2026-04-28 12:59:43'),(2,'Pearl White Dental Center','Dr. Elena Rodriguez, DMD','glorisalmon@deltajohnsons.com','$2y$12$IEZOkqdwrtVFKUi.PPOaA.j1WS9hMP/TPP6mD0WVjyZLBgUVAVRkm',NULL,NULL,'09325234295','Level 3, Sky Tower','Makati',NULL,NULL,'Metro Manila','pearl-white-dental-center-6fac','Landing Page/tenant_homepage.php?tenant=pearl-white-dental-center-6fac','PHQHMBPJ','pearlwhite','active','startup','2026-04-30 00:00:00',12,NULL,NULL,1,'2026-04-29 23:24:01'),(3,'Zenith Dental Collective','Gabriel Toledo','timothea586@deltajohnsons.com','$2y$12$8HZUQDJp/U5pC2.CZLjl9.dMn82.nwFaLgxPbCSKJuNUPbBNWzv/u',NULL,NULL,'0932 412 8888','Quirino Highway','Quezon City',NULL,NULL,'Metro Manila','zenith-dental-collective-00de','Landing Page/tenant_homepage.php?tenant=zenith-dental-collective-00de','4JB94JU4','zdc21','active','professional','2026-05-03 00:00:00',12,NULL,NULL,1,'2026-05-02 13:09:34'),(4,'ABD','Amiel','amielcarlsantos26@gmail.com','$2y$12$H2kqj9ytqLeLabgBqmeWwOFq6DRy7OJnmQQZfY.UCO2Cc0oFNBD1W',NULL,NULL,'09959079137','#69 E Delos Angeles','Malolos',NULL,NULL,'Bulacan','abd-db52','Landing Page/tenant_homepage.php?tenant=abd-db52','2W8G7D6A','ABD','inactive','startup','2026-05-03 00:00:00',12,NULL,NULL,1,'2026-05-03 09:20:48'),(5,'Santos','Amiel Carl','amielcarlsantos.basc@gmail.com','$2y$12$zZOBtkUirGinDp6Si0jHMuDarI9yl6UH9oYOfTkvnqV/6rimZuB46',NULL,NULL,'09959079137','#69 E Delos Angeles','Malolos',NULL,NULL,'Bulacan','santos-fe29','Landing Page/tenant_homepage.php?tenant=santos-fe29','4NXM6JQN','Santos','active','startup','2026-05-03 00:00:00',12,NULL,NULL,1,'2026-05-03 09:46:47'),(6,'bmw','Saint Mark','hibabif987@justnapa.com','$2y$12$cAY7Sbyl9QYSSYdl6dnppOIk1/AwYmAv59tQt4BCkey7gkXt5cyLK',NULL,NULL,'09959079137','#69 E Delos Angeles','Malolos',NULL,NULL,'Bulacan','bmw-9c1f','Landing Page/tenant_homepage.php?tenant=bmw-9c1f','RXFQPKAV','bmw','active','startup','2026-05-03 00:00:00',12,NULL,NULL,1,'2026-05-03 11:52:40'),(7,'toyota','Saint','wecolov551@justnapa.com','$2y$12$xLs1hFmtRsSkhshPHQuJIOosw/L0NEXSzvxanrfPf2aOTRr2uAXre',NULL,NULL,'09959079137','#69 E Delos Angeles','Malolos',NULL,NULL,'Bulacan','toyota-1bd0','Landing Page/tenant_homepage.php?tenant=toyota-1bd0','TK6MBDHE','toyota','active','trial','2026-05-03 00:00:00',12,NULL,NULL,1,'2026-05-03 11:58:58'),(8,'ror','Amats','jimek10787@inreur.com','$2y$12$Aqfn4p5ZZL6wi2m5VukYYOhQa/NXmkPA/bbJT8qfviZ2lMoqU1nb6',NULL,NULL,'09959079137','#69 E Delos Angeles','Malolos',NULL,NULL,'Bulacan','ror-44a3','Landing Page/tenant_homepage.php?tenant=ror-44a3','JV7AK9UX','ror','active','professional','2026-05-03 00:00:00',12,NULL,NULL,1,'2026-05-03 12:03:20'),(9,'MiniACE','Rose Viminda','small2090@deltajohnsons.com','$2y$12$Zlizi5IounBVsR4oih/qGOObbps0xptmBC0dE6EPUNHVTY/2F0i46',NULL,NULL,'09375234195','Sto. Cristo','Baliuag',NULL,NULL,'Bulacan','miniace-3fd9','Landing Page/tenant_homepage.php?tenant=miniace-3fd9','RRTQQZE7','MiniACE','active','professional','2026-05-04 00:00:00',12,NULL,NULL,1,'2026-05-03 14:59:25'),(10,'Rabid White','Dr. Pho Khut, DMD','grosskalli@deltajohnsons.com','$2y$12$NVcH7akXniJ5KYh7q5uh4eOMsEu3us0jmbNcpjnrbavQvFp9mNzTy',NULL,NULL,'0962 452 8485','Sampaloc','San Rafael',NULL,NULL,'Bulacan','rabid-white-0ed2','Landing Page/tenant_homepage.php?tenant=rabid-white-0ed2','4P5LPYPL','rhite','inactive','professional','2026-05-04 16:00:00',12,NULL,NULL,1,'2026-05-03 23:33:53'),(11,'ShinyWhite','Gold Javier','bronze4887@deltajohnsons.com','$2y$12$xbyS7ZyrBNZ6oV0YMfgeROrPt0CdHiE9ugLzce3LIqrCw/gn4IvXi',NULL,NULL,'09473334295','Maguinao','San Rafael',NULL,NULL,'Bulacan','shinywhite-25d4','Landing Page/tenant_homepage.php?tenant=shinywhite-25d4','KJLDPCLF','shinywh','inactive','startup','2026-05-04 16:00:00',12,NULL,NULL,1,'2026-05-04 00:04:54'),(12,'MiniACE2','Samuel Jackson','jenifferwhite@deltajohnsons.com','$2y$12$kEcZy/M0Hn3R4gN0DgUoT.cecajVBeIL2qEOiBQz5HeiDo.3biQwW',NULL,NULL,'09477230297','Glorietta','Baliuag',NULL,NULL,'Bulacan','miniace2-844c','Landing Page/tenant_homepage.php?tenant=miniace2-844c','JXUUPKTB','miniace2','inactive','professional','2026-05-04 16:00:00',12,NULL,NULL,1,'2026-05-04 01:24:48'),(13,'MiniACE23','Sameul Jackson','gradual9712@deltajohnsons.com','$2y$12$HJvIuLMxxnFzTuaorVQ55../sUYs2HmdYpZuwFH0ocCbzmPW.dn5q',NULL,NULL,'09477230297','Mabalas-balas','San Rafael',NULL,NULL,'Bulacan','miniace23-0792','Landing Page/tenant_homepage.php?tenant=miniace23-0792','F6DSB2X5','miniace22','active','professional','2026-05-04 16:00:00',12,NULL,NULL,1,'2026-05-04 01:29:18'),(14,'DeleteStank','Robert Capp','7emmalynne@wshu.net','$2y$12$CKEJJrIc5XU470Tp3i3ahe66z9/Spazq7NAlrPEYA0lTWTDUNkPni',NULL,NULL,'09552719898','Dona Telo','Baliuag','San Roque','3006','Bulacan','deletestank-c9a8','Landing Page/tenant_homepage.php?tenant=deletestank-c9a8','TG9C7Y8R','dstank','inactive','professional','2026-05-20 16:00:00',12,NULL,NULL,1,'2026-05-20 17:33:18'),(15,'Flossy','John Wick','aqua1104@wshu.net','$2y$12$oTDAoSmWMx2OxcWUf3vo3OCx8jKuk46dlXAn.CSdF2al5qQDQi4bO',NULL,NULL,'09172189289','Italian Garden','Pandi','Bunsuran I','3014','Bulacan','flossy-4148','Landing Page/tenant_homepage.php?tenant=flossy-4148','37FDAJ7D','flossyy','inactive','professional','2026-05-20 16:00:00',12,NULL,NULL,1,'2026-05-21 08:32:25'),(16,'Riceberry','Robb Velmort','6850anallese@wshu.net','$2y$12$Bx/0royNPxQ.YRWQXSj3cejgvvHfNcsHX7RW3xCZ/x6HowcV2qnRW',NULL,NULL,'09459828817','Dyan Lang Ho','Baliuag','Sabang','3006','Bulacan','riceberry-c683','Landing Page/tenant_homepage.php?tenant=riceberry-c683','9CSY7ALL','rberry','active','professional','2026-05-20 16:00:00',12,NULL,NULL,1,'2026-05-21 09:11:54'),(17,'Villangca Dental Center','Dr. Bienvenido S. Villangca','1025madelin@wshu.net','$2y$12$6O6CEi.G3kwG1K1OlO3f.O/AmR/72R1O247fpgvCcuBri6rUctK.i',NULL,NULL,'0948 828 9219','1C ACEA Square Bldg. DRT Highway','San Rafael','Ulingao','3008','Bulacan','villangca-dental-center-dcc4','Landing Page/tenant_homepage.php?tenant=villangca-dental-center-dcc4','F9T4KNSB','docbien21','active','professional','2026-05-27 16:00:00',12,NULL,NULL,1,'2026-05-28 03:53:42');
/*!40000 ALTER TABLE `tenants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('Admin','Receptionist','Dentist') COLLATE utf8mb4_general_ci NOT NULL,
  `first_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `password_reset_token` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `unique_user_per_tenant` (`username`,`tenant_id`),
  KEY `fk_users_tenant` (`tenant_id`),
  CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,1,'toothfairy2','54belita@deltajohnsons.com','$2y$12$wUEuagbtX73Z.f8CNpoueOyOD3BU6LjY0Xret4Ow2JeXkDFQ/dh3q','Receptionist','Lord','Farquad',NULL,'2026-04-28 13:01:53',NULL,NULL,NULL),(2,1,'toothfairy3','toothfairy@sample.com','$2y$12$E2emMTbQ02Coc9OXBPhC8u81ZYfdb5bLUyX5dLNfxoBoxkFCgXf4G','Dentist','Michael','Gordon',NULL,'2026-04-28 13:11:32',NULL,NULL,NULL),(3,1,'tfadmin2','7missie@deltajohnsons.com','$2y$12$N69d1jezAIkRr3Jx7fWJquCflHPDN130eG4Lv6i16Wz6V.nKP1TTe','Admin','George','Harrison',NULL,'2026-04-28 14:49:10',NULL,NULL,NULL),(4,1,'tfadmin3','partialardith@deltajohnsons.com','$2y$12$WTrxAwr7hr0pO9n/JomQ3uXGGdQ9u7/nKK4ELeMNqKcj/TdlItdVe','Admin','Ray','Cenat',NULL,'2026-05-01 15:21:40',NULL,NULL,NULL),(5,1,'fanechkaolive@deltajohnsons.com','fanechkaolive@deltajohnsons.com','$2y$12$KS67YJ1LKmwLQssbUNuTh.a6lgyjtR5P08xNxxlK0pSsQ.QEqP2HS','Admin','Michael','Jackson','09457231234','2026-05-03 08:57:42',NULL,NULL,NULL),(6,9,'radbud321231@gmail.com','radbud321231@gmail.com','$2y$12$gRg8XRgLt55gIiHL/cf2/.ZLg.39bTTk4iw3FRiWNy01H8X4u.Kza','Dentist','John','Silvestre','09477230297','2026-05-03 15:39:24',NULL,NULL,NULL),(7,9,'dr_smith','j.smith@dental.test','hashed_pass_1','Dentist','James','Smith','555-0101','2026-05-03 15:41:43','2026-05-03 15:41:43',NULL,NULL),(8,9,'dr_garcia','m.garcia@dental.test','hashed_pass_2','Dentist','Maria','Garcia','555-0102','2026-05-03 15:41:43','2026-05-03 15:41:43',NULL,NULL),(9,9,'dr_chen','l.chen@dental.test','hashed_pass_3','Dentist','Linda','Chen','555-0103','2026-05-03 15:41:43','2026-05-03 15:41:43',NULL,NULL),(10,9,'dr_patel','r.patel@dental.test','hashed_pass_4','Dentist','Raj','Patel','555-0104','2026-05-03 15:41:43','2026-05-03 15:41:43',NULL,NULL),(11,9,'dr_adams','s.adams@dental.test','hashed_pass_5','Dentist','Samuel','Adams','555-0105','2026-05-03 15:41:43','2026-05-03 15:41:43',NULL,NULL),(12,9,'dr_kim','h.kim@dental.test','hashed_pass_6','Dentist','Hana','Kim','555-0106','2026-05-03 15:41:43','2026-05-03 15:41:43',NULL,NULL),(13,9,'dr_taylor','b.taylor@dental.test','hashed_pass_7','Dentist','Blake','Taylor','555-0107','2026-05-03 15:41:43','2026-05-03 15:41:43',NULL,NULL),(14,9,'dr_white','e.white@dental.test','hashed_pass_8','Dentist','Elena','White','555-0108','2026-05-03 15:41:43','2026-05-03 15:41:43',NULL,NULL),(15,9,'dr_rossi','g.rossi@dental.test','hashed_pass_9','Dentist','Giovanni','Rossi','555-0109','2026-05-03 15:41:43','2026-05-03 15:41:43',NULL,NULL),(16,9,'front_sarah','sarah.m@dental.test','hashed_pass_10','Receptionist','Sarah','Miller','555-0201','2026-05-03 15:41:43','2026-05-03 15:41:43',NULL,NULL),(17,9,'front_kevin','k.jones@dental.test','hashed_pass_11','Receptionist','Kevin','Jones','555-0202','2026-05-03 15:41:43','2026-05-03 15:41:43',NULL,NULL),(18,9,'front_anita','a.desai@dental.test','hashed_pass_12','Receptionist','Anita','Desai','555-0203','2026-05-03 15:41:43','2026-05-03 15:41:43',NULL,NULL),(19,9,'front_lucas','l.varga@dental.test','hashed_pass_13','Receptionist','Lucas','Varga','555-0204','2026-05-03 15:41:43','2026-05-03 15:41:43',NULL,NULL),(20,9,'front_zoey','z.hall@dental.test','hashed_pass_14','Receptionist','Zoey','Hall','555-0205','2026-05-03 15:41:43','2026-05-03 15:41:43',NULL,NULL),(21,6,'dr_wilson','e.wilson@clinic6.test','password_hash_1','Dentist','Elizabeth','Wilson','555-6001','2026-05-03 15:44:58','2026-05-03 15:44:58',NULL,NULL),(22,6,'dr_ahmed','o.ahmed@clinic6.test','password_hash_2','Dentist','Omar','Ahmed','555-6002','2026-05-03 15:44:58','2026-05-03 15:44:58',NULL,NULL),(23,6,'dr_lee','j.lee@clinic6.test','password_hash_3','Dentist','Jenny','Lee','555-6003','2026-05-03 15:44:58','2026-05-03 15:44:58',NULL,NULL),(24,6,'dr_murphy','p.murphy@clinic6.test','password_hash_4','Dentist','Patrick','Murphy','555-6004','2026-05-03 15:44:58','2026-05-03 15:44:58',NULL,NULL),(25,6,'dr_santos','i.santos@clinic6.test','password_hash_5','Dentist','Isabella','Santos','555-6005','2026-05-03 15:44:58','2026-05-03 15:44:58',NULL,NULL),(26,6,'recept_mark','m.thompson@clinic6.test','password_hash_6','Receptionist','Mark','Thompson','555-6101','2026-05-03 15:44:58','2026-05-03 15:44:58',NULL,NULL),(27,6,'recept_claire','c.daniels@clinic6.test','password_hash_7','Receptionist','Claire','Daniels','555-6102','2026-05-03 15:44:58','2026-05-03 15:44:58',NULL,NULL),(28,13,'kind427@deltajohnsons.com','kind427@deltajohnsons.com','$2y$12$.rExhumzASDLjDmuZ35Zz..emvJZGbbdKuonBp2dRXPGtWKtysbsi','Admin','Miguel','Antonio','09477230297','2026-05-04 01:41:28',NULL,NULL,NULL),(29,17,'darkagedbat@gmail.com','darkagedbat@gmail.com','$2y$12$lr3UiBa4kYN8g/zdLKP/s.Vl82w6BjSJNe6RkvO3aaUZndubG0xd6','Receptionist','Carl Micko','Tibay','09477230297','2026-05-28 04:07:00',NULL,NULL,NULL),(30,17,'historicalcoral@wshu.net','historicalcoral@wshu.net','$2y$12$5AHZlX8NeuAcoG4pgB1nq.qE/3FOWoHDkMgezpWddUiJCJchdLDuK','Dentist','Jethro','Silva','09477230297','2026-05-28 04:18:41',NULL,NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-01 21:09:23
