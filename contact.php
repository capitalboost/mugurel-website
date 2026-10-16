<?php
/** Pagina statica, trecuta pe sabloanele comune (header/meniu/footer generate din catalog). */
require_once __DIR__ . '/admin/config.php';
$pageTitle       = 'Contact — Mugurel | Materiale Constructii';
$metaDescription = 'Contacteaza-ne pe WhatsApp la 0749 130 565 sau prin email la dunlite@gmail.com. Program: Luni-Vineri 8:00-18:00.';
$canonicalUrl    = 'https://mugurel-bricolaj.ro/contact.html';
$metaRobots      = 'index, follow';
$ogTitle         = 'Contact — Mugurel Materiale Constructii';
$ogDescription   = 'Scrie-ne pe WhatsApp la 0749 130 565. Raspundem rapid!';
$pageSlugActive  = 'contact';
$extraHead = <<<'HTML'
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "HomeAndConstructionBusiness",
  "name": "Mugurel — DUNLITE COM SRL",
  "url": "https://mugurel-bricolaj.ro",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Strada Caracal Nr.52",
    "postalCode": "207220",
    "addressLocality": "Dăbuleni",
    "addressRegion": "Dolj",
    "addressCountry": "RO"
  },
  "telephone": "+40749130565",
  "email": "dunlite@gmail.com",
  "sameAs": ["https://www.facebook.com/byMUGUREL"],
  "openingHoursSpecification": [
    {
      "@type": "OpeningHoursSpecification",
      "dayOfWeek": ["Monday","Tuesday","Wednesday","Thursday","Friday"],
      "opens": "08:00",
      "closes": "18:00"
    }
  ]
}
</script>
HTML;
require __DIR__ . '/templates/header.php';
?>
<!-- PAGE HERO -->
<div class="page-hero">
  <div class="page-hero-inner">
    <div class="breadcrumb"><a href="index.html">Acasa</a><span>›</span> Contact</div>
    <h1>Contacteaza-ne</h1>
    <p>Suntem aici pentru tine. Scrie-ne pe WhatsApp sau trimite-ne un email.</p>
  </div>
</div>

<!-- CONTENT -->
<div class="body-wrap" style="flex-direction:column; max-width:900px;">

  <!-- WA CTA PRINCIPAL -->
  <div class="wa-cta-card" style="margin-bottom:24px;">
    <h3>Cea mai rapida cale: WhatsApp</h3>
    <p>Trimite-ne un mesaj pe WhatsApp si iti raspundem in cel mai scurt timp. Poti cere oferte, verifica disponibilitatea produselor sau plasa o comanda direct din chat.</p>
    <a href="https://wa.me/40749130565?text=Buna%20ziua!%20Va%20contactez%20de%20pe%20site-ul%20Mugurel.%20Puteti%20sa%20ma%20ajutati%3F" class="btn-wa-big" target="_blank" rel="noopener">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
      Scrie pe WhatsApp — 0749 130 565
    </a>
  </div>

  <!-- GRID CONTACT -->
  <div class="contact-grid">
    <div class="contact-card">
      <h3>Date de contact</h3>
      <div class="contact-row">
        <div class="contact-row-icon">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
        </div>
        <div class="contact-row-info">
          <div class="label">WhatsApp / Telefon</div>
          <div class="value"><a href="https://wa.me/40749130565">0749 130 565</a></div>
        </div>
      </div>
      <div class="contact-row">
        <div class="contact-row-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        </div>
        <div class="contact-row-info">
          <div class="label">Email</div>
          <div class="value"><a href="mailto:dunlite@gmail.com">dunlite@gmail.com</a></div>
        </div>
      </div>
      <div class="contact-row">
        <div class="contact-row-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="contact-row-info">
          <div class="label">Program</div>
          <div class="value">Luni – Vineri: 8:00 – 18:00</div>
        </div>
      </div>
      <div class="contact-row">
        <div class="contact-row-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        </div>
        <div class="contact-row-info">
          <div class="label">Adresa</div>
          <div class="value">Strada Caracal Nr.52, 207220 Dăbuleni, Jud. Dolj</div>
        </div>
      </div>
      <div class="contact-row">
        <div class="contact-row-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
        </div>
        <div class="contact-row-info">
          <div class="label">Facebook</div>
          <div class="value"><a href="https://www.facebook.com/byMUGUREL" target="_blank" rel="noopener">facebook.com/byMUGUREL</a></div>
        </div>
      </div>

      <div class="map-container">
        <iframe src="https://maps.google.com/maps?q=Strada+Caracal+52+Dabuleani+Dolj+Romania&output=embed" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Locatie Mugurel Dabuleni"></iframe>
      </div>
    </div>

    <div class="contact-card">
      <h3>Intrebari frecvente</h3>
      <div style="margin-bottom:20px;">
        <p style="font-weight:700;color:#222;margin-bottom:6px;font-size:14px;">Cum pot comanda un produs?</p>
        <p style="font-size:13px;color:#666;line-height:1.6;">Cel mai simplu este sa ne scriei pe WhatsApp la <a href="https://wa.me/40749130565" style="color:#06363A;font-weight:600;">0749 130 565</a>. Ne spui ce produs te intereseaza si iti confirmam disponibilitatea si pretul.</p>
      </div>
      <div style="margin-bottom:20px;">
        <p style="font-weight:700;color:#222;margin-bottom:6px;font-size:14px;">Se poate livra la adresa mea?</p>
        <p style="font-size:13px;color:#666;line-height:1.6;">Da, livrăm produsele direct la adresa ta. Contacteaza-ne pentru detalii despre livrare si costuri.</p>
      </div>
      <div style="margin-bottom:20px;">
        <p style="font-weight:700;color:#222;margin-bottom:6px;font-size:14px;">Pot vedea produsele fizic?</p>
        <p style="font-size:13px;color:#666;line-height:1.6;">Da, ne puteti vizita la magazin in programul nostru (L–V 8:00–18:00). Va rugam sa ne contactati in prealabil pentru disponibilitate.</p>
      </div>
      <div style="margin-bottom:20px;">
        <p style="font-weight:700;color:#222;margin-bottom:6px;font-size:14px;">Ce metode de plata acceptati?</p>
        <p style="font-size:13px;color:#666;line-height:1.6;">Acceptam plata cash, transfer bancar si card. Contactati-ne pentru detalii specifice comenzii dvs.</p>
      </div>
      <div>
        <p style="font-weight:700;color:#222;margin-bottom:6px;font-size:14px;">Cat dureaza pana primesc raspuns?</p>
        <p style="font-size:13px;color:#666;line-height:1.6;">Pe WhatsApp raspundem de obicei in cateva minute in timpul programului de lucru. In afara programului, raspundem a doua zi dimineata.</p>
      </div>
    </div>
  </div>

</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
