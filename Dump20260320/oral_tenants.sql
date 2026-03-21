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
  `phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `city` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `province` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `subdomain_slug` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('active','inactive','suspended') COLLATE utf8mb4_general_ci DEFAULT 'active',
  `must_change_password` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tenant_id`),
  UNIQUE KEY `subdomain_slug` (`subdomain_slug`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenants`
--

LOCK TABLES `tenants` WRITE;
/*!40000 ALTER TABLE `tenants` DISABLE KEYS */;
INSERT INTO `tenants` VALUES (1,'Yes','Amiel','amielcarlsantos26@gmail.com','$2y$10$5VMWAykcvs3Q8mvDDEd7I.WHP5H6dPMXc.bIITDEAx8uPRXgVfriW','09959079137','#69 E Delos Angeles','Malolos','Bulacan','yes-b26d','active',1,'2026-03-15 14:02:19'),(2,'Gold\\\'s Best','Gold Javier','darkagedbat@gmail.com','$2y$12$UwS9GV6BGeWfCp11jdXvfeEJA0/F/cpJU0AXd5Rjv2RH9AIsMXEIe','09477230297','Diliman 1st','San Rafael','Bulacan','gold-s-best-518f','active',1,'2026-03-16 09:23:25'),(3,'MiniACE','Gabriel Toledo','darkagedbat@gmail.com','$2y$12$KwqsaKh8VTKMeHGvWI/veeeS2oyP7GRQ0TC5i/S2cqexTO0BC255S','09477230297','Brngy. Capihan','San Rafael','Bulacan','miniace-c577','active',1,'2026-03-16 10:33:59'),(4,'MiniACE2','Gabriel Toledo','darkagedbat@gmail.com','$2y$12$4VtRSJVLE9lERG1qhfdxcOaD6ojhcl.lyNqRm7Ccfyi5DEuvPHphO','09477230297','Brngy. Capihan','San Rafael','Bulacan','miniace2-574a','active',1,'2026-03-16 10:52:15'),(5,'Radford Clinic','Razz Dela Cruz','darkagedbat@gmail.com','$2y$12$QNfYPcM3lGcq0TyAIDcD/.l1mMT/M4feH97QkVGY2opIWOW6/vNyO','09477230297','Barangay San Roque','San Rafael','Bulacan','radford-clinic-dec9','active',1,'2026-03-16 13:03:43'),(6,'Winford\\\'s Medical','Sarado Nah','darkagedbat@gmail.com','$2y$12$AojUzl.0j0TDNrpTtgk4h.uJfHK4yuZAJueKUidAMR6jwWCtKC5Be','09477230297','Maguinao','San Rafael','Bulacan','winford-s-medical-9cf1','active',1,'2026-03-16 13:11:10'),(7,'Ace\\\'s Clinic','Raph De Guzman','amielcarlsantos.basc@gmail.com','$2y$12$grUjLRIY3bAYTvdd8vd24e/h0G7gpGt2nG5kVBIpO3jqi9tDHGzG.','09477230297','Subic','Baliuag','Bulacan','ace-s-clinic-29f6','active',1,'2026-03-16 13:33:22');
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

-- Dump completed on 2026-03-20 14:35:47
