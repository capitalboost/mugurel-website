<?php
/**
 * Politica de confidentialitate — rescrisa pe 16 oct 2026: comenzile online, cererile de retur, datele tehnice
 * (IP sters dupa 6 luni — orderPurgeTechnicalData()), analiza doar dupa acord (js/analytics.js), destinatarii reali.
 */
require_once __DIR__ . '/admin/config.php';
$pageTitle       = 'Politica de confidențialitate — Mugurel';
$metaDescription = 'Ce date personale prelucrează DUNLITE COM SRL (Mugurel) la comenzi, retururi și vizitarea site-ului, de ce, cât timp și ce drepturi ai.';
$canonicalUrl    = 'https://mugurel-bricolaj.ro/politica-confidentialitate.html';
$metaRobots      = 'noindex, follow';
require __DIR__ . '/templates/header.php';
?>
<div class="page-hero"><div class="page-hero-inner"><div class="breadcrumb"><a href="/index.html">Acasă</a><span>›</span> Politica de confidențialitate</div><h1>Politica de confidențialitate</h1><p>Ce date personale folosim, de ce, cât timp și ce drepturi ai.</p></div></div>

<div class="body-wrap" style="max-width:900px; flex-direction:column;">
  <div class="legal-content">
    <p class="updated">Ultima actualizare: 16 octombrie 2026</p>

    <h2>1. Cine prelucrează datele</h2>
    <p>Operatorul datelor este <strong>DUNLITE COM SRL</strong> („Mugurel”), cu sediul în Strada Caracal nr. 66, 207220 Dăbuleni, județul Dolj, CIF RO9027530, nr. de înregistrare la Registrul Comerțului J16/1357/1996.</p>
    <p>Pentru orice întrebare despre datele tale: email <a href="mailto:office@mugurel-bricolaj.ro">office@mugurel-bricolaj.ro</a>, telefon <a href="tel:+40749130565">0749 130 565</a>.</p>

    <h2>2. Ce date colectăm</h2>
    <ul>
      <li><strong>Comenzi online:</strong> nume, telefon, email, adresa de livrare (județ, localitate, adresă), metoda de plată, produsele comandate și mențiunile tale; pentru firme și denumirea, CUI-ul, numărul de înregistrare la Registrul Comerțului și sediul.</li>
      <li><strong>Cereri de retur:</strong> numărul comenzii sau al facturii, nume, telefon, email, adresa de unde ridică curierul, produsele returnate, data primirii, motivul (dacă îl spui) și, opțional, IBAN-ul pentru rambursare.</li>
      <li><strong>Mesaje pe telefon, WhatsApp sau email:</strong> datele pe care ni le dai când ceri o ofertă sau comanzi.</li>
      <li><strong>Date tehnice la trimiterea unei comenzi sau a unei cereri de retur:</strong> adresa IP și tipul de browser, pentru protecția formularelor împotriva abuzurilor și a comenzilor false.</li>
      <li><strong>Coșul de cumpărături</strong> se păstrează doar în browserul tău (localStorage) până trimiți comanda; nu ajunge la noi înainte de trimitere.</li>
      <li><strong>Date de navigare</strong> (pagini vizitate, dispozitiv, sursa vizitei, interacțiunea cu paginile) — <strong>doar dacă accepți</strong> cookie-urile de analiză (vezi secțiunea 7).</li>
    </ul>

    <h2>3. De ce le folosim și pe ce temei</h2>
    <ul>
      <li>procesarea, confirmarea telefonică și livrarea comenzilor; soluționarea retururilor și a reclamațiilor — <strong>executarea contractului</strong> (art. 6 alin. 1 lit. b GDPR);</li>
      <li>facturarea și evidența contabilă — <strong>obligație legală</strong> (art. 6 alin. 1 lit. c);</li>
      <li>protecția formularelor împotriva abuzurilor (adresa IP, limitarea numărului de trimiteri, verificarea „nu sunt robot” Cloudflare Turnstile) — <strong>interesul nostru legitim</strong> de a ține site-ul sigur (art. 6 alin. 1 lit. f);</li>
      <li>statistici despre folosirea site-ului — <strong>consimțământul tău</strong> (art. 6 alin. 1 lit. a), pe care îl poți retrage oricând.</li>
    </ul>
    <p>Nu folosim datele pentru decizii automate și nu le vindem.</p>

    <h2>4. Cui le transmitem</h2>
    <ul>
      <li>firmei de curierat sau transport, pentru livrarea comenzii și ridicarea produselor returnate (nume, telefon, adresă);</li>
      <li>furnizorului de găzduire a site-ului și a email-ului (Hostgate, România);</li>
      <li>Cloudflare, care protejează și accelerează site-ul (vede adresa IP a vizitatorilor); pe formularele de comandă și de retur, verificarea Cloudflare Turnstile analizează date tehnice despre browser și dispozitiv ca să deosebească oamenii de programele automate — fără cookie-uri de urmărire și fără reclame;</li>
      <li>Google (serviciul de email Gmail folosit de magazin pentru corespondență; Google Analytics doar cu acordul tău; harta Google Maps de pe pagina Contact, încărcată doar când apeși „Arată harta Google”) și Microsoft (Clarity, doar cu acordul tău);</li>
      <li>contabilului nostru și autorităților publice, când legea o cere.</li>
    </ul>
    <p>Unii dintre acești furnizori pot transfera date în afara Spațiului Economic European, pe baza clauzelor contractuale standard aprobate de Comisia Europeană sau a altor garanții prevăzute de GDPR.</p>

    <h2>5. Cât timp le păstrăm</h2>
    <ul>
      <li>comenzile și facturile — cât cere legislația financiar-contabilă (în prezent 5 sau 10 ani, în funcție de tipul documentului);</li>
      <li>cererile de retur și reclamațiile — 3 ani de la soluționare (termenul de prescripție);</li>
      <li>adresa IP și tipul de browser din comenzi și cereri — <strong>6 luni</strong>, apoi se șterg automat;</li>
      <li>evidența încercărilor de trimitere a formularelor (limitarea abuzurilor) — 10 minute;</li>
      <li>conversațiile pe WhatsApp și email — cât este necesar pentru comandă, garanție și reclamații.</li>
    </ul>

    <h2>6. Cum le protejăm</h2>
    <p>Site-ul folosește conexiune criptată (HTTPS), formularele sunt protejate împotriva trimiterilor automate, iar accesul la comenzi este limitat la personalul magazinului. Confirmarea comenzii poate fi văzută doar prin legătura unică primită pe email.</p>

    <h2 id="cookie">7. Cookie-uri și analiză</h2>
    <p><strong>Strict necesare</strong> (nu cer acord): reținerea alegerii tale privind cookie-urile, conținutul coșului (localStorage) și un cookie de sesiune care protejează formularele de comandă și de retur.</p>
    <p><strong>De analiză</strong> — se activează <strong>doar dacă apeși „Accept”</strong> în bannerul de la prima vizită:</p>
    <ul>
      <li><strong>Google Analytics 4</strong> (Google Ireland Limited) — statistici agregate: pagini vizitate, sursa vizitei, tipul dispozitivului, durata vizitei;</li>
      <li><strong>Microsoft Clarity</strong> (Microsoft Ireland Operations Limited) — hărți de căldură și înregistrări ale modului de navigare (derulare, clicuri); textul scris în formulare este mascat automat și nu este înregistrat.</li>
    </ul>
    <p>Dacă apeși „Refuz”, aceste instrumente nu se încarcă deloc. Îți poți schimba alegerea oricând din link-ul <a href="#cookie" data-cookie-settings>„Setări cookie”</a> din subsolul fiecărei pagini; dacă retragi acordul, ștergem cookie-urile de analiză din browserul tău.</p>

    <h2>8. Drepturile tale</h2>
    <p>Ai dreptul de acces la datele tale, la rectificarea lor, la ștergere („dreptul de a fi uitat”), la restricționarea prelucrării, la portabilitate, dreptul de a te opune prelucrării bazate pe interesul legitim și dreptul de a-ți retrage consimțământul. Ne poți scrie la <a href="mailto:office@mugurel-bricolaj.ro">office@mugurel-bricolaj.ro</a>; răspundem în cel mult o lună.</p>
    <p>Ai și dreptul să depui o plângere la Autoritatea Națională de Supraveghere a Prelucrării Datelor cu Caracter Personal — <a href="https://www.dataprotection.ro" target="_blank" rel="noopener">www.dataprotection.ro</a>.</p>

    <h2>9. Modificări</h2>
    <p>Când schimbăm această politică, publicăm aici versiunea nouă, cu data actualizării.</p>
  </div>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
