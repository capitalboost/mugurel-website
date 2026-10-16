<?php
declare(strict_types=1);

/**
 * catalog_import.php — catalogul nou din fisierele Dunlite (docs/PLAN-CATALOG-NOU.md, pasul N2).
 *
 *   php includes/catalog_import.php fisier.xlsx                       # doar raport, nu scrie nimic
 *   php includes/catalog_import.php fisier.xlsx --apply               # scrie in baza din admin/config(.local).php
 *   php includes/catalog_import.php fisier.xlsx --apply --replace-old # + sterge catalogul VECHI (produsele fara cod de bare)
 *
 * Fisierul: prima foaie; coloanele se recunosc dupa antet (Cod de bare, Denumire site, Pret, Categorie, Tag-uri...).
 *
 * Reguli:
 *  - Produsul e identificat prin CODUL DE BARE. Reimportul actualizeaza, nu dubleaza. Produsele din baza care nu
 *    sunt in fisier raman neatinse (catalogul vine in mai multe fisiere).
 *  - La reimport NU se ating: poza, stocul, vizibilitatea (le schimba Mugurel din admin).
 *  - Categoria „A > B > C" se creeaza daca nu exista (potrivire dupa nume, sub acelasi parinte).
 *  - Tag-urile rupte de virgula zecimala („grosime 1" + „5 mm") se refac, verificat pe denumire/specificatii.
 *  - Pret gol => „Pret la cerere" (fara cos). Status-ul din fisier se pastreaza doar pentru admin.
 *  - Orice eroare de format opreste importul intreg; scrierea se face intr-o singura tranzactie.
 */

require_once __DIR__ . '/xlsx_reader.php';         // readXlsxSheet()
require_once __DIR__ . '/catalog_rules.php';
require_once __DIR__ . '/catalog_text.php';

const CATALOG_COLUMNS = [
    'barcode' => 'cod de bare', 'sku' => 'sku', 'original' => 'denumire originala', 'name' => 'denumire site',
    'price' => 'pret', 'currency' => 'moneda', 'vat' => 'tva', 'unit' => 'unitate de vanzare',
    'manufacturer' => 'producator', 'type' => 'tip produs', 'category' => 'categorie', 'tags' => 'tag-uri',
    'description' => 'descriere', 'specs' => 'specificatii tehnice', 'documents' => 'documente',
    'sources' => 'sursa (url)', 'status' => 'status', 'notes' => 'observatii',
];
const CATALOG_REQUIRED = ['barcode', 'name', 'category', 'price', 'unit'];
const CATALOG_STATUS = ['ok' => 'ok', 'de verificat' => 'de_verificat', 'negasit' => 'negasit'];

/** Antet comparabil: litere mici, fara diacritice, spatii simple. */
function headerKey(string $s): string
{
    return trim((string) preg_replace('/\s+/', ' ', strtr(mb_strtolower(trim($s)),
        ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't'])));
}

/** Imparte o lista de link-uri separate prin „|", virgula sau rand nou. */
function splitUrls(string $s): array
{
    $out = [];
    foreach (preg_split('/\s*(?:\||\n)\s*/u', trim($s)) ?: [] as $u) {
        if (preg_match('#^https?://\S+$#i', $u)) { $out[] = $u; }
    }
    return array_values(array_unique($out));
}

/**
 * „Cheie: valoare" pe fiecare rand; un rand fara „:" continua valoarea de dinainte.
 * @return list<array{key:string, value:string}>
 */
function parseSpecs(string $s): array
{
    $specs = [];
    foreach (preg_split('/\r\n|\r|\n/', $s) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') { continue; }
        $pos = mb_strpos($line, ':');
        if ($pos !== false && $pos > 0 && $pos <= 80) {
            $specs[] = ['key' => trim(mb_substr($line, 0, $pos)), 'value' => trim(mb_substr($line, $pos + 1))];
        } elseif ($specs) {
            $specs[count($specs) - 1]['value'] .= ' ' . $line;
        }
    }
    return array_values(array_filter($specs, fn ($sp) => $sp['key'] !== '' && $sp['value'] !== ''));
}

