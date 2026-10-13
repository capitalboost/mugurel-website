<?php
// Teste pentru includes/cart.php (T6). Partea cu baza foloseste baza LOCALA si restaureaza ce modifica.
require_once __DIR__ . '/../../includes/cart.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass,$fail; if($c){$pass++;echo"  ✓ $m\n";}else{$fail++;echo"  ✗ $m\n";} }

// ── cartNormalizeItems: ce vine din browser nu e de incredere ──
$n = cartNormalizeItems([
    ['id' => 5, 'qty' => 2],
    ['id' => '6', 'qty' => '2,5'],
    ['id' => 5, 'qty' => 9],          // dublura => ignorata (primul castiga)
    ['id' => 0, 'qty' => 1],
    ['id' => -3, 'qty' => 1],
    ['id' => 'abc', 'qty' => 1],
    ['id' => 1.5, 'qty' => 1],
    ['id' => 7, 'qty' => 0],
    ['id' => 8, 'qty' => -1],
    ['id' => 9, 'qty' => 'mult'],
    ['id' => 10, 'qty' => 1.239],     // rotunjit la 2 zecimale
    ['id' => 11, 'qty' => 1e9],       // plafonat
    'nu e array',
    ['qty' => 1],
]);
ok($n === [
    ['id' => 5, 'qty' => 2.0], ['id' => 6, 'qty' => 2.5], ['id' => 10, 'qty' => 1.24], ['id' => 11, 'qty' => 99999.0],
], 'normalize: pastreaza doar randurile valide, fara dubluri');
ok(cartNormalizeItems('text') === [] && cartNormalizeItems(null) === [], 'normalize: intrare care nu e lista => []');
$many = [];
for ($i = 1; $i <= 150; $i++) { $many[] = ['id' => $i, 'qty' => 1]; }
ok(count(cartNormalizeItems($many)) === 100,          'normalize: maximum 100 de randuri');

// ── unitati cu zecimale ──
ok(cartAllowsDecimals('m²') && cartAllowsDecimals('ML') && cartAllowsDecimals(' mp '), 'zecimale: m², ml, mp');
ok(!cartAllowsDecimals('buc') && !cartAllowsDecimals('set') && !cartAllowsDecimals(null), 'zecimale: nu la buc, set, fara unitate');

// ── formatLei ──
ok(formatLei(0) === '0,00 lei',                        'lei: 0');
ok(formatLei(123456789) === '1.234.567,89 lei',        'lei: separatori RO');

// ── priceCart (fara baza) ──
$prod = fn (array $o = []) => $o + ['id' => 1, 'name' => 'P', 'price' => '10.00', 'price_unit' => 'buc', 'price_is_from' => 0,
                                     'stock_status' => 'in_stoc', 'image_path' => null, 'image_alt' => null, 'page_slug' => 'x'];
$products = [
    1 => $prod(['id' => 1, 'price' => '0.10']),
    2 => $prod(['id' => 2, 'price' => '12.49', 'price_unit' => 'ml']),
    3 => $prod(['id' => 3, 'price_is_from' => 1]),
    4 => $prod(['id' => 4, 'stock_status' => 'indisponibil']),
    5 => $prod(['id' => 5, 'stock_status' => 'la_comanda', 'price' => '1999.99']),
    6 => $prod(['id' => 6, 'price' => null]),
];
$c = priceCart([
    ['id' => 1, 'qty' => 3.0],     // 0,10 × 3 = 0,30 exact (cu float ar fi 0,30000000000000004)
    ['id' => 2, 'qty' => 2.5],     // 12,49 × 2,5 = 31,225 => 31,23
    ['id' => 3, 'qty' => 1.0],
    ['id' => 4, 'qty' => 1.0],
    ['id' => 5, 'qty' => 2.0],
    ['id' => 6, 'qty' => 1.0],
    ['id' => 99, 'qty' => 1.0],
    ['id' => 1, 'qty' => 1.5],     // zecimale la „buc" (dupa normalize n-ar trebui sa ajunga dublura, dar regula se testeaza)
], $products);
$byId = [];
foreach ($c['lines'] as $l) { $byId[] = $l; }
ok($byId[0]['orderable'] && $byId[0]['line_bani'] === 30,            'calcul: 0,10 × 3 = 0,30 lei exact');
ok($byId[1]['line_bani'] === 3123 && $byId[1]['price_bani'] === 1249, 'calcul: 12,49 × 2,5 = 31,23 lei');
ok(!$byId[2]['orderable'] && str_contains($byId[2]['problem'], 'la cerere'), 'calcul: „de la" => necomandabil');
ok(!$byId[3]['orderable'] && str_contains($byId[3]['problem'], 'nu mai este in stoc'), 'calcul: indisponibil => necomandabil');
ok($byId[4]['orderable'] && $byId[4]['stock_label'] === 'La comanda', 'calcul: la comanda => comandabil, cu eticheta');
ok(!$byId[5]['orderable'],                                            'calcul: fara pret => necomandabil');
ok(!$byId[6]['orderable'] && str_contains($byId[6]['problem'], 'nu mai este disponibil'), 'calcul: produs inexistent/ascuns');
ok(!$byId[7]['orderable'] && str_contains($byId[7]['problem'], 'numar intreg'), 'calcul: 1,5 buc => cantitate invalida');
ok($c['total_bani'] === 30 + 3123 + 399998,                           'calcul: totalul include doar randurile comandabile');
ok($c['orderable_count'] === 3 && $c['problem_count'] === 5,          'calcul: numaratori');
$keys = array_keys($c['lines'][0]);
ok(array_reduce($c['lines'], fn ($okk, $l) => $okk && array_keys($l) === $keys, true), 'calcul: toate randurile au aceleasi chei');
ok(priceCart([], [])['total_bani'] === 0,                             'calcul: cos gol => total 0');

// ── cartProducts pe baza locala ──
$db = Database::get();
$rows = cartProducts([3470, 3674, 999999999, -1]);
$ids = is_array($rows) ? array_keys($rows) : [];
sort($ids);
ok($ids === [3470, 3674],                                             'db: doar produsele existente');
ok(isset($rows[3470]['price'], $rows[3470]['stock_status'], $rows[3470]['price_is_from']), 'db: campurile necesare pentru calcul');
ok(cartProducts([]) === [],                                           'db: lista goala => [] fara interogare');

$vis = $db->query('SELECT is_visible FROM products WHERE id = 3471')->fetchColumn();
try {
    $db->exec('UPDATE products SET is_visible = 0 WHERE id = 3471');
    ok(!isset(cartProducts([3471])[3471]),                           'db: produs ascuns => tratat ca inexistent');
} finally {
    $db->prepare('UPDATE products SET is_visible = ? WHERE id = 3471')->execute([$vis]);
}
ok((int) $db->query('SELECT is_visible FROM products WHERE id = 3471')->fetchColumn() === (int) $vis, 'db: vizibilitatea restaurata');

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
