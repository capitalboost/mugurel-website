<?php
// Teste pentru db/import_catalog.php (catalogul nou, pasul N2). Partea cu baza lucreaza intr-o baza de test separata
// (mugurel_import_test, creata si stearsa aici) — baza locala mugurel_cms nu e atinsa.
require_once __DIR__ . '/../../db/import_catalog.php';
require_once __DIR__ . '/../../admin/config.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass,$fail; if($c){$pass++;echo"  ✓ $m\n";}else{$fail++;echo"  ✗ $m\n";} }

// ── slugify ──
ok(slugify('Țeavă pătrată 40 x 40 mm, Ø 26,9') === 'teava-patrata-40-x-40-mm-26-9', 'slug: diacritice, Ø si virgula');
ok(slugify('  Șape & nivelare  ') === 'sape-nivelare',                             'slug: simboluri si spatii');
ok(strlen(slugify(str_repeat('cuvant ', 40), 50)) <= 50,                           'slug: taiat la lungimea maxima');
ok(!str_ends_with(slugify(str_repeat('cuvant ', 40), 50), '-'),                    'slug: nu se termina cu cratima');

// ── HTML pe lista alba ──
$h = cleanDescriptionHtml('<p onclick="x()"><b>Titlu</b></p><script>alert(1)</script><p>Text <a href="javascript:x">link</a></p><ul><li style="c">a</li></ul><img src=x onerror=alert(1)>');
ok($h === '<p><strong>Titlu</strong></p><p>Text link</p><ul><li>a</li></ul>', 'html: atribute, script, link si img eliminate; b => strong');
ok(!str_contains(cleanDescriptionHtml('<p>a<style>p{}</style></p><iframe src="//x"></iframe>'), 'p{}'), 'html: <style> dispare cu continut');
ok(cleanDescriptionHtml('<p>Ș&amp;ț &lt;b&gt;</p>') === '<p>Ș&amp;ț &lt;b&gt;</p>',               'html: entitatile raman escapate, diacriticele intacte');
ok(cleanDescriptionHtml('') === '',                                                             'html: gol => gol');
ok(shortFromDescription('<p><strong>Titlu</strong></p><p>Primul paragraf real.</p>') === 'Primul paragraf real.', 'rezumat: sare peste titlul in <strong>');
$long = shortFromDescription('<p>' . str_repeat('cuvant ', 60) . '</p>', 50);
ok(mb_strlen($long) <= 51 && str_ends_with($long, '…'),                                        'rezumat: taiat la cuvant, cu „…"');

// ── tag-uri: virgula zecimala ──
$t = splitTags('țeavă pătrată, construcții, oțel, 20 x 20 mm, grosime 1, 5 mm, 6 m, negru', 'Țeavă pătrată 20 x 20 x 1,5 mm');
ok($t['tags'] === ['țeavă pătrată', 'construcții', 'oțel', '20 x 20 mm', 'grosime 1,5 mm', '6 m', 'negru'], 'tag: „grosime 1" + „5 mm" => „grosime 1,5 mm"');
ok($t['fixed'] === ['grosime 1,5 mm'],                                                          'tag: reparatia apare in raport');
$t = splitTags('Ø 26, 9 mm, grosime 2 mm, 6 m', 'Ø 26,9 x 2 mm');
ok($t['tags'] === ['Ø 26,9 mm', 'grosime 2 mm', '6 m'],                                         'tag: diametru refacut; „6 m" ramane separat');
$t = splitTags('RAL 9005, 100 x 50 mm', 'capac RAL 9005 100 x 50');
ok($t['tags'] === ['RAL 9005', '100 x 50 mm'],                                                  'tag: nu lipeste cand numarul refacut nu exista in text');
$t = splitTags('grosime 2, 5 mm', 'grosime 2 mm si 5 mm');
ok(count($t['tags']) === 2,                                                                     'tag: fara „2,5" in context => nu lipeste');
ok(splitTags('alb, Alb, alb', '')['tags'] === ['alb'],                                          'tag: dubluri eliminate');

