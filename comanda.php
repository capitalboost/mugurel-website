<?php
/**
 * /comanda.html — datele clientului si trimiterea comenzii.
 *
 * GET: formularul + sumarul cosului (sumarul il construieste js/comanda-pagina.js din raspunsul
 *      /cos-produse.php, iar tot el pune continutul cosului in campul ascuns „cos").
 * POST: validarea campurilor pe server (includes/order_form.php); la erori, formularul se
 *       reafiseaza cu valorile introduse si mesajul langa fiecare camp.
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/includes/order_form.php';

$values = array_fill_keys(array_keys(ORDER_MAX), '') + ['tip' => 'pf'];
$errors = [];
$notice = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $result = orderFormValidate($_POST);
    $values = $result['data'];
    $errors = $result['errors'];
    if ($errors) {
        http_response_code(422);
    } else {
        // Inregistrarea comenzii se adauga in T11; pana atunci nu pretindem ca s-a trimis ceva.
        http_response_code(503);
        $notice = 'Trimiterea comenzilor online nu este inca activa. Te rugam sa comanzi telefonic la 0749 130 565.';
    }
}

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
  <?php if ($errors): ?>
  <div class="cos-msg cos-msg--error" role="alert" data-form-errors tabindex="-1">
    Verifica campurile marcate mai jos (<?= count($errors) ?>).
  </div>
  <?php endif; ?>

  <div class="comanda-layout">
    <form class="comanda-form" method="post" action="/comanda.html" novalidate data-comanda-form>
      <input type="hidden" name="cos" value="" data-cos-field>
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

      <fieldset class="form-block">
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

      <p class="form-required-note"><span aria-hidden="true">*</span> camp obligatoriu</p>
      <button type="submit" class="btn-cart comanda-submit" data-comanda-submit>Trimite comanda</button>
    </form>

    <aside class="cos-summary comanda-summary" id="comanda-sumar" aria-live="polite">
      <p class="cos-loading">Se incarca produsele…</p>
    </aside>
  </div>
</main>

<?php require __DIR__ . '/templates/footer.php'; ?>
