<?php
/**
 * Emailurile unei comenzi (T12: notificarea interna; T13: confirmarea pentru client).
 * Continutul vine din comanda salvata (orderByToken), nu din formular.
 */
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/order_form.php';
require_once __DIR__ . '/cart.php'; // formatLei()

function mh(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function orderQtyLabel(array $item): string
{
    $q = rtrim(rtrim(number_format((float) $item['qty'], 2, ',', ''), '0'), ',');
    return $q . ($item['unit'] ? ' ' . $item['unit'] : '');
}

/** Tabelul de produse, comun ambelor emailuri. */
function orderItemsHtml(array $order): string
{
    $rows = '';
    foreach ($order['items'] as $it) {
        $rows .= '<tr><td style="padding:6px 8px;border-bottom:1px solid #e5e5e5">' . mh($it['name']) . '</td>'
               . '<td style="padding:6px 8px;border-bottom:1px solid #e5e5e5;white-space:nowrap">' . mh(orderQtyLabel($it)) . '</td>'
               . '<td style="padding:6px 8px;border-bottom:1px solid #e5e5e5;white-space:nowrap;text-align:right">' . mh(formatLei((int) $it['price_bani'])) . '</td>'
               . '<td style="padding:6px 8px;border-bottom:1px solid #e5e5e5;white-space:nowrap;text-align:right"><b>' . mh(formatLei((int) $it['line_bani'])) . '</b></td></tr>';
    }
    return '<table style="border-collapse:collapse;width:100%;font-size:14px">'
         . '<tr style="background:#f2f6f6"><th align="left" style="padding:6px 8px">Produs</th><th align="left" style="padding:6px 8px">Cantitate</th>'
         . '<th align="right" style="padding:6px 8px">Pret</th><th align="right" style="padding:6px 8px">Subtotal</th></tr>'
         . $rows
         . '<tr><td colspan="3" style="padding:8px;text-align:right"><b>Total (cu TVA)</b></td>'
         . '<td style="padding:8px;text-align:right;white-space:nowrap"><b>' . mh(formatLei((int) $order['total_bani'])) . '</b></td></tr></table>';
}

function orderItemsText(array $order): string
{
    $out = '';
    foreach ($order['items'] as $it) {
        $out .= '- ' . $it['name'] . ' — ' . orderQtyLabel($it) . ' × ' . formatLei((int) $it['price_bani'])
              . ' = ' . formatLei((int) $it['line_bani']) . "\n";
    }
    return $out . 'TOTAL (cu TVA): ' . formatLei((int) $order['total_bani']) . "\n";
}

/** Randurile „eticheta: valoare" cu datele clientului, livrarea si plata (doar cele completate). */
function orderDetails(array $order): array
{
    $d = [
        'Client'        => ORDER_TIPURI[$order['tip']] ?? $order['tip'],
        'Firma'         => $order['firma'],
        'CUI'           => $order['cui'],
        'Reg. Com.'     => $order['reg_com'],
        'Sediu social'  => $order['sediu'],
        $order['tip'] === 'pj' ? 'Persoana de contact' : 'Nume' => $order['nume'],
        'Telefon'       => $order['telefon'],
        'Email'         => $order['email'],
        'Plata'         => ORDER_PLATA[$order['plata']]['label'] ?? $order['plata'],
        'Livrare'       => $order['plata'] === 'ridicare'
                           ? 'Ridicare din magazin'
                           : trim($order['adresa'] . ', ' . $order['localitate'] . ', jud. ' . $order['judet'], ', '),
        'Mentiuni'      => (string) $order['mentiuni'],
    ];
    return array_filter($d, fn ($v) => $v !== '' && $v !== null);
}

/** Notificarea interna: comanda noua, cu tot ce trebuie ca sa suni clientul. */
function orderAdminEmail(array $order): array
{
    $subject = 'Comanda noua ' . $order['order_number'] . ' — ' . formatLei((int) $order['total_bani'])
             . ' — ' . ($order['tip'] === 'pj' && $order['firma'] !== '' ? $order['firma'] : $order['nume']);
    $rows = '';
    $text = "Comanda noua {$order['order_number']} din {$order['created_at']}\n\n";
    foreach (orderDetails($order) as $label => $value) {
        $rows .= '<tr><td style="padding:4px 12px 4px 0;color:#666;vertical-align:top">' . mh($label) . '</td>'
               . '<td style="padding:4px 0">' . nl2br(mh($value)) . '</td></tr>';
        $text .= $label . ': ' . $value . "\n";
    }
    $tel = mh($order['telefon']);
    $html = '<div style="font-family:Arial,sans-serif;color:#222;max-width:680px">'
          . '<h2 style="color:#06363A;margin:0 0 4px">Comanda noua ' . mh($order['order_number']) . '</h2>'
          . '<p style="margin:0 0 16px;color:#666">' . mh($order['created_at']) . ' · de confirmat telefonic: '
          . '<a href="tel:' . $tel . '">' . $tel . '</a></p>'
          . '<table style="font-size:14px;margin-bottom:16px">' . $rows . '</table>'
          . orderItemsHtml($order)
          . '<p style="margin-top:16px;font-size:13px;color:#666">' . mh(ORDER_TRANSPORT_INFO) . '</p></div>';
    $text .= "\nProduse:\n" . orderItemsText($order);
    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

/**
 * Trimite notificarea interna si noteaza momentul in comanda. Esecul NU afecteaza comanda
 * (e deja salvata); doar se scrie in log si email_admin_at ramane gol.
 */
function orderNotifyAdmin(PDO $pdo, array $order, array $smtp = []): bool
{
    $mail = orderAdminEmail($order);
    $ok = mailSend(ORDER_NOTIFY_TO, $mail['subject'], $mail['html'], $mail['text'], $order['email'], $smtp);
    if ($ok) {
        $pdo->prepare('UPDATE orders SET email_admin_at = NOW() WHERE id = ?')->execute([$order['id']]);
    } else {
        error_log("orderNotifyAdmin: notificarea pentru {$order['order_number']} NU a fost trimisa");
    }
    return $ok;
}

/** Datele firmei din subsolul emailului catre client (aceleasi ca in subsolul site-ului). */
const SHOP_FOOTER = 'DUNLITE COM SRL — CIF RO9027530 — J16/1357/1996 — Strada Caracal 52, Dabuleni, jud. Dolj';
const SHOP_PHONE  = '0749 130 565';

/** Confirmarea pentru client (T13): ce a comandat, ce urmeaza, cum plateste. Fara date interne (IP etc.). */
function orderClientEmail(array $order): array
{
    $plata = ORDER_PLATA[$order['plata']] ?? ['label' => $order['plata'], 'info' => ''];
    $link  = SITE_URL . '/comanda-trimisa.html?t=' . $order['public_token'];
    $next  = 'Te sunam in cel mai scurt timp (L–V 8:00–18:00) ca sa confirmam disponibilitatea produselor'
           . ($order['plata'] === 'ridicare' ? ' si momentul in care poti ridica marfa.' : ', costul si termenul de livrare.')
           . ' Comanda devine ferma dupa ce o confirmam telefonic.';
    $where = $order['plata'] === 'ridicare'
           ? 'Ridicare din magazin: Strada Caracal 52, Dabuleni (L–V 8:00–18:00).'
           : 'Livrare la: ' . trim($order['adresa'] . ', ' . $order['localitate'] . ', jud. ' . $order['judet'], ', ') . '.';

    $subject = 'Am primit comanda ta ' . $order['order_number'] . ' — Mugurel';
    $html = '<div style="font-family:Arial,sans-serif;color:#222;max-width:640px">'
          . '<h2 style="color:#06363A;margin:0 0 8px">Multumim pentru comanda!</h2>'
          . '<p style="margin:0 0 6px">Buna ziua, ' . mh($order['nume']) . ',</p>'
          . '<p style="margin:0 0 12px">Am primit comanda <b>' . mh($order['order_number']) . '</b>. ' . mh($next) . '</p>'
          . '<p style="margin:0 0 4px"><b>Plata:</b> ' . mh($plata['label']) . '. ' . mh($plata['info']) . '</p>'
          . '<p style="margin:0 0 16px"><b>' . ($order['plata'] === 'ridicare' ? 'Ridicare' : 'Livrare') . ':</b> ' . mh(preg_replace('/^(Ridicare din magazin|Livrare la): /', '', $where)) . '</p>'
          . orderItemsHtml($order)
          . ($order['plata'] === 'ridicare' ? '' : '<p style="margin-top:12px;font-size:13px;color:#555">' . mh(ORDER_TRANSPORT_INFO) . '</p>')
          . '<p style="margin-top:16px">Poti vedea comanda oricand aici: <a href="' . mh($link) . '">' . mh($link) . '</a></p>'
          . '<p>Ai o intrebare? Suna-ne la <a href="tel:+40749130565">' . SHOP_PHONE . '</a> sau scrie-ne pe '
          . '<a href="https://wa.me/40749130565">WhatsApp</a>.</p>'
          . '<p style="margin-top:24px;font-size:12px;color:#888">' . mh(SHOP_FOOTER) . '</p></div>';

    $text = "Multumim pentru comanda!\n\nBuna ziua, {$order['nume']},\n\n"
          . "Am primit comanda {$order['order_number']}. $next\n\n"
          . "Plata: {$plata['label']}. {$plata['info']}\n$where\n\nProduse:\n" . orderItemsText($order)
          . ($order['plata'] === 'ridicare' ? '' : "\n" . ORDER_TRANSPORT_INFO . "\n")
          . "\nComanda ta: $link\nTelefon: " . SHOP_PHONE . " | WhatsApp: https://wa.me/40749130565\n\n" . SHOP_FOOTER . "\n";

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

/** Trimite confirmarea catre client. Ca la notificarea interna: esecul nu afecteaza comanda. */
function orderNotifyClient(PDO $pdo, array $order, array $smtp = []): bool
{
    $mail = orderClientEmail($order);
    $ok = mailSend($order['email'], $mail['subject'], $mail['html'], $mail['text'], ORDER_NOTIFY_TO, $smtp);
    if ($ok) {
        $pdo->prepare('UPDATE orders SET email_client_at = NOW() WHERE id = ?')->execute([$order['id']]);
    } else {
        error_log("orderNotifyClient: confirmarea pentru {$order['order_number']} NU a fost trimisa");
    }
    return $ok;
}
