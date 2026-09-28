<?php
/**
 * Importa produsele din paginile statice HTML in baza de date.
 * Idempotent: rerularea actualizeaza randurile existente, nu creeaza dubluri.
 *
 * UPLOAD in public_html, acceseaza prin browser, STERGE imediat dupa.
 * Sau local: php db/migrate_products.php
 */

// Auto-detect: pe server config.php e in admin/, local e in ../admin/
if (file_exists(__DIR__ . '/admin/config.php')) {
    require_once __DIR__ . '/admin/config.php';
    require_once __DIR__ . '/admin/models/Database.php';
    $htmlBase = __DIR__ . '/';
} else {
    require_once __DIR__ . '/../admin/config.php';
    require_once __DIR__ . '/../admin/models/Database.php';
    $htmlBase = __DIR__ . '/../';
}

$isCli = PHP_SAPI === 'cli';
if (!$isCli) { header('Content-Type: text/plain; charset=utf-8'); }

const PAGES = ['acoperis','izolatie','gips-carton','zidarie-bca','gard-imprejmuiri',
               'electrice','incalzire','sanitare','apa-canal','gradina','mobilier',
               'electrocasnice','scule-unelte'];

/**
 * Produse care apar in mai multe categorii de nivel 1. Cheia e numele exact din HTML.
 * Valoarea: slug-urile L1 SUPLIMENTARE (cea proprie se deduce din category_id).
 */
const CROSS_LISTED = [
    // Numele sunt cele din PAGINILE DE CATEGORIE (sursa migrarii), nu cele din
    // catalog.html, care difera. Valoarea = categoriile L1 SUPLIMENTARE.
    'Teava PPR PN20 20mm bara 4m'         => ['apa-canal'],  // sta in incalzire.html
    'Chiuveta Bucatarie Inox 1 Cuva 60cm' => ['mobilier'],   // sta in sanitare.html
];

function normalizeSvg(string $svg): string { return trim(preg_replace('/\s+/', ' ', $svg)); }
function iconKeyForSvg(string $svg): string { return 'icon-' . substr(sha1(normalizeSvg($svg)), 0, 8); }
function stripTags2(string $s): string {
    $s = html_entity_decode($s, ENT_QUOTES, 'UTF-8');
    return trim(preg_replace('/\s+/', ' ', strip_tags($s)));
}

/** Fotografia reala a unui card, daca exista. */
function parseImage(string $card): array {
    if (preg_match('#<img[^>]*class="material-img"[^>]*>#s', $card, $m)) {
        $tag = $m[0];
        $src = preg_match('#\ssrc="([^"]*)"#', $tag, $s) ? $s[1] : null;
        $alt = preg_match('#\salt="([^"]*)"#', $tag, $a) ? stripTags2($a[1]) : null;
        if ($src !== null && $src !== '') { return [$src, $alt]; }
    }
    return [null, null];
}

/** Fotografia reala a unui accessory-card, daca exista (varianta accessory-img). */
function parseAccessoryImage(string $card): array {
    if (preg_match('#<img[^>]*class="accessory-img"[^>]*>#s', $card, $m)) {
        $tag = $m[0];
        $src = preg_match('#\ssrc="([^"]*)"#', $tag, $s) ? $s[1] : null;
        $alt = preg_match('#\salt="([^"]*)"#', $tag, $a) ? stripTags2($a[1]) : null;
        if ($src !== null && $src !== '') { return [$src, $alt]; }
    }
    return [null, null];
}

/** Sablonul generat pentru un produs, identic cu cel din waLink() (product_grid.php). */
function templateWaText(string $productName): string {
    return 'Buna ziua! Sunt interesat de: ' . $productName . '. Puteti confirma disponibilitatea?';
}

/**
 * Mesajul din href-ul unui buton WhatsApp (material sau accesoriu), decodat.
 * Returneaza null daca butonul lipseste, sau daca mesajul e identic cu sablonul
 * generat pentru acel produs (nu il stocam redundant).
 */
function parseWaText(string $card, string $btnClass, string $productName): ?string {
    if (!preg_match('#<a href="https://wa\.me/[^"]*?\?text=([^"]*)" class="' . $btnClass . '"#', $card, $m)) {
        return null;
    }
    $text = urldecode($m[1]);
    if ($text === '' || $text === templateWaText($productName)) { return null; }
    return $text;
}

