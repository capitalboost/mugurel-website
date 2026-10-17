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
require_once __DIR__ . '/includes/turnstile.php';

orderSessionStart();
header('Cache-Control: no-store'); // pagina contine tokenul sesiunii

const RETURN_PHONE_HINT = 'Te rugăm să încerci din nou sau să ne suni la 0749 130 565.';
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
        $notice = 'Ai trimis formularul de prea multe ori în ultimele minute. ' . RETURN_PHONE_HINT;
    } elseif (!orderCsrfValid($_POST['csrf'] ?? null)) {
        http_response_code(400);
        $notice = 'Sesiunea a expirat (pagina a stat deschisă prea mult). Datele tale sunt mai jos — apasă din nou „Trimite cererea".';
    } elseif (orderLooksLikeBot($_POST) || !orderFormTokenValid($_POST['idem'] ?? null)) {
        http_response_code(422);
        $notice = 'Cererea nu a putut fi trimisă. ' . RETURN_PHONE_HINT;
    } elseif ($result['errors']) {
        $errors = $result['errors'];
        http_response_code(422);
    } elseif (!turnstileVerify($_POST['cf-turnstile-response'] ?? null, orderClientIp($_SERVER))) {
        // captcha-ul se verifica abia cand formularul e corect: greselile din campuri se vad primele
        http_response_code(422);
        $notice = 'Confirmă că nu ești robot (caseta de deasupra butonului „Trimite cererea”), apoi trimite din nou. ' . RETURN_PHONE_HINT;
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
            $notice = 'Cererea nu a putut fi salvată din cauza unei erori tehnice. Datele tale sunt mai jos. ' . RETURN_PHONE_HINT;
        }
    }
}
orderMarkFormShown();

$extraHead       = TURNSTILE_SCRIPT; // captcha-ul Cloudflare, doar pe paginile cu formular
$pageScripts     = ['captcha-asteapta.js'];
$pageTitle       = 'Cerere de retur — Mugurel';
$metaDescription = 'Formularul pentru returnarea produselor cumpărate de la Mugurel: retragere în 14 zile sau produs neconform.';
$canonicalUrl    = SITE_URL . '/cerere-retur.html';
$metaRobots      = 'noindex, follow';
$v = fn (string $k): string => htmlspecialchars($values[$k] ?? '', ENT_QUOTES, 'UTF-8');
require __DIR__ . '/templates/header.php';
?>

<div class="page-hero">
  <div class="page-hero-inner">
    <div class="breadcrumb"><a href="/index.html">Acasă</a><span>›</span><a href="/politica-retur.html">Politica de retur</a><span>›</span><span>Cerere de retur</span></div>
    <h1>Cerere de retur</h1>
    <p>Curierul ridică produsele de la adresa ta; transportul pentru retur îl plătim noi.</p>
  </div>
</div>

<main class="cos-wrap comanda-wrap retur-wrap">
<?php if ($sent): ?>
  <section class="confirmare-box" data-retur-trimis>
    <p class="confirmare-nr">Am primit cererea de retur <strong><?= htmlspecialchars($sent, ENT_QUOTES, 'UTF-8') ?></strong></p>
    <p>Ți-am trimis confirmarea și pe email. Te contactăm în zilele lucrătoare următoare ca să stabilim ridicarea prin curier.
       Pentru retragerea în 14 zile, banii — inclusiv costul livrării inițiale — îi returnăm în cel mult 14 zile de la data cererii.</p>
    <p>Ai o întrebare? Suna-ne la <a href="tel:+40749130565">0749 130 565</a> (L–V 8:00–18:00).</p>
    <p><a class="cos-back" href="/catalog.html">← Înapoi la catalog</a></p>
  </section>
