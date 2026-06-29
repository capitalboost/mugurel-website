<?php $isEdit = !empty($user); ?>
<div class="adm-card">
  <h2><?= $isEdit ? 'Editare: '.htmlspecialchars($user['username'],ENT_QUOTES,'UTF-8') : 'Utilizator nou' ?></h2>
  <form method="POST" action="/admin/?page=users&action=save" class="adm-form">
    <?= Csrf::input() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $user['id'] ?>"><?php endif; ?>

    <label>Username *
      <input type="text" name="username" required maxlength="60"
             value="<?= htmlspecialchars($user['username']??'',ENT_QUOTES,'UTF-8') ?>">
    </label>
    <label>Email *
      <input type="email" name="email" required maxlength="180"
             value="<?= htmlspecialchars($user['email']??'',ENT_QUOTES,'UTF-8') ?>">
    </label>
    <label>Parola <?= $isEdit ? '(lasa gol pentru a pastra parola actuala)' : '* minim 8 caractere' ?>
      <input type="password" name="password" <?= $isEdit?'':'required' ?> minlength="8" autocomplete="new-password">
    </label>
    <label>Rol
      <select name="role">
        <option value="editor" <?= (!$isEdit||$user['role']==='editor')?'selected':'' ?>>Editor</option>
        <option value="admin"  <?= ($isEdit&&$user['role']==='admin')?'selected':'' ?>>Admin</option>
      </select>
    </label>
    <label style="flex-direction:row;align-items:center;gap:.5rem">
      <input type="checkbox" name="is_active" value="1" <?= (!$isEdit||$user['is_active'])?'checked':'' ?>>
      Cont activ
    </label>
    <div style="margin-top:1.5rem;display:flex;gap:1rem">
      <button type="submit" class="btn btn--primary">Salveaza</button>
      <a href="/admin/?page=users" class="btn" style="background:#eee">Anuleaza</a>
    </div>
  </form>
</div>
