-- Phlag v2.0 Complete Schema (PostgreSQL)
-- This is the complete database schema for Phlag v2.0 with environment-specific flag values

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