/**
 * Tag-urile sunt separate prin virgula, dar virgula e si separatorul zecimal: „grosime 1,5 mm" ajunge
 * „grosime 1" + „5 mm". Lipim inapoi o bucata care incepe cu cifra la cea de dinainte, care se termina cu cifra,
 * DOAR daca numarul refacut („1,5") apare in $context (denumire + specificatii). Altfel lasam bucatile asa.
 * @return array{tags: list<string>, fixed: list<string>}
 */
function splitTags(string $raw, string $context): array
{
    $parts = array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($t) => $t !== ''));
    $tags = []; $fixed = [];
    foreach ($parts as $part) {
        $n = count($tags);
        if ($n > 0 && preg_match('/^(\d+)(.*)$/u', $part, $m) && preg_match('/(\d+)$/u', $tags[$n - 1], $p)) {
            $number = (float) ($p[1] . '.' . $m[1]);
            // numarul refacut trebuie sa existe in text ca valoare: „0,4" se potriveste si cu „0,40 mm" din denumire
            preg_match_all('/(?<![\d,.])(\d+),(\d+)(?![\d,])/u', $context, $nums, PREG_SET_ORDER);
            $found = false;
            foreach ($nums as $num) { if (abs((float) ($num[1] . '.' . $num[2]) - $number) < 1e-9) { $found = true; break; } }
            if ($found) {
                $tags[$n - 1] .= ',' . $part;
                $fixed[] = $tags[$n - 1];
                continue;
            }
        }
        $tags[] = $part;
    }
    // acelasi tag de doua ori pe acelasi produs (dupa reparare) => o data
    $seen = []; $unique = [];
    foreach ($tags as $t) {
        $k = mb_strtolower($t);
        if (!isset($seen[$k])) { $seen[$k] = true; $unique[] = $t; }
    }
    return ['tags' => $unique, 'fixed' => $fixed];
}

/**
 * Transforma randurile foii in produse. Nu atinge baza.
 * @return array{products: list<array>, errors: list<string>, fixedTags: array<string,int>}
 */
