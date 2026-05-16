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
  `tenant_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `category` enum('Clinical Update','Patient Care','Facility News','Staff Training') DEFAULT 'Clinical Update',
  `image_path` varchar(511) DEFAULT NULL,
  `status` enum('active','archived') DEFAULT 'active',
  `publish_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_tenant_announcement` (`tenant_id`),
  CONSTRAINT `fk_tenant_announcement` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
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
  `status` enum('pending','pending_payment','completed','cancelled','approved','disapproved') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `procedure_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_appointment_request` tinyint(1) DEFAULT '1',
  `requested_by` enum('patient','receptionist','dentist') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'patient',
  PRIMARY KEY (`appointment_id`),
  KEY `fk_appt_tenant` (`tenant_id`),
  KEY `fk_appt_service` (`service_id`),
  CONSTRAINT `fk_appt_service` FOREIGN KEY (`service_id`) REFERENCES `service` (`service_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_appt_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointment`
--

LOCK TABLES `appointment` WRITE;
/*!40000 ALTER TABLE `appointment` DISABLE KEYS */;
INSERT INTO `appointment` VALUES (1,1,2,2,'2026-05-04','09:00:00',NULL,NULL,'pending',NULL,1,'patient');
/*!40000 ALTER TABLE `appointment` ENABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `billing`
--

LOCK TABLES `billing` WRITE;
/*!40000 ALTER TABLE `billing` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clinic_schedules`
--

LOCK TABLES `clinic_schedules` WRITE;
/*!40000 ALTER TABLE `clinic_schedules` DISABLE KEYS */;
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
  `hero_title` varchar(255) DEFAULT NULL,
  `hero_description` text,
  `about_title` varchar(255) DEFAULT NULL,
  `about_description` text,
  `contact_address` text,
  `contact_phone` varchar(50) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clinic_settings`
--

LOCK TABLES `clinic_settings` WRITE;
/*!40000 ALTER TABLE `clinic_settings` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clinical_notes`
--

LOCK TABLES `clinical_notes` WRITE;
/*!40000 ALTER TABLE `clinical_notes` DISABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dentist`
--

LOCK TABLES `dentist` WRITE;
/*!40000 ALTER TABLE `dentist` DISABLE KEYS */;
INSERT INTO `dentist` VALUES (2,1,'Michael','Gordon','toothfairy3','toothfairy@sample.com','$2y$12$E2emMTbQ02Coc9OXBPhC8u81ZYfdb5bLUyX5dLNfxoBoxkFCgXf4G');
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dentist_schedule`
--

LOCK TABLES `dentist_schedule` WRITE;
/*!40000 ALTER TABLE `dentist_schedule` DISABLE KEYS */;
INSERT INTO `dentist_schedule` VALUES (1,2,1,'Monday','09:00:00','17:00:00',1),(2,2,1,'Tuesday','09:00:00','17:00:00',0),(3,2,1,'Wednesday','09:00:00','17:00:00',1),(4,2,1,'Thursday','09:00:00','17:00:00',0),(5,2,1,'Friday','09:00:00','17:00:00',1),(6,2,1,'Saturday','09:00:00','17:00:00',0),(7,2,1,'Sunday','09:00:00','17:00:00',0);
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
  PRIMARY KEY (`patient_id`),
  UNIQUE KEY `uq_tenant_patient` (`tenant_id`,`tenant_patient_id`),
  UNIQUE KEY `username` (`username`),
  KEY `fk_patient_tenant` (`tenant_id`),
  KEY `idx_tenant_patient_id` (`tenant_id`,`tenant_patient_id`),
  KEY `idx_patient_reset_token` (`password_reset_token`),
  CONSTRAINT `fk_patient_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patient`
--

LOCK TABLES `patient` WRITE;
/*!40000 ALTER TABLE `patient` DISABLE KEYS */;
INSERT INTO `patient` VALUES (1,1,'Jethro','Silva','09577299828','tfpatient@sample.com','$2y$12$BGNL8v.gavmwJJHcCpR93u2vreWhhZD0OqUKGv/XI8V9UsF8SGB6y','tfpatient','Somewhere, San Miguel, Bulacan','2005-03-09','Male',NULL,NULL,NULL,NULL,1,NULL,NULL,0,NULL,'none'),(2,1,'Pipeng','Dilat','09123456789','tabepak769@inraud.com','$2y$12$GiVIeELo/D1rhRAF4j7SAurWYFlGCJ1aTVn/wU9S44bo32i5lhVRe','openeyes','',NULL,'','','','',NULL,2,'eaa8b6be4d99d5616d2cb1ccc4993a8573ff8eaf713dba69d750cde1d3a1e988','2026-04-30 13:33:44',0,NULL,'none');
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
  `appointment_id` int DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment`
