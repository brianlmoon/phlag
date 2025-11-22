-- Phlag v2.0 Migration Script for MySQL
-- Environment-Specific Flag Values
--
-- WARNING: This is a BREAKING CHANGE migration
-- - Removes value/temporal columns from phlags table
-- - Requires all environments to be explicitly created before use
-- - Old API endpoints will no longer work
--
-- BACKUP YOUR DATABASE BEFORE RUNNING THIS MIGRATION

-- Step 1: Create new phlag_environment_values table
CREATE TABLE `phlag_environment_values` (
    `phlag_environment_value_id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `phlag_id` bigint unsigned NOT NULL,
    `phlag_environment_id` bigint unsigned NOT NULL,
    `value` varchar(255) DEFAULT NULL,
    `start_datetime` datetime DEFAULT NULL,
    `end_datetime` datetime DEFAULT NULL,
    `create_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `update_datetime` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`phlag_environment_value_id`),
    UNIQUE KEY `phlag_env_unique` (`phlag_id`, `phlag_environment_id`),
    KEY `phlag_id` (`phlag_id`),
    KEY `phlag_environment_id` (`phlag_environment_id`),
    CONSTRAINT `fk_env_value_phlag` FOREIGN KEY (`phlag_id`) 
        REFERENCES `phlags` (`phlag_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_env_value_environment` FOREIGN KEY (`phlag_environment_id`) 
        REFERENCES `phlag_environments` (`phlag_environment_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Step 2: Add sort_order to phlag_environments table
ALTER TABLE `phlag_environments` 
ADD COLUMN `sort_order` int unsigned NOT NULL DEFAULT 0 AFTER `name`,
ADD KEY `sort_order` (`sort_order`);

-- Step 3: Modify phlags table (remove value and temporal columns)
ALTER TABLE `phlags` DROP COLUMN `value`;
ALTER TABLE `phlags` DROP COLUMN `start_datetime`;
ALTER TABLE `phlags` DROP COLUMN `end_datetime`;
ALTER TABLE `phlags` MODIFY `type` enum('SWITCH','INTEGER','FLOAT','STRING') NOT NULL;
