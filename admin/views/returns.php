<?php
// Variabile: $returns, $counts, $filter
$h = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$badge = ['noua' => 'adm-badge--warn', 'in_lucru' => 'adm-badge--muted', 'finalizata' => 'adm-badge--ok'];
?>
<div class="adm-card">
  <div class="adm-head">
    <h2>Cereri de retur <span class="adm-muted">(<?= count($returns) ?>)</span></h2>
    <div class="adm-head__actions">
      <a href="/admin/?page=returns" class="btn btn--sm <?= $filter === '' ? 'btn--primary' : 'btn--light' ?>">Toate</a>
      <?php foreach (RETURN_STATUS as $k => $l): ?>
      <a href="/admin/?page=returns&status=<?= $k ?>" class="btn btn--sm <?= $filter === $k ? 'btn--primary' : 'btn--light' ?>"><?= $l ?> (<?= (int) ($counts[$k] ?? 0) ?>)</a>
      <?php endforeach; ?>
    </div>
  </div>
  <p class="adm-muted adm-small">Fiecare cerere ajunge si pe email la comenzi@ (redirectionat spre dunlite@gmail.com). Curierul ridica produsele pe costul magazinului; la retragere, banii se returneaza in cel mult 14 zile de la cerere.</p>

  <?php if (!$returns): ?>
  <p class="adm-empty">Nicio cerere de retur<?= $filter ? ' cu starea „' . $h(RETURN_STATUS[$filter]) . '"' : '' ?>.</p>
  <?php endif; ?>

  <?php foreach ($returns as $r): ?>
  <details class="adm-return"<?= $r['status'] === 'noua' ? ' open' : '' ?>>
    <summary>
      <strong><?= $h($r['request_number']) ?></strong>
      <span class="adm-badge <?= $badge[$r['status']] ?>"><?= $h(RETURN_STATUS[$r['status']]) ?></span>
      <span><?= $h($r['nume']) ?> · <?= $h($r['telefon']) ?></span>
      <span class="adm-muted adm-small"><?= $h(date('d.m.Y H:i', strtotime($r['created_at']))) ?> · <?= $r['tip'] === 'neconform' ? 'produs neconform' : 'retragere 14 zile' ?></span>
    </summary>
    <table class="adm-table adm-return__table">
      <?php foreach (returnDetails($r) as $k => $v): ?>
      <tr><th><?= $h($k) ?></th><td><?= nl2br($h($v)) ?></td></tr>
      <?php endforeach; ?>
      <tr><th>Emailuri</th><td class="adm-small">magazin: <?= $r['email_admin_at'] ? $h($r['email_admin_at']) : '<strong>NETRIMIS</strong>' ?> · client: <?= $r['email_client_at'] ? $h($r['email_client_at']) : '<strong>NETRIMIS</strong>' ?></td></tr>
    </table>
    <form method="POST" action="/admin/?page=returns&action=status" class="adm-actions">
      <?= Csrf::input() ?>
      <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
      <?php if ($filter): ?><input type="hidden" name="filter" value="<?= $h($filter) ?>"><?php endif; ?>
      <?php foreach (RETURN_STATUS as $k => $l): if ($k === $r['status']) { continue; } ?>
      <button type="submit" name="status" value="<?= $k ?>" class="btn btn--sm <?= $k === 'finalizata' ? 'btn--primary' : 'btn--light' ?>">Marcheaza „<?= $l ?>"</button>
      <?php endforeach; ?>
      <a class="btn btn--sm btn--light" href="mailto:<?= $h($r['email']) ?>?subject=<?= rawurlencode('Retur ' . $r['request_number']) ?>">Scrie clientului</a>
    </form>
  </details>
  <?php endforeach; ?>
</div>
