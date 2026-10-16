<?php
// Teste pentru includes/orders.php (T11). Folosesc baza LOCALA si sterg tot ce creeaza.
// Ultima sectiune trimite comenzi reale la serverul local (http://localhost:8080).
require_once __DIR__ . '/../../includes/orders.php';
require_once __DIR__ . '/../../includes/cart.php';
require_once __DIR__ . '/../../includes/order_form.php';

require_once __DIR__ . '/catalog_fixture.php'; // fx('A') = produsele de test (dev/fixtures/cos_test.sql)
$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass,$fail; if($c){$pass++;echo"  ✓ $m\n";}else{$fail++;echo"  ✗ $m\n";} }

$pdo = Database::get();
$created = []; // form_token-urile create de test, sterse la final
$count = fn (string $sql, array $p = []) => (function () use ($pdo, $sql, $p) { $s = $pdo->prepare($sql); $s->execute($p); return (int) $s->fetchColumn(); })();

// ── functii simple ──
ok(orderNumber(42, 2026) === 'MUG-2026-000042',                    'numar: MUG-2026-000042');
ok(orderFormTokenValid(orderNewFormToken()),                      'token formular: 32 hex');
ok(!orderFormTokenValid('abc') && !orderFormTokenValid(['x']) && !orderFormTokenValid(str_repeat('G', 32)), 'token formular: formate invalide respinse');

$data = orderFormValidate(['nume' => 'Test Comanda', 'telefon' => '0749130565', 'email' => 'test@exemplu.ro',
    'judet' => 'Dolj', 'localitate' => 'Dabuleni', 'adresa' => 'Str. Test 1', 'plata' => 'ramburs', 'acord' => '1',
    'mentiuni' => 'Sunati inainte'])['data'];
// 2 × 25,24 (sac) + 2,5 × 22,43 (kg) = 50,48 + 56,08 (56,075 rotunjit) = 106,56 lei
$cart = priceCart(cartNormalizeItems([['id' => fx('A'), 'qty' => 2], ['id' => fx('KG'), 'qty' => '2,5']]), cartProducts([fx('A'), fx('KG')]));
ok($cart['problem_count'] === 0 && $cart['total_bani'] === 10656, 'pregatire: cos valid de 106,56 lei');

$h1 = orderContentHash($data, $cart['lines']);
ok($h1 === orderContentHash($data, array_reverse($cart['lines'])),  'amprenta: nu depinde de ordinea produselor');
$l2 = $cart['lines']; $l2[0]['qty'] = 3.0;
ok($h1 !== orderContentHash($data, $l2),                           'amprenta: alta cantitate => alta amprenta');
ok($h1 !== orderContentHash(['telefon' => '+40749000000'] + $data, $cart['lines']), 'amprenta: alt client => alta amprenta');

