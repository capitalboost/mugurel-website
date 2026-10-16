-- Migrare 014 — stoc pe cantitate (16 oct 2026), optional pe fiecare produs.
-- stock_qty NULL = stocul NU se numara: se afiseaza starea aleasa de mana (stock_status: in stoc / la comanda / indisponibil).
-- stock_qty = numar = cantitatea de pe stoc, in unitatea de vanzare (bucata, sac, m², kg — de aici zecimalele).
--   Fiecare comanda o scade; la 0 produsul trece singur pe „Indisponibil" (stock_status e tinut in pas cu ea).
-- Idempotenta prin acelasi tipar ca migrarile 002-008 (guard pe information_schema).

DROP PROCEDURE IF EXISTS mig014_add_column;
DELIMITER //
CREATE PROCEDURE mig014_add_column(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col) THEN
        SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
        PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;
END//
DELIMITER ;

CALL mig014_add_column('products', 'stock_qty', 'stock_qty DECIMAL(10,2) NULL DEFAULT NULL AFTER stock_status');

DROP PROCEDURE IF EXISTS mig014_add_column;
