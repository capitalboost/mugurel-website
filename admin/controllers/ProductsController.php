<?php
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../helpers/Upload.php';
require_once __DIR__ . '/../../includes/products_repo.php';
require_once __DIR__ . '/../../includes/product_icons.php';

$db = Database::get();

switch ($action) {
    case 'new':
    case 'edit':
        $id = Sanitize::posInt($_GET['id'] ?? 0);
        $product = $id ? Product::byId($id) : null;
        if ($id && !$product) { Flash::error('Produsul nu exista.'); header('Location: /admin/?page=products'); exit; }
        $categoryTree = categoryTree();
        $sectionsByPage = Product::sectionsByPage();
        $knownPageSlugs = Product::KNOWN_PAGE_SLUGS;
        $knownBadgeKinds = Product::KNOWN_BADGE_KINDS;
        $productIcons = PRODUCT_ICONS;
        ob_start();
        require __DIR__ . '/../views/products/edit.php';
        $bodyContent = ob_get_clean();
        $pageTitle = $id ? 'Editare produs' : 'Produs nou';
        require __DIR__ . '/../views/layout.php';
        break;

    case 'save':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /admin/?page=products'); exit; }
        Csrf::verify();
        $id = Sanitize::posInt($_POST['id'] ?? 0);

        $imagePath = null;
        if ($id) {
            $existing = Product::byId($id);
            $imagePath = $existing['image_path'] ?? null;
        }

        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $newPath = Upload::image($_FILES['image']);
                if ($imagePath) Upload::delete($imagePath);
                $imagePath = $newPath;
            } catch (RuntimeException $e) {
                Flash::error('Upload imagine: ' . $e->getMessage());
                header('Location: /admin/?page=products&action=' . ($id ? 'edit&id=' . $id : 'new'));
                exit;
            }
        }

        // section_key: fie una din cele existente (select), fie una noua (text) — textul are prioritate.
        $sectionKeyNew = Sanitize::slug($_POST['section_key_new'] ?? '');
        $sectionKeyExisting = Sanitize::slug($_POST['section_key_existing'] ?? '');
        $sectionKey = $sectionKeyNew !== '' ? $sectionKeyNew : $sectionKeyExisting;

        $pageSlug   = Sanitize::slug($_POST['page_slug'] ?? '');
        $cardKind   = $_POST['card_kind'] ?? 'material';
        $cardModifier = Sanitize::slug($_POST['card_modifier'] ?? '');
        $iconKey    = Sanitize::text($_POST['icon_key'] ?? '', 40);
        $badgeKind  = Sanitize::slug($_POST['badge_kind'] ?? '');

        $data = [
            'category_id'       => Sanitize::posInt($_POST['category_id'] ?? 0),
            'name'              => Sanitize::text($_POST['name'] ?? '', 200),
            'short_description' => Sanitize::text($_POST['short_description'] ?? '', 1000),
            'image_path'        => $imagePath,
            'image_alt'         => Sanitize::text($_POST['image_alt'] ?? '', 200),
            'price'             => Sanitize::price($_POST['price'] ?? ''),
            'price_unit'        => Sanitize::text($_POST['price_unit'] ?? '', 40),
            'is_visible'        => isset($_POST['is_visible']) ? 1 : 0,
            'sort_order'        => Sanitize::posInt($_POST['sort_order'] ?? 0),
            'created_by'        => Auth::id(),
            'page_slug'         => $pageSlug,
            'section_key'       => $sectionKey !== '' ? $sectionKey : null,
            'card_kind'         => $cardKind,
            'card_modifier'     => $cardModifier !== '' ? $cardModifier : null,
            'icon_key'          => $iconKey !== '' ? $iconKey : null,
            'icon_label'        => Sanitize::text($_POST['icon_label'] ?? '', 120),
            'badge_label'       => Sanitize::text($_POST['badge_label'] ?? '', 60),
            'badge_kind'        => $badgeKind !== '' ? $badgeKind : null,
            'wa_text'           => Sanitize::text($_POST['wa_text'] ?? '', 400),
        ];
        if ($data['wa_text'] === '') { $data['wa_text'] = null; }
        if ($data['icon_label'] === '') { $data['icon_label'] = null; }
        if ($data['badge_label'] === '') { $data['badge_label'] = null; }

        $errors = [];
        if (empty($data['name']) || $data['category_id'] === 0) {
            $errors[] = 'Numele si categoria sunt obligatorii.';
        }
        if (!in_array($data['page_slug'], Product::KNOWN_PAGE_SLUGS, true)) {
            $errors[] = 'Pagina aleasa nu este valida.';
        }
        if (!in_array($data['card_kind'], ['material', 'accessory'], true)) {
            $errors[] = 'Tipul de card nu este valid.';
        }
        if ($data['card_modifier'] !== null && $data['card_modifier'] !== 'sm') {
            $errors[] = 'Modificatorul de card nu este valid.';
        }
        if ($data['icon_key'] !== null && !isset(PRODUCT_ICONS[$data['icon_key']])) {
            $errors[] = 'Iconita aleasa nu este valida.';
        }
        if ($data['badge_kind'] !== null && !in_array($data['badge_kind'], Product::KNOWN_BADGE_KINDS, true)) {
            $errors[] = 'Tipul de badge nu este valid.';
        }

        if ($errors) {
            Flash::error(implode(' ', $errors));
            header('Location: /admin/?page=products&action=' . ($id ? 'edit&id=' . $id : 'new'));
            exit;
        }

        if ($id) {
            Product::update($id, $data);
            $db->prepare("INSERT INTO audit_log (user_id,entity_type,entity_id,action,ip_address) VALUES (?,?,?,?,?)")
               ->execute([Auth::id(), 'product', $id, 'update', $_SERVER['REMOTE_ADDR'] ?? '']);
            Flash::success('Produsul a fost actualizat.');
        } else {
            $newId = Product::create($data);
            $db->prepare("INSERT INTO audit_log (user_id,entity_type,entity_id,action,ip_address) VALUES (?,?,?,?,?)")
               ->execute([Auth::id(), 'product', $newId, 'create', $_SERVER['REMOTE_ADDR'] ?? '']);
            Flash::success('Produs creat cu succes.');
        }
        header('Location: /admin/?page=products');
        exit;

    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /admin/?page=products'); exit; }
        Csrf::verify();
        $id = Sanitize::posInt($_POST['id'] ?? 0);
        $imgPath = Product::delete($id);
        if ($imgPath) Upload::delete($imgPath);
        $db->prepare("INSERT INTO audit_log (user_id,entity_type,entity_id,action,ip_address) VALUES (?,?,?,?,?)")
           ->execute([Auth::id(), 'product', $id, 'delete', $_SERVER['REMOTE_ADDR'] ?? '']);
        Flash::success('Produsul a fost sters.');
        header('Location: /admin/?page=products');
        exit;

    default: // index
        $search      = Sanitize::text($_GET['q'] ?? '', 100);
        $catId       = Sanitize::posInt($_GET['cat'] ?? 0);
        $pageSlug    = Sanitize::slug($_GET['page_slug'] ?? '');
        $products    = Product::all($catId, $search, $pageSlug);
        $categories  = Product::categories();
        $knownPageSlugs = Product::KNOWN_PAGE_SLUGS;
        ob_start();
        require __DIR__ . '/../views/products/list.php';
        $bodyContent = ob_get_clean();
        $pageTitle = 'Produse';
        require __DIR__ . '/../views/layout.php';
}
