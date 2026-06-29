<div class="adm-card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
    <h2>Utilizatori (<?= count($users) ?>)</h2>
    <a href="/admin/?page=users&action=new" class="btn btn--primary">+ Utilizator nou</a>
  </div>
  <table class="adm-table">
    <thead><tr><th>Username</th><th>Email</th><th>Rol</th><th>Status</th><th>Ultima autentificare</th><th>Actiuni</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><?= htmlspecialchars($u['username'],ENT_QUOTES,'UTF-8') ?></td>
      <td><?= htmlspecialchars($u['email'],ENT_QUOTES,'UTF-8') ?></td>
      <td><?= $u['role'] === 'admin' ? '<strong>Admin</strong>' : 'Editor' ?></td>
      <td><?= $u['is_active'] ? 'Activ' : 'Inactiv' ?></td>
      <td><?= $u['last_login_at'] ?? 'Niciodata' ?></td>
      <td>
        <a href="/admin/?page=users&action=edit&id=<?= $u['id'] ?>" class="btn btn--sm btn--primary">Editeaza</a>
        <?php if ($u['id'] !== Auth::id()): ?>
        <form method="POST" action="/admin/?page=users&action=toggle" style="display:inline">
          <?= Csrf::input() ?>
          <input type="hidden" name="id" value="<?= $u['id'] ?>">
          <button type="submit" class="btn btn--sm" style="background:#eee">
            <?= $u['is_active'] ? 'Dezactiveaza' : 'Activeaza' ?>
          </button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
