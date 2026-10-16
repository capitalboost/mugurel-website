<?php
// Teste pentru cererile de retur (includes/returns.php, /cerere-retur.html) si pentru restul schimbarilor din 16 oct:
// livrarea doar in Dolj, stergerea IP-urilor din comenzi dupa 6 luni. Baza LOCALA + Mailpit + serverul local;
// sterge tot ce creeaza.
require_once __DIR__ . '/../../admin/config.php';
require_once __DIR__ . '/../../includes/returns.php';
require_once __DIR__ . '/../../includes/orders.php';
require_once __DIR__ . '/../../includes/order_security.php'; // ORDER_HONEYPOT
require_once __DIR__ . '/../models/Database.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass,$fail; if($c){$pass++;echo"  ✓ $m\n";}else{$fail++;echo"  ✗ $m\n";} }

const MAILPIT = 'http://127.0.0.1:8025/api/v1';
function mailpitSearch(string $q): array {
    $ch = curl_init(MAILPIT . '/search?query=' . rawurlencode($q));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $b = curl_exec($ch); unset($ch);
    return is_string($b) ? (json_decode($b, true)['messages'] ?? []) : [];
}
function mailpitDelete(string $q): void {
    $ch = curl_init(MAILPIT . '/search?query=' . rawurlencode($q));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => 'DELETE', CURLOPT_TIMEOUT => 10]);
    curl_exec($ch); unset($ch);
}

$pdo = Database::get();
$valid = ['tip' => 'retragere', 'order_ref' => 'MUG-2026-000123', 'nume' => 'Ion Retur', 'telefon' => '0749 130 565',
          'email' => 'retur-test@exemplu.ro', 'judet' => 'Dolj', 'localitate' => 'Dabuleni', 'adresa' => 'Str. Caracal 52',
          'produse' => "Adeziv Ceresit CM 11 — 2 saci", 'data_primirii' => date('Y-m-d', strtotime('-3 days')), 'acord' => '1'];

// ── IBAN ──
ok(returnIban('RO49 AAAA 1B31 0075 9384 0000') === 'RO49AAAA1B31007593840000', 'iban: valid, fara spatii');
ok(returnIban('RO49AAAA1B31007593840001') === null,                   'iban: cifra de control gresita => respins');
ok(returnIban('DE89370400440532013000') === null,                      'iban: doar romanesc');

// ── validare ──
$r = returnFormValidate($valid);
ok($r['errors'] === [] && $r['data']['telefon'] === '+40749130565',   'validare: cerere corecta, telefon normalizat');
$r = returnFormValidate(['tip' => 'altceva', 'acord' => ''] + array_fill_keys(array_keys(RETURN_MAX), ''));
ok(count($r['errors']) >= 8,                                            'validare: formular gol => erori pe toate campurile obligatorii');
ok(isset($r['errors']['tip'], $r['errors']['acord'], $r['errors']['produse']), 'validare: tip, acord si produse obligatorii');
$r = returnFormValidate(['tip' => 'neconform', 'motiv' => ''] + $valid);
ok(isset($r['errors']['motiv']),                                        'validare: produs neconform => motivul e obligatoriu');
ok(!isset(returnFormValidate(['motiv' => ''] + $valid)['errors']['motiv']), 'validare: la retragere motivul e optional');
ok(isset(returnFormValidate(['data_primirii' => date('Y-m-d', strtotime('+5 days'))] + $valid)['errors']['data_primirii']), 'validare: data primirii din viitor => eroare');
ok(isset(returnFormValidate(['iban' => 'RO00XXXX'] + $valid)['errors']['iban']), 'validare: IBAN gresit => eroare');
ok(returnFormValidate(['produse' => "<b>x</b>\nlinia 2 <script>"] + $valid)['data']['produse'] === "<b>x</b>\nlinia 2 <script>", 'validare: textul se pastreaza (se escapeaza la afisare)');