try {
    // ── creare ──
    $t1 = orderNewFormToken(); $created[] = $t1;
    $o = orderCreate($pdo, $data, $cart, $t1, '127.0.0.1', 'test');
    ok(!$o['duplicate'] && preg_match('/^MUG-\d{4}-\d{6}$/', $o['number']) === 1, 'creare: comanda noua cu numar ' . $o['number']);
    ok(preg_match('/^[0-9a-f]{48}$/', $o['token']) === 1,              'creare: token public de 48 hex');
    $row = orderByToken($pdo, $o['token']);
    ok($row !== null && (int) $row['total_bani'] === 10656,          'creare: total salvat 106,56 lei');
    ok($row['telefon'] === '+40749130565' && $row['status'] === 'noua' && $row['mentiuni'] === 'Sunati inainte', 'creare: datele clientului salvate');
    ok(count($row['items']) === 2,                                     'creare: 2 produse salvate');
    $it = $row['items'][1];
    ok($it['name'] === 'Platbandă zincată, oțel, 25 x 4 mm' && $it['unit'] === 'kg' && (float) $it['qty'] === 2.5
       && (int) $it['price_bani'] === 2243 && (int) $it['line_bani'] === 5608, 'creare: copie exacta a produsului (nume, unitate, pret, subtotal)');

    // ── copia nu se schimba cand se schimba pretul produsului ──
    $orig = $pdo->query('SELECT price FROM products WHERE id = ' . fx('A'))->fetchColumn();
    $pdo->exec('UPDATE products SET price = 999.00 WHERE id = ' . fx('A'));
    ok((int) orderByToken($pdo, $o['token'])['items'][0]['price_bani'] === 2524, 'copie: pretul din comanda ramane 25,24 dupa scumpire');
    $pdo->prepare('UPDATE products SET price = ? WHERE id = ' . fx('A'))->execute([$orig]);

    // ── dubluri ──
    $again = orderCreate($pdo, $data, $cart, $t1);
    ok($again['duplicate'] && $again['id'] === $o['id'],             'dublura: acelasi formular trimis din nou => aceeasi comanda');
    $t2 = orderNewFormToken(); $created[] = $t2;
    $back = orderCreate($pdo, $data, $cart, $t2);
    ok($back['duplicate'] && $back['id'] === $o['id'],               'dublura: formular nou, acelasi continut in 10 min („Inapoi" + retrimite) => aceeasi comanda');
    ok($count('SELECT COUNT(*) FROM orders WHERE content_hash = ?', [$h1]) === 1, 'dublura: o singura comanda in baza');

    $t3 = orderNewFormToken(); $created[] = $t3;
    $other = orderCreate($pdo, $data, priceCart([['id' => fx('A'), 'qty' => 5.0]], cartProducts([fx('A')])), $t3);
    ok(!$other['duplicate'] && $other['id'] !== $o['id'],            'comanda diferita (alt cos) => comanda noua');

    $pdo->prepare('UPDATE orders SET created_at = DATE_SUB(NOW(), INTERVAL 11 MINUTE) WHERE id = ?')->execute([$o['id']]);
    $t4 = orderNewFormToken(); $created[] = $t4;
    $later = orderCreate($pdo, $data, $cart, $t4);
    ok(!$later['duplicate'],                                          'aceeasi comanda dupa 11 minute => comanda noua (client care recomanda)');

    // ── rollback: eroare la jumatea scrierii => nimic salvat ──
    $broken = $cart;
    $broken['lines'][1]['name'] = null; // order_items.name NOT NULL => INSERT-ul pica dupa ce comanda a fost scrisa
    $t5 = orderNewFormToken(); $created[] = $t5;
    $data5 = ['nume' => 'Test Rollback'] + $data;
    $threw = false;
    try { orderCreate($pdo, $data5, $broken, $t5); } catch (Throwable $e) { $threw = true; }
    ok($threw,                                                         'rollback: eroarea ajunge la apelant');
    ok($count('SELECT COUNT(*) FROM orders WHERE form_token = ?', [$t5]) === 0, 'rollback: comanda NU ramane salvata');
    ok($count('SELECT COUNT(*) FROM order_items oi LEFT JOIN orders o ON o.id = oi.order_id WHERE o.id IS NULL') === 0, 'rollback: niciun produs orfan');
    ok(!$pdo->inTransaction(),                                         'rollback: tranzactia e inchisa');

    $threw = false;
    try { orderCreate($pdo, $data, priceCart([['id' => fx('IND'), 'qty' => 1.0]], cartProducts([fx('IND')])), orderNewFormToken()); } catch (InvalidArgumentException $e) { $threw = true; }
    ok($threw,                                                         'cos cu produs indisponibil => refuzat, nu se salveaza');

    ok(orderByToken($pdo, str_repeat('0', 48)) === null && orderByToken($pdo, "' OR 1=1 --") === null, 'cautare: token inexistent sau malformat => null');

    // ── HTTP: fluxul real, inclusiv dublu-click ──
    $base = getenv('BASE') ?: 'http://localhost:8080';
    $req = function (string $url, ?array $post, string $jar) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $jar,
                                CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 20]);
        if ($post !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
        $r = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        preg_match('/^Location: (.+)$/mi', $r, $m);
        return [$code, trim($m[1] ?? ''), $r];
    };
    $jar = tempnam(sys_get_temp_dir(), 'cj');
    [$c, , $page] = $req($base . '/comanda.html', null, $jar);
    if ($c !== 200) {
        ok(false, "server local indisponibil ($base) — testele HTTP NU au rulat");
    } else {
        preg_match('/name="csrf" value="([0-9a-f]{64})"/', $page, $m1);
        preg_match('/name="idem" value="([0-9a-f]{32})"/', $page, $m2);
        ok(isset($m1[1], $m2[1]),                                      'http: formularul are token CSRF si token anti-dublura');
        $created[] = $m2[1];
        $post = ['csrf' => $m1[1], 'idem' => $m2[1], 'nume' => 'Client HTTP', 'telefon' => '0749 130 565',
                 'email' => 'http@exemplu.ro', 'judet' => 'Dolj', 'localitate' => 'Dabuleni', 'adresa' => 'Str. HTTP 9',
                 'plata' => 'transfer', 'acord' => '1', 'cos' => json_encode([['id' => fx('B'), 'qty' => 3]])];
        [$c1, $loc1] = $req($base . '/comanda.html', $post, $jar);
        [$c2, $loc2] = $req($base . '/comanda.html', $post, $jar); // al doilea click / retrimitere
        ok($c1 === 303 && str_starts_with($loc1, '/comanda-trimisa.html?t='), 'http: comanda valida => 303 spre confirmare');
        ok($c2 === 303 && $loc2 === $loc1,                              'http: a doua trimitere => aceeasi confirmare');
        ok($count('SELECT COUNT(*) FROM orders WHERE form_token = ?', [$m2[1]]) === 1, 'http: o singura comanda in baza');
        $saved = orderByToken($pdo, substr($loc1, strlen('/comanda-trimisa.html?t=')));
        ok($saved !== null && (int) $saved['total_bani'] === 17049 && $saved['plata'] === 'transfer', 'http: total recalculat pe server (3 × 56,83 = 170,49 lei)');
    }
} finally {
    if ($created) {
        $in = implode(',', array_fill(0, count($created), '?'));
        $pdo->prepare("DELETE FROM orders WHERE form_token IN ($in)")->execute($created);
    }
    $pdo->exec("DELETE FROM orders WHERE email IN ('test@exemplu.ro', 'http@exemplu.ro')");
}
ok($count("SELECT COUNT(*) FROM orders WHERE email IN ('test@exemplu.ro', 'http@exemplu.ro')") === 0, 'curatenie: comenzile de test sterse');

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
