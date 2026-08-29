#!/bin/bash
set -e

cd "$(dirname "$0")"

DB_FILE="var/sqlite/phlag.db"

if [ ! -f "$DB_FILE" ]; then
    echo "Creating local SQLite database at $DB_FILE"
    mkdir -p "$(dirname "$DB_FILE")"
    sqlite3 "$DB_FILE" < schema/sqlite.sql
fi

docker compose -f docker-compose.dev.yml up -d --build
