<?php
// Teste pentru catalogul nou (pasii N3–N6): categorii, filtre, colectii, pagini. Necesita baza LOCALA cu importul
// din constructii_complet_MU.xlsx (147 produse) + tabla_complet_MU.xlsx (164) + electrocasnice_MU.xlsx (200) si serverul local pornit (bash dev/start_local.sh). Nu scrie nimic.
require_once __DIR__ . '/../../admin/config.php';
require_once __DIR__ . '/../../includes/catalog_page.php';
require_once __DIR__ . '/../../includes/site_nav.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass,$fail; if($c){$pass++;echo"  ✓ $m\n";}else{$fail++;echo"  ✗ $m\n";} }

// ── categorii ──
$root = catCategoryBySlug('materiale-constructii');
ok($root !== null && $root['count'] === 296,                         'radacina: 296 produse (147 + 149 acoperis, inclusiv subcategoriile)');
$metal = catCategoryBySlug('metal-si-profile');
ok($metal['count'] === 97 && count(catChildren($metal['id'])) === 7, 'Metal si profile: 97 produse, 7 subcategorii');
ok(count(catDescendantIds($root['id'])) === 33,                      'descendentii radacinii: 33 de categorii');
$ciment = catCategoryBySlug('ciment-si-var');
ok(catIsPassThrough($ciment) && catSkipSingleChild($ciment)['slug'] === 'ciment', '„Ciment si var" e de trecere => „Ciment"');
ok(!catIsPassThrough($metal),                                        'Metal si profile nu e de trecere');
$bc = array_column(catBreadcrumbs(catCategoryBySlug('ciment')), 0);
ok($bc === ['Materiale construcții', 'Ciment'],                      'breadcrumb fara categoria de trecere');
$nav = siteNavItems();
ok(array_column($nav, 'name') === ['Materiale construcții', 'Electrocasnice', 'Încălzire', 'Instalații electrice'],
                                                                     'meniul: ramurile principale, cele mai mari primele');
ok(array_column($nav[0]['children'], 'name') === ['Acoperiș', 'Adezivi', 'Mortare și tencuieli', 'Metal și profile', 'Gleturi și chituri', 'Ciment și var', 'Agregate', 'Șape și nivelare'],
                                                                     'panoul Materiale constructii: grupele, cu numele lor');
ok(count(siteNavGroups()) === 14,                                    'grupele tuturor ramurilor (prima pagina): 8 + 4 + 2');
$inc = array_values(array_filter($nav, fn ($i) => $i['name'] === 'Încălzire'))[0];
ok($inc['url'] === '/burlane-si-accesorii-pentru-evacuare-fum.html',  'meniul: ramura de trecere pastreaza numele, duce direct la subcategorie');

// ── filtre ──
$tevi = catCategoryBySlug('tevi-patrate-si-rectangulare');
$scope = catScope(catDescendantIds($tevi['id']));
ok(count($scope['ids']) === 46,                                      'tevi patrate/rectangulare: 46 in domeniu');
$sel = catSelectedFilters(['grosime' => 'grosime-2-mm'], $scope);
ok(count(catFilterIds($scope, $sel)) === 15,                         'filtru grosime 2 mm => 15');
$sel2 = catSelectedFilters(['grosime' => 'grosime-2-mm,grosime-3-mm'], $scope);
$n2 = count(catFilterIds($scope, $sel2));
ok($n2 > 15,                                                          'doua valori in aceeasi grupa = SAU (mai multe rezultate)');
$sel3 = catSelectedFilters(['grosime' => ['grosime-2-mm', 'grosime-3-mm'], 'dimensiune' => '40-x-20-mm'], $scope);
ok(count(catFilterIds($scope, $sel3)) < $n2,                          'grupe diferite = SI (mai putine rezultate); format grp[] acceptat');
ok(catSelectedFilters(['grosime' => 'nu-exista', 'brand' => 'x<script>'], $scope) === [], 'valori necunoscute ignorate');
$facets = catFacets($scope, $sel);
$groups = array_column($facets, 'grp');
ok(in_array('grosime', $groups, true) && in_array('dimensiune', $groups, true), 'filtrele afisate: grosime, dimensiune');
ok(!in_array('lungime', $groups, true),                              'grupa cu o singura valoare (6 m) nu apare');
$gros = array_values(array_filter($facets, fn ($f) => $f['grp'] === 'grosime'))[0];
ok(array_column($gros['options'], 'name')[0] === 'grosime 1,5 mm',   'valorile ordonate numeric (1,5 inainte de 2)');
$checked = array_values(array_filter($gros['options'], fn ($o) => $o['checked']));
ok(count($checked) === 1 && $checked[0]['count'] === 15,              'valoarea bifata isi pastreaza numaratoarea');

