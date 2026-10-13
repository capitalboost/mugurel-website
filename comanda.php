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

orderSessionStart();
header('Cache-Control: no-store'); // pagina contine tokenul sesiunii

const ORDER_PHONE_HINT = 'Te rugam sa incerci din nou sau sa comanzi telefonic la 0749 130 565.';

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
        $notice = 'Ai trimis formularul de prea multe ori in ultimele minute. ' . ORDER_PHONE_HINT;
    } elseif (!orderCsrfValid($_POST['csrf'] ?? null)) {
        http_response_code(400);
        $notice = 'Sesiunea a expirat (pagina a stat deschisa prea mult). Datele tale sunt mai jos — apasa din nou „Trimite comanda".';
    } elseif (orderLooksLikeBot($_POST) || !orderFormTokenValid($_POST['idem'] ?? null)) {
        http_response_code(422);
        $notice = 'Comanda nu a putut fi trimisa. ' . ORDER_PHONE_HINT;
    } else {
        $errors = $result['errors'];

        // Cosul: doar {id, qty} din browser; pretul si disponibilitatea se recitesc din baza.
        $cosRaw = $_POST['cos'] ?? '';
        $items = is_string($cosRaw) && strlen($cosRaw) <= 20000 ? cartNormalizeItems(json_decode($cosRaw, true)) : [];
        $cart = null;
        if ($items === []) {
            $cartError = 'Cosul tau este gol sau nu a putut fi citit.';
        } elseif (($products = cartProducts(array_column($items, 'id'))) === null) {
            $cartError = 'Nu putem verifica acum produsele din cos. ' . ORDER_PHONE_HINT;
        } else {
            $cart = priceCart($items, $products);
            if ($cart['problem_count'] > 0 || $cart['orderable_count'] === 0) {
                $cartError = 'Unele produse din cos nu se mai pot comanda (pret sau stoc schimbat).';
            }
        }

        if ($errors || $cartError) {
            http_response_code(422);
        } else {
            try {
                $order = orderCreate($pdo ?? Database::get(), $values, $cart, $formToken,
                    orderClientIp($_SERVER), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
                // Redirect dupa POST: un refresh pe pagina de confirmare nu mai retrimite formularul.
                header('Location: /comanda-trimisa.html?t=' . $order['token'], true, 303);
                exit;
            } catch (Throwable $e) {
                error_log('orderCreate: ' . $e->getMessage());
                http_response_code(503);
                $notice = 'Comanda nu a putut fi salvata din cauza unei erori tehnice. Datele tale sunt mai jos. ' . ORDER_PHONE_HINT;
            }
        }
    }
}
orderMarkFormShown();

$pageTitle       = 'Finalizeaza comanda — Mugurel';
$metaDescription = 'Datele de livrare pentru comanda la Mugurel Materiale Constructii.';
$canonicalUrl    = SITE_URL . '/comanda.html';
$metaRobots      = 'noindex, nofollow';
$pageScripts     = ['comanda-pagina.js'];

/** Atributele de eroare ale unui camp + mesajul afisat sub el. */
function fieldError(array $errors, string $name): array
{
    if (!isset($errors[$name])) {
        return ['', ''];
    }
    $id = 'err-' . $name;
    return [
        ' aria-invalid="true" aria-describedby="' . $id . '"',
        '<p class="form-error" id="' . $id . '">' . htmlspecialchars($errors[$name], ENT_QUOTES, 'UTF-8') . '</p>',
    ];
}
$v = fn (string $k): string => htmlspecialchars($values[$k] ?? '', ENT_QUOTES, 'UTF-8');

require __DIR__ . '/templates/header.php';
?>

<div class="page-hero">
  <div class="page-hero-inner">
    <div class="breadcrumb">
      <a href="/index.html">Acasa</a><span>›</span><a href="/cos.html">Cos</a><span>›</span><span>Comanda</span>
    </div>
    <h1>Finalizeaza comanda</h1>
  </div>
</div>

