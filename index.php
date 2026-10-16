<?php
/** Pagina statica, trecuta pe sabloanele comune (header/meniu/footer generate din catalog). */
require_once __DIR__ . '/admin/config.php';
$pageTitle       = 'Mugurel Dăbuleni — Materiale de construcții și electrocasnice';
$metaDescription = 'Mugurel, Dăbuleni: materiale de construcții, metal și profile, acoperiș și electrocasnice, cu prețuri afișate cu TVA. Comandă online sau pe WhatsApp la 0749 130 565. Livrare în județul Dolj.';
$canonicalUrl    = 'https://mugurel-bricolaj.ro/';
$metaRobots      = 'index, follow';
$ogTitle         = 'Mugurel — Materiale de construcții și electrocasnice, Dăbuleni';
$ogDescription   = 'Prețuri afișate cu TVA, comandă online sau pe WhatsApp, livrare în județul Dolj.';
$extraHead = <<<'HTML'
<link rel="preload" as="image" href="/img/hero.avif" type="image/avif" media="(min-width: 769px)">
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "HomeAndConstructionBusiness",
  "@id": "https://mugurel-bricolaj.ro/#business",
  "name": "Mugurel — DUNLITE COM SRL",
  "alternateName": "Mugurel Materiale Constructii Dabuleni",
  "url": "https://mugurel-bricolaj.ro",
  "telephone": "+40749130565",
  "email": "office@mugurel-bricolaj.ro",
  "foundingDate": "2017",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Strada Caracal 66",
    "addressLocality": "Dăbuleni",
    "addressRegion": "Dolj",
    "postalCode": "207220",
    "addressCountry": "RO"
  },
  "geo": {
    "@type": "GeoCoordinates",
    "latitude": 43.808586921155126,
    "longitude": 24.09469259393745
  },
  "openingHoursSpecification": [
    {
      "@type": "OpeningHoursSpecification",
      "dayOfWeek": ["Monday","Tuesday","Wednesday","Thursday","Friday"],
      "opens": "08:00",
      "closes": "18:00"
    }
  ],
  "sameAs": ["https://www.facebook.com/byMUGUREL"],
  "description": "Magazin de materiale de construcții și electrocasnice în Dăbuleni, județul Dolj. Prețuri afișate cu TVA, comandă online sau pe WhatsApp la 0749 130 565, livrare în județul Dolj.",
  "areaServed": { "@type": "AdministrativeArea", "name": "Județul Dolj" },
  "priceRange": "$$",
  "currenciesAccepted": "RON",
  "paymentAccepted": "Cash, Card, Bank transfer"
}
</script>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "@id": "https://mugurel-bricolaj.ro/#website",
  "url": "https://mugurel-bricolaj.ro",
  "name": "Mugurel — Materiale Construcții Dăbuleni",
  "inLanguage": "ro-RO"
}
</script>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Pot comanda online de la Mugurel?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Da. Pui produsele în coș, completezi datele de livrare și alegi plata: ramburs, transfer bancar sau ridicare din magazin. Te sunăm în programul de lucru să confirmăm stocul și livrarea."
      }
    },
    {
      "@type": "Question",
      "name": "Livrați la domiciliu?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Da, livrăm în tot județul Dolj, de obicei în aceeași zi sau a doua zi în zona Dăbuleni. Costul transportului ți-l spunem la telefon, când confirmăm comanda."
      }
    },
    {
      "@type": "Question",
      "name": "Prețurile includ TVA?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Da, toate prețurile de pe site includ TVA."
      }
    },
    {
      "@type": "Question",
      "name": "Care este programul magazinului?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Luni–Vineri, 08:00–18:00, pe Strada Caracal 66, Dăbuleni. Comenzile online le poți trimite oricând; le confirmăm în programul de lucru."
      }
    },
    {
      "@type": "Question",
      "name": "Vindeți și electrocasnice?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Da: aragazuri, combine frigorifice, aspiratoare, blendere și boxe portabile, alături de materialele de construcții."
      }
    }
  ]
}
</script>
HTML;
require_once __DIR__ . '/includes/home.php';
$hideWaFloat     = true; // si fara butonul WhatsApp plutitor (16 oct)
require __DIR__ . '/templates/header.php';
?>
<!-- BODY: SIDEBAR + MAIN -->
<div class="body-wrap">

  <!-- SIDEBAR -->
  <aside class="sidebar cat-sidebar">
    <?= renderHomeSidebar() ?>
    <div class="sidebar-wa-cta">
      <div class="sidebar-wa-cta-title">Nu gasesti ce cauti?</div>
      <p>Scrie-ne pe WhatsApp — iti raspundem in 10 minute.</p>
      <a href="https://wa.me/40749130565?text=Buna%20ziua!%20Caut%20un%20produs%20pe%20care%20nu%20l-am%20gasit%20pe%20site." class="sidebar-wa-btn" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
        0749 130 565
      </a>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="main-content home-main">

    <!-- HERO -->
    <section class="hero home-hide-mobile">
      <div class="hero-inner">
        <div class="hero-text">
          <p class="hero-eyebrow">Materiale de construcții · Acoperiș · Electrocasnice</p>
          <h1 class="hero-title">Materiale Construcții<br>în <span>Dăbuleni</span></h1>
          <p class="hero-desc">Adezivi, mortare, țevi și profile metalice, tablă și sisteme pentru acoperiș, plus electrocasnice — toate cu prețul afișat. Comanzi online sau pe WhatsApp, plătești la livrare, prin transfer sau în magazin.</p>
          <div class="hero-stats">
            <div class="hero-stat"><div class="n">Online</div><div class="l">Comandă cu coș</div></div>
            <div class="hero-stat"><div class="n">Dolj</div><div class="l">Livrare în județ</div></div>
            <div class="hero-stat"><div class="n">L–V 8–18</div><div class="l">Te sunăm să confirmăm</div></div>
          </div>
          <div class="hero-ctas">
            <a href="https://wa.me/40749130565?text=Buna%20ziua!%20Vreau%20sa%20cer%20o%20oferta%20de%20la%20Mugurel." class="btn-wa-hero" target="_blank" rel="noopener">
              <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
              Scrie-ne pe WhatsApp
            </a>
            <a href="catalog.html" class="btn-catalog-hero">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
              Vezi produsele
            </a>
          </div>
        </div>
        <div class="hero-img-area">
          <picture><source media="(max-width: 768px)" srcset="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"><img class="hero-illustration" src="img/hero.avif" alt="Casa in renovare — materiale constructii Mugurel Dabuleni" width="360" height="240" fetchpriority="high"></picture>
          
        </div>
      </div>
    </section>

    <!-- TRUST BAR -->
    <div class="trust-bar home-hide-mobile">
      <div class="trust-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <div><div class="t-title">Comandă online</div><div class="t-sub">Prețuri cu TVA, confirmare telefonică</div></div>
      </div>
      <div class="trust-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        <div><div class="t-title">Livrare în Dolj</div><div class="t-sub">La adresa ta</div></div>
      </div>
      <div class="trust-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
        <div><div class="t-title">Plată la livrare</div><div class="t-sub">Ramburs, transfer sau în magazin</div></div>
      </div>
      <div class="trust-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <div><div class="t-title">Program L–V 8–18</div><div class="t-sub">Strada Caracal 66, Dăbuleni</div></div>
      </div>
    </div>

    <!-- CATEGORIES GRID -->
    <section class="cat-section">
      <div class="section-head">
        <h2 class="section-title">Categorii produse</h2>
        <a href="catalog.html" class="section-link">Vezi toate &rarr;</a>
      </div>
      <div class="cat-grid">
        <?= renderHomeCategoryCards() ?>
      </div>
    </section>

    <!-- PRODUCTS SECTION -->
    <section class="prod-section">
      <div class="section-head">
        <h2 class="section-title">Produse reprezentative</h2>
        <a href="catalog.html" class="section-link">Catalog complet &rarr;</a>
      </div>
      <div class="prod-grid">
        <?= renderCatalogCards(homeFeatured(6)) ?>
      </div>
      <div style="text-align:center;margin-top:24px;">
        <a href="catalog.html" class="btn-catalog-hero" style="display:inline-flex;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
          Vezi toate produsele
        </a>
      </div>
    </section>

    <!-- ABOUT BANNER -->
    <section class="about-banner">
      <div class="about-banner-inner">
        <div class="about-banner-text">
          <h2>De ce să alegi Mugurel?</h2>
          <p>Suntem un magazin local din Dăbuleni, cu materiale de construcții și electrocasnice la prețuri afișate pe site. Comanzi online sau pe WhatsApp, iar noi te sunăm să confirmăm stocul și livrarea înainte de a pregăti marfa.</p>
          <a href="despre-noi.html" class="btn-outline-white">Află mai multe &rarr;</a>
        </div>
        <div class="about-banner-items">
          <div class="ab-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><span>Prețuri cu TVA afișate</span></div>
          <div class="ab-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Confirmare telefonică a fiecărei comenzi</span></div>
          <div class="ab-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg><span>Plată la livrare, prin transfer sau în magazin</span></div>
          <div class="ab-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg><span>Livrare la domiciliu în județul Dolj</span></div>
        </div>
      </div>
    </section>

  </main>
