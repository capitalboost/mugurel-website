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

// Test: query simplu pe categories
$stmt = Database::get()->query('SELECT COUNT(*) FROM categories');
$count = (int)$stmt->fetchColumn();
ok($count === 13, "13 categorii în DB (got $count)");

// Test: singleton — aceeași instanță
ok(Database::get() === Database::get(), 'Singleton returnează aceeași instanță');

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
