<?php
declare(strict_types=1);

/**
 * deploy_db.php — pregateste baza de date la deploy, fara pasi manuali.
 *
 * Rulat de .cpanel.yml dupa copierea fisierelor, din directorul clonei git:
 *   php db/deploy_db.php /home/mugurel/public_html
 *
 * Ce face:
 *   1. Incarca credentialele din <public_html>/admin/config.local.php.
 *   2. Aplica migrarile 002..012 (idempotente).
 *   3. Importa catalogul nou din db/catalog/*.xlsx O SINGURA DATA — doar cand
 *      nu exista inca niciun produs cu cod de bare (vezi pasul 2 din main()).
 *   4. Afiseaza un rezumat.
 *
 * Contract critic: acest script NU trebuie sa opreasca niciodata deploy-ul.
 * Orice eroare se scrie in output si scriptul iese cu cod 0.
 */

function out(string $msg): void
{
    fwrite(STDOUT, $msg . "\n");
}

/**
 * Transforma un fisier .sql scris pentru clientul `mysql` CLI (care poate
 * folosi DELIMITER // in jurul procedurilor stocate) intr-un text SQL
 * standard, executabil direct pe conexiune (PDO/mysqli), unde separatorul
 * de instructiuni ramane intotdeauna ';'.
 *
 * DELIMITER e o directiva doar pentru clientul CLI — serverul MySQL nu o
 * intelege. Liniile DELIMITER sunt eliminate, iar terminatorul custom
 * (ex. "//" la finalul liniei "END//") e inlocuit cu ';'. Restul textului
 * ramane neatins, deci ';' din interiorul corpului CREATE PROCEDURE
 * (BEGIN..END) ajunge la server ca parte a unei singure instructiuni —
 * serverul, spre deosebire de clientul CLI, stie sa parseze un bloc
 * BEGIN..END ca o singura instructiune chiar daca acesta contine ';'.
 */
function normalizeSql(string $sql): string
{
    $delimiter = ';';
    $lines = explode("\n", $sql);
    $out = [];

    foreach ($lines as $line) {
        if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $m)) {
            $delimiter = $m[1];
            continue; // directiva doar pentru clientul CLI, nu se trimite la server
        }

        if ($delimiter !== ';') {
            $quoted = preg_quote($delimiter, '/');
            if (preg_match('/' . $quoted . '\s*$/', $line)) {
                $line = preg_replace('/' . $quoted . '\s*$/', ';', $line);
            }
        }

        $out[] = $line;
    }

    return implode("\n", $out);
}

/** Ruleaza continutul intreg al unui fisier .sql (normalizat) intr-un singur exec(). */
function runSqlFile(PDO $pdo, string $path): void
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException("Nu pot citi fisierul: $path");
    }
    $pdo->exec(normalizeSql($raw));
}

function countTable(PDO $pdo, string $table): ?int
{
    try {
        $stmt = $pdo->query('SELECT COUNT(*) FROM `' . $table . '`');
        if ($stmt === false) {
            return null;
        }
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return null;
    }
}

