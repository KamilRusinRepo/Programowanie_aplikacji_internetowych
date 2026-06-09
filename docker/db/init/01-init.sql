CREATE TABLE IF NOT EXISTS roles (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    password_changed_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS user_roles (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role_id INT NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT user_roles_user_id_role_id_unique UNIQUE (user_id, role_id)
);

CREATE TABLE IF NOT EXISTS decks (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(120) NOT NULL,
    description TEXT,
    deck_type VARCHAR(20) NOT NULL DEFAULT 'general',
    source_language VARCHAR(60) NOT NULL,
    target_language VARCHAR(60),
    category VARCHAR(60),
    background_url TEXT,
    is_public BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS cards (
    id SERIAL PRIMARY KEY,
    deck_id INT NOT NULL REFERENCES decks(id) ON DELETE CASCADE,
    front_question TEXT NOT NULL,
    example_sentence TEXT,
    image_url TEXT,
    answer TEXT NOT NULL,
    translated_example TEXT,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS study_sessions (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    deck_id INT NOT NULL REFERENCES decks(id) ON DELETE CASCADE,
    total_cards INT NOT NULL DEFAULT 0,
    correct_cards INT NOT NULL DEFAULT 0,
    wrong_cards INT NOT NULL DEFAULT 0,
    xp_earned INT NOT NULL DEFAULT 0,
    duration_seconds INT NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS study_session_answers (
    id SERIAL PRIMARY KEY,
    session_id INT NOT NULL REFERENCES study_sessions(id) ON DELETE CASCADE,
    card_id INT NOT NULL REFERENCES cards(id) ON DELETE CASCADE,
    was_correct BOOLEAN NOT NULL,
    user_answer TEXT,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS card_progress (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    card_id INT NOT NULL REFERENCES cards(id) ON DELETE CASCADE,
    attempts INT NOT NULL DEFAULT 0,
    correct_count INT NOT NULL DEFAULT 0,
    wrong_count INT NOT NULL DEFAULT 0,
    mastery_level INT NOT NULL DEFAULT 0,
    wrong_streak INT NOT NULL DEFAULT 0,
    last_answered_at TIMESTAMPTZ,
    next_review_at TIMESTAMPTZ,
    CONSTRAINT card_progress_user_card_unique UNIQUE (user_id, card_id)
);

CREATE TABLE IF NOT EXISTS deck_follows (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    deck_id INT NOT NULL REFERENCES decks(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT deck_follows_user_deck_unique UNIQUE (user_id, deck_id)
);

CREATE TABLE IF NOT EXISTS deck_reviews (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    deck_id INT NOT NULL REFERENCES decks(id) ON DELETE CASCADE,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT deck_reviews_user_deck_unique UNIQUE (user_id, deck_id)
);

CREATE TABLE IF NOT EXISTS user_daily_progress (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    progress_date DATE NOT NULL,
    answered_count INT NOT NULL DEFAULT 0,
    correct_count INT NOT NULL DEFAULT 0,
    xp_earned INT NOT NULL DEFAULT 0,
    CONSTRAINT user_daily_progress_user_date_unique UNIQUE (user_id, progress_date)
);

CREATE TABLE IF NOT EXISTS login_attempts (
    id SERIAL PRIMARY KEY,
    login_identifier VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    was_successful BOOLEAN NOT NULL DEFAULT FALSE,
    failure_reason VARCHAR(80),
    attempted_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS login_attempts_identifier_ip_attempted_idx
    ON login_attempts (login_identifier, ip_address, attempted_at DESC);

CREATE OR REPLACE FUNCTION count_recent_failed_logins(
    p_login_identifier VARCHAR,
    p_ip_address VARCHAR,
    p_minutes INT
)
RETURNS INT AS $$
BEGIN
    RETURN (
        SELECT COUNT(*)
        FROM login_attempts
        WHERE login_identifier = LOWER(TRIM(p_login_identifier))
          AND COALESCE(ip_address, '') = COALESCE(p_ip_address, '')
          AND was_successful = FALSE
          AND COALESCE(failure_reason, '') <> 'rate_limited'
          AND attempted_at >= NOW() - (p_minutes || ' minutes')::INTERVAL
    );
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION login_lock_remaining_seconds(
    p_login_identifier VARCHAR,
    p_ip_address VARCHAR,
    p_limit INT,
    p_window_minutes INT,
    p_lock_seconds INT
)
RETURNS INT AS $$
DECLARE
    latest_failed_at TIMESTAMPTZ;
    failed_count INT;
    unlock_at TIMESTAMPTZ;
BEGIN
    failed_count := count_recent_failed_logins(p_login_identifier, p_ip_address, p_window_minutes);

    IF failed_count < p_limit THEN
        RETURN 0;
    END IF;

    SELECT MAX(attempted_at)
    INTO latest_failed_at
    FROM login_attempts
    WHERE login_identifier = LOWER(TRIM(p_login_identifier))
      AND COALESCE(ip_address, '') = COALESCE(p_ip_address, '')
      AND was_successful = FALSE
      AND COALESCE(failure_reason, '') <> 'rate_limited'
      AND attempted_at >= NOW() - (p_window_minutes || ' minutes')::INTERVAL;

    unlock_at := latest_failed_at + (p_lock_seconds || ' seconds')::INTERVAL;

    IF unlock_at <= NOW() THEN
        RETURN 0;
    END IF;

    RETURN CEIL(EXTRACT(EPOCH FROM (unlock_at - NOW())))::INT;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION cleanup_old_login_attempts()
RETURNS TRIGGER AS $$
BEGIN
    DELETE FROM login_attempts
    WHERE attempted_at < NOW() - INTERVAL '30 days';

    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_cleanup_old_login_attempts ON login_attempts;
CREATE TRIGGER trg_cleanup_old_login_attempts
AFTER INSERT ON login_attempts
FOR EACH STATEMENT
EXECUTE FUNCTION cleanup_old_login_attempts();

INSERT INTO roles (name) VALUES ('USER') ON CONFLICT (name) DO NOTHING;
INSERT INTO roles (name) VALUES ('ADMIN') ON CONFLICT (name) DO NOTHING;

