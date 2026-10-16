<?php
/**
 * Regulile importului de catalog (includes/catalog_import.php): in ce grupa intra fiecare tag din Excel.
 *
 * Grupele (coloana tags.grp):
 *   filtre pe pagina de categorie — brand, tip, material, culoare, dimensiune, grosime, diametru, lungime,
 *                                    cantitate, granulatie, clasa, utilizare, compozitie, caracteristica
 *   aplicatie — tag-urile de UTILIZARE, care devin pagini-colectie (ex. „Fier forjat", „Fatada")
 *   ignorat   — nu se salveaza (ex. „constructii", pus pe toate produsele)
 *
 * Ordinea regulilor conteaza: prima care se potriveste castiga. Un tag necunoscut ajunge in „aplicatie" si
 * apare in raportul importului, ca sa fie verificat. Clasificarea se face o singura data, la crearea tag-ului.
 */

const TAG_IGNORED = ['construcții', 'constructii'];

/** Specificatii care NU apar pe site (raman in notele interne ale produsului). */
const SPEC_INTERNAL_KEYS = ['Sursă date tehnice'];

const TAG_LISTS = [
    'culoare'        => ['alb', 'negru', 'gri', 'maro', 'roșu', 'verde', 'galben', 'albastru', 'antracit', 'transparent'],
    'utilizare'      => ['interior', 'exterior', 'interior/exterior'],
    'material'       => ['oțel', 'plastic', 'zincat', 'amprentat', 'aluminiu', 'cupru', 'pvc', 'lemn', 'inox', 'fontă'],
    'compozitie'     => ['var-ciment', 'pe bază de ipsos', 'pe bază de ciment', 'ciment alb', 'pe bază de var'],
    'caracteristica' => ['flexibil', 'aplicare mecanizată', 'trafic greu', 'rapid', 'hidrofob', 'perforat', 'cu capac',
                         'colțar exterior', 'colțar interior', 'cocoș de vânt'],
    'finisaj'        => ['mat', 'lucios', 'emailat', 'satinat', 'texturat', 'lăcuit', 'vopsit'],
];

/** [grupa, expresie regulata] — aplicate pe tag-ul deja reparat (virgula zecimala refacuta). */
const TAG_PATTERNS = [
    ['grosime',    '/^grosime\b/iu'],
    ['diametru',   '/^Ø/u'],
    ['culoare',    '/^RAL\s*\d{4}$/iu'],
    ['culoare',    '/^RR\s*\d{2,4}$/u'],                                   // coduri de culoare Ruukki (RR29, RR887)
    ['culoare',    '/^(?:alb|negru|gri|maro|roșu|verde|galben|albastru|bej|crem|antracit)\s+\p{L}+$/iu'], // „gri antracit"
    ['module',     '/^\d+\s*module?$/iu'],                                  // tabla tip tigla: lungimea in module
    ['dimensiune', '/^\d+(?:[.,]\d+)?\s*[x×]\s*\d+/u'],
    ['dimensiune', '/^\d+(?:[.,]\d+)?\s*°$/u'],                            // unghi (ex. cot 60°)
    ['cantitate',  '/^\d+(?:[.,]\d+)?\s*(?:g\/m²|g\/m2|m²|m2)$/u'],
    ['caracteristica', '/^\d+\s*straturi$/u'],
    ['granulatie', '/^\d+(?:[.,]\d+)?\s*[–-]\s*\d+(?:[.,]\d+)?\s*mm$/u'],
    ['lungime',    '/^\d+(?:[.,]\d+)?\s*m$/u'],
    ['cantitate',  '/^\d+(?:[.,]\d+)?\s*(?:kg|g|l|ml)$/u'],
    ['dimensiune', '/^\d+(?:[.,]\d+)?\s*(?:mm|cm)$/u'],
    ['clasa',      '/^(?:C\d|CS\s|CEM\s|CT-|M\d|C2|S\d|IPE\s|\d+(?:,\d+)?\s*[NR]$)/u'],  // 42,5R = clasa de rezistenta a cimentului
];

/**
 * Grupa unui tag. $brands = producatorii cunoscuti (din coloana Producator), $types = tipurile de produs
 * (din coloana Tip produs), ambele cu litere mici.
 */
function classifyTag(string $tag, array $brands, array $types): string
{
    $low = mb_strtolower(trim($tag));
    if (in_array($low, TAG_IGNORED, true)) { return 'ignorat'; }
    if (in_array($low, $brands, true)) { return 'brand'; }
    foreach ($types as $type) {
        // „platbandă" ~ „platbandă amprentată", „glet de finisaj" ~ „glet": unul incepe cu celalalt, la capat de cuvant
        if ($low === $type || str_starts_with($type, $low . ' ') || str_starts_with($low, $type . ' ')) { return 'tip'; }
    }
    foreach (TAG_LISTS as $grp => $list) {
        if (in_array($low, $list, true)) { return $grp; }
    }
    foreach (TAG_PATTERNS as [$grp, $re]) {
        if (preg_match($re, trim($tag))) { return $grp; }
    }
    return 'aplicatie';
}
