-- Migrare 004 — badge-urile cardurilor de produs.
-- image_path si image_alt exista deja din schema initiala; aici doar badge-ul.
-- Idempotenta prin acelasi tipar ca migrarea 002 (guard pe information_schema).

DROP PROCEDURE IF EXISTS mig004_add_column;
DELIMITER //
CREATE PROCEDURE mig004_add_column(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col) THEN
        SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
        PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;
END//
DELIMITER ;

CALL mig004_add_column('products', 'badge_label', 'badge_label VARCHAR(60) NULL DEFAULT NULL AFTER icon_label');
CALL mig004_add_column('products', 'badge_kind',  'badge_kind VARCHAR(30) NULL DEFAULT NULL AFTER badge_label');

DROP PROCEDURE IF EXISTS mig004_add_column;
