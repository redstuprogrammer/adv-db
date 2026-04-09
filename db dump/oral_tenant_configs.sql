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
-- Table structure for table `tenant_configs`
--

DROP TABLE IF EXISTS `tenant_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_configs` (
  `config_id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `brand_logo_path` varchar(255) DEFAULT NULL,
  `brand_bg_color` varchar(7) DEFAULT '#001f3f' COMMENT 'Brand card background - Default Navy Blue',
  `brand_subtitle` varchar(255) DEFAULT 'Powered by OralSync',
  `login_title` varchar(255) DEFAULT 'Clinic Login',
  `primary_btn_color` varchar(7) DEFAULT '#22c55e' COMMENT 'Sign In button - Default Green',
  `link_color` varchar(7) DEFAULT '#2563eb',
  `brand_bg_image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `brand_text_color` varchar(7) DEFAULT '#ffffff',
  `login_description` text DEFAULT NULL,
  PRIMARY KEY (`config_id`),
  UNIQUE KEY `unique_tenant_config` (`tenant_id`),
  CONSTRAINT `fk_config_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_configs`
--

LOCK TABLES `tenant_configs` WRITE;
/*!40000 ALTER TABLE `tenant_configs` DISABLE KEYS */;
INSERT INTO `tenant_configs` VALUES (1,1,NULL,'#001f3f','Powered by OralSync','Clinic Login','#22c55e','#2563eb',NULL,'2026-04-05 00:33:07','2026-04-05 00:33:07','#ffffff',NULL),(2,2,NULL,'#001f3f','Powered by OralSync','Clinic Login','#22c55e','#2563eb',NULL,'2026-04-05 00:33:07','2026-04-05 00:33:07','#ffffff',NULL),(3,3,NULL,'#001f3f','Powered by OralSync','Clinic Login','#22c55e','#2563eb',NULL,'2026-04-05 00:33:07','2026-04-05 00:33:07','#ffffff',NULL),(4,4,NULL,'#001f3f','Powered by OralSync','Clinic Login','#22c55e','#2563eb',NULL,'2026-04-05 00:33:07','2026-04-05 00:33:07','#ffffff',NULL),(5,5,'assets/uploads/tenants/5/brand_logo.png','#001f3f','Powered by OralSync','Clinic Login','#22c55e','#2563eb','assets/uploads/tenants/5/brand_bg_image.jpg','2026-04-05 00:33:07','2026-04-07 02:53:33','#ffffff','Please sign in to access your clinic portal.'),(6,6,NULL,'#001f3f','Powered by OralSync','Clinic Login','#22c55e','#2563eb',NULL,'2026-04-05 00:33:07','2026-04-05 00:33:07','#ffffff',NULL),(7,7,NULL,'#001f3f','Powered by OralSync','Clinic Login','#22c55e','#2563eb',NULL,'2026-04-05 00:33:07','2026-04-05 00:33:07','#ffffff',NULL),(8,8,NULL,'#001f3f','Powered by OralSync','Clinic Login','#22c55e','#2563eb',NULL,'2026-04-05 00:33:07','2026-04-05 00:33:07','#ffffff',NULL),(18,9,NULL,'#001f3f','Powered by OralSync','Clinic Login','#22c55e','#2563eb','assets/uploads/tenants/9/brand_bg_image.jpg','2026-04-07 03:18:40','2026-04-07 03:18:40','#ffffff','Please sign in to access your clinic portal.');
/*!40000 ALTER TABLE `tenant_configs` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-09 22:43:47
