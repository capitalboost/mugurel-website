<?php
/**
 * Cautarea din catalog (faza 4.3): sinonime, forme de plural si corectarea unei greseli de scriere.
 *  - „frigider" gaseste „Combina frigorifica", „mixer" gaseste blenderele, „tevi" gaseste „Teava";
 *  - „aragazuri", „frigidere", „aspiratoare" cauta forma de baza;
 *  - „aragazz", „cerezit": daca nu iese nimic, se cauta cel mai apropiat cuvant din numele produselor.
 * Diacriticele nu conteaza: coloanele sunt utf8mb4_unicode_ci („teava" gaseste „Țeavă").
 */

/** Cuvant cautat (fara diacritice, litere mici) => ce mai cautam in numele produselor. */
const SEARCH_SYNONYMS = [
    'frigider'   => ['frigorific'],
    'congelator' => ['frigorific'],
    'mixer'      => ['blender'],
    'tocator'    => ['blender'],
    'boxe'       => ['boxa'],
    'difuzor'    => ['boxa'],
    'tevi'       => ['teava'],
    'teava'      => ['teav'],
    'soba'       => ['soba', 'evacuare fum'],
    'sobe'       => ['soba', 'evacuare fum'],
    'cuptor'     => ['aragaz'],
    'plita'      => ['aragaz'],
    'ceainic'    => ['fierbator', 'cana electrica'],
    'fierbator'  => ['cana electrica'],
    'tencuiala'  => ['tinci', 'tencuiala'],
    'tinci'      => ['tencuiala fina'],
    'termoizolatie' => ['polistiren', 'termoizol'],
    'faianta'    => ['faianta'],
    'acoperis'   => ['tabla', 'tigla', 'coama'],
    'jgheaburi'  => ['jgheab'],
    'burlane'    => ['burlan'],
];

/** Litere mici, fara diacritice. */
function searchFold(string $s): string
{
    $s = mb_strtolower($s, 'UTF-8');
    return strtr($s, ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't']);
}

/** Forma de baza pentru pluralele uzuale: aragazuri→aragaz, frigidere→frigider, aspiratoare→aspirator, adezivi→adeziv. */
function searchStem(string $w): string
{
    foreach (['urile' => '', 'uri' => '', 'oare' => 'or', 'ele' => '', 'ere' => 'er', 'e' => '', 'i' => '', 'a' => ''] as $end => $repl) {
        if (str_ends_with($w, $end) && mb_strlen($w) - mb_strlen($end) + mb_strlen($repl) >= 4) {
            return mb_substr($w, 0, mb_strlen($w) - mb_strlen($end)) . $repl;
        }
    }
    return $w;
}

/** Ce cautam pentru un cuvant: el insusi, forma de baza si sinonimele (fara dubluri). @return list<string> */
function searchAlternatives(string $word): array
{
    $w = searchFold($word);
    $alts = [$w, searchStem($w)];
    foreach ([$w, searchStem($w)] as $k) {
        foreach (SEARCH_SYNONYMS[$k] ?? [] as $syn) { $alts[] = $syn; }
    }
    return array_values(array_unique(array_filter($alts, fn ($a) => mb_strlen($a) >= 2)));
}

/** Cuvintele din numele si marcile produselor vizibile (fara diacritice), pentru corectarea greselilor. @return list<string> */
function searchVocabulary(PDO $pdo): array
{
    static $vocab = null;
    if ($vocab !== null) { return $vocab; }
    $words = [];
    foreach ($pdo->query('SELECT name, manufacturer FROM products WHERE is_visible = 1 AND barcode IS NOT NULL') as $r) {
        foreach (preg_split('/[^\p{L}\p{N}]+/u', searchFold($r['name'] . ' ' . (string) $r['manufacturer'])) ?: [] as $t) {
            if (mb_strlen($t) >= 3 && !ctype_digit($t)) { $words[$t] = true; }
        }
    }
    return $vocab = array_keys($words);
}

/**
 * Cautarea corectata, daca un cuvant pare scris gresit (nu apare in niciun nume, dar unul foarte apropiat da).
 * @return string|null cautarea cu cuvintele corectate, sau null daca nu e nimic de corectat
 */
function searchCorrect(PDO $pdo, string $q): ?string
{
    $vocab = searchVocabulary($pdo);
    $out = []; $changed = false;
    foreach (preg_split('/\s+/u', trim($q)) ?: [] as $word) {
        $w = searchFold($word);
        $known = false;
        foreach (searchAlternatives($w) as $alt) {
            foreach ($vocab as $v) { if (str_contains($v, $alt)) { $known = true; break 2; } }
        }
        if ($known || mb_strlen($w) < 4 || ctype_digit($w)) { $out[] = $word; continue; }
        $max = mb_strlen($w) >= 7 ? 2 : 1;
        $best = null; $bestD = PHP_INT_MAX;
        foreach ($vocab as $v) {
            if (abs(strlen($v) - strlen($w)) > $max) { continue; }
            $d = levenshtein($w, $v);
            if ($d < $bestD || ($d === $bestD && $best !== null && strcmp($v, $best) < 0)) { $best = $v; $bestD = $d; }
        }
        if ($best !== null && $bestD <= $max) { $out[] = $best; $changed = true; } else { $out[] = $word; }
    }
    return $changed ? implode(' ', $out) : null;
}