// ── salvare + dubluri + emailuri ──
$token = orderNewFormToken();
$d = returnFormValidate($valid)['data'];
$created = [];
try {
    $a = returnCreate($pdo, $d, $token, '1.2.3.4', 'Test');
    $created[] = $a['id'];
    ok(!$a['duplicate'] && preg_match('/^RET-\d{4}-\d{6}$/', $a['number']) === 1, 'salvare: numar ' . $a['number']);
    $b = returnCreate($pdo, $d, $token);
    ok($b['duplicate'] && $b['id'] === $a['id'],                       'salvare: acelasi token (dublu click) => aceeasi cerere');
    $row = returnById($pdo, $a['id']);
    ok($row['status'] === 'noua' && $row['telefon'] === '+40749130565' && $row['ip'] === '1.2.3.4', 'salvare: date si stare initiala');
    $adm = returnAdminEmail($row);
    ok(str_contains($adm['subject'], $a['number']) && str_contains($adm['html'], 'MUG-2026-000123'), 'email magazin: numarul cererii si al comenzii');
    $cl = returnClientEmail(['nume' => '<b>Ion</b>'] + $row);
    ok(str_contains($cl['html'], '&lt;b&gt;Ion&lt;/b&gt;') && !str_contains($cl['html'], '<b>Ion</b>'), 'email client: datele clientului escapate');
    ok(str_contains($cl['text'], 'inclusiv costul livrării inițiale') && str_contains($cl['text'], 'plătim noi'), 'email client (retragere): transport platit de magazin, rambursare cu livrarea');
    ok(str_contains(returnClientEmail(['tip' => 'neconform'] + $row)['text'], 'înlocuire, reparare'), 'email client (neconform): solutiile legale');

    // ── HTTP: formularul real (CSRF, capcana, timp minim, trimitere, PRG, 2 emailuri) ──
    mailpitDelete('to:retur-http@exemplu.ro');
    $jar = tempnam(sys_get_temp_dir(), 'cj');
    $req = function (string $url, ?array $post = null) use ($jar): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 20]
            + ($post !== null ? [CURLOPT_POSTFIELDS => http_build_query($post)] : []));
        $raw = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        unset($ch);
        $loc = preg_match('/^Location:\s*(\S+)/mi', substr($raw, 0, $hs), $m) ? $m[1] : '';
        return [$code, substr($raw, $hs), $loc];
    };
    $base = 'http://localhost:8080';
    [$c, $page] = $req("$base/cerere-retur.html");
    ok($c === 200 && str_contains($page, 'name="csrf"') && str_contains($page, 'name="' . ORDER_HONEYPOT . '"'), 'http: formularul cu CSRF si camp-capcana');
    preg_match('/name="csrf" value="([0-9a-f]{64})"/', $page, $m1);
    preg_match('/name="idem" value="([0-9a-f]{32})"/', $page, $m2);
    $post = ['csrf' => $m1[1] ?? '', 'idem' => $m2[1] ?? '', 'email' => 'retur-http@exemplu.ro', 'iban' => 'RO49AAAA1B31007593840000'] + $valid;
    [$c, $b] = $req("$base/cerere-retur.html", ['csrf' => 'x'] + $post);
    ok($c === 400 && str_contains($b, 'value="Ion Retur"'),            'http: CSRF gresit => 400, datele raman');
    [$c, $b] = $req("$base/cerere-retur.html", [ORDER_HONEYPOT => 'spam'] + $post);
    ok($c === 422,                                                     'http: campul-capcana completat => respins');
    [$c, $b] = $req("$base/cerere-retur.html", ['acord' => ''] + $post);
    ok($c === 422 && str_contains($b, 'id="err-acord"'),             'http: fara acord => eroare pe bifa');
    [$c, , $loc] = $req("$base/cerere-retur.html", $post);
    [$c2, , $loc2] = $req("$base/cerere-retur.html", $post); // dublu click
    ok($c === 303 && preg_match('#^/cerere-retur\.html\?trimis=RET-\d{4}-\d{6}$#', $loc) === 1, 'http: cerere valida => 303 spre confirmare');
    ok($c2 === 303 && $loc2 === $loc,                                  'http: a doua trimitere => aceeasi cerere');
    $nr = substr($loc, strlen('/cerere-retur.html?trimis='));
    $saved = $pdo->query("SELECT * FROM return_requests WHERE request_number = " . $pdo->quote($nr))->fetch(PDO::FETCH_ASSOC);
    if ($saved) { $created[] = (int) $saved['id']; }
    ok($saved && $saved['iban'] === 'RO49AAAA1B31007593840000' && $saved['email_admin_at'] && $saved['email_client_at'], 'http: salvata, ambele emailuri marcate trimise');
    [$c, $b] = $req("$base$loc");
    ok($c === 200 && str_contains($b, $nr) && !str_contains($b, 'retur-http@exemplu.ro'), 'http: pagina de confirmare arata doar numarul cererii');
    $client = mailpitSearch('to:retur-http@exemplu.ro');
    ok(count($client) === 1 && str_contains($client[0]['Subject'], $nr), 'mailpit: o singura confirmare la client');
    $shop = array_values(array_filter(mailpitSearch('to:' . ORDER_NOTIFY_TO), fn ($x) => str_contains($x['Subject'], $nr)));
    ok(count($shop) === 1 && ($shop[0]['ReplyTo'][0]['Address'] ?? '') === 'retur-http@exemplu.ro', 'mailpit: o notificare la magazin, „Raspunde" merge la client');
    ok(!array_filter(array_merge($client, $shop), fn ($x) => str_contains(strtolower($x['To'][0]['Address'] ?? ''), 'mugurel-bricolaj.ro')), 'mailpit: nimic catre @mugurel-bricolaj.ro local');
} finally {
    if ($created) { $pdo->exec('DELETE FROM return_requests WHERE id IN (' . implode(',', array_map('intval', $created)) . ')'); }
    mailpitDelete('to:retur-http@exemplu.ro');
}

