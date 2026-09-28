<?php
/**
 * Randarea cardurilor de produs. Doar HTML — fara SQL.
 *
 * Site-ul are doua carduri vizual distincte, fara clase comune:
 *   .material-card  in paginile de categorie   -> renderMaterialCards()
 *   .prod-card      in catalog                 -> renderProdCards()
 * Sunt tinute separate intentionat; unificarea lor ar fi o schimbare de design.
 */
require_once __DIR__ . '/product_icons.php';

const WA_PHONE = '40749130565';

/** SVG-ul de WhatsApp, identic cu cel din paginile statice. */
const WA_ICON = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.231 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>';

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Pretul afisat. Conventia site-ului: aproape totul e "la cerere". */
function priceLabel(array $p): string {
    if ($p['price'] === null || (float)$p['price'] <= 0) { return 'Pret la cerere'; }
    return number_format((float)$p['price'], 2, ',', '.') . ' lei'
         . ($p['price_unit'] ? ' / ' . e($p['price_unit']) : '');
}

/**
 * Link-ul WhatsApp precompletat pentru un produs.
 * $custom e mesajul scris de mana in sursa (coloana wa_text), cand exista si difera
 * de sablon — altfel (null sau gol) se foloseste sablonul generic.
 */
function waLink(string $productName, ?string $custom = null): string {
    $msg = ($custom !== null && $custom !== '')
         ? $custom
         : 'Buna ziua! Sunt interesat de: ' . $productName . '. Puteti confirma disponibilitatea?';
    return 'https://wa.me/' . WA_PHONE . '?text=' . rawurlencode($msg);
}

/**
 * Blocurile de proprietati ale unui card, in ordinea din DB.
 * Un bloc e fie text, fie o lista — `body` cu is_list = 1 are un element pe linie.
 * Produsele fara proprietati cad pe short_description, ca sa nu ramana cardul gol.
 */
function renderProps(array $p): string {
    $props = $p['properties'] ?? [];
    if (!$props) {
        if (($p['short_description'] ?? '') === '') { return ''; }
        $props = [['label' => 'Descriere', 'body' => $p['short_description'], 'is_list' => 0]];
    }
    $out = '<div class="material-props">';
    foreach ($props as $pr) {
        $out .= '<div class="material-prop"><span class="prop-label">' . e($pr['label']) . '</span>';
        if ((int)$pr['is_list'] === 1) {
            $out .= '<ul class="prop-list">';
            foreach (explode("\n", $pr['body']) as $item) {
                if (trim($item) !== '') { $out .= '<li>' . e(trim($item)) . '</li>'; }
            }
            $out .= '</ul>';
        } else {
            $out .= '<span class="prop-val">' . e($pr['body']) . '</span>';
        }
        $out .= '</div>';
    }
    return $out . '</div>';
}

/** Continutul lui .material-img-wrap: fotografie (daca exista), placeholder, badge. */
function renderImgWrap(array $p): string {
    $hasImg = !empty($p['image_path']);
    $out = '<div class="material-img-wrap">';
    if ($hasImg) {
        $out .= '<img src="' . e($p['image_path']) . '" alt="' . e($p['image_alt'] ?? $p['name'])
              . '" class="material-img" width="280" height="160" loading="lazy" decoding="async"'
              . ' onerror="this.closest(\'.material-img-wrap\').style.display=\'none\'">';
    }
    $out .= renderIcon($p['icon_key'], $p['icon_label'], $hasImg);
    if (!empty($p['badge_label'])) {
        $cls = 'material-badge' . (!empty($p['badge_kind']) ? ' material-badge--' . e($p['badge_kind']) : '');
        $out .= '<div class="' . $cls . '">' . e($p['badge_label']) . '</div>';
    }
    return $out . '</div>';
}

/** Varianta din paginile de categorie. */
function renderMaterialCards(array $products): string {
    $out = '';
    foreach ($products as $p) {
        $out .= '<div class="material-card">'
              . renderImgWrap($p)
              . '<div class="material-body">'
              . '<h3 class="material-name">' . e($p['name']) . '</h3>'
              . renderProps($p)
              . '<div class="material-footer">'
              . '<span class="material-price">' . priceLabel($p) . '</span>'
              . '<a href="' . e(waLink($p['name'], $p['wa_text'] ?? null)) . '" class="btn-wa-material" target="_blank" rel="noopener">'
              . WA_ICON . 'Afla disponibilitate</a>'
              . '</div></div></div>';
    }
    return $out;
}

/**
 * Varianta din catalog. Filtrele JS existente citesc data-cat / data-subcat.
 *
 * NOTA: badge-ul de categorie sta in interiorul .prod-body, inaintea .prod-name —
 * asa e structurat in catalog.html azi (nu ca sibling inaintea lui .prod-body).
 * A se vedea raportul Task 4 pentru detalii si diferente ramase deschise
 * (iconita si clasa modificatoare a badge-ului, ex. prod-badge--electric).
 */