/** Badge-ul unui card: textul si tipul (fara prefixul material-badge--). */
function parseBadge(string $card): array {
    if (!preg_match('#<div class="material-badge([^"]*)"[^>]*>(.*?)</div>#s', $card, $m)) {
        return [null, null];
    }
    $label = stripTags2($m[2]);
    if ($label === '') { return [null, null]; }
    $kind = null;
    if (preg_match('#material-badge--([a-z0-9-]+)#', $m[1], $k)) { $kind = $k[1]; }
    return [$label, $kind];
}

/**
 * Extrage blocurile de proprietati ale unui card.
 * Fiecare bloc are o eticheta si fie text (<span class="prop-val">),
 * fie o lista (<ul class="prop-list">).
 */
function parseProperties(string $card): array {
    $out = [];
    if (!preg_match_all('#<div class="material-prop">(.*?)</div>#s', $card, $blocks)) {
        return $out;
    }
    foreach ($blocks[1] as $i => $block) {
        if (!preg_match('#<span class="prop-label">(.*?)</span>#s', $block, $l)) { continue; }
        $label = stripTags2($l[1]);
        // Restul blocului dupa eticheta: cateva carduri au valoarea intr-un
        // <span> simplu, fara class="prop-val" (typo in HTML-ul sursa).
        $rest = preg_replace('#<span class="prop-label">.*?</span>#s', '', $block, 1);
        if (preg_match('#<ul class="prop-list">(.*?)</ul>#s', $rest, $ul)) {
            preg_match_all('#<li>(.*?)</li>#s', $ul[1], $items);
            $body = implode("\n", array_map('stripTags2', $items[1]));
            $isList = 1;
        } elseif (preg_match('#<span(?:\s+class="prop-val")?>(.*?)</span>#s', $rest, $v)) {
            $body = stripTags2($v[1]);
            $isList = 0;
        } else {
            continue;
        }
        if ($body === '') { continue; }
        $out[] = ['label' => $label, 'body' => $body, 'is_list' => $isList, 'sort' => $i + 1];
    }
    return $out;
}

/** Slug din titlul unei sectiuni: "Cabluri &amp; Conductori" -> "cabluri-conductori". */
function sectionKeyFromTitle(string $title): string {
    $t = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
    $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t) ?: $t;
    $t = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $t));
    return trim(substr($t, 0, 60), '-');
}

/**
 * Cheia de sectiune pentru a N-a grila de un anumit tip dintr-o sectiune editoriala.
 * Prima grila (N=1) pastreaza cheia derivata din <h2>; urmatoarele primesc sufix -2,
 * -3, ... Numerotarea e separata pe tip de grila (materialele intre ele, accesoriile
 * intre ele), tinuta de apelant in doi contori distincti.
 */
function sectionKeyForGrid(string $baseKey, int $gridIndex): string {
    return $gridIndex <= 1 ? $baseKey : $baseKey . '-' . $gridIndex;
}

