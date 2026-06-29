<?php
require_once __DIR__ . '/../models/Page.php';

switch ($action) {
    case 'edit':
        $id         = Sanitize::posInt($_GET['id'] ?? 0);
        $pageRecord = Page::byId($id);
        if (!$pageRecord) { Flash::error('Pagina nu exista.'); header('Location: /admin/?page=pages'); exit; }
        if ($pageRecord['restricted'] && !Auth::hasRole('admin')) {
            http_response_code(403); die('Acces interzis — doar admin poate edita texte legale.');
        }
        ob_start();
        require __DIR__ . '/../views/pages/edit.php';
        $bodyContent = ob_get_clean();
        $pageTitle = 'Editare: ' . $pageRecord['title'];
        require __DIR__ . '/../views/layout.php';
        break;

    case 'save':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /admin/?page=pages'); exit; }
        Csrf::verify();
        $id         = Sanitize::posInt($_POST['id'] ?? 0);
        $pageRecord = Page::byId($id);
        if (!$pageRecord) { Flash::error('Pagina nu exista.'); header('Location: /admin/?page=pages'); exit; }
        if ($pageRecord['restricted'] && !Auth::hasRole('admin')) {
            http_response_code(403); die('Acces interzis.');
        }
        $data = [
            'title'            => Sanitize::text($_POST['title'] ?? '', 200),
            'content'          => Sanitize::html($_POST['content'] ?? ''),
            'meta_title'       => Sanitize::text($_POST['meta_title'] ?? '', 160),
            'meta_description' => Sanitize::text($_POST['meta_description'] ?? '', 320),
            'is_published'     => isset($_POST['is_published']) ? 1 : 0,
        ];
        Page::update($id, $data, Auth::id());
        $db = Database::get();
        $db->prepare("INSERT INTO audit_log (user_id,entity_type,entity_id,action,ip_address) VALUES (?,?,?,?,?)")
           ->execute([Auth::id(), 'page', $id, 'update', $_SERVER['REMOTE_ADDR'] ?? '']);
        Flash::success('Pagina a fost salvata.');
        header('Location: /admin/?page=pages');
        exit;

    default:
        $pages = Page::all();
        ob_start();
        require __DIR__ . '/../views/pages/list.php';
        $bodyContent = ob_get_clean();
        $pageTitle = 'Pagini';
        require __DIR__ . '/../views/layout.php';
}
