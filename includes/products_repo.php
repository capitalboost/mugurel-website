<?php
/**
 * Acces la produse. Doar SQL — fara HTML, fara echo.
 * Randarea sta in includes/product_grid.php.
 *
 * Toate functiile returneaza [] daca baza de date nu raspunde. Pagina trebuie
 * sa se randeze cu tot continutul editorial chiar si cu MySQL cazut.
 */
require_once __DIR__ . '/../admin/models/Database.php';

/**
 * SELECT-ul comun. Deriva pentru fiecare produs:
 *  - cat_slugs    slug-urile de nivel 1 (proprie + cross-listate), separate prin spatiu
 *  - subcat_slug  slug-ul de nivel 2, daca produsul e intr-o subcategorie
 *  - cat_label    eticheta pentru badge (numele categoriei de nivel 1)
 */
function productsBaseSql(): string {
    return "
        SELECT p.id, p.name, p.short_description, p.icon_key, p.icon_label,
               p.image_path, p.image_alt, p.badge_label, p.badge_kind,
               p.card_kind, p.card_modifier,
               p.price, p.price_unit,
               TRIM(CONCAT(
                   COALESCE(l1.slug, c.slug),
                   COALESCE((SELECT CONCAT(' ', GROUP_CONCAT(COALESCE(xl1.slug, x.slug) SEPARATOR ' '))
                             FROM product_categories pc
                             JOIN categories x ON x.id = pc.category_id
                             LEFT JOIN categories xl1 ON xl1.id = x.parent_id
                             WHERE pc.product_id = p.id), '')
               )) AS cat_slugs,
               CASE WHEN c.parent_id IS NULL THEN NULL ELSE c.slug END AS subcat_slug,
               COALESCE(l1.name, c.name) AS cat_label
        FROM products p
        JOIN categories c  ON c.id = p.category_id
        LEFT JOIN categories l1 ON l1.id = c.parent_id
        WHERE p.is_visible = 1
    ";
}

/**
 * Adauga LIMIT/OFFSET. Valorile sunt fortate la int, deci nu sunt injectabile.
 * Daca $limit e null, $offset e ignorat tacit (MySQL nu accepta OFFSET fara LIMIT) —
 * nu functioneaza independent de $limit.
 */
function withLimit(string $sql, ?int $limit, int $offset): string {
    if ($limit === null) { return $sql; }
    return $sql . ' LIMIT ' . max(0, $limit) . ' OFFSET ' . max(0, $offset);
}

/**
 * Ataseaza fiecarui produs blocurile lui de proprietati, sub cheia 'properties'.
 * O singura interogare pentru tot setul, nu una pe produs.
 * Fiecare proprietate: ['label' => string, 'body' => string, 'is_list' => int].
 * Pentru is_list = 1, body contine cate un element pe linie.
 */
function attachProperties(array $rows): array {
    if (!$rows) { return $rows; }
    try {
        $ids  = array_column($rows, 'id');
        $in   = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::get()->prepare(
            "SELECT product_id, label, body, is_list FROM product_properties
             WHERE product_id IN ($in) ORDER BY product_id, sort_order"
        );
        $stmt->execute($ids);
        $byProduct = [];
        foreach ($stmt->fetchAll() as $pr) {
            $byProduct[$pr['product_id']][] = $pr;
        }
        foreach ($rows as &$r) { $r['properties'] = $byProduct[$r['id']] ?? []; }
        unset($r);
        return $rows;
    } catch (Throwable $e) {
        error_log('attachProperties: ' . $e->getMessage());
        foreach ($rows as &$r) { $r['properties'] = []; }
        unset($r);
        return $rows;
    }
}

function productsForSection(string $pageSlug, string $sectionKey, ?string $kind = null, ?int $limit = null, int $offset = 0): array {
    try {
        $sql = productsBaseSql() . ' AND p.page_slug = ? AND p.section_key = ?';
        $params = [$pageSlug, $sectionKey];
        if ($kind !== null) { $sql .= ' AND p.card_kind = ?'; $params[] = $kind; }
        $sql .= ' ORDER BY p.sort_order, p.id';
        $stmt = Database::get()->prepare(withLimit($sql, $limit, $offset));
        $stmt->execute($params);
        return attachProperties($stmt->fetchAll());
    } catch (Throwable $e) {
        error_log('productsForSection: ' . $e->getMessage());
        return [];
    }
}

function productsForPage(string $pageSlug, ?string $kind = null, ?int $limit = null, int $offset = 0): array {
    try {
        $sql = productsBaseSql() . ' AND p.page_slug = ?';
        $params = [$pageSlug];
        if ($kind !== null) { $sql .= ' AND p.card_kind = ?'; $params[] = $kind; }
        $sql .= ' ORDER BY p.section_key, p.sort_order, p.id';
        $stmt = Database::get()->prepare(withLimit($sql, $limit, $offset));
        $stmt->execute($params);
        return attachProperties($stmt->fetchAll());
    } catch (Throwable $e) {
        error_log('productsForPage: ' . $e->getMessage());
        return [];
    }
}

function allProducts(?int $limit = null, int $offset = 0): array {
    try {
        $sql = productsBaseSql() . ' ORDER BY p.page_slug, p.sort_order, p.id';
        return attachProperties(Database::get()->query(withLimit($sql, $limit, $offset))->fetchAll());
    } catch (Throwable $e) {
        error_log('allProducts: ' . $e->getMessage());
        return [];
    }
}
