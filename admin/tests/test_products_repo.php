<?php
require_once __DIR__ . '/../../includes/products_repo.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass,$fail; if($c){$pass++;echo"  ✓ $m\n";}else{$fail++;echo"  ✗ $m\n";} }

// Electrice are 23 de materiale, distribuite 4/4/9/6 pe patru sectiuni.
$page = productsForPage('electrice', 'material');
ok(count($page) === 23, "productsForPage(electrice, 'material') => 23 (got " . count($page) . ')');

// Electrice are si 17 accesorii, in alte patru sectiuni (Task 5D).
$pageAcc = productsForPage('electrice', 'accessory');
ok(count($pageAcc) === 17, "productsForPage(electrice, 'accessory') => 17 (got " . count($pageAcc) . ')');

// Fara filtru de tip, productsForPage intoarce ambele tipuri.
$pageAll = productsForPage('electrice');
ok(count($pageAll) === 40, 'productsForPage(electrice) fara filtru => 40 (got ' . count($pageAll) . ')');

$sec = productsForSection('electrice', 'cabluri-conductori');
ok(count($sec) === 4, 'sectiunea cabluri-conductori => 4 (got ' . count($sec) . ')');

// productsForSection('electrice', '<sectiune>', 'accessory') intoarce doar accesorii (Task 5D).
$secAcc = productsForSection('electrice', 'tablouri-sigurante', 'accessory');
ok(count($secAcc) === 4, "sectiunea tablouri-sigurante, tip 'accessory' => 4 (got " . count($secAcc) . ')');
ok(!array_filter($secAcc, fn($p) => $p['card_kind'] !== 'accessory'),
    'toate randurile intoarse au card_kind = accessory');

$secMaterialOnly = productsForSection('electrice', 'cabluri-conductori', 'material');
ok(count($secMaterialOnly) === 4, "sectiunea cabluri-conductori, tip 'material' => 4 (got " . count($secMaterialOnly) . ')');

$secWrongKind = productsForSection('electrice', 'cabluri-conductori', 'accessory');
ok($secWrongKind === [], 'sectiune de materiale ceruta cu tip accessory => []');

$all = allProducts();
ok(count($all) === 495, 'allProducts() => 495 (got ' . count($all) . ')');

$allMaterial = array_filter($all, fn($p) => $p['card_kind'] === 'material');
ok(count($allMaterial) === 231, 'allProducts() contine 231 materiale (got ' . count($allMaterial) . ')');

$allAccessory = array_filter($all, fn($p) => $p['card_kind'] === 'accessory');
ok(count($allAccessory) === 264, 'allProducts() contine 264 accesorii (got ' . count($allAccessory) . ')');

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

// Sectiune inexistenta pe o pagina reala => array gol, nu eroare.
ok(productsForSection('electrice', 'sectiune-inexistenta') === [], 'sectiune inexistenta => []');

// allProducts(0) nu trebuie sa arunce; verificam explicit ce intoarce.
$zero = allProducts(0);
ok(is_array($zero), 'allProducts(0) nu arunca, intoarce array (count=' . count($zero) . ')');

// Ordinea blocurilor de proprietati respecta sort_order — verificat direct contra DB,
// pe un produs cu cel putin 3 blocuri (randarea din Task 4 depinde de ordinea asta).
$cu3Plus = array_values(array_filter($all, fn($p) => count($p['properties']) >= 3));
if ($cu3Plus) {
    $produs = $cu3Plus[0];
    $stmt = Database::get()->prepare(
        'SELECT label FROM product_properties WHERE product_id = ? ORDER BY sort_order'
    );
    $stmt->execute([$produs['id']]);
    $labelsAsteptate = array_column($stmt->fetchAll(), 'label');
    $labelsReale = array_column($produs['properties'], 'label');
    ok($labelsReale === $labelsAsteptate,
        'ordinea blocurilor respecta sort_order (produs ' . $produs['id'] . ': '
        . implode(' -> ', $labelsReale) . ')');
} else {
    ok(false, 'ordinea blocurilor respecta sort_order (niciun produs cu >=3 blocuri gasit)');
}

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
