<?php
/**
 * Genereaza Website/includes/product_icons.php din SVG-urile aflate azi in
 * paginile de categorie, plus cele proprii ale produselor din catalog.html
 * (Task 8C) — altfel cele 39 de produse importate doar in catalog ar randa cu
 * iconita generica de fallback, desi au un desen propriu in sursa. Ruleaza
 * local, o singura data (si din nou daca se adauga iconite noi in HTML).
 * NU se deployeaza pe server.
 *
 * Paginile de categorie au fost convertite la .php si citesc produsele din baza
 * de date — .html-urile originale nu mai exista pe disc. Cand un fisier lipseste,
 * scriptul cade pe git, la ultimul commit dinaintea conversiei (acelasi tipar ca
 * db/migrate_catalog_products.php pentru catalog.html).
 *
 * Usage: php db/extract_icons.php
 */

const PAGES = ['acoperis','izolatie','gips-carton','zidarie-bca','gard-imprejmuiri',
               'electrice','incalzire','sanitare','apa-canal','gradina','mobilier',
               'electrocasnice','scule-unelte'];

/** Ultimul commit din Website in care paginile de categorie erau inca .html. */
const PAGES_COMMIT = '41edc4b';

/** Acelasi commit folosit de db/migrate_catalog_products.php pentru catalog.html. */
const CATALOG_COMMIT = '41edc4b';

/** Normalizeaza spatiile ca sa nu produca chei diferite pentru acelasi desen. */
function normalizeSvg(string $svg): string {
    return trim(preg_replace('/\s+/', ' ', $svg));
}

/** Cheia determinista a unei iconite. Folosita si de db/migrate_products.php. */
function iconKeyForSvg(string $svg): string {
    return 'icon-' . substr(sha1(normalizeSvg($svg)), 0, 8);
}

/** Continutul unui fisier: de pe disc daca exista, altfel dintr-un commit git. */
function readFileOrGit(string $root, string $relPath, string $commit): ?string {
    $file = $root . $relPath;
    if (is_file($file)) { return file_get_contents($file); }

    $cmd = 'git -C ' . escapeshellarg(rtrim($root, '/')) . ' show '
         . escapeshellarg($commit . ':' . $relPath) . ' 2>&1';
    $html = shell_exec($cmd);
    if ($html === null || trim($html) === '') {
        fwrite(STDERR, "SKIP (lipsa si pe disc si in git $commit): $relPath\n");
        return null;
    }
    return str_replace("\r\n", "\n", $html);
}

/** Continutul unei pagini de categorie: de pe disc daca exista, altfel din git. */
function readPage(string $root, string $page): ?string {
    return readFileOrGit($root, $page . '.html', PAGES_COMMIT);
}

if (PHP_SAPI !== 'cli') { die("Doar din linia de comanda.\n"); }

$root       = __DIR__ . '/../';
$outputFile = $root . 'includes/product_icons.php';
$icons      = [];

foreach (PAGES as $page) {
    $html = readPage($root, $page);
    if ($html === null) { continue; }
    if (preg_match_all('#<div class="material-img-ph".*?(<svg.*?</svg>)#s', $html, $m)) {
        foreach ($m[1] as $svg) {
            $icons[iconKeyForSvg($svg)] = normalizeSvg($svg);
        }
    }
    if (preg_match_all('#<div class="accessory-img-ph".*?(<svg.*?</svg>)#s', $html, $m)) {
        foreach ($m[1] as $svg) {
            $icons[iconKeyForSvg($svg)] = normalizeSvg($svg);
        }
    }
}

$catalogHtml = readFileOrGit($root, 'catalog.html', CATALOG_COMMIT);
if ($catalogHtml !== null && preg_match_all('#<div class="prod-img-ph">(<svg.*?</svg>)#s', $catalogHtml, $m)) {
    foreach ($m[1] as $svg) {
        $icons[iconKeyForSvg($svg)] = normalizeSvg($svg);
    }
}

ksort($icons);

