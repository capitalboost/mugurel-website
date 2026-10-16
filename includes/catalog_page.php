<?php
/**
 * Pregateste o lista a catalogului nou pentru templates/catalog_list.php: categorie, colectie sau cautare.
 * Leaga catalog_repo.php (date) de product_grid.php (carduri).
 */
require_once __DIR__ . '/catalog_repo.php';
require_once __DIR__ . '/product_grid.php';

/**
 * @param array $opts [
 *   'title' => string, 'intro' => ?string, 'description' => ?string, 'baseUrl' => string (ex. /adezivi.html),
 *   'breadcrumbs' => list<[label, ?url]>, 'categoryIds' => ?list<int>, 'tagSlugs' => ?list<string>,
 *   'q' => string, 'activeIds' => list<int> (categoriile evidentiate in arbore), 'subcats' => list<array>,
 * ]
 * @param array $query $_GET
 */
function catalogListState(array $opts, array $query): array
{
    $q = trim((string) ($opts['q'] ?? ''));
    $scope = catScope($opts['categoryIds'] ?? null, $opts['tagSlugs'] ?? null, $q !== '' ? $q : null);
    $selected = catSelectedFilters($query, $scope);
    $ids = catFilterIds($scope, $selected);
    $sort = is_string($query['sort'] ?? null) && isset(CATALOG_SORTS[$query['sort']]) ? $query['sort'] : 'recomandate';
    $total = count($ids);
    $pages = max(1, (int) ceil($total / CATALOG_PAGE_SIZE));
    $page = min($pages, max(1, (int) (is_string($query['page'] ?? null) ? $query['page'] : 1)));

    $selectedSlugs = [];
    foreach ($selected as $grp => $tagIds) {
        foreach ($tagIds as $t) { $selectedSlugs[$grp][] = $scope['tagInfo'][$t]['slug']; }
    }
    $base = $opts['baseUrl'];
    $url = fn (array $slugs, string $s, int $p) => catListUrl($base, $slugs, $s, $p, $q);

    $chips = [];
    foreach ($selected as $grp => $tagIds) {
        foreach ($tagIds as $t) {
            $without = $selectedSlugs;
            $without[$grp] = array_values(array_diff($without[$grp], [$scope['tagInfo'][$t]['slug']]));
            $chips[] = [$scope['tagInfo'][$t]['name'], $url($without, $sort, 1)];
        }
    }
    $hidden = [];
    if ($q !== '') { $hidden[] = ['q', $q]; }
    foreach ($selectedSlugs as $grp => $slugs) { $hidden[] = [$grp, implode(',', $slugs)]; }

    $products = catProductsPage($ids, $sort, $page);
    $crumbs = $opts['breadcrumbs'];
    $jsonLd = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => []];
    $pos = 1;
    $jsonLd['itemListElement'][] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => 'Acasa', 'item' => SITE_URL . '/'];
    foreach ($crumbs as [$label, $u]) {
        $jsonLd['itemListElement'][] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $label]
            + ($u !== null ? ['item' => SITE_URL . $u] : []);
    }

    return [
        'title' => $opts['title'], 'intro' => $opts['intro'] ?? null, 'description' => $opts['description'] ?? null,
        'breadcrumbs' => $crumbs, 'baseUrl' => $base, 'q' => $q, 'sort' => $sort,
        'tree' => catTreeForSidebar(), 'activeIds' => $opts['activeIds'] ?? [], 'subcats' => $opts['subcats'] ?? [],
        'facets' => catFacets($scope, $selected), 'activeCount' => array_sum(array_map('count', $selected)),
        'chips' => $chips, 'hiddenFilters' => $hidden, 'resetUrl' => $url([], $sort, 1),
        'products' => $products, 'total' => $total, 'page' => $page, 'pages' => $pages,
        'pageUrl' => fn (int $p) => $url($selectedSlugs, $sort, $p),
        'dbDown' => !$products && !catCategories(),
        'jsonLd' => $jsonLd,
        'filtered' => $selected !== [] || $sort !== 'recomandate' || $page > 1,
    ];
}

/** Arborele pentru sidebar: radacinile cu produse si copiii lor cu produse; categoriile „de trecere" sar un nivel. */
function catTreeForSidebar(): array
{
    $cats = catCategories();
    $build = function (array $c) use (&$build, $cats): array {
        $c = catSkipSingleChild($c);
        $kids = [];
        foreach ($c['children'] as $ch) { if ($cats[$ch]['count'] > 0) { $kids[] = $build($cats[$ch]); } }
        return ['id' => $c['id'], 'slug' => $c['slug'], 'name' => $c['name'], 'count' => $c['count'], 'children' => $kids];
    };
    return array_map($build, catRoots());
}

/** Breadcrumb-ul unei categorii, fara categoriile „de trecere". @return list<array{0:string,1:?string}> */
function catBreadcrumbs(array $cat): array
{
    $out = [];
    foreach (catAncestors($cat['id']) as $a) {
        if ($a['id'] !== $cat['id'] && catIsPassThrough($a)) { continue; }
        $out[] = [$a['name'], '/' . $a['slug'] . '.html'];
    }
    return $out;
}

/** Un nod din arbore: deschis daca e pe calea categoriei curente. */
function catTreeNode(array $n, array $activeIds, int $depth = 0): string
{
    $active = in_array($n['id'], $activeIds, true);
    $current = $active && $n['id'] === end($activeIds);
    $out = '<li class="cat-tree-item cat-tree-item--d' . $depth . ($active ? ' is-open' : '') . '">'
         . '<a href="/' . e($n['slug']) . '.html"' . ($current ? ' aria-current="page" class="is-current"' : '') . '>'
         . e($n['name']) . ' <span class="cat-tree-n">' . (int) $n['count'] . '</span></a>';
    if ($n['children'] && ($active || $depth === 0)) {
        $out .= '<ul>';
        foreach ($n['children'] as $ch) { $out .= catTreeNode($ch, $activeIds, $depth + 1); }
        $out .= '</ul>';
    }
    return $out . '</li>';
}

/** Variabilele de pagina comune (titlu, descriere, canonical, robots) pentru o lista. */
function catalogListMeta(array $list, string $title, string $description): array
{
    return [
        'pageTitle' => $title . ($list['page'] > 1 ? ' — pagina ' . $list['page'] : '') . ' | Mugurel Dabuleni',
        'metaDescription' => $description,
        'canonicalUrl' => SITE_URL . $list['baseUrl'],
        // paginile filtrate/sortate/paginate nu se indexeaza (continut duplicat), dar link-urile se urmaresc
        'metaRobots' => $list['filtered'] || $list['q'] !== '' ? 'noindex, follow' : 'index, follow',
    ];
}
