SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(60)  NOT NULL UNIQUE,
    email         VARCHAR(180) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin','editor') NOT NULL DEFAULT 'editor',
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at DATETIME     NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id         SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug       VARCHAR(80)  NOT NULL UNIQUE,
    name       VARCHAR(120) NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO categories (slug, name, sort_order) VALUES
    ('acoperis','Acoperiș',1),('izolatie','Izolație',2),
    ('gips-carton','Gips Carton',3),('zidarie-bca','Zidărie BCA',4),
    ('gard-imprejmuiri','Gard & Împrejmuiri',5),('electrice','Electrice',6),
    ('incalzire','Încălzire',7),('sanitare','Sanitare',8),
    ('gradina','Grădină',9),('mobilier','Mobilier',10),
    ('electrocasnice','Electrocasnice',11),('scule-unelte','Scule & Unelte',12),
    ('apa-canal','Apă & Canal',13);

CREATE TABLE IF NOT EXISTS products (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id       SMALLINT UNSIGNED NOT NULL,
    name              VARCHAR(200) NOT NULL,
    short_description TEXT         NULL DEFAULT NULL,
    image_path        VARCHAR(500) NULL DEFAULT NULL,
    image_alt         VARCHAR(200) NULL DEFAULT NULL,
    price             DECIMAL(10,2) UNSIGNED NULL DEFAULT NULL,
    price_unit        VARCHAR(40)  NULL DEFAULT NULL,
    is_visible        TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_by        INT UNSIGNED NOT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_cat_sort (category_id, sort_order, is_visible),
    FOREIGN KEY (category_id) REFERENCES categories(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pages (
    id               SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug             VARCHAR(100) NOT NULL UNIQUE,
    title            VARCHAR(200) NOT NULL,
    content          LONGTEXT     NULL DEFAULT NULL,
    meta_title       VARCHAR(160) NULL DEFAULT NULL,
    meta_description VARCHAR(320) NULL DEFAULT NULL,
    restricted       TINYINT(1)   NOT NULL DEFAULT 0,
    is_published     TINYINT(1)   NOT NULL DEFAULT 1,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by       INT UNSIGNED NULL DEFAULT NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO pages (slug, title, restricted) VALUES
    ('despre-noi','Despre Noi',0),
    ('contact','Contact',0),
    ('politica-confidentialitate','Politica de Confidențialitate',1),
    ('termeni-conditii','Termeni și Condiții',1),
    ('politica-retur','Politica de Retur',1);

CREATE TABLE IF NOT EXISTS audit_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL DEFAULT NULL,
    entity_type VARCHAR(50)  NOT NULL,
    entity_id   INT UNSIGNED NULL DEFAULT NULL,
    action      VARCHAR(40)  NOT NULL,
    changes_json JSON        NULL DEFAULT NULL,
    ip_address  VARCHAR(45)  NULL DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_entity (entity_type, entity_id),
    KEY idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip           VARBINARY(16) NOT NULL,
    attempted_at DATETIME NOT NULL,
    INDEX idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
