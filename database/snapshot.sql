-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: tesis
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
-- Current Database: `tesis`
--

/*!40000 DROP DATABASE IF EXISTS `tesis`*/;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `tesis` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `tesis`;

--
-- Table structure for table `academic_periods`
--

DROP TABLE IF EXISTS `academic_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_periods` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `name` varchar(100) NOT NULL,
  `starts_on` date NOT NULL,
  `ends_on` date NOT NULL,
  `status` enum('planned','active','closed') NOT NULL DEFAULT 'planned',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  CONSTRAINT `chk_period_dates` CHECK (`ends_on` >= `starts_on`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_periods`
--

LOCK TABLES `academic_periods` WRITE;
/*!40000 ALTER TABLE `academic_periods` DISABLE KEYS */;
INSERT INTO `academic_periods` VALUES (1,'2026-I','I PAO 2026','2026-01-01','2026-06-30','closed'),(2,'2026-II','II PAO 2026','2026-07-01','2026-12-31','active'),(3,'2027-I','I PAO 2027','2027-01-01','2027-06-30','planned');
/*!40000 ALTER TABLE `academic_periods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `academic_subjects`
--

DROP TABLE IF EXISTS `academic_subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_subjects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `career_id` smallint(5) unsigned NOT NULL,
  `academic_period_id` smallint(5) unsigned NOT NULL,
  `semester` smallint(5) unsigned NOT NULL,
  `code` varchar(40) NOT NULL,
  `name` varchar(180) NOT NULL,
  `responsible_teacher_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subject_period` (`academic_period_id`,`career_id`,`code`),
  KEY `fk_subject_career` (`career_id`),
  KEY `fk_subject_teacher` (`responsible_teacher_id`),
  CONSTRAINT `fk_subject_career` FOREIGN KEY (`career_id`) REFERENCES `careers` (`id`),
  CONSTRAINT `fk_subject_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`),
  CONSTRAINT `fk_subject_teacher` FOREIGN KEY (`responsible_teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_subjects`
--

LOCK TABLES `academic_subjects` WRITE;
/*!40000 ALTER TABLE `academic_subjects` DISABLE KEYS */;
INSERT INTO `academic_subjects` VALUES (7,1,2,4,'PROY-401','Proyecto Integrador IV',26),(8,1,2,6,'SEG-601','Seguridad de Aplicaciones',27),(9,1,2,8,'TIT-801','Unidad de Integración Curricular',28);
/*!40000 ALTER TABLE `academic_subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_audit_log`
--

DROP TABLE IF EXISTS `admin_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(80) NOT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_admin_audit_date` (`created_at`),
  KEY `idx_admin_audit_entity` (`entity_type`,`entity_id`),
  KEY `fk_admin_audit_actor` (`actor_user_id`),
  CONSTRAINT `fk_admin_audit_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_audit_log`
--

LOCK TABLES `admin_audit_log` WRITE;
/*!40000 ALTER TABLE `admin_audit_log` DISABLE KEYS */;
INSERT INTO `admin_audit_log` VALUES (10,1,'demo_users_imported','user',20,'{\"demo\":true}',NULL,NULL,'2026-07-19 21:16:28'),(11,1,'demo_teacher_updated','user',26,'{\"demo\":true}',NULL,NULL,'2026-07-18 21:16:28'),(12,1,'demo_catalog_configured','project_type',NULL,'{\"demo\":true}',NULL,NULL,'2026-07-17 21:16:28'),(13,1,'project_restored','project',28,'[]',NULL,NULL,'2026-07-23 05:07:16'),(14,1,'project_restored','project',28,'[]',NULL,NULL,'2026-07-23 05:07:55'),(15,1,'project_restored','project',25,'[]',NULL,NULL,'2026-07-23 05:07:56'),(16,1,'user_status_changed','user',72,'{\"status\":\"blocked\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 07:09:34'),(17,1,'user_status_changed','user',72,'{\"status\":\"active\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 07:09:40');
/*!40000 ALTER TABLE `admin_audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `careers`
--

DROP TABLE IF EXISTS `careers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `careers` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `name` varchar(180) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `careers`
--

LOCK TABLES `careers` WRITE;
/*!40000 ALTER TABLE `careers` DISABLE KEYS */;
INSERT INTO `careers` VALUES (1,'TDS','Desarrollo de Software',1);
/*!40000 ALTER TABLE `careers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `type` enum('delivery','observation','status_change','review','reminder','system','tribunal','repository','comment') NOT NULL,
  `title` varchar(180) NOT NULL,
  `message` text NOT NULL,
  `action_url` varchar(500) DEFAULT NULL,
  `action_label` varchar(80) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_active` (`user_id`,`deleted_at`,`created_at`),
  KEY `idx_notifications_project` (`project_id`),
  CONSTRAINT `fk_notifications_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (10,24,22,'observation','Tienes observaciones nuevas','Revisa los comentarios registrados por tu tutora.','index.php?page=project-detail&id=22','Abrir proyecto','{\"demo\":true}',0,NULL,'2026-07-18 21:16:28','2026-07-19 21:16:28',NULL,NULL),(11,22,25,'status_change','Proyecto aprobado','El proyecto está listo para cargar sus documentos finales.','index.php?page=project-detail&id=25','Abrir proyecto','{\"demo\":true}',0,NULL,'2026-07-18 21:16:28','2026-07-19 21:16:28',NULL,NULL),(12,21,27,'repository','Proyecto publicado','El proyecto ya se encuentra publicado en el repositorio.','index.php?page=project-detail&id=27','Abrir proyecto','{\"demo\":true}',0,NULL,'2026-07-18 21:16:28','2026-07-19 21:16:28',NULL,NULL),(13,68,NULL,'system','Bienvenido al entorno de pruebas','Tu cuenta de estudiante esta lista para probar las funciones de la plataforma.','index.php?page=dashboard','Ir al inicio','{\"source\": \"test_setup\"}',0,NULL,'2026-07-21 19:30:21','2026-07-21 19:30:21',NULL,NULL),(14,68,NULL,'reminder','Revisa tus actividades pendientes','Consulta el panel para familiarizarte con entregas, observaciones y fechas academicas.','index.php?page=dashboard','Revisar panel','{\"source\": \"test_setup\"}',0,NULL,'2026-07-21 19:30:21','2026-07-21 19:30:21',NULL,NULL),(15,69,NULL,'review','Cuenta docente preparada','Tu cuenta docente esta lista para revisar proyectos y registrar observaciones de prueba.','index.php?page=dashboard','Ir al inicio','{\"source\": \"test_setup\"}',0,NULL,'2026-07-21 19:30:21','2026-07-21 19:30:21',NULL,NULL),(16,1,NULL,'system','Datos demostrativos preparados','El listado contiene registros suficientes para comprobar la paginación.',NULL,NULL,'{\"source\":\"pagination_demo\",\"item\":1}',1,'2026-07-23 03:55:46','2026-07-23 03:45:56','2026-07-23 03:55:46',NULL,NULL),(17,1,NULL,'reminder','Revisión de proyecto pendiente','Comprueba el segundo bloque de resultados del listado.',NULL,NULL,'{\"source\":\"pagination_demo\",\"item\":2}',1,'2026-07-23 03:55:53','2026-07-23 02:45:56','2026-07-23 03:55:53',NULL,NULL),(18,1,NULL,'status_change','Proyecto actualizado','Un proyecto demostrativo cambió de estado.',NULL,NULL,'{\"source\":\"pagination_demo\",\"item\":3}',1,NULL,'2026-07-23 01:45:56','2026-07-23 03:45:56',NULL,NULL),(19,1,NULL,'repository','Proyecto publicado','Hay un nuevo proyecto disponible en el repositorio.',NULL,NULL,'{\"source\":\"pagination_demo\",\"item\":4}',1,'2026-07-23 03:56:05','2026-07-23 00:45:56','2026-07-23 03:56:05',NULL,NULL),(20,1,NULL,'review','Revisión completada','La revisión académica fue registrada correctamente.',NULL,NULL,'{\"source\":\"pagination_demo\",\"item\":5}',1,NULL,'2026-07-22 23:45:56','2026-07-23 03:45:56',NULL,NULL),(21,1,NULL,'observation','Nueva observación','Se agregó una observación al documento demostrativo.',NULL,NULL,'{\"source\":\"pagination_demo\",\"item\":6}',1,'2026-07-23 05:33:57','2026-07-22 22:45:56','2026-07-23 05:33:57',NULL,NULL),(22,1,NULL,'system','Catálogo actualizado','Los datos institucionales fueron sincronizados.',NULL,NULL,'{\"source\":\"pagination_demo\",\"item\":7}',1,NULL,'2026-07-22 03:45:56','2026-07-23 03:45:56',NULL,NULL),(23,1,NULL,'reminder','Actividad próxima','Existe una actividad académica programada.',NULL,NULL,'{\"source\":\"pagination_demo\",\"item\":8}',0,NULL,'2026-07-21 03:45:56','2026-07-23 03:45:56',NULL,NULL),(24,1,NULL,'status_change','Estado aprobado','El proyecto demostrativo fue aprobado.',NULL,NULL,'{\"source\":\"pagination_demo\",\"item\":9}',1,NULL,'2026-07-20 03:45:56','2026-07-23 03:45:56',NULL,NULL),(25,1,NULL,'repository','Documento disponible','El documento final ya puede consultarse.',NULL,NULL,'{\"source\":\"pagination_demo\",\"item\":10}',0,NULL,'2026-07-19 03:45:56','2026-07-23 03:45:56',NULL,NULL),(26,1,NULL,'review','Comentarios registrados','La tutora agregó comentarios de revisión.',NULL,NULL,'{\"source\":\"pagination_demo\",\"item\":11}',0,NULL,'2026-07-18 03:45:56','2026-07-23 03:45:56',NULL,NULL),(27,1,NULL,'system','Prueba de segunda página','Esta notificación permite verificar la navegación entre páginas.',NULL,NULL,'{\"source\":\"pagination_demo\",\"item\":12}',0,NULL,'2026-07-17 03:45:56','2026-07-23 03:45:56',NULL,NULL);
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `observation_responses`
--

DROP TABLE IF EXISTS `observation_responses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `observation_responses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `observation_id` bigint(20) unsigned NOT NULL,
  `author_id` bigint(20) unsigned NOT NULL,
  `body` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_responses_observation` (`observation_id`),
  KEY `fk_responses_author` (`author_id`),
  CONSTRAINT `fk_responses_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_responses_observation` FOREIGN KEY (`observation_id`) REFERENCES `project_observations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `observation_responses`
--

LOCK TABLES `observation_responses` WRITE;
/*!40000 ALTER TABLE `observation_responses` DISABLE KEYS */;
INSERT INTO `observation_responses` VALUES (4,7,24,'La justificación fue incorporada en la versión que estamos preparando.','2026-07-18 21:16:28');
/*!40000 ALTER TABLE `observation_responses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_audit_log`
--

DROP TABLE IF EXISTS `project_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(80) NOT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `previous_state` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_state`)),
  `new_state` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_state`)),
  `reason` varchar(500) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_project_date` (`project_id`,`created_at`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  KEY `fk_audit_user` (`user_id`),
  CONSTRAINT `fk_audit_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_audit_log`
--

LOCK TABLES `project_audit_log` WRITE;
/*!40000 ALTER TABLE `project_audit_log` DISABLE KEYS */;
INSERT INTO `project_audit_log` VALUES (10,22,1,'delivery_submitted','delivery',4,NULL,'{\"demo\":true}','Actividad demostrativa',NULL,NULL,'2026-07-18 21:16:28'),(11,25,1,'project_approved','project',25,NULL,'{\"demo\":true}','Actividad demostrativa',NULL,NULL,'2026-07-18 21:16:28'),(12,27,1,'project_published','project',27,NULL,'{\"demo\":true}','Actividad demostrativa',NULL,NULL,'2026-07-18 21:16:28'),(13,38,1,'project_updated','project',38,'{\"id\":38,\"title\":\"Monitoreo de infraestructura de red\",\"status\":\"published\"}','{\"title\":\"Monitoreo de infraestructura de red\",\"subtitle\":\"Prueba de paginación 10\",\"project_type_id\":2,\"career_id\":1,\"academic_period_id\":2,\"tutor_id\":70,\"status\":\"approved\"}',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 03:49:05'),(14,38,1,'project_updated','project',38,'{\"id\":38,\"title\":\"Monitoreo de infraestructura de red\",\"status\":\"approved\"}','{\"title\":\"Monitoreo de infraestructura de red\",\"subtitle\":\"Prueba de paginación 10\",\"project_type_id\":2,\"career_id\":1,\"academic_period_id\":2,\"tutor_id\":28,\"status\":\"approved\"}',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 03:49:12'),(15,38,1,'project_updated','project',38,'{\"id\":38,\"title\":\"Monitoreo de infraestructura de red\",\"status\":\"approved\"}','{\"title\":\"Monitoreo de infraestructura de redd\",\"subtitle\":\"Prueba de paginación 100\",\"project_type_id\":3,\"career_id\":1,\"academic_period_id\":2,\"tutor_id\":28,\"status\":\"approved\"}',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 03:49:22'),(16,25,1,'project_updated','project',25,'{\"id\":25,\"code\":\"VIN-2026-001\",\"title\":\"Alfabetización digital para emprendedores locales\",\"status\":\"approved\",\"project_type_id\":4,\"created_at\":\"2026-06-14 21:16:28\"}','{\"title\":\"Alfabetización digital para emprendedores locales\",\"subtitle\":\"Proyecto de vinculación con herramientas de comercio electrónico\",\"project_type_id\":4,\"career_id\":1,\"academic_period_id\":2,\"tutor_id\":71,\"status\":\"approved\",\"code\":\"VIN-2026-001\"}',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 04:02:36'),(17,25,1,'project_trashed','project',25,'{\"id\":25,\"title\":\"Alfabetización digital para emprendedores locales\",\"status\":\"approved\"}','{\"deleted\":true}','Registro creado por error.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 05:07:04'),(18,28,1,'project_trashed','project',28,'{\"id\":28,\"title\":\"Prototipo descartado de control de asistencia\",\"status\":\"development\"}','{\"deleted\":true}','No más xd','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 05:07:35');
/*!40000 ALTER TABLE `project_audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_code_sequences`
--

DROP TABLE IF EXISTS `project_code_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_code_sequences` (
  `project_type_id` smallint(5) unsigned NOT NULL,
  `code_year` smallint(5) unsigned NOT NULL,
  `next_number` int(10) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`project_type_id`,`code_year`),
  CONSTRAINT `fk_code_sequence_type` FOREIGN KEY (`project_type_id`) REFERENCES `project_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_code_sequences`
--

LOCK TABLES `project_code_sequences` WRITE;
/*!40000 ALTER TABLE `project_code_sequences` DISABLE KEYS */;
INSERT INTO `project_code_sequences` VALUES (1,2026,3),(2,2026,13),(3,2026,3),(4,2026,2);
/*!40000 ALTER TABLE `project_code_sequences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_comments`
--

DROP TABLE IF EXISTS `project_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `author_id` bigint(20) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `delivery_id` bigint(20) unsigned DEFAULT NULL,
  `file_id` bigint(20) unsigned DEFAULT NULL,
  `observation_id` bigint(20) unsigned DEFAULT NULL,
  `body` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_comments_project` (`project_id`),
  KEY `fk_comments_author` (`author_id`),
  KEY `fk_comments_parent` (`parent_id`),
  KEY `fk_comments_delivery` (`delivery_id`),
  KEY `fk_comments_file` (`file_id`),
  KEY `fk_comments_observation` (`observation_id`),
  CONSTRAINT `fk_comments_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_comments_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `project_deliveries` (`id`),
  CONSTRAINT `fk_comments_file` FOREIGN KEY (`file_id`) REFERENCES `project_files` (`id`),
  CONSTRAINT `fk_comments_observation` FOREIGN KEY (`observation_id`) REFERENCES `project_observations` (`id`),
  CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_id`) REFERENCES `project_comments` (`id`),
  CONSTRAINT `fk_comments_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_comments`
--

LOCK TABLES `project_comments` WRITE;
/*!40000 ALTER TABLE `project_comments` DISABLE KEYS */;
INSERT INTO `project_comments` VALUES (4,22,28,NULL,NULL,NULL,NULL,'El avance general es correcto. Prioricen las dos observaciones antes de la próxima entrega.','2026-07-17 21:16:28',NULL,NULL);
/*!40000 ALTER TABLE `project_comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_deliveries`
--

DROP TABLE IF EXISTS `project_deliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_deliveries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `stage_id` bigint(20) unsigned DEFAULT NULL,
  `version_number` int(10) unsigned NOT NULL,
  `title` varchar(220) NOT NULL,
  `comment` text DEFAULT NULL,
  `status` enum('submitted','under_review','changes_required','approved') NOT NULL DEFAULT 'submitted',
  `submitted_by` bigint(20) unsigned NOT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_delivery_version` (`project_id`,`version_number`),
  KEY `fk_deliveries_stage` (`stage_id`),
  KEY `fk_deliveries_user` (`submitted_by`),
  CONSTRAINT `fk_deliveries_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_deliveries_stage` FOREIGN KEY (`stage_id`) REFERENCES `project_stages` (`id`),
  CONSTRAINT `fk_deliveries_user` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_deliveries`
--

LOCK TABLES `project_deliveries` WRITE;
/*!40000 ALTER TABLE `project_deliveries` DISABLE KEYS */;
INSERT INTO `project_deliveries` VALUES (4,22,66,1,'Informe de avance v1','Primera versión para revisión.','changes_required',24,'2026-07-13 21:16:28');
/*!40000 ALTER TABLE `project_deliveries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_downloads`
--

DROP TABLE IF EXISTS `project_downloads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_downloads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `downloaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_project_downloads` (`project_id`,`downloaded_at`),
  KEY `fk_download_user` (`user_id`),
  CONSTRAINT `fk_download_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_download_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_downloads`
--

LOCK TABLES `project_downloads` WRITE;
/*!40000 ALTER TABLE `project_downloads` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_downloads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_events`
--

DROP TABLE IF EXISTS `project_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(180) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `event_date` date NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_events_project_date` (`project_id`,`event_date`),
  KEY `fk_events_user` (`created_by`),
  CONSTRAINT `fk_events_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_events_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_events`
--

LOCK TABLES `project_events` WRITE;
/*!40000 ALTER TABLE `project_events` DISABLE KEYS */;
INSERT INTO `project_events` VALUES (10,22,'Entrega de versión corregida','delivery','high','2026-07-28','Fecha académica demostrativa para validar calendario y alertas.',0,1,'2026-07-19 21:16:28'),(11,25,'Validación de documentos finales','review','medium','2026-08-04','Fecha académica demostrativa para validar calendario y alertas.',0,1,'2026-07-19 21:16:28'),(12,23,'Reunión de seguimiento','meeting','medium','2026-08-11','Fecha académica demostrativa para validar calendario y alertas.',0,1,'2026-07-19 21:16:28');
/*!40000 ALTER TABLE `project_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_favorites`
--

DROP TABLE IF EXISTS `project_favorites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_favorites` (
  `user_id` bigint(20) unsigned NOT NULL,
  `project_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`,`project_id`),
  KEY `fk_favorite_project` (`project_id`),
  CONSTRAINT `fk_favorite_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_favorite_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_favorites`
--

LOCK TABLES `project_favorites` WRITE;
/*!40000 ALTER TABLE `project_favorites` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_favorites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_files`
--

DROP TABLE IF EXISTS `project_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_files` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `delivery_id` bigint(20) unsigned DEFAULT NULL,
  `category` varchar(60) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `storage_name` varchar(190) NOT NULL,
  `storage_path` varchar(500) NOT NULL,
  `mime_type` varchar(120) NOT NULL,
  `extension` varchar(12) NOT NULL,
  `size_bytes` bigint(20) unsigned NOT NULL,
  `checksum_sha256` char(64) NOT NULL,
  `uploaded_by` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `storage_name` (`storage_name`),
  KEY `idx_files_project` (`project_id`,`category`),
  KEY `fk_files_delivery` (`delivery_id`),
  KEY `fk_files_user` (`uploaded_by`),
  CONSTRAINT `fk_files_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `project_deliveries` (`id`),
  CONSTRAINT `fk_files_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_files_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_files`
--

LOCK TABLES `project_files` WRITE;
/*!40000 ALTER TABLE `project_files` DISABLE KEYS */;
INSERT INTO `project_files` VALUES (14,22,4,'delivery','Informe_avance_v1.pdf','674e5fb94b148d8e2adddeec94ccb53dba3bcdae965be1697f826b50bfab6ec5.pdf','storage/private/projects/22/674e5fb94b148d8e2adddeec94ccb53dba3bcdae965be1697f826b50bfab6ec5.pdf','application/pdf','pdf',673,'4c7f6e0bbd226d162aad3b04d39ec6f7fbc5ce461708964b1798684b25a3d7ee',24,'2026-07-19 21:16:28',NULL),(15,22,4,'annex','Anexos_investigacion.zip','7764654b1cd99ebbf7c4ec33a86c7969f316b5cee5b4ff18ca8a9549a70e689e.zip','storage/private/projects/22/7764654b1cd99ebbf7c4ec33a86c7969f316b5cee5b4ff18ca8a9549a70e689e.zip','application/zip','zip',522,'dd3a324409a2b947d92fdceb8adf7e7ac277d4e0dcb723d3455be6769291efe9',24,'2026-07-19 21:16:28',NULL),(16,25,NULL,'final','Informe_final_vinculacion.docx','fdfe028a320d8a1fe44bc7fef86eab8a9e53952bdc182794864a0955961e66ce.docx','storage/private/projects/25/fdfe028a320d8a1fe44bc7fef86eab8a9e53952bdc182794864a0955961e66ce.docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document','docx',1335,'b9d389ee48614041ffa2b9b84759f00a55511fdb7b01e601faae8e9b3878798b',22,'2026-07-19 21:16:28',NULL),(17,26,NULL,'final','Trabajo_titulacion_final.pdf','ee19c560ba20ac4c33055303f83c0cdd39b1f453de6a8a7249ae4b282487a192.pdf','storage/private/projects/26/ee19c560ba20ac4c33055303f83c0cdd39b1f453de6a8a7249ae4b282487a192.pdf','application/pdf','pdf',680,'840a88ca3f4b780bf9f940ea09d76da8ddb1cf0f9bf8bf9ebabffaba056efdb4',24,'2026-07-19 21:16:28',NULL),(18,27,NULL,'final','Informe_proyecto_publicado.pdf','8e479ac38f49cf400966e9b37f0e2069be031c964ba942fff7667e332322c55e.pdf','storage/private/projects/27/8e479ac38f49cf400966e9b37f0e2069be031c964ba942fff7667e332322c55e.pdf','application/pdf','pdf',682,'82e9afdd04b82b3c0eae120f5feb6a9a1bf73806b086f078f942dc282d31ece2',21,'2026-07-19 21:16:28',NULL),(19,27,NULL,'source','Codigo_fuente_demostrativo.zip','d77af8cffbd61371dd0981b387ebb4929638b63347d17ef0d1244fe0144ecb26.zip','storage/private/projects/27/d77af8cffbd61371dd0981b387ebb4929638b63347d17ef0d1244fe0144ecb26.zip','application/zip','zip',528,'7c06f4a68a6a8a06876b5aad658f87b99279d5622edbd64e268bbff7e5fa4545',21,'2026-07-19 21:16:28',NULL);
/*!40000 ALTER TABLE `project_files` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_observations`
--

DROP TABLE IF EXISTS `project_observations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_observations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `delivery_id` bigint(20) unsigned DEFAULT NULL,
  `file_id` bigint(20) unsigned DEFAULT NULL,
  `author_id` bigint(20) unsigned NOT NULL,
  `category` varchar(60) NOT NULL,
  `location_reference` varchar(180) DEFAULT NULL,
  `body` text NOT NULL,
  `status` enum('pending','addressed','resolved') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_observations_project` (`project_id`),
  KEY `fk_observations_delivery` (`delivery_id`),
  KEY `fk_observations_file` (`file_id`),
  KEY `fk_observations_author` (`author_id`),
  CONSTRAINT `fk_observations_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_observations_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `project_deliveries` (`id`),
  CONSTRAINT `fk_observations_file` FOREIGN KEY (`file_id`) REFERENCES `project_files` (`id`),
  CONSTRAINT `fk_observations_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_observations`
--

LOCK TABLES `project_observations` WRITE;
/*!40000 ALTER TABLE `project_observations` DISABLE KEYS */;
INSERT INTO `project_observations` VALUES (7,22,4,NULL,28,'Metodología','Página 18','Explicar el criterio utilizado para seleccionar la muestra y justificar su tamaño.','pending','2026-07-15 21:16:28',NULL),(8,22,4,NULL,28,'Referencias','Página 31','Unificar las referencias bibliográficas con el formato APA 7.','addressed','2026-07-16 21:16:28',NULL);
/*!40000 ALTER TABLE `project_observations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_participants`
--

DROP TABLE IF EXISTS `project_participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_participants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `role_code` varchar(50) NOT NULL,
  `permission_level` enum('manage','contribute','review','read') NOT NULL DEFAULT 'read',
  `is_leader` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `removed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_participant` (`project_id`,`user_id`,`role_code`),
  KEY `idx_participant_user` (`user_id`,`status`),
  CONSTRAINT `fk_participants_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_participants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_participants`
--

LOCK TABLES `project_participants` WRITE;
/*!40000 ALTER TABLE `project_participants` DISABLE KEYS */;
INSERT INTO `project_participants` VALUES (46,22,24,'student','manage',1,'active','2026-07-19 21:16:28',NULL),(47,22,28,'tutor','review',0,'active','2026-07-19 21:16:28',NULL),(48,23,20,'student','manage',1,'active','2026-07-19 21:16:28',NULL),(49,23,26,'tutor','review',0,'active','2026-07-19 21:16:28',NULL),(50,24,23,'student','manage',1,'active','2026-07-19 21:16:28',NULL),(51,24,27,'tutor','review',0,'active','2026-07-19 21:16:28',NULL),(52,25,22,'student','manage',1,'active','2026-07-19 21:16:28',NULL),(53,25,26,'tutor','review',0,'active','2026-07-19 21:16:28',NULL),(54,26,24,'student','manage',1,'active','2026-07-19 21:16:28',NULL),(55,26,28,'tutor','review',0,'active','2026-07-19 21:16:28',NULL),(56,27,21,'student','manage',1,'active','2026-07-19 21:16:28',NULL),(57,27,27,'tutor','review',0,'active','2026-07-19 21:16:28',NULL),(58,28,25,'student','manage',1,'active','2026-07-19 21:16:28',NULL),(59,28,26,'tutor','review',0,'active','2026-07-19 21:16:28',NULL),(60,23,21,'student','contribute',0,'active','2026-07-19 21:16:28',NULL);
/*!40000 ALTER TABLE `project_participants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_stages`
--

DROP TABLE IF EXISTS `project_stages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_stages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `stage_code` varchar(80) NOT NULL,
  `label` varchar(160) NOT NULL,
  `position` smallint(5) unsigned NOT NULL,
  `status` enum('upcoming','current','completed','skipped') NOT NULL DEFAULT 'upcoming',
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_stage` (`project_id`,`stage_code`),
  CONSTRAINT `fk_stages_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=85 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_stages`
--

LOCK TABLES `project_stages` WRITE;
/*!40000 ALTER TABLE `project_stages` DISABLE KEYS */;
INSERT INTO `project_stages` VALUES (64,22,'registration','Registro',1,'completed','2026-07-07 23:16:28'),(65,22,'development','Desarrollo',2,'current',NULL),(66,22,'review','Revisión',3,'upcoming',NULL),(67,23,'registration','Registro',1,'completed','2026-07-07 23:16:28'),(68,23,'development','Desarrollo',2,'current',NULL),(69,23,'review','Revisión',3,'upcoming',NULL),(70,24,'registration','Registro',1,'completed','2026-07-07 23:16:28'),(71,24,'development','Desarrollo',2,'current',NULL),(72,24,'review','Revisión',3,'upcoming',NULL),(73,25,'registration','Registro',1,'completed','2026-07-07 23:16:28'),(74,25,'development','Desarrollo',2,'current',NULL),(75,25,'review','Revisión',3,'upcoming',NULL),(76,26,'registration','Registro',1,'completed','2026-07-07 23:16:28'),(77,26,'development','Desarrollo',2,'current',NULL),(78,26,'review','Revisión',3,'upcoming',NULL),(79,27,'registration','Registro',1,'completed','2026-07-07 23:16:28'),(80,27,'development','Desarrollo',2,'completed','2026-07-07 23:16:28'),(81,27,'review','Revisión',3,'completed','2026-07-07 23:16:28'),(82,28,'registration','Registro',1,'completed','2026-07-07 23:16:28'),(83,28,'development','Desarrollo',2,'current',NULL),(84,28,'review','Revisión',3,'upcoming',NULL);
/*!40000 ALTER TABLE `project_stages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_types`
--

DROP TABLE IF EXISTS `project_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_types` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(60) NOT NULL,
  `name` varchar(140) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_types`
--

LOCK TABLES `project_types` WRITE;
/*!40000 ALTER TABLE `project_types` DISABLE KEYS */;
INSERT INTO `project_types` VALUES (1,'thesis','Titulación',1),(2,'pis','Proyecto integrador de saberes',1),(3,'practice','Prácticas preprofesionales',1),(4,'community','Proyecto de vinculación',1),(30,'thesis_profile','Perfil de tesis',1);
/*!40000 ALTER TABLE `project_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `projects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL,
  `project_type_id` smallint(5) unsigned NOT NULL,
  `career_id` smallint(5) unsigned NOT NULL,
  `academic_period_id` smallint(5) unsigned NOT NULL,
  `title` varchar(240) NOT NULL,
  `subtitle` varchar(300) DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `modality` enum('individual','group') DEFAULT NULL,
  `research_line_id` smallint(5) unsigned DEFAULT NULL,
  `academic_subject_id` bigint(20) unsigned DEFAULT NULL,
  `proposed_tutor_id` bigint(20) unsigned DEFAULT NULL,
  `tutor_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(60) NOT NULL DEFAULT 'development',
  `current_stage` varchar(100) NOT NULL DEFAULT 'registration',
  `approved_at` datetime DEFAULT NULL,
  `defense_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deletion_reason` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_projects_status` (`status`),
  KEY `idx_projects_period` (`academic_period_id`),
  KEY `idx_projects_type` (`project_type_id`),
  KEY `fk_projects_career` (`career_id`),
  KEY `fk_projects_research_line` (`research_line_id`),
  KEY `fk_projects_subject` (`academic_subject_id`),
  KEY `fk_projects_proposed_tutor` (`proposed_tutor_id`),
  KEY `fk_projects_tutor` (`tutor_id`),
  KEY `fk_projects_creator` (`created_by`),
  CONSTRAINT `fk_projects_career` FOREIGN KEY (`career_id`) REFERENCES `careers` (`id`),
  CONSTRAINT `fk_projects_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_projects_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`),
  CONSTRAINT `fk_projects_proposed_tutor` FOREIGN KEY (`proposed_tutor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_projects_research_line` FOREIGN KEY (`research_line_id`) REFERENCES `research_lines` (`id`),
  CONSTRAINT `fk_projects_subject` FOREIGN KEY (`academic_subject_id`) REFERENCES `academic_subjects` (`id`),
  CONSTRAINT `fk_projects_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_projects_type` FOREIGN KEY (`project_type_id`) REFERENCES `project_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projects`
--

LOCK TABLES `projects` WRITE;
/*!40000 ALTER TABLE `projects` DISABLE KEYS */;
INSERT INTO `projects` VALUES (22,'TIT-2026-001',1,1,2,'Sistema inteligente para la gestión de turnos médicos','Plataforma web accesible para centros de salud','Proyecto demostrativo con información realista para validar las herramientas administrativas.','group',5,9,28,28,'under_review','review',NULL,NULL,NULL,NULL,24,'2026-06-14 21:16:28','2026-07-23 03:53:54',NULL,NULL,NULL),(23,'PIS-2026-001',2,1,2,'Aplicación móvil para rutas de transporte urbano','Seguimiento de recorridos y alertas para usuarios','Proyecto demostrativo con información realista para validar las herramientas administrativas.','group',5,7,26,26,'development','development',NULL,NULL,NULL,NULL,20,'2026-06-14 21:16:28','2026-07-23 03:53:54',NULL,NULL,NULL),(24,'PRA-2026-001',3,1,2,'Automatización del inventario de equipos tecnológicos','Práctica preprofesional aplicada al control institucional','Proyecto demostrativo con información realista para validar las herramientas administrativas.','group',5,8,27,27,'changes_required','review',NULL,NULL,NULL,NULL,23,'2026-06-14 21:16:28','2026-07-23 03:53:54',NULL,NULL,NULL),(25,'VIN-2026-001',4,1,2,'Alfabetización digital para emprendedores locales','Proyecto de vinculación con herramientas de comercio electrónico','Proyecto demostrativo con información realista para validar las herramientas administrativas.','group',5,7,26,71,'approved','final_documents','2026-07-17 21:16:28',NULL,NULL,NULL,22,'2026-06-14 21:16:28','2026-07-23 05:07:56',NULL,NULL,NULL),(26,'TIT-2026-002',1,1,2,'Modelo de detección temprana de riesgos académicos','Análisis de indicadores para acompañamiento estudiantil','Proyecto demostrativo con información realista para validar las herramientas administrativas.','group',5,9,28,28,'defense','defense',NULL,NULL,'2026-07-11 21:16:28',NULL,24,'2026-06-14 21:16:28','2026-07-23 03:53:54',NULL,NULL,NULL),(27,'PIS-2026-002',2,1,2,'Panel de monitoreo energético para laboratorios','Visualización en tiempo real de consumo eléctrico','Proyecto demostrativo con información realista para validar las herramientas administrativas.','group',5,7,27,27,'published','published',NULL,NULL,'2026-07-11 21:16:28','2026-07-16 21:16:28',21,'2026-06-14 21:16:28','2026-07-23 03:53:54',NULL,NULL,NULL),(28,'PIS-2026-003',2,1,2,'Prototipo descartado de control de asistencia','Registro creado para probar la papelera administrativa','Proyecto demostrativo con información realista para validar las herramientas administrativas.','group',5,7,26,26,'development','registration',NULL,NULL,NULL,NULL,25,'2026-06-14 21:16:28','2026-07-23 05:07:55',NULL,NULL,NULL),(29,'PIS-2026-004',2,1,2,'Sistema de reservas para laboratorios','Prueba de paginación 1','Proyecto demostrativo para validar listados extensos.','group',NULL,NULL,70,70,'published','published','2026-07-23 03:45:56',NULL,'2026-07-23 03:45:56','2026-07-23 03:45:56',72,'2026-07-23 03:45:56','2026-07-23 03:53:54',NULL,NULL,NULL),(30,'PIS-2026-005',2,1,2,'Aplicación de control de asistencia','Prueba de paginación 2','Proyecto demostrativo para validar listados extensos.','group',NULL,NULL,70,70,'published','published','2026-07-23 03:45:56',NULL,'2026-07-23 03:45:56','2026-07-23 03:45:56',73,'2026-07-23 03:45:56','2026-07-23 03:53:54',NULL,NULL,NULL),(31,'PIS-2026-006',2,1,2,'Portal de seguimiento de tutorías','Prueba de paginación 3','Proyecto demostrativo para validar listados extensos.','group',NULL,NULL,70,70,'published','published','2026-07-23 03:45:56',NULL,'2026-07-23 03:45:56','2026-07-23 03:45:56',74,'2026-07-23 03:45:56','2026-07-23 03:53:54',NULL,NULL,NULL),(32,'PIS-2026-007',2,1,2,'Gestor documental para secretaría','Prueba de paginación 4','Proyecto demostrativo para validar listados extensos.','group',NULL,NULL,70,70,'published','published','2026-07-23 03:45:56',NULL,'2026-07-23 03:45:56','2026-07-23 03:45:56',75,'2026-07-23 03:45:56','2026-07-23 03:53:54',NULL,NULL,NULL),(33,'PIS-2026-008',2,1,2,'Panel de indicadores estudiantiles','Prueba de paginación 5','Proyecto demostrativo para validar listados extensos.','group',NULL,NULL,70,70,'published','published','2026-07-23 03:45:56',NULL,'2026-07-23 03:45:56','2026-07-23 03:45:56',76,'2026-07-23 03:45:56','2026-07-23 03:53:54',NULL,NULL,NULL),(34,'PIS-2026-009',2,1,2,'Plataforma de encuestas académicas','Prueba de paginación 6','Proyecto demostrativo para validar listados extensos.','group',NULL,NULL,70,70,'published','published','2026-07-23 03:45:56',NULL,'2026-07-23 03:45:56','2026-07-23 03:45:56',77,'2026-07-23 03:45:56','2026-07-23 03:53:54',NULL,NULL,NULL),(35,'PIS-2026-010',2,1,2,'Sistema de préstamos tecnológicos','Prueba de paginación 7','Proyecto demostrativo para validar listados extensos.','group',NULL,NULL,70,70,'published','published','2026-07-23 03:45:56',NULL,'2026-07-23 03:45:56','2026-07-23 03:45:56',72,'2026-07-23 03:45:56','2026-07-23 03:53:54',NULL,NULL,NULL),(36,'PIS-2026-011',2,1,2,'Agenda institucional inteligente','Prueba de paginación 8','Proyecto demostrativo para validar listados extensos.','group',NULL,NULL,70,70,'published','published','2026-07-23 03:45:56',NULL,'2026-07-23 03:45:56','2026-07-23 03:45:56',73,'2026-07-23 03:45:56','2026-07-23 03:53:54',NULL,NULL,NULL),(37,'PIS-2026-012',2,1,2,'Repositorio de recursos didácticos','Prueba de paginación 9','Proyecto demostrativo para validar listados extensos.','group',NULL,NULL,70,70,'published','published','2026-07-23 03:45:56',NULL,'2026-07-23 03:45:56','2026-07-23 03:45:56',74,'2026-07-23 03:45:56','2026-07-23 03:53:54',NULL,NULL,NULL),(38,'PRA-2026-002',3,1,2,'Monitoreo de infraestructura de redd','Prueba de paginación 100','Proyecto demostrativo para validar listados extensos.','group',NULL,NULL,70,28,'approved','published','2026-07-23 03:45:56',NULL,'2026-07-23 03:45:56','2026-07-23 03:45:56',75,'2026-07-23 03:45:56','2026-07-23 03:53:54',NULL,NULL,NULL);
/*!40000 ALTER TABLE `projects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `research_lines`
--

DROP TABLE IF EXISTS `research_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `research_lines` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `career_id` smallint(5) unsigned DEFAULT NULL,
  `name` varchar(180) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_research_line_career` (`career_id`),
  CONSTRAINT `fk_research_line_career` FOREIGN KEY (`career_id`) REFERENCES `careers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `research_lines`
--

LOCK TABLES `research_lines` WRITE;
/*!40000 ALTER TABLE `research_lines` DISABLE KEYS */;
INSERT INTO `research_lines` VALUES (5,1,'Desarrollo de software y transformación digital',1),(6,1,'Seguridad, datos e infraestructura tecnológica',1);
/*!40000 ALTER TABLE `research_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'student','Estudiante'),(6,'administrator','Administrador'),(7,'teacher','Docente');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_enrollments`
--

DROP TABLE IF EXISTS `student_enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_enrollments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `academic_period_id` smallint(5) unsigned NOT NULL,
  `career_id` smallint(5) unsigned NOT NULL,
  `semester` smallint(5) unsigned NOT NULL,
  `status` enum('active','withdrawn','completed') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_enrollment` (`student_id`,`academic_period_id`),
  KEY `idx_enrollment_lookup` (`academic_period_id`,`career_id`,`semester`,`status`),
  KEY `fk_enrollment_career` (`career_id`),
  CONSTRAINT `fk_enrollment_career` FOREIGN KEY (`career_id`) REFERENCES `careers` (`id`),
  CONSTRAINT `fk_enrollment_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`),
  CONSTRAINT `fk_enrollment_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  CONSTRAINT `chk_enrollment_semester` CHECK (`semester` between 1 and 10)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_enrollments`
--

LOCK TABLES `student_enrollments` WRITE;
/*!40000 ALTER TABLE `student_enrollments` DISABLE KEYS */;
INSERT INTO `student_enrollments` VALUES (13,20,2,1,4,'active'),(14,21,2,1,4,'active'),(15,22,2,1,2,'active'),(16,23,2,1,6,'active'),(17,24,2,1,8,'active'),(18,25,2,1,4,'active'),(43,68,2,1,1,'active'),(44,72,2,1,4,'active'),(45,73,2,1,4,'active'),(46,74,2,1,4,'active'),(47,75,2,1,4,'active'),(48,76,2,1,4,'active'),(49,77,2,1,4,'active');
/*!40000 ALTER TABLE `student_enrollments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_profiles`
--

DROP TABLE IF EXISTS `student_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_profiles` (
  `user_id` bigint(20) unsigned NOT NULL,
  `institutional_code` varchar(50) NOT NULL,
  `career_id` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `institutional_code` (`institutional_code`),
  KEY `fk_student_profile_career` (`career_id`),
  CONSTRAINT `fk_student_profile_career` FOREIGN KEY (`career_id`) REFERENCES `careers` (`id`),
  CONSTRAINT `fk_student_profile_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_profiles`
--

LOCK TABLES `student_profiles` WRITE;
/*!40000 ALTER TABLE `student_profiles` DISABLE KEYS */;
INSERT INTO `student_profiles` VALUES (20,'1750010001',1),(21,'1750010002',1),(22,'1750010003',1),(23,'1750010004',1),(24,'1750010005',1),(25,'1750010006',1),(68,'0202053810',1),(72,'0202053821',1),(73,'0202053822',1),(74,'0202053823',1),(75,'0202053824',1),(76,'0202053825',1),(77,'0202053826',1);
/*!40000 ALTER TABLE `student_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES ('file_extensions','[\"pdf\",\"docx\",\"zip\"]',NULL,'2026-07-19 06:14:20'),('file_max_mb','20',NULL,'2026-07-19 06:14:20'),('file_total_max_mb','50',NULL,'2026-07-19 06:14:20'),('institution_name','Instituto Superior Tecnológico El Libertador',NULL,'2026-07-19 06:14:20'),('project_code_digits','3',NULL,'2026-07-22 23:04:39'),('project_code_prefixes','{\"thesis\":\"TIT\",\"thesis_profile\":\"PFT\",\"pis\":\"PIS\",\"practice\":\"PRA\",\"community\":\"VIN\"}',NULL,'2026-07-22 23:04:39');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher_profiles`
--

DROP TABLE IF EXISTS `teacher_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teacher_profiles` (
  `user_id` bigint(20) unsigned NOT NULL,
  `institutional_code` varchar(50) NOT NULL,
  `academic_title` varchar(120) DEFAULT NULL,
  `can_tutor` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `institutional_code` (`institutional_code`),
  CONSTRAINT `fk_teacher_profile_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_profiles`
--

LOCK TABLES `teacher_profiles` WRITE;
/*!40000 ALTER TABLE `teacher_profiles` DISABLE KEYS */;
INSERT INTO `teacher_profiles` VALUES (26,'0202053801','Msc. (por confirmar)',1),(27,'0202053802','Msc. (por confirmar)',1),(28,'0202053803','Lic. (por confirmar)',1),(69,'0202053804','Msc. (por confirmar)',1),(70,'0202053805','Abg. (por confirmar)',1),(71,'0202053806','Msc. (por confirmar)',1);
/*!40000 ALTER TABLE `teacher_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_roles`
--

DROP TABLE IF EXISTS `user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_roles` (
  `user_id` bigint(20) unsigned NOT NULL,
  `role_id` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `fk_user_roles_role` (`role_id`),
  CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_roles`
--

LOCK TABLES `user_roles` WRITE;
/*!40000 ALTER TABLE `user_roles` DISABLE KEYS */;
INSERT INTO `user_roles` VALUES (1,6),(20,1),(21,1),(22,1),(23,1),(24,1),(25,1),(26,7),(27,7),(28,7),(68,1),(69,7),(70,7),(71,7),(72,1),(73,1),(74,1),(75,1),(76,1),(77,1);
/*!40000 ALTER TABLE `user_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(190) NOT NULL,
  `username` varchar(80) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `password_warning_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `temporary_password_expires_at` datetime DEFAULT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `full_name` varchar(180) NOT NULL,
  `status` enum('active','inactive','blocked') NOT NULL DEFAULT 'active',
  `last_login_at` datetime DEFAULT NULL,
  `session_version` int(10) unsigned NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deletion_reason` varchar(500) DEFAULT NULL,
  `purged_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'tesisad@gmail.com',NULL,'$2y$10$Sg94TVbx1kIiuJYhULWb4eemn/D8JsDfZf.3joE7huPhGsoj5ypJS',1,2,'2026-07-26 04:58:06',NULL,'Administrador de pruebas','active','2026-07-23 18:39:26',1,'2026-07-19 03:56:23','2026-07-23 18:39:26',NULL,NULL,NULL,NULL),(20,'ana.torres.demo@correo.com',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,'2026-07-26 21:14:28',NULL,'Ana Lucía Torres','active',NULL,1,'2026-07-14 21:14:28','2026-07-19 21:14:28',NULL,NULL,NULL,NULL),(21,'carlos.mendoza.demo@correo.com',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,'2026-07-26 21:14:28',NULL,'Carlos Andrés Mendoza','active',NULL,1,'2026-07-14 21:14:28','2026-07-19 21:14:28',NULL,NULL,NULL,NULL),(22,'sofia.lopez.demo@correo.com',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,'2026-07-26 21:14:28',NULL,'Sofía López Herrera','active',NULL,1,'2026-07-14 21:14:28','2026-07-19 21:14:28',NULL,NULL,NULL,NULL),(23,'diego.paredes.demo@correo.com',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,'2026-07-26 21:14:28',NULL,'Diego Paredes Ruiz','active',NULL,1,'2026-07-14 21:14:28','2026-07-19 21:14:28',NULL,NULL,NULL,NULL),(24,'valentina.mora.demo@correo.com',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,'2026-07-26 21:14:28',NULL,'Valentina Mora Cedeño','active',NULL,1,'2026-07-14 21:14:28','2026-07-19 21:14:28',NULL,NULL,NULL,NULL),(25,'mateo.silva.demo@correo.com',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,'2026-07-26 21:14:28',NULL,'Mateo Silva Ortiz','blocked',NULL,1,'2026-07-14 21:14:28','2026-07-19 21:14:28',NULL,NULL,NULL,NULL),(26,'maribel.fierro.pendiente@local.invalid',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,'2026-07-26 21:14:28',NULL,'Msc. Maribel Fierro Montero','active',NULL,1,'2026-07-14 21:14:28','2026-07-23 03:23:58',NULL,NULL,NULL,NULL),(27,'maria.navarrete.pendiente@local.invalid',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,'2026-07-26 21:14:28',NULL,'Msc. Maria Elena Navarrete','active',NULL,1,'2026-07-14 21:14:28','2026-07-23 03:23:58',NULL,NULL,NULL,NULL),(28,'diana.alegria.pendiente@local.invalid',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,'2026-07-26 21:14:28',NULL,'Lic. Diana Alegría Camino','active',NULL,1,'2026-07-14 21:14:28','2026-07-23 03:23:58',NULL,NULL,NULL,NULL),(68,'tesises@gmail.com',NULL,'$2y$10$q.Yspr0Hz2koESXnL/yu2.f9C8SRdkDCHIwnIAeF8DepD0.X1qpx2',0,0,NULL,NULL,'Estudiante de pruebas','active',NULL,1,'2026-07-21 17:40:30','2026-07-21 17:40:30',NULL,NULL,NULL,NULL),(69,'diana.ramirez.pendiente@local.invalid',NULL,'$2y$10$6uVhqPOS9uppnUF5aTm1Wua8xwNq5bBQ/v0STvH4gApHQ95B6IvxO',0,0,NULL,NULL,'Msc. Diana Anaid Ramirez','active',NULL,1,'2026-07-21 17:40:30','2026-07-23 03:23:58',NULL,NULL,NULL,NULL),(70,'alex.galarza.pendiente@local.invalid',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,NULL,NULL,'Abg. Alex Fabián Galarza','active',NULL,1,'2026-07-23 03:23:58','2026-07-23 03:23:58',NULL,NULL,NULL,NULL),(71,'henrry.marino.pendiente@local.invalid',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,NULL,NULL,'Msc. Henrry Mariño Acosta','active',NULL,1,'2026-07-23 03:23:58','2026-07-23 03:23:58',NULL,NULL,NULL,NULL),(72,'paginacion01@demo.local',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,NULL,NULL,'Adriana Ponce Vera','active',NULL,3,'2026-07-23 03:45:56','2026-07-23 07:09:40',NULL,NULL,NULL,NULL),(73,'paginacion02@demo.local',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,NULL,NULL,'Bruno Cárdenas Mena','active',NULL,1,'2026-07-23 03:45:56','2026-07-23 03:45:56',NULL,NULL,NULL,NULL),(74,'paginacion03@demo.local',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,NULL,NULL,'Camila Andrade Ruiz','active',NULL,1,'2026-07-23 03:45:56','2026-07-23 03:45:56',NULL,NULL,NULL,NULL),(75,'paginacion04@demo.local',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,NULL,NULL,'David Guerrero Paz','active',NULL,1,'2026-07-23 03:45:56','2026-07-23 03:45:56',NULL,NULL,NULL,NULL),(76,'paginacion05@demo.local',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,NULL,NULL,'Elena Morales Cedeño','active',NULL,1,'2026-07-23 03:45:56','2026-07-23 03:45:56',NULL,NULL,NULL,NULL),(77,'paginacion06@demo.local',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,NULL,NULL,'Fernando Viteri León','active',NULL,1,'2026-07-23 03:45:56','2026-07-23 03:45:56',NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'tesis'
--

--
-- Dumping routines for database 'tesis'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
