-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: ncc_database
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
-- Table structure for table `access_privilege`
--

DROP TABLE IF EXISTS `access_privilege`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `access_privilege` (
  `privilege_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `module_name` varchar(100) NOT NULL,
  `access_level` varchar(30) NOT NULL,
  `status` varchar(30) NOT NULL,
  PRIMARY KEY (`privilege_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `access_privilege`
--

LOCK TABLES `access_privilege` WRITE;
/*!40000 ALTER TABLE `access_privilege` DISABLE KEYS */;
/*!40000 ALTER TABLE `access_privilege` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `account_reconciliation`
--

DROP TABLE IF EXISTS `account_reconciliation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_reconciliation` (
  `reconciliation_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_number` varchar(50) NOT NULL,
  `reconciliation_date` date NOT NULL,
  `system_balance` decimal(12,2) NOT NULL,
  `bank_balance` decimal(12,2) NOT NULL,
  `status` varchar(30) NOT NULL,
  `remarks` text DEFAULT NULL,
  `performed_by` int(11) NOT NULL,
  PRIMARY KEY (`reconciliation_id`),
  KEY `performed_by` (`performed_by`),
  CONSTRAINT `account_reconciliation_ibfk_1` FOREIGN KEY (`performed_by`) REFERENCES `employee` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `account_reconciliation`
--

LOCK TABLES `account_reconciliation` WRITE;
/*!40000 ALTER TABLE `account_reconciliation` DISABLE KEYS */;
/*!40000 ALTER TABLE `account_reconciliation` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `announcement`
--

DROP TABLE IF EXISTS `announcement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcement` (
  `announcement_id` int(11) NOT NULL AUTO_INCREMENT,
  `sent_by` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`announcement_id`),
  KEY `sent_by` (`sent_by`),
  KEY `receiver_id` (`receiver_id`),
  CONSTRAINT `announcement_ibfk_1` FOREIGN KEY (`sent_by`) REFERENCES `employee` (`employee_id`),
  CONSTRAINT `announcement_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `employee` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcement`
--

LOCK TABLES `announcement` WRITE;
/*!40000 ALTER TABLE `announcement` DISABLE KEYS */;
/*!40000 ALTER TABLE `announcement` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `clock_in` time DEFAULT NULL,
  `clock_out` time DEFAULT NULL,
  `overtime_hours` decimal(5,2) NOT NULL,
  PRIMARY KEY (`attendance_id`),
  KEY `employee_id` (`employee_id`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`),
  CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `employee` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance`
--

LOCK TABLES `attendance` WRITE;
/*!40000 ALTER TABLE `attendance` DISABLE KEYS */;
INSERT INTO `attendance` VALUES (5,11,8,'2026-07-15','08:00:00','19:00:00',3.00);
/*!40000 ALTER TABLE `attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_table` varchar(100) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `timestamp` datetime NOT NULL,
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_account` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_log`
--

LOCK TABLES `audit_log` WRITE;
/*!40000 ALTER TABLE `audit_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `backup_record`
--

DROP TABLE IF EXISTS `backup_record`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_record` (
  `backup_id` int(11) NOT NULL AUTO_INCREMENT,
  `backup_type` varchar(30) NOT NULL,
  `file_path` varchar(225) DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `note` text DEFAULT NULL,
  PRIMARY KEY (`backup_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `backup_record`
--

LOCK TABLES `backup_record` WRITE;
/*!40000 ALTER TABLE `backup_record` DISABLE KEYS */;
/*!40000 ALTER TABLE `backup_record` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `budget_plan`
--

DROP TABLE IF EXISTS `budget_plan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `budget_plan` (
  `budget_id` int(11) NOT NULL AUTO_INCREMENT,
  `budget_purpose` varchar(150) NOT NULL,
  `period` varchar(20) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `used_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `prepared_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `status` varchar(30) NOT NULL,
  PRIMARY KEY (`budget_id`),
  KEY `prepared_by` (`prepared_by`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `budget_plan_ibfk_1` FOREIGN KEY (`prepared_by`) REFERENCES `employee` (`employee_id`),
  CONSTRAINT `budget_plan_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `employee` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `budget_plan`
--

LOCK TABLES `budget_plan` WRITE;
/*!40000 ALTER TABLE `budget_plan` DISABLE KEYS */;
/*!40000 ALTER TABLE `budget_plan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `complaint`
--

DROP TABLE IF EXISTS `complaint`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `complaint` (
  `complaint_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `officer_id` int(11) DEFAULT NULL,
  `description` text NOT NULL,
  `status` varchar(30) NOT NULL,
  `created_date` datetime NOT NULL,
  `resolved_date` datetime DEFAULT NULL,
  `escalated_to` int(11) DEFAULT NULL,
  PRIMARY KEY (`complaint_id`),
  KEY `customer_id` (`customer_id`),
  KEY `officer_id` (`officer_id`),
  KEY `escalated_to` (`escalated_to`),
  CONSTRAINT `complaint_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`),
  CONSTRAINT `complaint_ibfk_2` FOREIGN KEY (`officer_id`) REFERENCES `employee` (`employee_id`),
  CONSTRAINT `complaint_ibfk_3` FOREIGN KEY (`escalated_to`) REFERENCES `employee` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `complaint`
--

LOCK TABLES `complaint` WRITE;
/*!40000 ALTER TABLE `complaint` DISABLE KEYS */;
/*!40000 ALTER TABLE `complaint` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer`
--

DROP TABLE IF EXISTS `customer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer` (
  `customer_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `contact_no` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `loyalty_points` int(11) NOT NULL,
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer`
--

LOCK TABLES `customer` WRITE;
/*!40000 ALTER TABLE `customer` DISABLE KEYS */;
/*!40000 ALTER TABLE `customer` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery`
--

DROP TABLE IF EXISTS `delivery`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `delivery` (
  `delivery_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `scheduled_date` date NOT NULL,
  `route_details` text DEFAULT NULL,
  `status` varchar(30) NOT NULL,
  `transport_cost` decimal(10,2) NOT NULL,
  PRIMARY KEY (`delivery_id`),
  UNIQUE KEY `order_id` (`order_id`),
  KEY `driver_id` (`driver_id`),
  KEY `vehicle_id` (`vehicle_id`),
  CONSTRAINT `delivery_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `sales_order` (`order_id`),
  CONSTRAINT `delivery_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `employee` (`employee_id`),
  CONSTRAINT `delivery_ibfk_3` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicle` (`vehicle_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery`
--

LOCK TABLES `delivery` WRITE;
/*!40000 ALTER TABLE `delivery` DISABLE KEYS */;
/*!40000 ALTER TABLE `delivery` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery_issue`
--

DROP TABLE IF EXISTS `delivery_issue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `delivery_issue` (
  `issue_id` int(11) NOT NULL AUTO_INCREMENT,
  `delivery_id` int(11) NOT NULL,
  `reported_by` int(11) NOT NULL,
  `issue_description` text NOT NULL,
  `issue_date` datetime NOT NULL,
  PRIMARY KEY (`issue_id`),
  KEY `delivery_id` (`delivery_id`),
  KEY `reported_by` (`reported_by`),
  CONSTRAINT `delivery_issue_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `delivery` (`delivery_id`),
  CONSTRAINT `delivery_issue_ibfk_2` FOREIGN KEY (`reported_by`) REFERENCES `employee` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_issue`
--

LOCK TABLES `delivery_issue` WRITE;
/*!40000 ALTER TABLE `delivery_issue` DISABLE KEYS */;
/*!40000 ALTER TABLE `delivery_issue` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery_proof`
--

DROP TABLE IF EXISTS `delivery_proof`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `delivery_proof` (
  `proof_id` int(11) NOT NULL AUTO_INCREMENT,
  `delivery_id` int(11) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `uploaded_at` datetime NOT NULL,
  `received_by_name` varchar(100) NOT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`proof_id`),
  UNIQUE KEY `delivery_id` (`delivery_id`),
  CONSTRAINT `delivery_proof_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `delivery` (`delivery_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_proof`
--

LOCK TABLES `delivery_proof` WRITE;
/*!40000 ALTER TABLE `delivery_proof` DISABLE KEYS */;
/*!40000 ALTER TABLE `delivery_proof` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `department_target`
--

DROP TABLE IF EXISTS `department_target`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `department_target` (
  `target_id` int(11) NOT NULL AUTO_INCREMENT,
  `department` varchar(100) NOT NULL,
  `target_type` varchar(100) NOT NULL,
  `target_value` decimal(12,2) NOT NULL,
  `deadline` date NOT NULL,
  `status` varchar(30) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  PRIMARY KEY (`target_id`),
  KEY `assigned_by` (`assigned_by`),
  CONSTRAINT `department_target_ibfk_1` FOREIGN KEY (`assigned_by`) REFERENCES `employee` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `department_target`
--

LOCK TABLES `department_target` WRITE;
/*!40000 ALTER TABLE `department_target` DISABLE KEYS */;
/*!40000 ALTER TABLE `department_target` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `discount_policy`
--

DROP TABLE IF EXISTS `discount_policy`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `discount_policy` (
  `policy_id` int(11) NOT NULL AUTO_INCREMENT,
  `policy_name` varchar(150) NOT NULL,
  `discount_rate` decimal(5,2) NOT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `status` varchar(30) NOT NULL,
  `proposed_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`policy_id`),
  KEY `proposed_by` (`proposed_by`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `discount_policy_ibfk_1` FOREIGN KEY (`proposed_by`) REFERENCES `employee` (`employee_id`),
  CONSTRAINT `discount_policy_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `employee` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `discount_policy`
--

LOCK TABLES `discount_policy` WRITE;
/*!40000 ALTER TABLE `discount_policy` DISABLE KEYS */;
/*!40000 ALTER TABLE `discount_policy` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee`
--

DROP TABLE IF EXISTS `employee`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee` (
  `employee_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `nic` varchar(20) NOT NULL,
  `contact_no` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `job_title` varchar(100) NOT NULL,
  `hire_date` date NOT NULL,
  `base_salary` decimal(10,2) NOT NULL,
  `employment_status` varchar(30) NOT NULL,
  PRIMARY KEY (`employee_id`),
  UNIQUE KEY `nic` (`nic`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `employee_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_account` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee`
--

LOCK TABLES `employee` WRITE;
/*!40000 ALTER TABLE `employee` DISABLE KEYS */;
INSERT INTO `employee` VALUES (1,1,'Nimal Perera','6842719350','0712-345678',NULL,'driver','2025-03-12',75000.00,'active'),(2,2,'Kasun Fernando','3975061824','0723-456789',NULL,'driver','2025-02-15',75000.00,'active'),(3,3,'Sachin Jayasinghe','8251946370','0726-147258',NULL,'driver','2025-03-25',75000.00,'active'),(4,4,'Sachini Perera','4719283056','0745-678901',NULL,'order_processing_officer','2025-03-25',80000.00,'active'),(5,5,'Kavindi Silva','9362057184','0756-789012',NULL,'order_processing_officer','2025-03-28',80000.00,'active'),(6,6,'Dinithi Jayasinghe','7138964205','0715-024689',NULL,'customer_relation_manager','2025-03-20',82000.00,'active'),(7,7,'Hiruni Gunawardena','2587416093','0703-913578',NULL,'customer_relation_manager','2025-03-19',82000.00,'active'),(8,8,'Imran Zakhaev','8624071953','0767-890123',NULL,'supervisor','2025-03-09',85000.00,'active'),(9,9,'Tharushi Bandara','5492738160','0778-901234',NULL,'financial_officer','2025-02-18',85000.00,'active'),(10,10,'Ravindu Madushanka','3159682740','0789-012345',NULL,'inventory_manager','2025-04-02',80000.00,'active'),(11,11,'Dinuka Herath','7941526308','0701-123456',NULL,'distribution_manager','2025-04-03',80000.00,'active'),(12,12,'Supun Karunaratne','6283094715','0724-345678',NULL,'system_admin','2025-04-03',100000.00,'active'),(13,13,'senuni','4578162930','0779-890123',NULL,'ceo_head_manager','2025-04-01',250000.00,'active'),(14,NULL,'Kishan Tharmalingam','2364958071','0781-901234',NULL,'cleaning','2025-05-03',50000.00,'active'),(15,NULL,'Yashoda Senanayake','9813745206','0713-234567',NULL,'cleaning','2025-05-02',50000.00,'active'),(16,NULL,'Pasindu Ekanayake','8753206941','0747-468023',NULL,'security','2025-05-04',70000.00,'active'),(17,NULL,'Akila Samarasinghe','4578162931','5190472863',NULL,'security','2025-05-04',70000.00,'active'),(18,NULL,'Malith Rajapaksha','9564381207','0702-012345',NULL,'packing','2025-04-06',65000.00,'active'),(19,NULL,'Rizwan Ahamed','3942178650','0714-135790',NULL,'packing','2025-04-06',65000.00,'active'),(20,NULL,'Kavindu Dissanayake','7285063194','0725-246801',NULL,'packing','2025-04-09',65000.00,'active'),(21,NULL,'Anushiya Rajendran','6439817520','0758-579134',NULL,'packing','2025-04-29',65000.00,'active');
/*!40000 ALTER TABLE `employee` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expansion_plan`
--

DROP TABLE IF EXISTS `expansion_plan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expansion_plan` (
  `plan_id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_title` varchar(150) NOT NULL,
  `estimated_cost` decimal(12,2) NOT NULL,
  `submitted_date` date NOT NULL,
  `status` varchar(30) NOT NULL,
  `proposed_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`plan_id`),
  KEY `proposed_by` (`proposed_by`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `expansion_plan_ibfk_1` FOREIGN KEY (`proposed_by`) REFERENCES `employee` (`employee_id`),
  CONSTRAINT `expansion_plan_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `employee` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expansion_plan`
--

LOCK TABLES `expansion_plan` WRITE;
/*!40000 ALTER TABLE `expansion_plan` DISABLE KEYS */;
/*!40000 ALTER TABLE `expansion_plan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `financial_record`
--

DROP TABLE IF EXISTS `financial_record`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `financial_record` (
  `record_id` int(11) NOT NULL AUTO_INCREMENT,
  `recorded_by` int(11) NOT NULL,
  `related_payment_id` int(11) DEFAULT NULL,
  `supplier_payment_id` int(11) DEFAULT NULL,
  `type` varchar(30) NOT NULL,
  `category` varchar(60) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` text DEFAULT NULL,
  `record_date` date NOT NULL,
  `tax_type` varchar(30) DEFAULT NULL,
  `tax_amount` decimal(10,2) NOT NULL,
  PRIMARY KEY (`record_id`),
  KEY `recorded_by` (`recorded_by`),
  KEY `related_payment_id` (`related_payment_id`),
  KEY `supplier_payment_id` (`supplier_payment_id`),
  CONSTRAINT `financial_record_ibfk_1` FOREIGN KEY (`recorded_by`) REFERENCES `employee` (`employee_id`),
  CONSTRAINT `financial_record_ibfk_2` FOREIGN KEY (`related_payment_id`) REFERENCES `payment` (`payment_id`),
  CONSTRAINT `financial_record_ibfk_3` FOREIGN KEY (`supplier_payment_id`) REFERENCES `supplier_payment` (`supplier_payment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `financial_record`
--

LOCK TABLES `financial_record` WRITE;
/*!40000 ALTER TABLE `financial_record` DISABLE KEYS */;
/*!40000 ALTER TABLE `financial_record` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fuel_usage`
--

DROP TABLE IF EXISTS `fuel_usage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fuel_usage` (
  `fuel_id` int(11) NOT NULL AUTO_INCREMENT,
  `delivery_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `fuel_date` date NOT NULL,
  `liters` decimal(6,2) NOT NULL,
  `cost` decimal(10,2) NOT NULL,
  `odometer_reading` int(11) DEFAULT NULL,
  PRIMARY KEY (`fuel_id`),
  KEY `delivery_id` (`delivery_id`),
  KEY `driver_id` (`driver_id`),
  CONSTRAINT `fuel_usage_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `delivery` (`delivery_id`),
  CONSTRAINT `fuel_usage_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `employee` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fuel_usage`
--

LOCK TABLES `fuel_usage` WRITE;
/*!40000 ALTER TABLE `fuel_usage` DISABLE KEYS */;
/*!40000 ALTER TABLE `fuel_usage` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `internal_email`
--

DROP TABLE IF EXISTS `internal_email`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `internal_email` (
  `email_id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `subject` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `sent_at` datetime NOT NULL,
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`email_id`),
  KEY `idx_internal_email_recipient` (`recipient_id`,`sent_at`),
  KEY `idx_internal_email_sender` (`sender_id`,`sent_at`),
  CONSTRAINT `internal_email_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `user_account` (`user_id`),
  CONSTRAINT `internal_email_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `user_account` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `internal_email`
--

LOCK TABLES `internal_email` WRITE;
/*!40000 ALTER TABLE `internal_email` DISABLE KEYS */;
/*!40000 ALTER TABLE `internal_email` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice`
--

DROP TABLE IF EXISTS `invoice`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice` (
  `invoice_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `issue_date` date NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_status` varchar(30) NOT NULL,
  PRIMARY KEY (`invoice_id`),
  UNIQUE KEY `order_id` (`order_id`),
  CONSTRAINT `invoice_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `sales_order` (`order_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice`
--

LOCK TABLES `invoice` WRITE;
/*!40000 ALTER TABLE `invoice` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoice` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_request`
--

DROP TABLE IF EXISTS `leave_request`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_request` (
  `leave_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `status` varchar(30) NOT NULL,
  `requested_date` datetime NOT NULL,
  PRIMARY KEY (`leave_id`),
  KEY `employee_id` (`employee_id`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `leave_request_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`),
  CONSTRAINT `leave_request_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `employee` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_request`
--

LOCK TABLES `leave_request` WRITE;
/*!40000 ALTER TABLE `leave_request` DISABLE KEYS */;
/*!40000 ALTER TABLE `leave_request` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_history`
--

DROP TABLE IF EXISTS `login_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_history` (
  `login_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `login_time` datetime NOT NULL,
  `logout_time` datetime DEFAULT NULL,
  PRIMARY KEY (`login_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `login_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_account` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_history`
--

LOCK TABLES `login_history` WRITE;
/*!40000 ALTER TABLE `login_history` DISABLE KEYS */;
INSERT INTO `login_history` VALUES (6,1,'2026-07-16 02:04:49','2026-07-16 02:05:11'),(7,4,'2026-07-16 02:05:26','2026-07-16 02:06:44'),(8,6,'2026-07-16 02:06:58','2026-07-16 02:07:10'),(9,8,'2026-07-16 02:07:26','2026-07-16 02:07:33'),(10,9,'2026-07-16 02:08:15','2026-07-16 02:08:37'),(11,10,'2026-07-16 02:08:56','2026-07-16 02:09:13'),(12,12,'2026-07-16 02:09:28','2026-07-16 02:10:56'),(13,13,'2026-07-16 02:11:06',NULL),(14,1,'2026-07-16 10:05:25','2026-07-16 10:12:19'),(15,8,'2026-07-16 10:12:30','2026-07-16 11:05:07'),(16,1,'2026-07-16 11:09:09','2026-07-16 11:11:43'),(17,4,'2026-07-16 11:12:06','2026-07-16 11:14:36'),(18,6,'2026-07-16 11:15:02','2026-07-16 11:17:45'),(19,8,'2026-07-16 11:18:07','2026-07-16 11:59:26'),(20,1,'2026-07-16 11:27:40',NULL),(21,1,'2026-07-16 11:34:02',NULL),(22,1,'2026-07-16 12:00:25','2026-07-16 12:03:07'),(23,13,'2026-07-16 12:03:41','2026-07-16 12:16:14'),(24,13,'2026-07-16 12:16:36','2026-07-16 12:17:55'),(25,13,'2026-07-16 12:18:06','2026-07-16 12:18:14'),(26,8,'2026-07-16 12:18:28','2026-07-16 12:18:54'),(27,13,'2026-07-16 12:19:02','2026-07-16 12:19:17'),(28,8,'2026-07-16 12:19:32','2026-07-16 12:58:03'),(29,8,'2026-07-16 13:06:22','2026-07-16 13:23:26'),(30,8,'2026-07-16 14:54:07',NULL);
/*!40000 ALTER TABLE `login_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_setting`
--

DROP TABLE IF EXISTS `notification_setting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_setting` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `notification_type` varchar(100) NOT NULL,
  `receiver_role` varchar(50) NOT NULL,
  `channel` varchar(30) NOT NULL,
  `status` varchar(30) NOT NULL,
  PRIMARY KEY (`notification_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_setting`
--

LOCK TABLES `notification_setting` WRITE;
/*!40000 ALTER TABLE `notification_setting` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_setting` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_item`
--

DROP TABLE IF EXISTS `order_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_item` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `line_total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`item_id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `order_item_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `sales_order` (`order_id`),
  CONSTRAINT `order_item_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_item`
--

LOCK TABLES `order_item` WRITE;
/*!40000 ALTER TABLE `order_item` DISABLE KEYS */;
/*!40000 ALTER TABLE `order_item` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment`
--

DROP TABLE IF EXISTS `payment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `received_by` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(30) NOT NULL,
  `payment_date` datetime NOT NULL,
  `payment_status` varchar(30) NOT NULL,
  PRIMARY KEY (`payment_id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `customer_id` (`customer_id`),
  KEY `received_by` (`received_by`),
  CONSTRAINT `payment_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoice` (`invoice_id`),
  CONSTRAINT `payment_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`),
  CONSTRAINT `payment_ibfk_3` FOREIGN KEY (`received_by`) REFERENCES `employee` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment`
--

LOCK TABLES `payment` WRITE;
/*!40000 ALTER TABLE `payment` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll`
--

DROP TABLE IF EXISTS `payroll`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payroll` (
  `payroll_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `generated_by` int(11) NOT NULL,
  `period` varchar(20) NOT NULL,
  `base_salary` decimal(10,2) NOT NULL,
  `overtime_pay` decimal(10,2) NOT NULL,
  `deductions` decimal(10,2) NOT NULL,
  `net_pay` decimal(10,2) NOT NULL,
  `generated_date` datetime NOT NULL,
  PRIMARY KEY (`payroll_id`),
  KEY `employee_id` (`employee_id`),
  KEY `generated_by` (`generated_by`),
  CONSTRAINT `payroll_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`),
  CONSTRAINT `payroll_ibfk_2` FOREIGN KEY (`generated_by`) REFERENCES `employee` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll`
--

LOCK TABLES `payroll` WRITE;
/*!40000 ALTER TABLE `payroll` DISABLE KEYS */;
/*!40000 ALTER TABLE `payroll` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `performance_review`
--

DROP TABLE IF EXISTS `performance_review`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `performance_review` (
  `performance_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `reviewed_by` int(11) NOT NULL,
  `review_date` date NOT NULL,
  `rating` int(11) NOT NULL,
  `status` varchar(30) NOT NULL,
  `comments` text DEFAULT NULL,
  PRIMARY KEY (`performance_id`),
  KEY `employee_id` (`employee_id`),
  KEY `reviewed_by` (`reviewed_by`),
  CONSTRAINT `performance_review_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`),
  CONSTRAINT `performance_review_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `employee` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `performance_review`
--

LOCK TABLES `performance_review` WRITE;
/*!40000 ALTER TABLE `performance_review` DISABLE KEYS */;
INSERT INTO `performance_review` VALUES (2,1,8,'2026-07-10',4,'good','need to improve');
/*!40000 ALTER TABLE `performance_review` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product`
--

DROP TABLE IF EXISTS `product`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product` (
  `product_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_name` varchar(100) NOT NULL,
  `category` varchar(60) DEFAULT NULL,
  `selling_price` decimal(10,2) NOT NULL,
  `min_stock_level` int(11) NOT NULL,
  PRIMARY KEY (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product`
--

LOCK TABLES `product` WRITE;
/*!40000 ALTER TABLE `product` DISABLE KEYS */;
/*!40000 ALTER TABLE `product` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promotional_notification`
--

DROP TABLE IF EXISTS `promotional_notification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `promotional_notification` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `customer_group` varchar(30) NOT NULL,
  `sent_by` int(11) NOT NULL,
  `sent_at` datetime NOT NULL,
  PRIMARY KEY (`notification_id`),
  KEY `sent_by` (`sent_by`),
  CONSTRAINT `promotional_notification_ibfk_1` FOREIGN KEY (`sent_by`) REFERENCES `employee` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promotional_notification`
--

LOCK TABLES `promotional_notification` WRITE;
/*!40000 ALTER TABLE `promotional_notification` DISABLE KEYS */;
/*!40000 ALTER TABLE `promotional_notification` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_item`
--

DROP TABLE IF EXISTS `purchase_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_item` (
  `purchase_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `line_total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`purchase_item_id`),
  KEY `purchase_id` (`purchase_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `purchase_item_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchase_order` (`purchase_id`),
  CONSTRAINT `purchase_item_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_item`
--

LOCK TABLES `purchase_item` WRITE;
/*!40000 ALTER TABLE `purchase_item` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_item` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_order`
--

DROP TABLE IF EXISTS `purchase_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_order` (
  `purchase_id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `request_date` date NOT NULL,
  `approval_status` varchar(30) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `expected_date` date DEFAULT NULL,
  PRIMARY KEY (`purchase_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `requested_by` (`requested_by`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `purchase_order_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`),
  CONSTRAINT `purchase_order_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `employee` (`employee_id`),
  CONSTRAINT `purchase_order_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `employee` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_order`
--

LOCK TABLES `purchase_order` WRITE;
/*!40000 ALTER TABLE `purchase_order` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_order` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_order`
--

DROP TABLE IF EXISTS `sales_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_order` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `officer_id` int(11) NOT NULL,
  `order_date` datetime NOT NULL,
  `status` varchar(30) NOT NULL,
  `discount_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `is_credit` tinyint(1) NOT NULL,
  `credit_approved_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`order_id`),
  KEY `customer_id` (`customer_id`),
  KEY `officer_id` (`officer_id`),
  KEY `credit_approved_by` (`credit_approved_by`),
  CONSTRAINT `sales_order_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`),
  CONSTRAINT `sales_order_ibfk_2` FOREIGN KEY (`officer_id`) REFERENCES `employee` (`employee_id`),
  CONSTRAINT `sales_order_ibfk_3` FOREIGN KEY (`credit_approved_by`) REFERENCES `employee` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_order`
--

LOCK TABLES `sales_order` WRITE;
/*!40000 ALTER TABLE `sales_order` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_order` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_batch`
--

DROP TABLE IF EXISTS `stock_batch`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_batch` (
  `batch_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `received_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `original_quantity` int(11) NOT NULL,
  `current_quantity` int(11) NOT NULL,
  `status` varchar(30) NOT NULL,
  PRIMARY KEY (`batch_id`),
  KEY `product_id` (`product_id`),
  KEY `supplier_id` (`supplier_id`),
  CONSTRAINT `stock_batch_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`),
  CONSTRAINT `stock_batch_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_batch`
--

LOCK TABLES `stock_batch` WRITE;
/*!40000 ALTER TABLE `stock_batch` DISABLE KEYS */;
/*!40000 ALTER TABLE `stock_batch` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_movement`
--

DROP TABLE IF EXISTS `stock_movement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_movement` (
  `movement_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `movement_type` varchar(30) NOT NULL,
  `quantity` int(11) NOT NULL,
  `movement_date` datetime NOT NULL,
  `recorded_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`movement_id`),
  KEY `product_id` (`product_id`),
  KEY `batch_id` (`batch_id`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `stock_movement_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`),
  CONSTRAINT `stock_movement_ibfk_2` FOREIGN KEY (`batch_id`) REFERENCES `stock_batch` (`batch_id`),
  CONSTRAINT `stock_movement_ibfk_3` FOREIGN KEY (`recorded_by`) REFERENCES `employee` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_movement`
--

LOCK TABLES `stock_movement` WRITE;
/*!40000 ALTER TABLE `stock_movement` DISABLE KEYS */;
/*!40000 ALTER TABLE `stock_movement` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier`
--

DROP TABLE IF EXISTS `supplier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supplier` (
  `supplier_id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_name` varchar(100) NOT NULL,
  `contact_no` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`supplier_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier`
--

LOCK TABLES `supplier` WRITE;
/*!40000 ALTER TABLE `supplier` DISABLE KEYS */;
/*!40000 ALTER TABLE `supplier` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier_payment`
--

DROP TABLE IF EXISTS `supplier_payment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supplier_payment` (
  `supplier_payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `purchase_id` int(11) DEFAULT NULL,
  `paid_by` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` datetime NOT NULL,
  `status` varchar(30) NOT NULL,
  PRIMARY KEY (`supplier_payment_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `purchase_id` (`purchase_id`),
  KEY `paid_by` (`paid_by`),
  CONSTRAINT `supplier_payment_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`),
  CONSTRAINT `supplier_payment_ibfk_2` FOREIGN KEY (`purchase_id`) REFERENCES `purchase_order` (`purchase_id`),
  CONSTRAINT `supplier_payment_ibfk_3` FOREIGN KEY (`paid_by`) REFERENCES `employee` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_payment`
--

LOCK TABLES `supplier_payment` WRITE;
/*!40000 ALTER TABLE `supplier_payment` DISABLE KEYS */;
/*!40000 ALTER TABLE `supplier_payment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_error`
--

DROP TABLE IF EXISTS `system_error`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_error` (
  `error_id` int(11) NOT NULL AUTO_INCREMENT,
  `error_date` datetime NOT NULL,
  `error_type` varchar(100) NOT NULL,
  `error_message` text NOT NULL,
  `status` varchar(30) NOT NULL,
  PRIMARY KEY (`error_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_error`
--

LOCK TABLES `system_error` WRITE;
/*!40000 ALTER TABLE `system_error` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_error` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_setting`
--

DROP TABLE IF EXISTS `system_setting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_setting` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_name` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(30) NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`setting_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_setting`
--

LOCK TABLES `system_setting` WRITE;
/*!40000 ALTER TABLE `system_setting` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_setting` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_account`
--

DROP TABLE IF EXISTS `user_account`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_account` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_account`
--

LOCK TABLES `user_account` WRITE;
/*!40000 ALTER TABLE `user_account` DISABLE KEYS */;
INSERT INTO `user_account` VALUES (1,'driver','Nimal','$2y$10$/fEPNP9iR/aDt5XS8cVe2.gyWEEY88zlTId4i0bGZn0cSyXVNd2mm','nimal@ncc.lk',1,'2025-03-12 09:00:00'),(2,'driver','Kasun','$2y$10$cY4n1zHJ8ccPngf9fQ4DC.sfKQCxBbxvBLsbkppnAQ25ZHU1PAVGG','Kasun@ncc.lk',1,'2025-02-15 09:00:00'),(3,'driver','Sachin','$2y$10$55Qx2iKg46Rj/uLNGr1GB.SWYjvo6m3ID2KoCYQN1qQREw6T50dvi','Sachin@ncc.lk',1,'2025-03-25 09:00:00'),(4,'order_processing_officer','Sachini','$2y$10$GLP.jtzUmPCNyYP2zRufB.nAyZdvwRZfIlJCu7sSIeiCXmhjcBYUO','Sachini@ncc.lk',1,'2025-03-25 09:00:00'),(5,'order_processing_officer','Kavindi','$2y$10$Na9rs7sn5/1Zqv085fpGn.JFhl5V7F8XUn9CpX1HxvRpD2DbnAeTK','Kavindi@ncc.lk',1,'2025-03-28 09:00:00'),(6,'customer_relation_manager','Dinithi','$2y$10$5CRcv3vEJpiavuoci0pmReDpns1enbdgb.GDUsA6WsZ.mK65sCNNG','Dinithi@ncc.lk',1,'2025-03-20 09:00:00'),(7,'customer_relation_manager','Hiruni','$2y$10$HEQDqMGLYfH12dCRXodYCuc5eHFB3EmrW6EmaSDO5n5WvKMjzffhm','Hiruni@ncc.lk',1,'2025-03-19 09:00:00'),(8,'supervisor','Imran','$2y$10$E5rtDnDczLDTKOgNTYBvwuqOJdhl4IFICcj8uNLn9fVA4O3Qv61za','Imran@ncc.lk',1,'2025-03-09 09:00:00'),(9,'financial_officer','Tharushi','$2y$10$7oQr4uM8kZAaT6afNyyJXuGtsLZtAbby0irmBbzP5dHoPKaEPei/K','Tharushi@ncc.lk',1,'2025-02-18 09:00:00'),(10,'inventory_manager','Ravindu','$2y$10$rdYnhIebLGr8XgjT5IYjeuWu6RZ8zPH2HgRS/3sqNXeAD6.VrszqG','Ravindu@ncc.lk',1,'2025-04-02 09:00:00'),(11,'distribution_manager','Dinuka','$2y$10$ZBLvXhlZOnU1MushvD7wR.ggOcX4MOTJ46Ojmwhnt/6UiS78Ltmv6','Dinuka@ncc.lk',1,'2025-04-03 09:00:00'),(12,'system_admin','sysadmin','$2y$10$3GuIIHb3V2Oak2eUWI8r/Oox9f547MP6Ubv278pxjcp4dX5w6CcSe','admin@ncc.lk',1,'2025-04-03 09:00:00'),(13,'ceo_head_manager','Sahan','$2y$10$wh/blAR0UOgOBKheHFXwh.WNvcTq9T8z20l3Li6bqQcUTcGEeS7A.','Sahan@ncc.lk',1,'2025-04-01 09:00:00');
/*!40000 ALTER TABLE `user_account` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicle`
--

DROP TABLE IF EXISTS `vehicle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicle` (
  `vehicle_id` int(11) NOT NULL AUTO_INCREMENT,
  `plate_number` varchar(30) NOT NULL,
  `vehicle_type` varchar(50) NOT NULL,
  `capacity` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`vehicle_id`),
  UNIQUE KEY `plate_number` (`plate_number`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicle`
--

LOCK TABLES `vehicle` WRITE;
/*!40000 ALTER TABLE `vehicle` DISABLE KEYS */;
/*!40000 ALTER TABLE `vehicle` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'ncc_database'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-16 21:46:17
