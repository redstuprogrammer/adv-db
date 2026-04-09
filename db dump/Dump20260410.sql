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
  `status` enum('pending','completed','cancelled','approved','disapproved') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `procedure_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_appointment_request` tinyint(1) DEFAULT '1',
  `requested_by` enum('patient','receptionist','dentist') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'patient',
  PRIMARY KEY (`appointment_id`),
  KEY `fk_appt_tenant` (`tenant_id`),
  KEY `fk_appt_service` (`service_id`),
  CONSTRAINT `fk_appt_service` FOREIGN KEY (`service_id`) REFERENCES `service` (`service_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_appt_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointment`
--

LOCK TABLES `appointment` WRITE;
/*!40000 ALTER TABLE `appointment` DISABLE KEYS */;
INSERT INTO `appointment` VALUES (1,5,2,2,'2026-04-16',NULL,NULL,NULL,'pending',NULL,1,'patient'),(2,5,2,9,'2026-04-09','09:00:00','Patient requested morning slot',1,'pending','Tuli',1,'patient'),(3,5,3,9,'2026-04-09','14:30:00','First time visit',2,'pending','Cleaning',1,'patient'),(4,5,4,9,'2026-04-10','10:00:00','Follow up',1,'pending','Tuli',1,'patient'),(5,5,2,2,'2026-04-09','11:00:00','Urgent toothache',3,'pending','Root Canal',1,'patient'),(6,5,4,2,'2026-04-11','13:00:00','Patient had a conflict',2,'cancelled','Cleaning',1,'patient'),(7,5,2,9,'2026-04-08','11:00:00',NULL,NULL,'pending',NULL,1,'patient'),(8,5,2,9,'2026-04-09','16:30:00','ahtdog\n',NULL,'pending',NULL,1,'patient'),(9,5,2,9,'2026-04-10','11:00:00',NULL,NULL,'pending',NULL,1,'patient'),(10,5,2,9,'2026-04-10','16:30:00',NULL,NULL,'pending',NULL,1,'patient'),(11,5,2,9,'2026-04-13','10:30:00','testingness\n',NULL,'pending',NULL,1,'patient'),(12,5,2,2,'2026-04-10','15:30:00','tesntidbau',NULL,'pending',NULL,1,'patient'),(13,5,2,2,'2026-04-11','14:30:00','madami 1\n\n',NULL,'pending',NULL,1,'patient'),(14,5,2,2,'2026-04-15','18:30:00','madami 2',NULL,'pending',NULL,1,'patient'),(15,5,2,2,'2026-04-11','11:30:00','madami 3\n',NULL,'pending',NULL,1,'patient');
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
  `service_id` int NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `amount_paid` decimal(10,2) DEFAULT '0.00',
  `payment_status` enum('unpaid','partial','paid') DEFAULT 'unpaid',
  `billing_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`billing_id`),
  KEY `fk_bill_tenant` (`tenant_id`),
  KEY `fk_bill_appt` (`appointment_id`),
  KEY `fk_bill_patient` (`patient_id`),
  KEY `fk_bill_service` (`service_id`),
  CONSTRAINT `fk_bill_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointment` (`appointment_id`),
  CONSTRAINT `fk_bill_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_id`),
  CONSTRAINT `fk_bill_service` FOREIGN KEY (`service_id`) REFERENCES `service` (`service_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_bill_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clinic_schedules`
--

LOCK TABLES `clinic_schedules` WRITE;
/*!40000 ALTER TABLE `clinic_schedules` DISABLE KEYS */;
INSERT INTO `clinic_schedules` VALUES (1,5,'Monday','08:00:00','17:00:00',0),(2,5,'Tuesday','08:00:00','17:00:00',0),(3,5,'Wednesday','08:00:00','17:00:00',0),(4,5,'Thursday','08:00:00','17:00:00',0),(5,5,'Friday','08:00:00','17:00:00',0),(6,5,'Saturday','09:00:00','12:00:00',0),(7,5,'Sunday',NULL,NULL,1);
/*!40000 ALTER TABLE `clinic_schedules` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dentist`
--

LOCK TABLES `dentist` WRITE;
/*!40000 ALTER TABLE `dentist` DISABLE KEYS */;
INSERT INTO `dentist` VALUES (2,5,'Reggie','Santos','sinsdaOG','reggie4D@example.com','$2y$12$awHyxYAPc02M./7L9KRrhetx/XS5tp.AbaAGY/KNt8GBy20hRA6ce'),(6,5,'Jim','Gordon','radBud','docock@example.com','$2y$12$Aa022HistjCGKhuKAVHy..Ybk56smsaInY5rd.8oefqM9GRe.3jC2'),(8,8,'Amiel','Santos','Dentist1','amielcarlsantos.basc@gmail.com','$2y$12$Ct2aepbd0kmqjvRHcA2A/.4W/aswKYiG1dvlU3oF3KDEMkwRR85pq'),(9,5,'Amara','Cruz','amara_dentist','amara.cruz@example.com','$2y$10$7qzRk2h9X1wBq5y9L8z0uO');
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
INSERT INTO `dentist_schedule` VALUES (1,9,5,'Monday','08:00:00','17:00:00',1),(2,9,5,'Tuesday','08:00:00','17:00:00',1),(3,9,5,'Wednesday','08:00:00','17:00:00',1),(4,9,5,'Thursday','08:00:00','17:00:00',1),(5,9,5,'Friday','08:00:00','17:00:00',1),(6,9,5,'Saturday','00:00:00','00:00:00',0),(7,9,5,'Sunday','00:00:00','00:00:00',0),(8,2,5,'Monday','00:00:00','00:00:00',0),(9,2,5,'Tuesday','13:00:00','20:00:00',1),(10,2,5,'Wednesday','13:00:00','20:00:00',1),(11,2,5,'Thursday','13:00:00','20:00:00',1),(12,2,5,'Friday','13:00:00','20:00:00',1),(13,2,5,'Saturday','09:00:00','15:00:00',1),(14,2,5,'Sunday','00:00:00','00:00:00',0);
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
  `tenant_patient_id` int NOT NULL,
  PRIMARY KEY (`patient_id`),
  UNIQUE KEY `uq_tenant_patient` (`tenant_id`,`tenant_patient_id`),
  UNIQUE KEY `username` (`username`),
  KEY `fk_patient_tenant` (`tenant_id`),
  KEY `idx_tenant_patient_id` (`tenant_id`,`tenant_patient_id`),
  CONSTRAINT `fk_patient_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patient`
--

LOCK TABLES `patient` WRITE;
/*!40000 ALTER TABLE `patient` DISABLE KEYS */;
INSERT INTO `patient` VALUES (1,1,'Amiel','Santos','09123456789','test@example.com','$2y$12$qz7tub2gHFKgTeJSFot79.CK/V/cfzuO/OoxbQNnmd6KFPKsgDTR6','testpatient','Baliwag, Bulacan','2000-01-01','Male','Student','None','Peanuts','First test account',1),(2,5,'Daniel','Caesar','09477230297','test2@example.com','$2y$12$LjJycgbCZvCszHhzF5o7puk9i3Bi3.KgFvozWU0MF/UYvBDoH/k6a','whoknows','taga dyan lang','1996-04-03','Other','Self-employed','car accident','bagoong tapos matcha','',1),(3,5,'Mark','Agustin','09477230297','luvtatooine@example.com',NULL,NULL,'Robrick Houses, Vanda, Somewhere','1996-04-06','Male',NULL,NULL,NULL,NULL,2),(4,5,'Linda','Montemayor','0923 478','rykepyke@example.com',NULL,NULL,'Area 51, America','2003-03-09','Female',NULL,NULL,NULL,NULL,3),(5,9,'Jeffrey','Dahmer','09175230292','rugaysmolpatient@example.com',NULL,NULL,'Resorts World, Manila','2003-02-25','Male',NULL,NULL,NULL,NULL,1);
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
  `procedures_json` text COLLATE utf8mb4_general_ci,
  `source` enum('web','mobile') COLLATE utf8mb4_general_ci DEFAULT 'web',
  `reference_number` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `payment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `payment_type` enum('deposit','full') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'full' COMMENT 'deposit = booking fee, full = final bill payment',
  PRIMARY KEY (`payment_id`),
  KEY `fk_payment_tenant` (`tenant_id`),
  KEY `idx_payment_date` (`payment_date`),
  KEY `idx_payment_tenant_date` (`tenant_id`,`payment_date`),
  KEY `idx_payment_source` (`source`),
  CONSTRAINT `fk_payment_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment`
--

LOCK TABLES `payment` WRITE;
/*!40000 ALTER TABLE `payment` DISABLE KEYS */;
INSERT INTO `payment` VALUES (1,5,1,800.00,'Cash','Paid','[{\"service_id\":\"2\",\"name\":\"Dental Cleaning\",\"price\":800}]','mobile','MOB-2-1775751217493','2026-04-09 16:13:37','full');
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service`
--

LOCK TABLES `service` WRITE;
/*!40000 ALTER TABLE `service` DISABLE KEYS */;
INSERT INTO `service` VALUES (1,5,'Jaw Repair','',120000.00,'Orthodontics'),(2,5,'Dental Cleaning','',800.00,'Pediatric'),(3,5,'Tooth Removal','',1000.00,'Surgery'),(4,5,'Tooth Pasta','',2500.00,'Restorative'),(5,5,'Teeth Whitening','',2500.00,'Cosmetic');
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
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'system_name','OralSync','2026-04-06 19:45:16'),(2,'max_tenants','','2026-04-06 19:45:16'),(3,'max_users_per_tenant','','2026-04-06 19:45:16'),(4,'storage_limit','','2026-04-06 19:45:16'),(17,'logo_path','/uploads/logo_1775504716.jpg','2026-04-06 19:45:16');
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `super_admins`
--

