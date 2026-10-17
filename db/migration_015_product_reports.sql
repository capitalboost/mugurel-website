-- Migrare 015 — rapoarte „produsul are o problemă" trimise de vizitatori de pe pagina produsului (18 oct 2026).
-- Raportul pleacă pe email la magazin (ORDER_NOTIFY_TO) + copie la REPORT_COPY_TO și se păstrează aici, ca să nu se piardă.
CREATE TABLE IF NOT EXISTS product_reports (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id   INT UNSIGNED NULL,                     -- NULL dacă produsul a fost șters între timp
    product_name VARCHAR(255) NOT NULL,                 -- numele la momentul raportului
    tip          VARCHAR(20)  NOT NULL,                 -- pret, poza, descriere, fisa, altceva
    detalii      TEXT         NULL,
    email        VARCHAR(160) NULL,                     -- opțional, dacă vizitatorul vrea răspuns
    ip           VARCHAR(45)  NOT NULL DEFAULT '',
    email_sent_at DATETIME    NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_created (created_at),
    CONSTRAINT fk_product_reports_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
