<?php
/**
 * /cos.html — pagina cosului.
 *
 * Continutul cosului sta in browser (localStorage, js/cos.js), deci pagina e o coaja:
 * js/cos-pagina.js trimite {id, qty} la /cos-produse.php si afiseaza ce intoarce serverul
 * (nume, pret, stoc si totaluri recitite din baza).
 */
require_once __DIR__ . '/admin/config.php';

$pageTitle       = 'Coșul tău — Mugurel';
$metaDescription = 'Produsele alese pentru comanda la Mugurel Materiale Construcții.';
$canonicalUrl    = SITE_URL . '/cos.html';
$metaRobots      = 'noindex, nofollow';
$pageScripts     = ['cos-pagina.js'];

require __DIR__ . '/templates/header.php';
?>

<div class="page-hero">
  <div class="page-hero-inner">
    <div class="breadcrumb">
      <a href="/index.html">Acasă</a><span>›</span><span>Coș</span>
    </div>
    <h1>Coșul tău</h1>
  </div>
</div>

<main class="cos-wrap" id="cos-app" aria-live="polite">
  <p class="cos-loading" data-cos-state="loading">Se încarcă coșul…</p>
  <noscript>
    <p class="cos-msg cos-msg--error">Coșul funcționează doar cu JavaScript activat. Poți comanda pe WhatsApp sau la telefon: 0749 130 565.</p>
  </noscript>
</main>

<?php require __DIR__ . '/templates/footer.php'; ?>
