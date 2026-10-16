<?php
/**
 * Text pentru catalogul nou: adrese (slug) si HTML-ul descrierilor.
 *
 * cleanDescriptionHtml() lucreaza pe LISTA ALBA (nu ca Sanitize::html(), care cauta ce e periculos si se poate
 * ocoli — jurnal, punctul 4): pastreaza doar etichetele de mai jos, fara niciun atribut. Restul etichetelor
 * dispar, dar textul din ele ramane; <script>, <style> si altele asemenea dispar cu tot cu continut.
 */

/** Grupele de tag-uri (tags.grp), in ordinea in care apar filtrele pe pagina; numele afisat. */
const TAG_GROUP_LABELS = [
    'tip' => 'Tip produs', 'brand' => 'Brand', 'dimensiune' => 'Dimensiune', 'grosime' => 'Grosime',
    'diametru' => 'Diametru', 'lungime' => 'Lungime', 'module' => 'Lungime (module)', 'cantitate' => 'Cantitate / capacitate', 'putere' => 'Putere', 'energie' => 'Clasă energetică',
    'alimentare' => 'Alimentare', 'culoare' => 'Culoare',
    'finisaj' => 'Finisaj',
    'material' => 'Material', 'utilizare' => 'Utilizare', 'clasa' => 'Clasă / standard', 'compozitie' => 'Compoziție',
    'caracteristica' => 'Caracteristici', 'granulatie' => 'Granulație', 'aplicatie' => 'Aplicații',
];

const DESCRIPTION_TAGS = ['p', 'strong', 'em', 'ul', 'ol', 'li', 'br', 'h3', 'h4'];
const DESCRIPTION_RENAME = ['b' => 'strong', 'i' => 'em', 'h1' => 'h3', 'h2' => 'h3', 'h5' => 'h4', 'h6' => 'h4'];
const DESCRIPTION_DROP = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'svg', 'math', 'noscript', 'form'];

/** „Țeavă pătrată 40 x 40 mm, Ø 26,9" => „teava-patrata-40-x-40-mm-26-9". */
function slugify(string $text, int $maxLen = 120): string
{
    $map = ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
            'Ă' => 'a', 'Â' => 'a', 'Î' => 'i', 'Ș' => 's', 'Ş' => 's', 'Ț' => 't', 'Ţ' => 't',
            'Ø' => ' ', 'ø' => ' ', '×' => ' x ']; // Ø = diametru, nu litera „o"
    $s = strtr($text, $map);
    $ascii = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) : false;
    $s = strtolower($ascii !== false ? $ascii : $s);
    $s = trim((string) preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    if (strlen($s) > $maxLen) {
        $s = substr($s, 0, $maxLen);
        $cut = strrpos($s, '-');
        if ($cut !== false && $cut > $maxLen / 2) { $s = substr($s, 0, $cut); }
    }
    return $s;
}

function cleanDescriptionHtml(string $html): string
{
    if (trim($html) === '') { return ''; }
    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = $doc->getElementById('__root');
    if ($root === null) { return ''; }

    $out = '';
    foreach ($root->childNodes as $child) { $out .= descriptionNode($child); }
    // spatii goale si paragrafe goale lasate de etichetele eliminate
    $out = preg_replace('#<p>\s*</p>#u', '', $out);
    return trim((string) $out);
}

function descriptionNode(DOMNode $node): string
{
    if ($node instanceof DOMText) {
        return htmlspecialchars($node->wholeText, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
    if (!$node instanceof DOMElement) { return ''; } // comentarii, instructiuni de procesare
    $tag = strtolower($node->tagName);
    if (in_array($tag, DESCRIPTION_DROP, true)) { return ''; }
    $tag = DESCRIPTION_RENAME[$tag] ?? $tag;

    $inner = '';
    foreach ($node->childNodes as $child) { $inner .= descriptionNode($child); }
    if (!in_array($tag, DESCRIPTION_TAGS, true)) { return $inner; }
    if ($tag === 'br') { return '<br>'; }
    return "<$tag>$inner</$tag>";
}

/**
 * Rezumatul pentru card: primul paragraf care nu e doar un titlu in <strong>, ca text simplu,
 * taiat la cel mult $max caractere, la capat de cuvant.
 */
function shortFromDescription(string $html, int $max = 220): string
{
    if (!preg_match_all('#<p>(.*?)</p>#su', $html, $m)) { return ''; }
    foreach ($m[1] as $p) {
        if (preg_match('#^\s*<strong>.*</strong>\s*$#su', $p)) { continue; }
        $text = trim(html_entity_decode(strip_tags($p), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = (string) preg_replace('/\s+/u', ' ', $text);
        if ($text === '') { continue; }
        if (mb_strlen($text) <= $max) { return $text; }
        $cut = mb_substr($text, 0, $max);
        $sp = mb_strrpos($cut, ' ');
        return rtrim(mb_substr($cut, 0, $sp !== false ? $sp : $max), ' ,;:.–-') . '…';
    }
    return '';
}

/**
 * Cod de bare GTIN real (EAN-8/12/13/14) cu cifra de control corecta. Codurile care incep cu 2 sunt rezervate
 * uzului intern al magazinelor (GS1) — nu le trimitem la Google ca GTIN.
 */
function isValidGtin(string $code): bool
{
    if (!preg_match('/^(\d{8}|\d{12,14})$/', $code) || $code[0] === '2' && strlen($code) === 13) { return false; }
    $digits = array_map('intval', str_split($code));
    $check = array_pop($digits);
    $sum = 0;
    foreach (array_reverse($digits) as $i => $d) { $sum += $d * ($i % 2 === 0 ? 3 : 1); }
    return (10 - $sum % 10) % 10 === $check;
}
