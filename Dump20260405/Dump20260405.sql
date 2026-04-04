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
  `status` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `procedure_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
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
INSERT INTO `appointment` VALUES (1,5,2,2,'2026-04-11',NULL,NULL,NULL,'pending',NULL);
/*!40000 ALTER TABLE `appointment` ENABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dentist`
--

LOCK TABLES `dentist` WRITE;
/*!40000 ALTER TABLE `dentist` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dentist_schedule`
--

LOCK TABLES `dentist_schedule` WRITE;
/*!40000 ALTER TABLE `dentist_schedule` DISABLE KEYS */;
/*!40000 ALTER TABLE `dentist_schedule` ENABLE KEYS */;
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
  PRIMARY KEY (`patient_id`),
  UNIQUE KEY `username` (`username`),
  KEY `fk_patient_tenant` (`tenant_id`),
  CONSTRAINT `fk_patient_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patient`
--

LOCK TABLES `patient` WRITE;
/*!40000 ALTER TABLE `patient` DISABLE KEYS */;
INSERT INTO `patient` VALUES (1,1,'Amiel','Santos','09123456789','test@example.com','$2y$12$qz7tub2gHFKgTeJSFot79.CK/V/cfzuO/OoxbQNnmd6KFPKsgDTR6','testpatient','Baliwag, Bulacan','2000-01-01','Male','Student','None','Peanuts','First test account'),(2,5,'Daniel','Caesar','09477230297','test2@example.com','$2y$12$LjJycgbCZvCszHhzF5o7puk9i3Bi3.KgFvozWU0MF/UYvBDoH/k6a','whoknows','taga dyan lang','1996-04-03','Other','Self-employed','car accident','bagoong tapos matcha','');
/*!40000 ALTER TABLE `patient` ENABLE KEYS */;
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
  `appointment_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `mode` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Cash',
  `status` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `payment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  KEY `fk_payment_tenant` (`tenant_id`),
  KEY `idx_payment_date` (`payment_date`),
  KEY `idx_payment_tenant_date` (`tenant_id`,`payment_date`),
  CONSTRAINT `fk_payment_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment`
--

LOCK TABLES `payment` WRITE;
/*!40000 ALTER TABLE `payment` DISABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'system_name','OralSync','2026-04-04 06:24:43'),(2,'max_tenants','','2026-04-04 05:47:29'),(3,'max_users_per_tenant','','2026-04-04 05:47:29'),(4,'storage_limit','','2026-04-04 05:47:29'),(17,'logo_path','logo_1775283867.jpg','2026-04-04 06:24:27');
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
  `status` enum('Active','Inactive') COLLATE utf8mb4_general_ci DEFAULT 'Active',
  `hired_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_superadmin_reset_token` (`password_reset_token`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `super_admins`
--

LOCK TABLES `super_admins` WRITE;
/*!40000 ALTER TABLE `super_admins` DISABLE KEYS */;
INSERT INTO `super_admins` VALUES (1,'admin','admin123',NULL,NULL,'2026-04-04 16:01:23');
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
) ENGINE=InnoDB AUTO_INCREMENT=144 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `superadmin_logs`
--