// ── cautare, sortare, colectii ──
ok(count(catScope(null, null, 'ceresit')['ids']) === 13,             'cautare „ceresit" => 13');
ok(count(catScope(null, null, '5946043001367')['ids']) === 1,        'cautare dupa codul de bare');
ok(count(catScope(null, null, '%')['ids']) === 0,                    'cautarea nu trateaza % ca wildcard');
$ids = catScope(catDescendantIds($tevi['id']))['ids'];
$asc = array_column(catProductsPage($ids, 'pret-asc', 1, 100), 'price');
$sorted = $asc; sort($sorted, SORT_NUMERIC);
ok(array_map('floatval', $asc) === array_map('floatval', $sorted),   'sortare dupa pret crescator');
ok(count(catProductsPage($ids, 'recomandate', 2)) === 16,            'pagina 2 (30 pe pagina): 16 produse');
$cols = array_column(catCollections(), 'count', 'slug');
ok($cols === ['fier-forjat' => 14, 'termoizolatii' => 12, 'fatada' => 11, 'masa-de-spaclu' => 11, 'baie-si-bucatarie' => 4], 'colectiile si numarul lor de produse');

// ── produs ──
$p = catProductBySlug('adeziv-gresie-si-faianta-ceresit-cm-11-plus-gri-interior-exterior-25-kg');
ok($p !== null && (float) $p['price'] === 56.83 && count($p['specs']) > 10 && count($p['documents']) === 1, 'produs: pret, specificatii, fisa tehnica');
ok(!in_array('Sursă date tehnice', array_column($p['specs'], 'spec_key'), true), 'produs: specificatia interna nu e publica');
ok(catProductBySlug("x' OR 1=1 -- ") === null,                       'produs: slug ciudat => null');
ok(isValidGtin('5946043001367') && !isValidGtin('1000000038376'),    'GTIN: cifra de control');

// ── pagini (server local) ──
$base = 'http://localhost:8080';
function http(string $url): array {
    $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 15]]);
    $body = @file_get_contents($url, false, $ctx);
    $code = isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m) ? (int) $m[1] : 0;
    $loc = '';
    foreach ($http_response_header ?? [] as $h) { if (stripos($h, 'Location:') === 0) { $loc = trim(substr($h, 9)); } }
    return [$code, (string) $body, $loc];
}
[$c, $b] = http("$base/tevi-patrate-si-rectangulare.html");
ok($c === 200 && substr_count($b, 'class="prod-card cat-card"') === 30, 'pagina categoriei: 200, 30 de carduri');
ok(str_contains($b, '<meta name="robots" content="index, follow">') && str_contains($b, '"@type":"BreadcrumbList"'), 'categoria: indexabila, cu BreadcrumbList');
[$c, $b] = http("$base/tevi-patrate-si-rectangulare.html?grosime=grosime-2-mm");
ok($c === 200 && str_contains($b, 'noindex, follow') && str_contains($b, 'href="https://mugurel-bricolaj.ro/tevi-patrate-si-rectangulare.html"'), 'filtrata: noindex, canonical spre categorie');
[$c, , $loc] = http("$base/ciment-si-var.html");
ok($c === 302 && str_ends_with($loc, '/ciment.html'),                'categoria de trecere: 302 spre subcategorie');
[$c, $b] = http("$base/produs/adeziv-gresie-si-faianta-ceresit-cm-11-plus-gri-interior-exterior-25-kg.html");
ok($c === 200 && str_contains($b, '"@type":"Product"') && str_contains($b, '"gtin":"5946043001367"') && str_contains($b, '"price":"56.83"'), 'pagina produsului: JSON-LD cu GTIN si pret');
ok(str_contains($b, 'data-cart-id="') && !str_contains($b, 'dedeman'),  'pagina produsului: buton de cos, fara sursele interne');
[$c] = http("$base/produs/nu-exista.html");
ok($c === 404,                                                        'produs inexistent: 404');
[$c, $b] = http("$base/produs/profil-ipe-240-otel-laminat-la-cald.html");
ok($c === 200 && str_contains($b, 'Pret la cerere') && !str_contains($b, 'data-cart-id="') && !str_contains($b, '"offers"'), 'fara pret: „Pret la cerere", fara cos, fara oferta in JSON-LD');
[$c, $b] = http("$base/fier-forjat.html");
ok($c === 200 && str_contains($b, '<strong>14</strong>'),            'colectia Fier forjat: 14 produse');
[$c, , $loc] = http("$base/catalog.html?cat=tevi-rotunde");
ok($c === 301 && str_ends_with($loc, '/tevi-rotunde.html'),           'adresa veche catalog?cat= => 301 spre categorie');
[$c, $b] = http("$base/catalog.html?q=" . rawurlencode('<script>alert(1)</script>'));
ok($c === 200 && !str_contains($b, '<script>alert(1)</script>'),      'cautarea escapeaza textul cautat');
[$c, $b] = http("$base/");
ok($c === 200 && substr_count($b, 'class="prod-card cat-card"') === 6 && !preg_match('#href="/?(electrice|sanitare|mobilier|gradina|izolatie)\.html#', $b), 'prima pagina: 6 produse noi, niciun link spre paginile vechi');
[$c, $b] = http("$base/acoperis.html");
ok($c === 200 && str_contains($b, '<h1>Acoperiș</h1>'),               'adresa veche /acoperis.html = categoria noua Acoperis');
[$c, , $loc] = http("$base/izolatie.html");
ok($c === 301 && str_ends_with($loc, '/termoizolatii.html'),          'adresa veche /izolatie.html => 301 spre colectia Termoizolatii');
[$c, , $loc] = http("$base/mobilier.html");
ok($c === 301 && str_ends_with($loc, '/catalog.html'),               'pagina veche fara echivalent => 301 spre catalog');

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
