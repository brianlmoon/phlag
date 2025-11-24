#!/bin/bash
# Phlag Configuration Setup Script
#
# This script runs during container startup (via Phusion baseimage's my_init)
# and generates /app/etc/config.ini from environment variables if it doesn't
# already exist (e.g., from a volume mount).
#
# ## Configuration Priority
#
# 1. Volume-mounted config.ini (highest priority)
# 2. Generated from environment variables (fallback)
#
# ## Environment Variables
#
# Required (for generated config):
# - DB_PHLAG_TYPE: Database type (mysql|pgsql|sqlite)
# - DB_PHLAG_HOST: Database server hostname
# - DB_PHLAG_DB: Database name
#
# Optional:
# - DB_PHLAG_PORT: Database server port (default: 3306 for MySQL, 5432 for PostgreSQL)
# - DB_PHLAG_USER: Database username (default: empty)
# - DB_PHLAG_PASS: Database password (default: empty)
# - MAILER_FROM_ADDRESS: Email sender address
# - MAILER_FROM_NAME: Email sender name (default: "Phlag Admin")
# - MAILER_METHOD: Email method (smtp|mail, default: mail)
# - SMTP_HOST: SMTP server hostname
# - SMTP_PORT: SMTP server port (default: 587)
# - SMTP_ENCRYPTION: SMTP encryption (tls|ssl, default: tls)
# - SMTP_USERNAME: SMTP authentication username
# - SMTP_PASSWORD: SMTP authentication password
# - SESSION_TIMEOUT: Session timeout in seconds (default: 1800)
# - PHLAG_BASE_URL_PATH: Base URL path for subdirectory installs
#
# ## Exit Codes
#
# - 0: Success (config exists or was generated)
# - 1: Required environment variables missing

set -e

CONFIG_FILE="/app/etc/config.ini"

echo "Checking Phlag configuration..."

# Check if config.ini already exists (volume mount)
if [ -f "$CONFIG_FILE" ]; then
    echo "Found existing config.ini at $CONFIG_FILE (volume mount)"
    exit 0
fi

echo "No config.ini found, generating from environment variables..."

# Validate required environment variables
if [ -z "$DB_PHLAG_TYPE" ]; then
    echo "ERROR: DB_PHLAG_TYPE environment variable is required"
    exit 1
fi

if [ -z "$DB_PHLAG_HOST" ] && [ "$DB_PHLAG_TYPE" != "sqlite" ]; then
    echo "ERROR: DB_PHLAG_HOST environment variable is required for $DB_PHLAG_TYPE"
    exit 1
fi

if [ -z "$DB_PHLAG_DB" ]; then
    echo "ERROR: DB_PHLAG_DB environment variable is required"
    exit 1
fi

# Set default values for optional variables
DB_PHLAG_PORT="${DB_PHLAG_PORT:-}"
DB_PHLAG_USER="${DB_PHLAG_USER:-}"
DB_PHLAG_PASS="${DB_PHLAG_PASS:-}"

# Set default port based on database type if not provided
if [ -z "$DB_PHLAG_PORT" ]; then
    case "$DB_PHLAG_TYPE" in
        mysql)
            DB_PHLAG_PORT=3306
            ;;
        pgsql)
            DB_PHLAG_PORT=5432
            ;;
        sqlite)
            DB_PHLAG_PORT=""
            ;;
    esac
fi

# Email configuration defaults
MAILER_FROM_ADDRESS="${MAILER_FROM_ADDRESS:-}"
MAILER_FROM_NAME="${MAILER_FROM_NAME:-Phlag Admin}"
MAILER_METHOD="${MAILER_METHOD:-mail}"
SMTP_HOST="${SMTP_HOST:-}"
SMTP_PORT="${SMTP_PORT:-587}"
SMTP_ENCRYPTION="${SMTP_ENCRYPTION:-tls}"
SMTP_USERNAME="${SMTP_USERNAME:-}"
SMTP_PASSWORD="${SMTP_PASSWORD:-}"

# Application configuration defaults
SESSION_TIMEOUT="${SESSION_TIMEOUT:-1800}"
PHLAG_BASE_URL_PATH="${PHLAG_BASE_URL_PATH:-}"

# Generate config.ini
echo "Generating $CONFIG_FILE..."

cat > "$CONFIG_FILE" <<EOF
; Phlag Configuration
; Auto-generated from environment variables on $(date)

[db]
db.phlag.type   = $DB_PHLAG_TYPE
db.phlag.db     = $DB_PHLAG_DB
db.phlag.server = $DB_PHLAG_HOST
EOF

# Add port if set
if [ -n "$DB_PHLAG_PORT" ]; then
    echo "db.phlag.port   = $DB_PHLAG_PORT" >> "$CONFIG_FILE"
fi

# Add credentials if set
if [ -n "$DB_PHLAG_USER" ]; then
    echo "db.phlag.user   = $DB_PHLAG_USER" >> "$CONFIG_FILE"
fi

if [ -n "$DB_PHLAG_PASS" ]; then
    echo "db.phlag.pass   = $DB_PHLAG_PASS" >> "$CONFIG_FILE"
fi

# Add mailer configuration if email address is set
if [ -n "$MAILER_FROM_ADDRESS" ]; then
    cat >> "$CONFIG_FILE" <<EOF

[mailer]
mailer.from.address = $MAILER_FROM_ADDRESS
mailer.from.name = $MAILER_FROM_NAME
mailer.method = $MAILER_METHOD
EOF

    # Add SMTP configuration if method is smtp
    if [ "$MAILER_METHOD" = "smtp" ]; then
        if [ -n "$SMTP_HOST" ]; then
            cat >> "$CONFIG_FILE" <<EOF
mailer.smtp.host = $SMTP_HOST
mailer.smtp.port = $SMTP_PORT
mailer.smtp.encryption = $SMTP_ENCRYPTION
EOF
            if [ -n "$SMTP_USERNAME" ]; then
                echo "mailer.smtp.username = $SMTP_USERNAME" >> "$CONFIG_FILE"
            fi
            if [ -n "$SMTP_PASSWORD" ]; then
                echo "mailer.smtp.password = $SMTP_PASSWORD" >> "$CONFIG_FILE"
            fi
        else
            echo "WARNING: MAILER_METHOD is 'smtp' but SMTP_HOST is not set"
        fi
    fi
fi

# Add session configuration
cat >> "$CONFIG_FILE" <<EOF

[session]
session.timeout = $SESSION_TIMEOUT
EOF

# Add phlag configuration if base URL path is set
if [ -n "$PHLAG_BASE_URL_PATH" ]; then
    cat >> "$CONFIG_FILE" <<EOF

[phlag]
phlag.base_url_path = $PHLAG_BASE_URL_PATH
EOF
fi

# Set proper permissions
chown www-data:www-data "$CONFIG_FILE"
chmod 640 "$CONFIG_FILE"

echo "Configuration file generated successfully at $CONFIG_FILE"
echo "Database: $DB_PHLAG_TYPE ($DB_PHLAG_HOST:$DB_PHLAG_PORT/$DB_PHLAG_DB)"

exit 0
