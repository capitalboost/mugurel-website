<?php
// $error disponibil din AuthController
?>
<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — Mugurel Admin</title>
<link rel="stylesheet" href="/admin/assets/admin.css">
<meta name="robots" content="noindex,nofollow">
</head>
<body class="adm-login-page">
<div class="adm-login-box">
  <h1>Admin Mugurel</h1>
  <?php if ($error): ?>
  <div class="flash flash--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <form method="POST" action="/admin/?page=login">
    <?= Csrf::input() ?>
    <label>Username
      <input type="text" name="username" autocomplete="username" required autofocus>
    </label>
    <label>Parola
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button type="submit">Intra in cont</button>
  </form>
</div>
</body>
</html>
