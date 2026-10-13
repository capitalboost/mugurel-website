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
         'judet' => 'Dolj', 'localitate' => 'Dabuleni', 'adresa' => 'Str. Caracal nr. 52', 'mentiuni' => '', 'plata' => 'ramburs', 'acord' => '1'];
$r = orderFormValidate($good);
ok($r['errors'] === [],                                     'valid: fara erori');
ok($r['data']['telefon'] === '+40749130565',               'valid: telefon normalizat');
ok($r['data']['email'] === 'ion.popescu@exemplu.ro',       'valid: email cu litere mici');

ok(orderFormValidate(['nume' => "Ștefan O'Neil-Dumitrașcu"] + $good)['errors'] === [], 'nume: diacritice, apostrof, cratima');

$bad = orderFormValidate([]);
ok(array_keys($bad['errors']) === ['nume', 'telefon', 'email', 'plata', 'judet', 'localitate', 'adresa', 'acord'], 'gol: toate campurile obligatorii semnalate');
ok(isset(orderFormValidate(['acord' => 'da'] + $good)['errors']['acord']), 'acord: doar valoarea 1 e acceptata');
ok(isset(orderFormValidate(['acord' => ['1']] + $good)['errors']['acord']), 'acord: trimis ca array => respins');
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

// ── persoana juridica (T8) ──
foreach (['14399840', 'RO14399840', 'ro 14399840', 'RO 14 399 840', '18547290'] as $c) {
    ok(orderCui($c) !== null, "CUI valid: $c");
}
ok(orderCui('RO14399840') === '14399840',                    'CUI: intoarce doar cifrele');
foreach (['14399841', '1', '12345678901', 'RO', 'ABC123', '', '14399840X'] as $c) {
    ok(orderCui($c) === null, "CUI invalid: '$c'");
}
ok(orderRegCom('J16/123/2020') === 'J16/123/2020',            'RC clasic');
ok(orderRegCom('j 5/0042/2019') === 'J05/42/2019',            'RC clasic normalizat (majuscule, 05, fara zerouri)');
ok(orderRegCom('F40/1234/2015') === 'F40/1234/2015',          'RC: PFA (F)');
ok(orderRegCom('J2024000123168') === 'J2024000123168',        'RC: formatul unic din 2024');
foreach (['', 'J16-123-2020', 'X16/123/2020', 'J16/123/1850', 'J16/123', '123456'] as $r) {
    ok(orderRegCom($r) === null, "RC invalid: '$r'");
}

$pj = ['tip' => 'pj', 'firma' => 'Constructii Dabuleni SRL', 'cui' => 'RO14399840', 'reg_com' => 'J16/123/2020',
       'sediu' => 'Str. Principala 1, Dabuleni, Dolj'] + $good;
$r = orderFormValidate($pj);
ok($r['errors'] === [],                                      'PJ valid: fara erori');
ok($r['data']['cui'] === 'RO14399840',                      'PJ: prefixul RO pastrat (platitor de TVA)');
ok(orderFormValidate(['cui' => '14399840'] + $pj)['data']['cui'] === '14399840', 'PJ: fara RO ramane fara RO');
$r = orderFormValidate(['tip' => 'pj'] + $good);
ok(array_keys($r['errors']) === ['firma', 'cui', 'reg_com', 'sediu'], 'PJ gol: cele 4 campuri de firma obligatorii');
ok(orderFormValidate(['nume' => ''] + $pj)['errors']['nume'] === 'Scrie numele persoanei de contact.', 'PJ: mesajul pentru persoana de contact');
ok(orderFormValidate(['cui' => 'RO14399841'] + $pj)['errors']['cui'] === 'Codul fiscal nu este valid — verifica cifrele (ex. RO14399840).', 'PJ: CUI cu cifra de control gresita');

$pf = orderFormValidate(['tip' => 'pf', 'firma' => 'Ignorata SRL', 'cui' => 'gresit'] + $good);
ok($pf['errors'] === [] && $pf['data']['firma'] === '' && $pf['data']['cui'] === '', 'PF: campurile de firma sunt ignorate complet');
ok(orderFormValidate(['tip' => 'altceva'] + $good)['data']['tip'] === 'pf', 'tip necunoscut => persoana fizica');
ok(orderFormValidate(['tip' => ['pj']] + $good)['data']['tip'] === 'pf',   'tip trimis ca array => persoana fizica');

// ── plata si livrare (T9) ──
foreach (array_keys(ORDER_PLATA) as $pl) {
    ok(orderFormValidate(['plata' => $pl] + $good)['errors'] === [], "plata valida: $pl");
}
foreach (['', 'card', 'RAMBURS', ['ramburs']] as $pl) {
    $res = orderFormValidate(['plata' => $pl] + $good);
    ok(($res['errors']['plata'] ?? '') === 'Alege cum vrei sa platesti.' && $res['data']['plata'] === '', 'plata invalida: ' . json_encode($pl));
}
$rid = orderFormValidate(['plata' => 'ridicare', 'judet' => '', 'localitate' => '', 'adresa' => ''] + $good);
ok($rid['errors'] === [],                                   'ridicare: adresa de livrare nu e ceruta');
$rid2 = orderFormValidate(['plata' => 'ridicare'] + $good);
ok($rid2['data']['adresa'] === '' && $rid2['data']['judet'] === '' && $rid2['data']['localitate'] === '', 'ridicare: adresa completata e ignorata (nu exista livrare)');
$tr = orderFormValidate(['plata' => 'transfer', 'adresa' => ''] + $good);
ok(isset($tr['errors']['adresa']),                          'transfer: adresa de livrare ceruta');
ok(isset(ORDER_PLATA['ramburs']['info'], ORDER_PLATA['transfer']['info'], ORDER_PLATA['ridicare']['info']) && strlen(ORDER_TRANSPORT_INFO) > 40, 'texte: fiecare metoda are explicatie; mesaj de transport');
ok(!isset(ORDER_PLATA['card']),                             'fara plata cu cardul online (decizia din 13 oct)');

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
