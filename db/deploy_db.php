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
 *   2. Aplica migrarile 002..006 (idempotente).
 *   3. Importa datele de productie din productie_date.sql O SINGURA DATA —
 *      doar daca tabela `products` e goala (guard obligatoriu: fisierul
 *      contine DROP TABLE si ar sterge orice a adaugat Mugurel din admin).
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
    ];

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

    // 2. Import date de productie — o singura data, cu guard pe tabela goala.
    $dataFile = $dbDir . '/productie_date.sql';
    $importStatus = 'sarit';

    if (!is_file($dataFile)) {
        out('ATENTIE: productie_date.sql lipseste din db/, sar peste import.');
    } else {
        $productsCount = countTable($pdo, 'products');

        if ($productsCount === null) {
            out('Import SARIT: tabela `products` nu exista sau nu poate fi citita (probabil migrarile au esuat mai sus).');
        } elseif ($productsCount > 0) {
            out("Import SARIT: tabela `products` are deja $productsCount randuri — nu ating datele existente.");
        } else {
            // productie_date.sql seteaza created_by=1 pe toate produsele (FK catre users.id).
            $adminExists = countTable($pdo, 'users');
            $hasUserOne = false;
            if ($adminExists !== null) {
                try {
                    $stmt = $pdo->query('SELECT COUNT(*) FROM users WHERE id = 1');
                    $hasUserOne = $stmt !== false && ((int) $stmt->fetchColumn()) > 0;
                } catch (Throwable $e) {
                    out('EROARE: nu pot verifica users.id=1: ' . $e->getMessage());
                }
            } else {
                out('EROARE: tabela `users` nu exista sau nu poate fi citita.');
            }

            if (!$hasUserOne) {
                out('Import SARIT: nu exista un user cu id=1 in tabela `users`. ' .
                    'productie_date.sql seteaza created_by=1 pe toate produsele (cheie straina catre users.id) — ' .
                    'creeaza intai un user admin cu id=1, apoi ruleaza deploy-ul din nou.');
            } else {
                try {
                    runSqlFile($pdo, $dataFile);
                    $importStatus = 'importat';
                    out('OK — date importate din productie_date.sql');
                } catch (Throwable $e) {
                    out('EROARE la importul de date: ' . $e->getMessage());
                }
            }
        }
    }

    // 3. Rezumat.
    out('--- Rezumat ---');
    out('Migrari aplicate: ' . (count($applied) > 0 ? implode(', ', $applied) : 'niciuna'));
    out('Import date: ' . $importStatus);

    $counts = [
        'categories' => 'categorii',
        'products' => 'produse',
        'product_properties' => 'proprietati',
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
