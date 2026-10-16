-- Migrare 013 — cererile de retur trimise din formularul /cerere-retur.html (16 oct 2026).
-- Cererea ajunge pe email la magazin (ORDER_NOTIFY_TO = comenzi@, redirectionata spre dunlite@gmail.com) si se
-- pastreaza aici, ca sa nu se piarda daca emailul nu pleaca. Curierul ridica produsele; transportul il plateste magazinul.
CREATE TABLE IF NOT EXISTS return_requests (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_number  VARCHAR(20)  NULL DEFAULT NULL,            -- RET-2026-000001, setat dupa INSERT
    form_token      CHAR(32)     NOT NULL,                     -- anti-dublura (al doilea click / refresh)
    tip             ENUM('retragere','neconform') NOT NULL,   -- renunt in 14 zile / produs defect sau neconform
    order_ref       VARCHAR(40)  NOT NULL,                     -- numarul comenzii sau al facturii, cum il stie clientul
    nume            VARCHAR(120) NOT NULL,
    telefon         VARCHAR(20)  NOT NULL,
    email           VARCHAR(160) NOT NULL,
    judet           VARCHAR(40)  NOT NULL,
    localitate      VARCHAR(80)  NOT NULL,
    adresa          VARCHAR(200) NOT NULL,                     -- de unde ridica curierul
    produse         TEXT         NOT NULL,
    data_primirii   DATE         NULL DEFAULT NULL,
    motiv           TEXT         NULL DEFAULT NULL,
    iban            VARCHAR(34)  NULL DEFAULT NULL,            -- pentru rambursare, daca s-a platit numerar
    status          ENUM('noua','in_lucru','finalizata') NOT NULL DEFAULT 'noua',
    email_admin_at  DATETIME     NULL DEFAULT NULL,
    email_client_at DATETIME     NULL DEFAULT NULL,
    ip              VARCHAR(45)  NOT NULL DEFAULT '',
    user_agent      VARCHAR(255) NOT NULL DEFAULT '',
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_return_form_token (form_token),
    UNIQUE KEY uq_return_number (request_number),
    KEY idx_return_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
