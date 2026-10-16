<?php
/**
 * /produs/<slug>.html — pagina unui produs din catalogul nou (pasul N4).
 * Descriere completa, specificatii, fisa tehnica, pret, stoc, cos, produse similare, JSON-LD Product cu GTIN.
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/includes/catalog_page.php';

$slug = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($_GET['slug'] ?? '')));
$p = $slug !== '' ? catProductBySlug($slug) : null;
if ($p === null) {
    http_response_code(404);
    if (is_file(__DIR__ . '/404.html')) { include __DIR__ . '/404.html'; } else { echo '<h1>404 — Produsul nu a fost gasit</h1>'; }
    exit;
}

$url = SITE_URL . productUrl($p);
$category = catCategories()[(int) $p['category_id']] ?? null;
$breadcrumbs = $category ? catBreadcrumbs($category) : [];
$related = catRelated($p);
$description = cleanDescriptionHtml((string) $p['description_html']); // curatat si la import; aici inca o data, pentru editarile din admin

// Tag-urile devin legaturi: aplicatiile duc la colectia lor, atributele la categoria produsului filtrata pe acel tag.
$tagLinks = [];
$collectionsByTag = [];
foreach (catCollections() as $c) { foreach ($c['tag_slugs'] as $ts) { $collectionsByTag[$ts] = $c; } }
foreach ($p['tags'] as $t) {
    if ($t['grp'] === 'aplicatie') {
        if (isset($collectionsByTag[$t['slug']])) {
            $c = $collectionsByTag[$t['slug']];
            $tagLinks[$c['slug']] = [$c['name'], '/' . $c['slug'] . '.html'];
        }
    } elseif ($category && in_array($t['grp'], ['brand', 'dimensiune', 'grosime', 'diametru', 'culoare'], true)) {
        $tagLinks[$t['grp'] . $t['slug']] = [$t['name'], '/' . $category['slug'] . '.html?' . $t['grp'] . '=' . rawurlencode($t['slug'])];
    }
}

$hasPrice = $p['price'] !== null && (float) $p['price'] > 0;
$jsonLd = array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $p['name'],
    'url' => $url,
    'sku' => $p['sku'] ?: $p['barcode'],
    'gtin' => isValidGtin((string) $p['barcode']) ? $p['barcode'] : null, // codurile interne ale magazinului nu se trimit
    'brand' => $p['manufacturer'] ? ['@type' => 'Brand', 'name' => $p['manufacturer']] : null,
    'description' => $p['short_description'] ?: null,
    'category' => $category['name'] ?? null,
    'image' => !empty($p['image_path']) ? SITE_URL . '/' . ltrim($p['image_path'], '/') : null,
    'offers' => $hasPrice ? [
        '@type' => 'Offer',
        'url' => $url,
        'priceCurrency' => 'RON',
        'price' => number_format((float) $p['price'], 2, '.', ''),
        'availability' => [
            'in_stoc' => 'https://schema.org/InStock',
            'la_comanda' => 'https://schema.org/BackOrder',
            'indisponibil' => 'https://schema.org/OutOfStock',
        ][$p['stock_status']] ?? 'https://schema.org/InStock',
        'itemCondition' => 'https://schema.org/NewCondition',
        'seller' => ['@type' => 'Organization', 'name' => 'DUNLITE COM SRL — Mugurel'],
    ] : null,
], fn ($v) => $v !== null);
$crumbLd = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => []];
$pos = 1;
$crumbLd['itemListElement'][] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => 'Acasa', 'item' => SITE_URL . '/'];
foreach ($breadcrumbs as [$label, $u]) {
    $crumbLd['itemListElement'][] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $label, 'item' => SITE_URL . $u];
}
$crumbLd['itemListElement'][] = ['@type' => 'ListItem', 'position' => $pos, 'name' => $p['name']];

$pageTitle = $p['name'] . ' | Mugurel Dabuleni';
$metaDescription = mb_substr(($hasPrice ? priceLabel($p) . ' — ' : '') . ($p['short_description'] ?: $p['name']), 0, 300);
$canonicalUrl = $url;
$categorySlug = $category['slug'] ?? null;
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG;
require __DIR__ . '/templates/header.php';
?>

<div class="page-hero">
  <div class="page-hero-inner">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="/index.html">Acasa</a>
      <?php foreach ($breadcrumbs as [$label, $u]): ?><span>›</span><a href="<?= e($u) ?>"><?= e($label) ?></a><?php endforeach; ?>
    </nav>
  </div>
</div>

<main class="produs-wrap" id="continut">
  <article class="produs">
    <div class="produs-top">
      <div class="produs-media">
        <div class="produs-img"><?= panouGenerat($p['name']) ?>
          <?php if (!empty($p['image_path'])): ?><img src="<?= e('/' . ltrim($p['image_path'], '/')) ?>" alt="<?= e($p['image_alt'] ?: $p['name']) ?>" width="560" height="420" decoding="async" onerror="this.style.display='none'"><?php endif; ?>
        </div>
      </div>

      <div class="produs-info">
        <?php if ($p['manufacturer']): ?><div class="produs-brand"><?= e($p['manufacturer']) ?></div><?php endif; ?>
        <h1 class="produs-title"><?= e($p['name']) ?></h1>
        <p class="produs-cod">Cod: <?= e($p['sku'] ?: $p['barcode']) ?><?php if ($p['barcode']): ?> · EAN <?= e($p['barcode']) ?><?php endif; ?></p>

        <div class="produs-buy">
          <div class="produs-price"><?= priceLabel($p) ?></div>
          <?php if ($hasPrice): ?><div class="produs-tva">Pret cu TVA inclus</div><?php endif; ?>
          <?= stockLabel($p) ?>
          <?php if (canAddToCart($p)): ?>
            <?= cartButton($p) ?>
          <?php endif; ?>
          <a href="<?= e(waLink($p['name'], 'Buna ziua! Am o intrebare despre: ' . $p['name'] . ' (cod ' . ($p['sku'] ?: $p['barcode']) . ').')) ?>" class="btn-wa-prod produs-wa" target="_blank" rel="noopener"><?= WA_ICON ?><?= $hasPrice ? 'Intreaba pe WhatsApp' : 'Cere oferta pe WhatsApp' ?></a>
          <ul class="produs-benefits">
            <li>Livrare in Dabuleni si in judetul Dolj</li>
            <li>Ridicare din magazin: Strada Caracal 52, Dabuleni</li>
            <li>Te sunam sa confirmam comanda (L–V 8:00–18:00)</li>
          </ul>
        </div>

        <?php if ($tagLinks): ?>
        <div class="produs-tags" aria-label="Vezi si">
          <?php foreach ($tagLinks as [$label, $u]): ?><a class="chip" href="<?= e($u) ?>"><?= e($label) ?></a><?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="produs-details">
      <?php if ($description !== ''): ?>
      <section class="produs-section produs-desc" aria-labelledby="h-desc">
        <h2 id="h-desc">Descriere</h2>
        <?= $description ?>
      </section>
      <?php endif; ?>

      <?php if ($p['specs']): ?>
      <section class="produs-section" aria-labelledby="h-spec">
        <h2 id="h-spec">Specificatii tehnice</h2>
        <table class="produs-specs">
          <tbody>
          <?php foreach ($p['specs'] as $s): ?>
            <tr><th scope="row"><?= e($s['spec_key']) ?></th><td><?= e($s['spec_value']) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </section>
      <?php endif; ?>

      <?php if ($p['documents']): ?>
      <section class="produs-section" aria-labelledby="h-docs">
        <h2 id="h-docs">Documente</h2>
        <ul class="produs-docs">
          <?php foreach ($p['documents'] as $i => $d): ?>
          <li><a href="<?= e($d['url']) ?>" target="_blank" rel="noopener nofollow"><?= e($d['label']) ?><?= count($p['documents']) > 1 ? ' ' . ($i + 1) : '' ?> (PDF, site-ul producatorului)</a></li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>
    </div>
  </article>

  <?php if ($related): ?>
  <section class="produs-related" aria-labelledby="h-rel">
    <h2 id="h-rel">Din aceeasi categorie</h2>
    <div class="prod-grid"><?= renderCatalogCards($related) ?></div>
    <?php if ($category): ?><p><a class="cat-filters-reset" href="/<?= e($category['slug']) ?>.html">Toate produsele din <?= e($category['name']) ?> →</a></p><?php endif; ?>
  </section>
  <?php endif; ?>
</main>

<script type="application/ld+json"><?= json_encode($jsonLd, $jsonFlags) ?></script>
<script type="application/ld+json"><?= json_encode($crumbLd, $jsonFlags) ?></script>

<?php require __DIR__ . '/templates/footer.php'; ?>
