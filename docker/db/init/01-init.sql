CREATE TABLE IF NOT EXISTS app_healthcheck (
    id SERIAL PRIMARY KEY,
    message VARCHAR(100) NOT NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO app_healthcheck (message) VALUES ('database ready');