// Protectie: daca extragerea de acum gaseste mai putine iconite decat biblioteca
// existenta, ceva e rupt in sursa (pagini lipsa, regex care nu se mai leaga de
// marcaj etc.) — nu scriem peste o biblioteca buna cu una goala sau incompleta,
// ceea ce ar sparge randarea tuturor cardurilor de pe site.
$existingCount = 0;
if (is_file($outputFile)) {
    $existingSrc = file_get_contents($outputFile);
    $existingCount = preg_match_all("/^\s*'icon-[0-9a-f]{8}'\s*=>/m", $existingSrc);
}
if (count($icons) < $existingCount) {
    fwrite(STDERR, "EROARE: extragerea a gasit doar " . count($icons) . " iconite, biblioteca "
        . "existenta are $existingCount. Refuz sa scriu — ar sparge site-ul. "
        . "Verifica sursele (pagini lipsa pe disc si in git) inainte sa rulezi din nou.\n");
    exit(1);
}

$out  = "<?php\n";
$out .= "/**\n";
$out .= " * GENERAT AUTOMAT de db/extract_icons.php — nu edita manual.\n";
$out .= " * Iconitele placeholder ale cardurilor de produs, extrase din paginile statice.\n";
$out .= " */\n\n";
$out .= "const PRODUCT_ICONS = [\n";
foreach ($icons as $key => $svg) {
    $out .= "    '" . $key . "' => " . var_export($svg, true) . ",\n";
}
$out .= "];\n\n";
$out .= <<<'PHP'
/** Iconita generica pentru produsele fara icon_key (produse noi din admin). */
const PRODUCT_ICON_FALLBACK =
    '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5">'
    . '<rect x="15" y="12" width="50" height="36" rx="3"/><line x1="15" y1="24" x2="65" y2="24"/></svg>';

/**
 * Randeaza placeholder-ul unui card. Cheile necunoscute cad pe iconita generica,
 * ca un produs adaugat din admin sa nu randeze o gaura in grila.
 * $hidden = true cand cardul are si o fotografie reala (placeholder-ul ramane
 * in DOM ca fallback, dar ascuns).
 *
 * $label nu se mai randeaza (era un <span> cu majuscule care repeta titlul de sub
 * card — vezi raportul design/perf). Parametrul ramane in semnatura ca apelurile
 * existente sa nu se rupa; icon_label ramane in DB neatins, doar nu se mai afiseaza.
 */
function renderIcon(?string $key, ?string $label, bool $hidden = false): string {
    $svg = ($key !== null && isset(PRODUCT_ICONS[$key])) ? PRODUCT_ICONS[$key] : PRODUCT_ICON_FALLBACK;
    $style = $hidden ? 'display:none' : 'display:flex';
    return '<div class="material-img-ph" style="' . $style . '">' . $svg . '</div>';
}

/**
 * Placeholder-ul unui accessory-card. Eticheta nu se mai randeaza (acelasi motiv
 * ca la renderIcon) — parametrul $label ramane in semnatura din compatibilitate.
 */
function renderAccessoryIcon(?string $key, ?string $label = null, bool $hidden = false): string {
    $svg = ($key !== null && isset(PRODUCT_ICONS[$key])) ? PRODUCT_ICONS[$key] : PRODUCT_ICON_FALLBACK;
    return '<div class="accessory-img-ph" style="' . ($hidden ? 'display:none' : 'display:flex') . '">' . $svg . '</div>';
}

/**
 * Placeholder-ul cardului de catalog. Aceeasi biblioteca de iconite, alt container.
 * $hidden = true cand cardul are si o fotografie reala (placeholder-ul ramane
 * in DOM ca fallback, dar ascuns). Eticheta nu se mai randeaza (acelasi motiv
 * ca la renderIcon).
 */
function renderProdIcon(?string $key, ?string $label, bool $hidden = false): string {
    $svg = ($key !== null && isset(PRODUCT_ICONS[$key])) ? PRODUCT_ICONS[$key] : PRODUCT_ICON_FALLBACK;
    $style = $hidden ? 'display:none' : 'display:flex';
    return '<div class="prod-img-ph" style="' . $style . '">' . $svg . '</div>';
}
PHP;
$out .= "\n";

file_put_contents($outputFile, $out);
echo "Scris Website/includes/product_icons.php — " . count($icons) . " iconite.\n";
