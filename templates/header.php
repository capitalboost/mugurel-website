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
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Mugurel — Materiale Constructii Dabuleni', ENT_QUOTES, 'UTF-8') ?></title>
<meta name="description" content="<?= htmlspecialchars($metaDescription ?? '', ENT_QUOTES, 'UTF-8') ?>">
<link rel="canonical" href="<?= htmlspecialchars($canonicalUrl ?? 'https://mugurel-bricolaj.ro/', ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:title" content="<?= htmlspecialchars($pageTitle ?? 'Mugurel', ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:description" content="<?= htmlspecialchars($metaDescription ?? '', ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:type" content="website">
<meta property="og:image" content="https://mugurel-bricolaj.ro/img/og-image.jpg">
<meta property="og:url" content="<?= htmlspecialchars($canonicalUrl ?? 'https://mugurel-bricolaj.ro/', ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:site_name" content="Mugurel — Materiale Constructii Dabuleni">
<meta property="og:locale" content="ro_RO">
<meta name="robots" content="index, follow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/style.css">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon.png">
</head>
<body>

<!-- PROMO STRIP -->
<div class="promo-strip">
  <div class="promo-strip-inner">
    <div class="promo-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Stoc permanent disponibil</div>
    <span class="promo-sep">|</span>
    <div class="promo-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>L–V 8:00–18:00</div>
    <span class="promo-sep">|</span>
    <a href="https://wa.me/40749130565?text=Buna%20ziua!%20Vreau%20o%20oferta." class="promo-wa" target="_blank" rel="noopener">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
      Afla disponibilitate
    </a>
  </div>
</div>

<!-- HEADER -->
<header class="header">
  <div class="hdr">
    <a href="/index.html" class="logo">
      <img src="/img/logo.svg" alt="Logo Mugurel" width="160" height="44" loading="eager" decoding="async">
      <div class="logo-text"><div class="logo-name">MUGUREL</div><div class="logo-sub">Materiale constructii</div></div>
    </a>
    <div class="searchbar">
      <input type="text" placeholder="Cauta produse..." aria-label="Cauta produse">
      <button aria-label="Cauta"><svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg></button>
    </div>
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
    <?php $activeSlug = $categorySlug ?? ($pageData['slug'] ?? ''); ?>
    <a href="/catalog.html?cat=constructii" class="nav-item <?= in_array($activeSlug, ['acoperis','izolatie','gips-carton','zidarie-bca','gard-imprejmuiri'], true) ? 'nav-item--active' : '' ?>">Constructii</a>
    <a href="/electrice.html" class="nav-item <?= $activeSlug === 'electrice' ? 'nav-item--active' : '' ?>">Electrice</a>
    <a href="/incalzire.html" class="nav-item <?= $activeSlug === 'incalzire' ? 'nav-item--active' : '' ?>">Incalzire</a>
    <a href="/sanitare.html" class="nav-item <?= $activeSlug === 'sanitare' ? 'nav-item--active' : '' ?>">Sanitare</a>
    <a href="/apa-canal.html" class="nav-item <?= $activeSlug === 'apa-canal' ? 'nav-item--active' : '' ?>">Apa &amp; Canal</a>
    <a href="/gradina.html" class="nav-item <?= $activeSlug === 'gradina' ? 'nav-item--active' : '' ?>">Gradina</a>
    <a href="/mobilier.html" class="nav-item <?= $activeSlug === 'mobilier' ? 'nav-item--active' : '' ?>">Mobilier</a>
    <a href="/electrocasnice.html" class="nav-item <?= $activeSlug === 'electrocasnice' ? 'nav-item--active' : '' ?>">Electrocasnice</a>
    <a href="/scule-unelte.html" class="nav-item <?= $activeSlug === 'scule-unelte' ? 'nav-item--active' : '' ?>">Scule &amp; Unelte</a>
    <a href="/contact.html" class="nav-item <?= $activeSlug === 'contact' ? 'nav-item--active' : '' ?>">Contact</a>
  </div>
</nav>
