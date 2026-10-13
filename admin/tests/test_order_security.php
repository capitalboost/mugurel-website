<?php
// Teste pentru includes/order_security.php (T10). Partea de baza foloseste baza LOCALA si sterge ce scrie.
// Ultima sectiune trimite cereri reale la serverul local (http://localhost:8080) — trebuie sa ruleze.
require_once __DIR__ . '/../../includes/order_security.php';
require_once __DIR__ . '/../../includes/db.php';

orderSessionStart(); // inainte de orice afisare (trimite cookie)

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass,$fail; if($c){$pass++;echo"  ✓ $m\n";}else{$fail++;echo"  ✗ $m\n";} }

// ── ipInCidr ──
ok(ipInCidr('173.245.48.1', '173.245.48.0/20'),        'cidr v4: in interval');
ok(ipInCidr('173.245.63.255', '173.245.48.0/20'),      'cidr v4: ultima adresa din /20');
ok(!ipInCidr('173.245.64.0', '173.245.48.0/20'),       'cidr v4: prima adresa din afara');
ok(ipInCidr('2606:4700:10::6816:1', '2606:4700::/32'), 'cidr v6: in interval');
ok(!ipInCidr('2606:4701::1', '2606:4700::/32'),        'cidr v6: in afara');
ok(!ipInCidr('173.245.48.1', '2606:4700::/32'),        'cidr: v4 fata de interval v6 => nu');
ok(!ipInCidr('nu-e-ip', '173.245.48.0/20'),            'cidr: text => nu');
ok(ipInCidr('10.0.0.1', '10.0.0.1'),                   'cidr: adresa fara /prefix = potrivire exacta');

// ── IP-ul real al clientului ──
ok(orderClientIp(['REMOTE_ADDR' => '86.120.1.2']) === '86.120.1.2', 'ip: fara Cloudflare => REMOTE_ADDR');
ok(orderClientIp(['REMOTE_ADDR' => '86.120.1.2', 'HTTP_CF_CONNECTING_IP' => '1.2.3.4']) === '86.120.1.2',
   'ip: antet CF falsificat de un client direct => ignorat');
ok(orderClientIp(['REMOTE_ADDR' => '162.158.10.20', 'HTTP_CF_CONNECTING_IP' => '86.120.1.2']) === '86.120.1.2',
   'ip: cerere de la Cloudflare => IP-ul real din CF-Connecting-IP');
ok(orderClientIp(['REMOTE_ADDR' => '2400:cb00::1', 'HTTP_CF_CONNECTING_IP' => '2a02:2f0e::5']) === '2a02:2f0e::5',
   'ip: Cloudflare pe IPv6');
ok(orderClientIp(['REMOTE_ADDR' => '162.158.10.20', 'HTTP_CF_CONNECTING_IP' => 'gunoi']) === '162.158.10.20',
   'ip: antet CF invalid => REMOTE_ADDR');

// ── CSRF ──
$t = orderCsrfToken();
ok(strlen($t) === 64 && ctype_xdigit($t),               'csrf: token de 64 de caractere hex');
ok(orderCsrfToken() === $t,                            'csrf: acelasi token in aceeasi sesiune');
ok(orderCsrfValid($t),                                  'csrf: tokenul corect e acceptat');
ok(!orderCsrfValid(''),                                 'csrf: gol => respins');
ok(!orderCsrfValid(substr($t, 0, 63) . 'x'),           'csrf: un caracter diferit => respins');
ok(!orderCsrfValid([$t]),                               'csrf: trimis ca array => respins');
ok(!orderCsrfValid(null),                               'csrf: lipsa => respins');

// ── capcana anti-robot ──
unset($_SESSION['form_shown_at']);
ok(orderLooksLikeBot([]),                                'robot: formularul nu a fost afisat in sesiune');
// Pragul de productie e 3 s; il dam explicit, ca testul sa nu depinda de config-ul local.
$_SESSION['form_shown_at'] = time();
ok(orderLooksLikeBot([], 3),                             'robot: trimis imediat dupa afisare');
$_SESSION['form_shown_at'] = time() - 1;
ok(orderLooksLikeBot([], 3),                             'robot: trimis dupa 1 s (< 3 s)');
$_SESSION['form_shown_at'] = time() - 4;
ok(!orderLooksLikeBot([], 3),                            'om: trimis dupa 4 s');
$_SESSION['form_shown_at'] = time() - 30;
ok(!orderLooksLikeBot([], 3),                            'om: trimis dupa 30 s, campul ascuns gol');
ok(orderLooksLikeBot([ORDER_HONEYPOT => 'http://spam.example'], 3), 'robot: campul ascuns completat');
ok(orderLooksLikeBot([ORDER_HONEYPOT => ['x']], 3),      'robot: campul ascuns trimis ca array');
ok(!orderLooksLikeBot([ORDER_HONEYPOT => '   '], 3),     'om: doar spatii in campul ascuns (autocompletare) => acceptat');
$_SESSION['form_shown_at'] = (string) time();
ok(orderLooksLikeBot([], 3),                             'robot: momentul afisarii alterat (nu e numar intreg)');

