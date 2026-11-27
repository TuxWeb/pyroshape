BEGIN;

ALTER TABLE users
    ADD COLUMN totp_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN totp_secret_encrypted TEXT NULL,
    ADD COLUMN totp_enabled_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    ADD COLUMN totp_disabled_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    ADD COLUMN totp_last_used_step BIGINT NULL,
    ADD COLUMN totp_recovery_code_hash VARCHAR(255) NULL,
    ADD COLUMN auth_version BIGINT NOT NULL DEFAULT 0,
    ADD CONSTRAINT users_auth_version_check CHECK (auth_version >= 0),
    ADD CONSTRAINT users_totp_state_check CHECK (
        (totp_enabled = TRUE AND totp_secret_encrypted IS NOT NULL)
        OR (totp_enabled = FALSE AND totp_secret_encrypted IS NULL
            AND totp_last_used_step IS NULL AND totp_recovery_code_hash IS NULL)
    );

CREATE TABLE auth_totp_flows (
    flow_id VARCHAR(128) NOT NULL,
    users_id BIGINT NOT NULL,
    purpose VARCHAR(8) NOT NULL,
    auth_version BIGINT NOT NULL,
    expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    consumed_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    failed_attempts INTEGER NOT NULL DEFAULT 0,
    credential_hash CHAR(64) NULL,
    refresh_jti VARCHAR(255) NULL,
    remember_me BOOLEAN NULL,
    candidate_encrypted TEXT NULL,
    PRIMARY KEY (flow_id),
    CONSTRAINT auth_totp_flows_credential_hash_unique UNIQUE (credential_hash),
    CONSTRAINT auth_totp_flows_users_id_foreign
        FOREIGN KEY (users_id) REFERENCES users (id)
        ON DELETE CASCADE,
    CONSTRAINT auth_totp_flows_version_check CHECK (auth_version >= 0),
    CONSTRAINT auth_totp_flows_attempts_check CHECK (failed_attempts BETWEEN 0 AND 5),
    CONSTRAINT auth_totp_flows_purpose_check CHECK (
        (purpose = 'login' AND credential_hash IS NOT NULL AND refresh_jti IS NULL AND remember_me IS NOT NULL
            AND candidate_encrypted IS NULL)
        OR (purpose = 'setup' AND credential_hash IS NULL AND refresh_jti IS NOT NULL AND remember_me IS NULL)
        OR (purpose = 'recovery' AND credential_hash IS NOT NULL AND refresh_jti IS NULL AND remember_me IS NULL)
    ),
    CONSTRAINT auth_totp_flows_candidate_check CHECK (
        (purpose = 'login')
        OR (consumed_at IS NULL AND candidate_encrypted IS NOT NULL)
        OR (consumed_at IS NOT NULL AND candidate_encrypted IS NULL)
    )
);

CREATE INDEX auth_totp_flows_expiration_index ON auth_totp_flows (expires_at, flow_id);
CREATE INDEX auth_totp_flows_users_id_index ON auth_totp_flows (users_id, consumed_at, purpose, expires_at);

CREATE TABLE auth_totp_throttle (
    users_id BIGINT NOT NULL,
    window_started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    failed_attempts INTEGER NOT NULL,
    PRIMARY KEY (users_id),
    CONSTRAINT auth_totp_throttle_users_id_foreign
        FOREIGN KEY (users_id) REFERENCES users (id)
        ON DELETE CASCADE,
    CONSTRAINT auth_totp_throttle_attempts_check CHECK (failed_attempts BETWEEN 1 AND 10)
);

COMMIT;
