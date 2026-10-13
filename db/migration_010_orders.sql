-- Migrare 010 — comenzile online si produsele lor.
--
-- orders.form_token   — unic per afisare a formularului: acelasi formular trimis de doua ori = o singura comanda.
-- orders.content_hash — aceleasi date + acelasi cos in 10 minute = aceeasi comanda (ex. „Inapoi" + retrimitere).
-- orders.public_token — aleator, pentru pagina de confirmare (nu se pot ghici comenzile altora dupa numar).
-- order_items         — copie a produsului la momentul comenzii (nume, pret, unitate), ca o schimbare
--                       ulterioara de pret sa nu modifice comenzi deja primite.
-- Idempotenta: CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS orders (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_number    VARCHAR(20)  NULL,
    public_token    CHAR(48)     NOT NULL,
    form_token      CHAR(32)     NOT NULL,
    content_hash    CHAR(64)     NOT NULL,
    status          ENUM('noua','confirmata','livrata','anulata') NOT NULL DEFAULT 'noua',
    tip             ENUM('pf','pj') NOT NULL,
    nume            VARCHAR(100) NOT NULL,
    telefon         VARCHAR(20)  NOT NULL,
    email           VARCHAR(254) NOT NULL,
    plata           ENUM('ramburs','transfer','ridicare') NOT NULL,
    judet           VARCHAR(40)  NOT NULL DEFAULT '',
    localitate      VARCHAR(80)  NOT NULL DEFAULT '',
    adresa          VARCHAR(200) NOT NULL DEFAULT '',
    mentiuni        TEXT         NULL,
    firma           VARCHAR(150) NOT NULL DEFAULT '',
    cui             VARCHAR(16)  NOT NULL DEFAULT '',
    reg_com         VARCHAR(24)  NOT NULL DEFAULT '',
    sediu           VARCHAR(200) NOT NULL DEFAULT '',
    total_bani      INT UNSIGNED NOT NULL,
    ip              VARCHAR(45)  NOT NULL DEFAULT '',
    user_agent      VARCHAR(255) NOT NULL DEFAULT '',
    email_admin_at  DATETIME     NULL,
    email_client_at DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_order_number (order_number),
    UNIQUE KEY uq_public_token (public_token),
    UNIQUE KEY uq_form_token (form_token),
    KEY idx_content_time (content_hash, created_at),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id     INT UNSIGNED NOT NULL,
    product_id   INT UNSIGNED NULL,
    name         VARCHAR(200) NOT NULL,
    unit         VARCHAR(40)  NULL,
    qty          DECIMAL(10,2) NOT NULL,
    price_bani   INT UNSIGNED NOT NULL,
    line_bani    INT UNSIGNED NOT NULL,
    stock_status VARCHAR(20)  NULL,
    PRIMARY KEY (id),
    KEY idx_order (order_id),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
