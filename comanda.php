<?php
/**
 * /comanda.html — datele clientului si trimiterea comenzii.
 *
 * GET: formularul + sumarul cosului (sumarul il construieste js/comanda-pagina.js din raspunsul
 *      /cos-produse.php, iar tot el pune continutul cosului in campul ascuns „cos").
 * POST, in ordine (T10): limita pe IP -> token CSRF -> capcana anti-robot -> validarea campurilor
 *       (includes/order_form.php) -> reverificarea cosului pe server (includes/cart.php).
 *       La orice problema formularul se reafiseaza cu valorile introduse.
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/includes/order_form.php';
require_once __DIR__ . '/includes/order_security.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/order_mail.php';
require_once __DIR__ . '/includes/turnstile.php';

orderSessionStart();
header('Cache-Control: no-store'); // pagina contine tokenul sesiunii

const ORDER_PHONE_HINT = 'Te rugăm să încerci din nou sau să comanzi telefonic la 0749 130 565.';

$values = array_fill_keys(array_keys(ORDER_MAX), '') + ['tip' => 'pf', 'plata' => '', 'acord' => ''];
$errors = [];
$notice = null;      // mesaj general (galben)
$cartError = null;   // problema cu cosul (rosu, cu legatura spre cos)
// Tokenul formularului (anti-dublura): pastrat la reafisarea dupa o eroare, nou la fiecare afisare curata.
$formToken = orderFormTokenValid($_POST['idem'] ?? null) ? $_POST['idem'] : orderNewFormToken();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $result = orderFormValidate($_POST);
    $values = $result['data'];

    try {
        $pdo = Database::get();
    } catch (Throwable $e) {
        $pdo = null;
    }

    if ($pdo !== null && orderRateLimited($pdo, orderClientIp($_SERVER))) {
        http_response_code(429);
        $notice = 'Ai trimis formularul de prea multe ori în ultimele minute. ' . ORDER_PHONE_HINT;
    } elseif (!orderCsrfValid($_POST['csrf'] ?? null)) {
        http_response_code(400);
        $notice = 'Sesiunea a expirat (pagina a stat deschisă prea mult). Datele tale sunt mai jos — apasă din nou „Trimite comanda".';
    } elseif (orderLooksLikeBot($_POST) || !orderFormTokenValid($_POST['idem'] ?? null)) {
        http_response_code(422);
        $notice = 'Comanda nu a putut fi trimisă. ' . ORDER_PHONE_HINT;
    } else {
        $errors = $result['errors'];

        // Cosul: doar {id, qty} din browser; pretul si disponibilitatea se recitesc din baza.
        $cosRaw = $_POST['cos'] ?? '';
        $items = is_string($cosRaw) && strlen($cosRaw) <= 20000 ? cartNormalizeItems(json_decode($cosRaw, true)) : [];
        $cart = null;
        if ($items === []) {
            $cartError = 'Coșul tău este gol sau nu a putut fi citit.';
        } elseif (($products = cartProducts(array_column($items, 'id'))) === null) {
            $cartError = 'Nu putem verifica acum produsele din coș. ' . ORDER_PHONE_HINT;
        } else {
            $cart = priceCart($items, $products);
            if ($cart['problem_count'] > 0 || $cart['orderable_count'] === 0) {
                $cartError = 'Unele produse din coș nu se mai pot comanda (preț sau stoc schimbat).';
            }
        }

        if ($errors || $cartError) {
            http_response_code(422);
        } elseif (!turnstileVerify($_POST['cf-turnstile-response'] ?? null, orderClientIp($_SERVER))) {
            // captcha-ul se verifica abia cand formularul e corect: greselile din campuri se vad primele
            http_response_code(422);
            $notice = 'Confirmă că nu ești robot (caseta de deasupra butonului „Trimite comanda”), apoi trimite din nou. ' . ORDER_PHONE_HINT;
        } else {
            try {
                $db = $pdo ?? Database::get();
                $order = orderCreate($db, $values, $cart, $formToken,
                    orderClientIp($_SERVER), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
                // Emailurile pleaca o singura data, doar pentru o comanda noua (nu la retrimitere).
                // Un email esuat nu anuleaza comanda: e deja salvata, iar esecul e in log.
                if (!$order['duplicate'] && ($saved = orderByToken($db, $order['token'])) !== null) {
                    orderNotifyAdmin($db, $saved);
                    orderNotifyClient($db, $saved);
                }
                // Redirect dupa POST: un refresh pe pagina de confirmare nu mai retrimite formularul.
                header('Location: /comanda-trimisa.html?t=' . $order['token'], true, 303);
                exit;
            } catch (OrderStockException $e) {
                http_response_code(409);
                $cartError = 'Între timp s-a schimbat stocul — ' . $e->getMessage();
            } catch (Throwable $e) {
                error_log('orderCreate: ' . $e->getMessage());
                http_response_code(503);
                $notice = 'Comanda nu a putut fi salvată din cauza unei erori tehnice. Datele tale sunt mai jos. ' . ORDER_PHONE_HINT;
            }
        }
    }
}
orderMarkFormShown();

$extraHead       = TURNSTILE_SCRIPT; // captcha-ul Cloudflare, doar pe paginile cu formular
$pageTitle       = 'Finalizează comanda — Mugurel';
$metaDescription = 'Datele de livrare pentru comanda la Mugurel Materiale Construcții.';
$canonicalUrl    = SITE_URL . '/comanda.html';
$metaRobots      = 'noindex, nofollow';
$pageScripts     = ['comanda-pagina.js', 'captcha-asteapta.js'];

$v = fn (string $k): string => htmlspecialchars($values[$k] ?? '', ENT_QUOTES, 'UTF-8');

require __DIR__ . '/templates/header.php';
?>

<div class="page-hero">
  <div class="page-hero-inner">
    <div class="breadcrumb">
      <a href="/index.html">Acasă</a><span>›</span><a href="/cos.html">Coș</a><span>›</span><span>Comanda</span>
    </div>
    <h1>Finalizează comanda</h1>
  </div>
</div>

<main class="cos-wrap comanda-wrap">
  <noscript>
    <p class="cos-msg cos-msg--error">Comanda online funcționează doar cu JavaScript activat. Poți comanda pe WhatsApp sau la telefon: 0749 130 565.</p>
  </noscript>

  <?php if ($notice): ?>
  <p class="cos-msg cos-msg--warn" role="alert" data-form-notice><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>
  <?php if ($cartError): ?>
  <p class="cos-msg cos-msg--error" role="alert" data-cart-error><?= htmlspecialchars($cartError, ENT_QUOTES, 'UTF-8') ?> <a href="/cos.html">Verifică coșul</a>.</p>
  <?php endif; ?>
  <?php if ($errors): ?>
  <div class="cos-msg cos-msg--error" role="alert" data-form-errors tabindex="-1">
    Verifică câmpurile marcate mai jos (<?= count($errors) ?>).
  </div>
  <?php endif; ?>

  <div class="comanda-layout">
    <form class="comanda-form" method="post" action="/comanda.html" novalidate data-comanda-form>
      <input type="hidden" name="cos" value="" data-cos-field>
      <input type="hidden" name="idem" value="<?= htmlspecialchars($formToken, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(orderCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
      <div class="form-hp" aria-hidden="true">
        <label for="f-<?= ORDER_HONEYPOT ?>">Nu completa acest câmp</label>
        <input id="f-<?= ORDER_HONEYPOT ?>" name="<?= ORDER_HONEYPOT ?>" type="text" tabindex="-1" autocomplete="off" value="">
      </div>
      <?php $isPj = ($values['tip'] ?? 'pf') === 'pj'; ?>

      <fieldset class="form-block form-tip">
        <legend>Comanzi ca</legend>
        <div class="form-tip-options">
          <?php foreach (ORDER_TIPURI as $tipVal => $tipLbl): ?>
          <label class="form-tip-option">
            <input type="radio" name="tip" value="<?= $tipVal ?>"<?= ($values['tip'] ?? 'pf') === $tipVal ? ' checked' : '' ?> data-tip>
            <span><?= $tipLbl ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <fieldset class="form-block" data-pj-block<?= $isPj ? '' : ' hidden' ?>>
        <legend>Date firma</legend>
        <p class="form-hint">Pentru factura pe firmă.</p>
        <?php [$a, $msg] = fieldError($errors, 'firma'); ?>
        <div class="form-field">
          <label for="f-firma">Denumire firma <span aria-hidden="true">*</span></label>
          <input id="f-firma" name="firma" type="text" autocomplete="organization" maxlength="<?= ORDER_MAX['firma'] ?>" value="<?= $v('firma') ?>"<?= $a ?>>
          <?= $msg ?>
        </div>
        <div class="form-row">
          <?php [$a, $msg] = fieldError($errors, 'cui'); ?>
          <div class="form-field">
            <label for="f-cui">Cod fiscal (CUI) <span aria-hidden="true">*</span></label>
            <input id="f-cui" name="cui" type="text" autocomplete="off" maxlength="<?= ORDER_MAX['cui'] ?>" placeholder="ex. RO14399840" value="<?= $v('cui') ?>"<?= $a ?>>
            <?= $msg ?>
          </div>
          <?php [$a, $msg] = fieldError($errors, 'reg_com'); ?>
          <div class="form-field">
            <label for="f-reg_com">Nr. Registrul Comerțului <span aria-hidden="true">*</span></label>
            <input id="f-reg_com" name="reg_com" type="text" autocomplete="off" maxlength="<?= ORDER_MAX['reg_com'] ?>" placeholder="ex. J16/123/2020" value="<?= $v('reg_com') ?>"<?= $a ?>>
            <?= $msg ?>
          </div>
        </div>
        <?php [$a, $msg] = fieldError($errors, 'sediu'); ?>
        <div class="form-field">
          <label for="f-sediu">Sediul social (adresa completă) <span aria-hidden="true">*</span></label>
          <input id="f-sediu" name="sediu" type="text" autocomplete="off" maxlength="<?= ORDER_MAX['sediu'] ?>" value="<?= $v('sediu') ?>"<?= $a ?>>
          <?= $msg ?>
        </div>
      </fieldset>

      <fieldset class="form-block">
        <legend>Date de contact</legend>

        <?php [$a, $msg] = fieldError($errors, 'nume'); ?>
        <div class="form-field">
          <label for="f-nume" data-nume-label data-pf="Nume și prenume" data-pj="Persoana de contact (nume și prenume)"><?= $isPj ? 'Persoana de contact (nume și prenume)' : 'Nume și prenume' ?> <span aria-hidden="true">*</span></label>
          <input id="f-nume" name="nume" type="text" autocomplete="name" required maxlength="<?= ORDER_MAX['nume'] ?>" value="<?= $v('nume') ?>"<?= $a ?>>
          <?= $msg ?>
        </div>

        <div class="form-row">
          <?php [$a, $msg] = fieldError($errors, 'telefon'); ?>
          <div class="form-field">
            <label for="f-telefon">Telefon <span aria-hidden="true">*</span></label>
            <input id="f-telefon" name="telefon" type="tel" inputmode="tel" autocomplete="tel" required maxlength="<?= ORDER_MAX['telefon'] ?>" placeholder="07xx xxx xxx" value="<?= $v('telefon') ?>"<?= $a ?>>
            <?= $msg ?>
          </div>
          <?php [$a, $msg] = fieldError($errors, 'email'); ?>
          <div class="form-field">
            <label for="f-email">Email <span aria-hidden="true">*</span></label>
            <input id="f-email" name="email" type="email" inputmode="email" autocomplete="email" required maxlength="<?= ORDER_MAX['email'] ?>" value="<?= $v('email') ?>"<?= $a ?>>
            <?= $msg ?>
          </div>
        </div>
      </fieldset>

      <?php [$a, $msg] = fieldError($errors, 'plata'); ?>
      <fieldset class="form-block form-plata"<?= $a ? ' aria-describedby="err-plata"' : '' ?>>
        <legend>Livrare și plată <span aria-hidden="true">*</span></legend>
        <div class="form-plata-options">
          <?php foreach (ORDER_PLATA as $plVal => $pl): ?>
          <label class="form-plata-option">
            <input type="radio" name="plata" value="<?= $plVal ?>"<?= ($values['plata'] ?? '') === $plVal ? ' checked' : '' ?> data-plata>
            <span class="form-plata-text">
              <span class="form-plata-label"><?= htmlspecialchars($pl['label'], ENT_QUOTES, 'UTF-8') ?></span>
              <span class="form-plata-info"><?= htmlspecialchars($pl['info'], ENT_QUOTES, 'UTF-8') ?></span>
            </span>
          </label>
          <?php endforeach; ?>
        </div>
        <?= $msg ?>
        <p class="form-transport" data-transport-info<?= ($values['plata'] ?? '') === 'ridicare' ? ' hidden' : '' ?>><?= htmlspecialchars(ORDER_TRANSPORT_INFO, ENT_QUOTES, 'UTF-8') ?></p>
      </fieldset>

      <fieldset class="form-block" data-adresa-block<?= ($values['plata'] ?? '') === 'ridicare' ? ' hidden' : '' ?>>
        <legend>Adresa de livrare</legend>
        <div class="form-row">
          <?php [$a, $msg] = fieldError($errors, 'judet'); ?>
          <div class="form-field">
            <label for="f-judet">Județ <span aria-hidden="true">*</span></label>
            <select id="f-judet" name="judet" autocomplete="address-level1" required<?= $a ? str_replace('aria-describedby="err-judet"', 'aria-describedby="hint-judet err-judet"', $a) : ' aria-describedby="hint-judet"' ?>>
              <?php foreach (ORDER_DELIVERY_JUDETE as $j): /* livram doar aici; Dolj e preselectat */ ?>
              <option value="<?= $j ?>"<?= ($values['judet'] ?? '') === $j || count(ORDER_DELIVERY_JUDETE) === 1 ? ' selected' : '' ?>><?= $j ?></option>
              <?php endforeach; ?>
            </select>
            <p class="form-hint" id="hint-judet"><?= htmlspecialchars(ORDER_DELIVERY_NOTE, ENT_QUOTES, 'UTF-8') ?></p>
            <?= $msg ?>
          </div>
          <?php [$a, $msg] = fieldError($errors, 'localitate'); ?>
          <div class="form-field">
            <label for="f-localitate">Localitate <span aria-hidden="true">*</span></label>
            <input id="f-localitate" name="localitate" type="text" autocomplete="address-level2" required maxlength="<?= ORDER_MAX['localitate'] ?>" value="<?= $v('localitate') ?>"<?= $a ?>>
            <?= $msg ?>
          </div>
        </div>
        <?php [$a, $msg] = fieldError($errors, 'adresa'); ?>
        <div class="form-field">
          <label for="f-adresa">Adresa (strada, număr, bloc, apartament) <span aria-hidden="true">*</span></label>
          <input id="f-adresa" name="adresa" type="text" autocomplete="street-address" required maxlength="<?= ORDER_MAX['adresa'] ?>" value="<?= $v('adresa') ?>"<?= $a ?>>
          <?= $msg ?>
        </div>
        <div class="form-field">
          <label for="f-mentiuni">Mențiuni pentru comandă <span class="form-optional">(opțional)</span></label>
          <textarea id="f-mentiuni" name="mentiuni" rows="3" maxlength="<?= ORDER_MAX['mentiuni'] ?>"><?= $v('mentiuni') ?></textarea>
        </div>
      </fieldset>

      <?php [$a, $msg] = fieldError($errors, 'acord'); ?>
      <div class="form-acord">
        <label class="form-acord-label">
          <input type="checkbox" name="acord" value="1"<?= ($values['acord'] ?? '') === '1' ? ' checked' : '' ?><?= $a ?>>
          <span>Am citit și accept <a href="/termeni-conditii.html" target="_blank" rel="noopener">Termenii și condițiile</a>
          si <a href="/politica-confidentialitate.html" target="_blank" rel="noopener">Politica de confidențialitate</a>.
          Datele mele sunt folosite doar pentru această comandă. <span aria-hidden="true">*</span></span>
        </label>
        <?= $msg ?>
      </div>

      <p class="form-required-note"><span aria-hidden="true">*</span> câmp obligatoriu</p>
      <?= turnstileWidget() ?>
      <button type="submit" class="btn-cart comanda-submit" data-comanda-submit>Trimite comanda</button>
    </form>

    <aside class="cos-summary comanda-summary" id="comanda-sumar" aria-live="polite">
      <p class="cos-loading">Se încarcă produsele…</p>
    </aside>
  </div>
</main>

<?php require __DIR__ . '/templates/footer.php'; ?>
