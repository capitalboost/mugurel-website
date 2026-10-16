<?php
/**
 * Navigarea site-ului, generata din catalog (meniul de sus, meniul de pe telefon, footer, prima pagina).
 * Categoriile noi dintr-un fisier importat apar singure in meniu.
 *
 * Meniul de sus = ramurile principale (Materiale constructii, Electrocasnice...), fiecare cu un panou derulant
 * cu grupele si subcategoriile ei. Categoriile „de trecere" (o singura subcategorie) pastreaza numele lor, dar
 * duc direct la subcategorie (ex. „Incalzire" => /burlane-si-accesorii-pentru-evacuare-fum.html).
 */
require_once __DIR__ . '/catalog_repo.php';

/** Un nod de meniu: numele categoriei, adresa paginii efective, copiii paginii efective (pana la $maxDepth). */
function siteNavNode(array $c, int $depth = 0, int $maxDepth = 2): array
{
    $target = catSkipSingleChild($c);
    $children = [];
    if ($depth < $maxDepth) {
        foreach (catChildren($target['id']) as $ch) { $children[] = siteNavNode($ch, $depth + 1, $maxDepth); }
    }
    return ['id' => $c['id'], 'name' => $c['name'], 'url' => '/' . $target['slug'] . '.html', 'count' => $c['count'], 'children' => $children,
            'target_name' => $target['name']]; // „Ciment si var" duce la „Ciment"
}

/** Ramurile principale, cele mai mari primele. @return list<array{id:int, name:string, url:string, count:int, children:list<array>}> */
function siteNavItems(): array
{
    static $cache = null;
    if ($cache !== null) { return $cache; }
    $roots = catRoots();
    usort($roots, fn ($a, $b) => $b['count'] <=> $a['count']);
    return $cache = array_map('siteNavNode', $roots);
}

/** Grupele (nivelul 2) tuturor ramurilor; o ramura fara grupe apare ea insasi. Pentru prima pagina. */
function siteNavGroups(): array
{
    $out = [];
    foreach (siteNavItems() as $root) {
        if ($root['children']) {
            foreach ($root['children'] as $g) { $out[] = $g + ['root' => $root['id']]; }
        } else {
            $out[] = $root + ['root' => $root['id']];
        }
    }
    return $out;
}

/** Id-urile categoriilor de pe calea paginii curente (pentru evidentierea in meniu). */
function siteNavActiveIds(?string $categorySlug): array
{
    if ($categorySlug === null || $categorySlug === '') { return []; }
    $cat = catCategoryBySlug($categorySlug);
    return $cat ? array_column(catAncestors($cat['id']), 'id') : [];
}
