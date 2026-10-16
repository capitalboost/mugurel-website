<?php
/** Pagina statica, trecuta pe sabloanele comune (header/meniu/footer generate din catalog). */
require_once __DIR__ . '/admin/config.php';
$pageTitle       = 'Mugurel Dăbuleni — Materiale Construcții, Mobilier';
$metaDescription = 'Mugurel — magazin materiale constructii, mobilier si electrocasnice. Oferta completa A-Z. Comanda pe WhatsApp la 0749 130 565. Livrare rapida, preturi corecte.';
$canonicalUrl    = 'https://mugurel-bricolaj.ro/';
$metaRobots      = 'index, follow';
$ogTitle         = 'Mugurel — Materiale Constructii, Mobilier, Electrocasnice';
$ogDescription   = 'Oferta completa pentru casa ta. Comanda pe WhatsApp la 0749 130 565.';
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
  "email": "dunlite@gmail.com",
  "foundingDate": "2017",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Strada Caracal 52",
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
  "description": "Magazin de materiale de constructii, mobilier si electrocasnice in Dabuleni, judetul Dolj. Oferta completa A-Z pentru casa ta. Comenzi pe WhatsApp la 0749 130 565.",
  "priceRange": "$$",
  "currenciesAccepted": "RON",
  "paymentAccepted": "Cash, Card"
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
HTML;
require_once __DIR__ . '/includes/home.php';
$hidePromoWa     = true; // prima pagina: fara „Afla disponibilitate" din banda de sus
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
          <p class="hero-eyebrow">Constructii · Mobilier · Electrocasnice</p>
          <h1 class="hero-title">Materiale Construcții<br>în <span>Dăbuleni</span></h1>
          <p class="hero-desc">Gama completa de materiale de constructii, instalatii, mobilier si electrocasnice. Produse de calitate, preturi corecte, livrate rapid la tine acasa.</p>
          <div class="hero-stats">
            <div class="hero-stat"><div class="n">3000+</div><div class="l">Produse disponibile</div></div>
            <div class="hero-stat"><div class="n">A–Z</div><div class="l">Oferta completa</div></div>
            <div class="hero-stat"><div class="n">24h</div><div class="l">Raspuns oferta</div></div>
          </div>
          <div class="hero-ctas">
            <a href="https://wa.me/40749130565?text=Buna%20ziua!%20Vreau%20sa%20cer%20o%20oferta%20de%20la%20Mugurel." class="btn-wa-hero" target="_blank" rel="noopener">
              <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
              Scrie-ne pe WhatsApp
            </a>
            <a href="catalog.html" class="btn-catalog-hero">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
              Vezi catalogul complet
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
        <div><div class="t-title">Comanda pe WhatsApp</div><div class="t-sub">Simplu, rapid, direct</div></div>
      </div>
      <div class="trust-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        <div><div class="t-title">Livrare rapida</div><div class="t-sub">La adresa ta</div></div>
      </div>
      <div class="trust-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
        <div><div class="t-title">Produse de calitate</div><div class="t-sub">Marci verificate</div></div>
      </div>
      <div class="trust-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <div><div class="t-title">Program L–V 8–18</div><div class="t-sub">Disponibili zilnic</div></div>
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
          <h2>De ce sa alegi Mugurel?</h2>
          <p>Suntem un magazin specializat cu experienta in materiale de constructii, mobilier si electrocasnice. Oferim produse de calitate, sfaturi de specialitate si comanda simpla pe WhatsApp.</p>
          <a href="despre-noi.html" class="btn-outline-white">Afla mai multe &rarr;</a>
        </div>
        <div class="about-banner-items">
          <div class="ab-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><span>Produse verificate si garantate</span></div>
          <div class="ab-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Raspuns rapid pe WhatsApp</span></div>
          <div class="ab-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg><span>Echipa cu experienta</span></div>
          <div class="ab-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg><span>Livrare la domiciliu</span></div>
        </div>
      </div>
    </section>

  </main>
</div>

<!-- FOOTER -->

