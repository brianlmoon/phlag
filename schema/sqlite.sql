-- Phlag Complete Schema (SQLite)

CREATE TABLE phlags (
    phlag_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    type TEXT NOT NULL CHECK(type IN ('SWITCH', 'INTEGER', 'FLOAT', 'STRING')),
    create_datetime TEXT NOT NULL DEFAULT (datetime('now')),
    update_datetime TEXT DEFAULT NULL
);

CREATE TABLE phlag_api_keys (
    plag_api_key_id INTEGER PRIMARY KEY AUTOINCREMENT,
    description TEXT NOT NULL UNIQUE,
    api_key TEXT NOT NULL UNIQUE,
    create_datetime TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE phlag_users (
    phlag_user_id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    full_name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    create_datetime TEXT NOT NULL DEFAULT (datetime('now')),
    update_datetime TEXT DEFAULT NULL
);

CREATE TABLE phlag_environments (
    phlag_environment_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    sort_order INTEGER NOT NULL DEFAULT 0,
    create_datetime TEXT NOT NULL DEFAULT (datetime('now')),
    update_datetime TEXT DEFAULT NULL
);

CREATE INDEX idx_environments_sort ON phlag_environments(sort_order);

CREATE TABLE phlag_environment_values (
    phlag_environment_value_id INTEGER PRIMARY KEY AUTOINCREMENT,
    phlag_id INTEGER NOT NULL,
    phlag_environment_id INTEGER NOT NULL,
    value TEXT DEFAULT NULL,
    start_datetime TEXT DEFAULT NULL,
    end_datetime TEXT DEFAULT NULL,
    create_datetime TEXT NOT NULL DEFAULT (datetime('now')),
    update_datetime TEXT DEFAULT NULL,
    FOREIGN KEY (phlag_id) REFERENCES phlags(phlag_id) ON DELETE CASCADE,
    FOREIGN KEY (phlag_environment_id) REFERENCES phlag_environments(phlag_environment_id) ON DELETE CASCADE,
    UNIQUE (phlag_id, phlag_environment_id)
);

CREATE INDEX idx_env_values_phlag ON phlag_environment_values(phlag_id);
CREATE INDEX idx_env_values_environment ON phlag_environment_values(phlag_environment_id);

CREATE TABLE phlag_password_reset_tokens (
    phlag_password_reset_token_id INTEGER PRIMARY KEY AUTOINCREMENT,
    phlag_user_id INTEGER NOT NULL,
    token TEXT NOT NULL UNIQUE,
    expires_at TEXT NOT NULL,
    used INTEGER NOT NULL DEFAULT 0,
    create_datetime TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (phlag_user_id) REFERENCES phlag_users(phlag_user_id) ON DELETE CASCADE
);

CREATE INDEX idx_password_reset_user ON phlag_password_reset_tokens(phlag_user_id);
CREATE INDEX idx_password_reset_expires ON phlag_password_reset_tokens(expires_at);
