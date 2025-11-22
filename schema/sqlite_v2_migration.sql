-- Phlag v2.0 Migration Script for SQLite
-- Environment-Specific Flag Values
--
-- WARNING: This is a BREAKING CHANGE migration
-- - Removes value/temporal columns from phlags table
-- - Requires all environments to be explicitly created before use
-- - Old API endpoints will no longer work
--
-- BACKUP YOUR DATABASE BEFORE RUNNING THIS MIGRATION
--
-- Note: SQLite does not support DROP COLUMN directly, so we must recreate the table

-- Step 1: Create new phlag_environment_values table
CREATE TABLE phlag_environment_values (
    phlag_environment_value_id INTEGER PRIMARY KEY AUTOINCREMENT,
    phlag_id INTEGER NOT NULL,
    phlag_environment_id INTEGER NOT NULL,
    value TEXT DEFAULT NULL,
    start_datetime TEXT DEFAULT NULL,
    end_datetime TEXT DEFAULT NULL,
    create_datetime TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    update_datetime TEXT DEFAULT NULL,
    UNIQUE(phlag_id, phlag_environment_id),
    FOREIGN KEY (phlag_id) REFERENCES phlags (phlag_id) ON DELETE CASCADE,
    FOREIGN KEY (phlag_environment_id) REFERENCES phlag_environments (phlag_environment_id) ON DELETE CASCADE
);

CREATE INDEX idx_env_value_phlag_id ON phlag_environment_values (phlag_id);
CREATE INDEX idx_env_value_environment_id ON phlag_environment_values (phlag_environment_id);

-- Step 2: Add sort_order to phlag_environments table
-- SQLite requires table recreation for ALTER TABLE changes
CREATE TABLE phlag_environments_new (
    phlag_environment_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    sort_order INTEGER NOT NULL DEFAULT 0,
    create_datetime TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    update_datetime TEXT DEFAULT NULL
);

INSERT INTO phlag_environments_new (phlag_environment_id, name, create_datetime, update_datetime)
SELECT phlag_environment_id, name, create_datetime, update_datetime
FROM phlag_environments;

DROP TABLE phlag_environments;
ALTER TABLE phlag_environments_new RENAME TO phlag_environments;

CREATE INDEX idx_env_sort_order ON phlag_environments (sort_order);

-- Step 3: Recreate phlags table without value and temporal columns
CREATE TABLE phlags_new (
    phlag_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    type TEXT NOT NULL CHECK(type IN ('SWITCH','INTEGER','FLOAT','STRING')),
    create_datetime TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    update_datetime TEXT DEFAULT NULL
);

INSERT INTO phlags_new (phlag_id, name, description, type, create_datetime, update_datetime)
SELECT phlag_id, name, description, type, create_datetime, update_datetime
FROM phlags;

DROP TABLE phlags;
ALTER TABLE phlags_new RENAME TO phlags;
