<?php
// Variabilă așteptată: $pageTitle (string), $bodyContent (capturată cu ob)
$flash = Flash::html();
?>
<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Admin', ENT_QUOTES, 'UTF-8') ?> — Mugurel Admin</title>
<link rel="stylesheet" href="/admin/assets/admin.css">
<meta name="robots" content="noindex,nofollow">
</head>
<body>
<nav class="adm-nav">
  <a class="adm-nav__brand" href="/admin/">Admin Mugurel</a>
  <div class="adm-nav__links">
    <a href="/admin/?page=products">Produse</a>
    <a href="/admin/?page=pages">Pagini</a>
    <?php if (Auth::hasRole('admin')): ?>
    <a href="/admin/?page=users">Utilizatori</a>
    <?php endif; ?>
    <span class="adm-nav__user"><?= htmlspecialchars(Auth::username(), ENT_QUOTES, 'UTF-8') ?></span>
    <a href="/admin/?page=logout" class="adm-nav__logout">Iesire</a>
  </div>
</nav>
<main class="adm-main">
  <?= $flash ?>
  <?= $bodyContent ?? '' ?>
</main>
</body>
</html>
