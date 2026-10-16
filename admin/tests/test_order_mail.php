<?php
// Teste pentru emailurile comenzii (T12). Folosesc baza LOCALA si Mailpit (http://127.0.0.1:8025).
// Golesc cutia Mailpit la inceput — e doar sandbox-ul local de test.
require_once __DIR__ . '/../../includes/order_mail.php';
require_once __DIR__ . '/../../includes/orders.php';

require_once __DIR__ . '/catalog_fixture.php'; // fx('A') = produsele de test (dev/fixtures/cos_test.sql)
$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass,$fail; if($c){$pass++;echo"  ✓ $m\n";}else{$fail++;echo"  ✗ $m\n";} }

const MAILPIT = 'http://127.0.0.1:8025/api/v1';
function mailpit(string $method, string $path): array {
    $ch = curl_init(MAILPIT . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 10]);
    $body = curl_exec($ch);
    curl_close($ch);
    return is_string($body) ? (json_decode($body, true) ?? []) : [];
}
function mailpitMessages(): array { return mailpit('GET', '/messages?limit=200')['messages'] ?? []; }

// ── garda pe destinatari ──
ok(APP_ENV === 'local',                                                    'mediu: local (implicit)');
ok(ORDER_NOTIFY_TO === 'lucian@capitalboost.ro',                         'notificarile merg la lucian@capitalboost.ro in test');
ok(mailRecipientAllowed('lucian@capitalboost.ro'),                       'garda: adresa de test permisa');
ok(!mailRecipientAllowed('comenzi@mugurel-bricolaj.ro'),                 'garda: comenzi@mugurel-bricolaj.ro BLOCATA local');
ok(!mailRecipientAllowed('Comenzi@Mugurel-Bricolaj.RO'),                 'garda: blocata si cu majuscule');
ok(!mailRecipientAllowed('x@mail.mugurel-bricolaj.ro'),                  'garda: blocat si subdomeniul');
ok(mailRecipientAllowed('x@notmugurel-bricolaj.ro'),                     'garda: alt domeniu care doar seamana => permis');
ok(mailRecipientAllowed('comenzi@mugurel-bricolaj.ro', 'production'),    'garda: in productie adresa magazinului e permisa');
ok(!mailRecipientAllowed('nu-e-email'),                                  'garda: adresa invalida => blocata');

mailpit('DELETE', '/messages');
ok(!mailSend('comenzi@mugurel-bricolaj.ro', 'Test', '<p>x</p>', 'x'),   'trimitere blocata intoarce false');
ok(count(mailpitMessages()) === 0,                                       'trimitere blocata: nimic nu ajunge nici macar in Mailpit');

// ── continutul emailului (fara baza) ──
$fake = ['id' => 0, 'order_number' => 'MUG-2026-000099', 'created_at' => '2026-10-13 15:00:00', 'tip' => 'pj',
    'nume' => 'Ion <script>alert(1)</script>', 'telefon' => '+40749130565', 'email' => 'client@exemplu.ro',
    'plata' => 'transfer', 'judet' => 'Dolj', 'localitate' => 'Dabuleni', 'adresa' => 'Str. A & B 1',
    'mentiuni' => "Rand 1\n<b>Rand 2</b>", 'firma' => 'Firma <Test> SRL', 'cui' => 'RO14399840', 'reg_com' => 'J16/123/2020',
    'sediu' => 'Sediu 1', 'total_bani' => 20123,
    'items' => [['name' => 'Jgheab <Ø125>', 'unit' => 'buc', 'qty' => '2.00', 'price_bani' => 8500, 'line_bani' => 17000],
                ['name' => 'Tablou', 'unit' => 'ml', 'qty' => '2.50', 'price_bani' => 1249, 'line_bani' => 3123]]];
$m = orderAdminEmail($fake);
ok($m['subject'] === 'Comanda noua MUG-2026-000099 — 201,23 lei — Firma <Test> SRL', 'subiect: numar, total si firma');
ok(!str_contains($m['html'], '<script>') && str_contains($m['html'], 'Ion &lt;script&gt;'), 'html: numele clientului escapat (fara XSS in email)');
ok(str_contains($m['html'], 'Firma &lt;Test&gt; SRL') && str_contains($m['html'], 'Jgheab &lt;Ø125&gt;'), 'html: firma si produsul escapate');
ok(str_contains($m['html'], 'Rand 1<br />') && str_contains($m['html'], '&lt;b&gt;Rand 2'), 'html: mentiunile pe randuri, escapate');
foreach (['RO14399840', 'J16/123/2020', 'Persoana de contact', 'Transfer bancar', 'Str. A &amp; B 1, Dabuleni, jud. Dolj',
          '170,00 lei', '31,23 lei', '201,23 lei', '2 buc', '2,5 ml', 'href="tel:+40749130565"'] as $needle) {
    ok(str_contains($m['html'], $needle), "html contine: $needle");
}
ok(str_contains($m['text'], '- Tablou — 2,5 ml × 12,49 lei = 31,23 lei') && str_contains($m['text'], 'TOTAL (cu TVA): 201,23 lei'), 'text: varianta fara HTML cu produse si total');
$pf = orderAdminEmail(['tip' => 'pf', 'plata' => 'ridicare', 'firma' => '', 'cui' => '', 'reg_com' => '', 'sediu' => '',
                       'nume' => 'Maria', 'mentiuni' => null] + $fake);
