<?php
/**
 * Randarea produselor: pret, stoc, buton de cos, link WhatsApp, panoul generat si cardurile catalogului.
 * Doar HTML — fara SQL. (Cardurile catalogului vechi — .material-card / .accessory-card — au disparut pe 16 oct.)
 */

const WA_PHONE = '40749130565';

/** SVG-ul de WhatsApp, identic cu cel din paginile statice. */
const WA_ICON = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.231 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>';

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * Pretul afisat, cu TVA. Fara pret => "Pret la cerere"; cu price_is_from => "de la X lei"
 * (pret minim, produsul nu intra in cos).
 */
function priceLabel(array $p): string {
    if (($p['price'] ?? null) === null || (float)$p['price'] <= 0) { return 'Pret la cerere'; }
    return (!empty($p['price_is_from']) ? 'de la ' : '')
         . number_format((float)$p['price'], 2, ',', '.') . ' lei'
         . (!empty($p['price_unit']) ? ' / ' . e($p['price_unit']) : '');
}

/** Starile de stoc (coloana products.stock_status) si textul afisat pe site. */
const STOCK_LABELS = [
    'in_stoc'      => 'In stoc',
    'la_comanda'   => 'La comanda',
    'indisponibil' => 'Indisponibil',
];

/** Sub (sau egal cu) atatea unitati pe stoc, eticheta spune „Stoc limitat" (doar la produsele cu stoc numarat). */
const STOCK_LOW = 5;

/**
 * Cantitatea de pe stoc, daca stocul produsului se numara (products.stock_qty); null = stare aleasa de mana.
 * Cand se numara, stock_status e tinut in pas cu ea (0 => indisponibil) de admin si de comenzi.
 */
function stockQty(array $p): ?float {
    return isset($p['stock_qty']) && $p['stock_qty'] !== '' ? (float) $p['stock_qty'] : null;
}

/** Cantitate fara zecimale inutile: 3, 2,5. */
function formatQty(float $q): string {
    return rtrim(rtrim(number_format($q, 2, ',', ''), '0'), ',');
}

/** Textul de stoc al unui produs (si in cos). Stare lipsa sau necunoscuta => null (nu promitem ce nu stim). */
function stockText(array $p): ?string {
    $s = $p['stock_status'] ?? null;
    if (!isset(STOCK_LABELS[$s])) { return null; }
    $q = stockQty($p);
    if ($s === 'in_stoc' && $q !== null && $q > 0 && $q <= STOCK_LOW) {
        return 'Stoc limitat: ' . formatQty($q) . (!empty($p['price_unit']) ? ' ' . $p['price_unit'] : '');
    }
    return STOCK_LABELS[$s];
}

/** Eticheta de stoc a unui card / a paginii de produs. */
function stockLabel(array $p): string {
    $t = stockText($p);
    return $t === null ? '' : '<span class="stock stock--' . $p['stock_status'] . '">' . e($t) . '</span>';
}

/**
 * Poate produsul intra in cos? Doar cu pret fix (nu „de la", nu „la cerere") si daca nu e
 * indisponibil. Aceeasi regula se verifica si pe server, la cos si la comanda.
 */
function canAddToCart(array $p): bool {
    return (float)($p['price'] ?? 0) > 0
        && empty($p['price_is_from'])
        && ($p['stock_status'] ?? 'in_stoc') !== 'indisponibil';
}