LOCK TABLES `superadmin_logs` WRITE;
/*!40000 ALTER TABLE `superadmin_logs` DISABLE KEYS */;
INSERT INTO `superadmin_logs` VALUES (1,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-20','08:00:00'),(2,'Tenant Registration','Registered new clinic','admin','superadmin','Super Admin','2026-03-20','09:05:00'),(3,'Tenant Registration','Registered new clinic','admin','superadmin','Super Admin','2026-03-20','09:15:00'),(4,'Tenant Registration','Registered new clinic','admin','superadmin','Super Admin','2026-03-20','09:25:00'),(5,'Tenant Status Change','Changed tenant status','admin','superadmin','Super Admin','2026-03-20','11:10:00'),(6,'Audit Dashboard Access','Viewed dashboard','admin','superadmin','Super Admin','2026-03-20','13:10:00'),(7,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-03-20','17:30:00'),(8,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','04:16:29'),(9,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','05:01:37'),(10,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-03-25','05:02:00'),(11,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','05:02:17'),(12,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-03-25','05:07:56'),(13,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','05:07:59'),(14,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','05:10:30'),(15,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-03-25','05:11:18'),(16,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','05:11:22'),(17,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','05:16:33'),(18,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','05:20:14'),(19,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','13:14:49'),(20,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','13:21:17'),(21,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','13:22:06'),(22,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','13:47:36'),(23,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','13:49:36'),(24,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-03-25','13:51:50'),(25,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','13:57:47'),(26,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','14:04:36'),(27,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','14:05:13'),(28,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','14:05:23'),(29,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','14:15:43'),(30,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','14:16:49'),(31,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','14:25:14'),(32,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','14:32:33'),(33,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-03-25','14:42:06'),(34,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','14:42:11'),(35,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-03-25','14:42:19'),(36,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','14:44:33'),(37,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','15:04:18'),(38,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','15:08:57'),(39,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','15:14:32'),(40,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','15:15:43'),(41,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','15:31:38'),(42,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','15:33:27'),(43,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','15:36:31'),(44,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','16:10:17'),(45,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','08:26:08'),(46,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-03-26','08:36:44'),(47,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','08:40:05'),(48,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','12:44:05'),(49,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','12:47:36'),(50,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-03-26','12:48:48'),(51,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','12:49:50'),(52,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','12:53:35'),(53,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','13:17:24'),(54,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','13:18:08'),(55,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','13:47:28'),(56,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','13:48:07'),(57,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','14:14:33'),(58,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','14:14:39'),(59,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','14:23:59'),(60,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','15:05:37'),(61,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','15:16:50'),(62,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','15:36:42'),(63,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','15:50:20'),(64,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','15:55:43'),(65,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','15:56:30'),(66,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','16:00:16'),(67,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','16:04:07'),(68,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','16:14:12'),(69,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','16:14:23'),(70,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','16:27:57'),(71,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','16:34:28'),(72,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','16:48:16'),(73,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','16:49:52'),(74,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','17:07:13'),(75,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','17:11:45'),(76,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','23:15:02'),(77,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','23:18:07'),(78,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','23:28:06'),(79,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','23:28:28'),(80,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','23:38:30'),(81,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','23:50:18'),(82,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-27','00:58:57'),(83,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-01','12:38:48'),(84,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-02','05:43:02'),(85,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-02','06:05:37'),(86,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-02','07:30:52'),(87,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-02','09:42:19'),(88,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-02','09:43:25'),(89,'Tenant Status Change','Tenant status changed to inactive',NULL,'superadmin','Super Admin','2026-04-02','09:44:50'),(90,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-02','11:09:14'),(91,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-02','11:19:57'),(92,'Tenant Status Change','Tenant status changed to active',NULL,'superadmin','Super Admin','2026-04-02','11:38:59'),(93,'Tenant Status Change','Tenant status changed to inactive',NULL,'superadmin','Super Admin','2026-04-02','11:43:02'),(94,'Tenant Status Change','Tenant status changed to inactive',NULL,'superadmin','Super Admin','2026-04-02','11:43:06'),(95,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-02','16:39:14'),(96,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-02','16:41:04'),(97,'Tenant Status Change','Tenant status changed to inactive',NULL,'superadmin','Super Admin','2026-04-02','16:54:14'),(98,'Tenant Status Change','Tenant status changed to active',NULL,'superadmin','Super Admin','2026-04-02','16:59:09'),(99,'Tenant Status Change','Tenant status changed to active',NULL,'superadmin','Super Admin','2026-04-02','16:59:09'),(100,'Tenant Status Change','Tenant status changed to inactive',NULL,'superadmin','Super Admin','2026-04-02','16:59:11'),(101,'Tenant Status Change','Tenant status changed to inactive',NULL,'superadmin','Super Admin','2026-04-02','16:59:47'),(102,'Tenant Status Change','Tenant status changed to active',NULL,'superadmin','Super Admin','2026-04-02','17:00:59'),(103,'Tenant Status Change','Tenant status changed to inactive',NULL,'superadmin','Super Admin','2026-04-02','17:01:07'),(104,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','04:52:45'),(105,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-03','04:55:23'),(106,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','04:55:26'),(107,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','06:40:34'),(108,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','09:47:10'),(109,'Tenant Status Change','Tenant status changed to active',NULL,'superadmin','Super Admin','2026-04-03','09:50:15'),(110,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','10:49:38'),(111,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','11:18:19'),(112,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','13:07:31'),(113,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','16:05:24'),(114,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','16:59:00'),(115,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','18:06:34'),(116,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-03','18:07:05'),(117,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','18:07:08'),(118,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','18:17:18'),(119,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','19:09:58'),(120,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','19:39:34'),(121,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','05:08:34'),(122,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','05:40:22'),(123,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-04','05:40:45'),(124,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','05:41:21'),(125,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','06:03:52'),(126,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','06:24:12'),(127,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','06:28:12'),(128,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','07:07:42'),(129,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','07:40:33'),(130,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','07:51:12'),(131,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','08:44:12'),(132,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','10:18:51'),(133,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','10:36:04'),(134,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','11:15:51'),(135,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','12:32:02'),(136,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','13:13:23'),(137,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','13:27:16'),(138,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','14:13:29'),(139,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','15:16:14'),(140,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-04','15:19:58'),(141,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','15:20:01'),(142,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','15:32:42'),(143,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','16:01:23');
/*!40000 ALTER TABLE `superadmin_logs` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=137 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_activity_logs`
--

LOCK TABLES `tenant_activity_logs` WRITE;
/*!40000 ALTER TABLE `tenant_activity_logs` DISABLE KEYS */;
INSERT INTO `tenant_activity_logs` VALUES (1,6,'Tenant Login','Tenant logged in',1,'2026-03-26','17:11:56'),(2,6,'Tenant Login','Tenant logged in',1,'2026-03-26','18:15:57'),(3,8,'Tenant Login','Tenant logged in',1,'2026-03-26','19:56:03'),(4,8,'Tenant Login','Tenant logged in',1,'2026-03-26','23:19:48'),(5,8,'Tenant Login','Tenant logged in',1,'2026-03-26','23:20:21'),(6,6,'Tenant Login','Tenant logged in',1,'2026-03-26','23:28:39'),(7,8,'Tenant Login','Tenant logged in',1,'2026-03-26','23:33:40'),(8,8,'Tenant Login','Tenant logged in',1,'2026-03-26','23:50:41'),(9,8,'Tenant Login','Tenant logged in',1,'2026-03-27','00:17:19'),(10,8,'Tenant Login','Tenant logged in',1,'2026-03-27','00:34:11'),(11,6,'Tenant Login','Tenant logged in',1,'2026-04-01','12:39:10'),(12,6,'Tenant Login','Tenant logged in',1,'2026-04-02','05:43:29'),(13,6,'Tenant Login','Tenant logged in',1,'2026-04-02','07:31:44'),(14,6,'Tenant Login','Tenant logged in',1,'2026-04-02','09:46:42'),(15,6,'Tenant Login','Tenant logged in',1,'2026-04-02','11:37:02'),(16,6,'Tenant Login','Tenant logged in',1,'2026-04-02','16:39:37'),(17,6,'Tenant Logout','Tenant logged out',1,'2026-04-02','16:40:54'),(18,6,'Tenant Login','Tenant logged in',1,'2026-04-02','16:43:49'),(19,5,'Tenant Login','Tenant logged in',1,'2026-04-02','17:01:35'),(20,5,'Tenant Login','Tenant logged in',1,'2026-04-03','04:56:06'),(21,5,'Tenant Logout','Tenant logged out',1,'2026-04-03','05:56:36'),(22,5,'Tenant Login','Tenant logged in',1,'2026-04-03','05:56:43'),(23,5,'Tenant Login','Tenant logged in',1,'2026-04-03','09:50:47'),(24,5,'Tenant Logout','Tenant logged out',1,'2026-04-03','10:29:52'),(25,5,'Tenant Login','Tenant logged in',1,'2026-04-03','10:30:06'),(26,5,'Tenant Logout','Tenant logged out',1,'2026-04-03','10:30:36'),(27,5,'Tenant Login','Tenant logged in',1,'2026-04-03','10:41:26'),(28,5,'Tenant Login','Tenant logged in',1,'2026-04-03','10:53:50'),(29,5,'Tenant Login','Tenant logged in',1,'2026-04-03','11:28:01'),(30,5,'Patient Created','New patient: Daniel Caesar',1,'2026-04-03','11:37:27'),(31,5,'Tenant Logout','Tenant logged out',1,'2026-04-03','12:01:36'),(32,5,'Tenant Login','Tenant logged in',1,'2026-04-03','12:02:18'),(33,5,'Tenant Login','Tenant logged in',1,'2026-04-03','12:02:22'),(34,6,'Tenant Login','Tenant logged in',1,'2026-04-03','13:34:48'),(35,6,'Tenant Logout','Tenant logged out',1,'2026-04-03','13:46:14'),(36,5,'Tenant Login','Tenant logged in',1,'2026-04-03','13:46:28'),(37,5,'Tenant Logout','Tenant logged out',1,'2026-04-03','13:46:54'),(38,6,'Tenant Login','Tenant logged in',1,'2026-04-03','13:47:04'),(39,5,'Tenant Login','Tenant logged in',1,'2026-04-03','13:47:27'),(40,5,'Tenant Logout','Tenant logged out',1,'2026-04-03','13:49:32'),(41,5,'Tenant Login','Tenant logged in',1,'2026-04-03','13:55:38'),(42,5,'Tenant Login','Tenant logged in',1,'2026-04-03','16:07:13'),(43,6,'Tenant Login','Tenant logged in',1,'2026-04-03','16:07:44'),(44,5,'Appointment Created','New appointment scheduled for patient ID: 2',1,'2026-04-03','16:11:13'),(45,5,'Tenant Login','Tenant logged in',1,'2026-04-03','16:59:13'),(46,5,'Admin Login','Admin logged in',1,'2026-04-03','18:07:25'),(47,5,'Tenant Logout','Tenant logged out',1,'2026-04-03','18:07:46'),(48,5,'Dentist Login','Dentist logged in',1,'2026-04-03','18:08:20'),(49,5,'Dentist Login','Dentist logged in',1,'2026-04-03','18:08:41'),(50,5,'Admin Login','Admin logged in',1,'2026-04-03','18:23:46'),(51,5,'Tenant Logout','Tenant logged out',1,'2026-04-03','18:25:17'),(52,5,'Receptionist Login','Receptionist logged in',1,'2026-04-03','18:25:32'),(53,5,'Receptionist Login','Receptionist logged in',1,'2026-04-03','18:25:49'),(54,5,'Admin Login','Admin logged in',1,'2026-04-03','18:27:28'),(55,5,'Admin Login','Admin logged in',1,'2026-04-03','19:11:32'),(56,5,'Admin Login','Admin logged in',1,'2026-04-03','19:11:37'),(57,5,'Admin Login','Admin logged in',1,'2026-04-03','19:11:52'),(58,5,'Admin Login','Admin logged in',1,'2026-04-03','19:41:26'),(59,5,'Admin Login','Admin logged in',1,'2026-04-03','19:41:43'),(60,5,'Admin Login','Admin logged in',1,'2026-04-04','05:18:19'),(61,5,'Admin Login','Admin logged in',1,'2026-04-04','05:44:29'),(62,5,'Admin Login','Admin logged in',1,'2026-04-04','07:10:41'),(63,5,'Admin Login','Admin logged in',1,'2026-04-04','07:41:01'),(64,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','07:41:57'),(65,5,'Dentist Login','Dentist logged in',1,'2026-04-04','07:42:37'),(66,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','07:43:04'),(67,5,'Dentist Login','Dentist logged in',1,'2026-04-04','07:44:47'),(68,5,'Dentist Login','Dentist logged in',1,'2026-04-04','07:47:50'),(69,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','07:50:10'),(70,5,'Dentist Login','Dentist logged in',1,'2026-04-04','07:53:12'),(71,5,'Admin Login','Admin logged in',1,'2026-04-04','08:04:13'),(72,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','08:31:48'),(73,5,'Dentist Login','Dentist logged in',1,'2026-04-04','08:32:10'),(74,5,'Dentist Login','Dentist logged in',1,'2026-04-04','08:32:14'),(75,5,'Admin Login','Admin logged in',1,'2026-04-04','08:44:20'),(76,5,'Admin Login','Admin logged in',1,'2026-04-04','10:19:11'),(77,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','10:19:24'),(78,5,'Admin Login','Admin logged in',1,'2026-04-04','10:27:33'),(79,5,'Admin Login','Admin logged in',1,'2026-04-04','10:36:15'),(80,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','10:36:47'),(81,5,'Dentist Login','Dentist logged in',1,'2026-04-04','10:37:00'),(82,5,'Dentist Login','Dentist logged in',1,'2026-04-04','10:37:20'),(83,5,'Dentist Login','Dentist logged in',1,'2026-04-04','10:37:25'),(84,5,'Dentist Login','Dentist logged in',1,'2026-04-04','10:37:36'),(85,5,'Admin Login','Admin logged in',1,'2026-04-04','10:37:48'),(86,5,'Admin Login','Admin logged in',1,'2026-04-04','11:16:00'),(87,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','11:16:17'),(88,5,'Dentist Login','Dentist logged in',1,'2026-04-04','11:16:30'),(89,5,'Admin Login','Admin logged in',1,'2026-04-04','11:17:29'),(90,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','11:18:38'),(91,5,'Dentist Login','Dentist logged in',1,'2026-04-04','11:18:49'),(92,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','11:24:11'),(93,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','11:24:24'),(94,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','11:24:37'),(95,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','11:31:35'),(96,5,'Dentist Login','Dentist logged in',1,'2026-04-04','11:31:49'),(97,5,'Admin Login','Admin logged in',1,'2026-04-04','11:33:53'),(98,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','11:36:48'),(99,5,'Dentist Login','Dentist logged in',1,'2026-04-04','11:37:03'),(100,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','11:39:15'),(101,5,'Admin Login','Admin logged in',1,'2026-04-04','11:39:26'),(102,5,'Admin Login','Admin logged in',1,'2026-04-04','12:32:22'),(103,5,'Dentist Login','Dentist logged in',1,'2026-04-04','12:33:24'),(104,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','12:33:38'),(105,5,'Dentist Login','Dentist logged in',1,'2026-04-04','12:34:17'),(106,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','12:37:23'),(107,5,'Dentist Login','Dentist logged in',1,'2026-04-04','12:41:37'),(108,5,'Admin Login','Admin logged in',1,'2026-04-04','13:13:35'),(109,5,'Dentist Login','Dentist logged in',1,'2026-04-04','13:14:12'),(110,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','13:14:24'),(111,5,'Admin Login','Admin logged in',1,'2026-04-04','13:27:29'),(112,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','13:27:39'),(113,5,'Dentist Login','Dentist logged in',1,'2026-04-04','13:27:48'),(114,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','13:29:03'),(115,5,'Dentist Login','Dentist logged in',1,'2026-04-04','13:37:49'),(116,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','13:49:13'),(117,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','14:14:40'),(118,5,'Admin Login','Admin logged in',1,'2026-04-04','14:15:57'),(119,5,'Dentist Login','Dentist logged in',1,'2026-04-04','14:16:09'),(120,5,'Admin Login','Admin logged in',1,'2026-04-04','15:20:15'),(121,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','15:21:12'),(122,5,'Dentist Login','Dentist logged in',1,'2026-04-04','15:22:26'),(123,5,'Admin Login','Admin logged in',1,'2026-04-04','15:22:53'),(124,5,'Dentist Login','Dentist logged in',1,'2026-04-04','15:23:56'),(125,5,'Admin Login','Admin logged in',1,'2026-04-04','15:32:52'),(126,5,'Dentist Login','Dentist logged in',1,'2026-04-04','15:33:02'),(127,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','15:33:17'),(128,5,'Dentist Login','Dentist logged in',1,'2026-04-04','15:33:33'),(129,5,'Admin Login','Admin logged in',1,'2026-04-04','16:01:40'),(130,5,'Dentist Login','Dentist logged in',1,'2026-04-04','16:02:38'),(131,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','16:03:13'),(132,5,'Dentist Login','Dentist logged in',1,'2026-04-04','16:06:17'),(133,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','16:32:59'),(134,5,'Dentist Login','Dentist logged in',1,'2026-04-04','16:44:55'),(135,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','16:56:42'),(136,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','16:56:53');
/*!40000 ALTER TABLE `tenant_activity_logs` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_subscription_revenue`
--

LOCK TABLES `tenant_subscription_revenue` WRITE;
/*!40000 ALTER TABLE `tenant_subscription_revenue` DISABLE KEYS */;
INSERT INTO `tenant_subscription_revenue` VALUES (1,8,'startup',124.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(2,7,'startup',124.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(3,6,'startup',124.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(4,5,'startup',124.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(5,4,'startup',124.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(6,3,'startup',124.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(7,2,'startup',124.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(8,1,'startup',124.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(16,8,'startup',6200.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(17,7,'startup',6200.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(18,6,'startup',6200.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(19,5,'startup',6200.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(20,4,'startup',6200.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(21,3,'startup',6200.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(22,2,'startup',6200.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(23,1,'startup',6200.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00');
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
  `username` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('active','inactive','suspended') COLLATE utf8mb4_general_ci DEFAULT 'active',
  `subscription_tier` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'startup',
  `subscription_start_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `trial_start_date` timestamp NULL DEFAULT NULL,
  `trial_end_date` timestamp NULL DEFAULT NULL,
  `must_change_password` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tenant_id`),
  UNIQUE KEY `subdomain_slug` (`subdomain_slug`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_trial_end_date` (`trial_end_date`),
  KEY `idx_tenant_reset_token` (`password_reset_token`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenants`
--

LOCK TABLES `tenants` WRITE;
/*!40000 ALTER TABLE `tenants` DISABLE KEYS */;
INSERT INTO `tenants` VALUES (1,'Yes','Amiel','amielcarlsantos26@gmail.com','$2y$10$5VMWAykcvs3Q8mvDDEd7I.WHP5H6dPMXc.bIITDEAx8uPRXgVfriW',NULL,NULL,'09959079137','#69 E Delos Angeles','Malolos','Bulacan','yes-b26d',NULL,'active','startup','2026-03-26 12:28:05',NULL,NULL,1,'2026-03-15 14:02:19'),(2,'Gold\\\'s Best','Gold Javier','darkagedbat@gmail.com','$2y$12$UwS9GV6BGeWfCp11jdXvfeEJA0/F/cpJU0AXd5Rjv2RH9AIsMXEIe',NULL,NULL,'09477230297','Diliman 1st','San Rafael','Bulacan','gold-s-best-518f',NULL,'inactive','startup','2026-03-26 12:28:05',NULL,NULL,1,'2026-03-16 09:23:25'),(3,'MiniACE','Gabriel Toledo','darkagedbat@gmail.com','$2y$12$KwqsaKh8VTKMeHGvWI/veeeS2oyP7GRQ0TC5i/S2cqexTO0BC255S',NULL,NULL,'09477230297','Brngy. Capihan','San Rafael','Bulacan','miniace-c577',NULL,'active','startup','2026-03-26 12:28:05',NULL,NULL,1,'2026-03-16 10:33:59'),(4,'MiniACE2','Gabriel Toledo','darkagedbat@gmail.com','$2y$12$4VtRSJVLE9lERG1qhfdxcOaD6ojhcl.lyNqRm7Ccfyi5DEuvPHphO',NULL,NULL,'09477230297','Brngy. Capihan','San Rafael','Bulacan','miniace2-574a',NULL,'active','startup','2026-03-26 12:28:05',NULL,NULL,1,'2026-03-16 10:52:15'),(5,'Radford Clinic','Razz Dela Cruz','darkagedbat@gmail.com','$2y$12$q4Uz4UqQKpHvA6/iECa7OOMOweKxsK/GlZ11IYQYxH/pp8UJhPJ3S',NULL,NULL,'09477230297','Barangay San Roque','San Rafael','Bulacan','radford-clinic-dec9',NULL,'active','startup','2026-03-26 12:28:05',NULL,NULL,1,'2026-03-16 13:03:43'),(6,'Winford\\\'s Medical','Sarado Nah','darkagedbat@gmail.com','$2y$12$AojUzl.0j0TDNrpTtgk4h.uJfHK4yuZAJueKUidAMR6jwWCtKC5Be',NULL,NULL,'09477230297','Maguinao','San Rafael','Bulacan','winford-s-medical-9cf1',NULL,'active','startup','2026-03-26 12:28:05',NULL,NULL,1,'2026-03-16 13:11:10'),(7,'Ace\\\'s Clinic','Raph De Guzman','amielcarlsantos.basc@gmail.com','$2y$12$grUjLRIY3bAYTvdd8vd24e/h0G7gpGt2nG5kVBIpO3jqi9tDHGzG.',NULL,NULL,'09477230297','Subic','Baliuag','Bulacan','ace-s-clinic-29f6',NULL,'inactive','startup','2026-03-26 12:28:05',NULL,NULL,1,'2026-03-16 13:33:22'),(8,'13','Amiel','amielcarlsantos26@gmail.com','$2y$12$YuJ/orf3LSrPXSQcyepld.UG31zFER4hTmWMP67FTZ78g3UuZXYRm',NULL,NULL,'09959079137','#69 E Delos Angeles','Malolos','Bulacan','13-de45',NULL,'active','startup','2026-03-26 12:28:05',NULL,NULL,1,'2026-03-25 15:41:21');
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
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `unique_user_per_tenant` (`username`,`tenant_id`),
  KEY `fk_users_tenant` (`tenant_id`),
  CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,5,'tibsonme','reggie4C@example.com','$2y$12$XoFbflVsXVHB1gFjfi2SEei1T/m1I99JLvGINdIcVW7aWJfJw.0hm','Receptionist',NULL,NULL),(2,5,'sinsdaOG','reggie4D@example.com','$2y$12$awHyxYAPc02M./7L9KRrhetx/XS5tp.AbaAGY/KNt8GBy20hRA6ce','Dentist',NULL,NULL),(3,5,'tibsonme2','reggie4E@example.com','$2y$12$G2VhyPUcHq2S.ydK4aPTFeWzy.wtddX17VdiZHo4R5I8HBGT.40tK','Receptionist',NULL,NULL),(4,5,'adminStaff','reggie4F@test.example.com','$2y$12$TRSdtoxBWePMtv5SGBS1uOj99i82R8crVMSblF0kmG5o0jJddqXaO','Admin',NULL,NULL);
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

-- Dump completed on 2026-04-05  1:21:07
