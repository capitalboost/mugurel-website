<?php
// Variabile: $categoryName, $categorySlug, $products, $pageTitle, $metaDescription, $canonicalUrl
require __DIR__ . '/header.php';
?>

<!-- PAGE HERO -->
<div class="page-hero">
  <div class="page-hero-inner">
    <div class="breadcrumb">
      <a href="/index.html">Acasa</a><span>›</span>
      <span><?= htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <h1><?= htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') ?> in Dabuleni</h1>
  </div>
</div>

<!-- BODY -->
<div class="body-wrap">
  <div class="content-area">
    <?php if (empty($products)): ?>
    <p style="color:#aaa;padding:2rem 0">Niciun produs disponibil momentan. Contacteaza-ne pe WhatsApp pentru oferta.</p>
    <?php else: ?>
    <div class="materials-grid">
      <?php foreach ($products as $p): ?>
      <div class="material-card">
        <div class="material-img-wrap">
          <?php if ($p['image_path']): ?>
          <img src="<?= htmlspecialchars($p['image_path'], ENT_QUOTES, 'UTF-8') ?>"
               alt="<?= htmlspecialchars($p['image_alt'] ?: $p['name'], ENT_QUOTES, 'UTF-8') ?>"
               loading="lazy"
               onerror="this.closest('.material-img-wrap').style.display='none'">
          <?php endif; ?>
        </div>
        <div class="material-info">
          <h3><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></h3>
          <?php if ($p['short_description']): ?>
          <p><?= htmlspecialchars($p['short_description'], ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
          <?php if ($p['price']): ?>
          <div class="material-price">
            <?= number_format((float)$p['price'], 2) ?> RON/<?= htmlspecialchars($p['price_unit'] ?? '', ENT_QUOTES, 'UTF-8') ?>
          </div>
          <?php endif; ?>
          <div class="material-footer">
            <a href="https://wa.me/40749130565?text=Buna%20ziua!%20Vreau%20oferta%20pentru%20<?= rawurlencode($p['name']) ?>" class="mat-cta" target="_blank" rel="noopener">Cere oferta</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
