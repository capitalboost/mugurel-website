<?php
/**
 * /cerere-retur.html — formularul de retur (16 oct 2026).
 *
 * GET: formularul. POST, in ordine (ca la comanda): limita pe IP -> token CSRF -> capcana anti-robot -> validare
 * (includes/returns.php) -> salvare -> email la magazin (ORDER_NOTIFY_TO, pe productie comenzi@) + confirmare la client
 * -> redirect 303 spre ?trimis=<numarul cererii> (un refresh nu retrimite). La orice problema formularul se
 * reafiseaza cu ce a scris clientul.
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/includes/order_security.php';
require_once __DIR__ . '/includes/orders.php';     // orderNewFormToken(), orderFormTokenValid()
require_once __DIR__ . '/includes/returns.php';

orderSessionStart();
header('Cache-Control: no-store'); // pagina contine tokenul sesiunii

const RETURN_PHONE_HINT = 'Te rugam sa incerci din nou sau sa ne suni la 0749 130 565.';
$values = array_fill_keys(array_keys(RETURN_MAX), '') + ['tip' => is_string($_GET['tip'] ?? null) && isset(RETURN_TIPURI[$_GET['tip']]) ? $_GET['tip'] : '',
    'judet' => 'Dolj', 'acord' => ''];
$errors = [];
$notice = null;
$sent = is_string($_GET['trimis'] ?? null) && preg_match('/^RET-\d{4}-\d{6}$/', $_GET['trimis']) ? $_GET['trimis'] : null;
$formToken = orderFormTokenValid($_POST['idem'] ?? null) ? $_POST['idem'] : orderNewFormToken();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $result = returnFormValidate($_POST);
    $values = $result['data'];
    try { $pdo = Database::get(); } catch (Throwable $e) { $pdo = null; }

    if ($pdo !== null && orderRateLimited($pdo, orderClientIp($_SERVER))) {
        http_response_code(429);
        $notice = 'Ai trimis formularul de prea multe ori in ultimele minute. ' . RETURN_PHONE_HINT;
    } elseif (!orderCsrfValid($_POST['csrf'] ?? null)) {
        http_response_code(400);
        $notice = 'Sesiunea a expirat (pagina a stat deschisa prea mult). Datele tale sunt mai jos — apasa din nou „Trimite cererea".';
    } elseif (orderLooksLikeBot($_POST) || !orderFormTokenValid($_POST['idem'] ?? null)) {
        http_response_code(422);
        $notice = 'Cererea nu a putut fi trimisa. ' . RETURN_PHONE_HINT;
    } elseif ($result['errors']) {
        $errors = $result['errors'];
        http_response_code(422);
    } else {
        try {
            $db = $pdo ?? Database::get();
            $ret = returnCreate($db, $values, $formToken, orderClientIp($_SERVER), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
            if (!$ret['duplicate'] && ($saved = returnById($db, $ret['id'])) !== null) {
                returnNotify($db, $saved);
            }
            header('Location: /cerere-retur.html?trimis=' . rawurlencode($ret['number']), true, 303);
            exit;
        } catch (Throwable $e) {
            error_log('returnCreate: ' . $e->getMessage());
            http_response_code(503);
            $notice = 'Cererea nu a putut fi salvata din cauza unei erori tehnice. Datele tale sunt mai jos. ' . RETURN_PHONE_HINT;
        }
    }
}
orderMarkFormShown();

$pageTitle       = 'Cerere de retur — Mugurel';
$metaDescription = 'Formularul pentru returnarea produselor cumparate de la Mugurel: retragere in 14 zile sau produs neconform.';
$canonicalUrl    = SITE_URL . '/cerere-retur.html';
$metaRobots      = 'noindex, follow';
$v = fn (string $k): string => htmlspecialchars($values[$k] ?? '', ENT_QUOTES, 'UTF-8');
require __DIR__ . '/templates/header.php';
?>

<div class="page-hero">
  <div class="page-hero-inner">
    <div class="breadcrumb"><a href="/index.html">Acasa</a><span>›</span><a href="/politica-retur.html">Politica de retur</a><span>›</span><span>Cerere de retur</span></div>
    <h1>Cerere de retur</h1>
    <p>Curierul ridica produsele de la adresa ta; transportul pentru retur il platim noi.</p>
  </div>
</div>

<main class="cos-wrap comanda-wrap retur-wrap">
<?php if ($sent): ?>
  <section class="confirmare-box" data-retur-trimis>
    <p class="confirmare-nr">Am primit cererea de retur <strong><?= htmlspecialchars($sent, ENT_QUOTES, 'UTF-8') ?></strong></p>
    <p>Ti-am trimis confirmarea si pe email. Te contactam in zilele lucratoare urmatoare ca sa stabilim ridicarea prin curier.
       Pentru retragerea in 14 zile, banii — inclusiv costul livrarii initiale — ii returnam in cel mult 14 zile de la data cererii.</p>
    <p>Ai o intrebare? Suna-ne la <a href="tel:+40749130565">0749 130 565</a> (L–V 8:00–18:00).</p>
    <p><a class="cos-back" href="/catalog.html">← Inapoi la catalog</a></p>
  </section>
<?php else: ?>
  <?php if ($notice): ?>
  <p class="cos-msg cos-msg--warn" role="alert" data-form-notice><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>
  <?php if ($errors): ?>
  <div class="cos-msg cos-msg--error" role="alert" data-form-errors tabindex="-1">Verifica campurile marcate mai jos (<?= count($errors) ?>).</div>
  <?php endif; ?>

  <form method="post" action="/cerere-retur.html" class="comanda-form retur-form" novalidate>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(orderCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="idem" value="<?= htmlspecialchars($formToken, ENT_QUOTES, 'UTF-8') ?>">
    <div class="form-hp" aria-hidden="true"><label>Nu completa acest camp <input type="text" name="<?= ORDER_HONEYPOT ?>" tabindex="-1" autocomplete="off"></label></div>

    <fieldset class="form-block">
      <legend>Ce doresti</legend>
      <?php [$a, $msg] = fieldError($errors, 'tip'); ?>
      <div class="form-plata-options" role="radiogroup"<?= $a ?>>
        <?php foreach (RETURN_TIPURI as $k => $label): ?>
        <label class="form-plata-option">
          <input type="radio" name="tip" value="<?= $k ?>"<?= $values['tip'] === $k ? ' checked' : '' ?> required>
          <span class="form-plata-text"><span class="form-plata-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="form-plata-info"><?= $k === 'retragere'
              ? 'Persoanele fizice pot renunta la produse in 14 zile de la primire, fara sa spuna motivul.'
              : 'Produsul are un defect sau nu este cel comandat. Garantia legala de conformitate este de 2 ani.' ?></span></span>
        </label>
        <?php endforeach; ?>
      </div>
      <?= $msg ?>
    </fieldset>

    <fieldset class="form-block">
      <legend>Comanda si produsele</legend>
      <?php
      $field = function (string $name, string $label, string $type = 'text', string $extra = '', bool $required = true, string $hint = '') use ($errors, $v) {
          [$a, $msg] = fieldError($errors, $name);
          $id = 'f-' . $name;
          $req = $required ? ' <span aria-hidden="true">*</span>' : ' <span class="form-optional">(optional)</span>';
          $describ = $hint !== '' ? ' aria-describedby="hint-' . $name . ($a ? ' err-' . $name : '') . '"' : '';
          if ($describ && $a) { $a = str_replace(' aria-describedby="err-' . $name . '"', '', $a); }
          $input = $type === 'textarea'
              ? '<textarea id="' . $id . '" name="' . $name . '" rows="4" maxlength="' . RETURN_MAX[$name] . '"' . ($required ? ' required' : '') . $extra . $describ . $a . '>' . $v($name) . '</textarea>'
              : '<input id="' . $id . '" name="' . $name . '" type="' . $type . '" maxlength="' . RETURN_MAX[$name] . '" value="' . $v($name) . '"' . ($required ? ' required' : '') . $extra . $describ . $a . '>';
          echo '<div class="form-field"><label for="' . $id . '">' . $label . $req . '</label>' . $input
             . ($hint !== '' ? '<p class="form-hint" id="hint-' . $name . '">' . $hint . '</p>' : '') . $msg . '</div>';
      };
      ?>
      <div class="form-row">
        <?php $field('order_ref', 'Numarul comenzii sau al facturii', 'text', ' autocomplete="off"', true, 'Il gasesti in emailul de confirmare (ex. MUG-2026-000123) sau pe factura.'); ?>
        <?php $field('data_primirii', 'Data la care ai primit produsele', 'date', ' max="' . date('Y-m-d') . '"', false); ?>
      </div>
      <?php $field('produse', 'Ce produse returnezi', 'textarea', '', true, 'Denumirea si cantitatea, ex. „Adeziv Ceresit CM 11, 25 kg — 2 saci".'); ?>
      <?php $field('motiv', 'Motivul sau problema', 'textarea', '', false, 'Obligatoriu doar daca produsul e defect sau neconform. Pentru retragere nu trebuie sa spui motivul.'); ?>
    </fieldset>

    <fieldset class="form-block">
      <legend>Datele tale si adresa de ridicare</legend>
      <div class="form-row">
        <?php $field('nume', 'Nume si prenume', 'text', ' autocomplete="name"'); ?>
        <?php $field('telefon', 'Telefon', 'tel', ' autocomplete="tel" inputmode="tel"'); ?>
      </div>
      <?php $field('email', 'Email', 'email', ' autocomplete="email"'); ?>
      <div class="form-row">
        <?php [$a, $msg] = fieldError($errors, 'judet'); ?>
        <div class="form-field">
          <label for="f-judet">Judet <span aria-hidden="true">*</span></label>
          <select id="f-judet" name="judet" autocomplete="address-level1" required<?= $a ?>>
            <?php foreach (ORDER_JUDETE as $j): ?><option value="<?= $j ?>"<?= $values['judet'] === $j ? ' selected' : '' ?>><?= $j ?></option><?php endforeach; ?>
          </select>
          <?= $msg ?>
        </div>
        <?php $field('localitate', 'Localitate', 'text', ' autocomplete="address-level2"'); ?>
      </div>
      <?php $field('adresa', 'Adresa de unde ridica curierul', 'text', ' autocomplete="street-address"'); ?>
      <?php $field('iban', 'IBAN pentru rambursare', 'text', ' autocomplete="off" spellcheck="false"', false, 'Doar daca ai platit numerar (ramburs sau in magazin). Daca ai platit prin transfer, returnam in acelasi cont.'); ?>
    </fieldset>

    <?php [$a, $msg] = fieldError($errors, 'acord'); ?>
    <div class="form-acord">
      <label class="form-acord-label">
        <input type="checkbox" name="acord" value="1"<?= $values['acord'] === '1' ? ' checked' : '' ?><?= $a ?>>
        <span>Sunt de acord ca datele de mai sus sa fie folosite pentru returul produselor, conform <a href="/politica-confidentialitate.html" target="_blank" rel="noopener">Politicii de confidentialitate</a>.</span>
      </label>
      <?= $msg ?>
    </div>

    <button type="submit" class="btn-cart comanda-submit" data-retur-submit>Trimite cererea de retur</button>
    <p class="form-required-note">Campurile marcate cu * sunt obligatorii. Detalii in <a href="/politica-retur.html">Politica de retur</a>.</p>
  </form>
<?php endif; ?>
</main>

<?php require __DIR__ . '/templates/footer.php'; ?>
