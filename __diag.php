<?php
/**
 * Diagnostic temporar — de sters imediat dupa folosire.
 *
 * Verifica trei lucruri inainte de a redeploya site-ul pe baza de date:
 *   1. ce versiune de PHP executa efectiv fisierele din public_html
 *   2. daca Apache are incarcat php_module (cauza caderii precedente)
 *   3. daca sintaxa folosita de codul nou se parseaza pe acest server
 *
 * Cere un token in URL ca sa nu fie indexat sau deschis din intamplare.
 */

$TOKEN = 'm7x2verif';
if (($_GET['t'] ?? '') !== $TOKEN) {
    http_response_code(404);
    exit('Not found');
}

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

echo "=== VERSIUNE PHP ===\n";
echo "PHP_VERSION      : " . PHP_VERSION . "\n";
echo "PHP_VERSION_ID   : " . PHP_VERSION_ID . "  (80000 = 8.0, 80100 = 8.1)\n";
echo "SAPI             : " . PHP_SAPI . "\n";
echo "Minim necesar    : 8.0 (codul nou foloseste str_contains, named args, match)\n";
echo "VERDICT          : " . (PHP_VERSION_ID >= 80000 ? "OK — suporta codul nou" : "PREA VECHI") . "\n";

echo "\n=== MODULE APACHE (cauza caderii precedente) ===\n";
if (function_exists('apache_get_modules')) {
    $mods = apache_get_modules();
    $hasPhp = in_array('mod_php', $mods, true) || in_array('php_module', $mods, true)
           || (bool) preg_grep('/^mod_php/', $mods);
    echo "php_module incarcat : " . ($hasPhp ? "DA" : "NU") . "\n";
    echo "  -> Blocul <IfModule php_module> din .htaccess " .
         ($hasPhp ? "SE ACTIVA aici. Asta a spart site-ul." : "nu s-ar fi activat.") . "\n";
    echo "mod_rewrite         : " . (in_array('mod_rewrite', $mods, true) ? "DA" : "NU") . "\n";
} else {
    echo "apache_get_modules() indisponibila (SAPI: " . PHP_SAPI . ")\n";
    echo "  -> normal pe LSAPI/FastCGI; nu putem lista modulele de aici.\n";
}

echo "\n=== SINTAXA CERUTA DE CODUL NOU ===\n";
$checks = [
    'proprietati tipate nullable (7.4)' => 'class T1 { private static ?PDO $x = null; }',
    'arrow functions (7.4)'             => '$f = fn($a) => $a * 2;',
    'str_contains (8.0)'                => 'return str_contains("abc", "b");',
    'named arguments (8.0)'             => 'return str_pad(string: "a", length: 3);',
    'match (8.0)'                       => 'return match(1) { 1 => "unu", default => "alt" };',
    'tip mixed (8.0)'                   => 'function t2(mixed $v): int { return 1; }',
    'array_is_list (8.1)'               => 'return array_is_list([1,2,3]);',
];
foreach ($checks as $eticheta => $cod) {
    $ok = @eval('if (false) { ' . $cod . ' } return true;');
    echo str_pad($eticheta, 38) . ($ok ? "OK" : "ESUEAZA") . "\n";
}

echo "\n=== HANDLER PENTRU .php ===\n";
echo "Fisierul asta s-a executat, deci handlerul functioneaza.\n";
echo "Cale               : " . __FILE__ . "\n";
echo "Document root      : " . ($_SERVER['DOCUMENT_ROOT'] ?? '?') . "\n";
echo "Server software    : " . ($_SERVER['SERVER_SOFTWARE'] ?? '?') . "\n";

echo "\n=== BAZA DE DATE ===\n";
$cfg = __DIR__ . '/admin/config.php';
if (!is_file($cfg)) {
    echo "admin/config.php lipseste\n";
} else {
    require_once $cfg;
    echo "config incarcat    : DA (baza: " . (defined('DB_NAME') ? DB_NAME : '?') . ")\n";
    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                       DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo "conexiune          : OK\n";
        echo "versiune MySQL     : " . $pdo->query('SELECT VERSION()')->fetchColumn() . "\n";
        foreach (['products', 'product_properties', 'categories'] as $t) {
            $n = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES
                              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t'")->fetchColumn();
            $randuri = $n ? $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn() : '-';
            echo str_pad("tabela $t", 36) . ($n ? "exista, $randuri randuri" : "nu exista inca") . "\n";
        }
    } catch (Throwable $e) {
        echo "conexiune          : ESUATA — " . $e->getMessage() . "\n";
    }
}

echo "\n=== GATA ===\n";
