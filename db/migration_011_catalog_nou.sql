-- Migrare 011 — catalogul nou din fisierele Dunlite (16 oct, docs/PLAN-CATALOG-NOU.md, pasul N1).
--
-- Produsele noi vin dintr-un Excel cu cod de bare, denumire originala, producator, tip, categorie pe 3 niveluri,
-- tag-uri, descriere HTML, specificatii „Cheie: valoare", documente PDF, surse si status de verificare.
--   products        + barcode (UNIC — cheia reimportului), sku, slug (UNIC — adresa paginii produsului),
--                     original_name, manufacturer, product_type, description_html, verify_status,
--                     internal_notes, source_urls, imported_at
--   categories      + description (text de prezentare pe pagina categoriei, optional)
--   product_specs     specificatiile, in ordinea din fisier (filtrele se construiesc din ele)
--   product_documents fisele tehnice (PDF)
--   tags / product_tags  tag-urile, cu grupa lor (brand, grosime, aplicatie...) — aplicatiile devin colectii
-- Coloanele vechi (page_slug, section_key, card_kind...) raman: catalogul vechi le foloseste pana e inlocuit.
-- Idempotenta prin acelasi tipar ca migrarile 002-010 (guard pe information_schema).

DROP PROCEDURE IF EXISTS mig011_add_column;
DROP PROCEDURE IF EXISTS mig011_add_index;
DELIMITER //
CREATE PROCEDURE mig011_add_column(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col) THEN
        SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
        PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;
END//
CREATE PROCEDURE mig011_add_index(IN tbl VARCHAR(64), IN idx VARCHAR(64), IN ddl TEXT)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND INDEX_NAME = idx) THEN
        SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD ', ddl);
        PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;
END//
DELIMITER ;

CALL mig011_add_column('products', 'barcode',          'barcode VARCHAR(32) NULL DEFAULT NULL AFTER id');
CALL mig011_add_column('products', 'sku',              'sku VARCHAR(64) NULL DEFAULT NULL AFTER barcode');
CALL mig011_add_column('products', 'slug',             'slug VARCHAR(140) NULL DEFAULT NULL AFTER sku');
CALL mig011_add_column('products', 'original_name',    'original_name VARCHAR(255) NULL DEFAULT NULL AFTER name');
CALL mig011_add_column('products', 'manufacturer',     'manufacturer VARCHAR(120) NULL DEFAULT NULL AFTER original_name');
CALL mig011_add_column('products', 'product_type',     'product_type VARCHAR(120) NULL DEFAULT NULL AFTER manufacturer');
CALL mig011_add_column('products', 'description_html', 'description_html MEDIUMTEXT NULL DEFAULT NULL AFTER short_description');
CALL mig011_add_column('products', 'verify_status',
    'verify_status ENUM(''ok'',''de_verificat'',''negasit'') NULL DEFAULT NULL AFTER stock_status');
CALL mig011_add_column('products', 'internal_notes',   'internal_notes TEXT NULL DEFAULT NULL AFTER verify_status');
CALL mig011_add_column('products', 'source_urls',      'source_urls TEXT NULL DEFAULT NULL AFTER internal_notes');
CALL mig011_add_column('products', 'imported_at',      'imported_at DATETIME NULL DEFAULT NULL AFTER source_urls');
CALL mig011_add_index('products', 'uq_products_barcode', 'UNIQUE KEY uq_products_barcode (barcode)');
CALL mig011_add_index('products', 'uq_products_slug',    'UNIQUE KEY uq_products_slug (slug)');

CALL mig011_add_column('categories', 'description', 'description TEXT NULL DEFAULT NULL AFTER name');

CREATE TABLE IF NOT EXISTS product_specs (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    spec_key   VARCHAR(120) NOT NULL,
    spec_value TEXT NOT NULL,
    KEY idx_specs_product (product_id, sort_order),
    KEY idx_specs_key (spec_key),
    FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_documents (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    url        VARCHAR(1000) NOT NULL,
    label      VARCHAR(200) NOT NULL DEFAULT 'Fisa tehnica',
    KEY idx_docs_product (product_id, sort_order),
    FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- grp: brand | tip | material | culoare | dimensiune | grosime | diametru | lungime | cantitate | granulatie
--      | clasa | utilizare (interior/exterior) | compozitie | finisaj | module | caracteristica
--      | aplicatie (=> pagina-colectie) | ignorat
CREATE TABLE IF NOT EXISTS tags (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug       VARCHAR(120) NOT NULL,
    name       VARCHAR(120) NOT NULL,
    grp        VARCHAR(30)  NOT NULL DEFAULT 'aplicatie',
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_tags_slug (slug),
    KEY idx_tags_grp (grp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_tags (
    product_id INT UNSIGNED NOT NULL,
    tag_id     INT UNSIGNED NOT NULL,
    PRIMARY KEY (product_id, tag_id),
    KEY idx_product_tags_tag (tag_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (tag_id)     REFERENCES tags(id)     ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS mig011_add_column;
DROP PROCEDURE IF EXISTS mig011_add_index;