</div>

<!-- FOOTER -->

<!-- DESPRE MUGUREL -->
<section class="home-about" aria-labelledby="h-despre">
  <div class="home-about-inner">
    <p class="home-kicker">Despre noi</p>
    <h2 id="h-despre">Magazinul tău de materiale din Dăbuleni</h2>
    <p class="home-about-lead">Mugurel este magazinul de materiale de construcții și electrocasnice din Dăbuleni, județul Dolj. Prețurile sunt afișate cu TVA: comanzi online sau pe WhatsApp, iar noi te sunăm să confirmăm stocul și livrarea.</p>
    <ul class="home-facts">
      <li><span class="home-fact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span><strong>Din 2017</strong><span>magazin local în Dăbuleni</span></li>
      <li><span class="home-fact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></span><strong>Livrare în Dolj</strong><span>de obicei în aceeași zi sau a doua zi</span></li>
      <li><span class="home-fact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></span><strong>Prețuri cu TVA</strong><span>afișate pe fiecare produs</span></li>
      <li><span class="home-fact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span><strong>Strada Caracal 66</strong><span>Dăbuleni · L–V 8:00–18:00</span></li>
    </ul>
    <div class="home-about-actions">
      <a href="catalog.html" class="home-btn home-btn--primary">Vezi produsele</a>
      <a href="despre-noi.html" class="home-btn home-btn--ghost">Despre noi &rarr;</a>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="home-faq" aria-labelledby="h-faq">
  <div class="home-faq-inner">
    <p class="home-kicker">Întrebări frecvente</p>
    <h2 id="h-faq">Ai o întrebare? Probabil am răspuns deja</h2>
    <div class="home-faq-list">
      <details class="home-faq-item" open>
        <summary>Pot comanda online de la Mugurel?</summary>
        <p>Da. Pui produsele în coș, completezi datele de livrare și alegi plata: ramburs, transfer bancar sau ridicare din magazin. Te sunăm în programul de lucru să confirmăm stocul și livrarea.</p>
      </details>
      <details class="home-faq-item">
        <summary>Livrați la domiciliu?</summary>
        <p>Da, livrăm în tot județul Dolj, de obicei în aceeași zi sau a doua zi în zona Dăbuleni. Costul transportului ți-l spunem la telefon, când confirmăm comanda.</p>
      </details>
      <details class="home-faq-item">
        <summary>Prețurile includ TVA?</summary>
        <p>Da, toate prețurile de pe site includ TVA.</p>
      </details>
      <details class="home-faq-item">
        <summary>Care este programul magazinului?</summary>
        <p>Luni–Vineri, 08:00–18:00, pe Strada Caracal 66, Dăbuleni. Comenzile online le poți trimite oricând; le confirmăm în programul de lucru.</p>
      </details>
      <details class="home-faq-item">
        <summary>Vindeți și electrocasnice?</summary>
        <p>Da: aragazuri, combine frigorifice, aspiratoare, blendere și boxe portabile, alături de materialele de construcții.</p>
      </details>
    </div>
    <p class="home-faq-more">Nu găsești răspunsul? Sună-ne la <a href="tel:+40749130565">0749 130 565</a> sau vezi pagina de <a href="contact.html">contact</a>.</p>
  </div>
</section>

<?php require __DIR__ . '/templates/footer.php'; ?>
