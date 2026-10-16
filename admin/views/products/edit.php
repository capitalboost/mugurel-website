<?php
// Variabile: $product (null = produs nou), $old (ce s-a trimis inainte de o eroare de validare), $categoryOptions
$h = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$isEdit = !empty($product);
$v = function (string $k, $default = '') use ($old, $product) {
    if ($old !== null) { return $old[$k] ?? $default; }
    return $product[$k] ?? $default;
};
if ($old !== null) {
    $specs = [];
    foreach ((array) ($old['spec_key'] ?? []) as $i => $k) { $specs[] = ['spec_key' => $k, 'spec_value' => ($old['spec_value'] ?? [])[$i] ?? '']; }
    $docs = (string) ($old['documents'] ?? '');
    $tags = (string) ($old['tags'] ?? '');
} else {
    $specs = $product['specs'] ?? [];
    $docs = implode("\n", $product['documents'] ?? []);
    $tags = implode('; ', array_column($product['tags'] ?? [], 'name'));
}
$specs[] = ['spec_key' => '', 'spec_value' => '']; // un rand gol pentru adaugare
$priceVal = $v('price');
if ($old === null && $priceVal !== '' && $priceVal !== null) { $priceVal = number_format((float) $priceVal, 2, ',', ''); }
?>
<div class="adm-card">
  <div class="adm-head">
    <h2><?= $isEdit ? 'Editare: ' . $h($product['name']) : 'Produs nou' ?></h2>
    <div class="adm-head__actions">
      <?php if ($isEdit && $product['slug'] && $product['is_visible']): ?><a href="/produs/<?= $h($product['slug']) ?>.html" class="btn btn--light" target="_blank" rel="noopener">Vezi pe site</a><?php endif; ?>
      <a href="/admin/?page=products" class="btn btn--light">Inapoi la lista</a>
    </div>
  </div>

  <?php if ($isEdit && $product['verify_status'] && $product['verify_status'] !== 'ok'): ?>
  <div class="flash flash--warn">
    <strong><?= $product['verify_status'] === 'negasit' ? 'Produs negasit la sursa' : 'De verificat' ?>:</strong>
    descrierea si specificatiile vin din fisierul de import si n-au fost inca verificate. Cand totul e corect, alege „Verificat" mai jos.
  </div>
  <?php endif; ?>

  <form method="POST" action="/admin/?page=products&action=save" enctype="multipart/form-data" class="adm-form">
    <?= Csrf::input() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $product['id'] ?>"><?php endif; ?>

    <fieldset class="adm-fieldset">
      <legend>Produs</legend>
      <label>Denumire pe site *
        <input type="text" name="name" maxlength="200" required value="<?= $h($v('name')) ?>">
      </label>
      <div class="form-row">
        <label>Categorie *
          <select name="category_id" required>
            <option value="">— alege —</option>
            <?php foreach ($categoryOptions as $c): ?>
            <option value="<?= $c['id'] ?>"<?= (int) $v('category_id', 0) === $c['id'] ? ' selected' : '' ?>><?= str_repeat('— ', $c['depth']) . $h($c['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Producator (brand)
          <input type="text" name="manufacturer" maxlength="120" value="<?= $h($v('manufacturer')) ?>">
        </label>
      </div>
      <div class="form-row">
        <label>Cod de bare <?= $isEdit ? '' : '<span class="adm-muted">(gol = se genereaza un cod intern)</span>' ?>
          <input type="text" name="barcode" maxlength="32" pattern="[0-9A-Za-z\-]{4,32}" value="<?= $h($v('barcode')) ?>">
        </label>
        <label>SKU
          <input type="text" name="sku" maxlength="64" value="<?= $h($v('sku')) ?>">
        </label>
        <label>Tip produs
          <input type="text" name="product_type" maxlength="120" value="<?= $h($v('product_type')) ?>">
        </label>
      </div>
      <?php if ($isEdit): ?><p class="adm-muted adm-small">Adresa pe site: /produs/<?= $h($product['slug']) ?>.html (nu se schimba la redenumire, ca link-urile vechi sa mearga)</p><?php endif; ?>
    </fieldset>

    <fieldset class="adm-fieldset">
      <legend>Pret si stoc</legend>
      <div class="form-row">
        <label>Pret cu TVA (lei) <span class="adm-muted">— gol = „Pret la cerere", fara cos</span>
          <input type="text" name="price" inputmode="decimal" value="<?= $h($priceVal) ?>" placeholder="ex. 25,24">
        </label>
        <label>Unitate de vanzare
          <input type="text" name="price_unit" maxlength="40" value="<?= $h($v('price_unit')) ?>" placeholder="bucata, sac, kg, m²">
        </label>
        <?php
          // stoc numarat (stock_qty) => optiunea „Numar pe stoc"; altfel starea aleasa de mana
          $stockMode = $old !== null ? (string) ($old['stock_status'] ?? 'in_stoc')
              : (($product['stock_qty'] ?? null) !== null ? 'cantitate' : (string) ($product['stock_status'] ?? 'in_stoc'));
          $stockQtyVal = $old !== null ? (string) ($old['stock_qty'] ?? '')
              : (($product['stock_qty'] ?? null) !== null ? formatQty((float) $product['stock_qty']) : '');
        ?>
        <label>Stoc
          <select name="stock_status" data-stock-mode>
            <?php foreach (CatalogProduct::STOCK as $k => $l): ?><option value="<?= $k ?>"<?= $stockMode === $k ? ' selected' : '' ?>><?= $l ?><?= $k === 'indisponibil' ? ' (fara stoc)' : '' ?></option><?php endforeach; ?>
            <option value="cantitate"<?= $stockMode === 'cantitate' ? ' selected' : '' ?>>Numar pe stoc…</option>
          </select>
        </label>
        <label data-stock-qty<?= $stockMode === 'cantitate' ? '' : ' hidden' ?>>Cantitate pe stoc <span class="adm-muted">— in unitatea de vanzare; scade la fiecare comanda, la 0 devine „Indisponibil"</span>
          <input type="text" name="stock_qty" inputmode="decimal" value="<?= $h($stockQtyVal) ?>" placeholder="ex. 12">
        </label>
      </div>
      <div class="form-row">
        <label class="adm-check"><input type="checkbox" name="is_visible" value="1"<?= ($old !== null ? !empty($old['is_visible']) : (int) ($product['is_visible'] ?? 1)) ? ' checked' : '' ?>> Vizibil pe site</label>
        <label>Verificare
          <select name="verify_status">
            <option value="">—</option>
            <?php foreach (CatalogProduct::VERIFY as $k => $l): ?><option value="<?= $k ?>"<?= $v('verify_status') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Ordine in lista <span class="adm-muted">(mai mic = mai sus)</span>
          <input type="number" name="sort_order" min="0" max="65535" value="<?= (int) $v('sort_order', 0) ?>">
        </label>
      </div>
    </fieldset>

    <fieldset class="adm-fieldset">
      <legend>Poza</legend>
      <?php if ($isEdit && $product['image_path']): ?>
      <img src="<?= $h($product['image_path']) ?>" alt="" class="adm-img-preview">
      <label class="adm-check"><input type="checkbox" name="remove_image" value="1"> Sterge poza</label>
      <?php endif; ?>
      <div class="form-row">
        <label><?= $isEdit && $product['image_path'] ? 'Inlocuieste poza' : 'Incarca poza' ?> <span class="adm-muted">(JPG, PNG, WEBP, max 2 MB)</span>
          <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
        </label>
        <label>Text alternativ (pentru Google si cititoare de ecran)
          <input type="text" name="image_alt" maxlength="200" value="<?= $h($v('image_alt')) ?>">
        </label>
      </div>
    </fieldset>

    <fieldset class="adm-fieldset">
      <legend>Descriere</legend>
      <label>Rezumat pe card <span class="adm-muted">(gol = primul paragraf din descriere)</span>
        <textarea name="short_description" rows="2" maxlength="1000"><?= $h($v('short_description')) ?></textarea>
      </label>
      <label>Descriere completa <span class="adm-muted">— HTML simplu: &lt;p&gt;, &lt;strong&gt;, &lt;ul&gt;&lt;li&gt;, &lt;h3&gt;; restul se elimina la salvare</span>
        <textarea name="description_html" rows="14" class="adm-mono"><?= $h($v('description_html')) ?></textarea>
      </label>
    </fieldset>

    <fieldset class="adm-fieldset">
      <legend>Specificatii tehnice</legend>
      <table class="adm-specs" id="specsTable">
        <thead><tr><th>Caracteristica</th><th>Valoare</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($specs as $s): ?>
          <tr>
            <td><input type="text" name="spec_key[]" maxlength="120" value="<?= $h($s['spec_key']) ?>" aria-label="Caracteristica"></td>
            <td><input type="text" name="spec_value[]" maxlength="2000" value="<?= $h($s['spec_value']) ?>" aria-label="Valoare"></td>
            <td class="adm-nowrap"><button type="button" class="btn btn--sm btn--light" data-spec-up aria-label="Mai sus">↑</button><button type="button" class="btn btn--sm btn--light" data-spec-del aria-label="Sterge randul">✕</button></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <button type="button" class="btn btn--sm btn--light" id="specAdd">+ Adauga rand</button>
    </fieldset>

    <fieldset class="adm-fieldset">
      <legend>Tag-uri si documente</legend>
      <label>Tag-uri <span class="adm-muted">— separate prin „;" (ex. Ceresit; grosime 1,5 mm; fatada). Devin filtre pe site; „fatada", „fier forjat" etc. leaga produsul de colectii.</span>
        <textarea name="tags" rows="3"><?= $h($tags) ?></textarea>
      </label>
      <label>Fise tehnice (PDF) <span class="adm-muted">— cate un link pe rand</span>
        <textarea name="documents" rows="3" class="adm-mono"><?= $h($docs) ?></textarea>
      </label>
    </fieldset>

    <fieldset class="adm-fieldset">
      <legend>Note interne <span class="adm-muted">(nu apar pe site)</span></legend>
      <label>Observatii
        <textarea name="internal_notes" rows="4"><?= $h($v('internal_notes')) ?></textarea>
      </label>
      <?php if ($isEdit && !empty($product['source_urls'])): ?>
      <p class="adm-small"><strong>Surse folosite la descriere:</strong><br>
        <?php foreach (explode("\n", $product['source_urls']) as $u): ?><a href="<?= $h($u) ?>" target="_blank" rel="noopener noreferrer"><?= $h($u) ?></a><br><?php endforeach; ?>
      </p>
      <?php endif; ?>
      <?php if ($isEdit && $product['original_name']): ?><p class="adm-small"><strong>Denumire din gestiune:</strong> <?= $h($product['original_name']) ?></p><?php endif; ?>
    </fieldset>

    <div class="adm-actions">
      <button type="submit" class="btn btn--primary">Salveaza</button>
      <a href="/admin/?page=products" class="btn btn--light">Anuleaza</a>
    </div>
  </form>

  <?php if ($isEdit): ?>
  <form method="POST" action="/admin/?page=products&action=delete" class="adm-delete" onsubmit="return confirm('Stergi definitiv produsul? Dispare de pe site. Daca vrei doar sa-l ascunzi, debifeaza „Vizibil pe site\".')">
    <?= Csrf::input() ?>
    <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
    <button type="submit" class="btn btn--sm btn--danger">Sterge produsul</button>
  </form>
  <?php endif; ?>
</div>
<script>
(function () {
  var body = document.querySelector('#specsTable tbody');
  document.getElementById('specAdd').addEventListener('click', function () {
    var row = body.rows[body.rows.length - 1].cloneNode(true);
    row.querySelectorAll('input').forEach(function (i) { i.value = ''; });
    body.appendChild(row);
    row.querySelector('input').focus();
  });
  body.addEventListener('click', function (e) {
    var row = e.target.closest('tr');
    if (!row) { return; }
    if (e.target.hasAttribute('data-spec-del')) {
      if (body.rows.length > 1) { row.remove(); } else { row.querySelectorAll('input').forEach(function (i) { i.value = ''; }); }
    } else if (e.target.hasAttribute('data-spec-up') && row.previousElementSibling) {
      body.insertBefore(row, row.previousElementSibling);
    }
  });
  // „Numar pe stoc…" => apare campul de cantitate
  var mode = document.querySelector('[data-stock-mode]'), qty = document.querySelector('[data-stock-qty]');
  mode.addEventListener('change', function () {
    qty.hidden = mode.value !== 'cantitate';
    if (!qty.hidden) { qty.querySelector('input').focus(); }
  });
})();
</script>