<!-- DESPRE MUGUREL -->
<section style="background:#fff;padding:44px 0;">
  <div class="container" style="max-width:1000px;">
    <h2 style="font-family:'Roboto Condensed',sans-serif;font-size:24px;color:#06363A;margin-bottom:20px;">Despre Mugurel — Magazinul tău de materiale din Dăbuleni</h2>
    <div class="despre-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:32px;max-width:960px;margin:0 auto;">
      <div>
        <p style="font-size:14px;line-height:1.8;color:#444;margin-bottom:12px;">
          Mugurel este magazinul de materiale construcții, mobilier și electrocasnice din Dăbuleni, județul Dolj, deschis din 2017. Oferim o gamă completă de produse pentru construcție, renovare și amenajare interioară — de la fundație până la finisaje.
        </p>
        <p style="font-size:14px;line-height:1.8;color:#444;margin-bottom:12px;">
          Colaborăm cu echipe de constructori, instalatori și clienți individuali din Dăbuleni și localitățile învecinate: Bechet, Corabia, Calafat și întreaga zonă Câmpia Romănă. Comenzile se plasează rapid pe WhatsApp la <strong>0749 130 565</strong>.
        </p>
        <p style="font-size:14px;line-height:1.8;color:#444;">
          Stocul este actualizat săptămânal. Livrăm în aceeași zi sau a doua zi în toată zona Dăbuleni. Ne găsiți pe Strada Caracal 52, Dăbuleni — sau direct pe WhatsApp.
        </p>
      </div>
      <div>
        <h3 style="font-size:16px;font-weight:700;color:#06363A;margin-bottom:12px;">Ce găsiți la Mugurel:</h3>
        <ul style="font-size:14px;line-height:2.1;color:#444;padding-left:18px;">
          <li>Materiale de construcții: BCA, gips-carton, izolație, acoperiș, tablă, țiglă</li>
          <li>Instalații electrice: cabluri, prize, tablouri, becuri LED, aparataj</li>
          <li>Instalații termice: centrale termice, radiatoare, pompe căldură, aer condiționat</li>
          <li>Sanitare: baterii, căzi, cabine duș, mobilier baie, țevi PEX și PPR</li>
          <li>Mobilier: living, dormitor, bucătărie, mobilier baie și birou</li>
          <li>Electrocasnice: aparate mari și mici pentru bucătărie și baie</li>
          <li>Scule și unelte: electrice, manuale, echipament protecție</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- FAQ -->
<section style="background:#f5f5f5;padding:44px 0;">
  <div class="container" style="max-width:820px;">
    <h2 style="font-family:'Roboto Condensed',sans-serif;font-size:22px;color:#06363A;margin-bottom:28px;">Întrebări frecvente</h2>
    <div style="margin-bottom:22px;">
      <h3 style="font-size:15px;font-weight:700;color:#333;margin-bottom:6px;">Ce materiale de construcții găsiți la Mugurel în Dăbuleni?</h3>
      <p class="faq-answer" style="font-size:14px;line-height:1.75;color:#555;">La Mugurel găsiți o gamă completă: BCA, gips-carton, polistiren grafitat, membrane hidroizolatoare, tablă și țiglă pentru acoperiș, cărămidă, vată minerală, profile metalice și accesorii montaj. Stocul este disponibil permanent și livrăm în Dăbuleni și împrejurimi.</p>
    </div>
    <div style="margin-bottom:22px;">
      <h3 style="font-size:15px;font-weight:700;color:#333;margin-bottom:6px;">Livrați materiale de construcții la domiciliu în Dăbuleni?</h3>
      <p class="faq-answer" style="font-size:14px;line-height:1.75;color:#555;">Da, livrăm materiale de construcții în Dăbuleni și localitățile din jur. Contactați-ne pe WhatsApp la 0749 130 565 pentru ofertă și disponibilitate transport — de obicei livrăm în aceeași zi sau a doua zi.</p>
    </div>
    <div style="margin-bottom:22px;">
      <h3 style="font-size:15px;font-weight:700;color:#333;margin-bottom:6px;">Care este programul magazinului Mugurel din Dăbuleni?</h3>
      <p class="faq-answer" style="font-size:14px;line-height:1.75;color:#555;">Suntem deschiși Luni–Vineri între 08:00 și 18:00. Ne găsiți pe Strada Caracal 52, Dăbuleni. Pentru urgențe sau comenzi în afara programului, scrieți-ne pe WhatsApp la 0749 130 565.</p>
    </div>
    <div>
      <h3 style="font-size:15px;font-weight:700;color:#333;margin-bottom:6px;">Vindeți și electrocasnice sau doar materiale de construcții?</h3>
      <p class="faq-answer" style="font-size:14px;line-height:1.75;color:#555;">Vindem o gamă completă: materiale de construcții, mobilier pentru living, dormitor și bucătărie, electrocasnice mari și mici, scule și unelte. Suntem un magazin all-in-one pentru amenajarea și echiparea casei — deschiși din 2017 în Dăbuleni.</p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/templates/footer.php'; ?>
