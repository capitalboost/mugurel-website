<?php
// Simulează sesiune fără browser
$_SERVER['REMOTE_ADDR']  = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
session_start();

require_once __DIR__ . '/../helpers/Csrf.php';
require_once __DIR__ . '/../helpers/Flash.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass,$fail; if($c){$pass++;echo"  ✓ $m\n";}else{$fail++;echo"  ✗ $m\n";} }

// CSRF: token generat corect
$t1 = Csrf::token();
ok(strlen($t1) === 64, 'CSRF token are 64 caractere');
ok(Csrf::token() === $t1, 'Același token returnat fără rotire');

// CSRF: input HTML
$html = Csrf::input();
ok(strpos($html, 'name="csrf_token"') !== false, 'CSRF input conține name="csrf_token"');
ok(strpos($html, $t1) !== false, 'CSRF input conține valoarea tokenului');

// Flash: set/get
Flash::success('Test reușit');
$f = Flash::get();
ok($f['type'] === 'success', 'Flash type = success');
ok($f['msg'] === 'Test reușit', 'Flash msg corect');
ok(Flash::get() === null, 'Flash consumat după get()');

// Flash: HTML output
Flash::error('Eroare test');
$h = Flash::html();
ok(strpos($h, 'flash--error') !== false, 'Flash html conține clasa flash--error');
ok(Flash::html() === '', 'Flash html gol după ce a fost consumat');

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