// ── limita pe IP ──
$pdo = Database::get();
$ip = 'test-' . bin2hex(random_bytes(4));
try {
    $r = [];
    for ($i = 0; $i < 4; $i++) { $r[] = orderRateLimited($pdo, $ip, 3, 600); }
    ok($r === [false, false, false, true],                'limita: primele 3 trec, a 4-a e blocata');
    ok(orderRateLimited($pdo, 'alt-' . $ip, 3, 600) === false, 'limita: alt IP nu e afectat');
    $pdo->prepare('UPDATE order_attempts SET created_at = DATE_SUB(NOW(), INTERVAL 700 SECOND) WHERE ip = ?')->execute([$ip]);
    ok(orderRateLimited($pdo, $ip, 3, 600) === false,     'limita: dupa fereastra de 10 minute, IP-ul poate trimite din nou');
    $left = $pdo->prepare('SELECT COUNT(*) FROM order_attempts WHERE ip = ?');
    $left->execute([$ip]);
    ok((int) $left->fetchColumn() === 1,                   'limita: incercarile vechi sunt sterse automat');
} finally {
    $pdo->prepare('DELETE FROM order_attempts WHERE ip IN (?, ?)')->execute([$ip, 'alt-' . $ip]);
}

// ── integrare: cereri reale la serverul local ──
$base = getenv('BASE') ?: 'http://localhost:8080';
function http(string $url, ?array $post = null, string $cookieJar = ''): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookieJar, CURLOPT_COOKIEFILE => $cookieJar, CURLOPT_TIMEOUT => 20]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, $body];
}
[$code] = http($base . '/comanda.html');
if ($code !== 200) {
    ok(false, "server local indisponibil ($base) — testele de integrare NU au rulat");
} else {
    $jar = tempnam(sys_get_temp_dir(), 'cj');
    [, $page] = http($base . '/comanda.html', null, $jar);
    preg_match('/name="csrf" value="([0-9a-f]{64})"/', $page, $m);
    $token = $m[1] ?? '';
    ok($token !== '',                                        'http: formularul contine tokenul CSRF');
    $form = ['nume' => 'Ion Popescu', 'telefon' => '0749130565', 'email' => 'ion@exemplu.ro', 'judet' => 'Dolj',
             'localitate' => 'Dabuleni', 'adresa' => 'Str. Caracal 52', 'plata' => 'ramburs', 'acord' => '1',
             'cos' => json_encode([['id' => 3470, 'qty' => 1]])];

    [$c, $b] = http($base . '/comanda.html', $form, $jar);
    ok($c === 400 && str_contains($b, 'Sesiunea a expirat'),  'http: fara token CSRF => 400');
    ok(str_contains($b, 'value="Ion Popescu"'),               'http: fara token, datele raman in formular');

    [$c] = http($base . '/comanda.html', $form + ['csrf' => $token], tempnam(sys_get_temp_dir(), 'cj'));
    ok($c === 400,                                             'http: token valid dar din ALTA sesiune => 400');

    [$c, $b] = http($base . '/comanda.html', $form + ['csrf' => $token, ORDER_HONEYPOT => 'spam'], $jar);
    ok($c === 422 && str_contains($b, 'Comanda nu a putut fi trimisa'), 'http: campul ascuns completat => respins, mesaj generic');

    [$c, $b] = http($base . '/comanda.html', ['acord' => ''] + $form + ['csrf' => $token], $jar);
    ok($c === 422 && str_contains($b, 'id="err-acord"'),     'http: fara acord GDPR => eroare pe bifa');

    [$c, $b] = http($base . '/comanda.html', ['cos' => json_encode([['id' => 3686, 'qty' => 1]])] + $form + ['csrf' => $token], $jar);
    ok($c === 422 && str_contains($b, 'data-cart-error'),    'http: cos cu produs indisponibil (ocolind browserul) => respins');
    [$c, $b] = http($base . '/comanda.html', ['cos' => '{nu e json'] + $form + ['csrf' => $token], $jar);
    ok($c === 422 && str_contains($b, 'Cosul tau este gol'),  'http: cos corupt => respins');
    [$c, $b] = http($base . '/comanda.html', ['cos' => str_repeat('x', 20001)] + $form + ['csrf' => $token], $jar);
    ok($c === 422 && str_contains($b, 'data-cart-error'),    'http: cos prea mare => respins');

    [$c] = http($base . '/comanda.html', $form + ['csrf' => $token], $jar);
    ok($c === 503,                                             'http: totul valid => trece de toate protectiile (inregistrarea vine in T11)');

    // limita pe IP, cap-coada: umplem fereastra pentru IP-ul local, apoi curatam
    $local = ['::1', '127.0.0.1'];
    $ins = $pdo->prepare('INSERT INTO order_attempts (ip) VALUES (?)');
    try {
        foreach ($local as $lip) { for ($i = 0; $i < ORDER_RATE_MAX; $i++) { $ins->execute([$lip]); } }
        [$c, $b] = http($base . '/comanda.html', $form + ['csrf' => $token], $jar);
        ok($c === 429 && str_contains($b, 'de prea multe ori'), 'http: peste limita de trimiteri pe IP => 429');
    } finally {
        $pdo->exec("DELETE FROM order_attempts WHERE ip IN ('::1', '127.0.0.1')");
    }
}

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
