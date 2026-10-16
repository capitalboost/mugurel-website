<?php
declare(strict_types=1);

/**
 * import_preturi.php — pune preturile din Excel-ul de preturi in `products`.
 *
 *   php db/import_preturi.php "Preturi produse.xlsx"            # doar verificare, nu scrie nimic
 *   php db/import_preturi.php "Preturi produse.xlsx" --apply    # scrie in baza din admin/config.local.php
 *
 * Fisierul: foaia „Preturi", generata de export_preturi.py. Coloanele se recunosc dupa antet:
 *   ID | ... | Pret (lei, cu TVA) | Unitate | ...
 * Toate preturile sunt FIXE (decizia din 13 oct). O coloana veche „De la?" e acceptata doar goala.
 * Randul e identificat DOAR prin ID (= products.id); numele si descrierea sunt ignorate.
 *
 * Reguli: pret gol => produsul ramane „Pret la cerere"; importul seteaza price_is_from = 0;
 * orice rand invalid opreste importul intreg (nu se aplica nimic partial).
 */

const PRET_SHEET = 'Preturi';

/**
 * Citeste o foaie dintr-un .xlsx ca lista de randuri [litera coloana => valoare].
 * Fara biblioteci: un .xlsx e o arhiva zip cu XML. $sheetName = null => prima foaie din fisier.
 */
function readXlsxSheet(string $path, ?string $sheetName): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException("Nu pot deschide fisierul: $path");
    }
    try {
        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $relNs = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

        $workbook = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
        $rels = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
        if ($workbook === false || $rels === false) {
            throw new RuntimeException('Fisierul nu pare a fi un .xlsx valid.');
        }

        $target = null;
        foreach ($workbook->children($ns)->sheets->sheet as $sheet) {
            if ($target === null && ($sheetName === null || (string) $sheet->attributes()['name'] === $sheetName)) {
                $rid = (string) $sheet->attributes($relNs)['id'];
                foreach ($rels->Relationship as $rel) {
                    if ((string) $rel['Id'] === $rid) {
                        $target = 'xl/' . ltrim(preg_replace('#^/?xl/#', '', (string) $rel['Target']), '/');
                    }
                }
            }
        }
        if ($target === null) {
            throw new RuntimeException("Foaia „{$sheetName}\" nu exista in fisier.");
        }

        $shared = [];
        $sst = $zip->getFromName('xl/sharedStrings.xml');
        if ($sst !== false) {
            foreach (simplexml_load_string($sst)->children($ns)->si as $si) {
                // Text simplu (<t>) sau text formatat pe bucati (<r><t>).
                $shared[] = isset($si->t) ? (string) $si->t : implode('', array_map('strval', $si->xpath('.//*[local-name()="t"]')));
            }
        }

        $sheetXml = simplexml_load_string((string) $zip->getFromName($target));
        $rows = [];
        foreach ($sheetXml->children($ns)->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $col = preg_replace('/\d+/', '', (string) $c->attributes()['r']);
                $type = (string) $c->attributes()['t'];
                if ($type === 's') {
                    $val = $shared[(int) $c->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $val = (string) $c->is->t;
                } else {
                    $val = isset($c->v) ? (string) $c->v : '';
                }
                $cells[$col] = $val;
            }
            $rows[(int) $row->attributes()['r']] = $cells;
        }
        return $rows;
    } finally {
        $zip->close();
    }
}

/**
 * Transforma randurile foii in modificari de pret. Nu atinge baza.
 * @return array{changes: list<array{id:int, price:?float, is_from:int, unit:?string, row:int}>, errors: list<string>}
 */
