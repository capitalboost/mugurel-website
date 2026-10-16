<?php
/**
 * /sitemap.xml — generat din catalog (inlocuieste fisierul static de dinainte de catalogul nou).
 * Contine: prima pagina, catalogul, paginile de informatii indexabile, categoriile (fara cele „de trecere"),
 * colectiile si toate produsele vizibile. Paginile filtrate/cautarile nu intra (sunt noindex).
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/includes/catalog_repo.php';

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=3600');

$urls = [];
$add = function (string $path, ?string $lastmod = null, string $freq = 'weekly', string $prio = '0.5') use (&$urls): void {
    $urls[] = [SITE_URL . $path, $lastmod, $freq, $prio];
};
$day = fn (?string $dt) => $dt ? substr($dt, 0, 10) : null;

$products = [];
$lastByCat = [];
try {
    $products = Database::get()->query('SELECT slug, category_id, updated_at FROM products
                                        WHERE is_visible = 1 AND barcode IS NOT NULL AND slug IS NOT NULL ORDER BY id')->fetchAll();
    foreach ($products as $p) {
        $c = (int) $p['category_id'];
        if (!isset($lastByCat[$c]) || $p['updated_at'] > $lastByCat[$c]) { $lastByCat[$c] = $p['updated_at']; }
    }
} catch (Throwable $e) {
    error_log('sitemap: ' . $e->getMessage());
}
$newest = $lastByCat ? max($lastByCat) : null;

$add('/', $day($newest), 'daily', '1.0');
$add('/catalog.html', $day($newest), 'daily', '0.8');
foreach (['contact', 'despre-noi'] as $page) { $add("/$page.html", null, 'monthly', '0.4'); }

foreach (catCategories() as $c) {
    if ($c['count'] === 0 || catIsPassThrough($c)) { continue; }
    $last = null;
    foreach (catDescendantIds($c['id']) as $id) {
        if (isset($lastByCat[$id]) && $lastByCat[$id] > $last) { $last = $lastByCat[$id]; }
    }
    $add('/' . $c['slug'] . '.html', $day($last), 'weekly', $c['parent_id'] === null ? '0.8' : '0.7');
}
foreach (catCollections() as $col) { $add('/' . $col['slug'] . '.html', $day($newest), 'weekly', '0.6'); }
foreach ($products as $p) { $add('/produs/' . rawurlencode($p['slug']) . '.html', $day($p['updated_at']), 'weekly', '0.6'); }

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$loc, $lastmod, $freq, $prio]) {
    echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . '</loc>'
       . ($lastmod ? "<lastmod>$lastmod</lastmod>" : '')
       . "<changefreq>$freq</changefreq><priority>$prio</priority></url>\n";
}
echo "</urlset>\n";
