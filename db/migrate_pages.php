<?php
/**
 * Ruleaza o singura data: php db/migrate_pages.php
 * Extrage continutul HTML din paginile statice existente si il insereaza in DB.
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../admin/models/Database.php';

$db = Database::get();

$migrations = [
    'despre-noi' => [
        'file'  => __DIR__ . '/../despre-noi.html',
        'title' => 'Despre Noi',
    ],
    'contact' => [
        'file'  => __DIR__ . '/../contact.html',
        'title' => 'Contact',
    ],
    'politica-confidentialitate' => [
        'file'  => __DIR__ . '/../politica-confidentialitate.html',
        'title' => 'Politica de Confidentialitate',
    ],
    'termeni-conditii' => [
        'file'  => __DIR__ . '/../termeni-conditii.html',
        'title' => 'Termeni si Conditii',
    ],
    'politica-retur' => [
        'file'  => __DIR__ . '/../politica-retur.html',
        'title' => 'Politica de Retur',
    ],
];

foreach ($migrations as $slug => $cfg) {
    if (!file_exists($cfg['file'])) {
        echo "  SKIP (fisier lipsa): {$cfg['file']}\n";
        continue;
    }

    $html = file_get_contents($cfg['file']);

    // Extrage continutul principal — incearca mai multe strategii
    $content = '';
    if (preg_match('/<div class="body-wrap">(.*?)<\/div>\s*<footer/s', $html, $m)) {
        $content = trim($m[1]);
    } elseif (preg_match('/<main[^>]*>(.*?)<\/main>/s', $html, $m)) {
        $content = trim($m[1]);
    } elseif (preg_match('/<\/nav>(.*?)<footer/s', $html, $m)) {
        $content = trim($m[1]);
    } else {
        // Fallback: tot ce e intre </header> si <footer
        $content = preg_replace('/^.*<\/header>/s', '', $html);
        $content = preg_replace('/<footer.*/s', '', $content);
        $content = trim($content);
    }

    if (empty($content)) {
        echo "  WARN (continut gol): $slug\n";
        continue;
    }

    $db->prepare('UPDATE pages SET content = ? WHERE slug = ?')->execute([$content, $slug]);
    echo "  OK: $slug (" . strlen($content) . " bytes)\n";
}

echo "\nMigrare completa. Verifica paginile in admin -> Pagini.\n";