ok(str_contains($pf['html'], 'Ridicare din magazin') && !str_contains($pf['html'], 'CUI') && str_contains($pf['subject'], '— Maria'), 'PF + ridicare: fara date de firma, livrare = ridicare');

// ── integrare cu baza si Mailpit ──
$pdo = Database::get();
$data = orderFormValidate(['nume' => 'Mail Test', 'telefon' => '0749130565', 'email' => 'mailtest@exemplu.ro',
    'judet' => 'Dolj', 'localitate' => 'Dabuleni', 'adresa' => 'Str. Mail 1', 'plata' => 'ramburs', 'acord' => '1'])['data'];
$cart = priceCart([['id' => fx('A'), 'qty' => 1.0]], cartProducts([fx('A')]));
try {
    mailpit('DELETE', '/messages');
    $o = orderCreate($pdo, $data, $cart, orderNewFormToken());
    $saved = orderByToken($pdo, $o['token']);
    ok(orderNotifyAdmin($pdo, $saved),                                   'notificare: trimisa');
    $msgs = mailpitMessages();
    ok(count($msgs) === 1,                                               'Mailpit: exact un mesaj');
    $to = array_column($msgs[0]['To'] ?? [], 'Address');
    ok($to === ['lucian@capitalboost.ro'],                               'Mailpit: destinatar lucian@capitalboost.ro');
    ok(str_contains($msgs[0]['Subject'] ?? '', $o['number']),           'Mailpit: subiectul are numarul comenzii');
    $full = mailpit('GET', '/message/' . ($msgs[0]['ID'] ?? ''));
    ok(array_column($full['ReplyTo'] ?? [], 'Address') === ['mailtest@exemplu.ro'], 'Mailpit: „Raspunde" merge direct la client');
    ok(($full['From']['Address'] ?? '') === MAIL_FROM,                   'Mailpit: expeditor ' . MAIL_FROM);
    ok(str_contains($full['HTML'] ?? '', '25,24 lei') && str_contains($full['Text'] ?? '', 'TOTAL (cu TVA): 25,24 lei'), 'Mailpit: HTML si text complete');
    $at = $pdo->prepare('SELECT email_admin_at FROM orders WHERE id = ?'); $at->execute([$o['id']]);
    ok($at->fetchColumn() !== null,                                       'comanda: momentul notificarii salvat');

    // SMTP indisponibil: esec curat, comanda ramane
    $o2 = orderCreate($pdo, ['telefon' => '+40749000001'] + $data, $cart, orderNewFormToken());
    $saved2 = orderByToken($pdo, $o2['token']);
    $t0 = microtime(true);
    ok(orderNotifyAdmin($pdo, $saved2, ['port' => 1, 'timeout' => 3]) === false, 'SMTP oprit: trimiterea intoarce false, fara exceptie');
    ok(microtime(true) - $t0 < 10,                                        'SMTP oprit: nu blocheaza pagina (timeout scurt)');
    $at->execute([$o2['id']]);
    ok($at->fetchColumn() === null,                                       'SMTP oprit: email_admin_at ramane gol (se vede ce n-a plecat)');
    ok(orderByToken($pdo, $o2['token']) !== null,                         'SMTP oprit: comanda ramane salvata');

    // HTTP cap-coada: comanda din formular => un singur email, chiar si la retrimitere
    mailpit('DELETE', '/messages');
    $base = getenv('BASE') ?: 'http://localhost:8080';
    $jar = tempnam(sys_get_temp_dir(), 'cj');
    $get = curl_init($base . '/comanda.html');
    curl_setopt_array($get, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    $page = (string) curl_exec($get); unset($get); // in PHP 8 cookie-urile se scriu in fisier abia la distrugerea handle-ului
    preg_match('/name="csrf" value="([0-9a-f]{64})"/', $page, $c1);
    preg_match('/name="idem" value="([0-9a-f]{32})"/', $page, $c2);
    $post = ['csrf' => $c1[1] ?? '', 'idem' => $c2[1] ?? '', 'nume' => 'Mail HTTP', 'telefon' => '0749 130 565',
             'email' => 'mailtest@exemplu.ro', 'judet' => 'Dolj', 'localitate' => 'Dabuleni', 'adresa' => 'Str. HTTP 2',
             'plata' => 'ramburs', 'acord' => '1', 'cos' => json_encode([['id' => fx('EL'), 'qty' => 2]])];
    for ($i = 0; $i < 2; $i++) {
        $ch = curl_init($base . '/comanda.html');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
                                CURLOPT_POSTFIELDS => http_build_query($post)]);
        curl_exec($ch);
        $codes[] = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
    }
    ok($codes === [303, 303],                                             'http: doua trimiteri => 303, 303 (primit: ' . json_encode($codes) . ')');
    $msgs = mailpitMessages();
    $all = array_merge(...array_map(fn ($x) => array_column($x['To'] ?? [], 'Address'), $msgs ?: [['To' => []]]));
    sort($all);
    ok(count($msgs) === 2 && $all === ['lucian@capitalboost.ro', 'mailtest@exemplu.ro'], 'http: exact doua emailuri — notificarea interna + confirmarea clientului, nu patru');
    ok(!array_filter($all, fn ($a) => str_ends_with(strtolower($a), 'mugurel-bricolaj.ro')), 'http: niciun email catre @mugurel-bricolaj.ro');
    $admin = array_values(array_filter($msgs, fn ($x) => ($x['To'][0]['Address'] ?? '') === 'lucian@capitalboost.ro'));
    ok(str_contains($admin[0]['Subject'] ?? '', '746,62 lei'),           'http: notificarea interna are totalul 746,62 lei (2 × 373,31)');

    // ── T13: confirmarea pentru client ──
    $client = array_values(array_filter($msgs, fn ($x) => ($x['To'][0]['Address'] ?? '') === 'mailtest@exemplu.ro'));
    $cm = mailpit('GET', '/message/' . ($client[0]['ID'] ?? ''));
    ok(str_starts_with($cm['Subject'] ?? '', 'Am primit comanda ta MUG-'),  'client: subiect „Am primit comanda ta MUG-…"');
    ok(array_column($cm['ReplyTo'] ?? [], 'Address') === [ORDER_NOTIFY_TO], 'client: „Raspunde" merge la magazin (in test: ' . ORDER_NOTIFY_TO . ')');
    ok(str_contains($cm['HTML'] ?? '', '746,62 lei') && str_contains($cm['HTML'] ?? '', 'Ramburs la livrare'), 'client: produse, total si metoda de plata');
    ok(preg_match('#/comanda-trimisa\.html\?t=[0-9a-f]{48}#', $cm['HTML'] ?? '') === 1, 'client: legatura spre pagina comenzii, cu token');
    ok(!str_contains($cm['HTML'] ?? '', '127.0.0.1') && !str_contains($cm['HTML'] ?? '', '::1') && !str_contains($cm['HTML'] ?? '', 'curl'), 'client: fara date interne (IP, browser)');
    ok(str_contains($cm['Text'] ?? '', 'DUNLITE COM SRL') && str_contains($cm['Text'] ?? '', '0749 130 565'), 'client: varianta text cu datele firmei si telefonul');
    $ids = $pdo->query("SELECT email_admin_at IS NOT NULL a, email_client_at IS NOT NULL c FROM orders WHERE email = 'mailtest@exemplu.ro' AND nume = 'Mail HTTP'")->fetchAll(PDO::FETCH_ASSOC);
    ok(count($ids) === 1 && (int) $ids[0]['a'] === 1 && (int) $ids[0]['c'] === 1, 'client: comanda noteaza ambele emailuri trimise');

    // continutul pentru ridicare din magazin si escaparea
    $rid = orderClientEmail(['plata' => 'ridicare', 'public_token' => str_repeat('a', 48), 'nume' => 'Ana <b>X</b>'] + $fake);
    ok(str_contains($rid['html'], 'Strada Caracal 52') && !str_contains($rid['html'], 'Transportul nu este inclus'), 'client ridicare: adresa magazinului, fara mesaj de transport');
    ok(str_contains($rid['html'], 'Ana &lt;b&gt;X&lt;/b&gt;') && !str_contains($rid['html'], '<b>X</b>'), 'client: numele escapat');
    $tr = orderClientEmail(['plata' => 'transfer', 'public_token' => str_repeat('a', 48)] + $fake);
    ok(str_contains($tr['html'], 'factura proforma') && str_contains($tr['html'], 'Transportul nu este inclus'), 'client transfer: proforma + mesaj de transport');

    // SMTP oprit la client: comanda ramane, email_client_at gol
    $o3 = orderCreate($pdo, ['telefon' => '+40749000003'] + $data, $cart, orderNewFormToken());
    $s3 = orderByToken($pdo, $o3['token']);
    ok(orderNotifyClient($pdo, $s3, ['port' => 1, 'timeout' => 3]) === false && orderByToken($pdo, $o3['token'])['email_client_at'] === null,
       'client, SMTP oprit: false, comanda ramane, email_client_at gol');
} finally {
    $pdo->exec("DELETE FROM orders WHERE email = 'mailtest@exemplu.ro'");
}

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
