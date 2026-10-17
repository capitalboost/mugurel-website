<?php
/**
 * Lista de produse a catalogului nou: categorie, colectie sau cautare (catalog.html).
 * Pregatita de includes/catalog_page.php (catalogListState); aici doar HTML.
 *
 * Variabile: $list (vezi catalogListState), $pageTitle, $metaDescription, $canonicalUrl, $metaRobots.
 */
require __DIR__ . '/header.php';
$L = $list;
?>

<div class="page-hero">
  <div class="page-hero-inner">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="/index.html">Acasa</a>
      <?php foreach ($L['breadcrumbs'] as $i => [$label, $url]): ?><span>›</span><?php if ($url !== null && $i < count($L['breadcrumbs']) - 1): ?><a href="<?= e($url) ?>"><?= e($label) ?></a><?php else: ?><span aria-current="page"><?= e($label) ?></span><?php endif; ?><?php endforeach; ?>
    </nav>
    <h1><?= e($L['title']) ?></h1>
    <?php if (!empty($L['typed'])): ?><p class="cat-typed">Ai scris „<?= e($L['typed']) ?>” — îți arătăm rezultatele pentru „<?= e($L['q']) ?>”.</p><?php endif; ?>
    <?php if (!empty($L['intro'])): ?><p><?= e($L['intro']) ?></p><?php endif; ?>
  </div>
</div>

