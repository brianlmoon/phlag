CREATE TABLE phlags (
    phlag_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    start_datetime TEXT DEFAULT NULL,
    end_datetime TEXT DEFAULT NULL,
    type TEXT CHECK(type IN ('SWITCH', 'INTEGER', 'FLOAT', 'STRING')) DEFAULT NULL,
    value TEXT DEFAULT NULL,
    create_datetime TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    update_datetime TEXT DEFAULT NULL
);

CREATE TRIGGER phlags_update_timestamp
    AFTER UPDATE ON phlags
    FOR EACH ROW
BEGIN
    UPDATE phlags SET update_datetime = CURRENT_TIMESTAMP WHERE phlag_id = NEW.phlag_id;
END;

CREATE TABLE phlag_api_keys (
    plag_api_key_id INTEGER PRIMARY KEY AUTOINCREMENT,
    description TEXT NOT NULL UNIQUE,
    api_key TEXT NOT NULL UNIQUE,
    create_datetime TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE phlag_users (
    phlag_user_id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    full_name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    create_datetime TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    update_datetime TEXT DEFAULT NULL
);

CREATE TRIGGER phlag_users_update_timestamp
    AFTER UPDATE ON phlag_users
    FOR EACH ROW
BEGIN
    UPDATE phlag_users SET update_datetime = CURRENT_TIMESTAMP WHERE phlag_user_id = NEW.phlag_user_id;
END;

CREATE TABLE phlag_environments (
    phlag_environment_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    create_datetime TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    update_datetime TEXT DEFAULT NULL
);

CREATE TRIGGER phlag_environments_update_timestamp
    AFTER UPDATE ON phlag_environments
    FOR EACH ROW
BEGIN
    UPDATE phlag_environments SET update_datetime = CURRENT_TIMESTAMP WHERE phlag_environment_id = NEW.phlag_environment_id;
END;

CREATE TABLE phlag_password_reset_tokens (
    phlag_password_reset_token_id INTEGER PRIMARY KEY AUTOINCREMENT,
    phlag_user_id INTEGER NOT NULL,
    token TEXT NOT NULL UNIQUE,
    expires_at TEXT NOT NULL,
    used INTEGER NOT NULL DEFAULT 0,
    create_datetime TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (phlag_user_id) REFERENCES phlag_users (phlag_user_id) ON DELETE CASCADE
);

CREATE INDEX idx_password_reset_user ON phlag_password_reset_tokens(phlag_user_id);
CREATE INDEX idx_password_reset_expires ON phlag_password_reset_tokens(expires_at);
