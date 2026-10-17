<?php
/**
 * /catalog.html — toate produsele din catalogul nou, cu filtre; cu ?q= devine pagina de cautare.
 * Adresele vechi /catalog.html?cat=<slug> duc la pagina categoriei, daca exista.
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/includes/catalog_page.php';

$cat = is_string($_GET['cat'] ?? null) ? preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['cat'])) : '';
if ($cat !== '' && $cat !== 'all') {
    $c = catCategoryBySlug($cat);
    header('Location: ' . ($c ? '/' . $c['slug'] . '.html' : '/catalog.html'), true, 301);
    exit;
}

$q = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 80) : '';
$listFor = fn (string $q) => catalogListState([
    'title' => $q !== '' ? 'Rezultate pentru „' . $q . '"' : 'Toate produsele',
    'intro' => $q !== '' ? null : 'Catalogul Mugurel, Dabuleni: materiale de constructii cu pret si stoc. Comanda online sau pe WhatsApp.',
    'baseUrl' => '/catalog.html',
    'breadcrumbs' => [[$q !== '' ? 'Cautare' : 'Catalog', $q !== '' ? null : '/catalog.html']],
    'q' => $q,
], $_GET);
$list = $listFor($q);
// nimic gasit? poate e o greseala de scriere („aragazz") — cautam cel mai apropiat cuvant din catalog
if ($q !== '' && !$list['products'] && !$list['dbDown']) {
    try {
        $fixed = searchCorrect(Database::get(), $q);
        if ($fixed !== null) {
            $fixedList = $listFor($fixed);
            if ($fixedList['products']) { $list = $fixedList + ['typed' => $q]; $q = $fixed; }
        }
    } catch (Throwable $e) {
        error_log('searchCorrect: ' . $e->getMessage());
    }
}
extract(catalogListMeta($list, $q !== '' ? 'Cautare: ' . $q : 'Catalog produse',
    'Catalogul Mugurel Dabuleni: ' . $list['total'] . ' produse — adezivi, mortare, gleturi, ciment, metal si profile. Preturi cu TVA, livrare in Dolj.'));
$searchQuery = $q;
$navCatalog = $q === '';
$pageScripts = ['catalog-filtre.js'];
require __DIR__ . '/templates/catalog_list.php';
