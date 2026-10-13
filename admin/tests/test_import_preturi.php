<?php
// Teste pentru db/import_preturi.php (T2). Necesita baza LOCALA; restaureaza tot ce modifica.
require_once __DIR__ . '/../../db/import_preturi.php';
require_once __DIR__ . '/../../admin/config.php';
require_once __DIR__ . '/../../includes/db.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass,$fail; if($c){$pass++;echo"  ✓ $m\n";}else{$fail++;echo"  ✗ $m\n";} }

/** Un .xlsx minimal: antetul cu texte partajate (ca Excel), datele cu texte inline (ca openpyxl). */
function makeXlsx(array $rows, string $sheetName = 'Preturi'): string
{
    $path = tempnam(sys_get_temp_dir(), 'pret') . '.xlsx';
    $shared = [];
    $xmlRows = '';
    foreach ($rows as $i => $row) {
        $r = $i + 1;
        $xmlRows .= "<row r=\"$r\">";
        foreach (array_values($row) as $j => $val) {
            $ref = chr(65 + $j) . $r;
            if ($val === null) { continue; }
            if ($i === 0) {
                $shared[] = $val;
                $xmlRows .= "<c r=\"$ref\" t=\"s\"><v>" . (count($shared) - 1) . '</v></c>';
            } elseif (is_int($val) || is_float($val)) {
                $xmlRows .= "<c r=\"$ref\"><v>$val</v></c>";
            } else {
                $xmlRows .= "<c r=\"$ref\" t=\"inlineStr\"><is><t>" . htmlspecialchars($val, ENT_XML1) . '</t></is></c>';
            }
        }
        $xmlRows .= '</row>';
    }
    $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('xl/workbook.xml', "<workbook xmlns=\"$ns\" xmlns:r=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships\"><sheets>"
        . '<sheet name="Instructiuni" sheetId="1" r:id="rId1"/>'
        . "<sheet name=\"$sheetName\" sheetId=\"2\" r:id=\"rId2\"/></sheets></workbook>");
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Target="worksheets/sheet1.xml" Type="x"/>'
        . '<Relationship Id="rId2" Target="/xl/worksheets/sheet2.xml" Type="x"/></Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml', "<worksheet xmlns=\"$ns\"><sheetData/></worksheet>");
    $zip->addFromString('xl/worksheets/sheet2.xml', "<worksheet xmlns=\"$ns\"><sheetData>$xmlRows</sheetData></worksheet>");
    $sst = '';
    foreach ($shared as $s) { $sst .= '<si><t>' . htmlspecialchars($s, ENT_XML1) . '</t></si>'; }
    $zip->addFromString('xl/sharedStrings.xml', "<sst xmlns=\"$ns\">$sst</sst>");
    $zip->close();
    return $path;
}

$H = ['ID', 'Pagina', 'Produs', 'Pret (lei, cu TVA)', 'De la? (DA = pret minim)', 'Unitate (buc, m², ml...)', 'Observatii'];

// ── citirea .xlsx ──
$f = makeXlsx([$H, [1, 'Acoperis', 'Tabla', 55, 'DA', 'm²', 'x'], [2, 'Acoperis', 'Tigla', '6,5', null, 'buc', null]]);
$rows = readXlsxSheet($f, 'Preturi');
ok(count($rows) === 3,                         'xlsx: 3 randuri (antet + 2)');
ok(($rows[1]['A'] ?? '') === 'ID',             'xlsx: antetul vine din sharedStrings');
ok(($rows[2]['F'] ?? '') === 'm²',             'xlsx: text inline cu caractere speciale');
ok(($rows[3]['D'] ?? '') === '6,5',            'xlsx: pret scris ca text cu virgula');
ok(!isset($rows[3]['E']),                      'xlsx: celula goala lipseste');
$threw = false; try { readXlsxSheet($f, 'Altceva'); } catch (RuntimeException $e) { $threw = str_contains($e->getMessage(), 'Altceva'); }
ok($threw,                                     'xlsx: foaie inexistenta => eroare clara');
$threw = false; try { readXlsxSheet(__FILE__, 'Preturi'); } catch (RuntimeException $e) { $threw = true; }
ok($threw,                                     'xlsx: fisier care nu e xlsx => eroare');

// ── parsePriceRows ──
$p = parsePriceRows($rows);
ok($p['errors'] === [],                        'parse: fisier valid fara erori');
ok(count($p['changes']) === 2,                 'parse: 2 modificari');
ok($p['changes'][0] == ['id' => 1, 'price' => 55.0, 'is_from' => 1, 'unit' => 'm²', 'row' => 2], 'parse: rand „de la" corect');
ok($p['changes'][1]['price'] === 6.5 && $p['changes'][1]['is_from'] === 0, 'parse: „6,5" => 6.5, pret fix');