--

LOCK TABLES `payment` WRITE;
/*!40000 ALTER TABLE `payment` DISABLE KEYS */;
INSERT INTO `payment` VALUES (1,1,NULL,100.00,'online','pending',NULL,'web',NULL,'2026-04-28 15:06:08','full','cs_575f581b0a0e9dc8575552a0',NULL),(2,1,NULL,100.00,'online','pending',NULL,'web',NULL,'2026-04-28 15:08:51','full','cs_c17cb71f2fe4db42d3a2a9f7',NULL),(3,1,NULL,100.00,'online','pending',NULL,'web',NULL,'2026-04-28 15:33:14','full','cs_c45aed70f9c5cf2663227694',NULL),(4,1,NULL,23123.00,'Online','pending','{\"plan_name\":\"Tenant Subscription Update\",\"description\":\"Manual subscription adjustment\"}','web',NULL,'2026-04-29 12:39:37','full','cs_49f929e79c577f28d669fe29',NULL),(5,1,NULL,23123.00,'Online','pending','{\"plan_name\":\"Tenant Subscription Update\",\"description\":\"Manual subscription adjustment\"}','web',NULL,'2026-04-29 12:39:44','full','cs_84cc96792c08a470dbf49a05',NULL),(6,1,NULL,12121.00,'Online','pending','{\"plan_name\":\"Tenant Subscription Update\",\"description\":\"Manual subscription adjustment\"}','web',NULL,'2026-04-29 12:44:14','full','cs_d191452bf8a7d05a8e35fa91',NULL),(7,1,NULL,12121.00,'Online','pending','{\"plan_name\":\"Tenant Subscription Update\",\"description\":\"Manual subscription adjustment\"}','web',NULL,'2026-04-29 12:44:15','full','cs_f4e5cc92d444facfd5f7c1be',NULL),(8,1,NULL,12121.00,'Online','pending','{\"plan_name\":\"Tenant Subscription Update\",\"description\":\"Manual subscription adjustment\"}','web',NULL,'2026-04-29 12:45:18','full','cs_f4017b64a35d0fe5305882ad',NULL),(9,1,NULL,121.00,'Online','pending','{\"plan_name\":\"Tenant Subscription Update\",\"description\":\"Manual subscription adjustment\"}','web',NULL,'2026-04-29 13:37:05','full','cs_bb7a4bf344f9b740653c12c4',NULL),(10,1,NULL,3232.00,'Online','pending','{\"plan_name\":\"Tenant Subscription Update\",\"description\":\"Manual subscription adjustment\"}','web',NULL,'2026-04-29 13:37:52','full','cs_4b80ab2f02027d59231382d1',NULL),(11,1,NULL,1111.00,'Online','pending','{\"plan_name\":\"Tenant Subscription Update\",\"description\":\"Manual subscription adjustment\"}','web',NULL,'2026-04-29 13:45:47','full','cs_045e7da901216530ce051580',NULL),(12,1,NULL,1500.00,'Online','pending','{\"item\":\"Platform Subscription Renewal\"}','web',NULL,'2026-04-30 14:31:57','full','cs_6f2b9e81c5f0aef3664259e6',NULL),(13,1,NULL,1500.00,'Online','pending','{\"item\":\"Platform Subscription Renewal\"}','web',NULL,'2026-04-30 14:32:11','full','cs_2872ef2a484719541615e02d',NULL),(14,1,NULL,1500.00,'Online','pending','{\"item\":\"Platform Subscription Renewal\"}','web',NULL,'2026-04-30 14:33:56','full','cs_a25735a461cc0c4a767b51c4',NULL);
/*!40000 ALTER TABLE `payment` ENABLE KEYS */;
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
  `description` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_general_ci DEFAULT 'General',
  PRIMARY KEY (`service_id`),
  KEY `fk_service_tenant` (`tenant_id`),
  CONSTRAINT `fk_service_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service`
--

LOCK TABLES `service` WRITE;
/*!40000 ALTER TABLE `service` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_details`
--

LOCK TABLES `staff_details` WRITE;
/*!40000 ALTER TABLE `staff_details` DISABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `super_admins`
--

LOCK TABLES `super_admins` WRITE;
/*!40000 ALTER TABLE `super_admins` DISABLE KEYS */;
INSERT INTO `super_admins` VALUES (1,'admin','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',NULL,NULL,'2026-05-01 05:08:35','2026-04-28 12:04:27','darkagedbat@gmail.com');
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
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `superadmin_logs`
--

