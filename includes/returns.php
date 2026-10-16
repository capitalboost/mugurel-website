<?php
/**
 * Cererile de retur (formularul /cerere-retur.html, 16 oct 2026): validare, salvare, emailuri.
 *
 * Fluxul: clientul completeaza formularul => cererea se salveaza (return_requests) => email la magazin
 * (ORDER_NOTIFY_TO: pe productie comenzi@, redirectionat spre dunlite@gmail.com; „Raspunde" merge la client)
 * + confirmare la client cu numarul cererii. Curierul ridica produsele; transportul il plateste magazinul.
 * Protectiile sunt aceleasi ca la comanda (CSRF, camp-capcana, timp minim, limita pe IP) — includes/order_security.php.
 */
require_once __DIR__ . '/order_form.php';   // orderText(), orderPhone(), ORDER_JUDETE
require_once __DIR__ . '/order_mail.php';   // mh(), SHOP_FOOTER, SHOP_PHONE
require_once __DIR__ . '/mailer.php';

const RETURN_TIPURI = [
    'retragere' => 'Renunț la produs (retragere în 14 zile)',
    'neconform' => 'Produsul este defect sau nu corespunde comenzii',
];
const RETURN_MAX = ['order_ref' => 40, 'nume' => 120, 'telefon' => 30, 'email' => 160, 'localitate' => 80,
                    'adresa' => 200, 'produse' => 2000, 'motiv' => 2000, 'iban' => 40, 'data_primirii' => 10];

/** IBAN romanesc (RO + 2 cifre de control + 4 litere banca + 16 caractere) cu cifra de control corecta, fara spatii. */
function returnIban(string $raw): ?string
{
    $iban = strtoupper((string) preg_replace('/\s+/', '', $raw));
    if (!preg_match('/^RO\d{2}[A-Z]{4}[A-Z0-9]{16}$/', $iban)) { return null; }
    $num = '';
    foreach (str_split(substr($iban, 4) . substr($iban, 0, 4)) as $ch) { $num .= ctype_alpha($ch) ? (string) (ord($ch) - 55) : $ch; }
    $mod = 0;
    foreach (str_split($num, 7) as $chunk) { $mod = (int) (($mod . $chunk) % 97); }
    return $mod === 1 ? $iban : null;
}

/** @return array{data: array<string,string>, errors: array<string,string>} */
function returnFormValidate(array $in): array
{
    $d = [];
    foreach (RETURN_MAX as $k => $max) { $d[$k] = orderText($in[$k] ?? '', $max, in_array($k, ['produse', 'motiv'], true)); }
    $d['tip'] = is_string($in['tip'] ?? null) && isset(RETURN_TIPURI[$in['tip']]) ? $in['tip'] : '';
    $d['judet'] = is_string($in['judet'] ?? null) && in_array($in['judet'], ORDER_JUDETE, true) ? $in['judet'] : '';
    $d['acord'] = ($in['acord'] ?? null) === '1' ? '1' : '';
    $e = [];

    if ($d['tip'] === '') { $e['tip'] = 'Alege ce dorești: retragere sau produs defect/neconform.'; }
    if ($d['order_ref'] === '') { $e['order_ref'] = 'Scrie numărul comenzii (ex. MUG-2026-000123) sau al facturii.'; }
    if (mb_strlen($d['nume']) < 3) { $e['nume'] = 'Scrie numele complet.'; }
    $tel = orderPhone($d['telefon']);
    if ($tel === null) { $e['telefon'] = 'Scrie un număr de telefon valid (ex. 0749 130 565).'; } else { $d['telefon'] = $tel; }
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) { $e['email'] = 'Scrie o adresă de email validă.'; }
    if ($d['judet'] === '') { $e['judet'] = 'Alege județul.'; }
    if ($d['localitate'] === '') { $e['localitate'] = 'Scrie localitatea.'; }
    if (mb_strlen($d['adresa']) < 5) { $e['adresa'] = 'Scrie adresa de unde ridică curierul produsele.'; }
    if (mb_strlen($d['produse']) < 3) { $e['produse'] = 'Scrie ce produse returnezi și cantitatea.'; }
    if ($d['tip'] === 'neconform' && mb_strlen($d['motiv']) < 5) { $e['motiv'] = 'Descrie pe scurt problema produsului.'; }
    if ($d['data_primirii'] !== '') {
        $dt = DateTime::createFromFormat('!Y-m-d', $d['data_primirii']);
        if (!$dt || $dt->format('Y-m-d') !== $d['data_primirii'] || $dt > new DateTime('tomorrow')) {
            $e['data_primirii'] = 'Data primirii nu este validă.';
        }
    }
    if ($d['iban'] !== '') {
        $iban = returnIban($d['iban']);
        if ($iban === null) { $e['iban'] = 'IBAN-ul nu pare corect (RO + 22 de caractere). Îl poți lăsa gol.'; } else { $d['iban'] = $iban; }
    }
    if ($d['acord'] !== '1') { $e['acord'] = 'Ca să trimiți cererea, trebuie să accepți prelucrarea datelor pentru retur.'; }
    return ['data' => $d, 'errors' => $e];
}

