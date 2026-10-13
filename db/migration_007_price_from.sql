-- Migrare 007 — pret minim („de la X lei").
-- price_is_from = 1 inseamna ca `price` e pretul de pornire, nu pretul exact
-- (ex. tabla la metru, culori la comanda). Produsele „de la" nu intra in cos.
-- Idempotenta prin acelasi tipar ca migrarile 002-006 (guard pe information_schema).

DROP PROCEDURE IF EXISTS mig007_add_column;
DELIMITER //
CREATE PROCEDURE mig007_add_column(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col) THEN
        SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
        PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;
END//
DELIMITER ;

CALL mig007_add_column('products', 'price_is_from',
    'price_is_from TINYINT(1) NOT NULL DEFAULT 0 AFTER price_unit');

DROP PROCEDURE IF EXISTS mig007_add_column;
