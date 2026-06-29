<?php
// Variabile: $pageData (array din DB), $pageTitle, $metaDescription, $canonicalUrl
require __DIR__ . '/header.php';
?>

<div class="page-hero">
  <div class="page-hero-inner">
    <h1><?= htmlspecialchars($pageData['title'], ENT_QUOTES, 'UTF-8') ?></h1>
  </div>
</div>

<div class="body-wrap">
  <div class="content-area" style="max-width:800px;padding:2rem 1.5rem">
    <?= $pageData['content'] /* HTML sanitizat salvat in DB prin Sanitize::html() */ ?>
  </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