/**
 * Salveaza cererea. A doua trimitere cu acelasi token (dublu click, refresh) intoarce cererea existenta.
 * @return array{id:int, number:string, duplicate:bool}
 */
function returnCreate(PDO $pdo, array $d, string $formToken, string $ip = '', string $userAgent = ''): array
{
    $pdo->beginTransaction();
    try {
        $find = $pdo->prepare('SELECT id, request_number FROM return_requests WHERE form_token = ?');
        $find->execute([$formToken]);
        if ($row = $find->fetch(PDO::FETCH_ASSOC)) {
            $pdo->commit();
            return ['id' => (int) $row['id'], 'number' => $row['request_number'], 'duplicate' => true];
        }
        $pdo->prepare('INSERT INTO return_requests (form_token, tip, order_ref, nume, telefon, email, judet, localitate, adresa,
                produse, data_primirii, motiv, iban, ip, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$formToken, $d['tip'], $d['order_ref'], $d['nume'], $d['telefon'], $d['email'], $d['judet'], $d['localitate'],
                $d['adresa'], $d['produse'], $d['data_primirii'] ?: null, $d['motiv'] ?: null, $d['iban'] ?: null,
                mb_substr($ip, 0, 45), mb_substr($userAgent, 0, 255)]);
        $id = (int) $pdo->lastInsertId();
        $number = sprintf('RET-%s-%06d', date('Y'), $id);
        $pdo->prepare('UPDATE return_requests SET request_number = ? WHERE id = ?')->execute([$number, $id]);
        $pdo->commit();
        return ['id' => $id, 'number' => $number, 'duplicate' => false];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function returnById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM return_requests WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Randurile cererii, in ordinea din email (eticheta => valoare). */
function returnDetails(array $r): array
{
    return [
        'Tip cerere'         => RETURN_TIPURI[$r['tip']] ?? $r['tip'],
        'Comanda / factura'  => $r['order_ref'],
        'Nume'               => $r['nume'],
        'Telefon'            => $r['telefon'],
        'Email'              => $r['email'],
        'Ridicare de la'     => trim($r['adresa'] . ', ' . $r['localitate'] . ', jud. ' . $r['judet'], ', '),
        'Produse'            => $r['produse'],
        'Data primirii'      => $r['data_primirii'] ? date('d.m.Y', strtotime($r['data_primirii'])) : '—',
        'Motiv / problema'   => $r['motiv'] ?: '—',
        'IBAN pentru rambursare' => $r['iban'] ?: '— (aceeași metodă ca la plată)',
    ];
}

/** Emailul pentru magazin. „Raspunde" merge direct la client. */
function returnAdminEmail(array $r): array
{
    $rows = '';
    $text = '';
    foreach (returnDetails($r) as $k => $v) {
        $rows .= '<tr><th style="text-align:left;padding:4px 12px 4px 0;vertical-align:top;color:#555">' . mh($k)
               . '</th><td style="padding:4px 0">' . nl2br(mh($v)) . '</td></tr>';
        $text .= "$k: $v\n";
    }
    $subject = 'Cerere de retur ' . $r['request_number'] . ' — ' . $r['nume'] . ' (' . ($r['tip'] === 'neconform' ? 'produs neconform' : 'retragere') . ')';
    $html = '<div style="font-family:Arial,sans-serif;color:#222;max-width:680px">'
          . '<h2 style="color:#06363A;margin:0 0 8px">Cerere de retur ' . mh($r['request_number']) . '</h2>'
          . '<p style="margin:0 0 12px">Trimisă de pe site pe ' . mh(date('d.m.Y H:i', strtotime($r['created_at']))) . '. '
          . 'Contactează clientul și programează ridicarea prin curier (transportul îl plătește magazinul). '
          . 'Rambursarea: în cel mult 14 zile de la această cerere.</p>'
          . '<table style="border-collapse:collapse;font-size:14px">' . $rows . '</table></div>';
    return ['subject' => $subject, 'html' => $html, 'text' => "Cerere de retur {$r['request_number']}\n\n$text"];
}

/** Confirmarea pentru client. */
function returnClientEmail(array $r): array
{
    $next = $r['tip'] === 'neconform'
        ? 'Te contactăm în zilele lucrătoare următoare ca să stabilim ridicarea produsului prin curier și soluția: înlocuire, reparare sau banii înapoi.'
        : 'Te contactăm în zilele lucrătoare următoare ca să stabilim ridicarea produselor prin curier. Transportul pentru retur îl plătim noi. Îți returnăm banii — inclusiv costul livrării inițiale — în cel mult 14 zile de la data acestei cereri.';
    $subject = 'Am primit cererea de retur ' . $r['request_number'] . ' — Mugurel';
    $html = '<div style="font-family:Arial,sans-serif;color:#222;max-width:640px">'
          . '<h2 style="color:#06363A;margin:0 0 8px">Am primit cererea ta de retur</h2>'
          . '<p style="margin:0 0 6px">Bună ziua, ' . mh($r['nume']) . ',</p>'
          . '<p style="margin:0 0 12px">Numărul cererii: <b>' . mh($r['request_number']) . '</b> (comanda/factura ' . mh($r['order_ref']) . '). ' . mh($next) . '</p>'
          . '<p style="margin:0 0 4px"><b>Produse:</b><br>' . nl2br(mh($r['produse'])) . '</p>'
          . '<p style="margin:12px 0 4px"><b>Ridicare de la:</b> ' . mh(trim($r['adresa'] . ', ' . $r['localitate'] . ', jud. ' . $r['judet'], ', ')) . '</p>'
          . '<p style="margin-top:16px">Ai o întrebare? Sună-ne la <a href="tel:+40749130565">' . SHOP_PHONE . '</a> (L–V 8:00–18:00) sau răspunde la acest email.</p>'
          . '<p style="margin-top:24px;font-size:12px;color:#888">' . mh(SHOP_FOOTER) . '</p></div>';
    $text = "Am primit cererea ta de retur\n\nBună ziua, {$r['nume']},\n\nNumărul cererii: {$r['request_number']} (comanda/factura {$r['order_ref']}). $next\n\n"
          . "Produse:\n{$r['produse']}\n\nRidicare de la: " . trim($r['adresa'] . ', ' . $r['localitate'] . ', jud. ' . $r['judet'], ', ')
          . "\n\nTelefon: " . SHOP_PHONE . " (L–V 8:00–18:00)\n\n" . SHOP_FOOTER . "\n";
    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

/** Trimite ambele emailuri si noteaza ce a plecat. Un email esuat nu anuleaza cererea (e salvata). */
function returnNotify(PDO $pdo, array $r, array $smtp = []): void
{
    $a = returnAdminEmail($r);
    if (mailSend(ORDER_NOTIFY_TO, $a['subject'], $a['html'], $a['text'], $r['email'], $smtp)) {
        $pdo->prepare('UPDATE return_requests SET email_admin_at = NOW() WHERE id = ?')->execute([$r['id']]);
    } else {
        error_log("returnNotify: emailul catre magazin pentru {$r['request_number']} NU a plecat");
    }
    $c = returnClientEmail($r);
    if (mailSend($r['email'], $c['subject'], $c['html'], $c['text'], ORDER_NOTIFY_TO, $smtp)) {
        $pdo->prepare('UPDATE return_requests SET email_client_at = NOW() WHERE id = ?')->execute([$r['id']]);
    } else {
        error_log("returnNotify: confirmarea pentru {$r['request_number']} NU a plecat");
    }
}
