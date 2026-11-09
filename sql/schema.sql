-- Skyhint.app database schema (MariaDB 10.6+ / MySQL 8+)
-- Applied by bin/setup.php. Safe to run again: only creates what is missing.

CREATE TABLE IF NOT EXISTS cities (
    id            INT UNSIGNED  NOT NULL PRIMARY KEY,          -- GeoNames ID
    name          VARCHAR(200)  NOT NULL,
    region        VARCHAR(200)  NOT NULL DEFAULT '',
    country       CHAR(2)       NOT NULL,
    timezone      VARCHAR(64)   NOT NULL,
    latitude      DECIMAL(8,4)  NOT NULL,
    longitude     DECIMAL(8,4)  NOT NULL,
    updated_at    DATETIME      NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email              VARCHAR(254) NOT NULL,
    password_hash      VARCHAR(255) NOT NULL,
    email_verified_at  DATETIME     NULL,
    lang               CHAR(2)      NOT NULL DEFAULT 'de',
    mail_style         ENUM('alerts','digest','both') NOT NULL DEFAULT 'alerts',
    mail_enabled       TINYINT(1)   NOT NULL DEFAULT 1,       -- master switch, unsubscribe sets 0
    paused_until       DATE         NULL,
    unsubscribe_token  CHAR(32)     NOT NULL,
    bounce_count       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at         DATETIME     NOT NULL,
    last_login_at      DATETIME     NULL,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One account can watch up to 10 places, each with its own planets and event types.
CREATE TABLE IF NOT EXISTS alerts (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    name        VARCHAR(80)  NOT NULL DEFAULT '',
    city_id     INT UNSIGNED NOT NULL,
    prefs       JSON         NOT NULL,                        -- {"planets": [...], "events": [...]}
    enabled     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL,
    updated_at  DATETIME     NOT NULL,
    KEY ix_alerts_user (user_id),
    KEY ix_alerts_city (city_id),
    CONSTRAINT fk_alerts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_alerts_city FOREIGN KEY (city_id) REFERENCES cities(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_tokens (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    type        ENUM('verify','reset') NOT NULL,
    token_hash  CHAR(64)     NOT NULL,
    expires_at  DATETIME     NOT NULL,
    created_at  DATETIME     NOT NULL,
    UNIQUE KEY uq_tokens_hash (token_hash),
    KEY ix_tokens_user (user_id, type),
    CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_keys (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    name          VARCHAR(80)  NOT NULL,
    prefix        CHAR(12)     NOT NULL,                       -- shown in the dashboard
    key_hash      CHAR(64)     NOT NULL,                       -- sha256 of the full key
    created_at    DATETIME     NOT NULL,
    last_used_at  DATETIME     NULL,
    revoked_at    DATETIME     NULL,
    UNIQUE KEY uq_keys_hash (key_hash),
    KEY ix_keys_user (user_id),
    CONSTRAINT fk_keys_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Results from the calc service, shared by all users of a city.
CREATE TABLE IF NOT EXISTS calc_cache (
    cache_key   VARCHAR(190) NOT NULL PRIMARY KEY,
    payload     MEDIUMTEXT   NOT NULL,
    expires_at  DATETIME     NOT NULL,
    KEY ix_cache_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per event sent to a user; prevents duplicates.
CREATE TABLE IF NOT EXISTS sent_log (
    user_id    INT UNSIGNED NOT NULL,
    event_key  VARCHAR(120) NOT NULL,
    sent_at    DATETIME     NOT NULL,
    PRIMARY KEY (user_id, event_key),
    CONSTRAINT fk_sent_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mail job progress per user and local day: lets the job retry and report.
CREATE TABLE IF NOT EXISTS mail_runs (
    user_id     INT UNSIGNED NOT NULL,
    local_date  DATE         NOT NULL,
    kind        ENUM('alerts','digest') NOT NULL,
    status      ENUM('done','failed','skipped') NOT NULL,
    attempts    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    mails_sent  INT UNSIGNED NOT NULL DEFAULT 0,
    message     VARCHAR(500) NOT NULL DEFAULT '',
    updated_at  DATETIME     NOT NULL,
    PRIMARY KEY (user_id, local_date, kind),
    CONSTRAINT fk_runs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    bucket        VARCHAR(190) NOT NULL,
    window_start  INT UNSIGNED NOT NULL,
    hits          INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (bucket, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Small key/value store for the job (e.g. date of the last owner status mail).
CREATE TABLE IF NOT EXISTS app_state (
    name   VARCHAR(64)  NOT NULL PRIMARY KEY,
    value  VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily counters for the status page (signups, mails, API requests, ...). No personal data.
CREATE TABLE IF NOT EXISTS stat_counters (
    day    DATE        NOT NULL,
    name   VARCHAR(48) NOT NULL,
    value  BIGINT      NOT NULL DEFAULT 0,
    PRIMARY KEY (day, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per mail-job run (every 15 minutes): is the calc server reachable, how fast.
CREATE TABLE IF NOT EXISTS status_checks (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    checked_at  DATETIME     NOT NULL,
    calc_ok     TINYINT(1)   NOT NULL,
    calc_ms     INT UNSIGNED NULL,
    KEY ix_checks_time (checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
