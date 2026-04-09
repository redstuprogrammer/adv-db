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
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin','Receptionist','Dentist') NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
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

-- Dump completed on 2026-04-09 22:43:48
