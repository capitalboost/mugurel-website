<?php
$db = Database::get();

$stats = $db->query("
    SELECT
        (SELECT COUNT(*) FROM products WHERE is_visible=1) AS produse_active,
        (SELECT COUNT(*) FROM products WHERE is_visible=0) AS produse_ascunse,
        (SELECT COUNT(*) FROM products WHERE image_path IS NULL OR image_path='') AS produse_fara_imagine,
        (SELECT COUNT(*) FROM users   WHERE is_active=1)  AS utilizatori_activi,
        (SELECT COUNT(*) FROM audit_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS modificari_7_zile
")->fetch();

$recentLog = $db->query("
    SELECT al.created_at, al.action, al.entity_type, al.entity_id, u.username
    FROM audit_log al
    LEFT JOIN users u ON u.id = al.user_id
    ORDER BY al.created_at DESC
    LIMIT 10
")->fetchAll();

ob_start();
require __DIR__ . '/../views/dashboard.php';
$bodyContent = ob_get_clean();
$pageTitle = 'Dashboard';
require __DIR__ . '/../views/layout.php';