/** Importa cardurile de accesoriu dintr-un fragment HTML (o singura grila). */
function importAccessoryCards(PDO $db, PDOStatement $insert, PDOStatement $clearProps, string $chunk,
    array $cat, string $page, string $sectionKey, int $adminId, array &$stats, array &$touchedIds): int {
    preg_match_all(
        '#<div class="(accessory-card(?: accessory-card--[a-z0-9]+)?)">(.*?)(?=<div class="accessory-card|\z)#s',
        $chunk, $accCards
    );
    $accOrder = 0;
    $added = 0;

    foreach ($accCards[2] as $i => $card) {
        if (!preg_match('#<h4>(.*?)</h4>#s', $card, $n)) { continue; }
        $name = stripTags2($n[1]);
        if ($name === '') { continue; }

        $descr = preg_match('#<p>(.*?)</p>#s', $card, $p) ? stripTags2($p[1]) : '';

        $modifier = null;
        if (preg_match('#accessory-card--([a-z0-9]+)#', $accCards[1][$i], $mm)) { $modifier = $mm[1]; }
        if ($modifier === 'sm') { $stats['accSm']++; }

        [$imgPath, $imgAlt] = parseAccessoryImage($card);
        if ($imgPath !== null) { $stats['accWithImg']++; }

        preg_match('#<div class="accessory-img-ph".*?(<svg.*?</svg>)#s', $card, $s);
        $accIconKey = isset($s[1]) ? iconKeyForSvg($s[1]) : null;
        if ($accIconKey !== null) { $stats['accWithIcon']++; }

        // Eticheta placeholder-ului, doar cand chiar exista un <span> in el — unele
        // accesorii au (Cot 45°, Brida burlan, ...), altele nu. Nu o inventam din nume.
        $accIconLabel = null;
        if (preg_match('#<div class="accessory-img-ph"[^>]*>(.*?)</div>#s', $card, $ph)
            && preg_match('#<span>(.*?)</span>#s', $ph[1], $l)) {
            $accIconLabel = stripTags2($l[1]);
            if ($accIconLabel === '') { $accIconLabel = null; }
        }
        if ($accIconLabel !== null) { $stats['accWithLabel']++; }

        $waText = parseWaText($card, 'btn-wa-acc', $name);
        if ($waText !== null) { $stats['accWithWaText']++; }

        $insert->execute([
            ':cat' => $cat['id'], ':page' => $page,
            ':kind' => 'accessory', ':modifier' => $modifier, ':section' => $sectionKey,
            ':name' => $name, ':descr' => $descr, ':watext' => $waText,
            ':ikey' => $accIconKey, ':ilabel' => $accIconLabel,
            ':img' => $imgPath, ':imgalt' => $imgAlt,
            ':blabel' => null, ':bkind' => null,
            ':sort' => ++$accOrder, ':by' => $adminId,
        ]);

        $pid = (int)$db->lastInsertId();
        $clearProps->execute([$pid]); // accesoriile nu au proprietati
        $touchedIds[] = $pid;

        $added++; $stats['accTotal']++;
    }
    return $added;
}

/** Importa cardurile de material dintr-un fragment HTML (o singura grila). */
function importMaterialCards(PDO $db, PDOStatement $insert, PDOStatement $linkCross, PDOStatement $clearProps,
    PDOStatement $insertProp, string $chunk, array $cat, array $catBySlug, string $page, string $sectionKey,
    int $adminId, array &$stats, array &$touchedIds): int {
    preg_match_all('#<div class="material-card">(.*?)(?=<div class="material-card">|\z)#s', $chunk, $cards);
    $order = 0;
    $added = 0;

    foreach ($cards[1] as $card) {
        if (!preg_match('#<h3 class="material-name">(.*?)</h3>#s', $card, $n)) { continue; }
        $name = stripTags2($n[1]);
        if ($name === '') { continue; }

        $props = parseProperties($card);
        $descr = '';
        foreach ($props as $pr) {
            if ($pr['is_list'] === 0) { $descr = $pr['body']; break; }
        }

        preg_match('#<div class="material-img-ph".*?(<svg.*?</svg>)#s', $card, $s);
        $iconKey = isset($s[1]) ? iconKeyForSvg($s[1]) : null;
        if ($iconKey === null) { $stats['noIcon']++; }

        preg_match('#<div class="material-img-ph".*?<span>(.*?)</span>#s', $card, $l);
        $iconLabel = isset($l[1]) ? stripTags2($l[1]) : $name;

        [$imgPath, $imgAlt] = parseImage($card);
        [$badgeLabel, $badgeKind] = parseBadge($card);
        if ($imgPath !== null) { $stats['withImg']++; }
        if ($badgeLabel !== null) { $stats['withBadge']++; }

        $waText = parseWaText($card, 'btn-wa-material', $name);
        if ($waText !== null) { $stats['withWaText']++; }

        // category_id = categoria cea mai specifica pe care o cunoastem: pagina insasi.
        $insert->execute([
            ':cat' => $cat['id'], ':page' => $page,
            ':kind' => 'material', ':modifier' => null, ':section' => $sectionKey,
            ':name' => $name, ':descr' => $descr, ':watext' => $waText,
            ':ikey' => $iconKey, ':ilabel' => $iconLabel,
            ':img' => $imgPath, ':imgalt' => $imgAlt,
            ':blabel' => $badgeLabel, ':bkind' => $badgeKind,
            ':sort' => ++$order, ':by' => $adminId,
        ]);

        $pid = (int)$db->lastInsertId();
        $touchedIds[] = $pid;

        if (isset(CROSS_LISTED[$name])) {
            foreach (CROSS_LISTED[$name] as $extra) {
                if (isset($catBySlug[$extra])) {
                    $linkCross->execute([$pid, $catBySlug[$extra]['id']]);
                    $stats['crossed']++;
                }
            }
        }

        $clearProps->execute([$pid]);
        foreach ($props as $pr) {
            $insertProp->execute([$pid, $pr['sort'], $pr['label'], $pr['body'], $pr['is_list']]);
            $stats['totalProps']++;
        }

        $added++; $stats['total']++;
    }
    return $added;
}

