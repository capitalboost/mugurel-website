<?php
/**
 * /comanda-trimisa.html?t=<token> — confirmarea comenzii.
 *
 * Comanda se gaseste DOAR dupa tokenul public aleator (48 hex), nu dupa numar: comenzile altora nu se pot ghici.
 * Token gresit sau lipsa => 404. Adresa contine tokenul, deci pagina:
 *   - nu se indexeaza, nu se pune in cache, nu trimite Referer;
 *   - nu incarca Google Analytics / Clarity (ar inregistra adresa cu tot cu token).
 * Cosul din browser se goleste o singura data, imediat dupa trimitere (js/comanda-trimisa.js).
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/order_form.php';
require_once __DIR__ . '/includes/order_mail.php'; // mh(), orderQtyLabel(), formatLei()

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

$token = is_string($_GET['t'] ?? null) ? $_GET['t'] : '';
$order = null;
try {
    $order = orderByToken(Database::get(), $token);
} catch (Throwable $e) {
    error_log('comanda-trimisa: ' . $e->getMessage());
}
if ($order === null) {
    http_response_code(404);
}

$pageTitle       = $order ? 'Comanda ' . $order['order_number'] . ' — Mugurel' : 'Comanda nu a fost găsită — Mugurel';
$metaDescription = 'Confirmarea comenzii la Mugurel Materiale Construcții.';
$canonicalUrl    = SITE_URL . '/comanda-trimisa.html';
$metaRobots      = 'noindex, nofollow';
$skipAnalytics   = true;
$pageScripts     = ['comanda-trimisa.js'];

require __DIR__ . '/templates/header.php';
?>

<div class="page-hero">
  <div class="page-hero-inner">
    <h1><?= $order ? 'Mulțumim! Comanda a fost trimisă' : 'Comanda nu a fost găsită' ?></h1>
  </div>
</div>

<main class="cos-wrap confirmare-wrap" data-confirmare<?= $order ? ' data-order="' . mh($order['order_number']) . '"' : '' ?>>
<?php if (!$order): ?>
  <div class="cos-empty">
    <p class="cos-empty-title">Nu am găsit această comandă.</p>
    <p>Verifică legătura din emailul de confirmare sau sună-ne la <a href="tel:+40749130565">0749 130 565</a>.</p>
    <a class="btn-cart cos-empty-cta" href="/catalog.html">Înapoi la catalog</a>
  </div>
<?php else:
    $plata = ORDER_PLATA[$order['plata']] ?? ['label' => $order['plata'], 'info' => ''];
    $pickup = $order['plata'] === 'ridicare';
?>
  <section class="confirmare-box">
    <p class="confirmare-nr">Numărul comenzii: <strong data-order-number><?= mh($order['order_number']) ?></strong></p>
    <p>Am trimis confirmarea și pe email, la <strong><?= mh($order['email']) ?></strong>.</p>

    <h2 class="confirmare-h2">Ce urmează</h2>
    <ol class="confirmare-pasi">
      <li>Te sunăm la <strong><?= mh($order['telefon']) ?></strong> în cel mai scurt timp (L–V 8:00–18:00)
        <?= $pickup ? 'ca să confirmăm produsele și momentul ridicării.' : 'ca să confirmăm produsele, costul și termenul de livrare.' ?></li>
      <li><strong><?= mh($plata['label']) ?>:</strong> <?= mh($plata['info']) ?></li>
      <li><?= $pickup
            ? 'Ridici marfa din magazin: Strada Caracal 66, Dăbuleni.'
            : 'Livrăm la: ' . mh(trim($order['adresa'] . ', ' . $order['localitate'] . ', jud. ' . $order['judet'], ', ')) . '.' ?></li>
    </ol>
  </section>

  <section class="confirmare-box">
    <h2 class="confirmare-h2">Produsele comandate</h2>
    <ul class="comanda-sum-lines">
      <?php foreach ($order['items'] as $it): ?>
      <li class="comanda-sum-line"><span><?= mh($it['name']) ?> <span class="comanda-sum-qty">× <?= mh(orderQtyLabel($it)) ?></span></span><span><?= mh(formatLei((int) $it['line_bani'])) ?></span></li>
      <?php endforeach; ?>
    </ul>
    <p class="cos-summary-row cos-summary-total"><span>Total (cu TVA)</span><span data-order-total><?= mh(formatLei((int) $order['total_bani'])) ?></span></p>
    <?php if (!$pickup): ?><p class="cos-summary-note"><?= mh(ORDER_TRANSPORT_INFO) ?></p><?php endif; ?>
  </section>

  <p class="confirmare-contact">Ai o întrebare sau vrei să modifici comanda? Sună-ne la <a href="tel:+40749130565">0749 130 565</a>
    sau scrie-ne pe <a href="https://wa.me/40749130565?text=<?= rawurlencode('Bună ziua! Am o întrebare despre comanda ' . $order['order_number'] . '.') ?>" target="_blank" rel="noopener">WhatsApp</a>.</p>
  <p><a class="cos-back" href="/catalog.html">← Înapoi la catalog</a></p>
<?php endif; ?>
</main>

<?php require __DIR__ . '/templates/footer.php'; ?>
