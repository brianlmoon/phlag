-- Phlag Complete Schema (PostgreSQL)

CREATE TYPE phlag_type AS ENUM ('SWITCH', 'INTEGER', 'FLOAT', 'STRING');

CREATE TABLE phlags (
    phlag_id bigserial PRIMARY KEY,
    name varchar(255) NOT NULL UNIQUE,
    description varchar(1024) DEFAULT NULL,
    type phlag_type NOT NULL,
    create_datetime timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    update_datetime timestamp DEFAULT NULL
);

CREATE TABLE phlag_api_keys (
    plag_api_key_id bigserial PRIMARY KEY,
    description varchar(255) NOT NULL UNIQUE,
    api_key varchar(64) NOT NULL UNIQUE,
    create_datetime timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE phlag_users (
    phlag_user_id bigserial PRIMARY KEY,
    username varchar(255) NOT NULL UNIQUE,
    full_name varchar(255) NOT NULL,
    email varchar(255) NOT NULL UNIQUE,
    password varchar(255) NOT NULL,
    google_id varchar(255) DEFAULT NULL UNIQUE,
    create_datetime timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    update_datetime timestamp DEFAULT NULL
);

CREATE TABLE phlag_environments (
    phlag_environment_id bigserial PRIMARY KEY,
    name varchar(255) NOT NULL UNIQUE,
    sort_order integer NOT NULL DEFAULT 0,
    create_datetime timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    update_datetime timestamp DEFAULT NULL
);

CREATE INDEX idx_environments_sort ON phlag_environments(sort_order);

CREATE TABLE phlag_environment_values (
    phlag_environment_value_id bigserial PRIMARY KEY,
    phlag_id bigint NOT NULL REFERENCES phlags(phlag_id) ON DELETE CASCADE,
    phlag_environment_id bigint NOT NULL REFERENCES phlag_environments(phlag_environment_id) ON DELETE CASCADE,
    value varchar(255) DEFAULT NULL,
    start_datetime timestamp DEFAULT NULL,
    end_datetime timestamp DEFAULT NULL,
    create_datetime timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    update_datetime timestamp DEFAULT NULL,
    UNIQUE (phlag_id, phlag_environment_id)
);

CREATE INDEX idx_env_values_phlag ON phlag_environment_values(phlag_id);
CREATE INDEX idx_env_values_environment ON phlag_environment_values(phlag_environment_id);

CREATE TABLE phlag_api_key_environments (
    phlag_api_key_environment_id bigserial PRIMARY KEY,
    plag_api_key_id bigint NOT NULL REFERENCES phlag_api_keys(plag_api_key_id) ON DELETE CASCADE,
    phlag_environment_id bigint NOT NULL REFERENCES phlag_environments(phlag_environment_id) ON DELETE CASCADE,
    create_datetime timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (plag_api_key_id, phlag_environment_id)
);

CREATE INDEX idx_api_key_env_key ON phlag_api_key_environments(plag_api_key_id);
CREATE INDEX idx_api_key_env_environment ON phlag_api_key_environments(phlag_environment_id);

CREATE TABLE phlag_password_reset_tokens (
    phlag_password_reset_token_id bigserial PRIMARY KEY,
    phlag_user_id bigint NOT NULL REFERENCES phlag_users(phlag_user_id) ON DELETE CASCADE,
    token varchar(64) NOT NULL UNIQUE,
    expires_at timestamp NOT NULL,
    used boolean NOT NULL DEFAULT false,
    create_datetime timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_password_reset_user ON phlag_password_reset_tokens(phlag_user_id);
CREATE INDEX idx_password_reset_expires ON phlag_password_reset_tokens(expires_at);

CREATE TABLE phlag_sessions (
    session_id varchar(128) PRIMARY KEY,
    session_data text NOT NULL,
    last_activity integer NOT NULL,
    create_datetime timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_session_activity ON phlag_sessions(last_activity);

CREATE TABLE phlag_webhooks (
    phlag_webhook_id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    url VARCHAR(2048) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT true,
    headers_json TEXT,
    payload_template TEXT,
    event_types_json TEXT NOT NULL,
    include_environment_changes BOOLEAN NOT NULL DEFAULT false,
    create_datetime TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    update_datetime TIMESTAMP
);

CREATE INDEX idx_webhook_name ON phlag_webhooks(name);
CREATE INDEX idx_webhook_active ON phlag_webhooks(is_active);
