<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/admin/config.php';

$slug = preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['slug'] ?? ''));
if (empty($slug)) { header('Location: /'); exit; }

$db = Database::get();

// Verifica daca e categorie de produse
$stmt = $db->prepare('SELECT id, name FROM categories WHERE slug = ? AND is_active = 1');
$stmt->execute([$slug]);
$category = $stmt->fetch();

if ($category) {
    $stmt = $db->prepare(
        'SELECT id, name, short_description, image_path, image_alt, price, price_unit
         FROM products WHERE category_id = ? AND is_visible = 1 ORDER BY sort_order, name'
    );
    $stmt->execute([$category['id']]);
    $products        = $stmt->fetchAll();
    $categoryName    = $category['name'];
    $categorySlug    = $slug;
    $pageTitle       = $categoryName . ' in Dabuleni — Mugurel';
    $metaDescription = 'Materiale ' . $categoryName . ' in Dabuleni. Stoc disponibil, livrare Dolj. Comanda pe WhatsApp 0749 130 565.';
    $canonicalUrl    = SITE_URL . '/' . $slug . '.html';
    require __DIR__ . '/templates/category.php';
} else {
    // Pagina statica
    $stmt = $db->prepare('SELECT * FROM pages WHERE slug = ? AND is_published = 1');
    $stmt->execute([$slug]);
    $pageData = $stmt->fetch();
    if (!$pageData) {
        http_response_code(404);
        if (file_exists(__DIR__ . '/404.html')) {
            include __DIR__ . '/404.html';
        } else {
            echo '<h1>404 — Pagina nu a fost gasita</h1>';
        }
        exit;
    }
    $pageTitle       = $pageData['meta_title'] ?: $pageData['title'] . ' — Mugurel';
    $metaDescription = $pageData['meta_description'] ?? '';
    $canonicalUrl    = SITE_URL . '/' . $slug . '.html';
    require __DIR__ . '/templates/page.php';
}
