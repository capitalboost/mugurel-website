<?php
/**
 * Importa in `products` cele 40 de produse care traiesc DOAR in catalog.html
 * (adaugate in august ca sa umple subcategoriile goale) si, pentru cele 44 care
 * exista deja (importate din paginile de categorie), muta-le category_id catre
 * subcategoria lor daca `catalog.html` o cunoaste (data-subcat).
 *
 * catalog.html nu mai exista pe disc dupa conversia paginilor la .php — se
 * citeste din istoricul git, la commit-ul dinaintea conversiei:
 *   git show 41edc4b:catalog.html
 *
 * Idempotent: rerularea nu creeaza dubluri si nu schimba nimic in plus.
 * Nu atinge page_slug/section_key/proprietati/fotografie/badge/icon_key ale
 * produselor deja existente — doar category_id, si doar daca au data-subcat.
 * Produsele noi primesc icon_key din SVG-ul propriu (prod-img-ph), calculat cu
 * acelasi iconKeyForSvg() ca db/extract_icons.php — biblioteca de iconite
 * trebuie regenerata (db/extract_icons.php) ca sa contina si aceste chei.
 *
 * Usage: php db/migrate_catalog_products.php
 */

if (file_exists(__DIR__ . '/admin/config.php')) {
    require_once __DIR__ . '/admin/config.php';
    require_once __DIR__ . '/admin/models/Database.php';
} else {
    require_once __DIR__ . '/../admin/config.php';
    require_once __DIR__ . '/../admin/models/Database.php';
}

if (PHP_SAPI !== 'cli') { die("Doar din linia de comanda.\n"); }

const CATALOG_COMMIT = '41edc4b';

/** Slug-uri din data-cat care nu se potrivesc direct cu categories.slug. */
const CATEGORY_ALIAS = [
    'scule' => 'scule-unelte',
];

function normalizeSvg(string $svg): string { return trim(preg_replace('/\s+/', ' ', $svg)); }
function iconKeyForSvg(string $svg): string { return 'icon-' . substr(sha1(normalizeSvg($svg)), 0, 8); }
function stripTags2(string $s): string {
    $s = html_entity_decode($s, ENT_QUOTES, 'UTF-8');
    return trim(preg_replace('/\s+/', ' ', strip_tags($s)));
}

/** Citeste catalog.html din git. Ruleaza din radacina proiectului Website/ (parintele lui db/). */
function readCatalogFromGit(): string {
    $repoRoot = dirname(__DIR__);
    $cmd = 'git -C ' . escapeshellarg($repoRoot) . ' show ' . escapeshellarg(CATALOG_COMMIT . ':catalog.html');
    $html = shell_exec($cmd);
    if ($html === null || trim($html) === '') {
        fwrite(STDERR, "Eroare: nu am putut citi catalog.html din git (" . CATALOG_COMMIT . ")\n");
        exit(1);
    }
    // git pe Windows poate intoarce CRLF; normalizam ca regexurile bazate pe \s sa nu se lege de \r.
    return str_replace("\r\n", "\n", $html);
}

/**
 * Extrage cardurile .prod-card din catalog.html.
 * Returneaza array de: name, descr, catSlugs (data-cat, poate fi gol), subcatSlug (sau null), svg (sau null).
 */
function parseCatalogCards(string $html): array {
    preg_match_all(
        '#<div class="prod-card" data-cat="([^"]*)"(?: data-subcat="([^"]*)")?\s*>(.*?)(?=<div class="prod-card"|\z)#s',
        $html, $matches, PREG_SET_ORDER
    );
    $cards = [];
    foreach ($matches as $m) {
        if (!preg_match('#<h3 class="prod-name">(.*?)</h3>#s', $m[3], $n)) { continue; }
        $name = stripTags2($n[1]);
        if ($name === '') { continue; }

        $descr = preg_match('#<div class="prod-desc">(.*?)</div>#s', $m[3], $d) ? stripTags2($d[1]) : '';

        $catSlugs = array_values(array_filter(preg_split('/\s+/', trim($m[1]))));
        $subcat = isset($m[2]) && $m[2] !== '' ? $m[2] : null;

        $svg = null;
        $iconLabel = null;
        if (preg_match('#<div class="prod-img-ph">(.*?)</div>#s', $m[3], $ph)) {
            if (preg_match('#(<svg.*?</svg>)#s', $ph[1], $s)) { $svg = $s[1]; }
            // Eticheta placeholder-ului, doar cand chiar exista un <span> in el — catalog.html
            // nu are niciunul azi, dar nu presupunem asta pentru totdeauna.
            if (preg_match('#<span>(.*?)</span>#s', $ph[1], $l)) {
                $iconLabel = stripTags2($l[1]);
                if ($iconLabel === '') { $iconLabel = null; }
            }
        }

        $cards[] = [
            'name' => $name, 'descr' => $descr, 'catSlugs' => $catSlugs, 'subcat' => $subcat,
            'svg' => $svg, 'iconLabel' => $iconLabel,
        ];
    }
    return $cards;
}