$db = Database::get();

$catBySlug = [];
foreach ($db->query('SELECT id, slug, parent_id FROM categories')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $catBySlug[$r['slug']] = $r;
}

$adminId = (int)$db->query('SELECT id FROM users WHERE role="admin" LIMIT 1')->fetchColumn();
if (!$adminId) { die("Eroare: nu exista user admin in DB. Ruleaza intai db/seed_admin.php\n"); }

$insert = $db->prepare(
    'INSERT INTO products
        (category_id, page_slug, card_kind, card_modifier, section_key, name, short_description,
         wa_text, icon_key, icon_label, image_path, image_alt, badge_label, badge_kind,
         sort_order, created_by)
     VALUES (:cat, :page, :kind, :modifier, :section, :name, :descr, :watext, :ikey, :ilabel, :img, :imgalt, :blabel, :bkind, :sort, :by)
     ON DUPLICATE KEY UPDATE
        category_id = VALUES(category_id),
        card_kind = VALUES(card_kind), card_modifier = VALUES(card_modifier),
        short_description = VALUES(short_description),
        wa_text = VALUES(wa_text),
        icon_key = VALUES(icon_key), icon_label = VALUES(icon_label),
        image_path = VALUES(image_path), image_alt = VALUES(image_alt),
        badge_label = VALUES(badge_label), badge_kind = VALUES(badge_kind),
        sort_order = VALUES(sort_order), id = LAST_INSERT_ID(id)'
);
$linkCross = $db->prepare(
    'INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (?, ?)'
);
$clearProps = $db->prepare('DELETE FROM product_properties WHERE product_id = ?');
$insertProp = $db->prepare(
    'INSERT INTO product_properties (product_id, sort_order, label, body, is_list)
     VALUES (?, ?, ?, ?, ?)'
);

/**
 * Sterge produsele ramase orfane pe o pagina dupa import: cazul obisnuit e un produs
 * sters din pagina statica; cazul introdus de aceasta schimbare e un produs care si-a
 * schimbat section_key (grila lui a primit un sufix nou) — vechiul rand, sub cheia
 * veche, nu mai e atins de niciun INSERT ... ON DUPLICATE KEY din rularea curenta.
 * ON DELETE CASCADE sterge si proprietatile si legaturile cross-listate ale randului.
 */
function deleteStaleProducts(PDO $db, string $page, array $touchedIds): int {
    $ids = array_unique(array_map('intval', $touchedIds));
    if (!$ids) { return 0; } // pagina n-a produs niciun rand: nu stergem nimic orbeste
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("DELETE FROM products WHERE page_slug = ? AND id NOT IN ($in)");
    $stmt->execute([$page, ...$ids]);
    return $stmt->rowCount();
}

$stats = [
    'total' => 0, 'crossed' => 0, 'noIcon' => 0, 'totalProps' => 0, 'withImg' => 0, 'withBadge' => 0, 'withWaText' => 0,
    'accTotal' => 0, 'accWithImg' => 0, 'accSm' => 0, 'accWithIcon' => 0, 'accWithLabel' => 0, 'accWithWaText' => 0,
];
$perPage = [];
$accPerPage = [];

