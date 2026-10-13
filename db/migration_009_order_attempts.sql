-- Migrare 009 — incercarile de trimitere a comenzii, pentru limitarea pe IP (anti-abuz).
-- Se pastreaza doar cat dureaza fereastra de limitare; randurile vechi se sterg automat la fiecare trimitere.
-- Idempotenta: CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS order_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ip_time (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