$db = Database::get();

$catBySlug = [];
foreach ($db->query('SELECT id, slug, parent_id FROM categories')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $catBySlug[$r['slug']] = $r;
}

$adminId = (int)$db->query('SELECT id FROM users WHERE role="admin" LIMIT 1')->fetchColumn();
if (!$adminId) { die("Eroare: nu exista user admin in DB. Ruleaza intai db/seed_admin.php\n"); }

/** Rezolva un slug din data-cat (aplica CATEGORY_ALIAS) intr-un rand din categories, sau null. */
function resolveCategorySlug(string $slug, array $catBySlug): ?array {
    $slug = CATEGORY_ALIAS[$slug] ?? $slug;
    return $catBySlug[$slug] ?? null;
}

$html  = readCatalogFromGit();
$cards = parseCatalogCards($html);

// 'name' e utf8mb4_unicode_ci (case-insensitive) — potrivire deliberat insensibila la
// capitalizare: catalog.html si paginile de categorie scriu uneori acelasi produs cu alta
// capitalizare (ex. "Set 4 scaune bucatarie tapitate" vs "Set 4 Scaune Bucatarie Tapitate"
// din mobilier.php). Verificat: e singurul caz de acest fel din cele 535 de randuri.
$findByName   = $db->prepare('SELECT id, category_id, page_slug, icon_key, icon_label FROM products WHERE name = ? LIMIT 2');
$updateCat    = $db->prepare('UPDATE products SET category_id = ? WHERE id = ?');
$updateIcon   = $db->prepare('UPDATE products SET icon_key = ?, icon_label = ? WHERE id = ?');
$insert       = $db->prepare(
    'INSERT INTO products
        (category_id, page_slug, card_kind, card_modifier, section_key, name, short_description,
         wa_text, icon_key, icon_label, image_path, image_alt, badge_label, badge_kind,
         sort_order, created_by)
     VALUES (:cat, \'catalog\', \'material\', NULL, \'catalog\', :name, :descr, NULL, :ikey, :ilabel, NULL, NULL, NULL, NULL, :sort, :by)
     ON DUPLICATE KEY UPDATE
        category_id = VALUES(category_id),
        short_description = VALUES(short_description),
        icon_key = VALUES(icon_key), icon_label = VALUES(icon_label),
        sort_order = VALUES(sort_order), id = LAST_INSERT_ID(id)'
);
$linkExtra = $db->prepare('INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (?, ?)');

$stats = ['matched' => 0, 'catUpdated' => 0, 'iconUpdated' => 0, 'inserted' => 0, 'extraLinks' => 0, 'skippedNoCategory' => 0, 'dupNameWarnings' => 0];
$sort = 0;

foreach ($cards as $card) {
    $name    = $card['name'];
    $descr   = $card['descr'];
    $subcat  = $card['subcat'];
    $svg     = $card['svg'];
    $iconKey   = $svg !== null ? iconKeyForSvg($svg) : null;
    $iconLabel = $card['iconLabel'];

    // Categoria tinta: subcategoria din data-subcat daca exista, altfel prima din data-cat.
    $targetCat = null;
    if ($subcat !== null) {
        $targetCat = $catBySlug[$subcat] ?? null;
        if ($targetCat === null) {
            fwrite(STDERR, "ATENTIE: subcategoria necunoscuta '$subcat' pentru '$name', ignorata\n");
        }
    }
    if ($targetCat === null && $card['catSlugs']) {
        $targetCat = resolveCategorySlug($card['catSlugs'][0], $catBySlug);
    }
    if ($targetCat === null) {
        fwrite(STDERR, "SKIP (nicio categorie rezolvabila): $name\n");
        $stats['skippedNoCategory']++;
        continue;
    }
    // L1-ul "principal" al produsului: parintele subcategoriei alese, sau categoria insasi daca e deja L1.
    $primaryL1Id = $targetCat['parent_id'] ?? $targetCat['id'];

    $findByName->execute([$name]);
    $rows = $findByName->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) > 1) {
        fwrite(STDERR, "ATENTIE: numele '$name' apare de mai multe ori in products, folosesc primul rand\n");
        $stats['dupNameWarnings']++;
    }

    if ($rows) {
        // Produs deja existent (importat dintr-o pagina de categorie, sau catalog-only
        // dintr-o rulare anterioara a acestui script). Nu atingem page_slug, section_key,
        // proprietatile, fotografia sau badge-ul — doar category_id, si doar daca avem
        // o subcategorie certa din data-subcat.
        $pid = (int)$rows[0]['id'];
        $stats['matched']++;
        if ($subcat !== null && (int)$rows[0]['category_id'] !== (int)$targetCat['id']) {
            $updateCat->execute([$targetCat['id'], $pid]);
            $stats['catUpdated']++;
        }
        // Exceptie: cand randul e chiar al nostru (page_slug='catalog', dintr-o rulare
        // anterioara), icon_key/icon_label sunt proprietatea acestui script — le
        // actualizam sa reflecte sursa curenta. Randurile importate dintr-o pagina de
        // categorie (page_slug != 'catalog') au deja iconita lor corecta si nu se ating.
        if ($rows[0]['page_slug'] === 'catalog'
            && ((string)$rows[0]['icon_key'] !== (string)$iconKey
                || (string)$rows[0]['icon_label'] !== (string)$iconLabel)) {
            $updateIcon->execute([$iconKey, $iconLabel, $pid]);
            $stats['iconUpdated']++;
        }
    } else {
        // Produs nou: exista doar in catalog.html. page_slug='catalog' il tine in afara
        // paginilor de categorie — productsForPage()/productsForSection() nu cer niciodata
        // sectiunea 'catalog'.
        $insert->execute([
            ':cat' => $targetCat['id'], ':name' => $name, ':descr' => $descr,
            ':ikey' => $iconKey, ':ilabel' => $iconLabel, ':sort' => ++$sort, ':by' => $adminId,
        ]);
        $pid = (int)$db->lastInsertId();
        $stats['inserted']++;
    }

    // Legaturi suplimentare in product_categories pentru celelalte valori din data-cat,
    // dincolo de L1-ul principal deja acoperit de category_id (direct sau prin parinte).
    foreach ($card['catSlugs'] as $slug) {
        $extra = resolveCategorySlug($slug, $catBySlug);
        if ($extra === null) { continue; }
        $extraL1Id = $extra['parent_id'] ?? $extra['id'];
        if ($extraL1Id === $primaryL1Id) { continue; }
        $linkExtra->execute([$pid, $extraL1Id]);
        if ($linkExtra->rowCount() > 0) { $stats['extraLinks']++; }
    }
}

echo "Carduri procesate: " . count($cards) . "\n";
echo "Deja existente (matched pe nume): {$stats['matched']}\n";
echo "  din care category_id actualizat catre subcategorie: {$stats['catUpdated']}\n";
echo "  din care icon_key/icon_label actualizat (randuri proprii, page_slug=catalog): {$stats['iconUpdated']}\n";
echo "Nou inserate (page_slug=catalog): {$stats['inserted']}\n";
echo "Legaturi suplimentare in product_categories: {$stats['extraLinks']}\n";
if ($stats['skippedNoCategory']) { echo "SKIP fara categorie rezolvabila: {$stats['skippedNoCategory']}\n"; }
if ($stats['dupNameWarnings']) { echo "ATENTIE nume duplicate in products: {$stats['dupNameWarnings']}\n"; }
