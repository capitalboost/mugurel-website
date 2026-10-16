<?php
/**
 * Navigarea site-ului, generata din catalog (meniul de sus, meniul de pe telefon, footer, prima pagina).
 * Categoriile noi dintr-un fisier importat apar singure in meniu.
 *
 * Meniul de sus: grupele ramurii principale (cea cu cele mai multe produse, azi „Materiale constructii"),
 * apoi celelalte ramuri (ex. „Electrocasnice"). Categoriile „de trecere" sar un nivel.
 */
require_once __DIR__ . '/catalog_repo.php';

/** @return list<array{id:int, name:string, url:string, count:int, children:list<array>}> */
function siteNavItems(): array
{
    static $cache = null;
    if ($cache !== null) { return $cache; }
    $roots = catRoots();
    usort($roots, fn ($a, $b) => $b['count'] <=> $a['count']);
    $item = function (array $c): array {
        $c = catSkipSingleChild($c);
        return ['id' => $c['id'], 'name' => $c['name'], 'url' => '/' . $c['slug'] . '.html', 'count' => $c['count'],
                'children' => array_map(fn ($ch) => ['id' => catSkipSingleChild($ch)['id'], 'name' => catSkipSingleChild($ch)['name'],
                    'url' => '/' . catSkipSingleChild($ch)['slug'] . '.html', 'count' => $ch['count']], catChildren($c['id']))];
    };
    $items = [];
    if ($roots) {
        foreach (catChildren($roots[0]['id']) as $ch) { $items[] = $item($ch); }
        foreach (array_slice($roots, 1) as $r) { $items[] = $item($r); }
    }
    return $cache = $items;
}

/** Id-urile categoriilor de pe calea paginii curente (pentru evidentierea in meniu). */
function siteNavActiveIds(?string $categorySlug): array
{
    if ($categorySlug === null || $categorySlug === '') { return []; }
    $cat = catCategoryBySlug($categorySlug);
    return $cat ? array_column(catAncestors($cat['id']), 'id') : [];
}
