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
        SELECT p.id, p.name, p.short_description, p.wa_text, p.icon_key, p.icon_label,
               p.image_path, p.image_alt, p.badge_label, p.badge_kind,
               p.card_kind, p.card_modifier, p.section_key,
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

/**
 * $modifier distinge, in interiorul aceleiasi chei de sectiune, doua grile HTML
 * separate care impart acelasi section_key (caz intalnit cand o singura sectiune
 * din pagina statica avea doua sub-grile cu stiluri diferite ale cardului).
 * null = fara filtru pe card_modifier; '' = doar randurile cu card_modifier NULL;
 * orice alt string = doar randurile cu acel card_modifier exact.
 */
function productsForSection(string $pageSlug, string $sectionKey, ?string $kind = null, ?int $limit = null, int $offset = 0, ?string $modifier = null): array {
    try {
        $sql = productsBaseSql() . ' AND p.page_slug = ? AND p.section_key = ?';
        $params = [$pageSlug, $sectionKey];
        if ($kind !== null) { $sql .= ' AND p.card_kind = ?'; $params[] = $kind; }
        if ($modifier === '') {
            $sql .= ' AND p.card_modifier IS NULL';
        } elseif ($modifier !== null) {
            $sql .= ' AND p.card_modifier = ?'; $params[] = $modifier;
        }
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

/**
 * Toate produsele unei pagini, grupate: [section_key][card_kind] => randuri.
 * O singura interogare pentru produse (plus attachProperties(), tot o singura
 * interogare pentru proprietati) — inlocuieste cele doua interogari pe care le
 * facea inainte fiecare apel section() separat (o pagina cu 10-15 sectiuni
 * ajungea la 20-30 de interogari; acum ajunge la 2-3, indiferent de nr. sectiuni).
 *
 * Rezultatul e memorat intr-o variabila static, per cerere HTTP: prima sectiune
 * a paginii care il cere plateste interogarea completa; sectiunile urmatoare ale
 * aceleiasi pagini (acelasi $pageSlug) citesc din memorie, fara SQL suplimentar.
 */
function pageProductsIndexed(string $pageSlug): array {
    static $cache = [];
    if (array_key_exists($pageSlug, $cache)) { return $cache[$pageSlug]; }
    try {
        $sql = productsBaseSql() . ' AND p.page_slug = ? ORDER BY p.section_key, p.sort_order, p.id';
        $stmt = Database::get()->prepare($sql);
        $stmt->execute([$pageSlug]);
        $rows = attachProperties($stmt->fetchAll());
        $indexed = [];
        foreach ($rows as $r) {
            $indexed[$r['section_key']][$r['card_kind']][] = $r;
        }
        return $cache[$pageSlug] = $indexed;
    } catch (Throwable $e) {
        error_log('pageProductsIndexed: ' . $e->getMessage());
        return $cache[$pageSlug] = [];
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

/**
 * Conditia comuna pentru catalog: filtrare pe categorie (nivel 1 sau 2) si cautare text.
 * Returneaza [sqlFragment, params].
 */
function catalogWhere(?string $cat, ?string $q): array {
    $sql = ''; $params = [];
    if ($cat !== null && $cat !== '' && $cat !== 'all') {
        // Produsul se potriveste daca e in categoria ceruta, intr-o subcategorie a ei,
        // sau e cross-listat acolo.
        $sql .= ' AND (c.slug = ? OR l1.slug = ? OR EXISTS (
                    SELECT 1 FROM product_categories pc2
                    JOIN categories xc ON xc.id = pc2.category_id
                    LEFT JOIN categories xl1 ON xl1.id = xc.parent_id
                    WHERE pc2.product_id = p.id AND (xc.slug = ? OR xl1.slug = ?)))';
        $params = array_merge($params, [$cat, $cat, $cat, $cat]);
    }
    if ($q !== null && trim($q) !== '') {
        $like = '%' . trim($q) . '%';
        $sql .= ' AND (p.name LIKE ? OR p.short_description LIKE ?)';
        $params[] = $like; $params[] = $like;
    }
    return [$sql, $params];
}

function catalogProducts(?string $cat, ?string $q, int $limit, int $offset): array {
    try {
        [$where, $params] = catalogWhere($cat, $q);
        $sql = productsBaseSql() . $where . ' ORDER BY p.page_slug, p.sort_order, p.id'
             . ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
        $stmt = Database::get()->prepare($sql);
        $stmt->execute($params);
        return attachProperties($stmt->fetchAll());
    } catch (Throwable $e) {
        error_log('catalogProducts: ' . $e->getMessage());
        return [];
    }
}

/**
 * Arborele de categorii (nivel 1 cu copiii lor de nivel 2), pentru sidebar si chipsuri.
 * Fiecare nod: ['id', 'parent_id', 'slug', 'name', 'children' => [...]].
 * NULL sorteaza inaintea oricarei valori in MySQL, deci categoriile de nivel 1
 * (parent_id IS NULL) ies mereu primele in acest ORDER BY.
 */
function categoryTree(): array {
    try {
        $rows = Database::get()->query(
            'SELECT id, parent_id, slug, name FROM categories WHERE is_active = 1 ORDER BY parent_id, sort_order, name'
        )->fetchAll();
        $byId = [];
        foreach ($rows as $r) { $byId[$r['id']] = $r + ['children' => []]; }
        $tree = [];
        foreach ($byId as $id => $r) {
            if ($r['parent_id'] === null) { $tree[$id] = $r; }
        }
        foreach ($byId as $id => $r) {
            if ($r['parent_id'] !== null && isset($tree[$r['parent_id']])) {
                $tree[$r['parent_id']]['children'][] = $r;
            }
        }
        return array_values($tree);
    } catch (Throwable $e) {
        error_log('categoryTree: ' . $e->getMessage());
        return [];
    }
}

function catalogCount(?string $cat, ?string $q): int {
    try {
        [$where, $params] = catalogWhere($cat, $q);
        $sql = 'SELECT COUNT(*) FROM products p
                JOIN categories c ON c.id = p.category_id
                LEFT JOIN categories l1 ON l1.id = c.parent_id
                WHERE p.is_visible = 1' . $where;
        $stmt = Database::get()->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('catalogCount: ' . $e->getMessage());
        return 0;
    }
}