function parseCatalogRows(array $rows): array
{
    $errors = [];
    if ($rows === []) { return ['products' => [], 'errors' => ['Foaia e goala.'], 'fixedTags' => []]; }

    $headerRow = array_key_first($rows);
    $cols = [];
    foreach ($rows[$headerRow] as $col => $title) {
        $k = array_search(headerKey((string) $title), CATALOG_COLUMNS, true);
        if ($k !== false) { $cols[$k] = $col; }
    }
    foreach (CATALOG_REQUIRED as $k) {
        if (!isset($cols[$k])) { $errors[] = 'Lipseste coloana „' . CATALOG_COLUMNS[$k] . '" din antet.'; }
    }
    if ($errors) { return ['products' => [], 'errors' => $errors, 'fixedTags' => []]; }

    $products = []; $seen = []; $fixedTags = [];
    foreach ($rows as $r => $cells) {
        if ($r === $headerRow) { continue; }
        $get = fn (string $k): string => isset($cols[$k]) ? trim((string) ($cells[$cols[$k]] ?? '')) : '';
        if (implode('', array_map(fn ($c) => trim((string) $c), $cells)) === '') { continue; } // rand gol

        $barcode = $get('barcode');
        $name = $get('name');
        $where = "Randul $r" . ($barcode !== '' ? " ($barcode)" : '');
        if (!preg_match('/^[0-9A-Za-z-]{4,32}$/', $barcode)) { $errors[] = "$where: cod de bare invalid („{$barcode}\")."; continue; }
        if (isset($seen[$barcode])) { $errors[] = "$where: codul de bare apare de doua ori (si pe randul {$seen[$barcode]})."; continue; }
        $seen[$barcode] = $r;
        if ($name === '' || mb_strlen($name) > 200) { $errors[] = "$where: „Denumire site\" lipseste sau are peste 200 de caractere."; continue; }

        $price = null;
        $rawPrice = $get('price');
        if ($rawPrice !== '') {
            $normalized = str_replace([' ', "\u{00A0}"], '', $rawPrice);
            if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
                $normalized = str_replace('.', '', $normalized); // „1.234,50" — punctul separa miile
            }
            $normalized = str_replace(',', '.', $normalized);
            if (!is_numeric($normalized) || (float) $normalized <= 0 || (float) $normalized > 99999999.99) {
                $errors[] = "$where: pret invalid („{$rawPrice}\")."; continue;
            }
            $price = round((float) $normalized, 2);
        }
        $currency = $get('currency');
        if ($currency !== '' && headerKey($currency) !== 'lei') { $errors[] = "$where: moneda „{$currency}\" — se accepta doar lei."; continue; }
        $vat = $get('vat');
        if ($vat !== '' && headerKey($vat) !== 'inclus') { $errors[] = "$where: TVA „{$vat}\" — preturile trebuie sa fie cu TVA inclus."; continue; }
        $unit = $get('unit');
        if ($unit === '' || mb_strlen($unit) > 40) { $errors[] = "$where: unitatea de vanzare lipseste sau e prea lunga."; continue; }

        $path = array_values(array_filter(array_map('trim', explode('>', $get('category'))), fn ($p) => $p !== ''));
        if (count($path) < 1 || count($path) > 3) { $errors[] = "$where: categoria trebuie sa aiba 1–3 niveluri separate prin „>\"."; continue; }
        foreach ($path as $p) {
            if (mb_strlen($p) > 120) { $errors[] = "$where: numele de categorie „{$p}\" are peste 120 de caractere."; continue 2; }
        }

        $status = $get('status');
        $verify = $status === '' ? null : (CATALOG_STATUS[headerKey($status)] ?? null);
        if ($status !== '' && $verify === null) { $errors[] = "$where: status necunoscut („{$status}\") — OK / De verificat / Negasit."; continue; }

        $allSpecs = parseSpecs($get('specs'));
        $specs = array_values(array_filter($allSpecs, fn ($s) => !in_array($s['key'], SPEC_INTERNAL_KEYS, true)));
        $internalSpecs = array_values(array_filter($allSpecs, fn ($s) => in_array($s['key'], SPEC_INTERNAL_KEYS, true)));

        $context = $name . ' ' . $get('original') . ' ' . implode(' ', array_column($allSpecs, 'value'));
        $tagSplit = splitTags($get('tags'), $context);
        foreach ($tagSplit['fixed'] as $t) { $fixedTags[$t] = ($fixedTags[$t] ?? 0) + 1; }

        $html = cleanDescriptionHtml($get('description'));
        $notes = trim($get('notes') . ($internalSpecs ? "\n" . implode("\n", array_map(fn ($s) => $s['key'] . ': ' . $s['value'], $internalSpecs)) : ''));

        $products[] = [
            'row' => $r, 'barcode' => $barcode, 'sku' => $get('sku') ?: null, 'name' => $name,
            'original_name' => $get('original') ?: null, 'manufacturer' => $get('manufacturer') ?: null,
            'product_type' => $get('type') ?: null, 'price' => $price, 'unit' => $unit, 'path' => $path,
            'tags' => $tagSplit['tags'], 'description_html' => $html ?: null,
            'short_description' => shortFromDescription($html) ?: null,
            'specs' => $specs, 'documents' => splitUrls($get('documents')), 'sources' => splitUrls($get('sources')),
            'verify_status' => $verify, 'notes' => $notes ?: null,
        ];
    }
    return ['products' => $products, 'errors' => $errors, 'fixedTags' => $fixedTags];
}

/** Grupa fiecarui tag din fisier, cu producatorii si tipurile de produs din acelasi fisier. */
function classifyCatalogTags(array $products): array
{
    $brands = array_values(array_unique(array_filter(array_map(fn ($p) => mb_strtolower((string) $p['manufacturer']), $products))));
    $types = array_values(array_unique(array_filter(array_map(fn ($p) => mb_strtolower((string) $p['product_type']), $products))));
    // un tag care repeta doar numele unei categorii (ex. „acoperiș") nu adauga nimic — categoria exista deja
    $categoryNames = [];
    foreach ($products as $p) { foreach ($p['path'] as $c) { $categoryNames[mb_strtolower($c)] = true; } }
    $groups = [];
    foreach ($products as $p) {
        foreach ($p['tags'] as $t) {
            if (isset($groups[$t])) { continue; }
            $g = classifyTag($t, $brands, $types);
            $groups[$t] = $g === 'aplicatie' && isset($categoryNames[mb_strtolower(trim($t))]) ? 'ignorat' : $g;
        }
    }
    return $groups;
}

