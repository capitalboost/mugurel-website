<?php
/**
 * „Ai observat o greșeală la acest produs?" — rapoartele vizitatorilor (18 oct 2026), formular pe /raporteaza-problema.html.
 * Raportul se salvează (tabelul product_reports, migrarea 015) și pleacă pe email la magazin (ORDER_NOTIFY_TO)
 * și în copie la REPORT_COPY_TO. Vizitatorul nu primește email (nu trimitem mesaje către adrese scrise de oricine).
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/order_form.php'; // orderText()

const REPORT_TIPURI = [
    'pret'      => 'Prețul pare greșit',
    'poza'      => 'Poza nu corespunde produsului',
    'descriere' => 'Descrierea sau specificațiile sunt greșite',
    'fisa'      => 'Fișa tehnică nu se deschide',
    'altceva'   => 'Altceva',
];

/** @return array{data: array, errors: array<string,string>} */
function reportFormValidate(array $in): array
{
    $d = [
        'tip'     => is_string($in['tip'] ?? null) && isset(REPORT_TIPURI[$in['tip']]) ? $in['tip'] : '',
        'detalii' => orderText($in['detalii'] ?? '', 1000, true),
        'email'   => orderText($in['email'] ?? '', 160),
    ];
    $e = [];
    if ($d['tip'] === '') { $e['tip'] = 'Alege ce problemă ai observat.'; }
    if ($d['tip'] === 'altceva' && mb_strlen($d['detalii']) < 5) { $e['detalii'] = 'Scrie pe scurt ce ai observat.'; }
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) { $e['email'] = 'Adresa de email nu pare corectă (sau las-o goală).'; }
    return ['data' => $d, 'errors' => $e];
}

/** Salvează raportul. @return int id */
function reportCreate(PDO $pdo, array $product, array $d, string $ip = ''): int
{
    $pdo->prepare('INSERT INTO product_reports (product_id, product_name, tip, detalii, email, ip) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([(int) $product['id'], mb_substr((string) $product['name'], 0, 255), $d['tip'], $d['detalii'] ?: null, $d['email'] ?: null, mb_substr($ip, 0, 45)]);
    return (int) $pdo->lastInsertId();
}

/** Emailul către magazin (și copia). @return array{subject:string, html:string, text:string} */
function reportEmail(array $product, array $d, int $id): array
{
    $url = SITE_URL . '/produs/' . rawurlencode((string) $product['slug']) . '.html';
    $tip = REPORT_TIPURI[$d['tip']] ?? $d['tip'];
    $subject = 'Problemă raportată la produs: ' . mb_substr((string) $product['name'], 0, 80) . ' (' . $tip . ')';
    $h = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $rows = [
        'Produs'     => '<a href="' . $h($url) . '">' . $h($product['name']) . '</a>',
        'Cod'        => $h($product['sku'] ?: $product['barcode']),
        'Problema'   => $h($tip),
        'Detalii'    => $d['detalii'] !== '' ? nl2br($h($d['detalii'])) : '<em>—</em>',
        'Email'      => $d['email'] !== '' ? $h($d['email']) . ' (vrea răspuns)' : '<em>nu a lăsat</em>',
        'Raport nr.' => (string) $id,
    ];
    $html = '<div style="font-family:Arial,sans-serif;color:#222;max-width:640px"><h2 style="color:#06363A;margin:0 0 12px">Un vizitator a raportat o problemă la un produs</h2><table style="border-collapse:collapse;font-size:14px">';
    foreach ($rows as $k => $v) { $html .= '<tr><td style="padding:4px 12px 4px 0;color:#666;vertical-align:top">' . $k . '</td><td style="padding:4px 0">' . $v . '</td></tr>'; }
    $html .= '</table><p style="margin-top:16px;font-size:13px;color:#666">Corectează produsul din panoul de administrare (caută după cod).</p></div>';
    $text = "Un vizitator a raportat o problemă la un produs\n\nProdus: {$product['name']}\n$url\nCod: " . ($product['sku'] ?: $product['barcode'])
          . "\nProblema: $tip\nDetalii: " . ($d['detalii'] !== '' ? $d['detalii'] : '—') . "\nEmail: " . ($d['email'] !== '' ? $d['email'] : 'nu a lăsat')
          . "\nRaport nr. $id\n";
    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

/** Trimite raportul la magazin și copia. Un email eșuat nu pierde raportul (e salvat). */
function reportNotify(PDO $pdo, array $product, array $d, int $id, array $smtp = []): void
{
    $m = reportEmail($product, $d, $id);
    $reply = $d['email'] !== '' ? $d['email'] : null;
    $ok = mailSend(ORDER_NOTIFY_TO, $m['subject'], $m['html'], $m['text'], $reply, $smtp);
    if (REPORT_COPY_TO !== '' && strcasecmp(REPORT_COPY_TO, ORDER_NOTIFY_TO) !== 0) {
        $ok = mailSend(REPORT_COPY_TO, $m['subject'], $m['html'], $m['text'], $reply, $smtp) || $ok;
    }
    if ($ok) {
        $pdo->prepare('UPDATE product_reports SET email_sent_at = NOW() WHERE id = ?')->execute([$id]);
    } else {
        error_log("reportNotify: emailul pentru raportul $id NU a plecat");
    }
}
