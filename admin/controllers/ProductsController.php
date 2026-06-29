<?php
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../helpers/Upload.php';

$db = Database::get();

switch ($action) {
    case 'new':
    case 'edit':
        $id = Sanitize::posInt($_GET['id'] ?? 0);
        $product = $id ? Product::byId($id) : null;
        if ($id && !$product) { Flash::error('Produsul nu exista.'); header('Location: /admin/?page=products'); exit; }
        $categories = Product::categories();
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
        ];

        if (empty($data['name']) || $data['category_id'] === 0) {
            Flash::error('Numele si categoria sunt obligatorii.');
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
        $search     = Sanitize::text($_GET['q'] ?? '', 100);
        $catId      = Sanitize::posInt($_GET['cat'] ?? 0);
        $products   = Product::all($catId, $search);
        $categories = Product::categories();
        ob_start();
        require __DIR__ . '/../views/products/list.php';
        $bodyContent = ob_get_clean();
        $pageTitle = 'Produse';
        require __DIR__ . '/../views/layout.php';
}