function parsePriceRows(array $rows): array
{
    $errors = [];
    $changes = [];
    if ($rows === []) {
        return ['changes' => [], 'errors' => ['Foaia e goala.']];
    }

    $headerRow = array_key_first($rows);
    $cols = [];
    foreach ($rows[$headerRow] as $col => $title) {
        $t = mb_strtolower(trim((string) $title));
        if ($t === 'id') { $cols['id'] = $col; }
        elseif (str_starts_with($t, 'pret')) { $cols['price'] = $col; }
        elseif (str_starts_with($t, 'de la')) { $cols['from'] = $col; }
        elseif (str_starts_with($t, 'unitate')) { $cols['unit'] = $col; }
    }
    foreach (['id' => 'ID', 'price' => 'Pret', 'unit' => 'Unitate'] as $k => $label) {
        if (!isset($cols[$k])) {
            $errors[] = "Lipseste coloana „{$label}\" din antet.";
        }
    }
    if ($errors) {
        return ['changes' => [], 'errors' => $errors];
    }

    $seen = [];
    foreach ($rows as $r => $cells) {
        if ($r === $headerRow) { continue; }
        $rawId = trim((string) ($cells[$cols['id']] ?? ''));
        $rawPrice = trim((string) ($cells[$cols['price']] ?? ''));
        $rawFrom = isset($cols['from']) ? mb_strtoupper(trim((string) ($cells[$cols['from']] ?? ''))) : '';
        $unit = trim((string) ($cells[$cols['unit']] ?? ''));

        if ($rawId === '' && $rawPrice === '' && $rawFrom === '' && $unit === '') {
            continue; // rand gol
        }
        if (!preg_match('/^\d+$/', $rawId) || (int) $rawId <= 0) {
            $errors[] = "Randul $r: ID invalid („{$rawId}\").";
            continue;
        }
        $id = (int) $rawId;
        if (isset($seen[$id])) {
            $errors[] = "Randul $r: ID-ul $id apare de doua ori (si pe randul {$seen[$id]}).";
            continue;
        }
        $seen[$id] = $r;

        $price = null;
        if ($rawPrice !== '') {
            $normalized = str_replace(',', '.', str_replace(' ', '', $rawPrice));
            if (!is_numeric($normalized) || (float) $normalized <= 0) {
                $errors[] = "Randul $r (ID $id): pret invalid („{$rawPrice}\") — doar cifre, mai mare ca 0.";
                continue;
            }
            $price = round((float) $normalized, 2);
            if ($price > 99999999.99) {
                $errors[] = "Randul $r (ID $id): pret prea mare.";
                continue;
            }
        }

        if (!in_array($rawFrom, ['', 'NU'], true)) {
            $errors[] = "Randul $r (ID $id): toate preturile sunt fixe — sterge „{$rawFrom}\" din coloana „De la?\".";
            continue;
        }
        $isFrom = 0;
        if (mb_strlen($unit) > 40) {
            $errors[] = "Randul $r (ID $id): unitatea are peste 40 de caractere.";
            continue;
        }

        $changes[] = ['id' => $id, 'price' => $price, 'is_from' => $isFrom, 'unit' => $unit === '' ? null : $unit, 'row' => $r];
    }

    return ['changes' => $changes, 'errors' => $errors];
}

/**
 * Aplica modificarile intr-o singura tranzactie. Un ID inexistent in baza opreste tot.
 * Cu $apply = false calculeaza doar ce s-ar schimba si face rollback.
 * @return array{updated:int, unchanged:int, missing: list<int>}
 */
function applyPrices(PDO $pdo, array $changes, bool $apply): array
{
    $stats = ['updated' => 0, 'unchanged' => 0, 'missing' => []];
    $select = $pdo->prepare('SELECT price, price_unit, price_is_from FROM products WHERE id = ?');
    $update = $pdo->prepare('UPDATE products SET price = ?, price_unit = ?, price_is_from = ? WHERE id = ?');

    $pdo->beginTransaction();
    try {
        foreach ($changes as $c) {
            $select->execute([$c['id']]);
            $cur = $select->fetch(PDO::FETCH_ASSOC);
            if ($cur === false) {
                $stats['missing'][] = $c['id'];
                continue;
            }
            $same = ($cur['price'] === null ? null : round((float) $cur['price'], 2)) === $c['price']
                 && (($cur['price_unit'] ?? '') === '' ? null : $cur['price_unit']) === $c['unit']
                 && (int) $cur['price_is_from'] === $c['is_from'];
            if ($same) {
                $stats['unchanged']++;
                continue;
            }
            $update->execute([$c['price'], $c['unit'], $c['is_from'], $c['id']]);
            $stats['updated']++;
        }
        if ($apply && $stats['missing'] === []) {
            $pdo->commit();
        } else {
            $pdo->rollBack();
        }
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $stats;
}

function importMain(array $argv): int
{
    $file = null;
    $apply = false;
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--apply') { $apply = true; } else { $file = $arg; }
    }
    if ($file === null || !is_file($file)) {
        fwrite(STDERR, "Folosire: php db/import_preturi.php <fisier.xlsx> [--apply]\n");
        return 2;
    }

    require_once __DIR__ . '/../admin/config.php';
    echo 'Baza tinta: ' . DB_NAME . ' @ ' . DB_HOST . ($apply ? '  [SCRIE]' : '  [doar verificare]') . "\n";

    try {
        $parsed = parsePriceRows(readXlsxSheet($file, PRET_SHEET));
    } catch (Throwable $e) {
        fwrite(STDERR, 'EROARE: ' . $e->getMessage() . "\n");
        return 1;
    }
    if ($parsed['errors']) {
        echo "Fisierul are " . count($parsed['errors']) . " erori — nu s-a importat nimic:\n";
        foreach ($parsed['errors'] as $err) { echo "  - $err\n"; }
        return 1;
    }

    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stats = applyPrices($pdo, $parsed['changes'], $apply);

    $withPrice = count(array_filter($parsed['changes'], fn ($c) => $c['price'] !== null));
    $from = count(array_filter($parsed['changes'], fn ($c) => $c['is_from'] === 1));
    echo count($parsed['changes']) . " randuri citite: $withPrice cu pret ($from „de la\"), "
       . (count($parsed['changes']) - $withPrice) . " fara pret.\n";
    if ($stats['missing']) {
        echo 'EROARE: ID-uri care nu exista in baza: ' . implode(', ', $stats['missing']) . " — nu s-a importat nimic.\n";
        return 1;
    }
    echo ($apply ? 'Actualizate' : 'S-ar actualiza') . ": {$stats['updated']}, neschimbate: {$stats['unchanged']}.\n";
    if (!$apply) { echo "Nimic scris. Ruleaza cu --apply ca sa aplici.\n"; }
    return 0;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(importMain($argv));
}