<?php else: ?>
  <?php if ($notice): ?>
  <p class="cos-msg cos-msg--warn" role="alert" data-form-notice><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>
  <?php if ($errors): ?>
  <div class="cos-msg cos-msg--error" role="alert" data-form-errors tabindex="-1">Verifică câmpurile marcate mai jos (<?= count($errors) ?>).</div>
  <?php endif; ?>

  <form method="post" action="/cerere-retur.html" class="comanda-form retur-form" novalidate>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(orderCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="idem" value="<?= htmlspecialchars($formToken, ENT_QUOTES, 'UTF-8') ?>">
    <div class="form-hp" aria-hidden="true"><label>Nu completa acest câmp <input type="text" name="<?= ORDER_HONEYPOT ?>" tabindex="-1" autocomplete="off"></label></div>

    <fieldset class="form-block">
      <legend>Ce dorești</legend>
      <?php [$a, $msg] = fieldError($errors, 'tip'); ?>
      <div class="form-plata-options" role="radiogroup"<?= $a ?>>
        <?php foreach (RETURN_TIPURI as $k => $label): ?>
        <label class="form-plata-option">
          <input type="radio" name="tip" value="<?= $k ?>"<?= $values['tip'] === $k ? ' checked' : '' ?> required>
          <span class="form-plata-text"><span class="form-plata-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="form-plata-info"><?= $k === 'retragere'
              ? 'Persoanele fizice pot renunța la produse în 14 zile de la primire, fără să spună motivul.'
              : 'Produsul are un defect sau nu este cel comandat. Garanția legală de conformitate este de 2 ani.' ?></span></span>
        </label>
        <?php endforeach; ?>
      </div>
      <?= $msg ?>
    </fieldset>

    <fieldset class="form-block">
      <legend>Comanda și produsele</legend>
      <?php
      $field = function (string $name, string $label, string $type = 'text', string $extra = '', bool $required = true, string $hint = '') use ($errors, $v) {
          [$a, $msg] = fieldError($errors, $name);
          $id = 'f-' . $name;
          $req = $required ? ' <span aria-hidden="true">*</span>' : ' <span class="form-optional">(opțional)</span>';
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
        <?php $field('order_ref', 'Numărul comenzii sau al facturii', 'text', ' autocomplete="off"', true, 'Îl găsești în emailul de confirmare (ex. MUG-2026-000123) sau pe factură.'); ?>
        <?php $field('data_primirii', 'Data la care ai primit produsele', 'date', ' max="' . date('Y-m-d') . '"', false); ?>
      </div>
      <?php $field('produse', 'Ce produse returnezi', 'textarea', '', true, 'Denumirea și cantitatea, ex. „Adeziv Ceresit CM 11, 25 kg — 2 saci".'); ?>
      <?php $field('motiv', 'Motivul sau problema', 'textarea', '', false, 'Obligatoriu doar dacă produsul e defect sau neconform. Pentru retragere nu trebuie să spui motivul.'); ?>
    </fieldset>

    <fieldset class="form-block">
      <legend>Datele tale și adresa de ridicare</legend>
      <div class="form-row">
        <?php $field('nume', 'Nume și prenume', 'text', ' autocomplete="name"'); ?>
        <?php $field('telefon', 'Telefon', 'tel', ' autocomplete="tel" inputmode="tel"'); ?>
      </div>
      <?php $field('email', 'Email', 'email', ' autocomplete="email"'); ?>
      <div class="form-row">
        <?php [$a, $msg] = fieldError($errors, 'judet'); ?>
        <div class="form-field">
          <label for="f-judet">Județ <span aria-hidden="true">*</span></label>
          <select id="f-judet" name="judet" autocomplete="address-level1" required<?= $a ?>>
            <?php foreach (ORDER_JUDETE as $j): ?><option value="<?= $j ?>"<?= $values['judet'] === $j ? ' selected' : '' ?>><?= $j ?></option><?php endforeach; ?>
          </select>
          <?= $msg ?>
        </div>
        <?php $field('localitate', 'Localitate', 'text', ' autocomplete="address-level2"'); ?>
      </div>
      <?php $field('adresa', 'Adresa de unde ridică curierul', 'text', ' autocomplete="street-address"'); ?>
      <?php $field('iban', 'IBAN pentru rambursare', 'text', ' autocomplete="off" spellcheck="false"', false, 'Doar dacă ai plătit numerar (ramburs sau în magazin). Dacă ai plătit prin transfer, returnăm în același cont.'); ?>
    </fieldset>

    <?php [$a, $msg] = fieldError($errors, 'acord'); ?>
    <div class="form-acord">
      <label class="form-acord-label">
        <input type="checkbox" name="acord" value="1"<?= $values['acord'] === '1' ? ' checked' : '' ?><?= $a ?>>
        <span>Sunt de acord ca datele de mai sus să fie folosite pentru returul produselor, conform <a href="/politica-confidentialitate.html" target="_blank" rel="noopener">Politicii de confidențialitate</a>.</span>
      </label>
      <?= $msg ?>
    </div>

    <?= turnstileWidget() ?>
    <button type="submit" class="btn-cart comanda-submit" data-retur-submit>Trimite cererea de retur</button>
    <p class="form-required-note">Câmpurile marcate cu * sunt obligatorii. Detalii în <a href="/politica-retur.html">Politica de retur</a>.</p>
  </form>
<?php endif; ?>
</main>

<?php require __DIR__ . '/templates/footer.php'; ?>