// ── clasificare ──
$brands = ['ceresit']; $types = ['țeavă pătrată', 'glet', 'platbandă amprentată'];
foreach ([
    ['construcții', 'ignorat'], ['Ceresit', 'brand'], ['țeavă pătrată', 'tip'], ['glet de finisaj', 'tip'],
    ['platbandă', 'tip'], ['oțel', 'material'], ['RAL 9005', 'culoare'], ['negru', 'culoare'], ['40 x 20 mm', 'dimensiune'],
    ['grosime 1,5 mm', 'grosime'], ['Ø 26,9 mm', 'diametru'], ['6 m', 'lungime'], ['27,5 kg', 'cantitate'],
    ['0–1 mm', 'granulatie'], ['C1T', 'clasa'], ['42,5R', 'clasa'], ['CEM II', 'clasa'], ['interior/exterior', 'utilizare'],
    ['fier forjat', 'aplicatie'], ['fațadă', 'aplicatie'],
] as [$tag, $grp]) {
    ok(classifyTag($tag, $brands, $types) === $grp, "clasificare: „{$tag}\" => $grp");
}

// ── parseCatalogRows ──
$hdr = ['A' => 'Cod de bare', 'B' => 'SKU', 'C' => 'Denumire originală', 'D' => 'Denumire site', 'E' => 'Preț', 'F' => 'Monedă',
        'G' => 'TVA', 'H' => 'Unitate de vânzare', 'I' => 'Producător', 'J' => 'Tip produs', 'K' => 'Categorie', 'L' => 'Tag-uri',
        'M' => 'Descriere', 'N' => 'Specificații tehnice', 'O' => 'Documente', 'P' => 'Sursă (URL)', 'Q' => 'Status', 'R' => 'Observații'];
function row(array $over = []): array {
    return array_merge(['A' => '5940000000011', 'B' => 'MU-1', 'C' => 'TEAVA 20X20X1.5', 'D' => 'Țeavă pătrată 20 x 20 x 1,5 mm',
        'E' => '28,50', 'F' => 'Lei', 'G' => 'inclus', 'H' => 'bucată', 'I' => '', 'J' => 'Țeavă pătrată',
        'K' => 'Materiale construcții > Metal și profile > Țevi', 'L' => 'țeavă pătrată, construcții, grosime 1, 5 mm',
        'M' => '<p><strong>T</strong></p><p>Descriere.</p>', 'N' => "Brand: X\nGrosime perete (mm): 1,5\nSursă date tehnice: echivalent",
        'O' => 'https://ex.ro/a.pdf | https://ex.ro/b.pdf', 'P' => 'https://dedeman.ro/x', 'Q' => 'De verificat', 'R' => 'nota'], $over);
}
$p = parseCatalogRows([1 => $hdr, 2 => row(), 3 => row(['A' => '5940000000028', 'D' => 'Al doilea', 'E' => '1.234,50', 'Q' => 'OK']), 4 => []]);
ok($p['errors'] === [] && count($p['products']) === 2,               'parse: 2 produse valide, randul gol ignorat');
$a = $p['products'][0];
ok($a['price'] === 28.5 && $p['products'][1]['price'] === 1234.5,    'parse: „28,50" si „1.234,50"');
ok($a['path'] === ['Materiale construcții', 'Metal și profile', 'Țevi'], 'parse: categoria pe 3 niveluri');
ok($a['tags'] === ['țeavă pătrată', 'construcții', 'grosime 1,5 mm'], 'parse: tag reparat cu contextul din denumire');
ok(count($a['specs']) === 2 && $a['specs'][1]['key'] === 'Grosime perete (mm)', 'parse: specificatiile publice, fara „Sursă date tehnice"');
ok(str_contains((string) $a['notes'], 'Sursă date tehnice: echivalent'), 'parse: specificatia interna mutata in note');
ok($a['documents'] === ['https://ex.ro/a.pdf', 'https://ex.ro/b.pdf'], 'parse: documentele separate prin |');
ok($a['verify_status'] === 'de_verificat' && $p['products'][1]['verify_status'] === 'ok', 'parse: status');
ok($a['short_description'] === 'Descriere.',                         'parse: rezumatul pentru card');

