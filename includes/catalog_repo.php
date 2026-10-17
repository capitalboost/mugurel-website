<?php
/**
 * Catalogul nou (fisierele Dunlite): categorii pe 3 niveluri, filtre din tag-uri, colectii, pagina produsului.
 * Doar SQL si calcule — randarea sta in templates/ si includes/product_grid.php.
 *
 * Ca in products_repo.php: orice eroare de baza => rezultat gol (pagina se randeaza cu mesajul catre WhatsApp),
 * niciodata 500.
 *
 * Filtrele: in interiorul unei grupe (ex. Grosime 2 mm / 3 mm) = SAU; intre grupe = SI. Se calculeaza in PHP,
 * pe produsele categoriei (cateva sute, cel mult cateva mii de randuri produs–tag) — o interogare pentru tot.
 */
require_once __DIR__ . '/../admin/models/Database.php';
require_once __DIR__ . '/catalog_text.php';
require_once __DIR__ . '/catalog_search.php';

const CATALOG_PAGE_SIZE = 30;
const COLLECTION_MIN_PRODUCTS = 3;
/** Grupele care nu devin filtre pe pagina de categorie (aplicatiile au paginile lor — colectiile). */
const FACET_SKIP_GROUPS = ['aplicatie', 'ignorat'];
const CATALOG_SORTS = [
    'recomandate' => 'Recomandate',
    'pret-asc'    => 'Pret crescator',
    'pret-desc'   => 'Pret descrescator',
    'nume'        => 'Nume (A–Z)',
];

/**
 * Toate categoriile active, indexate dupa id, cu numarul de produse vizibile (inclusiv din subcategorii).
 * Memorate pe durata cererii.
 * @return array<int, array{id:int, parent_id:?int, slug:string, name:string, description:?string, count:int, children:list<int>}>
 */
function catCategories(): array
{
    static $cache = null;
    if ($cache !== null) { return $cache; }
    try {
        $pdo = Database::get();
        $rows = $pdo->query('SELECT id, parent_id, slug, name, description FROM categories WHERE is_active = 1
                             ORDER BY sort_order, name')->fetchAll();
        $direct = [];
        foreach ($pdo->query('SELECT category_id, COUNT(*) n FROM products WHERE is_visible = 1 GROUP BY category_id') as $r) {
            $direct[(int) $r['category_id']] = (int) $r['n'];
        }
        $cats = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $cats[$id] = ['id' => $id, 'parent_id' => $r['parent_id'] === null ? null : (int) $r['parent_id'],
                'slug' => $r['slug'], 'name' => $r['name'], 'description' => $r['description'],
                'count' => $direct[$id] ?? 0, 'children' => []];
        }
        foreach ($cats as $id => $c) {
            if ($c['parent_id'] !== null && isset($cats[$c['parent_id']])) { $cats[$c['parent_id']]['children'][] = $id; }
        }
        // totalurile urca de la frunze spre radacina (cel mult 3 niveluri, dar functioneaza pentru oricate)
        $total = function (int $id) use (&$total, &$cats): int {
            $n = $cats[$id]['count'];
            foreach ($cats[$id]['children'] as $ch) { $n += $total($ch); }
            return $n;
        };
        $out = $cats;
        foreach ($cats as $id => $_) { $out[$id]['count'] = $total($id); }
        return $cache = $out;
    } catch (Throwable $e) {
        error_log('catCategories: ' . $e->getMessage());
        return $cache = [];
    }
}

/** Pozitia fiecarei categorii in arbore (parcurgere in adancime, in ordinea din meniu). @return array<int,int> */
function catTreePositions(): array
{
    static $cache = null;
    if ($cache !== null) { return $cache; }
    $cats = catCategories();
    $pos = [];
    $walk = function (int $id) use (&$walk, &$pos, $cats): void {
        $pos[$id] = count($pos);
        foreach ($cats[$id]['children'] as $ch) { $walk($ch); }
    };
    foreach ($cats as $id => $c) { if ($c['parent_id'] === null) { $walk($id); } }
    return $cache = $pos;
}