foreach (PAGES as $page) {
    $file = $htmlBase . $page . '.html';
    if (!is_file($file)) { echo "SKIP (lipsa): $page.html\n"; continue; }
    if (!isset($catBySlug[$page])) { echo "SKIP (categorie lipsa in DB): $page\n"; continue; }

    $html  = file_get_contents($file);
    $count = 0;
    $accCount = 0;
    $cat = $catBySlug[$page];
    $touchedIds = [];

    // Sparge pagina in sectiuni; retine titlul <h2> al fiecareia.
    $parts = preg_split('/(<section[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    $sectionTitle = null;

    foreach ($parts as $part) {
        if (str_starts_with($part, '<section')) { $sectionTitle = null; continue; }
        $hasMaterial  = str_contains($part, 'class="material-card"');
        $hasAccessory = str_contains($part, 'class="accessory-card"') || str_contains($part, 'class="accessory-card ');
        if (!$hasMaterial && !$hasAccessory) { continue; }

        if (preg_match('#<h2[^>]*>(.*?)</h2>#s', $part, $h2)) {
            $sectionTitle = stripTags2($h2[1]);
        }
        $baseKey = $sectionTitle ? sectionKeyFromTitle($sectionTitle) : 'produse';

        // O sectiune editoriala poate contine mai multe grile HTML de acelasi tip
        // (materiale sau accesorii), provenite din "valuri" succesive de continut
        // adaugate in timp — de ex. "Profile Metalice" din gips-carton are doua grile
        // de accesorii (profile pereti, profile tavane). Fiecare grila primeste propria
        // cheie de sectiune: prima pastreaza cheia derivata din <h2>, urmatoarele
        // primesc sufix -2, -3, ... Numerotarea e separata pe tip de grila.
        $gridChunks = preg_split(
            '/(<div class="(?:materials-grid|accessories-grid)[^"]*">)/',
            $part, -1, PREG_SPLIT_DELIM_CAPTURE
        );

        $accGridN = 0;
        $matGridN = 0;
        $curKind  = null;

        // Fallback: daca sectiunea nu foloseste containerele standard de grila (nu s-a
        // intalnit inca, dar nu presupunem), trateaz-o ca o singura grila mixta —
        // comportamentul dinaintea acestei schimbari.
        if (count($gridChunks) <= 1) {
            $gridChunks = [$part];
            $curKind = 'both';
        }

        foreach ($gridChunks as $chunk) {
            if ($curKind !== 'both' && preg_match('#^<div class="(materials-grid|accessories-grid)#', $chunk, $gm)) {
                $curKind = $gm[1] === 'materials-grid' ? 'material' : 'accessory';
                continue;
            }
            if ($curKind === null) { continue; } // continut inainte de prima grila din sectiune

            $chunkHasAccessory = ($curKind === 'accessory' || $curKind === 'both')
                && (str_contains($chunk, 'class="accessory-card"') || str_contains($chunk, 'class="accessory-card '));
            $chunkHasMaterial = ($curKind === 'material' || $curKind === 'both')
                && str_contains($chunk, 'class="material-card"');

            if ($chunkHasAccessory) {
                $accGridN++;
                $sectionKey = sectionKeyForGrid($baseKey, $accGridN);
                $accCount += importAccessoryCards($db, $insert, $clearProps, $chunk, $cat, $page, $sectionKey, $adminId, $stats, $touchedIds);
            }

            if ($chunkHasMaterial) {
                $matGridN++;
                $sectionKey = sectionKeyForGrid($baseKey, $matGridN);
                $count += importMaterialCards($db, $insert, $linkCross, $clearProps, $insertProp, $chunk, $cat, $catBySlug, $page, $sectionKey, $adminId, $stats, $touchedIds);
            }
        }
    }
    $removed = deleteStaleProducts($db, $page, $touchedIds);
    $perPage[$page] = $count;
    $accPerPage[$page] = $accCount;
    echo str_pad($page, 20) . " $count materiale, $accCount accesorii"
       . ($removed ? " ($removed randuri orfane sterse)" : '') . "\n";
}

['total' => $total, 'crossed' => $crossed, 'noIcon' => $noIcon, 'totalProps' => $totalProps,
 'withImg' => $withImg, 'withBadge' => $withBadge, 'withWaText' => $withWaText,
 'accTotal' => $accTotal, 'accWithImg' => $accWithImg, 'accSm' => $accSm, 'accWithIcon' => $accWithIcon,
 'accWithLabel' => $accWithLabel, 'accWithWaText' => $accWithWaText] = $stats;

echo "\nTOTAL: $total produse, $crossed legaturi cross-listate, $noIcon carduri fara iconita, $totalProps proprietati, $withImg cu fotografie, $withBadge cu badge, $withWaText cu mesaj WhatsApp propriu\n";
echo "TOTAL: $total materiale + $accTotal accesorii = " . ($total + $accTotal) . " produse "
   . "($accSm accesorii --sm, $accWithImg accesorii cu fotografie, $accWithIcon accesorii cu iconita, "
   . "$accWithLabel cu eticheta pe iconita, $accWithWaText cu mesaj WhatsApp propriu)\n";
