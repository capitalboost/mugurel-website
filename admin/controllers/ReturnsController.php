<?php
/**
 * Cererile de retur trimise de pe site (/cerere-retur.html). Ajung si pe email (comenzi@); aici raman la indemana
 * si li se poate schimba starea: noua → in lucru → finalizata.
 */
require_once __DIR__ . '/../../includes/returns.php';

const RETURN_STATUS = ['noua' => 'Noua', 'in_lucru' => 'In lucru', 'finalizata' => 'Finalizata'];
$db = Database::get();

if ($action === 'status') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /admin/?page=returns'); exit; }
    Csrf::verify();
    $id = Sanitize::posInt($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    if ($id && isset(RETURN_STATUS[$status])) {
        $db->prepare('UPDATE return_requests SET status = ? WHERE id = ?')->execute([$status, $id]);
        $db->prepare('INSERT INTO audit_log (user_id, entity_type, entity_id, action, ip_address) VALUES (?, ?, ?, ?, ?)')
           ->execute([Auth::id(), 'return', $id, 'status:' . $status, $_SERVER['REMOTE_ADDR'] ?? '']);
        Flash::success('Starea cererii a fost schimbata.');
    }
    header('Location: /admin/?page=returns' . (isset($_POST['filter']) && isset(RETURN_STATUS[$_POST['filter']]) ? '&status=' . $_POST['filter'] : ''));
    exit;
}

$filter = is_string($_GET['status'] ?? null) && isset(RETURN_STATUS[$_GET['status']]) ? $_GET['status'] : '';
$stmt = $db->prepare('SELECT * FROM return_requests' . ($filter ? ' WHERE status = ?' : '') . ' ORDER BY created_at DESC LIMIT 200');
$stmt->execute($filter ? [$filter] : []);
$returns = $stmt->fetchAll();
$counts = $db->query("SELECT status, COUNT(*) n FROM return_requests GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

ob_start();
require __DIR__ . '/../views/returns.php';
$bodyContent = ob_get_clean();
$pageTitle = 'Retururi';
require __DIR__ . '/../views/layout.php';
