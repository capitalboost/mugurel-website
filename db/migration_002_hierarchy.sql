-- Migrare 002 — ierarhie de categorii + plasare editoriala pentru produse.
-- Idempotenta: modificarile de structura (ADD COLUMN / ADD KEY / ADD CONSTRAINT)
-- sunt aplicate printr-o procedura temporara care verifica information_schema
-- inainte de fiecare schimbare si o sare daca exista deja. Procedurile sunt
-- sterse la final, deci fisierul se poate rula de mai multe ori fara efecte
-- secundare si fara sa lase nimic in urma in baza de date.

-- ── 0. Proceduri temporare pentru modificari idempotente de structura ──
DROP PROCEDURE IF EXISTS mig002_add_column;
DROP PROCEDURE IF EXISTS mig002_add_index;
DROP PROCEDURE IF EXISTS mig002_add_fk;

DELIMITER //

CREATE PROCEDURE mig002_add_column(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col) THEN
        SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
        PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;
END//

CREATE PROCEDURE mig002_add_index(IN tbl VARCHAR(64), IN idx VARCHAR(64), IN ddl TEXT)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND INDEX_NAME = idx) THEN
        SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD ', ddl);
        PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;
END//

CREATE PROCEDURE mig002_add_fk(IN tbl VARCHAR(64), IN fk VARCHAR(64), IN ddl TEXT)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND CONSTRAINT_NAME = fk
                     AND CONSTRAINT_TYPE = 'FOREIGN KEY') THEN
        SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD ', ddl);
        PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;
END//

DELIMITER ;

-- ── 1. Ierarhie de categorii ──────────────────────────────────────────
CALL mig002_add_column('categories', 'parent_id', 'parent_id SMALLINT UNSIGNED NULL DEFAULT NULL AFTER slug');
CALL mig002_add_index('categories', 'idx_parent', 'KEY idx_parent (parent_id)');
CALL mig002_add_fk('categories', 'fk_cat_parent',
    'CONSTRAINT fk_cat_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON UPDATE CASCADE ON DELETE RESTRICT');

-- ── 2. Nume fara diacritice (conventia site-ului) ─────────────────────
UPDATE categories SET name='Acoperis'           WHERE slug='acoperis';
UPDATE categories SET name='Izolatie'           WHERE slug='izolatie';
UPDATE categories SET name='Gips Carton'        WHERE slug='gips-carton';
UPDATE categories SET name='Zidarie BCA'        WHERE slug='zidarie-bca';
UPDATE categories SET name='Gard & Imprejmuiri' WHERE slug='gard-imprejmuiri';
UPDATE categories SET name='Incalzire'          WHERE slug='incalzire';
UPDATE categories SET name='Gradina'            WHERE slug='gradina';
UPDATE categories SET name='Apa & Canal'        WHERE slug='apa-canal';

-- ── 3. Constructii ca parinte al celor 5 pagini de materiale ──────────
INSERT IGNORE INTO categories (slug, name, sort_order) VALUES ('constructii','Constructii',0);

UPDATE categories SET parent_id = (SELECT id FROM (SELECT id FROM categories WHERE slug='constructii') AS c)
WHERE slug IN ('acoperis','izolatie','gips-carton','zidarie-bca','gard-imprejmuiri');

-- ── 4. Cele 17 subcategorii din sidebar ───────────────────────────────
INSERT IGNORE INTO categories (slug, name, parent_id, sort_order)
SELECT v.slug, v.name, p.id, v.so FROM (
    SELECT 'cabluri'         AS slug, 'Cabluri & Conductori' AS name, 'electrice' AS parent, 1 AS so UNION ALL
    SELECT 'becuri',         'Becuri LED',           'electrice', 2 UNION ALL
    SELECT 'aparataj',       'Aparataj Electric',    'electrice', 3 UNION ALL
    SELECT 'iluminat',       'Corpuri Iluminat',     'electrice', 4 UNION ALL
    SELECT 'centrale',       'Centrale termice',     'incalzire', 1 UNION ALL
    SELECT 'radiatoare',     'Radiatoare',           'incalzire', 2 UNION ALL
    SELECT 'aer-conditionat','Aer conditionat',      'incalzire', 3 UNION ALL
    SELECT 'sobe',           'Sobe & Seminee',       'incalzire', 4 UNION ALL
    SELECT 'baterii',        'Baterii de apa',       'sanitare',  1 UNION ALL
    SELECT 'mobilier-baie',  'Mobilier baie',        'sanitare',  2 UNION ALL
    SELECT 'accesorii-baie', 'Accesorii baie',       'sanitare',  3 UNION ALL
    SELECT 'tevi',           'Tevi & Fitting',       'apa-canal', 1 UNION ALL
    SELECT 'canalizare',     'Canalizare PVC',       'apa-canal', 2 UNION ALL
    SELECT 'pompe',          'Pompe de apa',         'apa-canal', 3 UNION ALL
    SELECT 'living',         'Living',               'mobilier',  1 UNION ALL
    SELECT 'dormitor',       'Dormitor',             'mobilier',  2 UNION ALL
    SELECT 'bucatarie',      'Bucatarie',            'mobilier',  3
) AS v
JOIN (SELECT id, slug FROM categories) AS p ON p.slug = v.parent;

-- ── 5. Plasare editoriala + iconita pe produse ────────────────────────
CALL mig002_add_column('products', 'page_slug',   'page_slug   VARCHAR(80)  NOT NULL DEFAULT '''' AFTER category_id');
CALL mig002_add_column('products', 'section_key', 'section_key VARCHAR(60)  NULL DEFAULT NULL   AFTER page_slug');
CALL mig002_add_column('products', 'icon_key',    'icon_key    VARCHAR(40)  NULL DEFAULT NULL   AFTER image_alt');
CALL mig002_add_column('products', 'icon_label',  'icon_label  VARCHAR(120) NULL DEFAULT NULL   AFTER icon_key');
CALL mig002_add_index('products', 'idx_page_section', 'KEY idx_page_section (page_slug, section_key, sort_order)');
CALL mig002_add_index('products', 'uq_page_section_name', 'UNIQUE KEY uq_page_section_name (page_slug, section_key, name)');

-- ── 6. Cross-listing (produse in mai multe categorii) ─────────────────
CREATE TABLE IF NOT EXISTS product_categories (
    product_id  INT UNSIGNED     NOT NULL,
    category_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (product_id, category_id),
    KEY idx_cat (category_id),
    FOREIGN KEY (product_id)  REFERENCES products(id)   ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 7. Curatenie: elimina procedurile temporare ───────────────────────
DROP PROCEDURE IF EXISTS mig002_add_column;
DROP PROCEDURE IF EXISTS mig002_add_index;
DROP PROCEDURE IF EXISTS mig002_add_fk;