/**
 * Scrie catalogul intr-o singura tranzactie. Cu $apply = false calculeaza tot si face rollback.
 * @return array<string,mixed> statistici pentru raport
 */
function applyCatalog(PDO $pdo, array $products, array $tagGroups, bool $apply, bool $replaceOld): array
{
    $st = ['created' => 0, 'updated' => 0, 'old_deleted' => 0, 'old_categories_deleted' => 0,
           'categories_created' => 0, 'tags_created' => 0, 'untouched' => 0];
    $now = date('Y-m-d H:i:s');

    $pdo->beginTransaction();
    try {
        $adminId = (int) $pdo->query("SELECT id FROM users ORDER BY role = 'admin' DESC, id LIMIT 1")->fetchColumn();
        if ($adminId <= 0) { throw new RuntimeException('Nu exista niciun utilizator in `users` (created_by e obligatoriu).'); }

        if ($replaceOld) {
            $st['old_deleted'] = $pdo->exec('DELETE FROM products WHERE barcode IS NULL');
            // Categoriile ramase fara produse (cele vechi) se sterg ACUM, inainte sa creez categoriile noi: altfel o
            // categorie noua cu acelasi nume (ex. „Acoperis") nu ar mai primi slug-ul vechi `acoperis` si s-ar pierde
            // adresa /acoperis.html indexata de Google. Frunzele intai (parent_id e RESTRICT).
            do {
                $del = $pdo->exec('DELETE c FROM categories c
                    LEFT JOIN products p ON p.category_id = c.id
                    LEFT JOIN categories ch ON ch.parent_id = c.id
                    WHERE p.id IS NULL AND ch.id IS NULL');
                $st['old_categories_deleted'] += $del;
            } while ($del > 0);
        }

        // --- categorii: gasite dupa (parinte, nume), create la nevoie
        $catRows = $pdo->query('SELECT id, parent_id, slug, name FROM categories')->fetchAll(PDO::FETCH_ASSOC);
        $catByKey = []; $slugs = [];
        foreach ($catRows as $c) {
            $catByKey[(int) $c['parent_id'] . '|' . mb_strtolower($c['name'])] = (int) $c['id'];
            $slugs[$c['slug']] = true;
        }
        $insCat = $pdo->prepare('INSERT INTO categories (slug, parent_id, name, sort_order, is_active) VALUES (?, ?, ?, ?, 1)');
        $usedCats = [];
        // categoriile noi se adauga dupa cele existente (fiecare fisier ar porni altfel tot de la 1)
        $catSort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM categories')->fetchColumn();
        $categoryFor = function (array $path) use (&$catByKey, &$slugs, $insCat, $pdo, &$st, &$usedCats, &$catSort): int {
            $parent = 0; $parentSlug = '';
            foreach ($path as $name) {
                $key = $parent . '|' . mb_strtolower($name);
                if (!isset($catByKey[$key])) {
                    $slug = slugify($name, 80);
                    if (isset($slugs[$slug])) { $slug = slugify($parentSlug . ' ' . $name, 80); }
                    for ($i = 2; isset($slugs[$slug]); $i++) { $slug = slugify($name, 76) . "-$i"; }
                    $insCat->execute([$slug, $parent ?: null, $name, ++$catSort]);
                    $catByKey[$key] = (int) $pdo->lastInsertId();
                    $slugs[$slug] = true;
                    $st['categories_created']++;
                }
                $parent = $catByKey[$key];
                $usedCats[$parent] = true;
                $parentSlug = (string) $pdo->query('SELECT slug FROM categories WHERE id = ' . $parent)->fetchColumn();
            }
            return $parent;
        };

        // --- tag-uri
        $tagIds = [];
        foreach ($pdo->query('SELECT id, slug FROM tags')->fetchAll(PDO::FETCH_ASSOC) as $t) { $tagIds[$t['slug']] = (int) $t['id']; }
        $insTag = $pdo->prepare('INSERT INTO tags (slug, name, grp) VALUES (?, ?, ?)');

        // --- produse
        $find = $pdo->prepare('SELECT id, slug FROM products WHERE barcode = ?');
        $slugTaken = $pdo->prepare('SELECT COUNT(*) FROM products WHERE slug = ? AND (barcode IS NULL OR barcode <> ?)');
        $ins = $pdo->prepare('INSERT INTO products (barcode, sku, slug, category_id, page_slug, card_kind, name, original_name,
                manufacturer, product_type, short_description, description_html, image_alt, price, price_unit, price_is_from,
                stock_status, verify_status, internal_notes, source_urls, imported_at, is_visible, sort_order, created_by)
            VALUES (?, ?, ?, ?, \'\', \'material\', ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, \'in_stoc\', ?, ?, ?, ?, 1, ?, ?)');
        $upd = $pdo->prepare('UPDATE products SET sku = ?, category_id = ?, name = ?, original_name = ?, manufacturer = ?,
                product_type = ?, short_description = ?, description_html = ?, image_alt = COALESCE(image_alt, ?), price = ?,
                price_unit = ?, price_is_from = 0, verify_status = ?, internal_notes = ?, source_urls = ?, imported_at = ?,
                sort_order = ? WHERE id = ?');
        $delSpecs = $pdo->prepare('DELETE FROM product_specs WHERE product_id = ?');
        $insSpec = $pdo->prepare('INSERT INTO product_specs (product_id, sort_order, spec_key, spec_value) VALUES (?, ?, ?, ?)');
        $delDocs = $pdo->prepare('DELETE FROM product_documents WHERE product_id = ?');
        $insDoc = $pdo->prepare('INSERT INTO product_documents (product_id, sort_order, url, label) VALUES (?, ?, ?, ?)');
        $delTags = $pdo->prepare('DELETE FROM product_tags WHERE product_id = ?');
        $insPT = $pdo->prepare('INSERT IGNORE INTO product_tags (product_id, tag_id) VALUES (?, ?)');

        foreach ($products as $i => $p) {
            $catId = $categoryFor($p['path']);
            $sources = $p['sources'] ? implode("\n", $p['sources']) : null;
            $find->execute([$p['barcode']]);
            $cur = $find->fetch(PDO::FETCH_ASSOC);
            if ($cur) {
                $id = (int) $cur['id'];
                $upd->execute([$p['sku'], $catId, $p['name'], $p['original_name'], $p['manufacturer'], $p['product_type'],
                    $p['short_description'], $p['description_html'], $p['name'], $p['price'], $p['unit'], $p['verify_status'],
                    $p['notes'], $sources, $now, $i + 1, $id]);
                $st['updated']++;
            } else {
                $slug = slugify($p['name']);
                $slugTaken->execute([$slug, $p['barcode']]);
                if ((int) $slugTaken->fetchColumn() > 0) { $slug = slugify($p['name'], 105) . '-' . substr($p['barcode'], -6); }
                $ins->execute([$p['barcode'], $p['sku'], $slug, $catId, $p['name'], $p['original_name'], $p['manufacturer'],
                    $p['product_type'], $p['short_description'], $p['description_html'], $p['name'], $p['price'], $p['unit'],
                    $p['verify_status'], $p['notes'], $sources, $now, $i + 1, $adminId]);
                $id = (int) $pdo->lastInsertId();
                $st['created']++;
            }

            $delSpecs->execute([$id]);
            foreach ($p['specs'] as $k => $s) { $insSpec->execute([$id, $k + 1, mb_substr($s['key'], 0, 120), $s['value']]); }
            $delDocs->execute([$id]);
            foreach ($p['documents'] as $k => $u) { $insDoc->execute([$id, $k + 1, mb_substr($u, 0, 1000), 'Fisa tehnica']); }
            $delTags->execute([$id]);
            foreach ($p['tags'] as $t) {
                $grp = $tagGroups[$t] ?? 'aplicatie';
                if ($grp === 'ignorat') { continue; }
                $slug = slugify($t, 120);
                if ($slug === '') { continue; }
                if (!isset($tagIds[$slug])) {
                    $insTag->execute([$slug, mb_substr($t, 0, 120), $grp]);
                    $tagIds[$slug] = (int) $pdo->lastInsertId();
                    $st['tags_created']++;
                }
                $insPT->execute([$id, $tagIds[$slug]]);
            }
        }
        $st['untouched'] = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE barcode IS NOT NULL')->fetchColumn()
            - $st['created'] - $st['updated'];
        $st['old_remaining'] = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE barcode IS NULL')->fetchColumn();

        if ($apply) { $pdo->commit(); } else { $pdo->rollBack(); }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
    return $st;
}

/** Raportul citit inainte de --apply: arborele de categorii, tag-urile pe grupe, ce s-a reparat, ce lipseste. */
function catalogReport(array $parsed, array $tagGroups): string
{
    $products = $parsed['products'];
    $out = [];
    $tree = [];
    foreach ($products as $p) {
        $node = &$tree;
        foreach ($p['path'] as $name) { $node[$name] ??= ['#' => 0]; $node[$name]['#']++; $node = &$node[$name]; }
        unset($node);
    }
    $walk = function (array $n, int $depth) use (&$walk, &$out): void {
        foreach ($n as $name => $child) {
            if ($name === '#') { continue; }
            $out[] = str_repeat('    ', $depth) . "- $name ({$child['#']})";
            $walk($child, $depth + 1);
        }
    };
    $out[] = 'CATEGORII:';
    $walk($tree, 1);

    $byGroup = [];
    $counts = [];
    foreach ($products as $p) { foreach ($p['tags'] as $t) { $counts[$t] = ($counts[$t] ?? 0) + 1; } }
    foreach ($tagGroups as $t => $g) { $byGroup[$g][$t] = $counts[$t] ?? 0; }
    $out[] = '';
    $out[] = 'TAG-URI PE GRUPE (aplicatie = pagina-colectie; ignorat = nu se salveaza):';
    $order = array_merge(array_keys(TAG_GROUP_LABELS), ['ignorat']);
    foreach ($order as $g) {
        if (empty($byGroup[$g])) { continue; }
        arsort($byGroup[$g]);
        $out[] = sprintf('    %-15s %s', $g, implode(', ', array_map(fn ($t, $n) => "$t ($n)", array_keys($byGroup[$g]), $byGroup[$g])));
    }
    $out[] = '';
    $out[] = 'TAG-URI REPARATE (virgula zecimala): ' . ($parsed['fixedTags']
        ? implode(', ', array_map(fn ($t, $n) => "$t ×$n", array_keys($parsed['fixedTags']), $parsed['fixedTags'])) : 'niciunul');
    $suspect = array_keys(array_filter($tagGroups, fn ($g, $t) => $g !== 'ignorat' && preg_match('/^\d+$/u', $t), ARRAY_FILTER_USE_BOTH));
    if ($suspect) { $out[] = 'ATENTIE — tag-uri doar cu cifre (posibil bucati ramase): ' . implode(', ', $suspect); }

    $noPrice = array_filter($products, fn ($p) => $p['price'] === null);
    $out[] = '';
    $out[] = 'FARA PRET (apar cu „Pret la cerere", fara cos): ' . ($noPrice ? implode('; ', array_map(fn ($p) => $p['name'], $noPrice)) : 'niciunul');
    $st = array_count_values(array_map(fn ($p) => $p['verify_status'] ?? '(gol)', $products));
    $out[] = 'STATUS: ' . implode(', ', array_map(fn ($k, $v) => "$k $v", array_keys($st), $st));
    $noDesc = count(array_filter($products, fn ($p) => $p['description_html'] === null));
    $noSpecs = count(array_filter($products, fn ($p) => !$p['specs']));
    $out[] = "Fara descriere: $noDesc; fara specificatii: $noSpecs; cu fisa tehnica: "
        . count(array_filter($products, fn ($p) => $p['documents']));
    return implode("\n", $out) . "\n";
}

/**
 * Pozele catalogului: img/produse/mu-<cod de bare>-<descriere>.webp (fisierele de la Dunlite). Leaga fiecare poza
 * de produsul cu acel cod de bare. Nu atinge pozele urcate din admin (uploads/products): leaga doar produsele fara
 * poza sau cu o poza tot din img/produse. Se poate rula oricand (deploy, import) — rezultatul e acelasi.
 * @return array{linked:int, unmatched:string[]}
 */
function attachCatalogPhotos(PDO $pdo, string $imgDir): array
{
    $upd = $pdo->prepare("UPDATE products SET image_path = ? WHERE UPPER(barcode) = ?
        AND (image_path IS NULL OR image_path = '' OR (image_path LIKE 'img/produse/%' AND image_path <> ?))");
    $find = $pdo->prepare('SELECT COUNT(*) FROM products WHERE UPPER(barcode) = ?');
    $linked = 0; $unmatched = [];
    // poza scoasa din folder (ex. una gresita, mutata la „Poze de corectat") => produsul revine la panoul generat
    $gone = $pdo->prepare('UPDATE products SET image_path = NULL WHERE image_path = ?');
    foreach ($pdo->query("SELECT DISTINCT image_path FROM products WHERE image_path LIKE 'img/produse/%'")->fetchAll(PDO::FETCH_COLUMN) as $path) {
        if (!is_file(rtrim($imgDir, '/\\') . '/' . basename($path))) { $gone->execute([$path]); }
    }
    foreach (glob(rtrim($imgDir, '/\\') . '/mu-*.webp') ?: [] as $file) {
        $name = basename($file);
        if (!preg_match('/^mu-([a-z0-9]+)-/i', $name, $m)) { continue; }
        $code = strtoupper($m[1]);
        $path = 'img/produse/' . $name;
        $upd->execute([$path, $code, $path]);
        $linked += $upd->rowCount();
        $find->execute([$code]);
        if ((int) $find->fetchColumn() === 0) { $unmatched[] = $name; }
    }
    return ['linked' => $linked, 'unmatched' => $unmatched];
}

function importCatalogMain(array $argv): int
{
    $file = null; $apply = false; $replaceOld = false;
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--apply') { $apply = true; }
        elseif ($arg === '--replace-old') { $replaceOld = true; }
        else { $file = $arg; }
    }
    if ($file === null || !is_file($file)) {
        fwrite(STDERR, "Folosire: php includes/catalog_import.php <fisier.xlsx> [--apply] [--replace-old]\n");
        return 2;
    }

    require_once __DIR__ . '/../admin/config.php';
    echo 'Baza tinta: ' . DB_NAME . ' @ ' . DB_HOST . ($apply ? '  [SCRIE]' : '  [doar verificare]')
        . ($replaceOld ? '  [STERGE CATALOGUL VECHI]' : '') . "\n";

    try {
        $parsed = parseCatalogRows(readXlsxSheet($file, null));
    } catch (Throwable $e) {
        fwrite(STDERR, 'EROARE: ' . $e->getMessage() . "\n");
        return 1;
    }
    if ($parsed['errors']) {
        echo 'Fisierul are ' . count($parsed['errors']) . " erori — nu s-a importat nimic:\n";
        foreach ($parsed['errors'] as $err) { echo "  - $err\n"; }
        return 1;
    }
    $tagGroups = classifyCatalogTags($parsed['products']);
    echo count($parsed['products']) . " produse citite.\n\n" . catalogReport($parsed, $tagGroups) . "\n";

    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    try {
        $st = applyCatalog($pdo, $parsed['products'], $tagGroups, $apply, $replaceOld);
    } catch (Throwable $e) {
        echo 'EROARE la scriere — nu s-a importat nimic: ' . $e->getMessage() . "\n";
        return 1;
    }
    $verb = $apply ? '' : 'S-ar face: ';
    echo "{$verb}produse noi {$st['created']}, actualizate {$st['updated']}, categorii noi {$st['categories_created']}, "
        . "tag-uri noi {$st['tags_created']}.\n";
    if ($replaceOld) {
        echo "{$verb}catalog vechi sters: {$st['old_deleted']} produse, {$st['old_categories_deleted']} categorii.\n";
    }
    echo "Produse vechi (fara cod de bare) ramase: {$st['old_remaining']}; produse noi din alte fisiere, neatinse: {$st['untouched']}.\n";
    if (!$apply) { echo "Nimic scris. Ruleaza cu --apply ca sa aplici.\n"; return 0; }
    $ph = attachCatalogPhotos($pdo, __DIR__ . '/../img/produse');
    echo "Poze legate: {$ph['linked']}" . ($ph['unmatched'] ? '; fara produs: ' . implode(', ', $ph['unmatched']) : '') . "\n";
    return 0;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(importCatalogMain($argv));
}
