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
INSERT INTO `academic_periods` VALUES (2,'2026-I','I PAO 2026','2026-04-01','2026-09-30','active');
/*!40000 ALTER TABLE `academic_periods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `academic_period_transitions`
--

DROP TABLE IF EXISTS `academic_period_transitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_period_transitions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `closed_period_id` smallint(5) unsigned NOT NULL,
  `activated_period_id` smallint(5) unsigned NOT NULL,
  `performed_by` bigint(20) unsigned NOT NULL,
  `performed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reverted_by` bigint(20) unsigned DEFAULT NULL,
  `reverted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_period_transition_event` (`closed_period_id`,`activated_period_id`,`performed_at`),
  KEY `idx_period_transition_latest` (`performed_at`,`id`),
  KEY `idx_period_transition_actor` (`performed_by`,`performed_at`),
  KEY `idx_period_transition_reverted` (`reverted_at`),
  KEY `fk_period_transition_activated` (`activated_period_id`),
  KEY `fk_period_transition_reverter` (`reverted_by`),
  CONSTRAINT `fk_period_transition_activated` FOREIGN KEY (`activated_period_id`) REFERENCES `academic_periods` (`id`),
  CONSTRAINT `fk_period_transition_closed` FOREIGN KEY (`closed_period_id`) REFERENCES `academic_periods` (`id`),
  CONSTRAINT `fk_period_transition_performer` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_period_transition_reverter` FOREIGN KEY (`reverted_by`) REFERENCES `users` (`id`),
  CONSTRAINT `chk_period_transition_distinct` CHECK (`closed_period_id` <> `activated_period_id`),
  CONSTRAINT `chk_period_transition_reversal` CHECK (`reverted_by` is null and `reverted_at` is null or `reverted_by` is not null and `reverted_at` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  `action_label` varchar(180) DEFAULT NULL,
  `module` varchar(80) DEFAULT NULL,
  `entity_type` varchar(80) NOT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `element_label` varchar(255) DEFAULT NULL,
  `result` enum('correct','failed') NOT NULL DEFAULT 'correct',
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_admin_audit_date` (`created_at`),
  KEY `idx_admin_audit_entity` (`entity_type`,`entity_id`),
  KEY `fk_admin_audit_actor` (`actor_user_id`),
  KEY `idx_admin_activity_filters` (`module`,`action`,`result`,`created_at`),
  KEY `idx_admin_activity_actor_date` (`actor_user_id`,`created_at`),
  KEY `idx_admin_audit_entity_date` (`entity_type`,`entity_id`,`created_at`),
  CONSTRAINT `fk_admin_audit_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=122 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_audit_log`
--

LOCK TABLES `admin_audit_log` WRITE;
/*!40000 ALTER TABLE `admin_audit_log` DISABLE KEYS */;
INSERT INTO `admin_audit_log` VALUES (10,1,'demo_users_imported',NULL,NULL,'user',20,NULL,'correct','{\"demo\":true}',NULL,NULL,'2026-07-19 21:16:28'),(11,1,'demo_teacher_updated',NULL,NULL,'user',26,NULL,'correct','{\"demo\":true}',NULL,NULL,'2026-07-18 21:16:28'),(12,1,'demo_catalog_configured',NULL,NULL,'project_type',NULL,NULL,'correct','{\"demo\":true}',NULL,NULL,'2026-07-17 21:16:28'),(13,1,'project_restored',NULL,NULL,'project',28,NULL,'correct','[]',NULL,NULL,'2026-07-23 05:07:16'),(14,1,'project_restored',NULL,NULL,'project',28,NULL,'correct','[]',NULL,NULL,'2026-07-23 05:07:55'),(15,1,'project_restored',NULL,NULL,'project',25,NULL,'correct','[]',NULL,NULL,'2026-07-23 05:07:56'),(16,1,'user_status_changed',NULL,NULL,'user',72,NULL,'correct','{\"status\":\"blocked\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 07:09:34'),(17,1,'user_status_changed',NULL,NULL,'user',72,NULL,'correct','{\"status\":\"active\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 07:09:40'),(18,1,'user_status_changed',NULL,NULL,'user',72,NULL,'correct','{\"status\":\"blocked\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 18:56:49'),(19,1,'user_status_changed',NULL,NULL,'user',72,NULL,'correct','{\"status\":\"active\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 18:56:57'),(20,1,'user_updated',NULL,NULL,'user',72,NULL,'correct','{\"role\":\"student\",\"status\":\"active\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 19:27:03'),(21,1,'user_updated',NULL,NULL,'user',72,NULL,'correct','{\"role\":\"student\",\"status\":\"active\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 19:27:11'),(22,1,'user_updated',NULL,NULL,'user',72,NULL,'correct','{\"role\":\"student\",\"status\":\"active\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 19:31:01'),(23,1,'user_updated',NULL,NULL,'user',72,NULL,'correct','{\"role\":\"student\",\"status\":\"active\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 19:36:37'),(24,1,'password_reset',NULL,NULL,'user',72,NULL,'correct','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 19:36:42'),(25,1,'password_reset',NULL,NULL,'user',68,NULL,'correct','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 19:43:41'),(26,1,'user_status_changed',NULL,NULL,'user',72,NULL,'correct','{\"status\":\"blocked\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 19:48:08'),(27,1,'user_status_changed',NULL,NULL,'user',72,NULL,'correct','{\"status\":\"active\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 19:48:40'),(28,1,'user_status_changed',NULL,NULL,'user',20,NULL,'correct','{\"status\":\"blocked\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 19:52:40'),(29,1,'user_status_changed',NULL,NULL,'user',20,NULL,'correct','{\"status\":\"active\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 19:52:43'),(37,1,'support_material_updated','Editó material de apoyo','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"changes\":[{\"field\":\"material_type\",\"label\":\"Tipo de material\",\"previous\":\"Guía\",\"new\":\"Guía documental\"}]}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-27 02:11:40'),(38,1,'support_material_updated','Editó material de apoyo','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis hola','correct','{\"changes\":[{\"field\":\"title\",\"label\":\"Título\",\"previous\":\"Guía para la elaboración del perfil de tesis\",\"new\":\"Guía para la elaboración del perfil de tesis hola\"}]}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-27 02:13:51'),(41,1,'support_material.history_cleaned','Eliminó registros antiguos sin detalle','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis hola','correct','{\"schema_version\":1,\"deleted_count\":6,\"reason\":\"legacy_events_without_change_details\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-27 03:02:06'),(42,1,'support_material.updated','Editó la información del material','Repositorio','support_material',2,'Formato de seguimiento de prácticas preprofesionales','correct','{\"schema_version\":1,\"changes\":[{\"field\":\"material_type\",\"label\":\"Tipo de material\",\"old\":\"Formato\",\"new\":\"Formato documental\"},{\"field\":\"full_description\",\"label\":\"Descripción completa\",\"old\":\"Documento editable destinado al seguimiento periódico de las prácticas preprofesionales.\\n\\nPermite registrar actividades, resultados, evidencias y validaciones del responsable institucional.\",\"new\":\"Documento editable destinado al seguimiento periódico de las prácticas preprofesionales.\\r\\n\\r\\nPermite registrar actividades, resultados, evidencias y validaciones del responsable institucional.\"}]}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-27 03:07:34'),(43,1,'support_material.file_added','Agregó un archivo al material','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis _ Material de apoyo.pdf','correct','{\"file_id\":9,\"name\":\"Guía para la elaboración del perfil de tesis _ Material de apoyo.pdf\",\"extension\":\"pdf\",\"mime_type\":\"application\\/pdf\",\"size_bytes\":309745,\"is_primary\":false,\"is_package\":false}','::1','Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36','2026-07-27 06:11:15'),(44,1,'support_material.file_added','Agregó un archivo al material','Repositorio','support_material',1,'Tarea 1.docx','correct','{\"file_id\":10,\"name\":\"Tarea 1.docx\",\"extension\":\"docx\",\"mime_type\":\"application\\/vnd.openxmlformats-officedocument.wordprocessingml.document\",\"size_bytes\":207695,\"is_primary\":false,\"is_package\":false}','::1','Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36','2026-07-27 06:11:44'),(45,1,'support_material.file_added','Agregó un archivo al material','Repositorio','support_material',1,'WhatsApp Image 2026-05-27 at 9.06.00 PM.jpeg','correct','{\"file_id\":11,\"name\":\"WhatsApp Image 2026-05-27 at 9.06.00 PM.jpeg\",\"extension\":\"jpeg\",\"mime_type\":\"image\\/jpeg\",\"size_bytes\":77718,\"is_primary\":false,\"is_package\":false}','::1','Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36','2026-07-27 06:11:44'),(46,1,'support_material.file_added','Agregó un archivo al material','Repositorio','support_material',1,'merged.pdf','correct','{\"file_id\":12,\"name\":\"merged.pdf\",\"extension\":\"pdf\",\"mime_type\":\"application\\/pdf\",\"size_bytes\":520216,\"is_primary\":false,\"is_package\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-27 07:11:19'),(47,1,'support_material.file_removed','Retiró un archivo del material','Repositorio','support_material',1,'Tarea 1.docx','correct','{\"file_id\":10,\"name\":\"Tarea 1.docx\",\"extension\":\"docx\",\"size_bytes\":207695}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-27 07:28:17'),(48,1,'support_material.file_removed','Retiró un archivo del material','Repositorio','support_material',1,'WhatsApp Image 2026-05-27 at 9.06.00 PM.jpeg','correct','{\"file_id\":11,\"name\":\"WhatsApp Image 2026-05-27 at 9.06.00 PM.jpeg\",\"extension\":\"jpeg\",\"size_bytes\":77718}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-27 08:08:43'),(49,1,'support_material.updated','Editó la información del material','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"schema_version\":1,\"changes\":[{\"field\":\"title\",\"label\":\"Título\",\"old\":\"Guía para la elaboración del perfil de tesis hola\",\"new\":\"Guía para la elaboración del perfil de tesis\"}]}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-27 08:18:09'),(50,1,'support_material.file_added','Agregó un archivo al material','Repositorio','support_material',1,'webanimeo.zip','correct','{\"file_id\":13,\"name\":\"webanimeo.zip\",\"extension\":\"zip\",\"mime_type\":\"application\\/zip\",\"size_bytes\":2987356,\"is_primary\":false,\"is_package\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-27 08:24:59'),(51,1,'support_material.file_removed','Retiró un archivo del material','Repositorio','support_material',1,'webanimeo.zip','correct','{\"file_id\":13,\"name\":\"webanimeo.zip\",\"extension\":\"zip\",\"size_bytes\":2987356}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-27 08:25:06'),(52,1,'support_material.file_added','Agregó un archivo al material','Repositorio','support_material',1,'Practicas_María José.zip','correct','{\"file_id\":14,\"name\":\"Practicas_María José.zip\",\"extension\":\"zip\",\"mime_type\":\"application\\/zip\",\"size_bytes\":3623925,\"is_primary\":false,\"is_package\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-27 08:25:31'),(53,1,'support_material.presentation_changed','Cambió el archivo de presentación','Repositorio','support_material',1,'guia_perfil_tesis.pdf','correct','{\"previous_file_id\":1,\"new_file_id\":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-28 03:45:00'),(54,1,'support_material.presentation_removed','Quitó el archivo de presentación','Repositorio','support_material',1,'Archivo #1','correct','{\"previous_file_id\":1,\"new_file_id\":null}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-28 04:11:05'),(55,1,'support_material.presentation_selected','Seleccionó el archivo de presentación','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis _ Material de apoyo.pdf','correct','{\"previous_file_id\":null,\"new_file_id\":9}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-28 04:11:25'),(56,1,'support_material.file_added','Agregó un archivo al material','Repositorio','support_material',1,'01. CARATULA (3).docx','correct','{\"file_id\":15,\"name\":\"01. CARATULA (3).docx\",\"extension\":\"docx\",\"mime_type\":\"application\\/vnd.openxmlformats-officedocument.wordprocessingml.document\",\"size_bytes\":110219,\"is_package\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-28 04:11:42'),(57,1,'support_material.presentation_removed','Quitó el archivo de presentación','Repositorio','support_material',1,'Archivo #9','correct','{\"previous_file_id\":9,\"new_file_id\":null}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-28 04:24:44'),(58,1,'support_material.presentation_selected','Seleccionó el archivo de presentación','Repositorio','support_material',1,'guia_perfil_tesis.pdf','correct','{\"previous_file_id\":null,\"new_file_id\":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-28 04:24:58'),(59,1,'support_material.presentation_changed','Cambió el archivo de presentación','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis _ Material de apoyo.pdf','correct','{\"previous_file_id\":1,\"new_file_id\":9}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-28 04:25:06'),(60,1,'support_material.file_added','Agregó un archivo al material','Repositorio','support_material',1,'4. ACTA DE COMPROMISO PARA EJECUCION DE PRACTICAS (1).docx','correct','{\"file_id\":16,\"name\":\"4. ACTA DE COMPROMISO PARA EJECUCION DE PRACTICAS (1).docx\",\"extension\":\"docx\",\"mime_type\":\"application\\/vnd.openxmlformats-officedocument.wordprocessingml.document\",\"size_bytes\":118190,\"is_package\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-28 05:40:29'),(61,1,'support_material.file_removed','Retiró un archivo del material','Repositorio','support_material',1,'01. CARATULA (3).docx','correct','{\"file_id\":15,\"name\":\"01. CARATULA (3).docx\",\"extension\":\"docx\",\"size_bytes\":110219}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-28 05:40:44'),(62,1,'support_material.file_added','Agregó un archivo al material','Repositorio','support_material',1,'01. CARATULA (3).docx','correct','{\"file_id\":17,\"name\":\"01. CARATULA (3).docx\",\"extension\":\"docx\",\"mime_type\":\"application\\/vnd.openxmlformats-officedocument.wordprocessingml.document\",\"size_bytes\":110219,\"is_package\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-28 05:40:51'),(63,1,'support_material.file_added','Agregó un archivo al material','Repositorio','support_material',1,'10. BITÁCORA DEL ESTUDIANTE (2).docx','correct','{\"file_id\":18,\"name\":\"10. BITÁCORA DEL ESTUDIANTE (2).docx\",\"extension\":\"docx\",\"mime_type\":\"application\\/vnd.openxmlformats-officedocument.wordprocessingml.document\",\"size_bytes\":133085,\"is_package\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-28 06:06:08'),(64,1,'support_material.file_added','Agregó un archivo al material','Repositorio','support_material',1,'6. PLAN DE APRENDIZAJE PRÁCTICO Y DE ROTACIÓN (2).docx','correct','{\"file_id\":19,\"name\":\"6. PLAN DE APRENDIZAJE PRÁCTICO Y DE ROTACIÓN (2).docx\",\"extension\":\"docx\",\"mime_type\":\"application\\/vnd.openxmlformats-officedocument.wordprocessingml.document\",\"size_bytes\":143489,\"is_package\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-28 06:21:35'),(65,1,'support_material.file_added','Agregó un archivo al material','Repositorio','support_material',1,'9. EVALUACIÓN DE DESEMPEÑO POR PARTE DEL TUTOR ACADÉMICO (2).docx','correct','{\"file_id\":20,\"name\":\"9. EVALUACIÓN DE DESEMPEÑO POR PARTE DEL TUTOR ACADÉMICO (2).docx\",\"extension\":\"docx\",\"mime_type\":\"application\\/vnd.openxmlformats-officedocument.wordprocessingml.document\",\"size_bytes\":130975,\"is_package\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-28 06:21:35'),(66,1,'support_material.file_added','Agregó un archivo al material','Repositorio','support_material',1,'5. ACTA SEGURIDAD Y MEDIOS DE PROTECCIÓN EN LA FORMACIÓN PRÁCTICA EN EL ENTORNO LABORAL REAL (1).docx','correct','{\"file_id\":21,\"name\":\"5. ACTA SEGURIDAD Y MEDIOS DE PROTECCIÓN EN LA FORMACIÓN PRÁCTICA EN EL ENTORNO LABORAL REAL (1).docx\",\"extension\":\"docx\",\"mime_type\":\"application\\/vnd.openxmlformats-officedocument.wordprocessingml.document\",\"size_bytes\":125101,\"is_package\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-28 06:27:54'),(67,1,'support_material.file_removed','Retiró un archivo del material','Repositorio','support_material',1,'6. PLAN DE APRENDIZAJE PRÁCTICO Y DE ROTACIÓN (2).docx','correct','{\"file_id\":19,\"name\":\"6. PLAN DE APRENDIZAJE PRÁCTICO Y DE ROTACIÓN (2).docx\",\"extension\":\"docx\",\"size_bytes\":143489}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-28 06:32:26'),(68,1,'support_material.file_removed','Retiró un archivo del material','Repositorio','support_material',1,'4. ACTA DE COMPROMISO PARA EJECUCION DE PRACTICAS (1).docx','correct','{\"file_id\":16,\"name\":\"4. ACTA DE COMPROMISO PARA EJECUCION DE PRACTICAS (1).docx\",\"extension\":\"docx\",\"size_bytes\":118190}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-28 06:38:55'),(69,1,'support_material.file_removed','Retiró un archivo del material','Repositorio','support_material',1,'01. CARATULA (3).docx','correct','{\"file_id\":17,\"name\":\"01. CARATULA (3).docx\",\"extension\":\"docx\",\"size_bytes\":110219}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-28 06:38:55'),(70,1,'support_material.file_removed','Retiró un archivo del material','Repositorio','support_material',1,'5. ACTA SEGURIDAD Y MEDIOS DE PROTECCIÓN EN LA FORMACIÓN PRÁCTICA EN EL ENTORNO LABORAL REAL (1).docx','correct','{\"file_id\":21,\"name\":\"5. ACTA SEGURIDAD Y MEDIOS DE PROTECCIÓN EN LA FORMACIÓN PRÁCTICA EN EL ENTORNO LABORAL REAL (1).docx\",\"extension\":\"docx\",\"size_bytes\":125101}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-28 06:38:55'),(71,1,'support_material.file_added','Agregó un archivo al material','Repositorio','support_material',1,'webanimeo.zip','correct','{\"file_id\":22,\"name\":\"webanimeo.zip\",\"extension\":\"zip\",\"mime_type\":\"application\\/zip\",\"size_bytes\":2987356,\"is_package\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-28 07:00:00'),(72,1,'support_material.file_removed','Retiró un archivo del material','Repositorio','support_material',1,'webanimeo.zip','correct','{\"file_id\":22,\"name\":\"webanimeo.zip\",\"extension\":\"zip\",\"size_bytes\":2987356}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-28 07:00:21'),(73,1,'support_material.file_added','Agregó un archivo al material','Repositorio','support_material',1,'Practicas2.zip','correct','{\"file_id\":23,\"name\":\"Practicas2.zip\",\"extension\":\"zip\",\"mime_type\":\"application\\/zip\",\"size_bytes\":4750451,\"is_package\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-28 07:03:35'),(74,1,'support_material.file_added','Agregó un archivo al material','Repositorio','support_material',1,'Practicas.zip','correct','{\"file_id\":24,\"name\":\"Practicas.zip\",\"extension\":\"zip\",\"mime_type\":\"application\\/zip\",\"size_bytes\":19953169,\"is_package\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-28 07:03:35'),(75,1,'support_material.updated','Editó la información del material','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"schema_version\":1,\"changes\":[{\"field\":\"material_type\",\"label\":\"Tipo de material\",\"old\":\"Guía documental\",\"new\":\"Guía\"}]}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 04:45:05'),(76,1,'support_material.presentation_changed','Cambió el archivo de presentación','Repositorio','support_material',1,'guia_perfil_tesis.pdf','correct','{\"previous_file_id\":9,\"new_file_id\":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 05:21:57'),(77,1,'support_material.file_added','Agregó un archivo al material','Repositorio','support_material',1,'IdeaPPrincipal.docx','correct','{\"file_id\":26,\"name\":\"IdeaPPrincipal.docx\",\"extension\":\"docx\",\"mime_type\":\"application\\/vnd.openxmlformats-officedocument.wordprocessingml.document\",\"size_bytes\":226681,\"is_package\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 05:22:21'),(79,1,'support_material.presentation_changed','Cambió el archivo de presentación','Repositorio','support_material',1,'lista_de_verificacion_para_elaboracion_del_perfil_de_tesis.txt','correct','{\"previous_file_id\":1,\"new_file_id\":2,\"previous_name\":\"guia_perfil_tesis.pdf\",\"new_name\":\"lista_de_verificacion_para_elaboracion_del_perfil_de_tesis.txt\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 05:43:39'),(80,1,'support_material.file_removed','Retiró un archivo del material','Repositorio','support_material',1,'merged.pdf','correct','{\"file_id\":12,\"name\":\"merged.pdf\",\"extension\":\"pdf\",\"size_bytes\":520216,\"presentation\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 05:43:56'),(81,1,'support_material.file_restored','Restauró un archivo del material','Repositorio','support_material',1,'5. ACTA SEGURIDAD Y MEDIOS DE PROTECCIÓN EN LA FORMACIÓN PRÁCTICA EN EL ENTORNO LABORAL REAL (1).docx','correct','{\"material_id\":1,\"file_id\":21,\"original_name\":\"5. ACTA SEGURIDAD Y MEDIOS DE PROTECCIÓN EN LA FORMACIÓN PRÁCTICA EN EL ENTORNO LABORAL REAL (1).docx\",\"final_name\":\"5. ACTA SEGURIDAD Y MEDIOS DE PROTECCIÓN EN LA FORMACIÓN PRÁCTICA EN EL ENTORNO LABORAL REAL (1).docx\",\"deleted_at\":\"2026-07-28T06:38:55+00:00\",\"deleted_by\":1,\"deleted_by_name\":\"Administrador de pruebas\",\"name_conflict\":false,\"renamed\":false,\"restore_hours\":24,\"result\":\"correct\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 05:44:05'),(82,1,'support_material.file_purged','Eliminó definitivamente un archivo retirado','Repositorio','support_material',1,'merged.pdf','correct','{\"material_id\":1,\"file_ids\":[12],\"files\":[{\"file_id\":12,\"original_name\":\"merged.pdf\",\"extension\":\"pdf\",\"size_bytes\":520216,\"created_by\":1,\"created_by_name\":\"Administrador de pruebas\",\"deleted_at\":\"2026-07-29T05:43:56+00:00\",\"deleted_by\":1,\"deleted_by_name\":\"Administrador de pruebas\"}],\"purged_at\":\"2026-07-29T05:44:35+00:00\",\"purged_by\":1,\"restore_hours\":24,\"result\":\"correct\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 05:44:35'),(83,1,'support_material_withdrawn','Retiró material de apoyo','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"previous_status\":\"published\",\"new_status\":\"withdrawn\",\"reason_code\":\"outdated\",\"reason\":\"Información desactualizada\",\"reason_detail\":\"\",\"republication\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 06:27:16'),(84,1,'support_material_published','Publicó material de apoyo','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"previous_status\":\"withdrawn\",\"new_status\":\"published\",\"reason_code\":\"\",\"reason\":\"\",\"reason_detail\":\"\",\"republication\":true}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 06:28:29'),(85,1,'support_material_withdrawn','Retiró material de apoyo','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"previous_status\":\"published\",\"new_status\":\"withdrawn\",\"reason_code\":\"outdated\",\"reason\":\"Información desactualizada\",\"reason_detail\":\"\",\"republication\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 06:33:51'),(86,1,'support_material_published','Publicó material de apoyo','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"previous_status\":\"withdrawn\",\"new_status\":\"published\",\"reason_code\":\"review_completed\",\"reason\":\"Revisión administrativa finalizada\",\"reason_detail\":\"\",\"republication\":true}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 06:34:23'),(87,1,'support_material_availability_changed','Marcó material como no disponible','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"previous_available\":true,\"is_available\":false,\"reason_code\":\"files_pending\",\"reason\":\"Archivos pendientes de corrección\",\"reason_detail\":\"\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 06:34:37'),(88,1,'support_material_availability_changed','Marcó material como disponible','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"previous_available\":false,\"is_available\":true,\"reason_code\":\"files_verified\",\"reason\":\"Archivos verificados\",\"reason_detail\":\"\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 06:35:12'),(89,1,'support_material_availability_changed','Marcó material como no disponible','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"previous_available\":true,\"is_available\":false,\"reason_code\":\"administrative_review\",\"reason\":\"Revisión administrativa\",\"reason_detail\":\"\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 06:35:22'),(90,1,'support_material_withdrawn','Retiró material de apoyo','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"previous_status\":\"published\",\"new_status\":\"withdrawn\",\"reason_code\":\"other\",\"reason\":\"Otro motivo\",\"reason_detail\":\"NO MÁS\",\"republication\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 06:49:21'),(91,1,'support_material_published','Publicó material de apoyo','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"previous_status\":\"withdrawn\",\"new_status\":\"published\",\"reason_code\":\"\",\"reason\":\"\",\"reason_detail\":\"\",\"republication\":true}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 06:50:03'),(92,1,'support_material_availability_changed','Marcó material como disponible','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"previous_available\":false,\"is_available\":true,\"reason_code\":\"files_verified\",\"reason\":\"Archivos verificados\",\"reason_detail\":\"\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 06:53:21'),(93,1,'support_material_availability_changed','Marcó material como no disponible','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"previous_available\":true,\"is_available\":false,\"reason_code\":\"files_pending\",\"reason\":\"Archivos pendientes de corrección\",\"reason_detail\":\"\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 06:53:59'),(94,1,'support_material_withdrawn','Retiró material de apoyo','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"previous_status\":\"published\",\"new_status\":\"withdrawn\",\"reason_code\":\"outdated\",\"reason\":\"Información desactualizada\",\"reason_detail\":\"\",\"republication\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 07:01:50'),(95,1,'support_material_published','Publicó material de apoyo','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"previous_status\":\"withdrawn\",\"new_status\":\"published\",\"reason_code\":\"\",\"reason\":\"\",\"reason_detail\":\"\",\"republication\":true}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 07:07:42'),(96,1,'support_material_withdrawn','Retiró material de apoyo','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"previous_status\":\"published\",\"new_status\":\"withdrawn\",\"reason_code\":\"pending_review\",\"reason\":\"Material pendiente de revisión\",\"reason_detail\":\"\",\"republication\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 07:08:00'),(97,1,'support_material_published','Publicó material de apoyo','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"previous_status\":\"withdrawn\",\"new_status\":\"published\",\"reason_code\":\"\",\"reason\":\"\",\"reason_detail\":\"\",\"republication\":true}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 07:18:07'),(98,1,'support_material_withdrawn','Retiró material de apoyo','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"previous_status\":\"published\",\"new_status\":\"withdrawn\",\"reason_code\":\"pending_review\",\"reason\":\"Material pendiente de revisión\",\"reason_detail\":\"\",\"republication\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 07:18:36'),(99,1,'support_material_published','Publicó material de apoyo','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"previous_status\":\"withdrawn\",\"new_status\":\"published\",\"reason_code\":\"\",\"reason\":\"\",\"reason_detail\":\"\",\"republication\":true}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 07:18:39'),(106,1,'support_material.trashed','Envió material de apoyo a la Papelera','Papelera','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"material_id\":1,\"title\":\"Guía para la elaboración del perfil de tesis\",\"material_type\":\"Guía\",\"category_id\":1,\"category\":\"Tesis\",\"reason_code\":\"replaced\",\"reason\":\"Material reemplazado\",\"reason_detail\":\"\",\"previous_status\":\"published\",\"new_status\":\"Papelera\",\"origin\":\"Repositorio\",\"destination\":\"trash\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 07:36:57'),(107,1,'support_material_restored','Restauró material de apoyo desde la Papelera','Papelera','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"previous_status\":\"Papelera\",\"new_status\":\"published\",\"previous_trash_reason\":\"Material reemplazado\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 07:37:27'),(108,1,'support_material.trashed','Envió material de apoyo a la Papelera','Papelera','support_material',2,'Formato de seguimiento de prácticas preprofesionales','correct','{\"material_id\":2,\"title\":\"Formato de seguimiento de prácticas preprofesionales\",\"material_type\":\"Formato documental\",\"category_id\":2,\"category\":\"Prácticas\",\"reason_code\":\"other\",\"reason\":\"Otro motivo\",\"reason_detail\":\"POR QUE SÍ\",\"previous_status\":\"published\",\"new_status\":\"Papelera\",\"origin\":\"Repositorio\",\"destination\":\"trash\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 07:42:28'),(109,1,'support_material_withdrawn','Retiró material de apoyo','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"previous_status\":\"published\",\"new_status\":\"withdrawn\",\"reason_code\":\"incomplete_files\",\"reason\":\"Archivos incompletos\",\"reason_detail\":\"\",\"republication\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 07:44:43'),(110,1,'support_material_published','Publicó material de apoyo','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"previous_status\":\"withdrawn\",\"new_status\":\"published\",\"reason_code\":\"\",\"reason\":\"\",\"reason_detail\":\"\",\"republication\":true}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 07:52:52'),(111,1,'support_material_withdrawn','Retiró material de apoyo','Repositorio','support_material',3,'Instructivo para proyectos PIS','correct','{\"previous_status\":\"published\",\"new_status\":\"withdrawn\",\"reason_code\":\"outdated\",\"reason\":\"Información desactualizada\",\"reason_detail\":\"\",\"republication\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 08:05:13'),(112,1,'support_material_published','Publicó material de apoyo','Repositorio','support_material',3,'Instructivo para proyectos PIS','correct','{\"previous_status\":\"withdrawn\",\"new_status\":\"published\",\"reason_code\":\"\",\"reason\":\"\",\"reason_detail\":\"\",\"republication\":true}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 08:05:25'),(113,1,'support_material_withdrawn','Retiró material de apoyo','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"previous_status\":\"published\",\"new_status\":\"withdrawn\",\"reason_code\":\"incomplete_files\",\"reason\":\"Archivos incompletos\",\"reason_detail\":\"\",\"republication\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 08:05:45'),(114,1,'support_material_published','Publicó material de apoyo','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"previous_status\":\"withdrawn\",\"new_status\":\"published\",\"previous_available\":false,\"is_available\":true,\"reason_code\":\"state_consistency_repair\",\"reason\":\"Normalización de estado y disponibilidad\",\"reason_detail\":\"Reactivación mediante la transición administrativa de publicación\",\"republication\":true}',NULL,NULL,'2026-07-30 04:51:53'),(115,1,'support_material_withdrawn','Retiró material de apoyo','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"previous_status\":\"published\",\"new_status\":\"withdrawn\",\"previous_available\":true,\"is_available\":false,\"reason_code\":\"outdated\",\"reason\":\"Información desactualizada\",\"reason_detail\":\"\",\"republication\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-30 05:05:40'),(116,1,'support_material_published','Publicó material de apoyo','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"previous_status\":\"withdrawn\",\"new_status\":\"published\",\"previous_available\":false,\"is_available\":true,\"reason_code\":\"corrections_completed\",\"reason\":\"Correcciones completadas\",\"reason_detail\":\"\",\"republication\":true}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-30 05:05:56'),(117,1,'support_material.updated','Editó la información del material','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"schema_version\":1,\"changes\":[{\"field\":\"material_type\",\"label\":\"Tipo de material\",\"old\":\"Guía\",\"new\":\"Guía documental\"},{\"field\":\"keywords\",\"label\":\"Palabras clave\",\"old\":[\"Perfil de tesis\",\"Titulación\",\"Metodología\"],\"new\":[\"Tesis\",\"Perfil de tesis\",\"Titulación\",\"Metodología\"]}]}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-30 06:08:48'),(118,1,'support_material.updated','Editó la información del material','Repositorio','support_material',1,'Guía para la elaboración del perfil de tesis','correct','{\"schema_version\":1,\"changes\":[{\"field\":\"keywords\",\"label\":\"Palabras clave\",\"old\":[\"Tesis\",\"Perfil de tesis\",\"Titulación\",\"Metodología\"],\"new\":[\"Tesis\",\"Perfil de tesis\"]}]}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-30 06:33:23'),(119,1,'support_material.presentation_removed','Quitó el archivo de presentación','Repositorio','support_material',1,'Archivo #2','correct','{\"previous_file_id\":2,\"new_file_id\":null,\"previous_name\":\"lista_de_verificacion_para_elaboracion_del_perfil_de_tesis.txt\",\"new_name\":null}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-30 06:35:37'),(120,1,'support_material.presentation_selected','Seleccionó el archivo de presentación','Repositorio','support_material',1,'guia_perfil_tesis.pdf','correct','{\"previous_file_id\":null,\"new_file_id\":1,\"previous_name\":null,\"new_name\":\"guia_perfil_tesis.pdf\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-30 06:35:48'),(121,1,'support_material.file_replaced','Reemplazó un archivo del material','Repositorio','support_material',1,'horario2.jpg','correct','{\"file_id\":18,\"version_id\":1,\"previous_file\":{\"name\":\"10. BITÁCORA DEL ESTUDIANTE (2).docx\",\"extension\":\"docx\",\"mime_type\":\"application\\/vnd.openxmlformats-officedocument.wordprocessingml.document\",\"size_bytes\":133085},\"new_file\":{\"name\":\"horario2.jpg\",\"extension\":\"jpg\",\"mime_type\":\"image\\/jpeg\",\"size_bytes\":1374656},\"presentation_unchanged\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-30 07:07:23');
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
  `deduplication_key` varchar(190) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notifications_user_deduplication` (`user_id`,`deduplication_key`),
  KEY `idx_notifications_user_active` (`user_id`,`deleted_at`,`created_at`),
  KEY `idx_notifications_project` (`project_id`),
  CONSTRAINT `fk_notifications_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (10,24,22,'observation','Tienes observaciones nuevas','Revisa los comentarios registrados por tu tutora.','index.php?page=project-detail&id=22','Abrir proyecto','{\"demo\":true}',NULL,0,NULL,'2026-07-18 21:16:28','2026-07-19 21:16:28',NULL,NULL),(11,22,25,'status_change','Proyecto aprobado','El proyecto está listo para cargar sus documentos finales.','index.php?page=project-detail&id=25','Abrir proyecto','{\"demo\":true}',NULL,0,NULL,'2026-07-18 21:16:28','2026-07-19 21:16:28',NULL,NULL),(12,21,27,'repository','Proyecto publicado','El proyecto ya se encuentra publicado en el repositorio.','index.php?page=project-detail&id=27','Abrir proyecto','{\"demo\":true}',NULL,0,NULL,'2026-07-18 21:16:28','2026-07-19 21:16:28',NULL,NULL),(13,68,NULL,'system','Bienvenido al entorno de pruebas','Tu cuenta de estudiante esta lista para probar las funciones de la plataforma.','index.php?page=dashboard','Ir al inicio','{\"source\": \"test_setup\"}',NULL,0,NULL,'2026-07-21 19:30:21','2026-07-21 19:30:21',NULL,NULL),(14,68,NULL,'reminder','Revisa tus actividades pendientes','Consulta el panel para familiarizarte con entregas, observaciones y fechas academicas.','index.php?page=dashboard','Revisar panel','{\"source\": \"test_setup\"}',NULL,0,NULL,'2026-07-21 19:30:21','2026-07-21 19:30:21',NULL,NULL),(15,69,NULL,'review','Cuenta docente preparada','Tu cuenta docente esta lista para revisar proyectos y registrar observaciones de prueba.','index.php?page=dashboard','Ir al inicio','{\"source\": \"test_setup\"}',NULL,0,NULL,'2026-07-21 19:30:21','2026-07-21 19:30:21',NULL,NULL),(16,1,NULL,'system','Datos demostrativos preparados','El listado contiene registros suficientes para comprobar la paginación.',NULL,NULL,'{\"source\":\"pagination_demo\",\"item\":1}',NULL,1,'2026-07-23 19:43:14','2026-07-23 03:45:56','2026-07-23 19:43:14',NULL,NULL),(17,1,NULL,'reminder','Revisión de proyecto pendiente','Comprueba el segundo bloque de resultados del listado.',NULL,NULL,'{\"source\":\"pagination_demo\",\"item\":2}',NULL,1,'2026-07-23 03:55:53','2026-07-23 02:45:56','2026-07-23 03:55:53',NULL,NULL),(18,1,NULL,'status_change','Proyecto actualizado','Un proyecto demostrativo cambió de estado.',NULL,NULL,'{\"source\":\"pagination_demo\",\"item\":3}',NULL,1,NULL,'2026-07-23 01:45:56','2026-07-23 03:45:56',NULL,NULL),(19,1,NULL,'repository','Proyecto publicado','Hay un nuevo proyecto disponible en el repositorio.',NULL,NULL,'{\"source\":\"pagination_demo\",\"item\":4}',NULL,1,'2026-07-23 03:56:05','2026-07-23 00:45:56','2026-07-23 03:56:05',NULL,NULL),(20,1,NULL,'review','Revisión completada','La revisión académica fue registrada correctamente.',NULL,NULL,'{\"source\":\"pagination_demo\",\"item\":5}',NULL,1,NULL,'2026-07-22 23:45:56','2026-07-23 03:45:56',NULL,NULL),(21,1,NULL,'observation','Nueva observación','Se agregó una observación al documento demostrativo.',NULL,NULL,'{\"source\":\"pagination_demo\",\"item\":6}',NULL,1,'2026-07-23 05:33:57','2026-07-22 22:45:56','2026-07-23 05:33:57',NULL,NULL),(22,1,NULL,'system','Catálogo actualizado','Los datos institucionales fueron sincronizados.',NULL,NULL,'{\"source\":\"pagination_demo\",\"item\":7}',NULL,1,NULL,'2026-07-22 03:45:56','2026-07-23 03:45:56',NULL,NULL),(23,1,NULL,'reminder','Actividad próxima','Existe una actividad académica programada.',NULL,NULL,'{\"source\":\"pagination_demo\",\"item\":8}',NULL,0,NULL,'2026-07-21 03:45:56','2026-07-23 03:45:56',NULL,NULL),(24,1,NULL,'status_change','Estado aprobado','El proyecto demostrativo fue aprobado.',NULL,NULL,'{\"source\":\"pagination_demo\",\"item\":9}',NULL,1,NULL,'2026-07-20 03:45:56','2026-07-23 03:45:56',NULL,NULL),(25,1,NULL,'repository','Documento disponible','El documento final ya puede consultarse.',NULL,NULL,'{\"source\":\"pagination_demo\",\"item\":10}',NULL,0,NULL,'2026-07-19 03:45:56','2026-07-23 03:45:56',NULL,NULL),(26,1,NULL,'review','Comentarios registrados','La tutora agregó comentarios de revisión.',NULL,NULL,'{\"source\":\"pagination_demo\",\"item\":11}',NULL,0,NULL,'2026-07-18 03:45:56','2026-07-23 03:45:56',NULL,NULL),(27,1,NULL,'system','Prueba de segunda página','Esta notificación permite verificar la navegación entre páginas.',NULL,NULL,'{\"source\":\"pagination_demo\",\"item\":12}',NULL,0,NULL,'2026-07-17 03:45:56','2026-07-23 03:45:56',NULL,NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_audit_log`
--

LOCK TABLES `project_audit_log` WRITE;
/*!40000 ALTER TABLE `project_audit_log` DISABLE KEYS */;
INSERT INTO `project_audit_log` VALUES (10,22,1,'delivery_submitted','delivery',4,NULL,'{\"demo\":true}','Actividad demostrativa',NULL,NULL,'2026-07-18 21:16:28'),(11,25,1,'project_approved','project',25,NULL,'{\"demo\":true}','Actividad demostrativa',NULL,NULL,'2026-07-18 21:16:28'),(12,27,1,'project_published','project',27,NULL,'{\"demo\":true}','Actividad demostrativa',NULL,NULL,'2026-07-18 21:16:28'),(13,38,1,'project_updated','project',38,'{\"id\":38,\"title\":\"Monitoreo de infraestructura de red\",\"status\":\"published\"}','{\"title\":\"Monitoreo de infraestructura de red\",\"subtitle\":\"Prueba de paginación 10\",\"project_type_id\":2,\"career_id\":1,\"academic_period_id\":2,\"tutor_id\":70,\"status\":\"approved\"}',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 03:49:05'),(14,38,1,'project_updated','project',38,'{\"id\":38,\"title\":\"Monitoreo de infraestructura de red\",\"status\":\"approved\"}','{\"title\":\"Monitoreo de infraestructura de red\",\"subtitle\":\"Prueba de paginación 10\",\"project_type_id\":2,\"career_id\":1,\"academic_period_id\":2,\"tutor_id\":28,\"status\":\"approved\"}',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 03:49:12'),(15,38,1,'project_updated','project',38,'{\"id\":38,\"title\":\"Monitoreo de infraestructura de red\",\"status\":\"approved\"}','{\"title\":\"Monitoreo de infraestructura de redd\",\"subtitle\":\"Prueba de paginación 100\",\"project_type_id\":3,\"career_id\":1,\"academic_period_id\":2,\"tutor_id\":28,\"status\":\"approved\"}',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 03:49:22'),(16,25,1,'project_updated','project',25,'{\"id\":25,\"code\":\"VIN-2026-001\",\"title\":\"Alfabetización digital para emprendedores locales\",\"status\":\"approved\",\"project_type_id\":4,\"created_at\":\"2026-06-14 21:16:28\"}','{\"title\":\"Alfabetización digital para emprendedores locales\",\"subtitle\":\"Proyecto de vinculación con herramientas de comercio electrónico\",\"project_type_id\":4,\"career_id\":1,\"academic_period_id\":2,\"tutor_id\":71,\"status\":\"approved\",\"code\":\"VIN-2026-001\"}',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 04:02:36'),(17,25,1,'project_trashed','project',25,'{\"id\":25,\"title\":\"Alfabetización digital para emprendedores locales\",\"status\":\"approved\"}','{\"deleted\":true}','Registro creado por error.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 05:07:04'),(18,28,1,'project_trashed','project',28,'{\"id\":28,\"title\":\"Prototipo descartado de control de asistencia\",\"status\":\"development\"}','{\"deleted\":true}','No más xd','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 05:07:35'),(19,25,1,'project_published','project',25,'{\"id\":25,\"title\":\"Alfabetización digital para emprendedores locales\",\"status\":\"approved\",\"published_at\":null,\"type_code\":\"community\"}','{\"status\":\"published\"}',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-24 04:18:51'),(20,25,1,'project_unpublished','project',25,'{\"id\":25,\"title\":\"Alfabetización digital para emprendedores locales\",\"status\":\"published\",\"published_at\":\"2026-07-24 04:18:51\",\"type_code\":\"community\"}','{\"status\":\"approved\"}',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-24 04:42:12'),(21,25,1,'project_republished','project',25,'{\"id\":25,\"title\":\"Alfabetización digital para emprendedores locales\",\"status\":\"approved\",\"published_at\":null,\"type_code\":\"community\"}','{\"status\":\"published\"}','Restauración de una publicación retirada','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-24 04:49:55'),(22,25,1,'project_unpublished','project',25,'{\"id\":25,\"title\":\"Alfabetización digital para emprendedores locales\",\"status\":\"published\",\"published_at\":\"2026-07-24 04:49:55\",\"type_code\":\"community\"}','{\"status\":\"approved\"}',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-24 04:59:37'),(23,25,1,'project_republished','project',25,'{\"id\":25,\"title\":\"Alfabetización digital para emprendedores locales\",\"status\":\"approved\",\"published_at\":null,\"type_code\":\"community\"}','{\"status\":\"published\"}','Restauración de una publicación retirada','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-24 04:59:50'),(24,25,1,'project_unpublished','project',25,'{\"id\":25,\"title\":\"Alfabetización digital para emprendedores locales\",\"status\":\"published\",\"published_at\":\"2026-07-24 04:59:50\",\"type_code\":\"community\"}','{\"status\":\"approved\"}',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-24 05:50:25'),(25,25,1,'project_republished','project',25,'{\"id\":25,\"title\":\"Alfabetización digital para emprendedores locales\",\"status\":\"approved\",\"published_at\":null,\"type_code\":\"community\"}','{\"status\":\"published\"}','Restauración de una publicación retirada','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-24 05:50:36'),(26,25,1,'project_unpublished','project',25,'{\"id\":25,\"title\":\"Alfabetización digital para emprendedores locales\",\"status\":\"published\",\"published_at\":\"2026-07-24 05:50:36\",\"type_code\":\"community\"}','{\"status\":\"approved\"}',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-24 05:50:40'),(27,25,1,'project_republished','project',25,'{\"id\":25,\"title\":\"Alfabetización digital para emprendedores locales\",\"status\":\"approved\",\"published_at\":null,\"type_code\":\"community\"}','{\"status\":\"published\"}','Restauración de una publicación retirada','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-24 05:50:44');
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
  `status` enum('submitted','under_review','corrections_requested','approved') NOT NULL DEFAULT 'submitted',
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
INSERT INTO `project_deliveries` VALUES (4,22,66,1,'Informe de avance v1','Primera versión para revisión.','corrections_requested',24,'2026-07-13 21:16:28');
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
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `uploaded_by` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `purged_at` datetime DEFAULT NULL,
  `purged_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `storage_name` (`storage_name`),
  KEY `idx_files_project` (`project_id`,`category`),
  KEY `fk_files_delivery` (`delivery_id`),
  KEY `fk_files_user` (`uploaded_by`),
  KEY `idx_project_file_restore_window` (`project_id`,`deleted_at`,`purged_at`),
  KEY `fk_project_file_deleted_by` (`deleted_by`),
  KEY `fk_project_file_purged_by` (`purged_by`),
  CONSTRAINT `fk_files_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `project_deliveries` (`id`),
  CONSTRAINT `fk_files_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_files_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_project_file_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_project_file_purged_by` FOREIGN KEY (`purged_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_files`
--

LOCK TABLES `project_files` WRITE;
/*!40000 ALTER TABLE `project_files` DISABLE KEYS */;
INSERT INTO `project_files` (`id`,`project_id`,`delivery_id`,`category`,`original_name`,`storage_name`,`storage_path`,`mime_type`,`extension`,`size_bytes`,`checksum_sha256`,`uploaded_by`,`created_at`,`deleted_at`) VALUES (14,22,4,'delivery','Informe_avance_v1.pdf','674e5fb94b148d8e2adddeec94ccb53dba3bcdae965be1697f826b50bfab6ec5.pdf','storage/private/projects/22/674e5fb94b148d8e2adddeec94ccb53dba3bcdae965be1697f826b50bfab6ec5.pdf','application/pdf','pdf',673,'4c7f6e0bbd226d162aad3b04d39ec6f7fbc5ce461708964b1798684b25a3d7ee',24,'2026-07-19 21:16:28',NULL),(15,22,4,'annex','Anexos_investigacion.zip','7764654b1cd99ebbf7c4ec33a86c7969f316b5cee5b4ff18ca8a9549a70e689e.zip','storage/private/projects/22/7764654b1cd99ebbf7c4ec33a86c7969f316b5cee5b4ff18ca8a9549a70e689e.zip','application/zip','zip',522,'dd3a324409a2b947d92fdceb8adf7e7ac277d4e0dcb723d3455be6769291efe9',24,'2026-07-19 21:16:28',NULL),(16,25,NULL,'final','Informe_final_vinculacion.docx','fdfe028a320d8a1fe44bc7fef86eab8a9e53952bdc182794864a0955961e66ce.docx','storage/private/projects/25/fdfe028a320d8a1fe44bc7fef86eab8a9e53952bdc182794864a0955961e66ce.docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document','docx',1335,'b9d389ee48614041ffa2b9b84759f00a55511fdb7b01e601faae8e9b3878798b',22,'2026-07-19 21:16:28',NULL),(17,26,NULL,'final','Trabajo_titulacion_final.pdf','ee19c560ba20ac4c33055303f83c0cdd39b1f453de6a8a7249ae4b282487a192.pdf','storage/private/projects/26/ee19c560ba20ac4c33055303f83c0cdd39b1f453de6a8a7249ae4b282487a192.pdf','application/pdf','pdf',680,'840a88ca3f4b780bf9f940ea09d76da8ddb1cf0f9bf8bf9ebabffaba056efdb4',24,'2026-07-19 21:16:28',NULL),(18,27,NULL,'final','Informe_proyecto_publicado.pdf','8e479ac38f49cf400966e9b37f0e2069be031c964ba942fff7667e332322c55e.pdf','storage/private/projects/27/8e479ac38f49cf400966e9b37f0e2069be031c964ba942fff7667e332322c55e.pdf','application/pdf','pdf',682,'82e9afdd04b82b3c0eae120f5feb6a9a1bf73806b086f078f942dc282d31ece2',21,'2026-07-19 21:16:28',NULL),(19,27,NULL,'source','Codigo_fuente_demostrativo.zip','d77af8cffbd61371dd0981b387ebb4929638b63347d17ef0d1244fe0144ecb26.zip','storage/private/projects/27/d77af8cffbd61371dd0981b387ebb4929638b63347d17ef0d1244fe0144ecb26.zip','application/zip','zip',528,'7c06f4a68a6a8a06876b5aad658f87b99279d5622edbd64e268bbff7e5fa4545',21,'2026-07-19 21:16:28',NULL);
/*!40000 ALTER TABLE `project_files` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_file_versions`
--
DROP TABLE IF EXISTS `project_file_versions`;
CREATE TABLE `project_file_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `file_id` bigint(20) unsigned NOT NULL,
  `project_id` bigint(20) unsigned NOT NULL,
  `version_number` int(10) unsigned NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `storage_name` varchar(190) NOT NULL,
  `storage_path` varchar(500) NOT NULL,
  `extension` varchar(12) NOT NULL,
  `mime_type` varchar(120) NOT NULL,
  `size_bytes` bigint(20) unsigned NOT NULL DEFAULT 0,
  `checksum_sha256` char(64) NOT NULL,
  `replaced_by` bigint(20) unsigned DEFAULT NULL,
  `replaced_at` datetime NOT NULL DEFAULT current_timestamp(),
  `replacement_reason` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_file_version_number` (`file_id`,`version_number`),
  KEY `idx_project_file_version_project` (`project_id`,`replaced_at`),
  KEY `fk_project_file_version_actor` (`replaced_by`),
  CONSTRAINT `fk_project_file_version_file` FOREIGN KEY (`file_id`) REFERENCES `project_files` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_project_file_version_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_project_file_version_actor` FOREIGN KEY (`replaced_by`) REFERENCES `users` (`id`),
  CONSTRAINT `chk_project_file_version_positive` CHECK (`version_number` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `keywords`
--

DROP TABLE IF EXISTS `keywords`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `keywords` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `normalized_name` varchar(120) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_keywords_normalized_name` (`normalized_name`),
  KEY `idx_keywords_active_name` (`is_active`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `keywords`
--

LOCK TABLES `keywords` WRITE;
/*!40000 ALTER TABLE `keywords` DISABLE KEYS */;
/*!40000 ALTER TABLE `keywords` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_keywords`
--

DROP TABLE IF EXISTS `project_keywords`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_keywords` (
  `project_id` bigint(20) unsigned NOT NULL,
  `keyword_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`project_id`,`keyword_id`),
  KEY `idx_project_keywords_keyword` (`keyword_id`),
  CONSTRAINT `fk_project_keywords_keyword` FOREIGN KEY (`keyword_id`) REFERENCES `keywords` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_project_keywords_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_keywords`
--

LOCK TABLES `project_keywords` WRITE;
/*!40000 ALTER TABLE `project_keywords` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_keywords` ENABLE KEYS */;
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
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `presentation_file_id` bigint(20) unsigned DEFAULT NULL,
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
  KEY `idx_project_presentation` (`presentation_file_id`),
  KEY `idx_project_repository_visibility` (`status`,`is_available`,`deleted_at`),
  CONSTRAINT `fk_project_presentation` FOREIGN KEY (`presentation_file_id`) REFERENCES `project_files` (`id`),
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
INSERT INTO `projects` VALUES (22,'TIT-2026-001',1,1,2,'Sistema inteligente para la gestión de turnos médicos','Plataforma web accesible para centros de salud','Proyecto demostrativo con información realista para validar las herramientas administrativas.','group',5,9,28,28,'under_review','review',NULL,NULL,NULL,NULL,1,14,24,'2026-06-14 21:16:28','2026-07-27 22:43:43',NULL,NULL,NULL),(23,'PIS-2026-001',2,1,2,'Aplicación móvil para rutas de transporte urbano','Seguimiento de recorridos y alertas para usuarios','Proyecto demostrativo con información realista para validar las herramientas administrativas.','group',5,7,26,26,'development','development',NULL,NULL,NULL,NULL,1,NULL,20,'2026-06-14 21:16:28','2026-07-23 03:53:54',NULL,NULL,NULL),(24,'PRA-2026-001',3,1,2,'Automatización del inventario de equipos tecnológicos','Práctica preprofesional aplicada al control institucional','Proyecto demostrativo con información realista para validar las herramientas administrativas.','group',5,8,27,27,'development','development',NULL,NULL,NULL,NULL,1,NULL,23,'2026-06-14 21:16:28','2026-07-23 03:53:54',NULL,NULL,NULL),(25,'VIN-2026-001',4,1,2,'Alfabetización digital para emprendedores locales','Proyecto de vinculación con herramientas de comercio electrónico','Proyecto demostrativo con información realista para validar las herramientas administrativas.','group',5,7,26,71,'published','final_documents','2026-07-17 21:16:28',NULL,NULL,'2026-07-24 05:50:44',1,16,22,'2026-06-14 21:16:28','2026-07-27 22:43:43',NULL,NULL,NULL),(26,'TIT-2026-002',1,1,2,'Modelo de detección temprana de riesgos académicos','Análisis de indicadores para acompañamiento estudiantil','Proyecto demostrativo con información realista para validar las herramientas administrativas.','group',5,9,28,28,'defense','defense',NULL,NULL,'2026-07-11 21:16:28',NULL,1,17,24,'2026-06-14 21:16:28','2026-07-27 22:43:43',NULL,NULL,NULL),(27,'PIS-2026-002',2,1,2,'Panel de monitoreo energético para laboratorios','Visualización en tiempo real de consumo eléctrico','Proyecto demostrativo con información realista para validar las herramientas administrativas.','group',5,7,27,27,'published','published',NULL,NULL,'2026-07-11 21:16:28','2026-07-16 21:16:28',1,18,21,'2026-06-14 21:16:28','2026-07-27 22:43:43',NULL,NULL,NULL),(28,'PIS-2026-003',2,1,2,'Prototipo descartado de control de asistencia','Registro creado para probar la papelera administrativa','Proyecto demostrativo con información realista para validar las herramientas administrativas.','group',5,7,26,26,'development','registration',NULL,NULL,NULL,NULL,1,NULL,25,'2026-06-14 21:16:28','2026-07-23 05:07:55',NULL,NULL,NULL),(29,'PIS-2026-004',2,1,2,'Sistema de reservas para laboratorios','Prueba de paginación 1','Proyecto demostrativo para validar listados extensos.','group',NULL,NULL,70,70,'published','published','2026-07-23 03:45:56',NULL,'2026-07-23 03:45:56','2026-07-23 03:45:56',1,NULL,72,'2026-07-23 03:45:56','2026-07-27 22:43:44',NULL,NULL,NULL),(30,'PIS-2026-005',2,1,2,'Aplicación de control de asistencia','Prueba de paginación 2','Proyecto demostrativo para validar listados extensos.','group',NULL,NULL,70,70,'published','published','2026-07-23 03:45:56',NULL,'2026-07-23 03:45:56','2026-07-23 03:45:56',1,NULL,73,'2026-07-23 03:45:56','2026-07-27 22:43:44',NULL,NULL,NULL),(31,'PIS-2026-006',2,1,2,'Portal de seguimiento de tutorías','Prueba de paginación 3','Proyecto demostrativo para validar listados extensos.','group',NULL,NULL,70,70,'published','published','2026-07-23 03:45:56',NULL,'2026-07-23 03:45:56','2026-07-23 03:45:56',1,NULL,74,'2026-07-23 03:45:56','2026-07-27 22:43:44',NULL,NULL,NULL),(32,'PIS-2026-007',2,1,2,'Gestor documental para secretaría','Prueba de paginación 4','Proyecto demostrativo para validar listados extensos.','group',NULL,NULL,70,70,'published','published','2026-07-23 03:45:56',NULL,'2026-07-23 03:45:56','2026-07-23 03:45:56',1,NULL,75,'2026-07-23 03:45:56','2026-07-27 22:43:44',NULL,NULL,NULL),(33,'PIS-2026-008',2,1,2,'Panel de indicadores estudiantiles','Prueba de paginación 5','Proyecto demostrativo para validar listados extensos.','group',NULL,NULL,70,70,'published','published','2026-07-23 03:45:56',NULL,'2026-07-23 03:45:56','2026-07-23 03:45:56',1,NULL,76,'2026-07-23 03:45:56','2026-07-27 22:43:44',NULL,NULL,NULL),(34,'PIS-2026-009',2,1,2,'Plataforma de encuestas académicas','Prueba de paginación 6','Proyecto demostrativo para validar listados extensos.','group',NULL,NULL,70,70,'published','published','2026-07-23 03:45:56',NULL,'2026-07-23 03:45:56','2026-07-23 03:45:56',1,NULL,77,'2026-07-23 03:45:56','2026-07-27 22:43:44',NULL,NULL,NULL),(35,'PIS-2026-010',2,1,2,'Sistema de préstamos tecnológicos','Prueba de paginación 7','Proyecto demostrativo para validar listados extensos.','group',NULL,NULL,70,70,'published','published','2026-07-23 03:45:56',NULL,'2026-07-23 03:45:56','2026-07-23 03:45:56',1,NULL,72,'2026-07-23 03:45:56','2026-07-27 22:43:44',NULL,NULL,NULL),(36,'PIS-2026-011',2,1,2,'Agenda institucional inteligente','Prueba de paginación 8','Proyecto demostrativo para validar listados extensos.','group',NULL,NULL,70,70,'published','published','2026-07-23 03:45:56',NULL,'2026-07-23 03:45:56','2026-07-23 03:45:56',1,NULL,73,'2026-07-23 03:45:56','2026-07-27 22:43:44',NULL,NULL,NULL),(37,'PIS-2026-012',2,1,2,'Repositorio de recursos didácticos','Prueba de paginación 9','Proyecto demostrativo para validar listados extensos.','group',NULL,NULL,70,70,'published','published','2026-07-23 03:45:56',NULL,'2026-07-23 03:45:56','2026-07-23 03:45:56',1,NULL,74,'2026-07-23 03:45:56','2026-07-27 22:43:44',NULL,NULL,NULL),(38,'PRA-2026-002',3,1,2,'Monitoreo de infraestructura de redd','Prueba de paginación 100','Proyecto demostrativo para validar listados extensos.','group',NULL,NULL,70,28,'approved','published','2026-07-23 03:45:56',NULL,'2026-07-23 03:45:56','2026-07-23 03:45:56',1,NULL,75,'2026-07-23 03:45:56','2026-07-23 03:53:54',NULL,NULL,NULL);
/*!40000 ALTER TABLE `projects` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_project_presentation_update
BEFORE UPDATE ON projects
FOR EACH ROW
BEGIN
    IF NEW.presentation_file_id IS NOT NULL AND NOT EXISTS (
        SELECT 1
        FROM project_files file
        WHERE file.id = NEW.presentation_file_id
          AND file.project_id = NEW.id
          AND file.deleted_at IS NULL
          AND LOWER(file.extension) IN ('pdf','docx','txt','png','jpg','jpeg','webp')
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El archivo de presentación del proyecto no es válido';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

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
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_enrollments`
--

LOCK TABLES `student_enrollments` WRITE;
/*!40000 ALTER TABLE `student_enrollments` DISABLE KEYS */;
INSERT INTO `student_enrollments` VALUES (13,20,2,1,4,'active'),(14,21,2,1,4,'active'),(15,22,2,1,2,'active'),(16,23,2,1,6,'active'),(17,24,2,1,8,'active'),(18,25,2,1,4,'active'),(43,68,2,1,1,'active'),(45,73,2,1,4,'active'),(46,74,2,1,4,'active'),(47,75,2,1,4,'active'),(48,76,2,1,4,'active'),(49,77,2,1,4,'active'),(53,72,2,1,3,'active');
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
-- Table structure for table `support_material_audit_reads`
--

DROP TABLE IF EXISTS `support_material_audit_reads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `support_material_audit_reads` (
  `user_id` bigint(20) unsigned NOT NULL,
  `material_id` bigint(20) unsigned NOT NULL,
  `last_seen_audit_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`,`material_id`),
  KEY `fk_support_material_audit_read_material` (`material_id`),
  KEY `idx_support_material_audit_read_event` (`last_seen_audit_id`),
  CONSTRAINT `fk_support_material_audit_read_material` FOREIGN KEY (`material_id`) REFERENCES `support_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_support_material_audit_read_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_material_audit_reads`
--

LOCK TABLES `support_material_audit_reads` WRITE;
/*!40000 ALTER TABLE `support_material_audit_reads` DISABLE KEYS */;
INSERT INTO `support_material_audit_reads` VALUES (1,1,121,'2026-07-30 08:34:07'),(1,2,42,'2026-07-29 07:42:06');
/*!40000 ALTER TABLE `support_material_audit_reads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_material_categories`
--

DROP TABLE IF EXISTS `support_material_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `support_material_categories` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(80) NOT NULL,
  `name` varchar(120) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_material_categories`
--

LOCK TABLES `support_material_categories` WRITE;
/*!40000 ALTER TABLE `support_material_categories` DISABLE KEYS */;
INSERT INTO `support_material_categories` VALUES (1,'tesis','Tesis',1,'2026-07-24 00:02:12','2026-07-24 00:02:12'),(2,'practicas','Prácticas',1,'2026-07-24 00:02:12','2026-07-24 00:08:52'),(3,'proyecto-pis','Proyectos PIS',1,'2026-07-24 00:02:12','2026-07-24 00:02:12'),(4,'vinculacion','Vinculación',1,'2026-07-24 00:02:12','2026-07-24 00:08:52');
/*!40000 ALTER TABLE `support_material_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_material_file_versions`
--

DROP TABLE IF EXISTS `support_material_file_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `support_material_file_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `file_id` bigint(20) unsigned NOT NULL,
  `material_id` bigint(20) unsigned NOT NULL,
  `version_number` int(10) unsigned NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `storage_name` varchar(255) NOT NULL,
  `relative_path` varchar(500) NOT NULL,
  `extension` varchar(15) NOT NULL,
  `mime_type` varchar(150) NOT NULL,
  `size_bytes` bigint(20) unsigned NOT NULL DEFAULT 0,
  `sha256` char(64) DEFAULT NULL,
  `replaced_by` bigint(20) unsigned DEFAULT NULL,
  `replaced_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_support_file_version_number` (`file_id`,`version_number`),
  KEY `fk_support_file_version_material` (`material_id`),
  KEY `fk_support_file_version_actor` (`replaced_by`),
  KEY `idx_support_file_version_history` (`file_id`,`replaced_at`),
  CONSTRAINT `fk_support_file_version_actor` FOREIGN KEY (`replaced_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_support_file_version_file` FOREIGN KEY (`file_id`) REFERENCES `support_material_files` (`id`),
  CONSTRAINT `fk_support_file_version_material` FOREIGN KEY (`material_id`) REFERENCES `support_materials` (`id`),
  CONSTRAINT `chk_support_file_version_positive` CHECK (`version_number` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_material_file_versions`
--

LOCK TABLES `support_material_file_versions` WRITE;
/*!40000 ALTER TABLE `support_material_file_versions` DISABLE KEYS */;
INSERT INTO `support_material_file_versions` VALUES (1,18,1,1,'10. BITÁCORA DEL ESTUDIANTE (2).docx','75a2295e265badd62dd486c6e232acc7125b4676.docx','1/75a2295e265badd62dd486c6e232acc7125b4676.docx','docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document',133085,'737fa23095db7f777c6b411025966fc7340b1a3170164a42ba9573e5256cbfac',1,'2026-07-30 07:07:23');
/*!40000 ALTER TABLE `support_material_file_versions` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_support_file_version_number_immutable
BEFORE UPDATE ON support_material_file_versions
FOR EACH ROW
BEGIN
  IF NOT (NEW.version_number <=> OLD.version_number) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT='El numero de una version documental es inmutable';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `support_material_files`
--

DROP TABLE IF EXISTS `support_material_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `support_material_files` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `material_id` bigint(20) unsigned NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `storage_name` varchar(255) NOT NULL,
  `relative_path` varchar(500) NOT NULL,
  `extension` varchar(15) NOT NULL,
  `mime_type` varchar(150) NOT NULL,
  `size_bytes` bigint(20) unsigned NOT NULL DEFAULT 0,
  `sha256` char(64) DEFAULT NULL,
  `is_package` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `purged_at` datetime DEFAULT NULL,
  `purged_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_support_material_path` (`material_id`,`relative_path`),
  KEY `fk_support_file_created_by` (`created_by`),
  KEY `fk_support_file_deleted_by` (`deleted_by`),
  KEY `idx_support_file_material_active` (`material_id`,`deleted_at`),
  KEY `fk_support_file_purged_by` (`purged_by`),
  KEY `idx_support_file_restore_window` (`material_id`,`deleted_at`,`purged_at`),
  CONSTRAINT `fk_support_file_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_support_file_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_support_file_material` FOREIGN KEY (`material_id`) REFERENCES `support_materials` (`id`),
  CONSTRAINT `fk_support_file_purged_by` FOREIGN KEY (`purged_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_material_files`
--

LOCK TABLES `support_material_files` WRITE;
/*!40000 ALTER TABLE `support_material_files` DISABLE KEYS */;
INSERT INTO `support_material_files` VALUES (1,1,'guia_perfil_tesis.pdf','guia_perfil_tesis.pdf','guia_perfil_tesis.pdf','pdf','application/pdf',689,'0f742f7600edb7ad593edff0719e3fd8205c059f31c11262d66a088573b2c0f4',0,1,NULL,'2026-07-24 00:02:12',NULL,NULL,NULL,NULL),(2,1,'lista_de_verificacion_para_elaboracion_del_perfil_de_tesis.txt','lista_de_verificacion_para_elaboracion_del_perfil_de_tesis.txt','lista_de_verificacion_para_elaboracion_del_perfil_de_tesis.txt','txt','text/plain',87,'b4f9a5a71ba533da6f734e51eee6e5ed573f73e381040cb93b530a3e0f0a5409',0,2,NULL,'2026-07-24 00:02:12',NULL,NULL,NULL,NULL),(3,1,'material_tesis_completo.zip','material_tesis_completo.zip','material_tesis_completo.zip','zip','application/zip',777,'e6589ce62f518137a64fbaa031949c8ccb64e056944e62ee1d18af713299edfd',1,3,NULL,'2026-07-24 00:02:12',NULL,NULL,NULL,NULL),(4,2,'seguimiento_practicas.docx','seguimiento_practicas.docx','seguimiento_practicas.docx','docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document',1029,'98ae8e861dd6a8b4b6b2e035bacdf1b72bfef4a618f11610bde75acbe9805282',0,1,NULL,'2026-07-24 00:02:12',NULL,NULL,NULL,NULL),(5,3,'instructivo_proyectos_pis.pdf','instructivo_proyectos_pis.pdf','instructivo_proyectos_pis.pdf','pdf','application/pdf',688,'ba8b43d592938763e1e55264f7a15ff871331a0da3ba2be482909e7459b4dd14',0,1,NULL,'2026-07-24 00:02:12',NULL,NULL,NULL,NULL),(6,4,'informe_vinculacion.docx','informe_vinculacion.docx','informe_vinculacion.docx','docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document',1023,'c143cfa53b6fd7b2b9ca1360ec2ae263912cf4f44ffa91c91c7bf85fdb3ebe4e',0,1,NULL,'2026-07-24 00:02:12',NULL,NULL,NULL,NULL),(7,5,'reglamento_material_apoyo.txt','reglamento_material_apoyo.txt','reglamento_material_apoyo.txt','txt','text/plain',110,'dff8ba0d86ad0a99b9b9eee7d3d360ef86d1fdd29e31d097be6552d06c77700f',0,1,NULL,'2026-07-24 00:02:12',NULL,NULL,NULL,NULL),(9,1,'Guía para la elaboración del perfil de tesis _ Material de apoyo.pdf','d3fbd23ae6f6d5b118adb6b83c3731a6d6dc5bb9.pdf','1/d3fbd23ae6f6d5b118adb6b83c3731a6d6dc5bb9.pdf','pdf','application/pdf',309745,'3ea0e7fbad0527f30d87a82291ba516425bc21028bbdfc8a56322b98aa3c3ec3',0,4,1,'2026-07-27 06:11:15',NULL,NULL,NULL,NULL),(10,1,'Tarea 1.docx','2df90676f59c0e7ba42bfe1454aa5ee828943d5e.docx','1/2df90676f59c0e7ba42bfe1454aa5ee828943d5e.docx','docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document',207695,'c6aa492fca1fd7c7dbf23a63584ac0bfb9cc44a45ae7001bfa8ab4f09b40d82a',0,5,1,'2026-07-27 06:11:44','2026-07-27 07:28:17',1,NULL,NULL),(11,1,'WhatsApp Image 2026-05-27 at 9.06.00 PM.jpeg','d1f26fed9833466bdd7999c8a420cbd9b9bae265.jpeg','1/d1f26fed9833466bdd7999c8a420cbd9b9bae265.jpeg','jpeg','image/jpeg',77718,'32d99433b2261a59eb5b1873d31adf635d05690438ce591f612124a83c986d74',0,6,1,'2026-07-27 06:11:44','2026-07-27 08:08:43',1,NULL,NULL),(12,1,'merged.pdf','1c7b5dc746dd853a1e94d69ef8a031f8277adb88.pdf','1/1c7b5dc746dd853a1e94d69ef8a031f8277adb88.pdf','pdf','application/pdf',520216,NULL,0,7,1,'2026-07-27 07:11:19','2026-07-29 05:43:56',1,'2026-07-29 05:44:35',1),(13,1,'webanimeo.zip','d142c2fb792f1270e58e7d02672ded9e01a60dfb.zip','1/d142c2fb792f1270e58e7d02672ded9e01a60dfb.zip','zip','application/zip',2987356,'f9052ddcb61d4e3e15d2470926b2a4d0fbb2d9a9a89c6ec104b392548f5b389a',0,8,1,'2026-07-27 08:24:59','2026-07-27 08:25:06',1,NULL,NULL),(14,1,'Practicas_María José.zip','a6648e8f28d265c6ea229aa4aee6c85dd77e257b.zip','1/a6648e8f28d265c6ea229aa4aee6c85dd77e257b.zip','zip','application/zip',3623925,'ce4516d92cfa41a17291d2bd5f52c7b53d1fd8312afab1151cb0fabfe6c6b3a8',0,9,1,'2026-07-27 08:25:31',NULL,NULL,NULL,NULL),(15,1,'01. CARATULA (3).docx','0c7be3666aeae4d6538439cc666e17664cd374a9.docx','1/0c7be3666aeae4d6538439cc666e17664cd374a9.docx','docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document',110219,'0f0980532107a35e2057306d61cefb6972e4646149f248b9a9e7683f5ac7df8e',0,10,1,'2026-07-28 04:11:42','2026-07-28 05:40:44',1,NULL,NULL),(16,1,'4. ACTA DE COMPROMISO PARA EJECUCION DE PRACTICAS (1).docx','6a088cc54bb5114a1ccdc89afb6b913cdd657a72.docx','1/6a088cc54bb5114a1ccdc89afb6b913cdd657a72.docx','docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document',118190,'b650c9a32f1a2c566a449e7869f526afba651c5ac6e011b8f75ccfebfe81e099',0,11,1,'2026-07-28 05:40:29','2026-07-28 06:38:55',1,NULL,NULL),(17,1,'01. CARATULA (3).docx','26b3e9472356fbad05dcaadc599e84c9dd350b22.docx','1/26b3e9472356fbad05dcaadc599e84c9dd350b22.docx','docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document',110219,'0f0980532107a35e2057306d61cefb6972e4646149f248b9a9e7683f5ac7df8e',0,12,1,'2026-07-28 05:40:51','2026-07-28 06:38:55',1,NULL,NULL),(18,1,'horario2.jpg','fe78020f169df72df082276ce64e9d765d157b55.jpg','1/fe78020f169df72df082276ce64e9d765d157b55.jpg','jpg','image/jpeg',1374656,'4f06417cf349da520d4be339c7a43cecbb6949268447e581bec7b83148194712',0,13,1,'2026-07-28 06:06:08',NULL,NULL,NULL,NULL),(19,1,'6. PLAN DE APRENDIZAJE PRÁCTICO Y DE ROTACIÓN (2).docx','0fbcb0ceb14bf3f3969f3015ad7a59fb9bef1a81.docx','1/0fbcb0ceb14bf3f3969f3015ad7a59fb9bef1a81.docx','docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document',143489,'8d42924874c5663821351a54c8d4eed115a9622e4416e0036380c0ba23149606',0,14,1,'2026-07-28 06:21:35','2026-07-28 06:32:26',1,NULL,NULL),(20,1,'9. EVALUACIÓN DE DESEMPEÑO POR PARTE DEL TUTOR ACADÉMICO (2).docx','983377f5f7f327e091e6f70d279891380de70613.docx','1/983377f5f7f327e091e6f70d279891380de70613.docx','docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document',130975,'4482132b98185e918b657da98bbbe87715abf0fba2c26cdb05bcc52da642d664',0,15,1,'2026-07-28 06:21:35',NULL,NULL,NULL,NULL),(21,1,'5. ACTA SEGURIDAD Y MEDIOS DE PROTECCIÓN EN LA FORMACIÓN PRÁCTICA EN EL ENTORNO LABORAL REAL (1).docx','d3a960ab1bcb0a756c260ff8636ccb1f0f677449.docx','1/d3a960ab1bcb0a756c260ff8636ccb1f0f677449.docx','docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document',125101,'5727051cbea2648f667faae1a3030af66bae7779baa595fa48f3c98dd09f4e98',0,16,1,'2026-07-28 06:27:54',NULL,NULL,NULL,NULL),(22,1,'webanimeo.zip','9f5b10e5c91b5062f2a24e74f42b1d5161b6997b.zip','1/9f5b10e5c91b5062f2a24e74f42b1d5161b6997b.zip','zip','application/zip',2987356,'f9052ddcb61d4e3e15d2470926b2a4d0fbb2d9a9a89c6ec104b392548f5b389a',0,17,1,'2026-07-28 07:00:00','2026-07-28 07:00:21',1,NULL,NULL),(23,1,'Practicas2.zip','e2b5d08673bd37883c8594797a02a51b580dc22d.zip','1/e2b5d08673bd37883c8594797a02a51b580dc22d.zip','zip','application/zip',4750451,'41aff4447fe6c9195eac8e00c7e0de495b86b8fd3a87764e614d9148df11cf93',0,18,1,'2026-07-28 07:03:35',NULL,NULL,NULL,NULL),(24,1,'Practicas.zip','6013a61fb1f6413fec40f4a8d37bd5954c0c2b95.zip','1/6013a61fb1f6413fec40f4a8d37bd5954c0c2b95.zip','zip','application/zip',19953169,'7bf533a50c7f4ba77248e360f084566cb596775864047de97999663bacce2766',0,19,1,'2026-07-28 07:03:35',NULL,NULL,NULL,NULL),(26,1,'IdeaPPrincipal.docx','0c93f99755bb624a494fe9e190ec1792fe69630b.docx','1/0c93f99755bb624a494fe9e190ec1792fe69630b.docx','docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document',226681,'0934d15368faec3933fb910eda588ca705834998cef884126e632f96a8dcc4db',0,20,1,'2026-07-29 05:22:21',NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `support_material_files` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_materials`
--

DROP TABLE IF EXISTS `support_materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `support_materials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` smallint(5) unsigned NOT NULL,
  `academic_period_id` smallint(5) unsigned DEFAULT NULL,
  `title` varchar(220) NOT NULL,
  `material_type` varchar(100) NOT NULL,
  `description` varchar(500) NOT NULL,
  `full_description` text NOT NULL,
  `publisher` varchar(180) NOT NULL,
  `publication_date` date DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `status` enum('draft','published','withdrawn') NOT NULL DEFAULT 'draft',
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `presentation_file_id` bigint(20) unsigned DEFAULT NULL,
  `download_count` int(10) unsigned NOT NULL DEFAULT 0,
  `keywords_json` longtext DEFAULT NULL,
  `withdrawn_at` datetime DEFAULT NULL,
  `withdrawn_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deletion_reason` varchar(500) DEFAULT NULL,
  `purged_at` datetime DEFAULT NULL,
  `purged_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_support_material_period` (`academic_period_id`),
  KEY `fk_support_material_withdrawn_by` (`withdrawn_by`),
  KEY `fk_support_material_created_by` (`created_by`),
  KEY `fk_support_material_updated_by` (`updated_by`),
  KEY `idx_support_material_status_date` (`status`,`publication_date`),
  KEY `idx_support_material_category` (`category_id`),
  KEY `idx_support_material_presentation` (`presentation_file_id`),
  KEY `fk_support_material_deleted_by` (`deleted_by`),
  KEY `fk_support_material_purged_by` (`purged_by`),
  KEY `idx_support_material_visibility` (`status`,`is_available`,`deleted_at`,`purged_at`),
  KEY `idx_support_material_trash` (`deleted_at`,`purged_at`),
  CONSTRAINT `fk_support_material_category` FOREIGN KEY (`category_id`) REFERENCES `support_material_categories` (`id`),
  CONSTRAINT `fk_support_material_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_support_material_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_support_material_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`),
  CONSTRAINT `fk_support_material_presentation` FOREIGN KEY (`presentation_file_id`) REFERENCES `support_material_files` (`id`),
  CONSTRAINT `fk_support_material_purged_by` FOREIGN KEY (`purged_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_support_material_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_support_material_withdrawn_by` FOREIGN KEY (`withdrawn_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_materials`
--

LOCK TABLES `support_materials` WRITE;
/*!40000 ALTER TABLE `support_materials` DISABLE KEYS */;
INSERT INTO `support_materials` VALUES (1,1,2,'Guía para la elaboración del perfil de tesis','Guía documental','Orientaciones para estructurar correctamente el perfil y preparar el proceso de titulación.','Esta guía reúne los criterios institucionales para elaborar el perfil de tesis.\r\n\r\nIncluye recomendaciones para delimitar el tema, formular objetivos, organizar antecedentes y presentar la propuesta académica.','Instituto Superior Tecnológico \"El Libertador\"','2026-07-08','2026-07-08 00:00:00','published',1,1,100,'[\"Tesis\",\"Perfil de tesis\"]',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-07-24 00:02:12','2026-07-30 08:57:11'),(2,2,2,'Formato de seguimiento de prácticas preprofesionales','Formato documental','Formato institucional para registrar actividades, horas cumplidas y evidencias de prácticas.','Documento editable destinado al seguimiento periódico de las prácticas preprofesionales.\r\n\r\nPermite registrar actividades, resultados, evidencias y validaciones del responsable institucional.','Instituto Superior Tecnológico \"El Libertador\"','2026-06-20','2026-06-20 00:00:00','published',0,4,64,'[\"Prácticas\",\"Seguimiento\",\"Evidencias\"]',NULL,NULL,'2026-07-29 07:42:28',1,'Otro motivo: POR QUE SÍ',NULL,NULL,NULL,1,'2026-07-24 00:02:12','2026-07-30 04:52:22'),(3,3,2,'Instructivo para proyectos PIS','Instructivo','Pasos y criterios para organizar entregables, evidencias y presentación de proyectos integradores.','Este instructivo explica el flujo recomendado para desarrollar proyectos PIS.\n\nDetalla la organización de equipos, entregables mínimos, evidencias y criterios generales de presentación.','Instituto Superior Tecnológico \"El Libertador\"','2025-12-12','2025-12-12 00:00:00','published',1,5,49,'[\"PIS\",\"Entregables\",\"Proyectos\"]',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-07-24 00:02:12','2026-07-29 08:05:25'),(4,4,2,'Formato de informe de vinculación','Plantilla','Plantilla editable para documentar actividades, beneficiarios, resultados e impacto comunitario.','Plantilla institucional para presentar el informe de las actividades de vinculación.\n\nOrganiza objetivos, participantes, resultados, evidencias e indicadores de impacto comunitario.','Instituto Superior Tecnológico \"El Libertador\"','2025-11-30','2025-11-30 00:00:00','published',1,6,38,'[\"Vinculación\",\"Informe\",\"Impacto\"]',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-24 00:02:12','2026-07-29 00:15:40'),(5,1,2,'Reglamento de uso del material académico','Reglamento','Disposiciones generales para consultar y utilizar responsablemente los recursos institucionales.','Documento informativo sobre el uso responsable del material académico institucional.\n\nResume las condiciones de consulta, atribución y distribución de los recursos disponibles.','Instituto Superior Tecnológico \"El Libertador\"','2025-05-14','2025-05-14 00:00:00','published',1,7,21,'[\"Reglamento\",\"Recursos\",\"Uso académico\"]',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-24 00:02:12','2026-07-29 00:15:40');
/*!40000 ALTER TABLE `support_materials` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_support_material_presentation_insert
BEFORE INSERT ON support_materials
FOR EACH ROW
BEGIN
    IF NEW.presentation_file_id IS NOT NULL AND NOT EXISTS (
        SELECT 1
        FROM support_material_files file
        WHERE file.id = NEW.presentation_file_id
          AND file.material_id = NEW.id
          AND file.deleted_at IS NULL
          AND file.is_package = 0
          AND LOWER(file.extension) IN ('pdf','docx','txt','png','jpg','jpeg','webp')
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El archivo de presentación del material no es válido';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_support_material_presentation_update
BEFORE UPDATE ON support_materials
FOR EACH ROW
BEGIN
    IF NEW.presentation_file_id IS NOT NULL AND NOT EXISTS (
        SELECT 1
        FROM support_material_files file
        WHERE file.id = NEW.presentation_file_id
          AND file.material_id = NEW.id
          AND file.deleted_at IS NULL
          AND file.is_package = 0
          AND LOWER(file.extension) IN ('pdf','docx','txt','png','jpg','jpeg','webp')
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El archivo de presentación del material no es válido';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

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
INSERT INTO `system_settings` VALUES ('file_extensions','[\"pdf\",\"docx\",\"zip\"]',NULL,'2026-07-19 06:14:20'),('file_max_mb','20',NULL,'2026-07-19 06:14:20'),('file_total_max_mb','50',NULL,'2026-07-19 06:14:20'),('institution_name','Instituto Superior Tecnológico El Libertador',NULL,'2026-07-19 06:14:20'),('project_code_digits','3',NULL,'2026-07-22 23:04:39'),('project_code_prefixes','{\"thesis\":\"TIT\",\"thesis_profile\":\"PFT\",\"pis\":\"PIS\",\"practice\":\"PRA\",\"community\":\"VIN\"}',NULL,'2026-07-22 23:04:39'),('support_material_keywords','[{\"id\":1,\"name\":\"Tesis\",\"is_active\":true,\"aliases\":[]},{\"id\":2,\"name\":\"Perfil de tesis\",\"is_active\":true,\"aliases\":[]},{\"id\":3,\"name\":\"Titulación\",\"is_active\":true,\"aliases\":[]},{\"id\":4,\"name\":\"Investigación\",\"is_active\":true,\"aliases\":[]},{\"id\":5,\"name\":\"Metodología\",\"is_active\":true,\"aliases\":[]},{\"id\":6,\"name\":\"Normativa\",\"is_active\":true,\"aliases\":[]},{\"id\":7,\"name\":\"Reglamento\",\"is_active\":true,\"aliases\":[]},{\"id\":8,\"name\":\"Formato\",\"is_active\":true,\"aliases\":[]},{\"id\":9,\"name\":\"Plantilla\",\"is_active\":true,\"aliases\":[]},{\"id\":10,\"name\":\"Guía documental\",\"is_active\":true,\"aliases\":[]},{\"id\":11,\"name\":\"Vinculación\",\"is_active\":true,\"aliases\":[]},{\"id\":12,\"name\":\"Proyecto PIS\",\"is_active\":true,\"aliases\":[]},{\"id\":13,\"name\":\"Prácticas preprofesionales\",\"is_active\":true,\"aliases\":[]}]',NULL,'2026-07-31 00:00:00'),('support_material_types','[{\"id\":1,\"name\":\"Normativa\",\"is_active\":true,\"aliases\":[]},{\"id\":2,\"name\":\"Formato\",\"is_active\":true,\"aliases\":[]},{\"id\":3,\"name\":\"Guía documental\",\"is_active\":true,\"aliases\":[]},{\"id\":4,\"name\":\"Plantilla\",\"is_active\":true,\"aliases\":[]},{\"id\":5,\"name\":\"Instructivo\",\"is_active\":true,\"aliases\":[]},{\"id\":6,\"name\":\"Reglamento\",\"is_active\":true,\"aliases\":[]}]',NULL,'2026-07-31 00:00:00');
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
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `is_initial_admin` tinyint(1) NOT NULL DEFAULT 0,
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
  UNIQUE KEY `username` (`username`),
  KEY `idx_users_admin_access` (`is_admin`,`status`,`deleted_at`,`purged_at`),
  CONSTRAINT `chk_initial_admin_requires_access` CHECK (`is_initial_admin` = 0 or `is_admin` = 1)
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'tesisad@gmail.com',NULL,'$2y$10$.pvvdS7PcHlMOseQsx9Bpu663rCb4RXLARfUvoXYUhn5tMiemnsCW',0,0,NULL,NULL,'Administrador de pruebas',1,1,'active','2026-07-31 04:23:19',3,'2026-07-19 03:56:23','2026-07-31 04:23:19',NULL,NULL,NULL,NULL),(20,'ana.torres.demo@correo.com',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,'2026-07-26 21:14:28',NULL,'Ana Lucía Torres',0,0,'active',NULL,3,'2026-07-14 21:14:28','2026-07-31 04:45:05',NULL,NULL,NULL,NULL),(21,'carlos.mendoza.demo@correo.com',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,'2026-07-26 21:14:28',NULL,'Carlos Andrés Mendoza',0,0,'active',NULL,1,'2026-07-14 21:14:28','2026-07-31 04:45:05',NULL,NULL,NULL,NULL),(22,'sofia.lopez.demo@correo.com',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,'2026-07-26 21:14:28',NULL,'Sofía López Herrera',0,0,'active',NULL,1,'2026-07-14 21:14:28','2026-07-31 04:45:05',NULL,NULL,NULL,NULL),(23,'diego.paredes.demo@correo.com',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,'2026-07-26 21:14:28',NULL,'Diego Paredes Ruiz',0,0,'active',NULL,1,'2026-07-14 21:14:28','2026-07-19 21:14:28',NULL,NULL,NULL,NULL),(24,'valentina.mora.demo@correo.com',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,'2026-07-26 21:14:28',NULL,'Valentina Mora Cedeño',0,0,'active',NULL,1,'2026-07-14 21:14:28','2026-07-31 04:45:05',NULL,NULL,NULL,NULL),(25,'mateo.silva.demo@correo.com',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,'2026-07-26 21:14:28',NULL,'Mateo Silva Ortiz',0,0,'blocked',NULL,1,'2026-07-14 21:14:28','2026-07-19 21:14:28',NULL,NULL,NULL,NULL),(26,'maribel.fierro.pendiente@local.invalid',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,'2026-07-26 21:14:28',NULL,'Msc. Maribel Fierro Montero',0,0,'active',NULL,1,'2026-07-14 21:14:28','2026-07-23 03:23:58',NULL,NULL,NULL,NULL),(27,'maria.navarrete.pendiente@local.invalid',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,'2026-07-26 21:14:28',NULL,'Msc. Maria Elena Navarrete',0,0,'active',NULL,1,'2026-07-14 21:14:28','2026-07-23 03:23:58',NULL,NULL,NULL,NULL),(28,'diana.alegria.pendiente@local.invalid',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,'2026-07-26 21:14:28',NULL,'Lic. Diana Alegría Camino',0,0,'active',NULL,1,'2026-07-14 21:14:28','2026-07-31 04:45:05',NULL,NULL,NULL,NULL),(68,'tesises@gmail.com',NULL,'$2y$10$EBLEE8IE4ULTWtidyKhMz.s77YXNXUjXL0Ah9ty5f6damU4Ny7JsG',1,0,'2026-07-30 19:43:41',NULL,'Estudiante de pruebas',0,0,'active',NULL,2,'2026-07-21 17:40:30','2026-07-23 19:43:41',NULL,NULL,NULL,NULL),(69,'diana.ramirez.pendiente@local.invalid',NULL,'$2y$10$6uVhqPOS9uppnUF5aTm1Wua8xwNq5bBQ/v0STvH4gApHQ95B6IvxO',0,0,NULL,NULL,'Msc. Diana Anaid Ramirez',0,0,'active',NULL,1,'2026-07-21 17:40:30','2026-07-23 03:23:58',NULL,NULL,NULL,NULL),(70,'alex.galarza.pendiente@local.invalid',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,NULL,NULL,'Abg. Alex Fabián Galarza',0,0,'active',NULL,1,'2026-07-23 03:23:58','2026-07-31 04:45:05',NULL,NULL,NULL,NULL),(71,'henrry.marino.pendiente@local.invalid',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,NULL,NULL,'Msc. Henrry Mariño Acosta',0,0,'active',NULL,1,'2026-07-23 03:23:58','2026-07-31 04:45:05',NULL,NULL,NULL,NULL),(72,'paginacion01@demo.local',NULL,'$2y$10$QxVr6AX20btwI3bRQtt4E.Ox4NElqDxMAQ4szQaEJxuTgTsTdXuZC',1,0,'2026-07-30 19:36:42',NULL,'Adriana Ponce Vera',0,0,'active',NULL,12,'2026-07-23 03:45:56','2026-07-23 19:48:40',NULL,NULL,NULL,NULL),(73,'paginacion02@demo.local',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,NULL,NULL,'Bruno Cárdenas Mena',0,0,'active',NULL,1,'2026-07-23 03:45:56','2026-07-31 04:45:05',NULL,NULL,NULL,NULL),(74,'paginacion03@demo.local',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,NULL,NULL,'Camila Andrade Ruiz',0,0,'active',NULL,1,'2026-07-23 03:45:56','2026-07-23 03:45:56',NULL,NULL,NULL,NULL),(75,'paginacion04@demo.local',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,NULL,NULL,'David Guerrero Paz',0,0,'active',NULL,1,'2026-07-23 03:45:56','2026-07-23 03:45:56',NULL,NULL,NULL,NULL),(76,'paginacion05@demo.local',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,NULL,NULL,'Elena Morales Cedeño',0,0,'active',NULL,1,'2026-07-23 03:45:56','2026-07-31 04:45:05',NULL,NULL,NULL,NULL),(77,'paginacion06@demo.local',NULL,'$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,NULL,NULL,'Fernando Viteri León',0,0,'active',NULL,1,'2026-07-23 03:45:56','2026-07-31 04:45:05',NULL,NULL,NULL,NULL);
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
