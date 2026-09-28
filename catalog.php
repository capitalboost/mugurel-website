<?php
require_once __DIR__ . '/includes/page_bootstrap.php';

const CATALOG_PER_PAGE = 60;

$cat   = isset($_GET['cat']) ? preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['cat'])) : null;
$q     = isset($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 80) : null;
$page  = max(1, (int)($_GET['page'] ?? 1));

$total    = catalogCount($cat, $q);
$pages    = max(1, (int)ceil($total / CATALOG_PER_PAGE));
$page     = min($page, $pages);
$produse  = catalogProducts($cat, $q, CATALOG_PER_PAGE, ($page - 1) * CATALOG_PER_PAGE);
$tree     = categoryTree();

/** Construieste un URL de catalog pastrand filtrele curente. */
function catUrl(?string $cat, ?string $q, int $page = 1): string {
    $p = [];
    if ($cat !== null && $cat !== '') { $p['cat'] = $cat; }
    if ($q !== null && $q !== '')     { $p['q'] = $q; }
    if ($page > 1)                     { $p['page'] = $page; }
    return 'catalog.html' . ($p ? '?' . http_build_query($p) : '');
}

/**
 * Destinatia unei intrari de sidebar pentru un slug de categorie.
 * Daca exista o pagina editoriala dedicata (<slug>.php langa catalog.php), linkul
 * duce acolo (JSON-LD, texte, sectiuni tematice, FAQ — nu doar o lista filtrata).
 * Altfel duce la catalogul filtrat pe acel slug. Nu hardcodam lista de slug-uri
 * cu pagina proprie, ca sa ramana corect daca se adauga pagini noi.
 */
function catLink(string $slug): string {
    return is_file(__DIR__ . '/' . $slug . '.php') ? $slug . '.html' : catUrl($slug, null);
}

