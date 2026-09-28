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

    <hr style="margin:1.5rem 0;border:none;border-top:1px solid var(--border)">
    <h3 style="font-size:1rem;color:var(--verde);margin-bottom:1rem">Proprietati (blocuri afisate pe card)</h3>
    <datalist id="propLabelSuggestions">
      <?php foreach (['Avantaje','Specificatii','Descriere','Aspect','Utilizare','Unde se foloseste'] as $sugestie): ?>
      <option value="<?= htmlspecialchars($sugestie,ENT_QUOTES,'UTF-8') ?>">
      <?php endforeach; ?>
    </datalist>

    <template id="propBlockTemplate">
      <div class="prop-block" style="border:1px solid var(--border);border-radius:8px;padding:1rem;margin-bottom:.8rem">
        <div class="form-row">
          <label>Eticheta
            <input type="text" name="prop_label[]" list="propLabelSuggestions" maxlength="60" value="">
          </label>
          <label style="flex-direction:row;align-items:center;gap:.5rem;flex:none">
            <input type="checkbox" class="prop-is-list-toggle">
            <input type="hidden" name="prop_is_list[]" value="0">
            Lista cu bullet-uri
          </label>
        </div>
        <label>Continut
          <textarea name="prop_body[]" rows="3"></textarea>
        </label>
        <small class="prop-list-hint" style="display:none">
          Fiecare rand din continut devine un element de lista (bullet).
        </small>
        <div class="form-row" style="margin-top:.5rem">
          <button type="button" class="btn prop-move-up" style="background:#eee">&uarr; Mai sus</button>
          <button type="button" class="btn prop-move-down" style="background:#eee">&darr; Mai jos</button>
          <button type="button" class="btn prop-remove" style="background:#fdd">Sterge blocul</button>
        </div>
      </div>
    </template>

    <div id="propsList">
      <?php foreach (($productProperties ?? []) as $prop): $isList = (int)$prop['is_list'] === 1; ?>
      <div class="prop-block" style="border:1px solid var(--border);border-radius:8px;padding:1rem;margin-bottom:.8rem">
        <div class="form-row">
          <label>Eticheta
            <input type="text" name="prop_label[]" list="propLabelSuggestions" maxlength="60"
                   value="<?= htmlspecialchars($prop['label'],ENT_QUOTES,'UTF-8') ?>">
          </label>
          <label style="flex-direction:row;align-items:center;gap:.5rem;flex:none">
            <input type="checkbox" class="prop-is-list-toggle" <?= $isList ? 'checked' : '' ?>>
            <input type="hidden" name="prop_is_list[]" value="<?= $isList ? '1' : '0' ?>">
            Lista cu bullet-uri
          </label>
        </div>
        <label>Continut
          <textarea name="prop_body[]" rows="3"><?= htmlspecialchars($prop['body'],ENT_QUOTES,'UTF-8') ?></textarea>
        </label>
        <small class="prop-list-hint" style="<?= $isList ? '' : 'display:none' ?>">
          Fiecare rand din continut devine un element de lista (bullet).
        </small>
        <div class="form-row" style="margin-top:.5rem">
          <button type="button" class="btn prop-move-up" style="background:#eee">&uarr; Mai sus</button>
          <button type="button" class="btn prop-move-down" style="background:#eee">&darr; Mai jos</button>
          <button type="button" class="btn prop-remove" style="background:#fdd">Sterge blocul</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn" id="propAddBtn" style="background:#eee;margin-bottom:1rem">+ Adauga bloc</button>

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

(function () {
  const list = document.getElementById('propsList');
  const template = document.getElementById('propBlockTemplate');
  const addBtn = document.getElementById('propAddBtn');

  addBtn.addEventListener('click', function () {
    const clone = template.content.cloneNode(true);
    list.appendChild(clone);
  });

  list.addEventListener('change', function (e) {
    if (!e.target.classList.contains('prop-is-list-toggle')) return;
    const block = e.target.closest('.prop-block');
    const hidden = block.querySelector('input[name="prop_is_list[]"]');
    const hint = block.querySelector('.prop-list-hint');
    hidden.value = e.target.checked ? '1' : '0';
    hint.style.display = e.target.checked ? '' : 'none';
  });

  list.addEventListener('click', function (e) {
    const block = e.target.closest('.prop-block');
    if (!block) return;

    if (e.target.classList.contains('prop-remove')) {
      block.remove();
    } else if (e.target.classList.contains('prop-move-up')) {
      const prev = block.previousElementSibling;
      if (prev) list.insertBefore(block, prev);
    } else if (e.target.classList.contains('prop-move-down')) {
      const next = block.nextElementSibling;
      if (next) list.insertBefore(next, block);
    }
  });
})();
</script>