function main(array $argv): int
{
    out('=== deploy_db.php — pregatire baza de date la deploy ===');

    $publicHtml = $argv[1] ?? null;
    if ($publicHtml === null || $publicHtml === '') {
        out('EROARE: lipseste argumentul <public_html>. Sar peste pasul de baza de date.');
        return 0;
    }

    $configPath = rtrim($publicHtml, '/\\') . '/admin/config.local.php';
    if (!is_file($configPath)) {
        out("EROARE: nu gasesc config-ul la $configPath. Sar peste pasul de baza de date.");
        return 0;
    }

    try {
        require $configPath;
    } catch (Throwable $e) {
        out('EROARE la incarcarea config.local.php: ' . $e->getMessage());
        return 0;
    }

    foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $const) {
        if (!defined($const)) {
            out("EROARE: constanta $const nu e definita in config.local.php. Sar peste pasul de baza de date.");
            return 0;
        }
    }

    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
            ]
        );
    } catch (Throwable $e) {
        out('EROARE: conectare esuata la baza de date: ' . $e->getMessage());
        return 0;
    }

    $dbDir = __DIR__;

    // 1. Migrari, in ordine, idempotente.
    $migrations = [
        'migration_002_hierarchy.sql',
        'migration_003_properties.sql',
        'migration_004_images_badges.sql',
        'migration_005_accessories.sql',
        'migration_006_wa_text.sql',
        'migration_007_price_from.sql',
        'migration_008_stock.sql',
        'migration_009_order_attempts.sql',
        'migration_010_orders.sql',
        'migration_011_catalog_nou.sql',
        'migration_012_collections.sql',
        'migration_013_returns.sql',
        'migration_014_stock_qty.sql',
    ];

    $runMigrations = function () use ($pdo, $dbDir, $migrations): array {
        $applied = [];
        foreach ($migrations as $file) {
            $path = $dbDir . '/' . $file;
            if (!is_file($path)) {
                out("ATENTIE: migrarea $file lipseste din db/, o sar.");
                continue;
            }
            try {
                runSqlFile($pdo, $path);
                $applied[] = $file;
                out("OK — migrare aplicata: $file");
            } catch (Throwable $e) {
                out("EROARE la migrarea $file: " . $e->getMessage());
            }
        }
        return $applied;
    };

    $applied = $runMigrations();

    // 2. Catalogul nou (fisierele Dunlite din db/catalog/*.xlsx) — O SINGURA DATA.
    //
    // Se importa doar daca baza nu are inca niciun produs cu cod de bare (= catalogul nou nu exista inca).
    // Atunci, intr-o singura tranzactie: se sterg produsele vechi (fara cod de bare) si categoriile lor ramase
    // goale, si se importa toate fisierele. La orice deploy urmator catalogul nou exista => pasul se sare, deci
    // preturile/stocul/pozele schimbate de Mugurel din admin NU sunt suprascrise. Fisierele noi se incarca din
    // admin (Import Excel), nu prin deploy.
    //
    // (Pana la 16 oct 2026 aici se importa productie_date.sql — catalogul vechi de 534 de produse, cu DROP TABLE.
    //  Garda veche ar fi considerat catalogul nou „date vechi" si l-ar fi sters. Fisierul a fost scos.)
    $catalogDir = $dbDir . '/catalog';
    $importStatus = 'sarit';
    $files = glob($catalogDir . '/*.xlsx') ?: [];
    sort($files, SORT_NATURAL); // 1-constructii, 2-tabla, 3-electrocasnice: ordinea categoriilor din meniu

    $newProducts = null;
    try {
        $stmt = $pdo->query('SELECT COUNT(*) FROM products WHERE barcode IS NOT NULL');
        $newProducts = $stmt === false ? null : (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        out('EROARE: nu pot citi produsele (probabil migrarea 011 a esuat mai sus): ' . $e->getMessage());
    }

    if ($newProducts === null) {
        out('Catalog SARIT: tabela `products` nu poate fi citita.');
    } elseif ($newProducts > 0) {
        out("Catalog SARIT: catalogul nou exista deja ($newProducts produse cu cod de bare) — nu-l ating.");
    } elseif (!$files) {
        out('ATENTIE: niciun fisier in db/catalog/*.xlsx — catalogul nou nu s-a importat.');
    } else {
        try {
            require_once $dbDir . '/../includes/catalog_import.php';
            $all = [];
            foreach ($files as $file) {
                $parsed = parseCatalogRows(readXlsxSheet($file, null));
                if ($parsed['errors']) {
                    throw new RuntimeException(basename($file) . ' are ' . count($parsed['errors']) . ' erori, ex.: ' . $parsed['errors'][0]);
                }
                out('Citit ' . basename($file) . ': ' . count($parsed['products']) . ' produse.');
                $all = array_merge($all, $parsed['products']);
            }
            $st = applyCatalog($pdo, $all, classifyCatalogTags($all), true, true);
            $importStatus = 'importat';
            out("OK — catalog nou: {$st['created']} produse, {$st['categories_created']} categorii, {$st['tags_created']} tag-uri; "
                . "catalog vechi sters: {$st['old_deleted']} produse, {$st['old_categories_deleted']} categorii.");
        } catch (Throwable $e) {
            // applyCatalog lucreaza intr-o singura tranzactie: la eroare baza ramane exact cum era.
            out('EROARE la importul catalogului (nimic schimbat): ' . $e->getMessage());
        }
    }

    // Pozele catalogului (img/produse) — la fiecare deploy, ca pozele adaugate ulterior sa se lege singure.
    if ($newProducts !== null) {
        try {
            require_once $dbDir . '/../includes/catalog_import.php';
            $ph = attachCatalogPhotos($pdo, $dbDir . '/../img/produse');
            $fd = attachCatalogDocuments($pdo, $dbDir . '/../fise-tehnice');
            out("Fise tehnice de pe site legate acum: {$fd['linked']}" . ($fd['unmatched'] ? '; fara produs: ' . implode(', ', $fd['unmatched']) : ''));
            out("Poze catalog legate acum: {$ph['linked']}" . ($ph['unmatched'] ? '; fara produs: ' . implode(', ', $ph['unmatched']) : ''));
        } catch (Throwable $e) {
            out('EROARE la legarea pozelor (catalogul nu e afectat): ' . $e->getMessage());
        }
    }

    // 3. Rezumat.
    out('--- Rezumat ---');
    out('Migrari aplicate: ' . (count($applied) > 0 ? implode(', ', $applied) : 'niciuna'));
    out('Import catalog: ' . $importStatus);

    $counts = [
        'categories' => 'categorii',
        'products' => 'produse',
        'tags' => 'tag-uri',
        'collections' => 'colectii',
    ];
    foreach ($counts as $table => $label) {
        $n = countTable($pdo, $table);
        out($n === null ? "Nu pot numara $label (tabela $table)." : (ucfirst($label) . ": $n"));
    }

    out('=== deploy_db.php — gata ===');
    return 0;
}

try {
    $exitCode = main($argv);
} catch (Throwable $e) {
    // Ultima plasa de siguranta: deploy-ul de fisiere nu trebuie sa se opreasca niciodata din cauza acestui script.
    fwrite(STDOUT, 'EROARE NEASTEPTATA in deploy_db.php: ' . $e->getMessage() . "\n");
    $exitCode = 0;
}

exit($exitCode);
