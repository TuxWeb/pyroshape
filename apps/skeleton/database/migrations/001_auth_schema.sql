CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    pwdhash VARCHAR(255) NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    nickname VARCHAR(255) NULL,
    name VARCHAR(255) NULL,
    surname VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY users_email_unique (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    users_id BIGINT UNSIGNED NOT NULL,
    jti VARCHAR(255) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    released DATETIME NOT NULL,
    expiration DATETIME NOT NULL,
    revocation_time DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY auth_tokens_jti_unique (jti),
    KEY auth_tokens_users_id_index (users_id),
    KEY auth_tokens_expiration_index (expiration),
    CONSTRAINT auth_tokens_users_id_foreign
        FOREIGN KEY (users_id) REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_verification_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    users_id BIGINT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expiration DATETIME NOT NULL,
    used_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY email_verification_tokens_token_hash_unique (token_hash),
    KEY email_verification_tokens_users_id_index (users_id),
    CONSTRAINT email_verification_tokens_users_id_foreign
        FOREIGN KEY (users_id) REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