LOCK TABLES `superadmin_logs` WRITE;
/*!40000 ALTER TABLE `superadmin_logs` DISABLE KEYS */;
INSERT INTO `superadmin_logs` VALUES (1,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-28','12:11:50'),(2,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-28','12:12:13'),(3,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-28','12:38:14'),(4,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-28','12:52:22'),(5,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-28','12:58:40'),(6,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-28','12:58:51'),(7,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-28','12:59:08'),(8,'Registration','Registered: ToothFairy (Tier: professional)','sound762@deltajohnsons.com','superadmin','Super Admin','2026-04-28','12:59:43'),(9,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-28','13:00:31'),(10,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-28','14:45:59'),(11,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-28','14:56:42'),(12,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-29','05:24:23'),(13,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-29','05:56:32'),(14,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-29','12:42:34'),(15,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-29','13:55:11'),(16,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-29','14:12:41'),(17,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-29','16:37:09'),(18,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-29','16:42:42'),(19,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-29','23:21:54'),(20,'Registration','Registered: Pearl White Dental Center (Tier: startup)','glorisalmon@deltajohnsons.com','superadmin','Super Admin','2026-04-29','23:24:01'),(21,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-29','23:46:02'),(22,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-30','00:03:15'),(23,'Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-30','00:27:25'),(24,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-30','00:31:11'),(25,'Upload','Uploaded 1 document(s) for: Pearl White Dental Center','System','superadmin','Super Admin','2026-04-30','00:31:32'),(26,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-30','14:03:47'),(27,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-30','15:56:35'),(28,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-30','16:32:06'),(29,'Login','Superadmin logged in','admin','superadmin','Super Admin','2026-05-01','05:08:35');
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
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_activity_logs`
--

LOCK TABLES `tenant_activity_logs` WRITE;
/*!40000 ALTER TABLE `tenant_activity_logs` DISABLE KEYS */;
INSERT INTO `tenant_activity_logs` VALUES (1,1,'Login','Admin logged in',1,'2026-04-28','13:00:51'),(2,1,'Logout','Tenant logged out',1,'2026-04-28','13:01:55'),(3,1,'Login','Receptionist logged in',1,'2026-04-28','13:01:59'),(4,1,'Logout','Receptionist logged out',1,'2026-04-28','13:02:01'),(5,1,'Login','Receptionist logged in',1,'2026-04-28','13:03:38'),(6,1,'Logout','Receptionist logged out',1,'2026-04-28','13:10:30'),(7,1,'Login','Admin logged in',1,'2026-04-28','13:10:45'),(8,1,'Logout','Tenant logged out',1,'2026-04-28','13:11:34'),(9,1,'Login','Dentist logged in',1,'2026-04-28','13:11:43'),(10,1,'Schedule','Dentist updated full weekly schedule',1,'2026-04-28','13:11:57'),(11,1,'Login','Admin logged in',1,'2026-04-28','14:34:58'),(12,1,'Logout','Tenant logged out',1,'2026-04-28','14:35:42'),(13,1,'Login','Dentist logged in',1,'2026-04-28','14:35:52'),(14,1,'Logout','Dentist logged out',1,'2026-04-28','14:36:01'),(15,1,'Login','Admin logged in',1,'2026-04-28','14:39:07'),(16,1,'Login','Admin logged in',1,'2026-04-28','14:46:18'),(17,1,'Logout','Tenant logged out',1,'2026-04-28','14:46:22'),(18,1,'Login','Admin logged in',1,'2026-04-28','14:46:33'),(19,1,'Logout','Tenant logged out',1,'2026-04-28','14:49:14'),(20,1,'Login','Admin logged in',1,'2026-04-28','14:54:56'),(21,1,'Logout','Tenant logged out',1,'2026-04-28','14:55:40'),(22,1,'Login','Admin logged in',1,'2026-04-28','14:55:56'),(23,1,'Login','Receptionist logged in',1,'2026-04-28','15:01:35'),(24,2,'Login','Admin logged in',1,'2026-04-30','00:05:37'),(25,2,'Login','Admin logged in',1,'2026-04-30','00:31:51'),(26,1,'Login','Admin logged in',1,'2026-04-30','14:04:53'),(27,1,'Login','Admin logged in',1,'2026-04-30','15:14:40'),(28,1,'Login','Admin logged in',1,'2026-04-30','15:57:28'),(29,1,'Logout','Tenant logged out',1,'2026-04-30','16:07:18'),(30,1,'Login','Receptionist logged in',1,'2026-04-30','16:07:31'),(31,1,'Login','Admin logged in',1,'2026-04-30','16:15:46'),(32,1,'Login','Admin logged in',1,'2026-04-30','16:30:45'),(33,1,'Login','Admin logged in',1,'2026-05-01','06:20:41'),(34,1,'Login','Admin logged in',1,'2026-05-01','06:27:42'),(35,1,'Logout','Tenant logged out',1,'2026-05-01','09:25:12');
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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_configs`
--

