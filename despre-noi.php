<?php
/** Pagina statica, trecuta pe sabloanele comune (header/meniu/footer generate din catalog). */
require_once __DIR__ . '/admin/config.php';
$pageTitle       = 'Despre noi — Mugurel | Materiale de construcții și electrocasnice, Dăbuleni';
$metaDescription = 'Mugurel, brandul DUNLITE COM SRL din Dăbuleni: materiale de construcții și electrocasnice cu prețuri afișate, comandă online sau pe WhatsApp, livrare în județul Dolj.';
$canonicalUrl    = 'https://mugurel-bricolaj.ro/despre-noi.html';
$metaRobots      = 'index, follow';
$ogTitle         = 'Despre noi — Mugurel Materiale Constructii';
$ogDescription   = 'Materiale de construcții și electrocasnice în Dăbuleni. Comandă online sau pe WhatsApp la 0749 130 565.';
require_once __DIR__ . '/includes/home.php';
require __DIR__ . '/templates/header.php';
?>
<!-- PAGE HERO -->
<div class="page-hero">
  <div class="page-hero-inner">
    <div class="breadcrumb"><a href="index.html">Acasa</a><span>›</span> Despre noi</div>
    <h1>Despre Mugurel</h1>
    <p>Magazinul tău de materiale de construcții și electrocasnice din Dăbuleni.</p>
  </div>
</div>

<!-- CONTENT -->
<div class="body-wrap" style="flex-direction:column; max-width:900px;">
  <div class="about-content">
    <h2>Cine suntem</h2>
    <p>Mugurel este brandul comercial al DUNLITE COM SRL, magazin de materiale de construcții și electrocasnice din Dăbuleni. Avem produse pentru toate etapele unei construcții — de la zidărie și metal până la acoperiș și finisaje — și electrocasnice pentru casă.</p>
    <p>Ne-am construit reputatia pe baza unor principii simple: produse de calitate, preturi corecte si o relatie directa cu clientii nostri. Credem ca cel mai bun mod de a face afaceri este prin comunicare sincera si transparenta — de aceea te sunăm la fiecare comandă și îți răspundem direct pe WhatsApp.</p>
    <p>Indiferent ca esti un profesionist in constructii, un meserias sau pur si simplu renovezi casa, la Mugurel gasesti tot ce ai nevoie sub un singur acoperis.</p>

    <h2>Ce oferim</h2>
    <p>Gama noastra de produse acopera toate nevoile unei case:</p>
    <ul style="margin-left:20px; margin-bottom:16px; color:#444; font-size:14px; line-height:2;">
      <?= renderHomeOfferList() /* generata din catalog */ ?>
    </ul>

    <h2>Cum functionam</h2>
    <p>Alegi produsele de pe site — toate au prețul afișat cu TVA — și le pui în coș. Trimiți comanda cu datele de livrare și metoda de plată preferată: ramburs, transfer bancar sau ridicare din magazin. Te sunăm să confirmăm stocul și livrarea, apoi pregătim marfa. Preferi să vorbești direct? Scrie-ne pe WhatsApp.</p>

    <div class="about-values">
      <div class="value-card">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
        <h4>Calitate garantata</h4>
        <p>Lucram cu producatori si distribuitori verificati. Toate produsele vin cu garantia producatorului.</p>
      </div>
      <div class="value-card">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13 19.79 19.79 0 0 1 1.61 4.4 2 2 0 0 1 3.6 2.2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.16 6.16l1.87-1.87a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        <h4>Comunicare directa</h4>
        <p>Vorbesti direct cu noi pe WhatsApp. Fara call center, fara asteptari. Raspuns rapid in programul de lucru.</p>
      </div>
      <div class="value-card">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        <h4>Livrare în județul Dolj</h4>
        <p>Livrăm la adresa ta în tot județul Dolj. Costul transportului ți-l spunem la telefon, când confirmăm comanda.</p>
      </div>
    </div>
  </div>

  <!-- CTA -->
  <div style="margin-top:24px; text-align:center;">
    <a href="https://wa.me/40749130565?text=Buna%20ziua!%20Vreau%20sa%20cer%20o%20oferta%20de%20la%20Mugurel." class="btn-wa-hero" target="_blank" rel="noopener" style="font-size:15px; padding:13px 28px;">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
      Scrie-ne pe WhatsApp — 0749 130 565
    </a>
  </div>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
