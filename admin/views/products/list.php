<?php
// Variabile: $filters, $result ['rows','total','pages','page'], $categoryOptions
$h = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$qs = function (array $over) use ($filters): string {
    $p = array_filter(array_merge(['page' => 'products', 'q' => $filters['q'], 'cat' => $filters['cat'] ?: '', 'verify' => $filters['verify'],
        'stock' => $filters['stock'], 'photo' => $filters['photo'], 'price' => $filters['price'], 'visible' => $filters['visible']], $over),
        fn ($v) => $v !== '' && $v !== null && $v !== 0);
    return '/admin/?' . http_build_query($p);
};
$sel = fn (string $k, string $v) => $filters[$k] === $v ? ' selected' : '';
?>
<div class="adm-card">
  <div class="adm-head">
    <h2>Produse <span class="adm-muted">(<?= (int) $result['total'] ?>)</span></h2>
    <div class="adm-head__actions">
      <a href="/admin/?page=import" class="btn btn--light">Import din Excel</a>
      <a href="/admin/?page=products&action=new" class="btn btn--primary">+ Produs nou</a>
    </div>
  </div>

  <form method="GET" action="/admin/" class="adm-filters">
    <input type="hidden" name="page" value="products">
    <input type="search" name="q" value="<?= $h($filters['q']) ?>" placeholder="Nume, cod de bare, SKU, brand..." aria-label="Cauta">
    <select name="cat" aria-label="Categorie">
      <option value="">Toate categoriile</option>
      <?php foreach ($categoryOptions as $c): ?>
      <option value="<?= $c['id'] ?>"<?= (int) $filters['cat'] === $c['id'] ? ' selected' : '' ?>><?= str_repeat('— ', $c['depth']) . $h($c['label']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="verify" aria-label="Verificare">
      <option value="">Orice verificare</option>
      <?php foreach (CatalogProduct::VERIFY as $k => $l): ?><option value="<?= $k ?>"<?= $sel('verify', $k) ?>><?= $l ?></option><?php endforeach; ?>
    </select>
    <select name="stock" aria-label="Stoc">
      <option value="">Orice stoc</option>
      <?php foreach (CatalogProduct::STOCK as $k => $l): ?><option value="<?= $k ?>"<?= $sel('stock', $k) ?>><?= $l ?></option><?php endforeach; ?>
    </select>
    <select name="photo" aria-label="Poza">
      <option value="">Cu/fara poza</option>
      <option value="fara"<?= $sel('photo', 'fara') ?>>Fara poza</option>
      <option value="cu"<?= $sel('photo', 'cu') ?>>Cu poza</option>
    </select>
    <select name="price" aria-label="Pret">
      <option value="">Orice pret</option>
      <option value="fara"<?= $sel('price', 'fara') ?>>Fara pret</option>
    </select>
    <select name="visible" aria-label="Vizibil">
      <option value="">Vizibile si ascunse</option>
      <option value="1"<?= $sel('visible', '1') ?>>Doar vizibile</option>
      <option value="0"<?= $sel('visible', '0') ?>>Doar ascunse</option>
    </select>
    <button type="submit" class="btn btn--primary">Filtreaza</button>
    <?php if ($qs([]) !== '/admin/?page=products'): ?><a href="/admin/?page=products" class="adm-link">Sterge filtrele</a><?php endif; ?>
  </form>

  <div class="adm-table-wrap">
  <table class="adm-table">
    <thead>
      <tr><th>Poza</th><th>Produs</th><th>Categorie</th><th>Pret</th><th>Stoc</th><th>Verificare</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($result['rows'] as $p): ?>
    <tr<?= $p['is_visible'] ? '' : ' class="is-hidden"' ?>>
      <td><?php if ($p['image_path']): ?><img src="<?= $h('/' . ltrim($p['image_path'], '/')) ?>" alt=""><?php else: ?><span class="adm-badge adm-badge--muted">fara</span><?php endif; ?></td>
      <td>
        <a href="/admin/?page=products&action=edit&id=<?= (int) $p['id'] ?>" class="adm-strong"><?= $h($p['name']) ?></a>
        <div class="adm-muted adm-small">Cod <?= $h($p['barcode']) ?><?= $p['manufacturer'] ? ' · ' . $h($p['manufacturer']) : '' ?><?= $p['is_visible'] ? '' : ' · <strong>ascuns pe site</strong>' ?></div>
      </td>
      <td class="adm-small"><?= $h($p['category_name']) ?></td>
      <td class="adm-nowrap"><?= $p['price'] !== null ? number_format((float) $p['price'], 2, ',', '.') . ' lei' . ($p['price_unit'] ? ' / ' . $h($p['price_unit']) : '') : '<span class="adm-badge adm-badge--warn">la cerere</span>' ?></td>
      <td class="adm-small"><?= $h(CatalogProduct::STOCK[$p['stock_status']] ?? $p['stock_status']) ?><?= $p['stock_qty'] !== null ? ' <span class="adm-muted">(' . $h(formatQty((float) $p['stock_qty'])) . ')</span>' : '' ?></td>
      <td><?php if ($p['verify_status'] === 'ok'): ?><span class="adm-badge adm-badge--ok">Verificat</span>
          <?php elseif ($p['verify_status'] === 'negasit'): ?><span class="adm-badge adm-badge--danger">Negasit</span>
          <?php elseif ($p['verify_status'] === 'de_verificat'): ?><span class="adm-badge adm-badge--warn">De verificat</span><?php endif; ?></td>
      <td class="adm-nowrap">
        <a href="/admin/?page=products&action=edit&id=<?= (int) $p['id'] ?>" class="btn btn--sm btn--primary">Editeaza</a>
        <?php if ($p['is_visible'] && $p['slug']): ?><a href="/produs/<?= $h($p['slug']) ?>.html" class="btn btn--sm btn--light" target="_blank" rel="noopener">Pe site</a><?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$result['rows']): ?>
    <tr><td colspan="7" class="adm-empty">Niciun produs gasit.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>

  <?php if ($result['pages'] > 1): ?>
  <nav class="adm-pages" aria-label="Pagini">
    <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
      <?php if ($i === $result['page']): ?><span class="is-current"><?= $i ?></span><?php else: ?><a href="<?= $h($qs(['p' => $i])) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
  </nav>
  <?php endif; ?>
</div>
