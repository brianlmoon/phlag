CREATE TABLE phlags (
    phlag_id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description VARCHAR(1024) DEFAULT NULL,
    start_datetime TIMESTAMP DEFAULT NULL,
    end_datetime TIMESTAMP DEFAULT NULL,
    type VARCHAR(10) CHECK (type IN ('SWITCH', 'INTEGER', 'FLOAT', 'STRING')) DEFAULT NULL,
    value VARCHAR(255) DEFAULT NULL,
    create_datetime TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    update_datetime TIMESTAMP DEFAULT NULL
);

CREATE OR REPLACE FUNCTION update_phlags_timestamp()
RETURNS TRIGGER AS $$
BEGIN
    NEW.update_datetime = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER phlags_update_timestamp
    BEFORE UPDATE ON phlags
    FOR EACH ROW
    EXECUTE FUNCTION update_phlags_timestamp();

CREATE TABLE phlag_api_keys (
    plag_api_key_id BIGSERIAL PRIMARY KEY,
    description VARCHAR(255) NOT NULL UNIQUE,
    api_key VARCHAR(64) NOT NULL UNIQUE,
    create_datetime TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE phlag_users (
    phlag_user_id BIGSERIAL PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    create_datetime TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    update_datetime TIMESTAMP DEFAULT NULL
);

CREATE OR REPLACE FUNCTION update_phlag_users_timestamp()
RETURNS TRIGGER AS $$
BEGIN
    NEW.update_datetime = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER phlag_users_update_timestamp
    BEFORE UPDATE ON phlag_users
    FOR EACH ROW
    EXECUTE FUNCTION update_phlag_users_timestamp();

CREATE TABLE phlag_environments (
    phlag_environment_id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    create_datetime TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    update_datetime TIMESTAMP DEFAULT NULL
);

CREATE OR REPLACE FUNCTION update_phlag_environments_timestamp()
RETURNS TRIGGER AS $$
BEGIN
    NEW.update_datetime = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER phlag_environments_update_timestamp
    BEFORE UPDATE ON phlag_environments
    FOR EACH ROW
    EXECUTE FUNCTION update_phlag_environments_timestamp();

CREATE TABLE phlag_password_reset_tokens (
    phlag_password_reset_token_id BIGSERIAL PRIMARY KEY,
    phlag_user_id BIGINT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN NOT NULL DEFAULT FALSE,
    create_datetime TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_reset_user FOREIGN KEY (phlag_user_id)
        REFERENCES phlag_users (phlag_user_id) ON DELETE CASCADE
);

CREATE INDEX idx_password_reset_user ON phlag_password_reset_tokens(phlag_user_id);
CREATE INDEX idx_password_reset_expires ON phlag_password_reset_tokens(expires_at);
