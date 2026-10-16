<?php
// Variabile: $action, $report (text), $stats, $fileName, $errors, $token
$h = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="adm-card">
  <h2>Import produse din Excel</h2>

  <?php if ($action !== 'review'): ?>
  <p>Fisierul trebuie sa aiba acelasi format ca cele trimise pana acum (ex. <em>constructii_complet_MU.xlsx</em>): coloanele
    <strong>Cod de bare, Denumire site, Pret, Unitate de vanzare, Categorie</strong> (obligatorii), plus Producator, Tip produs,
    Tag-uri, Descriere, Specificatii tehnice, Documente, Status, Observatii.</p>
  <ul class="adm-list">
    <li>Produsele se recunosc dupa <strong>codul de bare</strong>: daca exista deja, se actualizeaza (nu se dubleaza).</li>
    <li>La actualizare raman neatinse <strong>poza, stocul si „vizibil pe site"</strong> — cele schimbate de tine din admin.</li>
    <li>Categoriile noi din coloana „Categorie" (ex. <em>Electrocasnice &gt; Frigidere</em>) se creeaza singure si apar in meniu.</li>
    <li>Produsele care nu sunt in fisier raman cum sunt.</li>
  </ul>
  <form method="POST" action="/admin/?page=import&action=preview" enctype="multipart/form-data" class="adm-form adm-upload">
    <?= Csrf::input() ?>
    <label>Fisier Excel (.xlsx, max 10 MB)
      <input type="file" name="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
    </label>
    <button type="submit" class="btn btn--primary">Verifica fisierul</button>
    <p class="adm-muted adm-small">Pasul 1 doar citeste fisierul si iti arata ce s-ar schimba. Nimic nu se scrie pana nu apesi „Aplica importul".</p>
  </form>

  <?php else: ?>
  <p>Fisier: <strong><?= $h($fileName) ?></strong></p>

  <?php if ($errors): ?>
  <div class="flash flash--error">
    <strong>Fisierul are <?= count($errors) ?> <?= count($errors) === 1 ? 'eroare' : 'erori' ?> — nu se poate importa.</strong> Corecteaza in Excel si incarca-l din nou.
    <ul class="adm-list"><?php foreach (array_slice($errors, 0, 50) as $e): ?><li><?= $h($e) ?></li><?php endforeach; ?></ul>
    <?php if (count($errors) > 50): ?><p>… si inca <?= count($errors) - 50 ?>.</p><?php endif; ?>
  </div>
  <?php else: ?>
  <div class="adm-stats">
    <div class="adm-stat"><div class="adm-stat__num"><?= (int) $stats['read'] ?></div><div class="adm-stat__label">Produse in fisier</div></div>
    <div class="adm-stat"><div class="adm-stat__num"><?= (int) $stats['created'] ?></div><div class="adm-stat__label">Produse noi</div></div>
    <div class="adm-stat"><div class="adm-stat__num"><?= (int) $stats['updated'] ?></div><div class="adm-stat__label">Se actualizeaza</div></div>
    <div class="adm-stat"><div class="adm-stat__num"><?= (int) $stats['categories_created'] ?></div><div class="adm-stat__label">Categorii noi</div></div>
    <div class="adm-stat"><div class="adm-stat__num"><?= (int) $stats['tags_created'] ?></div><div class="adm-stat__label">Tag-uri noi</div></div>
  </div>
  <details class="adm-report" open>
    <summary>Raportul complet (categorii, tag-uri si grupele lor, ce s-a reparat, produse fara pret)</summary>
    <pre><?= $h($report) ?></pre>
  </details>
  <form method="POST" action="/admin/?page=import&action=apply" class="adm-actions" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Se aplica…';">
    <?= Csrf::input() ?>
    <input type="hidden" name="token" value="<?= $h($token) ?>">
    <button type="submit" class="btn btn--primary">Aplica importul</button>
  </form>
  <?php endif; ?>

  <form method="POST" action="/admin/?page=import&action=cancel" class="adm-actions">
    <?= Csrf::input() ?>
    <button type="submit" class="btn btn--light">Renunta / alt fisier</button>
  </form>
  <?php endif; ?>
</div>
