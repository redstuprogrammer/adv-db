-- MySQL dump 10.13  Distrib 8.0.45, for Win64 (x86_64)
--
-- Host: localhost    Database: oral
-- ------------------------------------------------------
-- Server version	5.5.5-10.4.32-MariaDB

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
-- Table structure for table `tenants`
--

DROP TABLE IF EXISTS `tenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenants` (
  `tenant_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(150) NOT NULL,
  `owner_name` varchar(255) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `password_reset_token` varchar(255) DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `subdomain_slug` varchar(50) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `subscription_tier` varchar(50) DEFAULT 'startup',
  `subscription_start_date` timestamp NULL DEFAULT current_timestamp(),
  `trial_start_date` timestamp NULL DEFAULT NULL,
  `trial_end_date` timestamp NULL DEFAULT NULL,
  `must_change_password` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
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
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-09 22:43:48
