-- Migrare 006 — mesajul WhatsApp scris de mana, pastrat separat de sablon.
-- Idempotenta prin acelasi tipar ca migrarile 002/003/004/005 (guard pe information_schema).

DROP PROCEDURE IF EXISTS mig006_add_column;
DELIMITER //
CREATE PROCEDURE mig006_add_column(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col) THEN
        SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
        PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;
END//
DELIMITER ;

CALL mig006_add_column('products', 'wa_text',
    'wa_text VARCHAR(400) NULL DEFAULT NULL AFTER short_description');

DROP PROCEDURE IF EXISTS mig006_add_column;
