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
        'intro' => count($breadcrumbs) > 1 ? null : 'Materiale si produse la Mugurel, Dabuleni. Preturi cu TVA, livrare in Dolj.',
        'description' => $category['description'],
        'baseUrl' => '/' . $category['slug'] . '.html',
        'breadcrumbs' => $breadcrumbs,
        'categoryIds' => catDescendantIds($category['id']),
        'activeIds' => array_column(catAncestors($category['id']), 'id'),
        'subcats' => $subcats,
    ], $_GET);
    extract(catalogListMeta($list, $category['name'],
        $category['name'] . ' la Mugurel Dabuleni: ' . $list['total'] . ' produse cu pret si stoc, comanda online sau pe WhatsApp 0749 130 565. Livrare in Dolj.'));
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
        $collection['name'] . ' la Mugurel Dabuleni: ' . $list['total'] . ' produse. ' . (string) $collection['description']));
    $pageScripts = ['catalog-filtre.js'];
    require __DIR__ . '/templates/catalog_list.php';
    exit;
}

// ── pagina statica ──
$db = Database::get();
$stmt = $db->prepare('SELECT * FROM pages WHERE slug = ? AND is_published = 1');
$stmt->execute([$slug]);
$pageData = $stmt->fetch();
if (!$pageData) {
    http_response_code(404);
    if (file_exists(__DIR__ . '/404.html')) {
        include __DIR__ . '/404.html';
    } else {
        echo '<h1>404 — Pagina nu a fost gasita</h1>';
    }
    exit;
}
$pageTitle       = $pageData['meta_title'] ?: $pageData['title'] . ' — Mugurel';
$metaDescription = $pageData['meta_description'] ?? '';
$canonicalUrl    = SITE_URL . '/' . $slug . '.html';
require __DIR__ . '/templates/page.php';
