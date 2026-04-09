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
-- Table structure for table `tenant_subscription_revenue`
--

DROP TABLE IF EXISTS `tenant_subscription_revenue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_subscription_revenue` (
  `revenue_id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `subscription_tier` varchar(50) DEFAULT 'startup',
  `amount` decimal(10,2) NOT NULL,
  `billing_period_start` timestamp NULL DEFAULT current_timestamp(),
  `billing_period_end` timestamp NULL DEFAULT NULL,
  `status` varchar(50) DEFAULT 'paid',
  `payment_date` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
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
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-09 22:43:48
