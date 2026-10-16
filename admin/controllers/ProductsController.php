<?php
/**
 * Produsele catalogului nou (pasul N7). Formularul vechi (pagini fixe, sectiuni, iconite, badge-uri) a disparut
 * odata cu catalogul vechi; produsele se identifica acum prin codul de bare si au pagina lor pe site.
 */
require_once __DIR__ . '/../models/CatalogProduct.php';
require_once __DIR__ . '/../helpers/Upload.php';

$db = Database::get();
$audit = function (string $action, int $id) use ($db): void {
    $db->prepare('INSERT INTO audit_log (user_id, entity_type, entity_id, action, ip_address) VALUES (?, ?, ?, ?, ?)')
       ->execute([Auth::id(), 'product', $id, $action, $_SERVER['REMOTE_ADDR'] ?? '']);
};

switch ($action) {
    case 'new':
    case 'edit':
        $id = Sanitize::posInt($_GET['id'] ?? 0);
        $product = $id ? CatalogProduct::byId($id) : null;
        if ($id && !$product) { Flash::error('Produsul nu exista.'); header('Location: /admin/?page=products'); exit; }
        // dupa o eroare de validare, formularul se reafiseaza cu ce a scris utilizatorul
        $old = $_SESSION['product_form'] ?? null;
        unset($_SESSION['product_form']);
        $categoryOptions = CatalogProduct::categoryOptions();
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
        $existing = $id ? CatalogProduct::byId($id) : null;
        if ($id && !$existing) { Flash::error('Produsul nu exista.'); header('Location: /admin/?page=products'); exit; }
        $back = '/admin/?page=products&action=' . ($id ? 'edit&id=' . $id : 'new');

        [$data, $errors] = CatalogProduct::validate($_POST, $existing);
        if ($errors) {
            $_SESSION['product_form'] = $_POST;
            Flash::error(implode(' ', $errors));
            header('Location: ' . $back);
            exit;
        }

        $imagePath = $existing['image_path'] ?? null;
        $oldImage = null;
        // '' = poza scoasa intentionat: deploy-ul nu mai pune la loc poza din img/produse (NULL = fara poza, inca)
        if (!empty($_POST['remove_image']) && $imagePath) { $oldImage = $imagePath; $imagePath = ''; }
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $newPath = Upload::image($_FILES['image']);
                if ($imagePath) { $oldImage = $imagePath; }
                $imagePath = $newPath;
            } catch (RuntimeException $e) {
                $_SESSION['product_form'] = $_POST;
                Flash::error('Poza: ' . $e->getMessage());
                header('Location: ' . $back);
                exit;
            }
        }

        try {
            $savedId = CatalogProduct::save($id ?: null, $data, $imagePath, Auth::id());
        } catch (Throwable $e) {
            error_log('ProductsController save: ' . $e->getMessage());
            if ($imagePath && $imagePath !== ($existing['image_path'] ?? null)) { Upload::delete($imagePath); }
            $_SESSION['product_form'] = $_POST;
            Flash::error('Produsul nu a putut fi salvat. Incearca din nou.');
            header('Location: ' . $back);
            exit;
        }
        if ($oldImage) { Upload::delete($oldImage); }
        $audit($id ? 'update' : 'create', $savedId);
        Flash::success($id ? 'Produsul a fost actualizat.' : 'Produsul a fost creat.');
        header('Location: /admin/?page=products&action=edit&id=' . $savedId);
        exit;

    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /admin/?page=products'); exit; }
        Csrf::verify();
        $id = Sanitize::posInt($_POST['id'] ?? 0);
        $img = CatalogProduct::delete($id);
        if ($img) { Upload::delete($img); }
        $audit('delete', $id);
        Flash::success('Produsul a fost sters.');
        header('Location: /admin/?page=products');
        exit;

    default: // lista
        $filters = [
            'q' => Sanitize::text($_GET['q'] ?? '', 100),
            'cat' => Sanitize::posInt($_GET['cat'] ?? 0),
            // valorile necunoscute sunt ignorate in CatalogProduct::search()
            'verify' => is_string($_GET['verify'] ?? null) ? $_GET['verify'] : '',
            'stock' => is_string($_GET['stock'] ?? null) ? $_GET['stock'] : '',
            'photo' => is_string($_GET['photo'] ?? null) ? $_GET['photo'] : '',
            'price' => is_string($_GET['price'] ?? null) ? $_GET['price'] : '',
            'visible' => is_string($_GET['visible'] ?? null) ? $_GET['visible'] : '',
            'page' => Sanitize::posInt($_GET['p'] ?? 1),
        ];
        $result = CatalogProduct::search($filters);
        $categoryOptions = CatalogProduct::categoryOptions();
        ob_start();
        require __DIR__ . '/../views/products/list.php';
        $bodyContent = ob_get_clean();
        $pageTitle = 'Produse';
        require __DIR__ . '/../views/layout.php';
}
