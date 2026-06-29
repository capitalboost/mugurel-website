<div class="adm-card">
  <h2>Editare: <?= htmlspecialchars($pageRecord['title'],ENT_QUOTES,'UTF-8') ?></h2>
  <?php if ($pageRecord['restricted']): ?>
  <div class="flash flash--error" style="margin-bottom:1rem">Pagina legala — modificarile sunt vizibile public.</div>
  <?php endif; ?>

  <form method="POST" action="/admin/?page=pages&action=save" class="adm-form">
    <?= Csrf::input() ?>
    <input type="hidden" name="id" value="<?= $pageRecord['id'] ?>">

    <label>Titlu pagina *
      <input type="text" name="title" required maxlength="200"
             value="<?= htmlspecialchars($pageRecord['title'],ENT_QUOTES,'UTF-8') ?>">
    </label>

    <label>Continut
      <textarea id="pageContent" name="content"><?= htmlspecialchars($pageRecord['content']??'',ENT_QUOTES,'UTF-8') ?></textarea>
    </label>

    <div class="form-row">
      <label>Meta title (SEO)
        <input type="text" name="meta_title" maxlength="160"
               value="<?= htmlspecialchars($pageRecord['meta_title']??'',ENT_QUOTES,'UTF-8') ?>">
      </label>
      <label>Meta description (SEO)
        <input type="text" name="meta_description" maxlength="320"
               value="<?= htmlspecialchars($pageRecord['meta_description']??'',ENT_QUOTES,'UTF-8') ?>">
      </label>
    </div>

    <label style="flex-direction:row;align-items:center;gap:.5rem">
      <input type="checkbox" name="is_published" value="1" <?= $pageRecord['is_published']?'checked':'' ?>>
      Publicat
    </label>

    <div style="margin-top:1.5rem;display:flex;gap:1rem">
      <button type="submit" class="btn btn--primary">Salveaza</button>
      <a href="/admin/?page=pages" class="btn" style="background:#eee">Anuleaza</a>
    </div>
  </form>
</div>

<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
  selector: '#pageContent',
  plugins: 'lists link',
  toolbar: 'undo redo | bold italic underline | h2 h3 | bullist numlist | link | removeformat',
  menubar: false,
  branding: false,
  height: 450,
  valid_elements: 'p,br,strong/b,em/i,u,ul,ol,li,h2,h3,h4,a[href|target],blockquote',
  content_style: 'body { font-family: Open Sans, sans-serif; font-size: 15px; }'
});
</script>
