<?php
require_once __DIR__ . '/../helpers/Sanitize.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass,$fail; if($c){$pass++;echo"  ✓ $m\n";}else{$fail++;echo"  ✗ $m\n";} }

// text()
ok(Sanitize::text('  hello  ') === 'hello', 'text() trimează spații');
ok(Sanitize::text('<b>bold</b>') === 'bold', 'text() elimină taguri');
ok(strlen(Sanitize::text(str_repeat('a', 1000), 10)) === 10, 'text() respectă max length');

// html()
$h = Sanitize::html('<p>Test <b>bold</b> <script>alert(1)</script></p>');
ok(strpos($h, '<script>') === false, 'html() elimină script');
ok(strpos($h, '<p>') !== false, 'html() păstrează <p>');
ok(strpos($h, '<b>') !== false, 'html() păstrează <b>');

$link = Sanitize::html('<a href="javascript:alert(1)">click</a>');
ok(strpos($link, 'javascript:') === false, 'html() elimină javascript: href');

$onclick = Sanitize::html('<p onclick="alert(1)">test</p>');
ok(strpos($onclick, 'onclick') === false, 'html() elimină onclick');

// slug()
ok(Sanitize::slug('Acoperis BCA') === 'acoperisbca', 'slug() lowercase fără spații');
ok(Sanitize::slug('gips-carton') === 'gips-carton', 'slug() păstrează cratimă');
ok(Sanitize::slug('../etc/passwd') === 'etcpasswd', 'slug() elimină path traversal');

// posInt()
ok(Sanitize::posInt('42') === 42, 'posInt() parsează string');
ok(Sanitize::posInt('-5') === 0, 'posInt() returnează 0 pentru negativ');

// price()
ok(Sanitize::price('25.50') === 25.50, 'price() parsează float');
ok(Sanitize::price('0') === null, 'price() returnează null pentru 0');

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);