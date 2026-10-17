<?php
require_once __DIR__ . '/../includes/site_nav.php';
$navItems = siteNavItems();
$navActive = siteNavActiveIds($categorySlug ?? null);
$hs = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ro">
<head>
<?php if (empty($skipAnalytics)): /* paginile cu date secrete in adresa (ex. confirmarea comenzii) nu incarca analiza */ ?>
  <!-- Google Analytics + Microsoft Clarity: pornesc doar dupa „Accept" in bannerul de cookie-uri (js/analytics.js) -->
  <script src="<?= '/js/analytics.js?v=' . @filemtime(__DIR__ . '/../js/analytics.js') ?>" defer></script>
<?php endif; ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Mugurel — Materiale Construcții Dăbuleni', ENT_QUOTES, 'UTF-8') ?></title>
<meta name="description" content="<?= htmlspecialchars($metaDescription ?? '', ENT_QUOTES, 'UTF-8') ?>">
<link rel="canonical" href="<?= htmlspecialchars($canonicalUrl ?? 'https://mugurel-bricolaj.ro/', ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:title" content="<?= $hs($ogTitle ?? $pageTitle ?? 'Mugurel') ?>">
<meta property="og:description" content="<?= $hs($ogDescription ?? $metaDescription ?? '') ?>">
<meta property="og:type" content="website">
<?php // imaginea la distribuire (WhatsApp, Facebook): poza paginii daca are una ($ogImage, ex. produsul), altfel img/og-image.jpg
$ogImg = $ogImage ?? 'https://mugurel-bricolaj.ro/img/og-image.jpg'; ?>
<meta property="og:image" content="<?= $hs($ogImg) ?>">
<?php if (!isset($ogImage)): ?>
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<?php endif; ?>
<meta property="og:url" content="<?= htmlspecialchars($canonicalUrl ?? 'https://mugurel-bricolaj.ro/', ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:site_name" content="Mugurel — Materiale Construcții Dăbuleni">
<meta property="og:locale" content="ro_RO">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= $hs($ogTitle ?? $pageTitle ?? 'Mugurel') ?>">
<meta name="twitter:description" content="<?= $hs($ogDescription ?? $metaDescription ?? '') ?>">
<meta name="twitter:image" content="<?= $hs($ogImg) ?>">
<meta name="robots" content="<?= $hs($metaRobots ?? 'index, follow') ?>">
<?= $extraHead ?? '' /* date structurate, preload-uri — HTML scris in fisierul paginii, nu date de la utilizator */ ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= '/css/style.css?v=' . @filemtime(__DIR__ . '/../css/style.css') ?>">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon.png">
</head>
<body>

<!-- BANDA UE OBLIGATORIE -->
<div class="eu-topband-wrap">
  <img class="eu-topband-img" src="/img/proiect-digitalizare/header_sigle_obligatorii.png" alt="Cofinanțat de Uniunea Europeană — Guvernul României — Programul Regional Sud-Vest Oltenia 2021-2027">
  <div class="eu-mfe-inline">
    Pentru informații detaliate despre celelalte programe cofinanțate de Uniunea Europeană, vă invităm să vizitați <a href="https://www.mfe.gov.ro/" target="_blank" rel="noopener">www.mfe.gov.ro</a>
  </div>
</div>

<!-- PROMO STRIP -->
<div class="promo-strip">
  <div class="promo-strip-inner">
    <div class="promo-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Livrare în județul Dolj</div>
    <span class="promo-sep">|</span>
    <div class="promo-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>L–V 8:00–18:00</div>
  </div>
</div>

