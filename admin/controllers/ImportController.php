<?php
/**
 * Importul catalogului din Excel, direct din admin (pasul N7) — acelasi cod ca linia de comanda
 * (includes/catalog_import.php). Doi pasi: 1) incarci fisierul => raport, nimic scris; 2) „Aplica" => scrie.
 * Fisierul asteapta intre pasi intr-un director temporar, sub un nume aleator legat de sesiune; dupa aplicare
 * (sau dupa o ora) se sterge. Produsele din alte fisiere nu sunt atinse; catalogul vechi nu se sterge de aici.
 */
require_once __DIR__ . '/../../includes/catalog_import.php';

const IMPORT_MAX_BYTES = 10 * 1024 * 1024;
const IMPORT_TTL = 3600;

$importDir = rtrim(sys_get_temp_dir(), '/\\') . '/mugurel-import';
$cleanup = function () use ($importDir): void {
    foreach (glob($importDir . '/*.xlsx') ?: [] as $f) {
        if (filemtime($f) < time() - IMPORT_TTL) { @unlink($f); }
    }
};
/** Fisierul din sesiune, daca exista si e inca valabil. */
$pending = function () use ($importDir): ?array {
    $s = $_SESSION['catalog_import'] ?? null;
    if (!is_array($s) || !preg_match('/^[a-f0-9]{32}$/', $s['token'] ?? '')) { return null; }
    $path = $importDir . '/' . $s['token'] . '.xlsx';
    return is_file($path) && filemtime($path) >= time() - IMPORT_TTL ? $s + ['path' => $path] : null;
};

$report = null; $stats = null; $fileName = null; $errors = [];

switch ($action) {
    case 'preview':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /admin/?page=import'); exit; }
        Csrf::verify();
        $cleanup();
        $f = $_FILES['file'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Flash::error('Alege un fisier .xlsx.'); header('Location: /admin/?page=import'); exit;
        }
        if ($f['size'] > IMPORT_MAX_BYTES) { Flash::error('Fisierul depaseste 10 MB.'); header('Location: /admin/?page=import'); exit; }
        if (strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION)) !== 'xlsx' || !is_uploaded_file($f['tmp_name'])) {
            Flash::error('Se accepta doar fisiere Excel .xlsx.'); header('Location: /admin/?page=import'); exit;
        }
        $zip = new ZipArchive();
        if ($zip->open($f['tmp_name']) !== true || $zip->locateName('xl/workbook.xml') === false) {
            Flash::error('Fisierul nu e un Excel .xlsx valid.'); header('Location: /admin/?page=import'); exit;
        }
        $zip->close();
        if (!is_dir($importDir)) { mkdir($importDir, 0700, true); }
        $token = bin2hex(random_bytes(16));
        if (!move_uploaded_file($f['tmp_name'], $importDir . '/' . $token . '.xlsx')) {
            Flash::error('Fisierul nu a putut fi salvat temporar.'); header('Location: /admin/?page=import'); exit;
        }
        $_SESSION['catalog_import'] = ['token' => $token, 'name' => mb_substr(basename((string) $f['name']), 0, 120)];
        header('Location: /admin/?page=import&action=review');
        exit;

    case 'review':
        $p = $pending();
        if (!$p) { Flash::error('Incarca din nou fisierul (a expirat sau nu exista).'); header('Location: /admin/?page=import'); exit; }
        $fileName = $p['name'];
        try {
            $parsed = parseCatalogRows(readXlsxSheet($p['path'], null));
            $errors = $parsed['errors'];
            if (!$errors) {
                $groups = classifyCatalogTags($parsed['products']);
                $report = catalogReport($parsed, $groups);
                $stats = applyCatalog(Database::get(), $parsed['products'], $groups, false, false);
                $stats['read'] = count($parsed['products']);
            }
        } catch (Throwable $e) {
            error_log('ImportController review: ' . $e->getMessage());
            $errors = ['Fisierul nu a putut fi citit: ' . $e->getMessage()];
        }
        break;

    case 'apply':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /admin/?page=import'); exit; }
        Csrf::verify();
        $p = $pending();
        if (!$p || !hash_equals($p['token'], (string) ($_POST['token'] ?? ''))) {
            Flash::error('Incarca din nou fisierul (a expirat sau nu exista).'); header('Location: /admin/?page=import'); exit;
        }
        try {
            $parsed = parseCatalogRows(readXlsxSheet($p['path'], null));
            if ($parsed['errors']) { throw new RuntimeException('fisierul are erori'); }
            $st = applyCatalog(Database::get(), $parsed['products'], classifyCatalogTags($parsed['products']), true, false);
            attachCatalogPhotos(Database::get(), __DIR__ . '/../../img/produse'); // produsele noi primesc poza, daca exista
            attachCatalogDocuments(Database::get(), __DIR__ . '/../../fise-tehnice'); // fisele gazduite pe site raman
        } catch (Throwable $e) {
            error_log('ImportController apply: ' . $e->getMessage());
            Flash::error('Importul NU s-a aplicat (nimic nu s-a schimbat): ' . $e->getMessage());
            header('Location: /admin/?page=import&action=review');
            exit;
        }
        @unlink($p['path']);
        unset($_SESSION['catalog_import']);
        Database::get()->prepare('INSERT INTO audit_log (user_id, entity_type, entity_id, action, changes_json, ip_address) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([Auth::id(), 'catalog_import', null, 'import', json_encode(['fisier' => $p['name']] + $st, JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '']);
        Flash::success("Import aplicat din „{$p['name']}\": {$st['created']} produse noi, {$st['updated']} actualizate, "
            . "{$st['categories_created']} categorii noi.");
        header('Location: /admin/?page=products');
        exit;

    case 'cancel':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            if ($p = $pending()) { @unlink($p['path']); }
            unset($_SESSION['catalog_import']);
        }
        header('Location: /admin/?page=import');
        exit;
}

$token = $pending()['token'] ?? null;
ob_start();
require __DIR__ . '/../views/import.php';
$bodyContent = ob_get_clean();
$pageTitle = 'Import din Excel';
require __DIR__ . '/../views/layout.php';