$bad = parsePriceRows(readXlsxSheet(makeXlsx([$H,
    [3, '', '', 'abc', null, 'buc', null],          // pret text
    [4, '', '', -5, null, 'buc', null],             // pret negativ
    [5, '', '', null, 'DA', 'buc', null],           // DA fara pret
    [6, '', '', 10, 'poate', 'buc', null],          // valoare De la invalida
    [7, '', '', 10, null, str_repeat('x', 41), null], // unitate prea lunga
    ['x7', '', '', 10, null, 'buc', null],          // ID invalid
    [8, '', '', 10, 'nu', 'buc', null],             // „nu" acceptat (litere mici)
    [8, '', '', 11, null, 'buc', null],             // ID dublat
    [null, null, null, null, null, null, null],     // rand gol, ignorat
]), 'Preturi'));
ok(count($bad['errors']) === 7,                'parse: 7 erori detectate (' . count($bad['errors']) . ')');
ok(count($bad['changes']) === 1 && $bad['changes'][0]['id'] === 8, 'parse: singurul rand valid e ID 8 („nu" = pret fix)');
$all = implode("\n", $bad['errors']);
ok(str_contains($all, 'ID 3') && str_contains($all, 'pret invalid'), 'parse: mesajul spune randul si ID-ul');
ok(str_contains($all, 'de doua ori'),          'parse: ID dublat semnalat');

$noHeader = parsePriceRows([1 => ['A' => 'Cod', 'B' => 'Cost']]);
ok(count($noHeader['errors']) === 4,           'parse: antet fara coloanele cerute => 4 erori');
ok(parsePriceRows([])['errors'] !== [],        'parse: foaie goala => eroare');

// ── applyPrices pe baza locala (restauram la final) ──
$pdo = Database::get();
$ids = $pdo->query('SELECT id FROM products ORDER BY id LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);
$snap = $pdo->query('SELECT id, price, price_unit, price_is_from FROM products WHERE id IN (' . implode(',', $ids) . ')')->fetchAll(PDO::FETCH_ASSOC);
$read = fn (int $id) => $pdo->query("SELECT price, price_unit, price_is_from FROM products WHERE id = $id")->fetch(PDO::FETCH_ASSOC);

$changes = [
    ['id' => (int) $ids[0], 'price' => 99.99, 'is_from' => 0, 'unit' => 'buc', 'row' => 2],
    ['id' => (int) $ids[1], 'price' => null,  'is_from' => 0, 'unit' => null,  'row' => 3],
];
try {
    $before = $read((int) $ids[0]);
    $dry = applyPrices($pdo, $changes, false);
    ok($dry['updated'] >= 1,                    'apply: verificarea numara modificarile');
    ok($read((int) $ids[0]) == $before,         'apply: fara --apply nu se scrie nimic');

    $s = applyPrices($pdo, $changes, true);
    $now = $read((int) $ids[0]);
    ok((float) $now['price'] === 99.99 && $now['price_unit'] === 'buc' && (int) $now['price_is_from'] === 0, 'apply: pretul e scris');
    ok($read((int) $ids[1])['price'] === null,  'apply: pret gol => produs „la cerere"');
    ok(applyPrices($pdo, $changes, true)['updated'] === 0, 'apply: a doua rulare nu mai schimba nimic (idempotent)');

    $withMissing = array_merge([['id' => (int) $ids[0], 'price' => 1.0, 'is_from' => 0, 'unit' => 'buc', 'row' => 2]],
                               [['id' => 999999999, 'price' => 1.0, 'is_from' => 0, 'unit' => 'buc', 'row' => 3]]);
    $m = applyPrices($pdo, $withMissing, true);
    ok($m['missing'] === [999999999],           'apply: ID inexistent raportat');
    ok((float) $read((int) $ids[0])['price'] === 99.99, 'apply: cu un ID inexistent nu se aplica nimic (rollback)');
} finally {
    $restore = $pdo->prepare('UPDATE products SET price = ?, price_unit = ?, price_is_from = ? WHERE id = ?');
    foreach ($snap as $r) { $restore->execute([$r['price'], $r['price_unit'], $r['price_is_from'], $r['id']]); }
}
$after = $pdo->query('SELECT id, price, price_unit, price_is_from FROM products WHERE id IN (' . implode(',', $ids) . ')')->fetchAll(PDO::FETCH_ASSOC);
ok($after == $snap,                             'baza locala restaurata dupa test');

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