$bad = parseCatalogRows([1 => $hdr,
    2 => row(), 3 => row(),                                              // cod de bare dublat
    4 => row(['A' => '5940000000035', 'E' => 'abc']),
    5 => row(['A' => '5940000000042', 'F' => 'EUR']),
    6 => row(['A' => '5940000000059', 'K' => 'A > B > C > D']),
    7 => row(['A' => '5940000000066', 'Q' => 'poate']),
    8 => row(['A' => '5940000000073', 'H' => '']),
    9 => row(['A' => '12', 'D' => 'cod scurt'])]);
ok(count($bad['errors']) === 7,                                       'parse: 7 randuri gresite => 7 erori');
ok(str_contains(implode(' ', $bad['errors']), 'de doua ori'),         'parse: eroare clara la cod dublat');
$noHdr = parseCatalogRows([1 => ['A' => 'ID', 'B' => 'Nume'], 2 => ['A' => '1']]);
ok(count($noHdr['errors']) >= 4 && $noHdr['products'] === [],          'parse: antet strain => erori pe coloane lipsa');
$empty = parseCatalogRows([1 => $hdr, 2 => row(['E' => ''])]);
ok($empty['errors'] === [] && $empty['products'][0]['price'] === null, 'parse: pret gol => „Pret la cerere"');

