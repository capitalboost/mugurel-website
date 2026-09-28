<?php $isEdit = !empty($product); ?>
<div class="adm-card">
  <h2><?= $isEdit ? 'Editare: '.htmlspecialchars($product['name'],ENT_QUOTES,'UTF-8') : 'Produs nou' ?></h2>
  <form method="POST" action="/admin/?page=products&action=save" enctype="multipart/form-data" class="adm-form">
    <?= Csrf::input() ?>
    <?php if ($isEdit): ?>
    <input type="hidden" name="id" value="<?= $product['id'] ?>">
    <?php endif; ?>

    <label>Categorie *
      <select name="category_id" required>
        <option value="">— alege —</option>
        <?php foreach ($categoryTree as $l1): ?>
        <optgroup label="<?= htmlspecialchars($l1['name'],ENT_QUOTES,'UTF-8') ?>">
          <option value="<?= $l1['id'] ?>"
            <?= ($isEdit && $product['category_id']==$l1['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($l1['name'],ENT_QUOTES,'UTF-8') ?>
          </option>
          <?php foreach ($l1['children'] as $l2): ?>
          <option value="<?= $l2['id'] ?>"
            <?= ($isEdit && $product['category_id']==$l2['id']) ? 'selected' : '' ?>>
            &nbsp;&nbsp;— <?= htmlspecialchars($l2['name'],ENT_QUOTES,'UTF-8') ?>
          </option>
          <?php endforeach; ?>
        </optgroup>
        <?php endforeach; ?>
      </select>
    </label>

    <label>Nume produs *
      <input type="text" name="name" maxlength="200" required
             value="<?= htmlspecialchars($product['name']??'',ENT_QUOTES,'UTF-8') ?>">
    </label>

    <label>Descriere scurta
      <textarea name="short_description" rows="4"><?= htmlspecialchars($product['short_description']??'',ENT_QUOTES,'UTF-8') ?></textarea>
    </label>

    <label>Imagine produs
      <?php if ($isEdit && $product['image_path']): ?>
      <img src="<?= htmlspecialchars($product['image_path'],ENT_QUOTES,'UTF-8') ?>" class="adm-img-preview" id="imgPreview">
      <?php else: ?>
      <img src="" class="adm-img-preview" id="imgPreview" style="display:none">
      <?php endif; ?>
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"
             onchange="previewImg(this)">
      <small>JPG, PNG, WebP, GIF — max 2MB</small>
    </label>

    <label>Text alternativ imagine (alt)
      <input type="text" name="image_alt" maxlength="200"
             value="<?= htmlspecialchars($product['image_alt']??'',ENT_QUOTES,'UTF-8') ?>">
    </label>

    <div class="form-row">
      <label>Pret (RON)
        <input type="number" name="price" step="0.01" min="0"
               value="<?= $product['price']??'' ?>">
      </label>
      <label>Unitate
        <input type="text" name="price_unit" maxlength="40" placeholder="mp, ml, buc..."
               value="<?= htmlspecialchars($product['price_unit']??'',ENT_QUOTES,'UTF-8') ?>">
      </label>
      <label>Ordine afisare
        <input type="number" name="sort_order" min="0"
               value="<?= $product['sort_order']??0 ?>">
      </label>
    </div>

    <label style="flex-direction:row;align-items:center;gap:.5rem">
      <input type="checkbox" name="is_visible" value="1"
             <?= (!$isEdit || $product['is_visible']) ? 'checked' : '' ?>>
      Vizibil pe site
    </label>

    <hr style="margin:1.5rem 0;border:none;border-top:1px solid var(--border)">
    <h3 style="font-size:1rem;color:var(--verde);margin-bottom:1rem">Unde apare si cum arata</h3>

    <label>Pagina *
      <select name="page_slug" required>
        <option value="">— alege —</option>
        <?php foreach ($knownPageSlugs as $slug): ?>
        <option value="<?= htmlspecialchars($slug,ENT_QUOTES,'UTF-8') ?>"
          <?= ($isEdit && ($product['page_slug']??'')===$slug) ? 'selected' : '' ?>>
          <?= $slug==='catalog' ? 'Doar in catalog' : htmlspecialchars($slug,ENT_QUOTES,'UTF-8') ?>
        </option>
        <?php endforeach; ?>
      </select>
    </label>

    <div class="form-row">
      <label>Sectiune existenta
        <select name="section_key_existing">
          <option value="">— fara —</option>
          <?php foreach ($sectionsByPage as $pgSlug => $keys): ?>
          <optgroup label="<?= htmlspecialchars($pgSlug,ENT_QUOTES,'UTF-8') ?>">
            <?php foreach ($keys as $key): ?>
            <option value="<?= htmlspecialchars($key,ENT_QUOTES,'UTF-8') ?>"
              <?= ($isEdit && ($product['section_key']??'')===$key) ? 'selected' : '' ?>>
              <?= htmlspecialchars($key,ENT_QUOTES,'UTF-8') ?>
            </option>
            <?php endforeach; ?>
          </optgroup>
          <?php endforeach; ?>
        </select>
      </label>
      <label>...sau o sectiune noua
        <input type="text" name="section_key_new" maxlength="60" placeholder="ex. produse-noi">
      </label>
    </div>
    <small style="display:block;margin:-.8rem 0 1.2rem;color:var(--warn)">
      Atentie: o sectiune noua nu apare automat pe site. Cineva trebuie sa adauge apelul ei in
      fisierul .php al paginii — pana atunci, produsul nu se vede nicaieri.
    </small>

    <label>Tip card *</label>
    <div class="form-row" style="margin-top:-.8rem;margin-bottom:1.2rem">
      <label style="flex-direction:row;align-items:center;gap:.5rem;flex:none">
        <input type="radio" name="card_kind" value="material"
               <?= (!$isEdit || ($product['card_kind']??'material')==='material') ? 'checked' : '' ?>>
        Material
      </label>
      <label style="flex-direction:row;align-items:center;gap:.5rem;flex:none">
        <input type="radio" name="card_kind" value="accessory"
               <?= ($isEdit && ($product['card_kind']??'')==='accessory') ? 'checked' : '' ?>>
        Accesoriu
      </label>
    </div>

    <label>Varianta card (doar pentru accesorii)
      <select name="card_modifier">
        <option value="">— normal —</option>
        <option value="sm" <?= ($isEdit && ($product['card_modifier']??'')==='sm') ? 'selected' : '' ?>>Mic (sm)</option>
      </select>
    </label>

    <label>Iconita
      <div class="icon-grid">
        <label class="icon-grid__item">
          <input type="radio" name="icon_key" value=""
                 <?= (!$isEdit || empty($product['icon_key'])) ? 'checked' : '' ?>>
          <span class="icon-grid__preview">—</span>
          <span class="icon-grid__label">fara iconita</span>
        </label>
        <?php foreach ($productIcons as $key => $svg): ?>
        <label class="icon-grid__item">
          <input type="radio" name="icon_key" value="<?= htmlspecialchars($key,ENT_QUOTES,'UTF-8') ?>"
                 <?= ($isEdit && ($product['icon_key']??'')===$key) ? 'checked' : '' ?>>
          <span class="icon-grid__preview"><?= $svg ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </label>

    <label>Eticheta sub iconita
      <input type="text" name="icon_label" maxlength="120"
             value="<?= htmlspecialchars($product['icon_label']??'',ENT_QUOTES,'UTF-8') ?>">
    </label>

    <div class="form-row">
      <label>Eticheta badge
        <input type="text" name="badge_label" maxlength="60" placeholder='ex. "Cel mai vandut"'
               value="<?= htmlspecialchars($product['badge_label']??'',ENT_QUOTES,'UTF-8') ?>">
      </label>
      <label>Tip badge
        <select name="badge_kind">
          <option value="">— fara —</option>
          <?php foreach (['popular'=>'Popular','premium'=>'Premium','new'=>'Nou','eco'=>'Eco'] as $val=>$lbl): ?>
          <option value="<?= $val ?>" <?= ($isEdit && ($product['badge_kind']??'')===$val) ? 'selected' : '' ?>>
            <?= $lbl ?>
          </option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <label>Mesaj WhatsApp personalizat
      <textarea name="wa_text" rows="2" maxlength="400"
                placeholder="Buna ziua! Sunt interesat de: <?= htmlspecialchars($product['name'] ?? 'NUME PRODUS', ENT_QUOTES,'UTF-8') ?>. Puteti confirma disponibilitatea?"><?= htmlspecialchars($product['wa_text']??'',ENT_QUOTES,'UTF-8') ?></textarea>
      <small>Necompletat = se foloseste sablonul aratat mai sus, cu numele produsului.</small>
    </label>

    <div style="margin-top:1.5rem;display:flex;gap:1rem">
      <button type="submit" class="btn btn--primary">Salveaza</button>
      <a href="/admin/?page=products" class="btn" style="background:#eee">Anuleaza</a>
    </div>
  </form>
</div>
<script>
function previewImg(input) {
  const preview = document.getElementById('imgPreview');
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