/** Iconita de sidebar/nav pentru o categorie de nivel 1. Fallback generic pentru cele fara iconita dedicata. */
function catIconSvg(string $slug): string {
    $icons = [
        'constructii' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
        'electrice' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>',
        'incalzire' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8.56 2.9A7 7 0 0 1 19 9v4.29a2 2 0 0 0 .73 1.54l1.27.95a1 1 0 0 1 .37.78V17a1 1 0 0 1-1 1H3.63a1 1 0 0 1-1-1v-1.44a1 1 0 0 1 .37-.78l1.27-.95A2 2 0 0 0 5 13.29V9a7 7 0 0 1 3.56-6.1z"/></svg>',
        'sanitare' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 16.3c2.2 0 4-1.83 4-4.05 0-1.16-.57-2.26-1.71-3.19S7.29 6.75 7 5.3c-.29 1.45-1.14 2.84-2.29 3.76S3 11.1 3 12.25c0 2.22 1.8 4.05 4 4.05z"/></svg>',
        'apa-canal' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        'gradina' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 8C8 10 5.9 16.17 3.82 21.34A1 1 0 0 0 5 22.08c3.56-4 8.3-5.77 12-4.08"/></svg>',
        'mobilier' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>',
        'scule-unelte' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>',
        'electrocasnice' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="4" y1="8" x2="20" y2="8"/><circle cx="12" cy="15" r="3"/></svg>',
    ];
    return $icons[$slug] ?? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>';
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-R6M7YYLB48"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-R6M7YYLB48');
  </script>
  <!-- Microsoft Clarity -->
  <script type="text/javascript">
      (function(c,l,a,r,i,t,y){
          c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
          t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
          y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
      })(window, document, "clarity", "script", "y5btegvf2j");
  </script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Catalog Produse — Mugurel | Materiale Constructii, Mobilier, Electrocasnice</title>
<meta name="description" content="Catalog complet Mugurel: materiale constructii, electrice, incalzire, sanitare, apa si canal, gradina, mobilier, scule. Scrie-ne pe WhatsApp — Raspuns rapid la 0749 130 565.">
<link rel="canonical" href="https://mugurel-bricolaj.ro/catalog.html">
<meta property="og:title" content="Catalog Produse — Mugurel Materiale Constructii">
<meta property="og:description" content="Produse din toate categoriile. Pret la cerere pe WhatsApp 0749 130 565.">
<meta property="og:type" content="website">
<meta property="og:image" content="https://mugurel-bricolaj.ro/img/og-image.jpg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:url" content="https://mugurel-bricolaj.ro/catalog.html">
<meta property="og:site_name" content="Mugurel — Materiale Constructii Dăbuleni">
<meta property="og:locale" content="ro_RO">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Catalog Produse — Mugurel Materiale Constructii">
<meta name="twitter:description" content="Produse din toate categoriile. Pret la cerere pe WhatsApp 0749 130 565.">
<meta name="twitter:image" content="https://mugurel-bricolaj.ro/img/og-image.jpg">
<meta name="robots" content="index, follow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= '/css/style.css?v=' . @filemtime(__DIR__ . '/css/style.css') ?>">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="favicon.png">
</head>
<body>

<!-- BANDA UE OBLIGATORIE -->
<div class="eu-topband-wrap">
  <img class="eu-topband-img" src="/img/proiect-digitalizare/header_sigle_obligatorii.png" alt="Cofinantat de Uniunea Europeana - Guvernul Romaniei - Programul Regional Sud-Vest Oltenia 2021-2027">
  <div class="eu-mfe-inline">
    Pentru informatii detaliate despre celelalte programe cofinantate de Uniunea Europeana, va invitam sa vizitati <a href="https://www.mfe.gov.ro/" target="_blank" rel="noopener">www.mfe.gov.ro</a>
  </div>
</div>

<!-- PROMO STRIP -->
<div class="promo-strip">
  <div class="promo-strip-inner">
    <div class="promo-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Stoc permanent disponibil</div>
    <span class="promo-sep">|</span>
    <div class="promo-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>L–V 8:00–18:00</div>
    <span class="promo-sep">|</span>
    <a href="https://wa.me/40749130565?text=Buna%20ziua!%20Vreau%20sa%20cer%20o%20oferta%20de%20la%20Mugurel." class="promo-wa" target="_blank" rel="noopener">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
      Scrie-ne pe WhatsApp — Raspuns rapid
    </a>
  </div>
</div>

<!-- HEADER -->
<header class="header">
  <div class="hdr">
    <a href="index.html" class="logo">
      <img src="img/logo.svg" alt="Logo Mugurel" width="160" height="44" loading="eager" decoding="async">
      <div class="logo-text"><div class="logo-name">MUGUREL</div><div class="logo-sub">Materiale constructii</div></div>
    </a>
    <form class="searchbar" method="get" action="catalog.html">
      <input type="hidden" name="cat" value="<?= e($cat) ?>">
      <input type="text" name="q" id="search-input" placeholder="Cauta produse: tevi, cabluri, izolatie..." aria-label="Cauta produse" value="<?= e($q) ?>">
      <button type="submit" aria-label="Cauta"><svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg></button>
    </form>
    <div class="hdr-right">
      <a href="tel:+40749130565" class="hdr-phone"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13 19.79 19.79 0 0 1 1.61 4.4 2 2 0 0 1 3.6 2.2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.16 6.16l1.87-1.87a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>0749 130 565</a>
      <a href="https://wa.me/40749130565?text=Buna%20ziua!%20Vreau%20sa%20cer%20o%20oferta%20de%20la%20Mugurel." class="hdr-wa" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg><span>WhatsApp</span></a>
      <button class="hamburger" id="hamburger" aria-label="Meniu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
    </div>
  </div>
</header>

<!-- NAV -->
<nav class="nav">
  <div class="nav-inner">
    <a href="catalog.html?cat=constructii" class="nav-item" data-nav="constructii">Constructii</a>
    <a href="electrice.html" class="nav-item" data-nav="electrice">Electrice</a>
    <a href="incalzire.html" class="nav-item" data-nav="incalzire">Incalzire</a>
    <a href="sanitare.html" class="nav-item" data-nav="sanitare">Sanitare</a>
    <a href="apa-canal.html" class="nav-item" data-nav="apa-canal">Apa &amp; Canal</a>
    <a href="gradina.html" class="nav-item" data-nav="gradina">Gradina</a>
    <a href="mobilier.html" class="nav-item" data-nav="mobilier">Mobilier</a>
    <a href="electrocasnice.html" class="nav-item" data-nav="electrocasnice">Electrocasnice</a>
    <a href="scule-unelte.html" class="nav-item" data-nav="scule">Scule &amp; Unelte</a>
    <a href="contact.html" class="nav-item">Contact</a><a href="finantari-europene/" class="nav-item nav-item--eu" title="Proiect finantat prin Programul Regional Sud-Vest Oltenia"><svg viewBox="0 0 20 14" aria-hidden="true"><rect width="20" height="14" rx="2" fill="#003399"/><g fill="#FFCC00"><circle cx="10" cy="3.2" r="0.65"/><circle cx="12.7" cy="3.9" r="0.65"/><circle cx="14.6" cy="5.8" r="0.65"/><circle cx="15.3" cy="8.5" r="0.65"/><circle cx="14.6" cy="11.2" r="0.65"/><circle cx="12.7" cy="13.1" r="0.65"/><circle cx="10" cy="13.8" r="0.65"/><circle cx="7.3" cy="13.1" r="0.65"/><circle cx="5.4" cy="11.2" r="0.65"/><circle cx="4.7" cy="8.5" r="0.65"/><circle cx="5.4" cy="5.8" r="0.65"/><circle cx="7.3" cy="3.9" r="0.65"/></g></svg>Proiect finantat de ADR Oltenia</a>
  </div>
</nav>

<!-- PAGE HERO -->
<div class="page-hero">
  <div class="page-hero-inner">
    <div class="breadcrumb"><a href="index.html">Acasa</a><span>›</span> <span id="breadcrumb-cat">Catalog Produse</span></div>
    <h1 id="page-title">Catalog Produse</h1>
    <p>Selectie din catalogul nostru de 3000+ produse. Pentru lista completa, contactati-ne pe WhatsApp la 0749 130 565.</p>
  </div>
</div>

<!-- BODY -->
<div class="body-wrap">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-title">Categorii</div>

    <div class="sidebar-cat">
      <a href="catalog.html?cat=all" class="sidebar-cat-head<?= $cat === null ? ' sidebar-cat-head--active' : '' ?>" data-filter="all">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        Toate produsele
      </a>
    </div>

    <?php foreach ($tree as $top):
        $childSlugs = array_column($top['children'], 'slug');
        $isOpen     = $cat === $top['slug'] || in_array($cat, $childSlugs, true);
    ?>
    <div class="sidebar-cat<?= $isOpen ? ' open' : '' ?>">
      <a href="<?= e(catLink($top['slug'])) ?>" class="sidebar-cat-head<?= $isOpen ? ' open' : '' ?><?= $cat === $top['slug'] ? ' sidebar-cat-head--active' : '' ?>" data-filter="<?= e($top['slug']) ?>">
        <?= catIconSvg($top['slug']) ?>
        <?= e($top['name']) ?>
        <?php if ($top['children']): ?><svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg><?php endif; ?>
      </a>
      <?php if ($top['children']): ?>
      <ul class="sidebar-sub">
        <?php foreach ($top['children'] as $sub): ?>
        <li><a href="<?= e(catLink($sub['slug'])) ?>" class="sidebar-sub-item<?= $cat === $sub['slug'] ? ' active' : '' ?>" data-filter="<?= e($sub['slug']) ?>"><?= e($sub['name']) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <div class="sidebar-wa-cta">
      <div class="sidebar-wa-cta-title">Nu gasesti ce cauti?</div>
      <p>Scrie-ne pe WhatsApp si te ajutam.</p>
      <a href="https://wa.me/40749130565?text=Buna%20ziua!%20Caut%20un%20produs%20pe%20care%20nu%20l-am%20gasit%20pe%20site." class="sidebar-wa-btn" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
        0749 130 565
      </a>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main-content">

    <!-- FILTER BAR -->
    <div class="filter-bar">
      <div class="results">Afisare: <strong><span id="result-count"><?= count($produse) ?></span></strong> din <?= $total ?> produse<?php if ($pages > 1): ?><span class="results-page">pagina <?= $page ?> din <?= $pages ?></span><?php endif; ?></div>
      <div class="filter-chips">
        <a class="chip<?= $cat === null ? ' active' : '' ?>" data-filter="all" href="<?= e(catUrl(null, $q)) ?>">Toate</a>
        <?php foreach ($tree as $top): ?>
        <a class="chip<?= $cat === $top['slug'] ? ' active' : '' ?>" data-filter="<?= e($top['slug']) ?>" href="<?= e(catUrl($top['slug'], $q)) ?>"><?= e($top['name']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- PRODUCTS GRID -->
    <?php if ($total > 0): ?>
    <div class="prod-grid" id="prod-grid">
<?= renderProdCards($produse) ?>

    </div><!-- end #prod-grid -->
    <?php else: ?>
    <div id="no-results">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:48px;height:48px;margin-bottom:12px;opacity:.4;"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <p style="font-size:16px;font-weight:600;color:#444;margin-bottom:8px;">Nu am gasit produse in aceasta categorie</p>
      <p style="font-size:13px;margin-bottom:20px;">Contacteaza-ne pe WhatsApp si iti spunem daca avem produsul in stoc.</p>
      <a href="https://wa.me/40749130565?text=Buna%20ziua!%20Caut%20un%20produs%20din%20categoria%20respectiva." class="btn-wa-hero" target="_blank" rel="noopener" style="display:inline-flex;">
        <svg viewBox="0 0 24 24" fill="currentColor" style="width:18px;height:18px;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
        Intreaba pe WhatsApp
      </a>
    </div>
    <?php endif; ?>

    <?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Paginare catalog">
      <?php if ($page > 1): ?><a class="pg-btn" href="<?= e(catUrl($cat, $q, $page - 1)) ?>" rel="prev">&laquo;</a><?php endif; ?>
      <?php for ($i = 1; $i <= $pages; $i++):
            if ($i > 2 && $i < $pages - 1 && abs($i - $page) > 1) {
                if ($i === 3) { echo '<span class="pg-btn" aria-hidden="true">&hellip;</span>'; }
                continue;
            } ?>
        <?php if ($i === $page): ?><span class="pg-btn active"><?= $i ?></span>
        <?php else: ?><a class="pg-btn" href="<?= e(catUrl($cat, $q, $i)) ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $pages): ?><a class="pg-btn" href="<?= e(catUrl($cat, $q, $page + 1)) ?>" rel="next">&raquo;</a><?php endif; ?>
    </nav>
    <?php endif; ?>

  </main>
</div>

<!-- FOOTER -->
<footer class="footer">
  <div class="footer-inner">
    <div class="footer-top">
      <div class="f-about">
        <img src="img/logo.svg" alt="Mugurel" class="f-logo" width="160" height="44" loading="eager" decoding="async">
        <p>Magazin de materiale de constructii, mobilier si electrocasnice. Oferta completa A-Z pentru casa ta.</p>
        <div class="f-social">
          <a href="https://www.facebook.com/byMUGUREL" class="soc-btn" target="_blank" rel="noopener" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg></a>
          <a href="https://wa.me/40749130565" class="soc-btn" target="_blank" rel="noopener" aria-label="WhatsApp"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg></a>
        </div>
      </div>
      <div class="f-col">
        <h4>Categorii</h4>
        <ul>
          <li><a href="catalog.html?cat=constructii">Constructii</a></li>
          <li><a href="electrice.html">Electrice</a></li>
          <li><a href="incalzire.html">Incalzire</a></li>
          <li><a href="sanitare.html">Sanitare</a></li>
          <li><a href="apa-canal.html">Apa &amp; Canal</a></li>
          <li><a href="gradina.html">Gradina</a></li>
        </ul>
      </div>
      <div class="f-col">
        <h4>Informatii</h4>
        <ul>
          <li><a href="despre-noi.html">Despre noi</a></li>
          <li><a href="contact.html">Contact</a></li>
          <li><a href="politica-retur.html">Politica de retur</a></li>
          <li><a href="termeni-conditii.html">Termeni si conditii</a></li>
          <li><a href="politica-confidentialitate.html">Confidentialitate</a></li>
        </ul>
      </div>
      <div class="f-col">
        <h4>Contact</h4>
        <div class="f-contact-row"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13 19.79 19.79 0 0 1 1.61 4.4 2 2 0 0 1 3.6 2.2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.16 6.16l1.87-1.87a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg><a href="https://wa.me/40749130565">0749 130 565</a></div>
        <div class="f-contact-row"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><a href="mailto:dunlite@gmail.com">dunlite@gmail.com</a></div>
        <div class="f-contact-row"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>L–V: 8:00–18:00</div>
      </div>
    </div>
    <div class="f-bottom">
      <span>© 2026 DUNLITE COM SRL — CIF: RO9027530 | J16/1357/1996 | Strada Caracal Nr.52, 207220 Dăbuleni, Jud. Dolj</span>
      <span>Built by <a href="https://capitalboost.ro/" style="color:rgba(255,255,255,0.4);">CapitalBoost.ro</a></span>
    </div>
  </div>
</footer>

<a href="#" id="wa-float" class="wa-float" target="_blank" rel="noopener" aria-label="WhatsApp">
  <div class="wa-float-pulse"></div>
  <div class="wa-float-btn"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg></div>
</a>

<div class="mobile-menu" id="mobile-menu">
  <div class="mobile-menu-header">
    <a href="index.html" class="logo"><img src="img/logo.svg" alt="Mugurel" style="height:36px;"></a>
    <button class="mobile-close" id="mobile-close" aria-label="Inchide meniu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
  </div>
  <a href="index.html" class="mobile-nav-item">Acasa</a>
  <a href="catalog.html" class="mobile-nav-item">Catalog produse</a>
  <a href="catalog.html?cat=constructii" class="mobile-nav-item">Constructii</a>
  <a href="acoperis.html" class="mobile-nav-item" style="padding-left:28px;font-size:14px;color:#555;">Acoperis</a>
  <a href="izolatie.html" class="mobile-nav-item" style="padding-left:28px;font-size:14px;color:#555;">Izolatie</a>
  <a href="gips-carton.html" class="mobile-nav-item" style="padding-left:28px;font-size:14px;color:#555;">Gips-carton</a>
  <a href="zidarie-bca.html" class="mobile-nav-item" style="padding-left:28px;font-size:14px;color:#555;">Zidarie &amp; BCA</a>
  <a href="electrice.html" class="mobile-nav-item">Electrice</a>
  <a href="incalzire.html" class="mobile-nav-item">Incalzire</a>
  <a href="sanitare.html" class="mobile-nav-item">Sanitare</a>
  <a href="apa-canal.html" class="mobile-nav-item">Apa &amp; Canal</a>
  <a href="gradina.html" class="mobile-nav-item">Gradina</a>
  <a href="mobilier.html" class="mobile-nav-item">Mobilier</a>
  <a href="electrocasnice.html" class="mobile-nav-item">Electrocasnice</a>
  <a href="scule-unelte.html" class="mobile-nav-item">Scule &amp; Unelte</a>
  <a href="gard-imprejmuiri.html" class="mobile-nav-item">Gard &amp; Imprejmuiri</a>
  <a href="despre-noi.html" class="mobile-nav-item">Despre noi</a>
  <a href="contact.html" class="mobile-nav-item">Contact</a>
  <a href="https://wa.me/40749130565?text=Buna%20ziua!%20Va%20contactez%20de%20pe%20site-ul%20Mugurel." class="mobile-wa" target="_blank" rel="noopener">
    <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
    Scrie-ne pe WhatsApp — 0749 130 565
  </a>
</div>

<button class="back-to-top" id="back-to-top" aria-label="Inapoi sus">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>
</button>

<script src="js/main.js"></script>
</body>
</html>


