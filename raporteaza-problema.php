<?php
/**
 * /raporteaza-problema.html?produs=<slug> — „Ai observat o greșeală la acest produs?" (18 oct 2026).
 * Pagină separată, ca paginile de produs să rămână ușoare (fără sesiune, captcha sau formular pe fiecare produs).
 * POST, în ordine (ca la retur): limită pe IP -> CSRF -> capcană anti-robot -> validare -> captcha -> salvare -> email la
 * magazin + copie (includes/product_reports.php) -> redirect 303 spre ?produs=…&trimis=1.
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/includes/order_security.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/product_reports.php';
require_once __DIR__ . '/includes/turnstile.php';
require_once __DIR__ . '/includes/catalog_page.php';

$slug = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($_GET['produs'] ?? $_POST['produs'] ?? '')));
$product = $slug !== '' ? catProductBySlug($slug) : null;
if ($product === null) {
    $notFoundTitle = 'Produsul nu mai este pe site';
    require __DIR__ . '/404.php';
    exit;
}

orderSessionStart();
header('Cache-Control: no-store'); // pagina conține tokenul sesiunii

const REPORT_PHONE_HINT = 'Te rugăm să încerci din nou sau să ne suni la 0749 130 565.';
$values = ['tip' => '', 'detalii' => '', 'email' => ''];
$errors = [];
$notice = null;
$sent = ($_GET['trimis'] ?? '') === '1';
$self = '/raporteaza-problema.html?produs=' . rawurlencode($slug);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $result = reportFormValidate($_POST);
    $values = $result['data'];
    try { $pdo = Database::get(); } catch (Throwable $e) { $pdo = null; }
    if ($pdo !== null && orderRateLimited($pdo, orderClientIp($_SERVER))) {
        http_response_code(429);
        $notice = 'Ai trimis formularul de prea multe ori în ultimele minute. ' . REPORT_PHONE_HINT;
    } elseif (!orderCsrfValid($_POST['csrf'] ?? null)) {
        http_response_code(400);
        $notice = 'Sesiunea a expirat (pagina a stat deschisă prea mult). Apasă din nou „Trimite raportul”.';
    } elseif (orderLooksLikeBot($_POST)) {
        http_response_code(422);
        $notice = 'Raportul nu a putut fi trimis. ' . REPORT_PHONE_HINT;
    } elseif ($result['errors']) {
        $errors = $result['errors'];
        http_response_code(422);
    } elseif (!turnstileVerify($_POST['cf-turnstile-response'] ?? null, orderClientIp($_SERVER))) {
        http_response_code(422);
        $notice = 'Confirmă că nu ești robot (caseta de deasupra butonului „Trimite raportul”), apoi trimite din nou.';
    } else {
        try {
            $db = $pdo ?? Database::get();
            $id = reportCreate($db, $product, $values, orderClientIp($_SERVER));
            reportNotify($db, $product, $values, $id);
            header('Location: ' . $self . '&trimis=1', true, 303);
            exit;
        } catch (Throwable $e) {
            error_log('reportCreate: ' . $e->getMessage());
            http_response_code(503);
            $notice = 'Raportul nu a putut fi salvat din cauza unei erori tehnice. ' . REPORT_PHONE_HINT;
        }
    }
}
orderMarkFormShown();

$extraHead       = TURNSTILE_SCRIPT;
$pageScripts     = ['captcha-asteapta.js'];
$pageTitle       = 'Raportează o problemă — ' . $product['name'];
$metaDescription = 'Ai observat o greșeală la un produs Mugurel? Spune-ne și o corectăm.';
$canonicalUrl    = SITE_URL . $self;
$metaRobots      = 'noindex, nofollow';
$v = fn (string $k): string => htmlspecialchars($values[$k] ?? '', ENT_QUOTES, 'UTF-8');
$productUrl = productUrl($product);
require __DIR__ . '/templates/header.php';
?>

<div class="page-hero">
  <div class="page-hero-inner">
    <div class="breadcrumb"><a href="/index.html">Acasă</a><span>›</span><a href="<?= $hs($productUrl) ?>">Produs</a><span>›</span><span>Raportează o problemă</span></div>
    <h1>Raportează o problemă</h1>
    <p><?= $hs($product['name']) ?></p>
  </div>
</div>

<main class="cos-wrap comanda-wrap retur-wrap" id="continut">
<?php if ($sent): ?>
  <section class="confirmare-box" data-raport-trimis>
    <p class="confirmare-nr"><strong>Mulțumim!</strong> Am primit raportul și verificăm produsul.</p>
    <p>Dacă ne-ai lăsat adresa de email, îți răspundem după ce corectăm.</p>
    <p><a class="cos-back" href="<?= $hs($productUrl) ?>">← Înapoi la produs</a></p>
  </section>
<?php else: ?>
  <?php if ($notice): ?>
  <p class="cos-msg cos-msg--warn" role="alert" data-form-notice><?= $hs($notice) ?></p>
  <?php endif; ?>

  <form method="post" action="<?= $hs($self) ?>" class="comanda-form retur-form" novalidate>
    <input type="hidden" name="csrf" value="<?= $hs(orderCsrfToken()) ?>">
    <input type="hidden" name="produs" value="<?= $hs($slug) ?>">
    <div class="form-hp" aria-hidden="true"><label>Nu completa acest câmp <input type="text" name="<?= ORDER_HONEYPOT ?>" tabindex="-1" autocomplete="off"></label></div>

    <fieldset class="form-block">
      <legend>Ce ai observat?</legend>
      <?php [$a, $msg] = fieldError($errors, 'tip'); ?>
      <div class="form-plata-options" role="radiogroup"<?= $a ?>>
        <?php foreach (REPORT_TIPURI as $k => $label): ?>
        <label class="form-plata-option">
          <input type="radio" name="tip" value="<?= $k ?>"<?= $values['tip'] === $k ? ' checked' : '' ?> required>
          <span class="form-plata-text"><span class="form-plata-label"><?= $hs($label) ?></span></span>
        </label>
        <?php endforeach; ?>
      </div>
      <?= $msg ?>
    </fieldset>

    <fieldset class="form-block">
      <legend>Detalii</legend>
      <?php [$a, $msg] = fieldError($errors, 'detalii'); ?>
      <div class="form-field">
        <label for="f-detalii">Ce e greșit? <span class="form-optional">(opțional, dar ne ajută)</span></label>
        <textarea id="f-detalii" name="detalii" rows="4" maxlength="1000" placeholder="ex. pe sac scrie 5 kg, nu 25 kg"<?= $a ?>><?= $v('detalii') ?></textarea>
        <?= $msg ?>
      </div>
      <?php [$a, $msg] = fieldError($errors, 'email'); ?>
      <div class="form-field">
        <label for="f-email">Email <span class="form-optional">(opțional — dacă vrei să-ți răspundem)</span></label>
        <input id="f-email" name="email" type="email" maxlength="160" autocomplete="email" value="<?= $v('email') ?>"<?= $a ?>>
        <?= $msg ?>
      </div>
    </fieldset>

    <?= turnstileWidget() ?>
    <button type="submit" class="btn-cart comanda-submit" data-raport-submit>Trimite raportul</button>
    <p class="form-required-note">Folosim datele doar ca să corectăm produsul și, dacă ne lași emailul, ca să-ți răspundem. Detalii în <a href="/politica-confidentialitate.html">Politica de confidențialitate</a>.</p>
  </form>
<?php endif; ?>
</main>

<?php require __DIR__ . '/templates/footer.php'; ?>
