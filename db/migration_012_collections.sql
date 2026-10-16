-- Migrare 012 — colectii (pagini „Fier forjat", „Termoizolatii"...) construite din tag-urile de aplicatie.
-- Decizia din 16 oct: 5 colectii grupate, fiecare cu cel putin 3 produse. Produsele intra AUTOMAT, dupa tag:
-- legatura e pe slug-ul tag-ului (nu pe id), ca un produs nou importat cu acelasi tag sa apara singur in colectie.
-- O colectie cu mai putin de COLLECTION_MIN_PRODUCTS (includes/catalog_repo.php) produse nu se afiseaza.

CREATE TABLE IF NOT EXISTS collections (
    id          SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug        VARCHAR(80)  NOT NULL,
    name        VARCHAR(120) NOT NULL,
    description TEXT NULL DEFAULT NULL,
    sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_collections_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS collection_tags (
    collection_id SMALLINT UNSIGNED NOT NULL,
    tag_slug      VARCHAR(120) NOT NULL,
    PRIMARY KEY (collection_id, tag_slug),
    FOREIGN KEY (collection_id) REFERENCES collections(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO collections (slug, name, description, sort_order) VALUES
    ('fier-forjat', 'Fier forjat', 'Platbande amprentate și arcuite, elemente decorative și profile pentru porți, garduri și balustrade din fier forjat.', 1),
    ('termoizolatii', 'Termoizolații', 'Adezivi și mase de șpaclu pentru lipirea și armarea plăcilor de polistiren (expandat, extrudat, grafitat) și vată minerală.', 2),
    ('fatada', 'Fațadă', 'Materiale pentru fațade: adezivi pentru termoizolații, tinciuri decorative și tencuieli de exterior.', 3),
    ('masa-de-spaclu', 'Masă de șpaclu', 'Adezivi care se folosesc și ca masă de șpaclu, pentru stratul de armare cu plasă din fibră de sticlă.', 4),
    ('baie-si-bucatarie', 'Baie și bucătărie', 'Adezivi pentru gresie și faianță potriviți în băi, bucătării și alte spații umede.', 5);

INSERT IGNORE INTO collection_tags (collection_id, tag_slug)
SELECT c.id, t.tag_slug FROM collections c
JOIN (
    SELECT 'fier-forjat' AS coll, 'fier-forjat' AS tag_slug
    UNION ALL SELECT 'termoizolatii', 'polistiren'
    UNION ALL SELECT 'termoizolatii', 'polistiren-extrudat'
    UNION ALL SELECT 'termoizolatii', 'polistiren-grafitat'
    UNION ALL SELECT 'termoizolatii', 'vata-minerala'
    UNION ALL SELECT 'fatada', 'fatada'
    UNION ALL SELECT 'masa-de-spaclu', 'masa-de-spaclu'
    UNION ALL SELECT 'baie-si-bucatarie', 'baie'
    UNION ALL SELECT 'baie-si-bucatarie', 'bucatarie'
) t ON t.coll = c.slug;
