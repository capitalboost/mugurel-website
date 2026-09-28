-- Migrare 003 — blocurile de proprietati ale cardurilor de produs.
-- Idempotenta: CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS product_properties (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED     NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    label      VARCHAR(60)      NOT NULL,
    body       TEXT             NOT NULL,
    is_list    TINYINT(1)       NOT NULL DEFAULT 0,
    UNIQUE KEY uq_prod_sort (product_id, sort_order),
    KEY idx_product (product_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
