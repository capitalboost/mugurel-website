<div class="adm-card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
    <h2>Produse (<?= count($products) ?>)</h2>
    <a href="/admin/?page=products&action=new" class="btn btn--primary">+ Produs nou</a>
  </div>

  <form method="GET" action="/admin/" style="display:flex;gap:.5rem;margin-bottom:1.2rem">
    <input type="hidden" name="page" value="products">
    <input type="text" name="q" value="<?= htmlspecialchars($search ?? '', ENT_QUOTES,'UTF-8') ?>" placeholder="Cauta produse...">
    <select name="cat">
      <option value="0">Toate categoriile</option>
      <?php foreach ($categories as $cat): ?>
      <option value="<?= $cat['id'] ?>" <?= ($catId??0)==$cat['id']?'selected':'' ?>>
        <?= htmlspecialchars($cat['name'], ENT_QUOTES,'UTF-8') ?>
      </option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn--primary">Filtreaza</button>
  </form>

  <table class="adm-table">
    <thead>
      <tr><th>Imagine</th><th>Nume</th><th>Categorie</th><th>Pret</th><th>Vizibil</th><th>Actiuni</th></tr>
    </thead>
    <tbody>
    <?php foreach ($products as $p): ?>
    <tr>
      <td>
        <?php if ($p['image_path']): ?>
        <img src="<?= htmlspecialchars($p['image_path'],ENT_QUOTES,'UTF-8') ?>" alt="">
        <?php else: ?>
        <span style="color:#aaa;font-size:.8rem">Fara imagine</span>
        <?php endif; ?>
      </td>
      <td><?= htmlspecialchars($p['name'],ENT_QUOTES,'UTF-8') ?></td>
      <td><?= htmlspecialchars($p['category_name'],ENT_QUOTES,'UTF-8') ?></td>
      <td><?= $p['price'] ? number_format($p['price'],2).' '.$p['price_unit'] : '&mdash;' ?></td>
      <td><?= $p['is_visible'] ? 'Da' : 'Nu' ?></td>
      <td>
        <a href="/admin/?page=products&action=edit&id=<?= $p['id'] ?>" class="btn btn--sm btn--primary">Editeaza</a>
        <form method="POST" action="/admin/?page=products&action=delete" style="display:inline"
              onsubmit="return confirm('Stergi produsul?')">
          <?= Csrf::input() ?>
          <input type="hidden" name="id" value="<?= $p['id'] ?>">
          <button type="submit" class="btn btn--sm btn--danger">Sterge</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($products)): ?>
    <tr><td colspan="6" style="text-align:center;color:#aaa;padding:2rem">Niciun produs gasit.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
