-- Migrare 008 — starea stocului afisata pe carduri.
-- in_stoc (implicit, decizia din 13 oct: toate produsele pornesc „In stoc"),
-- la_comanda (se poate comanda, termen confirmat telefonic), indisponibil (fara buton de cos).
-- Idempotenta prin acelasi tipar ca migrarile 002-007 (guard pe information_schema).

DROP PROCEDURE IF EXISTS mig008_add_column;
DELIMITER //
CREATE PROCEDURE mig008_add_column(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col) THEN
        SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
        PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;
END//
DELIMITER ;

CALL mig008_add_column('products', 'stock_status',
    'stock_status ENUM(''in_stoc'',''la_comanda'',''indisponibil'') NOT NULL DEFAULT ''in_stoc'' AFTER price_is_from');

DROP PROCEDURE IF EXISTS mig008_add_column;
