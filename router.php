<?php
/**
 * /<slug>.html fara fisier propriu: categorie din catalogul nou, colectie sau pagina statica din `pages`.
 * (Paginile produselor au adresa lor, /produs/<slug>.html — vezi produs.php.)
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/includes/catalog_page.php';

$slug = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($_GET['slug'] ?? '')));
if ($slug === '') { header('Location: /'); exit; }

// ── categorie ──
$category = catCategoryBySlug($slug);
if ($category !== null && $category['count'] > 0) {
    $target = catSkipSingleChild($category);
    if ($target['id'] !== $category['id']) {
        // „Ciment si var" are doar „Ciment": duce direct acolo (302 — se poate schimba cand apar subcategorii noi)
        header('Location: /' . $target['slug'] . '.html', true, 302);
        exit;
    }
    $subcats = array_map('catSkipSingleChild', catChildren($category['id']));
    $breadcrumbs = catBreadcrumbs($category);
    $list = catalogListState([
        'title' => $category['name'],
        'intro' => count($breadcrumbs) > 1 ? null : 'Materiale și produse la Mugurel, Dăbuleni. Prețuri cu TVA, livrare în Dolj.',
        'description' => $category['description'],
        'baseUrl' => '/' . $category['slug'] . '.html',
        'breadcrumbs' => $breadcrumbs,
        'categoryIds' => catDescendantIds($category['id']),
        'activeIds' => array_column(catAncestors($category['id']), 'id'),
        'subcats' => $subcats,
    ], $_GET);
    extract(catalogListMeta($list, $category['name'],
        $category['name'] . ' la Mugurel Dăbuleni: ' . $list['total'] . ' produse cu preț și stoc, comandă online sau pe WhatsApp 0749 130 565. Livrare în Dolj.'));
    $categorySlug = $category['slug'];
    $pageScripts = ['catalog-filtre.js'];
    require __DIR__ . '/templates/catalog_list.php';
    exit;
}

// ── colectie ──
$collection = catCollectionBySlug($slug);
if ($collection !== null) {
    $list = catalogListState([
        'title' => $collection['name'],
        'intro' => $collection['description'],
        'baseUrl' => '/' . $collection['slug'] . '.html',
        'breadcrumbs' => [['Catalog', '/catalog.html'], [$collection['name'], '/' . $collection['slug'] . '.html']],
        'tagSlugs' => $collection['tag_slugs'],
    ], $_GET);
    extract(catalogListMeta($list, $collection['name'] . ' — materiale',
        $collection['name'] . ' la Mugurel Dăbuleni: ' . $list['total'] . ' produse. ' . (string) $collection['description']));
    $pageScripts = ['catalog-filtre.js'];
    require __DIR__ . '/templates/catalog_list.php';
    exit;
}

// ── adresele vechi ale catalogului (pana la 16 oct 2026) ──
// Unde exista o categorie noua cu acelasi slug (ex. /acoperis.html, /incalzire.html), ea a raspuns deja mai sus.
// Restul duc permanent (301) la cea mai apropiata pagina noua, ca Google sa mute pozitiile, nu sa le piarda.
const LEGACY_REDIRECTS = [
    'izolatie'         => '/termoizolatii.html',
    'gips-carton'      => '/gleturi-si-chituri.html',
    'zidarie-bca'      => '/mortare-si-tencuieli.html',
    'gard-imprejmuiri' => '/fier-forjat.html',
    'electrice'        => '/instalatii-electrice.html',
    'apa-canal'        => '/catalog.html',
    'sanitare'         => '/catalog.html',
    'gradina'          => '/catalog.html',
    'mobilier'         => '/catalog.html',
    'electrocasnice'   => '/catalog.html',
    'scule-unelte'     => '/catalog.html',
    'acoperis'         => '/catalog.html',
    'incalzire'        => '/catalog.html',
    'constructii'      => '/materiale-constructii.html',
];
if (isset(LEGACY_REDIRECTS[$slug])) {
    $to = LEGACY_REDIRECTS[$slug];
    // tinta poate fi la randul ei o categorie/colectie disparuta — atunci catalogul
    $toSlug = basename($to, '.html');
    if ($to !== '/catalog.html' && catCategoryBySlug($toSlug) === null && catCollectionBySlug($toSlug) === null) { $to = '/catalog.html'; }
    // tinta e o categorie „de trecere" (o singura subcategorie)? un singur salt, direct la pagina finala
    if (($toCat = catCategoryBySlug($toSlug)) !== null) { $to = '/' . catSkipSingleChild($toCat)['slug'] . '.html'; }
    header('Location: ' . $to, true, 301);
    exit;
}

// ── pagina statica ──
try {
    $stmt = Database::get()->prepare('SELECT * FROM pages WHERE slug = ? AND is_published = 1');
    $stmt->execute([$slug]);
    $pageData = $stmt->fetch();
} catch (Throwable $e) {
    // baza indisponibila: raspuns scurt, fara detalii (mesajul de conectare poate contine utilizatorul bazei)
    error_log('router.php: ' . $e->getMessage());
    http_response_code(503);
    header('Retry-After: 300');
    echo '<h1>Site-ul este temporar indisponibil</h1><p>Revino în câteva minute sau sună la 0749 130 565.</p>';
    exit;
}
if (!$pageData) {
    require __DIR__ . '/404.php';
    exit;
}
$pageTitle       = $pageData['meta_title'] ?: $pageData['title'] . ' — Mugurel';
$metaDescription = $pageData['meta_description'] ?? '';
$canonicalUrl    = SITE_URL . '/' . $slug . '.html';
require __DIR__ . '/templates/page.php';
