# Migration: Add is_important Column to phlag_environments

This migration adds the `is_important` boolean column to the `phlag_environments` table. Important environments always display on the flag list page, regardless of total environment count.

## MySQL

```sql
-- Add is_important column with default false
ALTER TABLE `phlag_environments` 
ADD COLUMN `is_important` tinyint(1) NOT NULL DEFAULT 0
AFTER `sort_order`;
```

## PostgreSQL

```sql
-- Add is_important column with default false
ALTER TABLE phlag_environments 
ADD COLUMN is_important boolean NOT NULL DEFAULT false;
```

## SQLite

```sql
-- Add is_important column with default 0 (SQLite uses INTEGER for boolean)
ALTER TABLE phlag_environments 
ADD COLUMN is_important INTEGER NOT NULL DEFAULT 0;
```

## Verification

After running migration, verify the change:

**MySQL:**
```sql
SHOW COLUMNS FROM phlag_environments LIKE 'is_important';
```

**PostgreSQL:**
```sql
SELECT column_name, data_type, column_default 
FROM information_schema.columns 
WHERE table_name = 'phlag_environments' AND column_name = 'is_important';
```

**SQLite:**
```sql
PRAGMA table_info(phlag_environments);
```

## Notes

- All existing environments will default to `is_important = false`
- No data migration needed - the default value is appropriate
- The column is NOT NULL to avoid ambiguity (explicit false, not null)
- Placement after `sort_order` maintains logical grouping in MySQL

## Rollback

If you need to rollback:

**MySQL:**
```sql
ALTER TABLE `phlag_environments` DROP COLUMN `is_important`;
```

**PostgreSQL:**
```sql
ALTER TABLE phlag_environments DROP COLUMN is_important;
```

**SQLite:**
```sql
-- SQLite requires table recreation to drop columns (pre-3.35.0)
-- For SQLite 3.35.0+:
ALTER TABLE phlag_environments DROP COLUMN is_important;
```
