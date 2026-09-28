<?php
require_once __DIR__ . '/../../includes/products_repo.php';
require_once __DIR__ . '/../../includes/page_bootstrap.php';

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

// Task 8C: 39 de produse existente doar in catalog.html au fost importate cu
// page_slug='catalog', card_kind='material' — 495 + 39 = 534, materiale 231 + 39 = 270.
// (Al 40-lea card din catalog.html, "Set 4 scaune bucatarie tapitate", e acelasi produs
// cu "Set 4 Scaune Bucatarie Tapitate" de pe pagina Mobilier, scris cu alta capitalizare —
// potrivirea pe nume e insensibila la capitalizare, deci nu creeaza un rand separat.)
$all = allProducts();
ok(count($all) === 534, 'allProducts() => 534 (got ' . count($all) . ')');

$allMaterial = array_filter($all, fn($p) => $p['card_kind'] === 'material');
ok(count($allMaterial) === 270, 'allProducts() contine 270 materiale (got ' . count($allMaterial) . ')');

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

// ── catalogProducts / catalogCount (Task 8A) ──
ok(catalogCount(null, null) === 534, 'catalogCount fara filtru => 534 (got ' . catalogCount(null, null) . ')');
ok(count(catalogProducts(null, null, 60, 0)) === 60, 'prima pagina => 60 de produse');
ok(catalogProducts(null, null, 60, 60)[0]['id'] !== catalogProducts(null, null, 60, 0)[0]['id'], 'offset schimba rezultatul');

// Categorie de nivel 1 include subcategoriile ei.
$el = catalogCount('electrice', null);
ok($el >= 23, "categoria electrice => cel putin 23 (got $el)");
$cab = catalogCount('cabluri', null);
ok($cab > 0 && $cab <= $el, "subcategoria cabluri <= categoria parinte (got $cab din $el)");

// Cautarea gaseste dupa nume si dupa descriere.
ok(catalogCount(null, 'cablu') > 0, 'cautarea dupa "cablu" gaseste rezultate');
ok(catalogCount(null, 'zzzznuexista') === 0, 'cautare fara rezultate => 0');

// Filtru + cautare se combina.
ok(catalogCount('electrice', 'cablu') <= catalogCount(null, 'cablu'), 'filtru + cautare se restrang reciproc');

// Categorie inexistenta.
ok(catalogCount('nu-exista', null) === 0, 'categorie inexistenta => 0');

// ── pageProductsIndexed() (task design/perf — reduce interogarile per pagina) ──
$idx = pageProductsIndexed('electrice');
ok(is_array($idx), 'pageProductsIndexed intoarce array');
ok(count($idx['cabluri-conductori']['material'] ?? []) === 4,
    "pageProductsIndexed('electrice')['cabluri-conductori']['material'] => 4 (got "
    . count($idx['cabluri-conductori']['material'] ?? []) . ')');
ok(count($idx['tablouri-sigurante']['accessory'] ?? []) === 4,
    "pageProductsIndexed('electrice')['tablouri-sigurante']['accessory'] => 4 (got "
    . count($idx['tablouri-sigurante']['accessory'] ?? []) . ')');
ok(pageProductsIndexed('nu-exista') === [], 'pageProductsIndexed: pagina inexistenta => []');

// Al doilea apel pentru aceeasi pagina trebuie sa vina din cache-ul static, nu dintr-o
// noua interogare — verificam indirect ca rezultatul e identic (aceleasi obiecte/date).
$idx2 = pageProductsIndexed('electrice');
ok($idx === $idx2, 'pageProductsIndexed: al doilea apel intoarce acelasi rezultat (cache static)');

// Proprietatile sunt atasate si in indexul pe pagina, nu doar in productsForSection().
$primulCablu = $idx['cabluri-conductori']['material'][0] ?? null;
ok($primulCablu !== null && array_key_exists('properties', $primulCablu),
    'pageProductsIndexed: randurile au cheia properties atasata');

// ── section() citeste din pageProductsIndexed(), dar parametrii optionali
// (modifier/limit/offset) trebuie sa se comporte identic cu inainte ──
$totalAcoperisAcc = productsForSection('acoperis', 'accesorii-acoperis', 'accessory');
$modSm  = section('acoperis', 'accesorii-acoperis', 'accessory', null, 0, 'sm');
$modGol = section('acoperis', 'accesorii-acoperis', 'accessory', null, 0, '');
// numarul de carduri .accessory-card randate in fiecare varianta trebuie sa insumeze totalul
$nSm  = substr_count($modSm,  'class="accessory-card');
$nGol = substr_count($modGol, 'class="accessory-card');
ok($nSm + $nGol === count($totalAcoperisAcc),
    "section() cu modifier 'sm' + '' insumeaza totalul sectiunii ($nSm + $nGol vs " . count($totalAcoperisAcc) . ')');
ok($nSm > 0 && $nGol > 0, "section() cu modifier 'sm' si '' intorc ambele carduri (got $nSm si $nGol)");

// limit/offset aplicate in PHP peste indexul deja incarcat
$toateCabluri  = section('electrice', 'cabluri-conductori');
$primele2      = section('electrice', 'cabluri-conductori', 'material', 2, 0);
$urmatoarele2  = section('electrice', 'cabluri-conductori', 'material', 2, 2);
ok(substr_count($toateCabluri, 'class="material-card"') === 4, 'section(): fara limit => toate cele 4 randuri');
ok(substr_count($primele2, 'class="material-card"') === 2, 'section(): limit=2, offset=0 => 2 randuri');
ok(substr_count($urmatoarele2, 'class="material-card"') === 2, 'section(): limit=2, offset=2 => alte 2 randuri');
ok($primele2 !== $urmatoarele2, 'section(): limit/offset produc subseturi diferite');

// sectiune/pagina inexistenta => mesajul de indisponibilitate, nu eroare
ok(str_contains(section('nu-exista', 'nu-exista'), 'wa.me/40749130565'),
    'section(): pagina inexistenta => mesajul de indisponibilitate');

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
