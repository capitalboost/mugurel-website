<?php
require_once __DIR__ . '/../../includes/product_grid.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass,$fail; if($c){$pass++;echo"  ✓ $m\n";}else{$fail++;echo"  ✗ $m\n";} }

$sample = [[
    'id' => 1,
    'name' => 'Cablu CYY-F 3x2.5mm² 100m',
    'short_description' => 'Cablu electric pentru instalatii fixe. Conductor cupru, izolatie PVC. Rola 100m.',
    'icon_key' => 'inexistenta', 'icon_label' => 'Cablu CYY-F 3x2.5mm²',
    'price' => null, 'price_unit' => null,
    'cat_slugs' => 'electrice', 'subcat_slug' => 'cabluri', 'cat_label' => 'Electrice',
    'properties' => [
        ['label' => 'Aspect',   'body' => 'Cablu flexibil cu 3 conductori de cupru.', 'is_list' => 0],
        ['label' => 'Avantaje', 'body' => "Flexibil, usor de pozat
Rezistenta la UV", 'is_list' => 1],
    ],
]];

// ── varianta .material-card (pagini de categorie) ──
$m = renderMaterialCards($sample);
ok(str_contains($m, 'class="material-card"'),        'material: containerul .material-card');
ok(str_contains($m, 'class="material-name"'),        'material: titlul .material-name');
ok(str_contains($m, 'class="prop-val"'),             'material: textul in .prop-val');
ok(str_contains($m, '<span class="prop-label">Aspect</span>'), 'material: eticheta primului bloc');
ok(str_contains($m, '<ul class="prop-list">'),       'material: blocul de tip lista devine <ul>');
ok(substr_count($m, '<li>') === 2,                   'material: doua elemente in lista');
ok(substr_count($m, 'class="material-prop"') === 2,  'material: ambele blocuri randate');
ok(str_contains($m, 'class="btn-wa-material"'),      'material: butonul e .btn-wa-material');
ok(str_contains($m, 'href="https://wa.me/40749130565'), 'material: link WhatsApp direct');
ok(str_contains($m, 'Pret la cerere'),               'material: pret la cerere cand price e null');
ok(!str_contains($m, 'class="prod-card"'),           'material: NU foloseste clasele de catalog');

// ── varianta .prod-card (catalog) ──
$p = renderProdCards($sample);
ok(str_contains($p, 'class="prod-card"'),            'prod: containerul .prod-card');
ok(str_contains($p, 'data-cat="electrice"'),         'prod: data-cat din cat_slugs');
ok(str_contains($p, 'data-subcat="cabluri"'),        'prod: data-subcat din subcat_slug');
ok(str_contains($p, 'class="prod-desc"'),            'prod: descrierea in .prod-desc');
ok(str_contains($p, 'class="btn-wa-prod"'),          'prod: butonul e .btn-wa-prod');
ok(str_contains($p, 'data-wa-product='),             'prod: atributul data-wa-product');
ok(!str_contains($p, 'class="material-card"'),       'prod: NU foloseste clasele de categorie');

// ── escaping ──
$xss = $sample;
$xss[0]['name'] = 'Test "<script>alert(1)</script>';
foreach (['renderMaterialCards','renderProdCards'] as $fn) {
    $h = $fn($xss);
    ok(!str_contains($h, '<script>'), "$fn escapeaza numele");
}

// ── multi-categorie ──
$multi = $sample;
$multi[0]['cat_slugs'] = 'incalzire apa-canal';
ok(str_contains(renderProdCards($multi), 'data-cat="incalzire apa-canal"'), 'prod: cat_slugs multiple');

// Produs fara proprietati: cade pe short_description, cardul nu ramane gol.
$fara = $sample; $fara[0]['properties'] = [];
ok(str_contains(renderMaterialCards($fara), 'class="prop-val"'), 'fara properties => foloseste short_description');

// ── lista goala ──
ok(renderMaterialCards([]) === '', 'lista goala => string gol');
ok(str_contains(renderEmptyNotice(), 'wa.me/40749130565'), 'mesajul gol trimite pe WhatsApp');

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
