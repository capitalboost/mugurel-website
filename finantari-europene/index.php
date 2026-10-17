<?php
/**
 * /finantari-europene/ — pagina proiectului DIGITALIZARE DUNLITE COM (identitate vizuala UE/Regio obligatorie).
 * Pe sabloanele comune ale site-ului (meniu, footer, ANPC, „Cerere de retur") din 18 oct 2026; continutul e cel aprobat.
 */
require_once __DIR__ . '/../admin/config.php';
$pageTitle       = 'Finanțări europene — Digitalizare Dunlite Com | Mugurel';
$metaDescription = 'Proiect cofinanțat din Fondul European de Dezvoltare Regională — DIGITALIZARE DUNLITE COM. Programul Regional Sud-Vest Oltenia 2021-2027.';
$canonicalUrl    = SITE_URL . '/finantari-europene/';
$ogTitle         = 'Finanțări europene — Digitalizare Dunlite Com';
$extraHead = <<<'HTML'
<style>
  /* Pagina Finantari Europene — bloc obligatoriu de identitate vizuala UE/Regio */
  .eu-page { max-width: 880px; margin: 0 auto; padding: 22px 20px 60px; }
  .eu-band { width: 100%; height: auto; display: block; }
  .eu-band-wrap { border-bottom: 1px solid #e2e2e2; padding-bottom: 16px; margin-bottom: 22px; }
  .eu-date { text-align: right; font-size: 12px; color: #888; margin: 6px 0 24px; }
  .eu-title { font-family: 'Roboto Condensed', sans-serif; color: var(--verde); font-size: 26px; font-weight: 700; margin: 0 0 6px; }
  .eu-subtitle { font-family: 'Roboto Condensed', sans-serif; color: var(--text); font-size: 17px; font-weight: 700; margin: 0 0 24px; }
  .eu-facts { width: 100%; border-collapse: collapse; font-size: 14px; margin: 20px 0; }
  .eu-facts tr { border-bottom: 1px solid #ececec; }
  .eu-facts td { padding: 9px 6px; vertical-align: top; }
  .eu-facts td:first-child { font-weight: 700; color: var(--verde); width: 38%; }
  .eu-text p { margin: 0 0 14px; line-height: 1.65; }
  .eu-objectives { margin: 0 0 14px; padding-left: 20px; line-height: 1.65; }
  .eu-objectives li { margin-bottom: 6px; }
  .eu-pdf-btn { display: inline-flex; align-items: center; gap: 8px; background: var(--verde); color: #fff; font-size: 14px; font-weight: 700; padding: 12px 24px; border-radius: var(--radius); text-decoration: none; margin: 18px 0 36px; transition: background 150ms; }
  .eu-pdf-btn:hover { background: var(--verde-mid); }
  .eu-pdf-btn svg { width: 18px; height: 18px; }
  .eu-mfe-bar { background: var(--verde-mid); color: #fff; text-align: center; font-size: 13px; font-weight: 600; padding: 12px 16px; border-radius: var(--radius); margin: 30px 0 18px; }
  .eu-mfe-bar a { color: #fff; text-decoration: underline; }
  .eu-footer-band { width: 100%; height: auto; display: block; margin-top: 10px; }
  .eu-disclaimer { font-size: 12px; color: #888; line-height: 1.5; text-align: center; margin: 14px 0 0; }
  .eu-section-title { font-family: 'Roboto Condensed', sans-serif; color: var(--verde); font-size: 21px; font-weight: 700; margin: 38px 0 16px; padding-top: 24px; border-top: 1px solid #e2e2e2; }
  .eu-investitie p { margin: 0 0 14px; line-height: 1.65; }
  .eu-obj-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 0 0 24px; }
  .eu-obj-card { background: var(--verde-light); border-radius: var(--radius); padding: 14px 16px; font-size: 14px; line-height: 1.5; }
  .eu-obj-card strong { display: block; color: var(--verde); margin-bottom: 4px; }
  .eu-tech-card { border: 1px solid #e2e2e2; border-radius: var(--radius); padding: 16px 18px; margin-bottom: 14px; }
  .eu-tech-card h3 { font-family: 'Roboto Condensed', sans-serif; color: var(--verde); font-size: 16px; font-weight: 700; margin: 0 0 10px; }
  .eu-tech-card p { margin: 0 0 8px; font-size: 14px; line-height: 1.6; }
  .eu-tech-card p:last-child { margin-bottom: 0; }
  .eu-tech-card .eu-tech-label { font-weight: 700; color: var(--rosu); }
  @media (max-width: 600px) { .eu-obj-grid { grid-template-columns: 1fr; } }

  /* Bara MFE secundara */
  .eu-mfe-bar--mfe { background: #003399; margin-top: 8px; }

  /* Beneficiar in comunicat */
  .eu-beneficiar { display: flex; align-items: center; gap: 16px; margin-top: 20px; padding-top: 16px; border-top: 1px solid #e2e2e2; }
  .eu-beneficiar-logo { height: 40px; width: auto; flex-shrink: 0; }
  .eu-beneficiar-info { font-size: 13px; line-height: 1.6; }
  .eu-beneficiar-info strong { display: block; color: var(--verde); }
  .eu-beneficiar-info a { color: var(--verde); text-decoration: none; }
  .eu-beneficiar-info a:hover { text-decoration: underline; }
  @media (max-width: 480px) { .eu-beneficiar { flex-direction: column; align-items: flex-start; gap: 10px; } }
</style>
HTML;
require __DIR__ . '/../templates/header.php';
?>

<!-- CONTINUT PAGINA FINANTARI EUROPENE -->
<div class="eu-page">

  <p class="eu-date">Iulie 2026</p>

  <h1 class="eu-title">Comunicat de presă</h1>
  <p class="eu-subtitle">Anunț închidere etapă de implementare proiect &bdquo;DIGITALIZARE DUNLITE COM&rdquo;</p>

  <table class="eu-facts">
    <tr><td>Titlul proiectului</td><td>DIGITALIZARE DUNLITE COM</td></tr>
    <tr><td>Numele beneficiarului</td><td>SC DUNLITE COM SRL</td></tr>
    <tr><td>Valoarea totală a proiectului</td><td>397.084,41 lei</td></tr>
    <tr><td>Finanțare nerambursabilă</td><td>357.268,00 lei, din care contribuția FEDR 303.677,80 lei</td></tr>
    <tr><td>Data începere proiect</td><td>10.04.2025</td></tr>
    <tr><td>Data finalizare proiect</td><td>09.07.2026</td></tr>
    <tr><td>Codul MySMIS</td><td>331805</td></tr>
  </table>

  <div class="eu-text">
    <p><strong>Obiectivul general al proiectului:</strong> Obiectivul general al Dunlite Com SRL în cadrul programului „Digitalizare în folosul IMM-urilor” este reprezentat de creșterea eficienței operaționale și a competitivității prin implementarea soluțiilor de digitalizare care optimizează procesele de afaceri, îmbunătățesc experiența clienților și facilitează luarea deciziilor bazate pe date.</p>

    <p><strong>Obiective specifice:</strong></p>
    <ul class="eu-objectives">
      <li>Integrarea unui sistem ERP pentru a centraliza și automatiza gestionarea resurselor, comenzilor, inventarului și operațiunilor financiare.</li>
      <li>Automatizarea proceselor de gestionare a inventarului și a lanțului de aprovizionare pentru a optimiza stocurile și a reduce costurile.</li>
      <li>Asigurarea securității datelor și protecția infrastructurii IT prin implementarea soluțiilor avansate de securitate cibernetică.</li>
    </ul>

    <p><strong>Rezultatele proiectului:</strong> Achiziție de echipamente IT și sistem ERP pentru digitalizarea activității.</p>

    <p><strong>Impactul proiectului:</strong></p>
    <ul class="eu-objectives">
      <li><strong>Social:</strong> impact asupra comunității locale prin oferirea de oportunități de formare profesională la locul de muncă (personalul va învăța să utilizeze echipamente IT).</li>
      <li><strong>Economic:</strong> creșterea veniturilor, datorită îmbunătățirii bunurilor/serviciilor oferite ca urmare a utilizării TIC.</li>
    </ul>

    <p>Proiect cofinanțat din Fondul European de Dezvoltare Regională, Prioritatea 2 – Digitalizare în beneficiul cetățenilor și al firmelor, Obiectivul specific 1.2 – Valorificarea avantajelor digitalizării, în beneficiul cetățenilor, al companiilor, al organizațiilor de cercetare și al autorităților publice.</p>

    <div class="eu-beneficiar">
      <img src="/img/logo.svg" alt="Mugurel — DUNLITE COM SRL" class="eu-beneficiar-logo">
      <div class="eu-beneficiar-info">
        <strong>SC DUNLITE COM SRL</strong><br>
        <a href="https://mugurel-bricolaj.ro">mugurel-bricolaj.ro</a> &nbsp;|&nbsp; <a href="mailto:office@mugurel-bricolaj.ro">office@mugurel-bricolaj.ro</a>
      </div>
    </div>
  </div>

  <h2 class="eu-section-title">Despre proiect și investiție</h2>
  <div class="eu-investitie">
    <p><strong>Activitatea principală autorizată</strong> a societății DUNLITE COM SRL este reprezentată de codul CAEN 4752 – Comerț cu amănuntul al articolelor de fierărie, al articolelor din sticlă și a celor pentru vopsit, în magazine specializate. Codul CAEN supus investiției prin proiect este 4752 și este autorizat la locația de implementare Dăbuleni, Oraș Dăbuleni, Strada Caracal, Nr. 66, Județul Dolj.</p>

    <p>Proiectul de digitalizare al Dunlite Com SRL își propune să realizeze următoarele obiective principale:</p>
    <div class="eu-obj-grid">
      <div class="eu-obj-card"><strong>Creșterea eficienței operaționale</strong>Automatizarea proceselor de business pentru a reduce costurile și a îmbunătăți productivitatea.</div>
      <div class="eu-obj-card"><strong>Îmbunătățirea calității serviciilor</strong>Implementarea de soluții digitale pentru a oferi clienților servicii mai rapide și de calitate superioară.</div>
      <div class="eu-obj-card"><strong>Transformare digitală completă</strong>Integrarea tehnologiilor moderne în toate aspectele operațiunilor companiei.</div>
      <div class="eu-obj-card"><strong>Dezvoltare durabilă și sustenabilă</strong>Utilizarea tehnologiilor ecologice pentru a minimiza impactul asupra mediului.</div>
    </div>

    <p><strong>Descrierea tehnică a investiției:</strong></p>

    <div class="eu-tech-card">
      <h3>Achiziția și implementarea unui ERP (Enterprise Resource Planning)</h3>
      <p><span class="eu-tech-label">Funcționalități:</span> gestionarea integrată a resurselor umane, financiare, logistice și a producției.</p>
      <p><span class="eu-tech-label">Beneficii:</span> optimizarea fluxurilor de lucru și centralizarea datelor pentru o decizie informată și rapidă.</p>
    </div>

    <div class="eu-tech-card">
      <h3>Implementarea unui Sistem de Management al Documentelor (DMS)</h3>
      <p><span class="eu-tech-label">Funcționalități:</span> stocarea electronică, gestionarea și arhivarea documentelor.</p>
      <p><span class="eu-tech-label">Beneficii:</span> acces rapid și securizat la documente, reducerea utilizării hârtiei.</p>
    </div>

    <div class="eu-tech-card">
      <h3>Infrastructura IT</h3>
      <p><span class="eu-tech-label">Echipamente:</span> servere, rețele de comunicații, echipamente de securitate cibernetică.</p>
      <p><span class="eu-tech-label">Software:</span> aplicații de colaborare, platforme de lucru la distanță și soluții de backup și recuperare date.</p>
      <p><span class="eu-tech-label">Beneficii:</span> asigurarea unui mediu IT sigur și eficient pentru operațiunile zilnice.</p>
    </div>
  </div>

  <a class="eu-pdf-btn" href="/img/proiect-digitalizare/comunicat-presa-digitalizare-dunlite-com.pdf" download target="_blank" rel="noopener">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    Descarcă comunicatul de presă (PDF)
  </a>

  <div class="eu-mfe-bar">
    Pentru informații detaliate despre Programul Regional Sud-Vest Oltenia, vă invităm să vizitați <a href="https://www.adroltenia.ro/" target="_blank" rel="noopener">www.adroltenia.ro</a>
  </div>

  <div class="eu-mfe-bar eu-mfe-bar--mfe">
    Pentru informații detaliate despre celelalte programe cofinanțate de Uniunea Europeană, vă invităm să vizitați <a href="https://www.mfe.gov.ro/" target="_blank" rel="noopener">www.mfe.gov.ro</a>
  </div>

  <p class="eu-disclaimer">Conținutul acestui material nu reprezintă în mod obligatoriu poziția oficială a Uniunii Europene sau a Guvernului României.</p>

  <img class="eu-footer-band" src="/img/proiect-digitalizare/footer_adr.png" alt="ADR Sud-Vest Oltenia — Dezvoltăm Oltenia! — Programul Regional Sud-Vest Oltenia 2021-2027">

</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