<!-- HEADER -->
<header class="header">
  <div class="hdr">
    <a href="/index.html" class="logo">
      <img src="/img/logo.svg" alt="Logo Mugurel" width="160" height="44" loading="eager" decoding="async">
      <div class="logo-text"><div class="logo-name">MUGUREL</div><div class="logo-sub">Materiale construcții</div></div>
    </a>
    <form class="searchbar" method="get" action="/catalog.html" role="search">
      <input type="search" name="q" placeholder="Caută produse: adeziv, țeavă, glet..." aria-label="Caută produse" value="<?= $hs($searchQuery ?? '') ?>">
      <button type="submit" aria-label="Caută"><svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg></button>
    </form>
    <div class="hdr-right">
      <a href="tel:+40749130565" class="hdr-phone"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13 19.79 19.79 0 0 1 1.61 4.4 2 2 0 0 1 3.6 2.2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.16 6.16l1.87-1.87a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>0749 130 565</a>
      <a href="https://wa.me/40749130565?text=Buna%20ziua!%20Va%20contactez%20de%20pe%20site-ul%20Mugurel." class="hdr-wa" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg><span>WhatsApp</span></a>
      <button class="hamburger" id="hamburger" aria-label="Meniu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
    </div>
  </div>
</header>

<!-- NAV -->
<nav class="nav">
  <div class="nav-inner">
    <?php foreach ($navItems as $it): $act = in_array($it['id'], $navActive, true); ?>
    <?php if ($it['children']): ?>
    <div class="nav-dd">
      <a href="<?= $hs($it['url']) ?>" class="nav-item nav-dd-toggle<?= $act ? ' nav-item--active' : '' ?>" aria-haspopup="true"<?= $act ? ' aria-current="true"' : '' ?>><?= $hs($it['name']) ?><svg class="nav-dd-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg></a>
      <div class="nav-dd-menu">
        <div class="nav-dd-grid">
          <?php foreach ($it['children'] as $g): ?>
          <div class="nav-dd-group">
            <a href="<?= $hs($g['url']) ?>" class="nav-dd-title"><?= $hs($g['name']) ?> <span><?= (int) $g['count'] ?></span></a>
            <?php foreach ($g['children'] as $sub): ?>
            <a href="<?= $hs($sub['url']) ?>" class="nav-dd-link"><?= $hs($sub['name']) ?></a>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <a href="<?= $hs($it['url']) ?>" class="nav-dd-all">Toate produsele din <?= $hs($it['name']) ?> (<?= (int) $it['count'] ?>) &rarr;</a>
      </div>
    </div>
    <?php else: ?>
    <a href="<?= $hs($it['url']) ?>" class="nav-item<?= $act ? ' nav-item--active' : '' ?>"<?= $act ? ' aria-current="true"' : '' ?>><?= $hs($it['name']) ?></a>
    <?php endif; ?>
    <?php endforeach; ?>
    <a href="/catalog.html" class="nav-item<?= ($navCatalog ?? false) ? ' nav-item--active' : '' ?>">Toate produsele</a>
    <a href="/contact.html" class="nav-item<?= ($pageData['slug'] ?? $pageSlugActive ?? '') === 'contact' ? ' nav-item--active' : '' ?>">Contact</a>
    <a href="/finantari-europene/" class="nav-item nav-item--eu" title="Proiect finanțat prin Programul Regional Sud-Vest Oltenia"><svg viewBox="0 0 20 14" aria-hidden="true"><rect width="20" height="14" rx="2" fill="#003399"/><g fill="#FFCC00"><circle cx="10" cy="3.2" r="0.65"/><circle cx="12.7" cy="3.9" r="0.65"/><circle cx="14.6" cy="5.8" r="0.65"/><circle cx="15.3" cy="8.5" r="0.65"/><circle cx="14.6" cy="11.2" r="0.65"/><circle cx="12.7" cy="13.1" r="0.65"/><circle cx="10" cy="13.8" r="0.65"/><circle cx="7.3" cy="13.1" r="0.65"/><circle cx="5.4" cy="11.2" r="0.65"/><circle cx="4.7" cy="8.5" r="0.65"/><circle cx="5.4" cy="5.8" r="0.65"/><circle cx="7.3" cy="3.9" r="0.65"/></g></svg>Proiect finanțat de ADR Oltenia</a>
  </div>
</nav>
