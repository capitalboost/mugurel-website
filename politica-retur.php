<?php
/** Pagina statica, trecuta pe sabloanele comune (header/meniu/footer generate din catalog). */
require_once __DIR__ . '/admin/config.php';
$pageTitle       = 'Politica de Retur — Mugurel';
$metaDescription = 'Politica de retur si returnare a produselor achizitionate de la Mugurel — DUNLITE COM SRL';
$canonicalUrl    = 'https://mugurel-bricolaj.ro/politica-retur.html';
$metaRobots      = 'noindex, follow';
require __DIR__ . '/templates/header.php';
?>
<div class="page-hero"><div class="page-hero-inner"><div class="breadcrumb"><a href="index.html">Acasa</a><span>›</span> Politica de Retur</div><h1>Politica de Retur</h1><p>Conditiile pentru returnarea sau schimbul produselor achizitionate.</p></div></div>

<div class="body-wrap" style="max-width:900px; flex-direction:column;">
  <div class="legal-content">
    <p class="updated">Ultima actualizare: 18 Iunie 2026</p>

    <h2>1. Dreptul de retragere (pentru consumatori)</h2>
    <p>Conform OUG 34/2014 (transpunerea Directivei 2011/83/UE), in cazul contractelor incheiate la distanta sau in afara spatiilor comerciale, consumatorii beneficiaza de un drept de retragere de <strong>14 zile calendaristice</strong> de la data primirii produsului, fara a fi necesara invocarea unui motiv.</p>
    <p>Pentru a exercita acest drept, notificati-ne prin WhatsApp la <strong>0749 130 565</strong> sau prin email la <strong>dunlite@gmail.com</strong>, indicand produsul pe care doriti sa il returnati si numarul comenzii.</p>

    <h2>2. Conditii de returnare</h2>
    <p>Produsul returnat trebuie sa indeplineasca urmatoarele conditii:</p>
    <ul>
      <li>Sa fie in starea originala, nefolosit si nedeteriorizat;</li>
      <li>Sa fie insotit de ambalajul original si de documentul de achizitie (factura/bon fiscal);</li>
      <li>Sa nu fi fost montat, taiat, vopsit sau modificat in orice fel;</li>
      <li>Sa nu prezinte urme de utilizare, murdarire sau deteriorare cauzata de client.</li>
    </ul>
    <p>Produsele care nu indeplinesc aceste conditii nu pot fi returnate.</p>

    <h2>3. Produse exceptate de la returnare</h2>
    <p>Nu pot fi returnate:</p>
    <ul>
      <li>Produsele confectionate la comanda sau personalizate;</li>
      <li>Produsele care au fost dezsigilate si nu pot fi returnate din motive de igiena;</li>
      <li>Produsele deteriorate din vina clientului;</li>
      <li>Produse cu ambalaj deteriorat sau lipsa.</li>
    </ul>

    <h2>4. Procedura de returnare</h2>
    <ol style="padding-left:20px; margin-bottom:16px; color:#444; font-size:14px; line-height:2;">
      <li>Contactati-ne prin WhatsApp sau email in termen de 14 zile de la primirea produsului;</li>
      <li>Va comunicam adresa de returnare si instructiunile;</li>
      <li>Expediati produsul in ambalaj adecvat, cu dovada de achizitie;</li>
      <li>Dupa verificarea produsului (max. 5 zile lucratoare), va comunicam decizia;</li>
      <li>In cazul acceptarii returului, rambursarea se efectueaza in max. 14 zile calendaristice.</li>
    </ol>

    <h2>5. Costul returnarii</h2>
    <p>Costul returului este suportat de client, cu exceptia situatiei in care produsul este defect sau nu corespunde comenzii confirmate.</p>

    <h2>6. Garantia produselor</h2>
    <p>Toate produsele beneficiaza de garantia producatorului. In caz de defecte aparute in perioada de garantie, va rugam sa ne contactati la <strong>0749 130 565</strong> sau <strong>dunlite@gmail.com</strong> pentru a initia procedura de garantie.</p>

    <h2>7. Contact retur</h2>
    <p>Pentru orice solicitare legata de retururi sau garantie, ne puteti contacta:</p>
    <ul>
      <li>WhatsApp: <a href="https://wa.me/40749130565" style="color:var(--verde);">0749 130 565</a></li>
      <li>Email: <a href="mailto:dunlite@gmail.com" style="color:var(--verde);">dunlite@gmail.com</a></li>
      <li>Program: Luni–Vineri 8:00–18:00</li>
    </ul>
  </div>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
