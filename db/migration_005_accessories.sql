-- Migrare 005 — accesoriile ca produse (card_kind, card_modifier).
-- Idempotenta prin acelasi tipar ca migrarile 002/003/004 (guard pe information_schema).

DROP PROCEDURE IF EXISTS mig005_add_column;
DELIMITER //
CREATE PROCEDURE mig005_add_column(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col) THEN
        SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
        PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;
END//
DELIMITER ;

CALL mig005_add_column('products', 'card_kind',
    "card_kind ENUM('material','accessory') NOT NULL DEFAULT 'material' AFTER page_slug");
CALL mig005_add_column('products', 'card_modifier',
    'card_modifier VARCHAR(20) NULL DEFAULT NULL AFTER card_kind');

DROP PROCEDURE IF EXISTS mig005_add_column;
