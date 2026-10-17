<?php
/**
 * Pagina „nu am gasit" a site-ului (faza 4.1). O includ router.php si produs.php, iar .htaccess o foloseste ca
 * ErrorDocument pentru orice alta adresa inexistenta. Cine ajunge aici (link vechi, produs scos, adresa scrisa
 * gresit) primeste cautarea, categoriile principale si telefonul/WhatsApp — nu o pagina goala.
 * Optional, inainte de include: $notFoundTitle (ex. „Produsul nu mai este pe site").
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/includes/site_nav.php';

http_response_code(404);
$pageTitle       = 'Pagina nu a fost găsită — Mugurel';
$metaDescription = 'Pagina căutată nu există. Caută în catalogul Mugurel sau sună la 0749 130 565.';
$metaRobots      = 'noindex, follow';
$canonicalUrl    = SITE_URL . '/';
$nfTitle         = $notFoundTitle ?? 'Pagina nu a fost găsită';
try {
    $nfBranches = siteNavItems();
} catch (Throwable $e) {
    $nfBranches = []; // fara baza: pagina tot apare, cu cautarea si telefonul
}
require __DIR__ . '/templates/header.php';
?>

<div class="page-hero">
  <div class="page-hero-inner">
    <h1><?= $hs($nfTitle) ?></h1>
  </div>
</div>

<main class="nf-wrap" id="continut">
  <p class="nf-lead">Adresa nu mai există sau a fost scrisă greșit. Poate te ajută una dintre variantele de mai jos.</p>

  <form class="nf-search" method="get" action="/catalog.html" role="search">
    <label for="nf-q">Caută în catalog</label>
    <div class="nf-search-row">
      <input id="nf-q" type="search" name="q" placeholder="ex. adeziv, țeavă, aragaz" autocomplete="off">
      <button type="submit" class="btn-cart">Caută</button>
    </div>
  </form>

  <?php if ($nfBranches): ?>
  <h2 class="nf-h2">Categorii</h2>
  <ul class="nf-cats">
    <?php foreach ($nfBranches as $b): ?>
    <li><a href="<?= $hs($b['url']) ?>"><?= $hs($b['name']) ?> <span><?= (int) $b['count'] ?></span></a></li>
    <?php endforeach; ?>
    <li><a href="/catalog.html">Toate produsele</a></li>
  </ul>
  <?php endif; ?>

  <div class="nf-help">
    <p>Nu găsești ce cauți? Îți spunem pe loc dacă avem produsul.</p>
    <div class="nf-help-actions">
      <a class="hdr-phone" href="tel:+40749130565">Sună la 0749 130 565</a>
      <a class="hdr-wa" href="https://wa.me/40749130565?text=Buna%20ziua!%20Caut%20un%20produs%20pe%20site-ul%20Mugurel." target="_blank" rel="noopener">Scrie-ne pe WhatsApp</a>
      <a class="nf-home" href="/">Prima pagină</a>
    </div>
  </div>
</main>

<?php require __DIR__ . '/templates/footer.php'; ?>