/** Butonul „Adauga in cos" — sau nimic, daca produsul nu se poate comanda online. */
function cartButton(array $p): string {
    if (!canAddToCart($p) || empty($p['id'])) { return ''; }
    return '<button type="button" class="btn-cart" data-cart-id="' . (int)$p['id'] . '"'
         . ' data-cart-name="' . e($p['name']) . '">Adauga in cos</button>';
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
 * Panou generat determinist din numele produsului: fundal si forme calculate
 * din md5(nume), mereu identice pentru acelasi produs, practic niciodata la
 * fel intre produse. Randat ca strat de baza in toate zonele de imagine —
 * si sub fotografie (cand exista), ca plasa de siguranta daca fisierul lipseste.
 */
function panouGenerat(string $name): string {
    $h = md5($name);
    $tonuri = ['#0A5258', '#0E6A66', '#127A6E', '#14655F', '#0C4E55', '#16806F'];
    $bg = $tonuri[hexdec(substr($h, 0, 2)) % count($tonuri)];
    $rot = hexdec(substr($h, 2, 2)) % 45;
    $o = [];
    for ($i = 0; $i < 6; $i++) {
        $x = hexdec(substr($h, 4 + $i * 4, 2)) % 100;
        $y = hexdec(substr($h, 6 + $i * 4, 2)) % 100;
        $r = 8 + (hexdec(substr($h, 4 + $i * 2, 1)) % 14);
        $op = 0.06 + (hexdec(substr($h, 5 + $i * 2, 1)) % 6) * 0.018;
        $o[] = ($i % 2 === 0)
            ? '<circle cx="' . $x . '" cy="' . $y . '" r="' . $r . '" fill="#fff" opacity="' . round($op, 3) . '"/>'
            : '<rect x="' . $x . '" y="' . $y . '" width="' . ($r * 2) . '" height="' . ($r * 2) . '" fill="#fff" opacity="' . round($op, 3) . '" transform="rotate(' . $rot . ' ' . $x . ' ' . $y . ')"/>';
    }
    return '<div class="v-gen" style="background:' . $bg . '">'
         . '<svg viewBox="0 0 100 100" preserveAspectRatio="xMidYMid slice" aria-hidden="true">' . implode('', $o) . '</svg>'
         . '</div>';
}

/** Adresa paginii unui produs din catalogul nou. */
function productUrl(array $p): string {
    return '/produs/' . rawurlencode((string)$p['slug']) . '.html';
}

/**
 * Cardurile catalogului nou (categorii, colectii, cautare, prima pagina). Acelasi aspect ca .prod-card din
 * catalog, dar imaginea si numele duc la pagina produsului. Fara pret => „Pret la cerere" + WhatsApp, fara cos.
 */
function renderCatalogCards(array $products): string {
    $out = '';
    foreach ($products as $p) {
        $url = e(productUrl($p));
        $img = '<a class="prod-img-wrap" href="' . $url . '" tabindex="-1" aria-hidden="true">' . panouGenerat($p['name']);
        if (!empty($p['image_path'])) {
            $img .= '<img src="' . e('/' . ltrim($p['image_path'], '/')) . '" alt="" class="prod-img-photo" width="220" height="150"'
                  . ' loading="lazy" decoding="async" onerror="this.style.display=\'none\'">';
        }
        $img .= '</a>';
        // fara cos: produs indisponibil => „cand revine?"; fara pret => cerere de oferta
        $unavailable = ($p['stock_status'] ?? '') === 'indisponibil';
        $action = canAddToCart($p)
            ? cartButton($p)
            : '<a href="' . e(waLink($p['name'], $unavailable ? 'Buna ziua! Cand revine pe stoc: ' . $p['name'] . '?' : ($p['wa_text'] ?? null)))
              . '" class="btn-wa-prod" target="_blank" rel="noopener">' . WA_ICON . ($unavailable ? 'Întreabă când revine' : 'Cere oferta') . '</a>';
        $out .= '<div class="prod-card cat-card" data-product-id="' . (int)$p['id'] . '">'
              . $img
              . '<div class="prod-body">'
              . (!empty($p['category_name']) ? '<div class="prod-cat">' . e($p['category_name']) . '</div>' : '')
              . '<h3 class="prod-name"><a href="' . $url . '">' . e($p['name']) . '</a></h3>'
              . (!empty($p['short_description']) ? '<div class="prod-desc">' . e($p['short_description']) . '</div>' : '')
              . stockLabel($p)
              . '<div class="prod-footer"><div class="prod-price">' . priceLabel($p) . '</div></div>'
              . $action
              . '</div></div>';
    }
    return $out;
}

/** Afisat cand o sectiune nu are produse — inclusiv cand DB-ul e cazut. */
function renderEmptyNotice(): string {
    return '<p class="cat-empty-note">Lista de produse nu poate fi afisata momentan. '
         . 'Scrie-ne pe <a href="https://wa.me/' . WA_PHONE . '" target="_blank" rel="noopener">WhatsApp</a> '
         . 'si iti spunem imediat ce avem in stoc.</p>';
}