// ── scrierea in baza: intr-o baza de test separata ──
$MYSQLDUMP = 'C:/Users/lucia/AppData/Local/mugurel-dev/mariadb/bin/mysqldump.exe';
$MYSQL = 'C:/Users/lucia/AppData/Local/mugurel-dev/mariadb/bin/mysql.exe';
$TEST_DB = 'mugurel_import_test';
$root = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$root->exec("DROP DATABASE IF EXISTS $TEST_DB; CREATE DATABASE $TEST_DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
shell_exec("\"$MYSQLDUMP\" -u root -h 127.0.0.1 --no-data " . DB_NAME . " | \"$MYSQL\" -u root -h 127.0.0.1 $TEST_DB");
$pdo = new PDO('mysql:host=' . DB_HOST . ";dbname=$TEST_DB;charset=utf8mb4", DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
try {
    $pdo->exec("INSERT INTO users (id, username, email, password_hash, role) VALUES (1, 'admin', 'a@t.ro', 'x', 'admin')");
    $pdo->exec("INSERT INTO categories (id, slug, name) VALUES (1, 'acoperis', 'Acoperis')");
    $pdo->exec("INSERT INTO products (id, category_id, page_slug, name, created_by) VALUES (7, 1, 'acoperis', 'Tabla veche', 1)");
    $pdo->exec("INSERT INTO product_properties (product_id, label, body) VALUES (7, 'Aspect', 'x')");

    $three = parseCatalogRows([1 => $hdr, 2 => row(),
        3 => row(['A' => '5940000000028', 'D' => 'Adeziv', 'K' => 'Materiale construcții > Adezivi', 'L' => 'Ceresit, interior, fier forjat']),
        4 => row(['A' => '5940000000035', 'D' => 'Țeavă pătrată 20 x 20 x 1,5 mm', 'E' => ''])])['products'];
    $groups = classifyCatalogTags($three);

    $dry = applyCatalog($pdo, $three, $groups, false, true);
    ok($dry['created'] === 3 && $dry['old_deleted'] === 1,            'db: verificarea (fara --apply) numara corect');
    ok((int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() === 1, 'db: verificarea nu scrie nimic');

    $s1 = applyCatalog($pdo, $three, $groups, true, true);
    ok($s1['created'] === 3 && $s1['categories_created'] === 4,       'db: 3 produse, 4 categorii noi');
    ok($s1['old_deleted'] === 1 && $s1['old_categories_deleted'] === 1, 'db: catalogul vechi sters (produs + categorie)');
    ok((int) $pdo->query('SELECT COUNT(*) FROM product_properties')->fetchColumn() === 0, 'db: proprietatile vechi sterse in cascada');
    $tree = $pdo->query('SELECT c.slug, p.slug parent FROM categories c LEFT JOIN categories p ON p.id = c.parent_id ORDER BY c.id')->fetchAll();
    ok(array_column($tree, 'slug') === ['materiale-constructii', 'metal-si-profile', 'tevi', 'adezivi'], 'db: slug-urile categoriilor');
    ok($tree[2]['parent'] === 'metal-si-profile',                     'db: ierarhia categoriilor');
    $slugs = $pdo->query('SELECT slug FROM products ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    ok(count(array_unique($slugs)) === 3 && $slugs[2] === 'teava-patrata-20-x-20-x-1-5-mm-000035', 'db: denumire identica => slug cu sufix din codul de bare');
    ok((int) $pdo->query("SELECT COUNT(*) FROM product_specs")->fetchColumn() === 6, 'db: specificatii salvate');
    ok((int) $pdo->query("SELECT COUNT(*) FROM tags WHERE slug = 'constructii'")->fetchColumn() === 0, 'db: tag-ul ignorat nu se salveaza');
    ok($pdo->query("SELECT grp FROM tags WHERE slug = 'fier-forjat'")->fetchColumn() === 'aplicatie', 'db: „fier forjat" = colectie');
    ok($pdo->query("SELECT stock_status FROM products WHERE barcode = '5940000000011'")->fetchColumn() === 'in_stoc', 'db: produsele noi pornesc „In stoc"');

    // Mugurel pune o poza si schimba stocul din admin; apoi vine acelasi fisier, cu un pret nou
    $pdo->exec("UPDATE products SET image_path = 'uploads/products/a.jpg', stock_status = 'la_comanda' WHERE barcode = '5940000000011'");
    $three[0]['price'] = 30.0;
    $s2 = applyCatalog($pdo, $three, $groups, true, true);
    ok($s2['created'] === 0 && $s2['updated'] === 3 && $s2['categories_created'] === 0 && $s2['tags_created'] === 0, 'db: reimport => doar actualizari, nimic dublat');
    $r = $pdo->query("SELECT price, image_path, stock_status FROM products WHERE barcode = '5940000000011'")->fetch();
    ok((float) $r['price'] === 30.0,                                   'db: reimportul schimba pretul');
    ok($r['image_path'] === 'uploads/products/a.jpg' && $r['stock_status'] === 'la_comanda', 'db: reimportul pastreaza poza si stocul');
    ok((int) $pdo->query('SELECT COUNT(*) FROM product_specs')->fetchColumn() === 6, 'db: specificatiile nu se dubleaza');

    // al doilea fisier (alt domeniu) nu atinge produsele din primul
    $other = parseCatalogRows([1 => $hdr, 2 => row(['A' => '5940000000099', 'D' => 'Frigider', 'K' => 'Electrocasnice > Frigidere'])])['products'];
    $s3 = applyCatalog($pdo, $other, classifyCatalogTags($other), true, false);
    ok($s3['created'] === 1 && $s3['untouched'] === 3,                 'db: fisierul 2 adauga, produsele din fisierul 1 raman');
    ok((int) $pdo->query("SELECT COUNT(*) FROM categories WHERE parent_id IS NULL")->fetchColumn() === 2, 'db: ramura noua „Electrocasnice" creata automat');

    // un fisier stricat la mijloc nu lasa nimic scris pe jumatate
    $broken = $three; $broken[1]['path'] = [str_repeat('x', 200)];
    $before = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    $threw = false; try { applyCatalog($pdo, $broken, $groups, true, false); } catch (Throwable $e) { $threw = true; }
    ok($threw && (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn() === $before, 'db: eroare la scriere => rollback complet');
} finally {
    $root->exec("DROP DATABASE IF EXISTS $TEST_DB");
}

// ── fisierul real de la client, daca e pe aceasta masina ──
$real = __DIR__ . '/../../../Fisiere de lucru/Prouse Dunlite client/constructii_complet_MU.xlsx';
if (is_file($real)) {
    $p = parseCatalogRows(readXlsxSheet($real, null));
    ok($p['errors'] === [] && count($p['products']) === 147,           'fisier real: 147 produse, 0 erori');
    $g = classifyCatalogTags($p['products']);
    ok(!array_filter(array_keys($g), fn ($t) => preg_match('/^\d+$/u', $t)), 'fisier real: niciun tag ramas doar cu cifre');
    ok(count($p['fixedTags']) === 17,                                  'fisier real: 17 tag-uri reparate');
}

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
