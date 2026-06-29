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
        <?php foreach ($categories as $cat): ?>
        <option value="<?= $cat['id'] ?>"
          <?= ($isEdit && $product['category_id']==$cat['id']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($cat['name'],ENT_QUOTES,'UTF-8') ?>
        </option>
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
