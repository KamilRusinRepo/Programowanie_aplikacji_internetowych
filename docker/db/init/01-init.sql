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

CREATE OR REPLACE VIEW public_deck_statistics AS
SELECT
    d.id AS deck_id,
    d.user_id AS owner_id,
    u.username AS owner_username,
    d.name,
    d.description,
    d.deck_type,
    d.source_language,
    d.target_language,
    d.category,
    d.background_url,
    d.created_at,
    COUNT(DISTINCT c.id) AS card_count,
    COUNT(DISTINCT df.user_id) AS learner_count,
    COALESCE(ROUND(AVG(dr.rating)::numeric, 1), 0) AS average_rating,
    COUNT(DISTINCT dr.id) AS review_count
FROM decks d
INNER JOIN users u ON u.id = d.user_id
LEFT JOIN cards c ON c.deck_id = d.id
LEFT JOIN deck_follows df ON df.deck_id = d.id
LEFT JOIN deck_reviews dr ON dr.deck_id = d.id
WHERE d.is_public = TRUE
GROUP BY
    d.id,
    d.user_id,
    u.username,
    d.name,
    d.description,
    d.deck_type,
    d.source_language,
    d.target_language,
    d.category,
    d.background_url,
    d.created_at;

CREATE OR REPLACE VIEW user_learning_summary AS
SELECT
    u.id AS user_id,
    u.username,
    u.email,
    COALESCE(user_role.role_name, 'USER') AS role_name,
    COALESCE(owned.owned_decks, 0) AS owned_decks,
    COALESCE(followed.followed_decks, 0) AS followed_decks,
    COALESCE(sessions.study_sessions, 0) AS study_sessions,
    COALESCE(progress.total_xp, 0) AS total_xp,
    COALESCE(progress.total_answered, 0) AS total_answered,
    COALESCE(progress.total_correct, 0) AS total_correct,
    COALESCE(mastery.mastered_cards, 0) AS mastered_cards,
    COALESCE(sessions.study_seconds, 0) AS study_seconds
FROM users u
LEFT JOIN (
    SELECT ur.user_id, MIN(r.name) AS role_name
    FROM user_roles ur
    INNER JOIN roles r ON r.id = ur.role_id
    GROUP BY ur.user_id
) user_role ON user_role.user_id = u.id
LEFT JOIN (
    SELECT user_id, COUNT(*) AS owned_decks
    FROM decks
    GROUP BY user_id
) owned ON owned.user_id = u.id
LEFT JOIN (
    SELECT user_id, COUNT(*) AS followed_decks
    FROM deck_follows
    GROUP BY user_id
) followed ON followed.user_id = u.id
LEFT JOIN (
    SELECT user_id, COUNT(*) AS study_sessions, COALESCE(SUM(duration_seconds), 0) AS study_seconds
    FROM study_sessions
    GROUP BY user_id
) sessions ON sessions.user_id = u.id
LEFT JOIN (
    SELECT user_id,
           COALESCE(SUM(xp_earned), 0) AS total_xp,
           COALESCE(SUM(answered_count), 0) AS total_answered,
           COALESCE(SUM(correct_count), 0) AS total_correct
    FROM user_daily_progress
    GROUP BY user_id
) progress ON progress.user_id = u.id
LEFT JOIN (
    SELECT user_id, COUNT(*) AS mastered_cards
    FROM card_progress
    WHERE mastery_level >= 4
    GROUP BY user_id
) mastery ON mastery.user_id = u.id;

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

