<div class="adm-card">
  <h2>Pagini editabile</h2>
  <table class="adm-table">
    <thead><tr><th>Titlu</th><th>Slug</th><th>Tip</th><th>Status</th><th>Ultima modificare</th><th>Actiuni</th></tr></thead>
    <tbody>
    <?php foreach ($pages as $p): ?>
    <tr>
      <td><?= htmlspecialchars($p['title'],ENT_QUOTES,'UTF-8') ?></td>
      <td><code>/<?= htmlspecialchars($p['slug'],ENT_QUOTES,'UTF-8') ?>.html</code></td>
      <td><?= $p['restricted'] ? '<span style="color:var(--danger)">Legal</span>' : 'General' ?></td>
      <td><?= $p['is_published'] ? 'Publicat' : 'Ascuns' ?></td>
      <td><?= $p['updated_at'] ?></td>
      <td>
        <?php if (!$p['restricted'] || Auth::hasRole('admin')): ?>
        <a href="/admin/?page=pages&action=edit&id=<?= $p['id'] ?>" class="btn btn--sm btn--primary">Editeaza</a>
        <?php else: ?>
        <span style="color:#aaa;font-size:.8rem">Doar admin</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
