<div class="adm-stats">
  <div class="adm-stat">
    <div class="adm-stat__num"><?= $stats['produse_active'] ?></div>
    <div class="adm-stat__label">Produse active</div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat__num" style="color:var(--warn)"><?= $stats['produse_fara_imagine'] ?></div>
    <div class="adm-stat__label">Fara imagine</div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat__num"><?= $stats['utilizatori_activi'] ?></div>
    <div class="adm-stat__label">Utilizatori activi</div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat__num"><?= $stats['modificari_7_zile'] ?></div>
    <div class="adm-stat__label">Modificari (7 zile)</div>
  </div>
</div>

<?php if ($stats['produse_fara_imagine'] > 0): ?>
<div class="flash flash--error">
  <?= $stats['produse_fara_imagine'] ?> produse fara imagine.
  <a href="/admin/?page=products&q=&cat=0" style="color:inherit;font-weight:700">Completeaza &rarr;</a>
</div>
<?php endif; ?>

<div class="adm-card">
  <h2>Activitate recenta</h2>
  <table class="adm-table">
    <thead><tr><th>Data</th><th>Utilizator</th><th>Actiune</th><th>Entitate</th></tr></thead>
    <tbody>
    <?php foreach ($recentLog as $log): ?>
    <tr>
      <td><?= $log['created_at'] ?></td>
      <td><?= htmlspecialchars($log['username']??'&mdash;',ENT_QUOTES,'UTF-8') ?></td>
      <td><?= htmlspecialchars($log['action'],ENT_QUOTES,'UTF-8') ?></td>
      <td><?= htmlspecialchars($log['entity_type'],ENT_QUOTES,'UTF-8') ?>
          <?= $log['entity_id'] ? '#'.$log['entity_id'] : '' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
