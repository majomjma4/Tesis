-- Canonical structural baseline for a NEW installation on an empty database.
-- Generated from the live schema metadata of the canonical application database.
-- Schema only: no rows, users, passwords, hashes, emails, tokens, or academic data.
-- This file does not create, select, drop, or replace any database.
-- Existing database updates must use approved UP migrations recorded after this baseline.

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_defense_schedules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `academic_period_id` smallint(5) unsigned NOT NULL,
  `defense_date` date DEFAULT NULL,
  `defense_time` time DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_academic_defense_schedules_period` (`academic_period_id`),
  KEY `idx_academic_defense_schedules_updated_by` (`updated_by`),
  CONSTRAINT `fk_academic_defense_schedules_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_academic_defense_schedules_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_periods` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `name` varchar(100) NOT NULL,
  `starts_on` date NOT NULL,
  `ends_on` date NOT NULL,
  `status` enum('planned','active','closed') NOT NULL DEFAULT 'planned',
  `active_guard` tinyint(4) GENERATED ALWAYS AS (case when `status` = 'active' then 1 else NULL end) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  UNIQUE KEY `uq_academic_periods_single_active` (`active_guard`),
  CONSTRAINT `chk_period_dates` CHECK (`ends_on` >= `starts_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `careers` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `name` varchar(180) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_security_locks` (
  `lock_key` varchar(64) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 1,
  `locked_until` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`lock_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migration_project_flow_20260818_backup` (
  `entity_type` varchar(20) NOT NULL,
  `entity_id` bigint(20) unsigned NOT NULL,
  `original_status` varchar(60) NOT NULL,
  `original_updated_at` datetime DEFAULT NULL,
  `captured_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`entity_type`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `type` enum('delivery','observation','status_change','review','reminder','system','tribunal','repository','comment','adjustment') NOT NULL,
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
  KEY `idx_notifications_archived` (`archived_at`),
  CONSTRAINT `fk_notifications_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_recovery_rate_limits` (
  `ip_address` varchar(45) NOT NULL,
  `window_started_at` datetime NOT NULL,
  `last_requested_at` datetime NOT NULL,
  `request_count` tinyint(3) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`ip_address`),
  KEY `idx_password_recovery_rate_last_requested` (`last_requested_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `requested_ip` varchar(45) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token_hash` (`token_hash`),
  KEY `idx_password_resets_user_expires` (`user_id`,`expires_at`),
  CONSTRAINT `fk_password_resets_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_adjustment_request_responses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint(20) unsigned NOT NULL,
  `author_id` bigint(20) unsigned NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_adjustment_response_request_date` (`request_id`,`created_at`,`id`),
  KEY `idx_adjustment_response_author` (`author_id`),
  CONSTRAINT `fk_adjustment_response_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_adjustment_response_request` FOREIGN KEY (`request_id`) REFERENCES `project_adjustment_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_adjustment_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `requested_by` bigint(20) unsigned NOT NULL,
  `request_type` varchar(60) NOT NULL,
  `message` text NOT NULL,
  `related_section` varchar(100) DEFAULT NULL,
  `related_field` varchar(100) DEFAULT NULL,
  `file_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('pending','addressed','closed') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `addressed_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `closed_by` bigint(20) unsigned DEFAULT NULL,
  `rejection_reason` varchar(500) DEFAULT NULL,
  `lock_version` int(10) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_adjustment_project_status_date` (`project_id`,`status`,`created_at`,`id`),
  KEY `idx_adjustment_requester` (`requested_by`),
  KEY `idx_adjustment_file` (`file_id`),
  KEY `idx_adjustment_closed_by` (`closed_by`),
  CONSTRAINT `fk_adjustment_closed_by` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_adjustment_file` FOREIGN KEY (`file_id`) REFERENCES `project_files` (`id`),
  CONSTRAINT `fk_adjustment_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_adjustment_requester` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  CONSTRAINT `chk_adjustment_lock_version` CHECK (`lock_version` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `effective_context` varchar(32) DEFAULT NULL,
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
  CONSTRAINT `fk_audit_project_rebuilt` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_audit_user_rebuilt` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_defenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `attempt_number` int(10) unsigned NOT NULL,
  `defense_date` date DEFAULT NULL,
  `defense_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `modality` enum('presential','virtual','hybrid') DEFAULT NULL,
  `result` enum('approved','rejected') DEFAULT NULL,
  `result_notes` varchar(2000) DEFAULT NULL,
  `result_registered_by` bigint(20) unsigned DEFAULT NULL,
  `result_registered_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_defenses_attempt` (`project_id`,`attempt_number`),
  KEY `fk_project_defenses_result_user` (`result_registered_by`),
  KEY `idx_project_defenses_current` (`project_id`,`attempt_number`),
  CONSTRAINT `fk_project_defenses_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_project_defenses_result_user` FOREIGN KEY (`result_registered_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_draft_files` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `draft_id` char(36) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `storage_name` varchar(190) NOT NULL,
  `storage_path` varchar(500) NOT NULL,
  `mime_type` varchar(120) NOT NULL,
  `extension` varchar(12) NOT NULL,
  `size_bytes` bigint(20) unsigned NOT NULL,
  `checksum_sha256` char(64) NOT NULL,
  `zip_meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`zip_meta`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_draft_files_storage_name` (`storage_name`),
  KEY `idx_project_draft_files_draft` (`draft_id`),
  KEY `idx_project_draft_files_owner` (`user_id`,`draft_id`),
  CONSTRAINT `fk_project_draft_files_draft` FOREIGN KEY (`draft_id`) REFERENCES `project_drafts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_project_draft_files_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_drafts` (
  `id` char(36) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_drafts_user` (`user_id`),
  KEY `idx_project_drafts_expiration` (`expires_at`),
  CONSTRAINT `fk_project_drafts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(180) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_events_project_date` (`project_id`,`event_date`),
  KEY `fk_events_user` (`created_by`),
  KEY `idx_project_events_owner_date` (`created_by`,`event_date`),
  CONSTRAINT `fk_events_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_events_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_file_review_states` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `file_id` bigint(20) unsigned NOT NULL,
  `checksum_sha256` char(64) NOT NULL,
  `status` enum('development','under_review','approved','corrections_requested') NOT NULL DEFAULT 'development',
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_file_review_version` (`file_id`,`checksum_sha256`),
  KEY `idx_project_file_review_summary` (`project_id`,`status`),
  KEY `fk_project_file_review_actor` (`reviewed_by`),
  CONSTRAINT `fk_project_file_review_actor` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_project_file_review_file` FOREIGN KEY (`file_id`) REFERENCES `project_files` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_project_file_review_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_file_version_addressed_observations` (
  `change_id` bigint(20) unsigned NOT NULL,
  `observation_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`change_id`,`observation_id`),
  KEY `idx_version_addressed_observation` (`observation_id`,`change_id`),
  CONSTRAINT `fk_version_addressed_change` FOREIGN KEY (`change_id`) REFERENCES `project_file_version_changes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_version_addressed_observation` FOREIGN KEY (`observation_id`) REFERENCES `project_observations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_file_version_archive_manifests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `file_id` bigint(20) unsigned NOT NULL,
  `version_id` bigint(20) unsigned NOT NULL,
  `checksum_sha256` char(64) NOT NULL,
  `size_bytes` bigint(20) unsigned NOT NULL,
  `mime_type` varchar(120) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `version_number` int(10) unsigned NOT NULL,
  `replaced_at` datetime NOT NULL,
  `replaced_by` bigint(20) unsigned DEFAULT NULL,
  `historical_document_status` varchar(40) NOT NULL,
  `declared_summary` varchar(2000) DEFAULT NULL,
  `sections_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sections_json`)),
  `addressed_observations_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`addressed_observations_json`)),
  `storage_tier` varchar(40) NOT NULL,
  `archived_reason` varchar(500) NOT NULL,
  `verified_at` datetime NOT NULL,
  `checksum_verified` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_archive_manifest_version` (`version_id`),
  KEY `idx_archive_manifest_project` (`project_id`,`version_number`),
  KEY `fk_archive_manifest_file` (`file_id`),
  KEY `fk_archive_manifest_actor` (`replaced_by`),
  CONSTRAINT `fk_archive_manifest_actor` FOREIGN KEY (`replaced_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_archive_manifest_file` FOREIGN KEY (`file_id`) REFERENCES `project_files` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_archive_manifest_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_archive_manifest_version` FOREIGN KEY (`version_id`) REFERENCES `project_file_versions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_archive_manifest_sections` CHECK (`sections_json` is null or json_valid(`sections_json`)),
  CONSTRAINT `chk_archive_manifest_observations` CHECK (`addressed_observations_json` is null or json_valid(`addressed_observations_json`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_file_version_changes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `file_id` bigint(20) unsigned NOT NULL,
  `previous_version_id` bigint(20) unsigned DEFAULT NULL,
  `previous_checksum` char(64) NOT NULL,
  `new_checksum` char(64) NOT NULL,
  `previous_version_number` int(10) unsigned NOT NULL,
  `new_version_number` int(10) unsigned NOT NULL,
  `changed_by` bigint(20) unsigned NOT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reason` varchar(500) NOT NULL,
  `declared_summary` varchar(2000) NOT NULL,
  `sections_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sections_json`)),
  `previous_document_status` enum('development','under_review','approved','corrections_requested') NOT NULL,
  `new_document_status` enum('development','under_review','approved','corrections_requested') NOT NULL DEFAULT 'development',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_file_version_change_new` (`file_id`,`new_checksum`),
  UNIQUE KEY `uq_project_file_version_change_number` (`file_id`,`new_version_number`),
  KEY `idx_file_version_change_project_date` (`project_id`,`changed_at`,`id`),
  KEY `idx_file_version_change_previous` (`previous_version_id`),
  KEY `idx_file_version_change_actor` (`changed_by`),
  CONSTRAINT `fk_file_version_change_actor` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_file_version_change_file` FOREIGN KEY (`file_id`) REFERENCES `project_files` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_file_version_change_previous` FOREIGN KEY (`previous_version_id`) REFERENCES `project_file_versions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_file_version_change_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_file_version_change_checksums` CHECK (`previous_checksum` <> `new_checksum`),
  CONSTRAINT `chk_file_version_change_numbers` CHECK (`new_version_number` = `previous_version_number` + 1),
  CONSTRAINT `chk_file_version_change_sections` CHECK (`sections_json` is null or json_valid(`sections_json`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `physical_status` enum('active','archived','unavailable') NOT NULL DEFAULT 'active',
  `archived_at` datetime DEFAULT NULL,
  `archived_by` bigint(20) unsigned DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `checksum_verified` tinyint(1) NOT NULL DEFAULT 0,
  `storage_tier` varchar(40) NOT NULL DEFAULT 'active',
  `retention_until` date DEFAULT NULL,
  `legal_hold` tinyint(1) NOT NULL DEFAULT 0,
  `unavailable_reason` varchar(500) DEFAULT NULL,
  `archive_reason` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_file_version_number` (`file_id`,`version_number`),
  KEY `idx_project_file_version_project` (`project_id`,`replaced_at`),
  KEY `fk_project_file_version_actor` (`replaced_by`),
  KEY `idx_project_file_version_conservation` (`project_id`,`physical_status`,`legal_hold`),
  KEY `fk_project_file_version_archived_by` (`archived_by`),
  CONSTRAINT `fk_project_file_version_actor` FOREIGN KEY (`replaced_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_project_file_version_archived_by` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_project_file_version_file` FOREIGN KEY (`file_id`) REFERENCES `project_files` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_project_file_version_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_project_file_version_positive` CHECK (`version_number` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  KEY `fk_project_file_deleted_by` (`deleted_by`),
  KEY `fk_project_file_purged_by` (`purged_by`),
  KEY `idx_project_file_restore_window` (`project_id`,`deleted_at`,`purged_at`),
  CONSTRAINT `fk_files_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `project_deliveries` (`id`),
  CONSTRAINT `fk_files_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_files_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_project_file_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_project_file_purged_by` FOREIGN KEY (`purged_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_observations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `delivery_id` bigint(20) unsigned DEFAULT NULL,
  `file_id` bigint(20) unsigned DEFAULT NULL,
  `file_checksum_sha256` char(64) DEFAULT NULL,
  `project_file_version_id` bigint(20) unsigned DEFAULT NULL,
  `author_id` bigint(20) unsigned NOT NULL,
  `category` varchar(60) NOT NULL,
  `location_reference` varchar(180) DEFAULT NULL,
  `selection_anchor` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (`selection_anchor` is null or json_valid(`selection_anchor`)),
  `body` text NOT NULL,
  `status` enum('pending','addressed','resolved') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_observations_project` (`project_id`),
  KEY `fk_observations_delivery` (`delivery_id`),
  KEY `fk_observations_file` (`file_id`),
  KEY `fk_observations_author` (`author_id`),
  KEY `idx_project_observations_file_revision` (`file_id`,`file_checksum_sha256`),
  KEY `idx_project_observations_file_version` (`project_file_version_id`),
  CONSTRAINT `fk_observations_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_observations_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `project_deliveries` (`id`),
  CONSTRAINT `fk_observations_file` FOREIGN KEY (`file_id`) REFERENCES `project_files` (`id`),
  CONSTRAINT `fk_observations_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_project_observations_file_version` FOREIGN KEY (`project_file_version_id`) REFERENCES `project_file_versions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_participants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `role_code` varchar(50) NOT NULL,
  `tribunal_position` varchar(20) DEFAULT NULL,
  `permission_level` enum('manage','contribute','review','read') NOT NULL DEFAULT 'read',
  `is_leader` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `removed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_participant` (`project_id`,`user_id`,`role_code`),
  KEY `idx_participant_user` (`user_id`,`status`),
  KEY `idx_project_participant_tribunal_position` (`project_id`,`role_code`,`tribunal_position`),
  CONSTRAINT `fk_participants_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_participants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_review_representations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `file_id` bigint(20) unsigned NOT NULL,
  `project_file_version_id` bigint(20) unsigned DEFAULT NULL,
  `checksum_sha256` char(64) NOT NULL,
  `representation_type` enum('libreoffice_pdf','supplemental_pdf') NOT NULL,
  `storage_name` varchar(190) NOT NULL,
  `storage_path` varchar(500) NOT NULL,
  `mime_type` varchar(120) NOT NULL DEFAULT 'application/pdf',
  `size_bytes` bigint(20) unsigned NOT NULL,
  `pdf_checksum_sha256` char(64) NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_review_representation_revision` (`file_id`,`checksum_sha256`,`representation_type`),
  UNIQUE KEY `uq_review_representation_storage` (`storage_name`),
  KEY `idx_review_representation_project` (`project_id`,`file_id`,`checksum_sha256`),
  KEY `idx_review_representation_version` (`project_file_version_id`),
  KEY `fk_review_representation_creator` (`created_by`),
  CONSTRAINT `fk_review_representation_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_review_representation_file` FOREIGN KEY (`file_id`) REFERENCES `project_files` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_representation_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_representation_version` FOREIGN KEY (`project_file_version_id`) REFERENCES `project_file_versions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_review_representation_checksum` CHECK (`checksum_sha256` regexp '^[a-f0-9]{64}$'),
  CONSTRAINT `chk_review_representation_pdf_checksum` CHECK (`pdf_checksum_sha256` regexp '^[a-f0-9]{64}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_type_keywords` (
  `project_type_id` smallint(5) unsigned NOT NULL,
  `keyword_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`project_type_id`,`keyword_id`),
  KEY `fk_project_type_keywords_keyword` (`keyword_id`),
  CONSTRAINT `fk_project_type_keywords_keyword` FOREIGN KEY (`keyword_id`) REFERENCES `keywords` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_project_type_keywords_type` FOREIGN KEY (`project_type_id`) REFERENCES `project_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_types` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(60) NOT NULL,
  `name` varchar(140) NOT NULL,
  `registration_description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `publication_origin` varchar(40) NOT NULL DEFAULT 'workflow',
  `current_stage` varchar(100) NOT NULL DEFAULT 'registration',
  `approved_at` datetime DEFAULT NULL,
  `defense_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `repository_added_by` bigint(20) unsigned DEFAULT NULL,
  `repository_added_at` datetime DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `withdrawn_at` datetime DEFAULT NULL,
  `withdrawn_by` bigint(20) unsigned DEFAULT NULL,
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
  KEY `idx_projects_repository_withdrawn` (`status`,`withdrawn_at`,`deleted_at`),
  KEY `fk_projects_withdrawn_by` (`withdrawn_by`),
  KEY `idx_projects_repository_origin` (`publication_origin`,`status`,`deleted_at`,`withdrawn_at`),
  KEY `idx_projects_repository_added_by` (`repository_added_by`),
  CONSTRAINT `fk_project_presentation` FOREIGN KEY (`presentation_file_id`) REFERENCES `project_files` (`id`),
  CONSTRAINT `fk_projects_career` FOREIGN KEY (`career_id`) REFERENCES `careers` (`id`),
  CONSTRAINT `fk_projects_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_projects_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`),
  CONSTRAINT `fk_projects_proposed_tutor` FOREIGN KEY (`proposed_tutor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_projects_repository_added_by` FOREIGN KEY (`repository_added_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_projects_research_line` FOREIGN KEY (`research_line_id`) REFERENCES `research_lines` (`id`),
  CONSTRAINT `fk_projects_subject` FOREIGN KEY (`academic_subject_id`) REFERENCES `academic_subjects` (`id`),
  CONSTRAINT `fk_projects_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_projects_type` FOREIGN KEY (`project_type_id`) REFERENCES `project_types` (`id`),
  CONSTRAINT `fk_projects_withdrawn_by` FOREIGN KEY (`withdrawn_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `repository_direct_publish_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `request_token` char(64) NOT NULL,
  `payload_hash` char(64) NOT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `response_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_repository_direct_publish_request` (`user_id`,`request_token`),
  KEY `idx_repository_direct_publish_project` (`project_id`),
  CONSTRAINT `fk_repository_direct_publish_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_repository_direct_publish_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teacher_profiles` (
  `user_id` bigint(20) unsigned NOT NULL,
  `institutional_code` varchar(50) NOT NULL,
  `academic_title` varchar(120) DEFAULT NULL,
  `can_tutor` tinyint(1) NOT NULL DEFAULT 1,
  `can_manage_thesis` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `institutional_code` (`institutional_code`),
  KEY `idx_teacher_profiles_manage_thesis` (`can_manage_thesis`),
  CONSTRAINT `fk_teacher_profile_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `session_token_hash` char(64) NOT NULL,
  `session_version` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_activity_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `device_label` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_sessions_token_hash` (`session_token_hash`),
  KEY `idx_user_sessions_user` (`user_id`),
  KEY `idx_user_sessions_active` (`user_id`,`revoked_at`),
  KEY `idx_user_sessions_expires` (`expires_at`),
  CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(190) DEFAULT NULL,
  `username` varchar(80) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `password_warning_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `temporary_password_expires_at` datetime DEFAULT NULL,
  `temporary_password_last_warning_at` date DEFAULT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `avatar_path` varchar(500) DEFAULT NULL,
  `avatar_updated_at` datetime DEFAULT NULL,
  `full_name` varchar(180) NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `is_initial_admin` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive','blocked') NOT NULL DEFAULT 'active',
  `last_login_at` datetime DEFAULT NULL,
  `session_version` int(10) unsigned NOT NULL DEFAULT 1,
  `profile_version` int(10) unsigned NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deletion_reason` varchar(500) DEFAULT NULL,
  `purged_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `idx_users_admin_access` (`is_admin`,`status`,`deleted_at`,`purged_at`),
  CONSTRAINT `chk_initial_admin_requires_access` CHECK (`is_initial_admin` = 0 or `is_admin` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