// ── livrare doar in Dolj (comanda) ──
$base = ['nume' => 'Test Livrare', 'telefon' => '0749130565', 'email' => 'livrare@exemplu.ro', 'localitate' => 'Craiova',
         'adresa' => 'Str. Test 1', 'acord' => '1'];
ok(isset(orderFormValidate(['judet' => 'Olt', 'plata' => 'ramburs'] + $base)['errors']['judet']), 'livrare: judetul Olt la ramburs => eroare');
ok(str_contains(orderFormValidate(['judet' => 'Olt', 'plata' => 'transfer'] + $base)['errors']['judet'] ?? '', 'Dolj'), 'livrare: mesajul spune ca livram doar in Dolj');
ok(!isset(orderFormValidate(['judet' => 'Dolj', 'plata' => 'ramburs'] + $base)['errors']['judet']), 'livrare: Dolj => acceptat');
ok(!isset(orderFormValidate(['judet' => '', 'plata' => 'ridicare'] + $base)['errors']['judet']), 'livrare: ridicare din magazin => fara judet');

// ── IP-ul din comenzi se sterge dupa 6 luni ──
$t = orderNewFormToken();
$pdo->prepare("INSERT INTO orders (public_token, form_token, content_hash, tip, nume, telefon, email, plata, judet, localitate, adresa,
               total_bani, ip, user_agent, created_at) VALUES (?, ?, ?, 'pf', 'Vechi', '+40749130565', 'vechi@exemplu.ro', 'ramburs',
               'Dolj', 'Dabuleni', 'Str. 1', 100, '9.9.9.9', 'UA vechi', DATE_SUB(NOW(), INTERVAL 7 MONTH))")
    ->execute([bin2hex(random_bytes(24)), $t, bin2hex(random_bytes(16))]);
$pdo->prepare("INSERT INTO orders (public_token, form_token, content_hash, tip, nume, telefon, email, plata, judet, localitate, adresa,
               total_bani, ip, user_agent, created_at) VALUES (?, ?, ?, 'pf', 'Nou', '+40749130565', 'vechi@exemplu.ro', 'ramburs',
               'Dolj', 'Dabuleni', 'Str. 1', 100, '8.8.8.8', 'UA nou', DATE_SUB(NOW(), INTERVAL 5 MONTH))")
    ->execute([bin2hex(random_bytes(24)), orderNewFormToken(), bin2hex(random_bytes(16))]);
try {
    orderPurgeTechnicalData($pdo);
    $rows = $pdo->query("SELECT nume, ip, user_agent FROM orders WHERE email = 'vechi@exemplu.ro' ORDER BY nume")->fetchAll(PDO::FETCH_ASSOC);
    ok($rows[1]['nume'] === 'Vechi' && $rows[1]['ip'] === '' && $rows[1]['user_agent'] === '', 'IP: comanda de 7 luni => IP si browser sterse');
    ok($rows[0]['nume'] === 'Nou' && $rows[0]['ip'] === '8.8.8.8',     'IP: comanda de 5 luni => pastrat');
} finally {
    $pdo->exec("DELETE FROM orders WHERE email = 'vechi@exemplu.ro'");
}

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