LOCK TABLES `super_admins` WRITE;
/*!40000 ALTER TABLE `super_admins` DISABLE KEYS */;
INSERT INTO `super_admins` VALUES (1,'admin','admin123','$2y$12$/RZ5gCvi760US.IcdFycwOixud4eYkESUzlFCwpLBOekqRuVnCY3.','2026-04-05 17:18:43','2026-04-09 16:16:46');
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
) ENGINE=InnoDB AUTO_INCREMENT=227 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `superadmin_logs`
--

LOCK TABLES `superadmin_logs` WRITE;
/*!40000 ALTER TABLE `superadmin_logs` DISABLE KEYS */;
INSERT INTO `superadmin_logs` VALUES (1,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-20','08:00:00'),(2,'Tenant Registration','Registered new clinic','admin','superadmin','Super Admin','2026-03-20','09:05:00'),(3,'Tenant Registration','Registered new clinic','admin','superadmin','Super Admin','2026-03-20','09:15:00'),(4,'Tenant Registration','Registered new clinic','admin','superadmin','Super Admin','2026-03-20','09:25:00'),(5,'Tenant Status Change','Changed tenant status','admin','superadmin','Super Admin','2026-03-20','11:10:00'),(6,'Audit Dashboard Access','Viewed dashboard','admin','superadmin','Super Admin','2026-03-20','13:10:00'),(7,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-03-20','17:30:00'),(8,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','04:16:29'),(9,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','05:01:37'),(10,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-03-25','05:02:00'),(11,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','05:02:17'),(12,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-03-25','05:07:56'),(13,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','05:07:59'),(14,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','05:10:30'),(15,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-03-25','05:11:18'),(16,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','05:11:22'),(17,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','05:16:33'),(18,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','05:20:14'),(19,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','13:14:49'),(20,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','13:21:17'),(21,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','13:22:06'),(22,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','13:47:36'),(23,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','13:49:36'),(24,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-03-25','13:51:50'),(25,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','13:57:47'),(26,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','14:04:36'),(27,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','14:05:13'),(28,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','14:05:23'),(29,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','14:15:43'),(30,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','14:16:49'),(31,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','14:25:14'),(32,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','14:32:33'),(33,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-03-25','14:42:06'),(34,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','14:42:11'),(35,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-03-25','14:42:19'),(36,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','14:44:33'),(37,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','15:04:18'),(38,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','15:08:57'),(39,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','15:14:32'),(40,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','15:15:43'),(41,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','15:31:38'),(42,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','15:33:27'),(43,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','15:36:31'),(44,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-25','16:10:17'),(45,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','08:26:08'),(46,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-03-26','08:36:44'),(47,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','08:40:05'),(48,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','12:44:05'),(49,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','12:47:36'),(50,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-03-26','12:48:48'),(51,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','12:49:50'),(52,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','12:53:35'),(53,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','13:17:24'),(54,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','13:18:08'),(55,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','13:47:28'),(56,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','13:48:07'),(57,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','14:14:33'),(58,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','14:14:39'),(59,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','14:23:59'),(60,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','15:05:37'),(61,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','15:16:50'),(62,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','15:36:42'),(63,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','15:50:20'),(64,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','15:55:43'),(65,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','15:56:30'),(66,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','16:00:16'),(67,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','16:04:07'),(68,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','16:14:12'),(69,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','16:14:23'),(70,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','16:27:57'),(71,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','16:34:28'),(72,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','16:48:16'),(73,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','16:49:52'),(74,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','17:07:13'),(75,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','17:11:45'),(76,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','23:15:02'),(77,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','23:18:07'),(78,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','23:28:06'),(79,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','23:28:28'),(80,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','23:38:30'),(81,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-26','23:50:18'),(82,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-03-27','00:58:57'),(83,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-01','12:38:48'),(84,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-02','05:43:02'),(85,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-02','06:05:37'),(86,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-02','07:30:52'),(87,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-02','09:42:19'),(88,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-02','09:43:25'),(89,'Tenant Status Change','Tenant status changed to inactive',NULL,'superadmin','Super Admin','2026-04-02','09:44:50'),(90,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-02','11:09:14'),(91,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-02','11:19:57'),(92,'Tenant Status Change','Tenant status changed to active',NULL,'superadmin','Super Admin','2026-04-02','11:38:59'),(93,'Tenant Status Change','Tenant status changed to inactive',NULL,'superadmin','Super Admin','2026-04-02','11:43:02'),(94,'Tenant Status Change','Tenant status changed to inactive',NULL,'superadmin','Super Admin','2026-04-02','11:43:06'),(95,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-02','16:39:14'),(96,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-02','16:41:04'),(97,'Tenant Status Change','Tenant status changed to inactive',NULL,'superadmin','Super Admin','2026-04-02','16:54:14'),(98,'Tenant Status Change','Tenant status changed to active',NULL,'superadmin','Super Admin','2026-04-02','16:59:09'),(99,'Tenant Status Change','Tenant status changed to active',NULL,'superadmin','Super Admin','2026-04-02','16:59:09'),(100,'Tenant Status Change','Tenant status changed to inactive',NULL,'superadmin','Super Admin','2026-04-02','16:59:11'),(101,'Tenant Status Change','Tenant status changed to inactive',NULL,'superadmin','Super Admin','2026-04-02','16:59:47'),(102,'Tenant Status Change','Tenant status changed to active',NULL,'superadmin','Super Admin','2026-04-02','17:00:59'),(103,'Tenant Status Change','Tenant status changed to inactive',NULL,'superadmin','Super Admin','2026-04-02','17:01:07'),(104,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','04:52:45'),(105,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-03','04:55:23'),(106,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','04:55:26'),(107,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','06:40:34'),(108,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','09:47:10'),(109,'Tenant Status Change','Tenant status changed to active',NULL,'superadmin','Super Admin','2026-04-03','09:50:15'),(110,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','10:49:38'),(111,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','11:18:19'),(112,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','13:07:31'),(113,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','16:05:24'),(114,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','16:59:00'),(115,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','18:06:34'),(116,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-03','18:07:05'),(117,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','18:07:08'),(118,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','18:17:18'),(119,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','19:09:58'),(120,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-03','19:39:34'),(121,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','05:08:34'),(122,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','05:40:22'),(123,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-04','05:40:45'),(124,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','05:41:21'),(125,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','06:03:52'),(126,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','06:24:12'),(127,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','06:28:12'),(128,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','07:07:42'),(129,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','07:40:33'),(130,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','07:51:12'),(131,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','08:44:12'),(132,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','10:18:51'),(133,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','10:36:04'),(134,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','11:15:51'),(135,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','12:32:02'),(136,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','13:13:23'),(137,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','13:27:16'),(138,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','14:13:29'),(139,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','15:16:14'),(140,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-04','15:19:58'),(141,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','15:20:01'),(142,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','15:32:42'),(143,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','16:01:23'),(144,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','17:21:21'),(145,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','19:01:02'),(146,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','19:01:18'),(147,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','19:19:07'),(148,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','20:59:30'),(149,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','21:53:50'),(150,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-04','21:54:13'),(151,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','21:54:31'),(152,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-04','21:55:44'),(153,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','03:20:12'),(154,'Tenant Registration','Registered: Rugay Smol (Tier: professional)','afton92@sharebot.net','superadmin','Super Admin','2026-04-05','03:25:18'),(155,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-05','03:27:17'),(156,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','03:36:44'),(157,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','04:04:59'),(158,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','05:51:02'),(159,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','05:59:22'),(160,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','06:08:31'),(161,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','06:25:58'),(162,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','06:32:53'),(163,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','06:40:31'),(164,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','07:29:44'),(165,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','09:04:45'),(166,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','09:04:52'),(167,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','14:18:00'),(168,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','14:43:21'),(169,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','14:45:16'),(170,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','15:14:55'),(171,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','15:15:01'),(172,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','15:27:48'),(173,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','15:48:42'),(174,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','16:21:22'),(175,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','16:43:59'),(176,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','16:58:04'),(177,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','17:34:43'),(178,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-05','17:35:33'),(179,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','17:35:36'),(180,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','17:50:31'),(181,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','18:20:13'),(182,'Tenant Status Change','Tenant status changed to inactive',NULL,'superadmin','Super Admin','2026-04-05','18:20:29'),(183,'Tenant Status Change','Tenant status changed to inactive',NULL,'superadmin','Super Admin','2026-04-05','18:20:41'),(184,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','18:44:51'),(185,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','19:08:57'),(186,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','19:18:06'),(187,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-05','23:10:28'),(188,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-06','14:44:23'),(189,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-06','16:07:37'),(190,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-06','16:19:05'),(191,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-06','16:50:01'),(192,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-06','17:00:50'),(193,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-06','17:13:44'),(194,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-06','18:22:54'),(195,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-06','18:39:00'),(196,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-06','19:00:53'),(197,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-06','19:16:22'),(198,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-06','19:27:56'),(199,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-06','19:28:21'),(200,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-06','19:28:24'),(201,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-06','19:44:36'),(202,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-06','20:01:49'),(203,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-06','20:35:31'),(204,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-07','02:19:13'),(205,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-07','02:30:35'),(206,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-07','02:45:47'),(207,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-07','03:09:38'),(208,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-07','03:31:20'),(209,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-07','03:40:19'),(210,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-07','03:49:54'),(211,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-08','11:00:17'),(212,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-08','11:00:32'),(213,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-08','11:00:38'),(214,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-08','13:27:38'),(215,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-09','00:20:41'),(216,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-09','01:10:06'),(217,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-09','12:41:42'),(218,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-09','12:44:40'),(219,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-09','14:01:27'),(220,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-09','14:02:10'),(221,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-09','14:02:20'),(222,'Superadmin Logout','Superadmin logged out','admin','superadmin','Super Admin','2026-04-09','14:05:08'),(223,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-09','14:49:43'),(224,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-09','16:12:27'),(225,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-09','16:16:41'),(226,'Superadmin Login','Superadmin logged in','admin','superadmin','Super Admin','2026-04-09','16:16:46');
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
) ENGINE=InnoDB AUTO_INCREMENT=298 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_activity_logs`
--

LOCK TABLES `tenant_activity_logs` WRITE;
/*!40000 ALTER TABLE `tenant_activity_logs` DISABLE KEYS */;
INSERT INTO `tenant_activity_logs` VALUES (1,6,'Tenant Login','Tenant logged in',1,'2026-03-26','17:11:56'),(2,6,'Tenant Login','Tenant logged in',1,'2026-03-26','18:15:57'),(3,8,'Tenant Login','Tenant logged in',1,'2026-03-26','19:56:03'),(4,8,'Tenant Login','Tenant logged in',1,'2026-03-26','23:19:48'),(5,8,'Tenant Login','Tenant logged in',1,'2026-03-26','23:20:21'),(6,6,'Tenant Login','Tenant logged in',1,'2026-03-26','23:28:39'),(7,8,'Tenant Login','Tenant logged in',1,'2026-03-26','23:33:40'),(8,8,'Tenant Login','Tenant logged in',1,'2026-03-26','23:50:41'),(9,8,'Tenant Login','Tenant logged in',1,'2026-03-27','00:17:19'),(10,8,'Tenant Login','Tenant logged in',1,'2026-03-27','00:34:11'),(11,6,'Tenant Login','Tenant logged in',1,'2026-04-01','12:39:10'),(12,6,'Tenant Login','Tenant logged in',1,'2026-04-02','05:43:29'),(13,6,'Tenant Login','Tenant logged in',1,'2026-04-02','07:31:44'),(14,6,'Tenant Login','Tenant logged in',1,'2026-04-02','09:46:42'),(15,6,'Tenant Login','Tenant logged in',1,'2026-04-02','11:37:02'),(16,6,'Tenant Login','Tenant logged in',1,'2026-04-02','16:39:37'),(17,6,'Tenant Logout','Tenant logged out',1,'2026-04-02','16:40:54'),(18,6,'Tenant Login','Tenant logged in',1,'2026-04-02','16:43:49'),(19,5,'Tenant Login','Tenant logged in',1,'2026-04-02','17:01:35'),(20,5,'Tenant Login','Tenant logged in',1,'2026-04-03','04:56:06'),(21,5,'Tenant Logout','Tenant logged out',1,'2026-04-03','05:56:36'),(22,5,'Tenant Login','Tenant logged in',1,'2026-04-03','05:56:43'),(23,5,'Tenant Login','Tenant logged in',1,'2026-04-03','09:50:47'),(24,5,'Tenant Logout','Tenant logged out',1,'2026-04-03','10:29:52'),(25,5,'Tenant Login','Tenant logged in',1,'2026-04-03','10:30:06'),(26,5,'Tenant Logout','Tenant logged out',1,'2026-04-03','10:30:36'),(27,5,'Tenant Login','Tenant logged in',1,'2026-04-03','10:41:26'),(28,5,'Tenant Login','Tenant logged in',1,'2026-04-03','10:53:50'),(29,5,'Tenant Login','Tenant logged in',1,'2026-04-03','11:28:01'),(30,5,'Patient Created','New patient: Daniel Caesar',1,'2026-04-03','11:37:27'),(31,5,'Tenant Logout','Tenant logged out',1,'2026-04-03','12:01:36'),(32,5,'Tenant Login','Tenant logged in',1,'2026-04-03','12:02:18'),(33,5,'Tenant Login','Tenant logged in',1,'2026-04-03','12:02:22'),(34,6,'Tenant Login','Tenant logged in',1,'2026-04-03','13:34:48'),(35,6,'Tenant Logout','Tenant logged out',1,'2026-04-03','13:46:14'),(36,5,'Tenant Login','Tenant logged in',1,'2026-04-03','13:46:28'),(37,5,'Tenant Logout','Tenant logged out',1,'2026-04-03','13:46:54'),(38,6,'Tenant Login','Tenant logged in',1,'2026-04-03','13:47:04'),(39,5,'Tenant Login','Tenant logged in',1,'2026-04-03','13:47:27'),(40,5,'Tenant Logout','Tenant logged out',1,'2026-04-03','13:49:32'),(41,5,'Tenant Login','Tenant logged in',1,'2026-04-03','13:55:38'),(42,5,'Tenant Login','Tenant logged in',1,'2026-04-03','16:07:13'),(43,6,'Tenant Login','Tenant logged in',1,'2026-04-03','16:07:44'),(44,5,'Appointment Created','New appointment scheduled for patient ID: 2',1,'2026-04-03','16:11:13'),(45,5,'Tenant Login','Tenant logged in',1,'2026-04-03','16:59:13'),(46,5,'Admin Login','Admin logged in',1,'2026-04-03','18:07:25'),(47,5,'Tenant Logout','Tenant logged out',1,'2026-04-03','18:07:46'),(48,5,'Dentist Login','Dentist logged in',1,'2026-04-03','18:08:20'),(49,5,'Dentist Login','Dentist logged in',1,'2026-04-03','18:08:41'),(50,5,'Admin Login','Admin logged in',1,'2026-04-03','18:23:46'),(51,5,'Tenant Logout','Tenant logged out',1,'2026-04-03','18:25:17'),(52,5,'Receptionist Login','Receptionist logged in',1,'2026-04-03','18:25:32'),(53,5,'Receptionist Login','Receptionist logged in',1,'2026-04-03','18:25:49'),(54,5,'Admin Login','Admin logged in',1,'2026-04-03','18:27:28'),(55,5,'Admin Login','Admin logged in',1,'2026-04-03','19:11:32'),(56,5,'Admin Login','Admin logged in',1,'2026-04-03','19:11:37'),(57,5,'Admin Login','Admin logged in',1,'2026-04-03','19:11:52'),(58,5,'Admin Login','Admin logged in',1,'2026-04-03','19:41:26'),(59,5,'Admin Login','Admin logged in',1,'2026-04-03','19:41:43'),(60,5,'Admin Login','Admin logged in',1,'2026-04-04','05:18:19'),(61,5,'Admin Login','Admin logged in',1,'2026-04-04','05:44:29'),(62,5,'Admin Login','Admin logged in',1,'2026-04-04','07:10:41'),(63,5,'Admin Login','Admin logged in',1,'2026-04-04','07:41:01'),(64,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','07:41:57'),(65,5,'Dentist Login','Dentist logged in',1,'2026-04-04','07:42:37'),(66,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','07:43:04'),(67,5,'Dentist Login','Dentist logged in',1,'2026-04-04','07:44:47'),(68,5,'Dentist Login','Dentist logged in',1,'2026-04-04','07:47:50'),(69,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','07:50:10'),(70,5,'Dentist Login','Dentist logged in',1,'2026-04-04','07:53:12'),(71,5,'Admin Login','Admin logged in',1,'2026-04-04','08:04:13'),(72,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','08:31:48'),(73,5,'Dentist Login','Dentist logged in',1,'2026-04-04','08:32:10'),(74,5,'Dentist Login','Dentist logged in',1,'2026-04-04','08:32:14'),(75,5,'Admin Login','Admin logged in',1,'2026-04-04','08:44:20'),(76,5,'Admin Login','Admin logged in',1,'2026-04-04','10:19:11'),(77,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','10:19:24'),(78,5,'Admin Login','Admin logged in',1,'2026-04-04','10:27:33'),(79,5,'Admin Login','Admin logged in',1,'2026-04-04','10:36:15'),(80,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','10:36:47'),(81,5,'Dentist Login','Dentist logged in',1,'2026-04-04','10:37:00'),(82,5,'Dentist Login','Dentist logged in',1,'2026-04-04','10:37:20'),(83,5,'Dentist Login','Dentist logged in',1,'2026-04-04','10:37:25'),(84,5,'Dentist Login','Dentist logged in',1,'2026-04-04','10:37:36'),(85,5,'Admin Login','Admin logged in',1,'2026-04-04','10:37:48'),(86,5,'Admin Login','Admin logged in',1,'2026-04-04','11:16:00'),(87,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','11:16:17'),(88,5,'Dentist Login','Dentist logged in',1,'2026-04-04','11:16:30'),(89,5,'Admin Login','Admin logged in',1,'2026-04-04','11:17:29'),(90,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','11:18:38'),(91,5,'Dentist Login','Dentist logged in',1,'2026-04-04','11:18:49'),(92,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','11:24:11'),(93,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','11:24:24'),(94,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','11:24:37'),(95,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','11:31:35'),(96,5,'Dentist Login','Dentist logged in',1,'2026-04-04','11:31:49'),(97,5,'Admin Login','Admin logged in',1,'2026-04-04','11:33:53'),(98,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','11:36:48'),(99,5,'Dentist Login','Dentist logged in',1,'2026-04-04','11:37:03'),(100,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','11:39:15'),(101,5,'Admin Login','Admin logged in',1,'2026-04-04','11:39:26'),(102,5,'Admin Login','Admin logged in',1,'2026-04-04','12:32:22'),(103,5,'Dentist Login','Dentist logged in',1,'2026-04-04','12:33:24'),(104,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','12:33:38'),(105,5,'Dentist Login','Dentist logged in',1,'2026-04-04','12:34:17'),(106,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','12:37:23'),(107,5,'Dentist Login','Dentist logged in',1,'2026-04-04','12:41:37'),(108,5,'Admin Login','Admin logged in',1,'2026-04-04','13:13:35'),(109,5,'Dentist Login','Dentist logged in',1,'2026-04-04','13:14:12'),(110,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','13:14:24'),(111,5,'Admin Login','Admin logged in',1,'2026-04-04','13:27:29'),(112,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','13:27:39'),(113,5,'Dentist Login','Dentist logged in',1,'2026-04-04','13:27:48'),(114,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','13:29:03'),(115,5,'Dentist Login','Dentist logged in',1,'2026-04-04','13:37:49'),(116,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','13:49:13'),(117,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','14:14:40'),(118,5,'Admin Login','Admin logged in',1,'2026-04-04','14:15:57'),(119,5,'Dentist Login','Dentist logged in',1,'2026-04-04','14:16:09'),(120,5,'Admin Login','Admin logged in',1,'2026-04-04','15:20:15'),(121,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','15:21:12'),(122,5,'Dentist Login','Dentist logged in',1,'2026-04-04','15:22:26'),(123,5,'Admin Login','Admin logged in',1,'2026-04-04','15:22:53'),(124,5,'Dentist Login','Dentist logged in',1,'2026-04-04','15:23:56'),(125,5,'Admin Login','Admin logged in',1,'2026-04-04','15:32:52'),(126,5,'Dentist Login','Dentist logged in',1,'2026-04-04','15:33:02'),(127,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','15:33:17'),(128,5,'Dentist Login','Dentist logged in',1,'2026-04-04','15:33:33'),(129,5,'Admin Login','Admin logged in',1,'2026-04-04','16:01:40'),(130,5,'Dentist Login','Dentist logged in',1,'2026-04-04','16:02:38'),(131,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','16:03:13'),(132,5,'Dentist Login','Dentist logged in',1,'2026-04-04','16:06:17'),(133,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','16:32:59'),(134,5,'Dentist Login','Dentist logged in',1,'2026-04-04','16:44:55'),(135,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','16:56:42'),(136,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','16:56:53'),(137,5,'Admin Login','Admin logged in',1,'2026-04-04','17:21:48'),(138,5,'Dentist Login','Dentist logged in',1,'2026-04-04','17:26:12'),(139,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','17:26:23'),(140,5,'Dentist Login','Dentist logged in',1,'2026-04-04','17:29:20'),(141,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','17:31:49'),(142,5,'Admin Login','Admin logged in',1,'2026-04-04','19:01:32'),(143,5,'Dentist Login','Dentist logged in',1,'2026-04-04','19:01:44'),(144,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','19:01:54'),(145,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','19:02:25'),(146,5,'Dentist Login','Dentist logged in',1,'2026-04-04','19:02:33'),(147,5,'Admin Login','Admin logged in',1,'2026-04-04','19:02:43'),(148,5,'Dentist Login','Dentist logged in',1,'2026-04-04','19:07:02'),(149,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','19:07:36'),(150,5,'Dentist Login','Dentist logged in',1,'2026-04-04','19:11:57'),(151,5,'Admin Login','Admin logged in',1,'2026-04-04','19:12:58'),(152,5,'Dentist Login','Dentist logged in',1,'2026-04-04','19:14:47'),(153,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','19:15:15'),(154,5,'Admin Login','Admin logged in',1,'2026-04-04','19:15:19'),(155,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','19:19:42'),(156,5,'Admin Login','Admin logged in',1,'2026-04-04','19:22:56'),(157,5,'Dentist Login','Dentist logged in',1,'2026-04-04','19:38:06'),(158,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','19:39:39'),(159,5,'Admin Login','Admin logged in',1,'2026-04-04','21:00:18'),(160,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','21:01:34'),(161,5,'Admin Login','Admin logged in',1,'2026-04-04','21:01:44'),(162,5,'Tenant Logout','Tenant logged out',1,'2026-04-04','21:05:20'),(163,5,'Receptionist Login','Receptionist logged in',1,'2026-04-04','21:58:07'),(164,5,'Dentist Login','Dentist logged in',1,'2026-04-04','21:58:49'),(165,9,'Admin Login','Admin logged in',1,'2026-04-05','03:36:16'),(166,5,'Admin Login','Admin logged in',1,'2026-04-05','03:40:37'),(167,5,'Dentist Login','Dentist logged in',1,'2026-04-05','04:00:51'),(168,5,'Admin Login','Admin logged in',1,'2026-04-05','04:05:07'),(169,5,'Admin Login','Admin logged in',1,'2026-04-05','04:08:02'),(170,5,'Dentist Login','Dentist logged in',1,'2026-04-05','04:08:58'),(171,5,'Receptionist Login','Receptionist logged in',1,'2026-04-05','04:49:10'),(172,5,'Admin Login','Admin logged in',1,'2026-04-05','09:05:51'),(173,5,'Receptionist Login','Receptionist logged in',1,'2026-04-05','09:10:37'),(174,5,'Dentist Login','Dentist logged in',1,'2026-04-05','09:20:03'),(175,5,'Admin Login','Admin logged in',1,'2026-04-05','09:21:32'),(176,5,'Dentist Login','Dentist logged in',1,'2026-04-05','09:27:37'),(177,5,'Dentist Login','Dentist logged in',1,'2026-04-05','09:27:37'),(178,5,'Receptionist Login','Receptionist logged in',1,'2026-04-05','09:28:09'),(179,8,'Admin Login','Admin logged in',1,'2026-04-05','15:05:50'),(180,8,'Admin Login','Admin logged in',1,'2026-04-05','15:08:21'),(181,5,'Admin Login','Admin logged in',1,'2026-04-05','15:08:29'),(182,5,'Admin Login','Admin logged in',1,'2026-04-05','15:09:14'),(183,8,'Admin Login','Admin logged in',1,'2026-04-05','15:11:59'),(184,5,'Dentist Login','Dentist logged in',1,'2026-04-05','15:13:40'),(185,8,'Admin Login','Admin logged in',1,'2026-04-05','15:14:56'),(186,8,'Admin Login','Admin logged in',1,'2026-04-05','15:15:30'),(187,5,'Receptionist Login','Receptionist logged in',1,'2026-04-05','15:16:08'),(188,8,'Receptionist Login','Receptionist logged in',1,'2026-04-05','15:16:15'),(189,5,'Admin Login','Admin logged in',1,'2026-04-05','15:32:23'),(190,5,'Admin Login','Admin logged in',1,'2026-04-05','15:32:25'),(191,5,'Admin Login','Admin logged in',1,'2026-04-05','16:44:16'),(192,5,'Receptionist Login','Receptionist logged in',1,'2026-04-05','17:03:01'),(193,5,'Receptionist Login','Receptionist logged in',1,'2026-04-05','17:07:00'),(194,5,'Dentist Login','Dentist logged in',1,'2026-04-05','17:10:05'),(195,5,'Receptionist Login','Receptionist logged in',1,'2026-04-05','17:14:55'),(196,5,'Admin Login','Admin logged in',1,'2026-04-05','17:20:02'),(197,5,'Tenant Logout','Tenant logged out',1,'2026-04-05','17:26:03'),(198,5,'Dentist Login','Dentist logged in',1,'2026-04-05','17:26:14'),(199,5,'Receptionist Login','Receptionist logged in',1,'2026-04-05','17:30:07'),(200,5,'Admin Login','Admin logged in',1,'2026-04-05','17:35:11'),(201,5,'Receptionist Login','Receptionist logged in',1,'2026-04-05','17:51:02'),(202,5,'Receptionist Login','Receptionist logged in',1,'2026-04-05','17:56:12'),(203,5,'Dentist Login','Dentist logged in',1,'2026-04-05','17:58:58'),(204,5,'Admin Login','Admin logged in',1,'2026-04-05','18:20:24'),(205,5,'Admin Login','Admin logged in',1,'2026-04-05','18:48:24'),(206,5,'Admin Login','Admin logged in',1,'2026-04-05','19:18:40'),(207,5,'Appointment Updated','Appointment ID: 1 updated to Pending',1,'2026-04-05','19:19:57'),(208,5,'Tenant Logout','Tenant logged out',1,'2026-04-05','19:27:15'),(209,5,'Dentist Login','Dentist logged in',1,'2026-04-05','19:27:34'),(210,5,'Receptionist Login','Receptionist logged in',1,'2026-04-05','19:30:06'),(211,5,'Admin Login','Admin logged in',1,'2026-04-05','19:34:32'),(212,5,'Admin Login','Admin logged in',1,'2026-04-05','23:11:58'),(213,5,'Admin Login','Admin logged in',1,'2026-04-06','14:56:30'),(214,5,'Appointment Updated','Appointment ID: 1 updated to Completed',1,'2026-04-06','15:13:33'),(215,5,'Tenant Logout','Tenant logged out',1,'2026-04-06','15:27:05'),(216,5,'Admin Login','Admin logged in',1,'2026-04-06','16:03:45'),(217,5,'Admin Login','Admin logged in',1,'2026-04-06','16:07:51'),(218,5,'Tenant Logout','Tenant logged out',1,'2026-04-06','16:15:25'),(219,5,'Receptionist Login','Receptionist logged in',1,'2026-04-06','16:15:39'),(220,5,'Dentist Login','Dentist logged in',1,'2026-04-06','16:20:17'),(221,5,'Receptionist Login','Receptionist logged in',1,'2026-04-06','16:50:21'),(222,5,'Receptionist Login','Receptionist logged in',1,'2026-04-06','17:01:15'),(223,5,'Admin Login','Admin logged in',1,'2026-04-06','17:04:54'),(224,5,'Tenant Logout','Tenant logged out',1,'2026-04-06','17:05:21'),(225,5,'Receptionist Login','Receptionist logged in',1,'2026-04-06','17:05:35'),(226,5,'Admin Login','Admin logged in',1,'2026-04-06','17:05:55'),(227,5,'Appointment Updated','Appointment ID: 1 updated to Cancelled',1,'2026-04-06','17:06:29'),(228,5,'Appointment Updated','Appointment ID: 1 updated to Completed',1,'2026-04-06','17:06:33'),(229,5,'Appointment Updated','Appointment ID: 1 updated to Pending',1,'2026-04-06','17:06:43'),(230,5,'Tenant Logout','Tenant logged out',1,'2026-04-06','17:06:59'),(231,5,'Receptionist Login','Receptionist logged in',1,'2026-04-06','17:07:12'),(232,5,'Receptionist Login','Receptionist logged in',1,'2026-04-06','17:13:57'),(233,5,'Receptionist Login','Receptionist logged in',1,'2026-04-06','18:23:15'),(234,5,'Dentist Login','Dentist logged in',1,'2026-04-06','18:24:12'),(235,5,'Admin Login','Admin logged in',1,'2026-04-06','18:24:24'),(236,5,'Tenant Logout','Tenant logged out',1,'2026-04-06','18:27:09'),(237,5,'Receptionist Login','Receptionist logged in',1,'2026-04-06','18:27:24'),(238,5,'Receptionist Login','Receptionist logged in',1,'2026-04-06','18:47:27'),(239,5,'Dentist Login','Dentist logged in',1,'2026-04-06','18:47:50'),(240,5,'Receptionist Login','Receptionist logged in',1,'2026-04-06','18:53:37'),(241,5,'Admin Login','Admin logged in',1,'2026-04-06','18:56:41'),(242,5,'Tenant Logout','Tenant logged out',1,'2026-04-06','18:57:23'),(243,5,'Receptionist Login','Receptionist logged in',1,'2026-04-06','18:57:34'),(244,5,'Receptionist Login','Receptionist logged in',1,'2026-04-06','19:18:08'),(245,5,'Admin Login','Admin logged in',1,'2026-04-06','19:18:41'),(246,5,'Receptionist Login','Receptionist logged in',1,'2026-04-06','19:29:59'),(247,5,'Dentist Login','Dentist logged in',1,'2026-04-06','19:31:33'),(248,5,'Receptionist Login','Receptionist logged in',1,'2026-04-06','19:46:36'),(249,5,'Receptionist Login','Receptionist logged in',1,'2026-04-06','19:51:01'),(250,5,'Dentist Login','Dentist logged in',1,'2026-04-06','19:53:14'),(251,5,'Admin Login','Admin logged in',1,'2026-04-06','19:54:48'),(252,5,'Tenant Logout','Tenant logged out',1,'2026-04-06','19:58:10'),(253,5,'Receptionist Login','Receptionist logged in',1,'2026-04-06','20:45:42'),(254,5,'Dentist Login','Dentist logged in',1,'2026-04-06','20:50:57'),(255,5,'Admin Login','Admin logged in',1,'2026-04-06','20:53:53'),(256,5,'Tenant Logout','Tenant logged out',1,'2026-04-06','20:55:15'),(257,5,'Receptionist Login','Receptionist logged in',1,'2026-04-07','02:20:35'),(258,5,'Admin Login','Admin logged in',1,'2026-04-07','02:32:21'),(259,5,'Admin Login','Admin logged in',1,'2026-04-07','02:47:09'),(260,5,'Tenant Logout','Tenant logged out',1,'2026-04-07','02:54:05'),(261,5,'Admin Login','Admin logged in',1,'2026-04-07','02:56:28'),(262,9,'Admin Login','Admin logged in',1,'2026-04-07','03:10:49'),(263,9,'Tenant Logout','Tenant logged out',1,'2026-04-07','03:13:08'),(264,9,'Receptionist Login','Receptionist logged in',1,'2026-04-07','03:14:16'),(265,9,'Admin Login','Admin logged in',1,'2026-04-07','03:16:57'),(266,9,'Tenant Logout','Tenant logged out',1,'2026-04-07','03:18:45'),(267,9,'Admin Login','Admin logged in',1,'2026-04-07','03:21:48'),(268,5,'Admin Login','Admin logged in',1,'2026-04-07','03:51:33'),(269,5,'Admin Login','Admin logged in',1,'2026-04-08','11:01:36'),(270,5,'Tenant Logout','Tenant logged out',1,'2026-04-08','11:04:33'),(271,5,'Dentist Login','Dentist logged in',1,'2026-04-08','11:06:27'),(272,5,'Admin Login','Admin logged in',1,'2026-04-08','11:51:08'),(273,5,'Tenant Logout','Tenant logged out',1,'2026-04-08','12:40:52'),(274,5,'Receptionist Login','Receptionist logged in',1,'2026-04-08','12:42:29'),(275,5,'Receptionist Login','Receptionist logged in',1,'2026-04-08','12:50:06'),(276,5,'Admin Login','Admin logged in',1,'2026-04-08','13:27:46'),(277,5,'Receptionist Login','Receptionist logged in',1,'2026-04-08','15:38:47'),(278,5,'Admin Login','Admin logged in',1,'2026-04-09','00:21:30'),(279,5,'Tenant Logout','Tenant logged out',1,'2026-04-09','00:26:51'),(280,5,'Dentist Login','Dentist logged in',1,'2026-04-09','00:27:19'),(281,5,'Receptionist Login','Receptionist logged in',1,'2026-04-09','00:29:05'),(282,5,'Admin Login','Admin logged in',1,'2026-04-09','12:45:33'),(283,5,'Admin Login','Admin logged in',1,'2026-04-09','12:45:59'),(284,5,'Tenant Logout','Tenant logged out',1,'2026-04-09','12:46:37'),(285,5,'Receptionist Login','Receptionist logged in',1,'2026-04-09','12:47:14'),(286,5,'Tenant Logout','Tenant logged out',1,'2026-04-09','12:47:21'),(287,5,'Receptionist Login','Receptionist logged in',1,'2026-04-09','12:47:36'),(288,5,'Tenant Logout','Tenant logged out',1,'2026-04-09','12:48:44'),(289,5,'Receptionist Login','Receptionist logged in',1,'2026-04-09','12:49:52'),(290,5,'Receptionist Login','Receptionist logged in',1,'2026-04-09','14:01:49'),(291,5,'Receptionist Login','Receptionist logged in',1,'2026-04-09','14:02:38'),(292,5,'Receptionist Login','Receptionist logged in',1,'2026-04-09','14:49:58'),(293,5,'Receptionist Login','Receptionist logged in',1,'2026-04-09','16:12:42'),(294,5,'Tenant Logout','Tenant logged out',1,'2026-04-09','16:16:08'),(295,5,'Admin Login','Admin logged in',1,'2026-04-09','16:16:12'),(296,5,'Tenant Logout','Tenant logged out',1,'2026-04-09','16:17:19'),(297,5,'Receptionist Login','Receptionist logged in',1,'2026-04-09','16:22:19');
/*!40000 ALTER TABLE `tenant_activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_configs`
--

DROP TABLE IF EXISTS `tenant_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_configs` (
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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_configs`
--