function catCategoryBySlug(string $slug): ?array
{
    foreach (catCategories() as $c) {
        if ($c['slug'] === $slug) { return $c; }
    }
    return null;
}

/** Calea de la radacina pana la categorie (pentru breadcrumb). */
function catAncestors(int $id): array
{
    $cats = catCategories();
    $chain = [];
    for ($guard = 0; isset($cats[$id]) && $guard < 10; $guard++) {
        array_unshift($chain, $cats[$id]);
        $id = $cats[$id]['parent_id'] ?? -1;
    }
    return $chain;
}

/** Categoria + toate subcategoriile ei (id-uri). */
function catDescendantIds(int $id): array
{
    $cats = catCategories();
    $ids = [$id];
    foreach ($cats[$id]['children'] ?? [] as $ch) { $ids = array_merge($ids, catDescendantIds($ch)); }
    return $ids;
}

/** Subcategoriile directe care au produse. */
function catChildren(int $id): array
{
    $cats = catCategories();
    return array_values(array_filter(array_map(fn ($ch) => $cats[$ch], $cats[$id]['children'] ?? []), fn ($c) => $c['count'] > 0));
}

/** Ramurile principale (nivelul 1) care au produse — pentru meniu. */
function catRoots(): array
{
    return array_values(array_filter(catCategories(), fn ($c) => $c['parent_id'] === null && $c['count'] > 0));
}

/**
 * Categorie „de trecere": fara produse proprii si cu o singura subcategorie cu produse (ex. „Ciment si var" >
 * „Ciment"). Pagina ei ar fi un pas in plus, deci nu apare in meniu/breadcrumb, iar adresa ei duce la subcategorie.
 */
function catIsPassThrough(array $cat): bool
{
    $children = catChildren($cat['id']);
    return count($children) === 1 && $cat['count'] === $children[0]['count'];
}

/** Prima categorie care nu e „de trecere", coborand din $cat. */
function catSkipSingleChild(array $cat): array
{
    for ($guard = 0; $guard < 5 && catIsPassThrough($cat); $guard++) { $cat = catChildren($cat['id'])[0]; }
    return $cat;
}

/**
 * Domeniul unei liste: produsele vizibile din aceste categorii SAU cu aceste tag-uri, cu tag-urile lor.
 * @return array{ids: list<int>, tags: array<int, list<int>>, tagInfo: array<int, array{slug:string,name:string,grp:string}>}
 */