<main class="cos-wrap comanda-wrap">
  <noscript>
    <p class="cos-msg cos-msg--error">Comanda online functioneaza doar cu JavaScript activat. Poti comanda pe WhatsApp sau la telefon: 0749 130 565.</p>
  </noscript>

  <?php if ($notice): ?>
  <p class="cos-msg cos-msg--warn" role="alert" data-form-notice><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>
  <?php if ($cartError): ?>
  <p class="cos-msg cos-msg--error" role="alert" data-cart-error><?= htmlspecialchars($cartError, ENT_QUOTES, 'UTF-8') ?> <a href="/cos.html">Verifica cosul</a>.</p>
  <?php endif; ?>
  <?php if ($errors): ?>
  <div class="cos-msg cos-msg--error" role="alert" data-form-errors tabindex="-1">
    Verifica campurile marcate mai jos (<?= count($errors) ?>).
  </div>
  <?php endif; ?>

  <div class="comanda-layout">
    <form class="comanda-form" method="post" action="/comanda.html" novalidate data-comanda-form>
      <input type="hidden" name="cos" value="" data-cos-field>
      <input type="hidden" name="idem" value="<?= htmlspecialchars($formToken, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(orderCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
      <div class="form-hp" aria-hidden="true">
        <label for="f-<?= ORDER_HONEYPOT ?>">Nu completa acest camp</label>
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
        <p class="form-hint">Pentru factura pe firma.</p>
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
            <label for="f-reg_com">Nr. Registrul Comertului <span aria-hidden="true">*</span></label>
            <input id="f-reg_com" name="reg_com" type="text" autocomplete="off" maxlength="<?= ORDER_MAX['reg_com'] ?>" placeholder="ex. J16/123/2020" value="<?= $v('reg_com') ?>"<?= $a ?>>
            <?= $msg ?>
          </div>
        </div>
        <?php [$a, $msg] = fieldError($errors, 'sediu'); ?>
        <div class="form-field">
          <label for="f-sediu">Sediul social (adresa completa) <span aria-hidden="true">*</span></label>
          <input id="f-sediu" name="sediu" type="text" autocomplete="off" maxlength="<?= ORDER_MAX['sediu'] ?>" value="<?= $v('sediu') ?>"<?= $a ?>>
          <?= $msg ?>
        </div>
      </fieldset>

      <fieldset class="form-block">
        <legend>Date de contact</legend>

        <?php [$a, $msg] = fieldError($errors, 'nume'); ?>
        <div class="form-field">
          <label for="f-nume" data-nume-label data-pf="Nume si prenume" data-pj="Persoana de contact (nume si prenume)"><?= $isPj ? 'Persoana de contact (nume si prenume)' : 'Nume si prenume' ?> <span aria-hidden="true">*</span></label>
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
        <legend>Livrare si plata <span aria-hidden="true">*</span></legend>
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
            <label for="f-judet">Judet <span aria-hidden="true">*</span></label>
            <select id="f-judet" name="judet" autocomplete="address-level1" required<?= $a ?>>
              <option value="">Alege judetul</option>
              <?php foreach (ORDER_JUDETE as $j): ?>
              <option value="<?= $j ?>"<?= ($values['judet'] ?? '') === $j ? ' selected' : '' ?>><?= $j ?></option>
              <?php endforeach; ?>
            </select>
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
          <label for="f-adresa">Adresa (strada, numar, bloc, apartament) <span aria-hidden="true">*</span></label>
          <input id="f-adresa" name="adresa" type="text" autocomplete="street-address" required maxlength="<?= ORDER_MAX['adresa'] ?>" value="<?= $v('adresa') ?>"<?= $a ?>>
          <?= $msg ?>
        </div>
        <div class="form-field">
          <label for="f-mentiuni">Mentiuni pentru comanda <span class="form-optional">(optional)</span></label>
          <textarea id="f-mentiuni" name="mentiuni" rows="3" maxlength="<?= ORDER_MAX['mentiuni'] ?>"><?= $v('mentiuni') ?></textarea>
        </div>
      </fieldset>

      <?php [$a, $msg] = fieldError($errors, 'acord'); ?>
      <div class="form-acord">
        <label class="form-acord-label">
          <input type="checkbox" name="acord" value="1"<?= ($values['acord'] ?? '') === '1' ? ' checked' : '' ?><?= $a ?>>
          <span>Am citit si accept <a href="/termeni-conditii.html" target="_blank" rel="noopener">Termenii si conditiile</a>
          si <a href="/politica-confidentialitate.html" target="_blank" rel="noopener">Politica de confidentialitate</a>.
          Datele mele sunt folosite doar pentru aceasta comanda. <span aria-hidden="true">*</span></span>
        </label>
        <?= $msg ?>
      </div>

      <p class="form-required-note"><span aria-hidden="true">*</span> camp obligatoriu</p>
      <button type="submit" class="btn-cart comanda-submit" data-comanda-submit>Trimite comanda</button>
    </form>

    <aside class="cos-summary comanda-summary" id="comanda-sumar" aria-live="polite">
      <p class="cos-loading">Se incarca produsele…</p>
    </aside>
  </div>
</main>

<?php require __DIR__ . '/templates/footer.php'; ?>