<div class="body-wrap cat-wrap">

  <aside class="sidebar cat-sidebar" aria-label="Categorii si filtre">
    <?php if ($L['tree']): ?>
    <div class="sidebar-head cat-tree-head"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg><span>Categorii produse</span></div>
    <ul class="cat-tree">
      <?php foreach ($L['tree'] as $node) { echo catTreeNode($node, $L['activeIds']); } ?>
    </ul>
    <?php endif; ?>

    <?php if ($L['facets']): ?>
    <details class="cat-filters" open data-cat-filters>
      <summary class="sidebar-head"><span>Filtreaza</span><?php if ($L['activeCount']): ?> <span class="cat-filters-n">(<?= (int)$L['activeCount'] ?>)</span><?php endif; ?></summary>
      <form method="get" action="<?= e($L['baseUrl']) ?>" class="cat-filters-form" data-cat-filter-form>
        <?php if ($L['q'] !== ''): ?><input type="hidden" name="q" value="<?= e($L['q']) ?>"><?php endif; ?>
        <?php if ($L['sort'] !== 'recomandate'): ?><input type="hidden" name="sort" value="<?= e($L['sort']) ?>"><?php endif; ?>
        <?php foreach ($L['facets'] as $f): ?>
        <fieldset class="cat-facet">
          <legend><?= e($f['label']) ?></legend>
          <?php foreach ($f['options'] as $i => $o): ?>
          <label class="cat-facet-opt<?= $i >= 8 && !$o['checked'] ? ' cat-facet-opt--more' : '' ?>">
            <input type="checkbox" name="<?= e($f['grp']) ?>[]" value="<?= e($o['slug']) ?>"<?= $o['checked'] ? ' checked' : '' ?>>
            <span><?= e($o['name']) ?></span> <span class="cat-facet-n"><?= (int)$o['count'] ?></span>
          </label>
          <?php endforeach; ?>
          <?php if (count($f['options']) > 8): ?><button type="button" class="cat-facet-toggle" data-facet-more hidden>Arata toate (<?= count($f['options']) ?>)</button><?php endif; ?>
        </fieldset>
        <?php endforeach; ?>
        <div class="cat-filters-actions">
          <button type="submit" class="btn-cart cat-filters-apply">Aplica filtrele</button>
          <?php if ($L['activeCount']): ?><a class="cat-filters-reset" href="<?= e($L['resetUrl']) ?>">Sterge filtrele</a><?php endif; ?>
        </div>
      </form>
    </details>
    <?php endif; ?>

    <div class="sidebar-wa-cta">
      <div class="sidebar-wa-cta-title">Nu gasesti ce cauti?</div>
      <p>Scrie-ne pe WhatsApp si te ajutam.</p>
      <a href="<?= e(waLink('un produs din ' . $L['title'], 'Buna ziua! Caut un produs din „' . $L['title'] . '" si nu l-am gasit pe site.')) ?>" class="sidebar-wa-btn" target="_blank" rel="noopener"><?= WA_ICON ?>0749 130 565</a>
    </div>
  </aside>

  <main class="main-content" id="continut">

    <?php if ($L['subcats']): ?>
    <nav class="cat-subcats" aria-label="Subcategorii">
      <?php foreach ($L['subcats'] as $s): ?>
      <a class="cat-subcat" href="/<?= e($s['slug']) ?>.html"><?= e($s['name']) ?> <span><?= (int)$s['count'] ?></span></a>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <div class="filter-bar">
      <div class="results" role="status">
        <strong><?= (int)$L['total'] ?></strong> <?= $L['total'] === 1 ? 'produs' : 'produse' ?>
        <?php if ($L['pages'] > 1): ?><span class="results-page">pagina <?= (int)$L['page'] ?> din <?= (int)$L['pages'] ?></span><?php endif; ?>
      </div>
      <form method="get" action="<?= e($L['baseUrl']) ?>" class="cat-sort" data-cat-sort-form>
        <?php foreach ($L['hiddenFilters'] as [$name, $value]): ?><input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>"><?php endforeach; ?>
        <label for="cat-sort">Ordoneaza:</label>
        <select id="cat-sort" name="sort">
          <?php foreach (CATALOG_SORTS as $k => $label): ?><option value="<?= e($k) ?>"<?= $k === $L['sort'] ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="chip">Aplica</button></noscript>
      </form>
    </div>

    <?php if ($L['chips']): ?>
    <div class="cat-active" aria-label="Filtre active">
      <?php foreach ($L['chips'] as [$label, $url]): ?><a class="chip active" href="<?= e($url) ?>" aria-label="Scoate filtrul <?= e($label) ?>"><?= e($label) ?> ✕</a><?php endforeach; ?>
      <a class="cat-filters-reset" href="<?= e($L['resetUrl']) ?>">Sterge toate</a>
    </div>
    <?php endif; ?>

    <?php if ($L['products']): ?>
    <div class="prod-grid" id="prod-grid"><?= renderCatalogCards($L['products']) ?></div>
    <?php elseif ($L['dbDown']): ?>
    <?= renderEmptyNotice() ?>
    <?php else: ?>
    <div class="cat-none">
      <p class="cat-none-title"><?= $L['q'] !== '' ? 'Nu am gasit produse pentru „' . e($L['q']) . '"' : 'Niciun produs nu se potriveste filtrelor alese' ?></p>
      <?php if ($L['q'] !== '' && !$L['activeCount']): ?>
      <p>Încearcă un alt cuvânt (ex. „adeziv”, „țeavă”, „aragaz”) sau <a href="https://wa.me/40749130565?text=<?= rawurlencode('Buna ziua! Caut: ' . $L['q']) ?>" target="_blank" rel="noopener">scrie-ne pe WhatsApp</a> — îți spunem imediat dacă avem produsul.</p>
      <?php else: ?>
      <p>Incearca alte filtre sau scrie-ne pe WhatsApp — iti spunem imediat daca avem produsul.</p>
      <?php endif; ?>
      <?php if ($L['activeCount']): ?><a class="btn-cart" href="<?= e($L['resetUrl']) ?>">Sterge filtrele</a><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($L['pages'] > 1): ?>
    <nav class="pagination" aria-label="Paginare">
      <?php if ($L['page'] > 1): ?><a class="pg-btn" href="<?= e($L['pageUrl']($L['page'] - 1)) ?>" rel="prev" aria-label="Pagina anterioara">&laquo;</a><?php endif; ?>
      <?php
        // paginile afisate: primele 2, ultimele 2 si vecinele paginii curente; „…" o singura data in fiecare gol
        $shown = array_unique(array_filter([1, 2, $L['page'] - 1, $L['page'], $L['page'] + 1, $L['pages'] - 1, $L['pages']],
            fn ($n) => $n >= 1 && $n <= $L['pages']));
        sort($shown);
        $prev = 0;
        foreach ($shown as $i):
            if ($i > $prev + 1) { echo '<span class="pg-btn" aria-hidden="true">&hellip;</span>'; }
            $prev = $i; ?>
        <?php if ($i === $L['page']): ?><span class="pg-btn active" aria-current="page"><?= $i ?></span>
        <?php else: ?><a class="pg-btn" href="<?= e($L['pageUrl']($i)) ?>"><?= $i ?></a><?php endif; ?>
      <?php endforeach; ?>
      <?php if ($L['page'] < $L['pages']): ?><a class="pg-btn" href="<?= e($L['pageUrl']($L['page'] + 1)) ?>" rel="next" aria-label="Pagina urmatoare">&raquo;</a><?php endif; ?>
    </nav>
    <?php endif; ?>

    <?php if (!empty($L['description'])): ?>
    <section class="cat-description"><p><?= nl2br(e($L['description'])) ?></p></section>
    <?php endif; ?>
  </main>
</div>

<?php if (!empty($L['jsonLd'])): ?>
<script type="application/ld+json"><?= json_encode($L['jsonLd'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