function renderProdCards(array $products): string {
    $out = '';
    foreach ($products as $p) {
        $subcat = $p['subcat_slug'] ? ' data-subcat="' . e($p['subcat_slug']) . '"' : '';
        $waText = !empty($p['wa_text']) ? ' data-wa-text="' . e($p['wa_text']) . '"' : '';
        $hasImg = !empty($p['image_path']);
        $imgWrap = '<div class="prod-img-wrap">';
        if ($hasImg) {
            $imgWrap .= '<img src="' . e($p['image_path']) . '" alt="' . e($p['image_alt'] ?? $p['name'])
                      . '" class="prod-img-photo" width="220" height="150" loading="lazy" decoding="async"'
                      . ' onerror="this.closest(\'.prod-img-wrap\').style.display=\'none\'">';
        }
        $imgWrap .= renderProdIcon($p['icon_key'], $p['icon_label'], $hasImg) . '</div>';
        $out .= '<div class="prod-card" data-cat="' . e($p['cat_slugs']) . '"' . $subcat . '>'
              . $imgWrap
              . '<div class="prod-body">'
              . '<div class="' . badgeClass($p) . '">' . e($p['cat_label']) . '</div>'
              . '<h3 class="prod-name">' . e($p['name']) . '</h3>'
              . '<div class="prod-desc">' . e($p['short_description']) . '</div>'
              . '<div class="prod-footer">'
              . '<div class="prod-price">' . priceLabel($p) . '</div>'
              . '<button class="btn-wa-prod" data-wa-product="' . e($p['name']) . '"' . $waText . '>'
              . WA_ICON . 'Afla disponibilitate</button>'
              . '</div></div></div>';
    }
    return $out;
}

/**
 * Varianta accesoriilor. Card simplu: titlu, descriere, buton WhatsApp.
 *
 * Wrap-ul de imagine (.accessory-img-wrap) se randeaza doar daca produsul are
 * fotografie SAU iconita — marea majoritate (223 din 264) n-au niciuna. Cand
 * exista si fotografie si iconita, placeholder-ul ramane in DOM ca fallback,
 * dar ascuns (acelasi tipar ca la .material-img-wrap / renderImgWrap()).
 */
function renderAccessoryCards(array $products): string {
    $out = '';
    foreach ($products as $p) {
        $cls = 'accessory-card' . (!empty($p['card_modifier'])
             ? ' accessory-card--' . e($p['card_modifier']) : '');
        $out .= '<div class="' . $cls . '">';

        $hasImg  = !empty($p['image_path']);
        $hasIcon = !empty($p['icon_key']);
        if ($hasImg || $hasIcon) {
            $out .= '<div class="accessory-img-wrap">';
            if ($hasImg) {
                $out .= '<img src="' . e($p['image_path']) . '" alt="' . e($p['image_alt'] ?? $p['name'])
                      . '" class="accessory-img" width="160" height="100" loading="lazy" decoding="async"'
                      . ' onerror="this.closest(\'.accessory-img-wrap\').style.display=\'none\'">';
            }
            if ($hasIcon) {
                $out .= renderAccessoryIcon($p['icon_key'], $p['icon_label'] ?? null, $hasImg);
            }
            $out .= '</div>';
        }

        $out .= '<div class="accessory-body">'
              . '<h4>' . e($p['name']) . '</h4>'
              . '<p>' . e($p['short_description']) . '</p>'
              . '<a href="' . e(waLink($p['name'], $p['wa_text'] ?? null)) . '" class="btn-wa-acc" target="_blank" rel="noopener">'
              . 'Afla disponibilitate</a>'
              . '</div></div>';
    }
    return $out;
}

/** Modificatorul de badge al unei categorii de nivel 1. Null = culoarea implicita. */
const BADGE_KIND = [
    'electrice'      => 'electric',
    'incalzire'      => 'incalzire',
    'sanitare'       => 'sanitare',
    'scule-unelte'   => 'scule',
    'constructii'    => 'constructii',
    'apa-canal'      => 'apa-canal',
    'gradina'        => 'gradina',
    'mobilier'       => 'mobilier',
    'electrocasnice' => 'electrocasnice',
];

/** Prima categorie de nivel 1 a produsului determina culoarea badge-ului. */
function badgeClass(array $p): string {
    $first = explode(' ', trim((string)$p['cat_slugs']))[0] ?? '';
    $kind  = BADGE_KIND[$first] ?? null;
    return 'prod-badge' . ($kind !== null ? ' prod-badge--' . $kind : '');
}

/** Afisat cand o sectiune nu are produse — inclusiv cand DB-ul e cazut. */
function renderEmptyNotice(): string {
    return '<p class="cat-empty-note">Lista de produse nu poate fi afisata momentan. '
         . 'Scrie-ne pe <a href="https://wa.me/' . WA_PHONE . '" target="_blank" rel="noopener">WhatsApp</a> '
         . 'si iti spunem imediat ce avem in stoc.</p>';
}
