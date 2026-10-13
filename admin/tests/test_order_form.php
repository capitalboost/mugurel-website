<?php
// Teste pentru includes/order_form.php (T7 — persoana fizica). Fara baza de date.
require_once __DIR__ . '/../../includes/order_form.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass,$fail; if($c){$pass++;echo"  ✓ $m\n";}else{$fail++;echo"  ✗ $m\n";} }

// ── telefon ──
foreach (['0749 130 565', '0749-130-565', '0749.130.565', '+40 749 130 565', '0040749130565', '40749130565',
          '+40 (749) 130 565', '+400749130565', '0251 123 456', '0351123456'] as $t) {
    ok(orderPhone($t) !== null, "telefon valid: $t");
}
ok(orderPhone('0749 130 565') === '+40749130565', 'telefon: normalizat la +40749130565');
ok(orderPhone('0251 123 456') === '+40251123456', 'telefon fix: normalizat la +40251123456');
foreach (['', '0749', '07491305651', '0149130565', '0849130565', '+44 7911 123456', 'telefon', '0749 13O 565'] as $t) {
    ok(orderPhone($t) === null, "telefon invalid: '$t'");
}

// ── curatarea textului ──
ok(orderText("  Ion   Popescu \t", 100) === 'Ion Popescu',                 'text: spatii comprimate');
ok(orderText("Ion\x00Pop\x1Fescu", 100) === 'Ion Pop escu',                 'text: caractere de control eliminate');
ok(orderText(['array'], 100) === '',                                           'text: array (POST falsificat) => gol');
ok(orderText(str_repeat('a', 300), 100) === str_repeat('a', 100),            'text: taiat la lungimea maxima');
ok(orderText("rand 1\r\nrand 2\x07", 100, true) === "rand 1\nrand 2",         'text multilinie: pastreaza randurile, scoate controlul');
ok(mb_strlen(orderText(str_repeat('ă', 150), 100)) === 100,                  'text: taiere pe caractere, nu pe octeti (diacritice)');

// ── validare completa ──
$good = ['nume' => 'Ion Popescu', 'telefon' => '0749 130 565', 'email' => 'Ion.Popescu@Exemplu.ro',
         'judet' => 'Dolj', 'localitate' => 'Dabuleni', 'adresa' => 'Str. Caracal nr. 52', 'mentiuni' => ''];
$r = orderFormValidate($good);
ok($r['errors'] === [],                                     'valid: fara erori');
ok($r['data']['telefon'] === '+40749130565',               'valid: telefon normalizat');
ok($r['data']['email'] === 'ion.popescu@exemplu.ro',       'valid: email cu litere mici');

ok(orderFormValidate(['nume' => "Ștefan O'Neil-Dumitrașcu"] + $good)['errors'] === [], 'nume: diacritice, apostrof, cratima');

$bad = orderFormValidate([]);
ok(array_keys($bad['errors']) === ['nume', 'telefon', 'email', 'judet', 'localitate', 'adresa'], 'gol: toate campurile obligatorii semnalate');
ok(!isset($bad['errors']['mentiuni']),                       'gol: mentiunile sunt optionale');

$cases = [
    'nume'       => ['Io', '<script>', 'Ion 123', '   '],
    'telefon'    => ['123', 'abc'],
    'email'      => ['ion', 'ion@', 'ion@exemplu', 'ion@@exemplu.ro', "ion@exemplu.ro\nBcc: x@y.ro"],
    'judet'      => ['Atlantis', 'dolj', ''],
    'localitate' => ['D'],
    'adresa'     => ['Str'],
];
foreach ($cases as $field => $values) {
    foreach ($values as $v) {
        $res = orderFormValidate([$field => $v] + $good);
        ok(isset($res['errors'][$field]) && count($res['errors']) === 1, "invalid $field: " . json_encode($v));
    }
}

$long = orderFormValidate(['mentiuni' => str_repeat('x', 5000)] + $good);
ok($long['errors'] === [] && mb_strlen($long['data']['mentiuni']) === 1000, 'mentiuni: taiate la 1000 de caractere');
ok(orderFormValidate(['nume' => ['Ion']] + $good)['errors']['nume'] ?? false, 'campuri trimise ca array => eroare, nu crash');

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