LOCK TABLES `tenant_configs` WRITE;
/*!40000 ALTER TABLE `tenant_configs` DISABLE KEYS */;
INSERT INTO `tenant_configs` VALUES (1,1,'brand_logo_path','','2026-04-30 00:30:07','2026-04-30 00:30:07'),(2,1,'brand_bg_color','#001f3f','2026-04-30 00:30:07','2026-04-30 00:30:07'),(3,1,'brand_subtitle','Powered by OralSync','2026-04-30 00:30:07','2026-04-30 00:30:07'),(4,1,'login_title','Clinic Login','2026-04-30 00:30:07','2026-04-30 00:30:07'),(5,1,'primary_btn_color','#22c55e','2026-04-30 00:30:07','2026-04-30 00:30:07'),(6,1,'link_color','#2563eb','2026-04-30 00:30:07','2026-04-30 00:30:07'),(7,1,'brand_bg_image_path','','2026-04-30 00:30:07','2026-04-30 00:30:07'),(8,1,'brand_text_color','#ffffff','2026-04-30 00:30:07','2026-04-30 00:30:07'),(9,1,'booking_deposit_amount','500.00','2026-05-01 09:31:33','2026-05-01 09:31:33'),(10,1,'cancellation_hours','24','2026-05-01 09:31:33','2026-05-01 09:31:33');
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_documents`
--

LOCK TABLES `tenant_documents` WRITE;
/*!40000 ALTER TABLE `tenant_documents` DISABLE KEYS */;
INSERT INTO `tenant_documents` VALUES (1,2,'Pearl_White_Dental_Center_Profile.pdf','uploads/tenant_docs/doc_2_69f293114083c.pdf','application/pdf',18264,'2026-04-29 23:24:01'),(2,2,'Pearl_White_Dental_Center_Profile.pdf','uploads/tenant_docs/doc_2_69f2a2e44c767.pdf','application/pdf',18264,'2026-04-30 00:31:32');
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenants`
--

LOCK TABLES `tenants` WRITE;
/*!40000 ALTER TABLE `tenants` DISABLE KEYS */;
INSERT INTO `tenants` VALUES (1,'ToothFairy','Carl Micko T. Tibay','sound762@deltajohnsons.com','$2y$12$wIGzlzWMFJRKfgQlOnYJJub9nY3ig2mbulCXuuqBSfZQv08yqAfNK',NULL,NULL,'09477230297','Mabalas-balas','San Rafael','Bulacan','toothfairy-73d1','Landing Page/tenant_homepage.php?tenant=toothfairy-73d1','A2GNMEVT','toothfairy','active','professional','2026-04-29 00:00:00',12,NULL,NULL,1,'2026-04-28 12:59:43'),(2,'Pearl White Dental Center','Dr. Elena Rodriguez, DMD','glorisalmon@deltajohnsons.com','$2y$12$IEZOkqdwrtVFKUi.PPOaA.j1WS9hMP/TPP6mD0WVjyZLBgUVAVRkm',NULL,NULL,'09325234295','Level 3, Sky Tower','Makati','Metro Manila','pearl-white-dental-center-6fac','Landing Page/tenant_homepage.php?tenant=pearl-white-dental-center-6fac','PHQHMBPJ','pearlwhite','active','startup','2026-04-30 00:00:00',12,NULL,NULL,1,'2026-04-29 23:24:01');
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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `unique_user_per_tenant` (`username`,`tenant_id`),
  KEY `fk_users_tenant` (`tenant_id`),
  CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,1,'toothfairy2','54belita@deltajohnsons.com','$2y$12$wUEuagbtX73Z.f8CNpoueOyOD3BU6LjY0Xret4Ow2JeXkDFQ/dh3q','Receptionist','Lord','Farquad','2026-04-28 13:01:53',NULL),(2,1,'toothfairy3','toothfairy@sample.com','$2y$12$E2emMTbQ02Coc9OXBPhC8u81ZYfdb5bLUyX5dLNfxoBoxkFCgXf4G','Dentist','Michael','Gordon','2026-04-28 13:11:32',NULL),(3,1,'tfadmin2','7missie@deltajohnsons.com','$2y$12$N69d1jezAIkRr3Jx7fWJquCflHPDN130eG4Lv6i16Wz6V.nKP1TTe','Admin','George','Harrison','2026-04-28 14:49:10',NULL);
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

-- Dump completed on 2026-05-01 17:32:31