function catScope(?array $categoryIds, ?array $tagSlugs = null, ?string $search = null): array
{
    $empty = ['ids' => [], 'tags' => [], 'tagInfo' => []];
    try {
        $pdo = Database::get();
        $where = ['p.is_visible = 1', 'p.barcode IS NOT NULL'];
        $params = [];
        if ($categoryIds !== null) {
            if (!$categoryIds) { return $empty; }
            $where[] = 'p.category_id IN (' . implode(',', array_map('intval', $categoryIds)) . ')';
        }
        if ($tagSlugs !== null) {
            if (!$tagSlugs) { return $empty; }
            $where[] = 'EXISTS (SELECT 1 FROM product_tags xt JOIN tags x ON x.id = xt.tag_id
                         WHERE xt.product_id = p.id AND x.slug IN (' . implode(',', array_fill(0, count($tagSlugs), '?')) . '))';
            $params = array_merge($params, array_values($tagSlugs));
        }
        if ($search !== null && trim($search) !== '') {
            // fiecare cuvant trebuie sa se gaseasca; un cuvant = el, forma lui de baza sau un sinonim (catalog_search.php)
            foreach (preg_split('/\s+/u', trim($search)) ?: [] as $word) {
                if ($word === '') { continue; }
                $any = ['p.barcode = ?', 'p.sku = ?'];
                array_push($params, $word, $word);
                foreach (searchAlternatives($word) as $alt) {
                    $any[] = 'p.name LIKE ? OR p.original_name LIKE ? OR p.manufacturer LIKE ?';
                    $like = '%' . addcslashes($alt, '%_\\') . '%';
                    array_push($params, $like, $like, $like);
                }
                $where[] = '(' . implode(' OR ', $any) . ')';
            }
        }
        $stmt = $pdo->prepare('SELECT p.id, p.category_id, p.sort_order FROM products p WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        // ordinea „Recomandate": dupa locul categoriei in arbore, apoi ordinea din fisier — produsele din fisiere
        // diferite (fiecare numerotat de la 1) nu se amesteca intre ele
        $pos = catTreePositions();
        $rows = $stmt->fetchAll();
        usort($rows, fn ($a, $b) => [$pos[(int) $a['category_id']] ?? PHP_INT_MAX, (int) $a['sort_order'], (int) $a['id']]
                                <=> [$pos[(int) $b['category_id']] ?? PHP_INT_MAX, (int) $b['sort_order'], (int) $b['id']]);
        $ids = array_map(fn ($r) => (int) $r['id'], $rows);
        if (!$ids) { return $empty; }

        $tags = []; $tagInfo = [];
        $rows = $pdo->query('SELECT pt.product_id, t.id, t.slug, t.name, t.grp FROM product_tags pt JOIN tags t ON t.id = pt.tag_id
                             WHERE pt.product_id IN (' . implode(',', $ids) . ')')->fetchAll();
        foreach ($rows as $r) {
            $tags[(int) $r['product_id']][] = (int) $r['id'];
            $tagInfo[(int) $r['id']] = ['slug' => $r['slug'], 'name' => $r['name'], 'grp' => $r['grp']];
        }
        return ['ids' => $ids, 'tags' => $tags, 'tagInfo' => $tagInfo];
    } catch (Throwable $e) {
        error_log('catScope: ' . $e->getMessage());
        return $empty;
    }
}

/**
 * Filtrele cerute in adresa (?grosime=grosime-2-mm,grosime-3-mm&brand=ceresit), pastrate doar daca exista in
 * domeniul curent. @return array<string, list<int>> grupa => id-uri de tag
 */
function catSelectedFilters(array $query, array $scope): array
{
    $bySlug = [];
    foreach ($scope['tagInfo'] as $id => $t) { $bySlug[$t['grp']][$t['slug']] = $id; }
    $selected = [];
    foreach (TAG_GROUP_LABELS as $grp => $_) {
        if (in_array($grp, FACET_SKIP_GROUPS, true) || !isset($query[$grp])) { continue; }
        // din link-uri vine „a,b"; din formularul de filtre (fara JS) vine grp[]=a&grp[]=b
        $values = is_array($query[$grp]) ? $query[$grp] : explode(',', (string) $query[$grp]);
        foreach (array_slice($values, 0, 50) as $slug) {
            if (!is_string($slug)) { continue; }
            $slug = preg_replace('/[^a-z0-9-]/', '', strtolower($slug));
            if (isset($bySlug[$grp][$slug])) { $selected[$grp][] = $bySlug[$grp][$slug]; }
        }
        if (isset($selected[$grp])) { $selected[$grp] = array_values(array_unique($selected[$grp])); }
    }
    return $selected;
}

/** Produsele din domeniu care trec de filtre; $exceptGroup ignora filtrul unei grupe (pentru numaratorile ei). */
function catFilterIds(array $scope, array $selected, ?string $exceptGroup = null): array
{
    $out = [];
    foreach ($scope['ids'] as $id) {
        $own = array_flip($scope['tags'][$id] ?? []);
        foreach ($selected as $grp => $tagIds) {
            if ($grp === $exceptGroup) { continue; }
            $hit = false;
            foreach ($tagIds as $t) { if (isset($own[$t])) { $hit = true; break; } }
            if (!$hit) { continue 2; }
        }
        $out[] = $id;
    }
    return $out;
}

/**
 * Filtrele de afisat: o grupa apare doar daca are cel putin 2 valori in domeniu (altfel nu filtreaza nimic).
 * Fiecare valoare are numarul de produse pe care l-ai obtine bifand-o (cu celelalte grupe aplicate).
 * @return list<array{grp:string, label:string, options: list<array{id:int, slug:string, name:string, count:int, checked:bool}>}>
 */
function catFacets(array $scope, array $selected): array
{
    $byGroup = [];
    foreach ($scope['tagInfo'] as $id => $t) {
        if (!in_array($t['grp'], FACET_SKIP_GROUPS, true)) { $byGroup[$t['grp']][$id] = $t; }
    }
    $facets = [];
    foreach (TAG_GROUP_LABELS as $grp => $label) {
        if (empty($byGroup[$grp]) || count($byGroup[$grp]) < 2) { continue; }
        $base = catFilterIds($scope, $selected, $grp);
        $counts = [];
        foreach ($base as $pid) { foreach ($scope['tags'][$pid] ?? [] as $t) { $counts[$t] = ($counts[$t] ?? 0) + 1; } }
        $options = [];
        foreach ($byGroup[$grp] as $id => $t) {
            $checked = in_array($id, $selected[$grp] ?? [], true);
            if (($counts[$id] ?? 0) === 0 && !$checked) { continue; }
            $options[] = ['id' => $id, 'slug' => $t['slug'], 'name' => $t['name'], 'count' => $counts[$id] ?? 0, 'checked' => $checked];
        }
        if (count($options) < 2 && empty($selected[$grp])) { continue; }
        usort($options, fn ($a, $b) => catNaturalCompare($a['name'], $b['name']));
        $facets[] = ['grp' => $grp, 'label' => $label, 'options' => $options];
    }
    return $facets;
}

/** „grosime 2 mm" < „grosime 10 mm"; „Ø 26,9 mm" < „Ø 114,3 mm" — ordonare dupa numere, nu dupa text. */
function catNaturalCompare(string $a, string $b): int
{
    $num = fn (string $s) => preg_match('/(\d+(?:[.,]\d+)?)/u', $s, $m) ? (float) str_replace(',', '.', $m[1]) : null;
    $prefix = fn (string $s) => mb_strtolower((string) preg_replace('/\d.*$/u', '', $s));
    if ($prefix($a) === $prefix($b) && $num($a) !== null && $num($b) !== null && $num($a) != $num($b)) {
        return $num($a) <=> $num($b);
    }
    return strnatcasecmp($a, $b);
}

/**
 * O pagina de produse pentru card, in ordinea ceruta.
 * @return list<array> randuri cu campurile cardului + category_name
 */
function catProductsPage(array $ids, string $sort, int $page, int $perPage = CATALOG_PAGE_SIZE): array
{
    if (!$ids) { return []; }
    try {
        $order = match ($sort) {
            'pret-asc'  => 'p.price IS NULL, p.price ASC, p.sort_order',
            'pret-desc' => 'p.price IS NULL, p.price DESC, p.sort_order',
            'nume'      => 'p.name ASC',
            default     => 'FIELD(p.id, ' . implode(',', $ids) . ')',
        };
        $sql = 'SELECT p.id, p.slug, p.name, p.short_description, p.image_path, p.image_alt, p.price, p.price_unit,
                       p.price_is_from, p.stock_status, p.stock_qty, p.manufacturer, p.wa_text, c.name AS category_name, c.slug AS category_slug
                FROM products p JOIN categories c ON c.id = p.category_id
                WHERE p.id IN (' . implode(',', array_map('intval', $ids)) . ")
                ORDER BY $order
                LIMIT " . max(1, $perPage) . ' OFFSET ' . max(0, ($page - 1) * $perPage);
        return Database::get()->query($sql)->fetchAll();
    } catch (Throwable $e) {
        error_log('catProductsPage: ' . $e->getMessage());
        return [];
    }
}

/** Produsul complet pentru pagina lui: specificatii, documente, tag-uri, categorie. Null daca nu exista/nu e vizibil. */
function catProductBySlug(string $slug): ?array
{
    try {
        $pdo = Database::get();
        $stmt = $pdo->prepare('SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p
                               JOIN categories c ON c.id = p.category_id
                               WHERE p.slug = ? AND p.is_visible = 1 AND p.barcode IS NOT NULL');
        $stmt->execute([$slug]);
        $p = $stmt->fetch();
        if (!$p) { return null; }
        $id = (int) $p['id'];
        $p['specs'] = $pdo->query("SELECT spec_key, spec_value FROM product_specs WHERE product_id = $id ORDER BY sort_order")->fetchAll();
        $p['documents'] = $pdo->query("SELECT url, label FROM product_documents WHERE product_id = $id ORDER BY sort_order")->fetchAll();
        $p['tags'] = $pdo->query("SELECT t.slug, t.name, t.grp FROM product_tags pt JOIN tags t ON t.id = pt.tag_id
                                  WHERE pt.product_id = $id ORDER BY t.grp, t.name")->fetchAll();
        return $p;
    } catch (Throwable $e) {
        error_log('catProductBySlug: ' . $e->getMessage());
        return null;
    }
}

/** Produse din aceeasi categorie, pentru „Produse similare" (fara produsul curent). */
function catRelated(array $product, int $limit = 4): array
{
    $scope = catScope([(int) $product['category_id']]);
    $ids = array_values(array_filter($scope['ids'], fn ($id) => $id !== (int) $product['id']));
    return catProductsPage(array_slice($ids, 0, $limit), 'recomandate', 1, $limit);
}

/**
 * Colectiile active, cu tag-urile lor si numarul de produse. Doar cele cu cel putin COLLECTION_MIN_PRODUCTS.
 * @return list<array{id:int, slug:string, name:string, description:?string, tag_slugs:list<string>, count:int}>
 */
function catCollections(): array
{
    static $cache = null;
    if ($cache !== null) { return $cache; }
    try {
        $pdo = Database::get();
        $rows = $pdo->query('SELECT id, slug, name, description FROM collections WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
        $tags = [];
        foreach ($pdo->query('SELECT collection_id, tag_slug FROM collection_tags') as $r) { $tags[(int) $r['collection_id']][] = $r['tag_slug']; }
        $out = [];
        foreach ($rows as $r) {
            $slugs = $tags[(int) $r['id']] ?? [];
            $count = count(catScope(null, $slugs)['ids']);
            if ($count >= COLLECTION_MIN_PRODUCTS) {
                $out[] = ['id' => (int) $r['id'], 'slug' => $r['slug'], 'name' => $r['name'], 'description' => $r['description'],
                          'tag_slugs' => $slugs, 'count' => $count];
            }
        }
        return $cache = $out;
    } catch (Throwable $e) {
        error_log('catCollections: ' . $e->getMessage());
        return $cache = [];
    }
}

function catCollectionBySlug(string $slug): ?array
{
    foreach (catCollections() as $c) {
        if ($c['slug'] === $slug) { return $c; }
    }
    return null;
}

/** Adresa unei liste cu filtre/sortare/pagina; parametrii goi dispar. */
function catListUrl(string $base, array $selectedSlugs, string $sort, int $page, ?string $q = null): string
{
    $params = [];
    if ($q !== null && $q !== '') { $params['q'] = $q; }
    foreach (TAG_GROUP_LABELS as $grp => $_) {
        if (!empty($selectedSlugs[$grp])) { $params[$grp] = implode(',', $selectedSlugs[$grp]); }
    }
    if ($sort !== 'recomandate') { $params['sort'] = $sort; }
    if ($page > 1) { $params['page'] = $page; }
    // virgula dintre valori ramane lizibila in adresa (?grosime=grosime-2-mm,grosime-3-mm)
    return $base . ($params ? '?' . str_replace('%2C', ',', http_build_query($params)) : '');
}
