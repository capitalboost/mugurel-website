<?php
require_once __DIR__ . '/../../includes/products_repo.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass,$fail; if($c){$pass++;echo"  ✓ $m\n";}else{$fail++;echo"  ✗ $m\n";} }

// Electrice are 23 de produse, distribuite 4/4/9/6 pe patru sectiuni.
$page = productsForPage('electrice');
ok(count($page) === 23, 'productsForPage(electrice) => 23 (got ' . count($page) . ')');

$sec = productsForSection('electrice', 'cabluri-conductori');
ok(count($sec) === 4, 'sectiunea cabluri-conductori => 4 (got ' . count($sec) . ')');

$all = allProducts();
ok(count($all) === 231, 'allProducts() => 231 (got ' . count($all) . ')');

// Forma randului — Task 4 depinde de exact aceste chei.
if ($page) {
    $r = $page[0];
    foreach (['id','name','short_description','icon_key','icon_label','price','price_unit',
              'cat_slugs','subcat_slug','cat_label','properties'] as $k) {
        ok(array_key_exists($k, $r), "randul contine cheia '$k'");
    }
}

// Proprietatile sunt atasate, in ordine, si listele au mai multe linii.
$cuProp = array_filter($all, fn($p) => count($p['properties']) > 1);
ok(count($cuProp) === 187, '187 produse cu mai multe blocuri (got ' . count($cuProp) . ')');
$liste = array_filter($all, fn($p) => array_filter($p['properties'], fn($x) => (int)$x['is_list'] === 1));
ok(count($liste) === 187, '187 produse au un bloc de tip lista (got ' . count($liste) . ')');

// limit/offset — nefolosite azi, dar trebuie sa functioneze pentru cele 3000.
ok(count(allProducts(10)) === 10, 'allProducts(10) => 10');
ok(allProducts(1, 1)[0]['id'] !== allProducts(1, 0)[0]['id'], 'offset schimba randul returnat');

// Produsele cross-listate au mai multe slug-uri in cat_slugs.
$multi = array_filter($all, fn($p) => str_contains(trim($p['cat_slugs']), ' '));
ok(count($multi) >= 2, 'cel putin 2 produse cross-listate (got ' . count($multi) . ')');

// Pagina inexistenta => array gol, nu eroare.
ok(productsForPage('nu-exista') === [], 'pagina inexistenta => []');

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