LOCK TABLES `tenant_configs` WRITE;
/*!40000 ALTER TABLE `tenant_configs` DISABLE KEYS */;
INSERT INTO `tenant_configs` VALUES (1,1,NULL,'#001f3f','Powered by OralSync','Clinic Login','#22c55e','#2563eb',NULL,'2026-04-05 00:33:07','2026-04-05 00:33:07','#ffffff',NULL,NULL),(2,2,NULL,'#001f3f','Powered by OralSync','Clinic Login','#22c55e','#2563eb',NULL,'2026-04-05 00:33:07','2026-04-05 00:33:07','#ffffff',NULL,NULL),(3,3,NULL,'#001f3f','Powered by OralSync','Clinic Login','#22c55e','#2563eb',NULL,'2026-04-05 00:33:07','2026-04-05 00:33:07','#ffffff',NULL,NULL),(4,4,NULL,'#001f3f','Powered by OralSync','Clinic Login','#22c55e','#2563eb',NULL,'2026-04-05 00:33:07','2026-04-05 00:33:07','#ffffff',NULL,NULL),(5,5,NULL,'#001f3f','Powered by OralSync','Clinic Login','#22c55e','#2563eb',NULL,'2026-04-05 00:33:07','2026-04-09 16:29:18','#ffffff','Please sign in to access your clinic portal.',NULL),(6,6,NULL,'#001f3f','Powered by OralSync','Clinic Login','#22c55e','#2563eb',NULL,'2026-04-05 00:33:07','2026-04-05 00:33:07','#ffffff',NULL,NULL),(7,7,NULL,'#001f3f','Powered by OralSync','Clinic Login','#22c55e','#2563eb',NULL,'2026-04-05 00:33:07','2026-04-05 00:33:07','#ffffff',NULL,NULL),(8,8,NULL,'#001f3f','Powered by OralSync','Clinic Login','#22c55e','#2563eb',NULL,'2026-04-05 00:33:07','2026-04-05 00:33:07','#ffffff',NULL,NULL),(18,9,NULL,'#001f3f','Powered by OralSync','Clinic Login','#22c55e','#2563eb',NULL,'2026-04-07 03:18:40','2026-04-09 16:29:46','#ffffff','Please sign in to access your clinic portal.',NULL);
/*!40000 ALTER TABLE `tenant_configs` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_subscription_revenue`
--

LOCK TABLES `tenant_subscription_revenue` WRITE;
/*!40000 ALTER TABLE `tenant_subscription_revenue` DISABLE KEYS */;
INSERT INTO `tenant_subscription_revenue` VALUES (1,8,'startup',124.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(2,7,'startup',124.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(3,6,'startup',124.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(4,5,'startup',124.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(5,4,'startup',124.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(6,3,'startup',124.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(7,2,'startup',124.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(8,1,'startup',124.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(16,8,'startup',6200.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(17,7,'startup',6200.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(18,6,'startup',6200.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(19,5,'startup',6200.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(20,4,'startup',6200.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(21,3,'startup',6200.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(22,2,'startup',6200.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(23,1,'startup',6200.00,'2026-03-01 00:00:00','2026-03-31 00:00:00','paid','2026-03-01 00:00:00','2026-03-01 00:00:00'),(24,1,'startup',124.00,'2026-04-01 00:00:00','2026-04-30 00:00:00','paid','2026-04-30 00:00:00','2026-04-06 13:18:35'),(25,5,'startup',124.00,'2026-04-01 00:00:00','2026-04-30 00:00:00','paid','2026-04-30 00:00:00','2026-04-06 13:18:35'),(26,6,'startup',124.00,'2026-04-01 00:00:00','2026-04-30 00:00:00','paid','2026-04-30 00:00:00','2026-04-06 13:18:35'),(27,8,'startup',124.00,'2026-04-01 00:00:00','2026-04-30 00:00:00','paid','2026-04-30 00:00:00','2026-04-06 13:18:35'),(28,9,'professional',249.00,'2026-04-01 00:00:00','2026-04-30 00:00:00','paid','2026-04-30 00:00:00','2026-04-06 13:18:35');
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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenants`
--

LOCK TABLES `tenants` WRITE;
/*!40000 ALTER TABLE `tenants` DISABLE KEYS */;
INSERT INTO `tenants` VALUES (1,'Yes','Amiel','amielcarlsantos26@gmail.com','$2y$10$5VMWAykcvs3Q8mvDDEd7I.WHP5H6dPMXc.bIITDEAx8uPRXgVfriW',NULL,NULL,'09959079137','#69 E Delos Angeles','Malolos','Bulacan','yes-b26d',NULL,'active','startup','2026-03-26 12:28:05',NULL,NULL,1,'2026-03-15 14:02:19'),(2,'Gold\\\'s Best','Gold Javier','darkagedbat@gmail.com','$2y$12$UwS9GV6BGeWfCp11jdXvfeEJA0/F/cpJU0AXd5Rjv2RH9AIsMXEIe',NULL,NULL,'09477230297','Diliman 1st','San Rafael','Bulacan','gold-s-best-518f',NULL,'inactive','startup','2026-03-26 12:28:05',NULL,NULL,1,'2026-03-16 09:23:25'),(3,'MiniACE','Gabriel Toledo','darkagedbat@gmail.com','$2y$12$KwqsaKh8VTKMeHGvWI/veeeS2oyP7GRQ0TC5i/S2cqexTO0BC255S',NULL,NULL,'09477230297','Brngy. Capihan','San Rafael','Bulacan','miniace-c577',NULL,'inactive','startup','2026-03-26 12:28:05',NULL,NULL,1,'2026-03-16 10:33:59'),(4,'MiniACE2','Gabriel Toledo','darkagedbat@gmail.com','$2y$12$4VtRSJVLE9lERG1qhfdxcOaD6ojhcl.lyNqRm7Ccfyi5DEuvPHphO',NULL,NULL,'09477230297','Brngy. Capihan','San Rafael','Bulacan','miniace2-574a',NULL,'inactive','startup','2026-03-26 12:28:05',NULL,NULL,1,'2026-03-16 10:52:15'),(5,'Radford Clinic','Razz Dela Cruz','darkagedbat@gmail.com','$2y$12$q4Uz4UqQKpHvA6/iECa7OOMOweKxsK/GlZ11IYQYxH/pp8UJhPJ3S',NULL,NULL,'09477230297','Barangay San Roque','San Rafael','Bulacan','radford-clinic-dec9',NULL,'active','startup','2026-03-26 12:28:05',NULL,NULL,1,'2026-03-16 13:03:43'),(6,'Winford\\\'s Medical','Sarado Nah','darkagedbat@gmail.com','$2y$12$AojUzl.0j0TDNrpTtgk4h.uJfHK4yuZAJueKUidAMR6jwWCtKC5Be',NULL,NULL,'09477230297','Maguinao','San Rafael','Bulacan','winford-s-medical-9cf1',NULL,'active','startup','2026-03-26 12:28:05',NULL,NULL,1,'2026-03-16 13:11:10'),(7,'Ace\\\'s Clinic','Raph De Guzman','amielcarlsantos.basc@gmail.com','$2y$12$grUjLRIY3bAYTvdd8vd24e/h0G7gpGt2nG5kVBIpO3jqi9tDHGzG.',NULL,NULL,'09477230297','Subic','Baliuag','Bulacan','ace-s-clinic-29f6',NULL,'inactive','startup','2026-03-26 12:28:05',NULL,NULL,1,'2026-03-16 13:33:22'),(8,'13','Amiel','amielcarlsantos26@gmail.com','$2y$12$YuJ/orf3LSrPXSQcyepld.UG31zFER4hTmWMP67FTZ78g3UuZXYRm',NULL,NULL,'09959079137','#69 E Delos Angeles','Malolos','Bulacan','13-de45',NULL,'active','startup','2026-03-26 12:28:05',NULL,NULL,1,'2026-03-25 15:41:21'),(9,'Rugay Smol','Alden Richards','afton92@sharebot.net','$2y$12$s3ZjvQsTf5e.8CuQMY2bruDjIb0s.ywA8OCRjeaG.ZmLdriu73Zdq',NULL,NULL,'09372413451','Balete Drive','Quezon City','Metro Manila','rugay-smol-22c0',NULL,'active','professional','2026-04-05 03:25:18',NULL,NULL,1,'2026-04-05 03:25:18');
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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,5,'tibsonme','reggie4C@example.com','$2y$12$XoFbflVsXVHB1gFjfi2SEei1T/m1I99JLvGINdIcVW7aWJfJw.0hm','Receptionist',NULL,NULL,'2026-04-04 18:15:13','2026-04-04 18:15:13'),(2,5,'sinsdaOG','reggie4D@example.com','$2y$12$awHyxYAPc02M./7L9KRrhetx/XS5tp.AbaAGY/KNt8GBy20hRA6ce','Dentist','Reggie','Santos','2026-04-04 18:15:13','2026-04-06 14:03:08'),(3,5,'tibsonme2','reggie4E@example.com','$2y$12$G2VhyPUcHq2S.ydK4aPTFeWzy.wtddX17VdiZHo4R5I8HBGT.40tK','Receptionist',NULL,NULL,'2026-04-04 18:15:13','2026-04-04 18:15:13'),(4,5,'adminStaff','reggie4F@test.example.com','$2y$12$TRSdtoxBWePMtv5SGBS1uOj99i82R8crVMSblF0kmG5o0jJddqXaO','Admin',NULL,NULL,'2026-04-04 18:15:13','2026-04-04 18:15:13'),(5,5,'newgal','reggie4G@example.com','$2y$12$G48BQ.X1U8hkFkC3xsFgCuUOq7flCmNsX/ZMNv7KJZIjLLu/oxQxa','Admin','Sandra','Bullock',NULL,NULL),(6,5,'radBud','docock@example.com','$2y$12$Aa022HistjCGKhuKAVHy..Ybk56smsaInY5rd.8oefqM9GRe.3jC2','Dentist','Jim','Gordon',NULL,NULL),(7,8,'Dayami','amielcarlsantos26@icloud.com','$2y$12$MOfdYt2MDZ6THuMMpjinquwAlsrxxVBG0RCLltIhLbsRjpTTDIX0u','Receptionist','Amiel','Carl Santos',NULL,NULL),(8,8,'Dentist1','amielcarlsantos.basc@gmail.com','$2y$12$Ct2aepbd0kmqjvRHcA2A/.4W/aswKYiG1dvlU3oF3KDEMkwRR85pq','Dentist','Amiel','Santos',NULL,NULL),(9,8,'recept','amielcarlsantos@gmail.com','$2y$12$qoWcqq2ENXb7FP7USN8NieL9hcKIN5h3iogfBophqgNbGvk/wdDTW','Receptionist','Amiel','Carl Santos',NULL,NULL),(10,9,'amieluv','receptionist@example.com','$2y$12$hEDjQRWFeHynvio7wYQXb.cwhiUa6Rtvq0arGF0Cuya5SCOB3yKnq','Receptionist','Amiel','Santos',NULL,NULL);
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

-- Dump completed on 2026-04-10  0:37:51
