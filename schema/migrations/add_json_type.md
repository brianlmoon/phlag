# Migration: Add JSON Type to Phlags

This migration adds the `JSON` type option to existing phlag installations and updates the `value` column to support larger data sizes.

## MySQL

```sql
-- Add JSON to type enum
ALTER TABLE `phlags` 
MODIFY COLUMN `type` enum('SWITCH','INTEGER','FLOAT','STRING','JSON') NOT NULL;

-- Update value column to MEDIUMTEXT for better utf8mb4 support
-- MEDIUMTEXT supports up to ~4M characters with utf8mb4 (16MB storage)
ALTER TABLE `phlag_environment_values`
MODIFY COLUMN `value` mediumtext;
```

**Note**: The MEDIUMTEXT change is important for utf8mb4 encoding. TEXT (64KB) only holds ~16K multi-byte characters, while MEDIUMTEXT (16MB) holds ~4M characters.

## PostgreSQL

**Note**: PostgreSQL doesn't support adding values to existing ENUMs directly. Use this approach:

```sql
-- Option 1: Add value to existing type (PostgreSQL 9.1+)
ALTER TYPE phlag_type ADD VALUE 'JSON';

-- Option 2: If you need to reorder (requires recreation)
-- This is more complex and requires temporarily nullable column
BEGIN;
ALTER TABLE phlags ALTER COLUMN type TYPE TEXT;
DROP TYPE phlag_type;
CREATE TYPE phlag_type AS ENUM ('SWITCH', 'INTEGER', 'FLOAT', 'STRING', 'JSON');
ALTER TABLE phlags ALTER COLUMN type TYPE phlag_type USING type::phlag_type;
COMMIT;
```

**Recommendation**: Use Option 1 (simpler, no downtime).

## SQLite

SQLite requires table recreation to modify CHECK constraints:

```sql
BEGIN TRANSACTION;

-- Create new table with updated constraint
CREATE TABLE phlags_new (
    phlag_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    type TEXT NOT NULL CHECK(type IN ('SWITCH', 'INTEGER', 'FLOAT', 'STRING', 'JSON')),
    create_datetime TEXT NOT NULL DEFAULT (datetime('now')),
    update_datetime TEXT DEFAULT NULL
);

-- Copy data
INSERT INTO phlags_new (phlag_id, name, description, type, create_datetime, update_datetime)
SELECT phlag_id, name, description, type, create_datetime, update_datetime
FROM phlags;

-- Drop old table and rename new one
DROP TABLE phlags;
ALTER TABLE phlags_new RENAME TO phlags;

COMMIT;
```

## Verification

After running migration, verify the change:

**MySQL:**
```sql
SHOW COLUMNS FROM phlags LIKE 'type';
```

**PostgreSQL:**
```sql
SELECT enum_range(NULL::phlag_type);
```

**SQLite:**
```sql
SELECT sql FROM sqlite_master WHERE type='table' AND name='phlags';
```

## Rollback

If you need to rollback (before creating any JSON flags):

**MySQL:**
```sql
ALTER TABLE `phlags` 
MODIFY COLUMN `type` enum('SWITCH','INTEGER','FLOAT','STRING') NOT NULL;
```

**PostgreSQL:**
```sql
-- Cannot remove enum value in PostgreSQL without recreating type
-- Only rollback if no JSON flags exist
```

**SQLite:**
```sql
-- Recreate table without JSON in CHECK constraint (similar to forward migration)
```
