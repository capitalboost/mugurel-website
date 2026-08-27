<?php
require_once __DIR__ . '/../models/Database.php';

$pass = 0; $fail = 0;

function ok(bool $cond, string $msg): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ✓ $msg\n"; }
    else        { $fail++; echo "  ✗ $msg\n"; }
}

// Test: conexiune reușită
try {
    $db = Database::get();
    ok($db instanceof PDO, 'PDO instance returnat');
} catch (Exception $e) {
    ok(false, 'Conexiune DB: ' . $e->getMessage());
}

// Test: ierarhia de categorii dupa migrarea 002
$count = (int)Database::get()->query('SELECT COUNT(*) FROM categories')->fetchColumn();
ok($count === 31, "31 categorii în DB (got $count)");

$l1 = (int)Database::get()->query('SELECT COUNT(*) FROM categories WHERE parent_id IS NULL')->fetchColumn();
ok($l1 === 9, "9 categorii de nivel 1 (got $l1)");

$orfane = (int)Database::get()->query(
    'SELECT COUNT(*) FROM categories c WHERE c.parent_id IS NOT NULL
       AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM categories) p WHERE p.id = c.parent_id)'
)->fetchColumn();
ok($orfane === 0, "nicio subcategorie orfana (got $orfane)");

// Test: singleton — aceeași instanță
ok(Database::get() === Database::get(), 'Singleton returnează aceeași instanță');

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
