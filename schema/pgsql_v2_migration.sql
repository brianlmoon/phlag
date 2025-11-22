-- Phlag v2.0 Migration Script for PostgreSQL
-- Environment-Specific Flag Values
--
-- WARNING: This is a BREAKING CHANGE migration
-- - Removes value/temporal columns from phlags table
-- - Requires all environments to be explicitly created before use
-- - Old API endpoints will no longer work
--
-- BACKUP YOUR DATABASE BEFORE RUNNING THIS MIGRATION

-- Step 1: Create new phlag_environment_values table
CREATE TABLE phlag_environment_values (
    phlag_environment_value_id BIGSERIAL PRIMARY KEY,
    phlag_id BIGINT NOT NULL,
    phlag_environment_id BIGINT NOT NULL,
    value VARCHAR(255) DEFAULT NULL,
    start_datetime TIMESTAMP DEFAULT NULL,
    end_datetime TIMESTAMP DEFAULT NULL,
    create_datetime TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    update_datetime TIMESTAMP DEFAULT NULL,
    CONSTRAINT phlag_env_unique UNIQUE (phlag_id, phlag_environment_id),
    CONSTRAINT fk_env_value_phlag FOREIGN KEY (phlag_id) 
        REFERENCES phlags (phlag_id) ON DELETE CASCADE,
    CONSTRAINT fk_env_value_environment FOREIGN KEY (phlag_environment_id) 
        REFERENCES phlag_environments (phlag_environment_id) ON DELETE CASCADE
);

CREATE INDEX idx_env_value_phlag_id ON phlag_environment_values (phlag_id);
CREATE INDEX idx_env_value_environment_id ON phlag_environment_values (phlag_environment_id);

-- Step 2: Add sort_order to phlag_environments table
ALTER TABLE phlag_environments 
ADD COLUMN sort_order INTEGER NOT NULL DEFAULT 0;

CREATE INDEX idx_env_sort_order ON phlag_environments (sort_order);

-- Step 3: Modify phlags table (remove value and temporal columns)
ALTER TABLE phlags DROP COLUMN value;
ALTER TABLE phlags DROP COLUMN start_datetime;
ALTER TABLE phlags DROP COLUMN end_datetime;
ALTER TABLE phlags ALTER COLUMN type SET NOT NULL;
