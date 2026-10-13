<?php
require_once __DIR__ . '/../../includes/product_grid.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass,$fail; if($c){$pass++;echo"  ✓ $m\n";}else{$fail++;echo"  ✗ $m\n";} }

$sample = [[
    'id' => 1,
    'name' => 'Cablu CYY-F 3x2.5mm² 100m',
    'short_description' => 'Cablu electric pentru instalatii fixe. Conductor cupru, izolatie PVC. Rola 100m.',
    'icon_key' => 'inexistenta', 'icon_label' => 'Cablu CYY-F 3x2.5mm²',
    'price' => null, 'price_unit' => null,
    'cat_slugs' => 'electrice', 'subcat_slug' => 'cabluri', 'cat_label' => 'Electrice',
    'properties' => [
        ['label' => 'Aspect',   'body' => 'Cablu flexibil cu 3 conductori de cupru.', 'is_list' => 0],
        ['label' => 'Avantaje', 'body' => "Flexibil, usor de pozat
Rezistenta la UV", 'is_list' => 1],
    ],
]];

// ── varianta .material-card (pagini de categorie) ──
$m = renderMaterialCards($sample);
ok(str_contains($m, 'class="material-card"'),        'material: containerul .material-card');
ok(str_contains($m, 'class="material-name"'),        'material: titlul .material-name');
ok(str_contains($m, 'class="prop-val"'),             'material: textul in .prop-val');
ok(str_contains($m, '<span class="prop-label">Aspect</span>'), 'material: eticheta primului bloc');
ok(str_contains($m, '<ul class="prop-list">'),       'material: blocul de tip lista devine <ul>');
ok(substr_count($m, '<li>') === 2,                   'material: doua elemente in lista');
ok(substr_count($m, 'class="material-prop"') === 2,  'material: ambele blocuri randate');
ok(str_contains($m, 'class="btn-wa-material"'),      'material: butonul e .btn-wa-material');
ok(str_contains($m, 'href="https://wa.me/40749130565'), 'material: link WhatsApp direct');
ok(str_contains($m, 'Pret la cerere'),               'material: pret la cerere cand price e null');
ok(!str_contains($m, 'class="prod-card"'),           'material: NU foloseste clasele de catalog');

// ── varianta .prod-card (catalog) ──
$p = renderProdCards($sample);
ok(str_contains($p, 'class="prod-card"'),            'prod: containerul .prod-card');
ok(str_contains($p, 'data-cat="electrice"'),         'prod: data-cat din cat_slugs');
ok(str_contains($p, 'data-subcat="cabluri"'),        'prod: data-subcat din subcat_slug');
ok(str_contains($p, 'class="prod-desc"'),            'prod: descrierea in .prod-desc');
ok(str_contains($p, 'class="btn-wa-prod"'),          'prod: butonul e .btn-wa-prod');
ok(str_contains($p, 'data-wa-product='),             'prod: atributul data-wa-product');
ok(!str_contains($p, 'class="material-card"'),       'prod: NU foloseste clasele de categorie');

// ── escaping ──
$xss = $sample;
$xss[0]['name'] = 'Test "<script>alert(1)</script>';
foreach (['renderMaterialCards','renderProdCards'] as $fn) {
    $h = $fn($xss);
    ok(!str_contains($h, '<script>'), "$fn escapeaza numele");
}

// ── multi-categorie ──
$multi = $sample;
$multi[0]['cat_slugs'] = 'incalzire apa-canal';
ok(str_contains(renderProdCards($multi), 'data-cat="incalzire apa-canal"'), 'prod: cat_slugs multiple');

// Produs fara proprietati: cade pe short_description, cardul nu ramane gol.
$fara = $sample; $fara[0]['properties'] = [];
ok(str_contains(renderMaterialCards($fara), 'class="prop-val"'), 'fara properties => foloseste short_description');

// ── fotografie si badge (Task 5C) ──
$cuPoza = $sample;
$cuPoza[0]['image_path'] = 'img/cablu-myym.jpg';
$cuPoza[0]['image_alt']  = 'Cablu MYYM';
$cuPoza[0]['badge_label'] = 'Cel mai vandut';
$cuPoza[0]['badge_kind']  = 'popular';
$h = renderMaterialCards($cuPoza);
ok(str_contains($h, 'class="material-img"'),                 'randeaza <img> cand exista fotografie');
ok(str_contains($h, 'class="v-gen"'),                         'panoul generat e prezent si cand exista fotografie (strat de baza)');
ok(str_contains($h, 'material-badge material-badge--popular'), 'badge cu modificator');
ok(str_contains($h, 'Cel mai vandut'),                       'textul badge-ului');
ok(str_contains($h, "onerror=\"this.style.display='none'\""), 'onerror ascunde doar <img>-ul, nu tot blocul');
ok(str_contains($h, 'loading="lazy"'),                       'material: <img> are loading="lazy"');
ok(str_contains($h, 'decoding="async"'),                     'material: <img> are decoding="async"');
ok(str_contains($h, 'width="280" height="160"'),             'material: <img> are width/height');
ok(!str_contains($h, 'material-img-ph'),                     'material: nicio iconita placeholder cand exista fotografie');

$fara = $sample;
$fara[0]['image_path'] = null; $fara[0]['badge_label'] = null;
$h2 = renderMaterialCards($fara);
ok(!str_contains($h2, '<img'),                    'fara fotografie => niciun <img>');
ok(str_contains($h2, 'class="v-gen"'),            'panoul generat e prezent cand nu exista fotografie');
ok(!str_contains($h2, 'material-badge'),          'fara badge => niciun element de badge');
ok(!str_contains($h2, 'material-img-ph'),         'material: nicio iconita placeholder ramasa');
ok(!str_contains($h2, '<span>'),                  'panoul fara fotografie nu are <span> cu eticheta (design curat)');

// ── panoul generat: determinism si varietate (Task panou-generat) ──
$panou1 = panouGenerat('Tabla Zincata Simpla');
$panou2 = panouGenerat('Tabla Zincata Simpla');
ok($panou1 === $panou2, 'panouGenerat: acelasi nume => rezultat identic (determinism)');
ok(panouGenerat('Robinet cu Sfera') !== panouGenerat('Teava PPR 20mm'),
    'panouGenerat: nume diferite => panouri diferite');
ok(str_contains($panou1, 'class="v-gen"'),        'panouGenerat: containerul .v-gen');
ok(str_contains($panou1, 'aria-hidden="true"'),   'panouGenerat: svg-ul e aria-hidden');

// panou prezent cand nu exista poza — pe cardul de tip material (Task panou-generat, verif. 1)
$faraPoza = $sample;
$faraPoza[0]['image_path'] = null;
ok(str_contains(renderMaterialCards($faraPoza), 'class="v-gen"'),
    'material: panoul generat e prezent cand nu exista poza');

$faraKind = $sample;
$faraKind[0]['image_path'] = null;
$faraKind[0]['badge_label'] = 'Reducere';
$faraKind[0]['badge_kind'] = null;
$h3 = renderMaterialCards($faraKind);
ok(str_contains($h3, 'class="material-badge"'), 'badge fara modificator => clasa exacta, fara spatiu in coada');

// ── lista goala ──
ok(renderMaterialCards([]) === '', 'lista goala => string gol');
ok(str_contains(renderEmptyNotice(), 'wa.me/40749130565'), 'mesajul gol trimite pe WhatsApp');

// ── renderAccessoryCards() (Task 5D) ──
$acc = [[
    'id' => 500,
    'name' => 'Tablou electric metalic/plastic 4-8 module',
    'short_description' => 'Tablou de distributie pentru locuinte, cu sina DIN integrata.',
    'card_modifier' => null,
    'image_path' => null, 'image_alt' => null,
    'icon_key' => null, 'icon_label' => null,
]];

// varianta 1/4: nimic (fara fotografie, fara iconita) — cazul a 223 din 264 de accesorii reale
$a = renderAccessoryCards($acc);
ok(str_contains($a, 'class="accessory-card">'),        'accessory: containerul .accessory-card simplu');
ok(str_contains($a, 'class="accessory-body"'),          'accessory: corpul .accessory-body');
ok(str_contains($a, '<h4>Tablou electric metalic/plastic 4-8 module</h4>'), 'accessory: numele in <h4>');
ok(str_contains($a, '<p>Tablou de distributie pentru locuinte, cu sina DIN integrata.</p>'), 'accessory: descrierea in <p>');
ok(str_contains($a, 'class="btn-wa-acc"'),               'accessory: butonul e .btn-wa-acc');
ok(str_contains($a, 'href="https://wa.me/40749130565'), 'accessory: link WhatsApp direct');
ok(str_contains($a, 'target="_blank" rel="noopener"'),  'accessory: target si rel pe buton');
ok(!str_contains($a, 'accessory-img-wrap'),              'accessory: fara img-wrap cand nu are fotografie nici iconita');
ok(!str_contains($a, 'accessory-card--'),                'accessory: fara modificator cand card_modifier e null');
ok(!str_contains($a, 'class="material-card"'),           'accessory: NU foloseste clasele de materiale');

// varianta --sm
$sm = $acc;
$sm[0]['card_modifier'] = 'sm';
$hSm = renderAccessoryCards($sm);
ok(str_contains($hSm, 'class="accessory-card accessory-card--sm"'), 'accessory: varianta --sm are ambele clase');

// varianta 2/4: doar fotografie, fara iconita (cazul a 21 din 264)
$cuPoza = $acc;
$cuPoza[0]['image_path'] = 'img/jgheab-semicircular.jpg';
$cuPoza[0]['image_alt']  = 'Jgheab semicircular';
$hPoza = renderAccessoryCards($cuPoza);
ok(str_contains($hPoza, 'class="accessory-img-wrap"'), 'accessory: img-wrap prezent cand exista fotografie');
ok(str_contains($hPoza, 'src="img/jgheab-semicircular.jpg"'), 'accessory: src-ul fotografiei');
ok(str_contains($hPoza, 'alt="Jgheab semicircular"'),  'accessory: alt-ul fotografiei');
ok(str_contains($hPoza, 'class="accessory-img"'),      'accessory: clasa .accessory-img');
ok(str_contains($hPoza, 'class="v-gen"'),              'accessory: panoul generat e strat de baza si cu fotografie');
ok(str_contains($hPoza, "onerror=\"this.style.display='none'\""),
    'accessory: onerror ascunde doar <img>-ul, nu tot blocul');
ok(!str_contains($hPoza, 'accessory-img-ph'),          'accessory: fara placeholder de iconita cand n-are iconita');
ok(str_contains($hPoza, 'loading="lazy"'),             'accessory: <img> are loading="lazy"');
ok(str_contains($hPoza, 'decoding="async"'),           'accessory: <img> are decoding="async"');

// fara alt explicit => cade pe nume
$faraAlt = $acc;
$faraAlt[0]['image_path'] = 'img/x.jpg';
$faraAlt[0]['image_alt']  = null;
$hFaraAlt = renderAccessoryCards($faraAlt);
ok(str_contains($hFaraAlt, 'alt="Tablou electric metalic/plastic 4-8 module"'), 'accessory: alt cade pe nume cand image_alt e null');

// varianta 3/4: doar iconita, fara fotografie (cazul a 16 din 264) — iconita nu se mai randeaza,
// dar zona de imagine tot se randeaza, cu panoul generat ca strat de baza.
$doarIcon = $acc;
$doarIcon[0]['icon_key'] = 'icon-09304de8';
$hIcon = renderAccessoryCards($doarIcon);
ok(str_contains($hIcon, 'class="accessory-img-wrap"'),   'accessory: img-wrap prezent cand exista iconita');
ok(str_contains($hIcon, 'class="v-gen"'),                 'accessory: panoul generat vizibil cand n-are fotografie');
ok(!str_contains($hIcon, '<img'),                          'accessory: fara <img> cand n-are fotografie');
ok(!str_contains($hIcon, 'accessory-img-ph'),               'accessory: iconita nu se mai randeaza (icon_key ramane in DB, doar nu se afiseaza)');
ok(!str_contains($hIcon, '<span>'),                         'accessory: fara icon_label => nimic randat (12 din 20 cazuri reale)');

// icon_label nu se mai randeaza deloc, nici cand exista (design curat, fara eticheta
// care repeta titlul de sub card — vezi raportul design/perf) — dar ramane in DB.
$doarIconCuEticheta = $acc;
$doarIconCuEticheta[0]['icon_key']   = 'icon-09304de8';
$doarIconCuEticheta[0]['icon_label'] = 'Cot 45°';
$hIconEticheta = renderAccessoryCards($doarIconCuEticheta);
ok(!str_contains($hIconEticheta, '<span>'), 'accessory: fara <span> chiar si cand icon_label exista');

// varianta 4/4: si fotografie si iconita (cazul a 4 din 264) — iconita nu se mai randeaza,
// fotografia sta deasupra panoului generat.
$ambele = $acc;
$ambele[0]['image_path'] = 'img/brida-burlan.jpg';
$ambele[0]['image_alt']  = 'Brida burlan';
$ambele[0]['icon_key']   = 'icon-09304de8';
$ambele[0]['icon_label'] = 'Brida burlan';
$hAmbele = renderAccessoryCards($ambele);
ok(str_contains($hAmbele, 'class="accessory-img"'),        'accessory: fotografia e prezenta cand exista ambele');
ok(str_contains($hAmbele, 'class="v-gen"'),                'accessory: panoul generat ramane strat de baza sub fotografie');
ok(!str_contains($hAmbele, 'accessory-img-ph'),
    'accessory: iconita nu se mai randeaza chiar daca icon_key si icon_label exista');
ok(!str_contains($hAmbele, '<span>'),
    'accessory: fara <span> chiar si cand exista icon_label');

// icon_key necunoscut nu produce nicio eroare (iconita oricum nu se mai randeaza)
$iconNecunoscuta = $acc;
$iconNecunoscuta[0]['icon_key'] = 'icon-inexistenta';
$hNecunoscuta = renderAccessoryCards($iconNecunoscuta);
ok(!str_contains($hNecunoscuta, PRODUCT_ICON_FALLBACK), 'accessory: nicio iconita (nici fallback) cand icon_key e necunoscut');
ok(str_contains($hNecunoscuta, 'class="v-gen"'), 'accessory: panoul generat randat in locul iconitei necunoscute');

// escaping
$xssAcc = $acc;
$xssAcc[0]['name'] = 'Test "<script>alert(1)</script>';
$xssAcc[0]['short_description'] = 'Descriere "<script>alert(2)</script>';
$hXss = renderAccessoryCards($xssAcc);
ok(!str_contains($hXss, '<script>'), 'accessory: escapeaza numele si descrierea');

// lista goala
ok(renderAccessoryCards([]) === '', 'accessory: lista goala => string gol');

// ── mesaj WhatsApp propriu / sablon (Task 5E) ──

// waLink(): custom prezent => il foloseste
ok(waLink('Produs X', 'Mesaj scris de mana.') === 'https://wa.me/40749130565?text=' . rawurlencode('Mesaj scris de mana.'),
    'waLink: foloseste mesajul custom cand exista');
// waLink(): custom null => sablonul
ok(waLink('Produs X', null) === 'https://wa.me/40749130565?text='
    . rawurlencode('Buna ziua! Sunt interesat de: Produs X. Puteti confirma disponibilitatea?'),
    'waLink: cade pe sablon cand custom e null');
// waLink(): custom string gol => tot sablonul (nu text gol)
ok(waLink('Produs X', '') === 'https://wa.me/40749130565?text='
    . rawurlencode('Buna ziua! Sunt interesat de: Produs X. Puteti confirma disponibilitatea?'),
    'waLink: cade pe sablon cand custom e string gol');

// renderMaterialCards(): produs cu wa_text propriu => href-ul contine mesajul custom, nu sablonul
$custom = $sample;
$custom[0]['wa_text'] = 'Buna ziua! Sunt interesat de acest cablu.';
$hCustom = renderMaterialCards($custom);
ok(str_contains($hCustom, 'text=' . rawurlencode('Buna ziua! Sunt interesat de acest cablu.')),
    'material: href contine wa_text propriu cand exista');
ok(!str_contains($hCustom, rawurlencode('Sunt interesat de: ' . $custom[0]['name'])),
    'material: href NU contine sablonul cand exista wa_text propriu');

// renderMaterialCards(): produs fara wa_text => sablonul, ca inainte
$faraWa = $sample;
$faraWa[0]['wa_text'] = null;
$hFaraWa = renderMaterialCards($faraWa);
ok(str_contains($hFaraWa, rawurlencode('Sunt interesat de: ' . $faraWa[0]['name'])),
    'material: href foloseste sablonul cand wa_text lipseste');

// renderAccessoryCards(): acelasi comportament
$accCustom = $acc;
$accCustom[0]['wa_text'] = 'Buna ziua! Sunt interesat de tabloul electric.';
$hAccCustom = renderAccessoryCards($accCustom);
ok(str_contains($hAccCustom, 'text=' . rawurlencode('Buna ziua! Sunt interesat de tabloul electric.')),
    'accessory: href contine wa_text propriu cand exista');

$accFaraWa = $acc;
$hAccFaraWa = renderAccessoryCards($accFaraWa);
ok(str_contains($hAccFaraWa, rawurlencode('Sunt interesat de: ' . $accFaraWa[0]['name'])),
    'accessory: href foloseste sablonul cand wa_text lipseste');

// renderProdCards(): data-wa-text prezent doar cand exista wa_text
$prodCustom = $sample;
$prodCustom[0]['wa_text'] = 'Mesaj propriu produs catalog.';
ok(str_contains(renderProdCards($prodCustom), 'data-wa-text="Mesaj propriu produs catalog."'),
    'prod: data-wa-text randat cand exista wa_text');
ok(!str_contains(renderProdCards($sample), 'data-wa-text'),
    'prod: fara data-wa-text cand nu exista wa_text');

// ── renderProdIcon() (Task 8A; eticheta eliminata la task design/perf) ──
$icoCunoscut = renderProdIcon('inexistenta', 'Eticheta test');
ok(str_contains($icoCunoscut, 'class="prod-img-ph"'), 'renderProdIcon: containerul .prod-img-ph');
ok(!str_contains($icoCunoscut, '<span>'), 'renderProdIcon: fara <span> chiar si cand eticheta exista');
ok(str_contains($icoCunoscut, PRODUCT_ICON_FALLBACK), 'renderProdIcon: cheie necunoscuta cade pe iconita generica');

$icoFaraLabel = renderProdIcon(null, null);
ok(!str_contains($icoFaraLabel, '<span>'), 'renderProdIcon: fara <span> cand eticheta lipseste');

$icoLabelGol = renderProdIcon(null, '');
ok(!str_contains($icoLabelGol, '<span>'), 'renderProdIcon: fara <span> cand eticheta e string gol');

// ── badgeClass() (Task 8A) ──
$badgeCazuri = [
    'electrice'      => 'prod-badge--electric',
    'incalzire'      => 'prod-badge--incalzire',
    'sanitare'       => 'prod-badge--sanitare',
    'scule-unelte'   => 'prod-badge--scule',
    'constructii'    => 'prod-badge--constructii',
    'apa-canal'      => 'prod-badge--apa-canal',
    'gradina'        => 'prod-badge--gradina',
    'mobilier'       => 'prod-badge--mobilier',
    'electrocasnice' => 'prod-badge--electrocasnice',
];
foreach ($badgeCazuri as $slug => $expected) {
    $cls = badgeClass(['cat_slugs' => $slug]);
    ok($cls === 'prod-badge ' . $expected, "badgeClass($slug) => $expected (got $cls)");
}

$badgeNecunoscut = badgeClass(['cat_slugs' => 'slug-necunoscut']);
ok($badgeNecunoscut === 'prod-badge', 'badgeClass: slug necunoscut => doar prod-badge (got ' . $badgeNecunoscut . ')');

// Produs cross-listat: prima categorie din cat_slugs determina culoarea.
$badgeCrossListat = badgeClass(['cat_slugs' => 'incalzire apa-canal']);
ok($badgeCrossListat === 'prod-badge prod-badge--incalzire',
    'badgeClass: cross-listat foloseste prima categorie (got ' . $badgeCrossListat . ')');

// ── renderProdCards() nu mai randeaza iconita placeholder — panoul generat ii ia locul ──
$prodHtml = renderProdCards($sample);
ok(!str_contains($prodHtml, 'class="prod-img-ph"'), 'renderProdCards: nu mai foloseste .prod-img-ph');
ok(!str_contains($prodHtml, 'class="material-img-ph"'), 'renderProdCards: NU foloseste .material-img-ph');
ok(str_contains($prodHtml, 'class="v-gen"'), 'renderProdCards: panoul generat e prezent');

// ── renderProdCards() randeaza fotografia cand exista (fix inconsecventa catalog) ──
$prodCuPoza = $sample;
$prodCuPoza[0]['image_path'] = 'img/tabla-cutata.jpg';
$prodCuPoza[0]['image_alt']  = 'Tabla cutata';
$hProdPoza = renderProdCards($prodCuPoza);
ok(str_contains($hProdPoza, 'src="img/tabla-cutata.jpg"'),   'prod: randeaza <img> cand exista fotografie');
ok(str_contains($hProdPoza, 'alt="Tabla cutata"'),            'prod: alt-ul fotografiei');
ok(str_contains($hProdPoza, "onerror=\"this.style.display='none'\""),
    'prod: onerror ascunde doar <img>-ul, nu tot blocul');
ok(str_contains($hProdPoza, 'loading="lazy"'),                'prod: <img> are loading="lazy"');
ok(str_contains($hProdPoza, 'decoding="async"'),              'prod: <img> are decoding="async"');
ok(str_contains($hProdPoza, 'class="v-gen"'),
    'prod: panoul generat ramane strat de baza cand exista fotografie');

$prodFaraPoza = $sample;
$prodFaraPoza[0]['image_path'] = null;
$hProdFaraPoza = renderProdCards($prodFaraPoza);
ok(!str_contains($hProdFaraPoza, '<img'),                      'prod: fara fotografie => niciun <img>');
ok(str_contains($hProdFaraPoza, 'class="v-gen"'),
    'prod: panoul generat prezent cand nu exista fotografie');

// ── priceLabel(): pret fix, pret minim („de la"), fara pret (T2) ──
$base = ['price' => null, 'price_unit' => null, 'price_is_from' => 0];
ok(priceLabel($base) === 'Pret la cerere',                                  'pret: null => Pret la cerere');
ok(priceLabel(['price' => '0.00'] + $base) === 'Pret la cerere',           'pret: 0 => Pret la cerere');
ok(priceLabel(['price' => '55.00', 'price_unit' => 'm²'] + $base) === '55,00 lei / m²', 'pret fix: 55,00 lei / m²');
ok(priceLabel(['price' => '1234.5', 'price_unit' => 'buc'] + $base) === '1.234,50 lei / buc', 'pret: separator de mii si zecimale RO');
ok(priceLabel(['price' => '55', 'price_unit' => 'm²', 'price_is_from' => 1] + $base) === 'de la 55,00 lei / m²', 'pret minim: de la 55,00 lei / m²');
ok(priceLabel(['price' => '12', 'price_unit' => ''] + $base) === '12,00 lei',  'pret fara unitate: fara " / "');
ok(priceLabel(['price' => '12', 'price_unit' => null, 'price_is_from' => '1'] + $base) === 'de la 12,00 lei', 'pret minim fara unitate');
ok(priceLabel(['price' => '12', 'price_unit' => '<b>x</b>'] + $base) === '12,00 lei / &lt;b&gt;x&lt;/b&gt;', 'pret: unitatea e escapata');
ok(priceLabel(['price' => '12']) === '12,00 lei',                          'pret: functioneaza fara cheile price_unit/price_is_from');

$cuPret = $sample;
$cuPret[0]['price'] = '55.00'; $cuPret[0]['price_unit'] = 'm²'; $cuPret[0]['price_is_from'] = 1;
ok(str_contains(renderMaterialCards($cuPret), '<span class="material-price">de la 55,00 lei / m²</span>'), 'material: afiseaza „de la"');
ok(str_contains(renderProdCards($cuPret), '<div class="prod-price">de la 55,00 lei / m²</div>'),        'catalog: afiseaza „de la"');
$acc = renderAccessoryCards($cuPret);
ok(str_contains($acc, '<span class="accessory-price">de la 55,00 lei / m²</span>'),                       'accesoriu: afiseaza pretul (nou in T2)');
ok(strpos($acc, 'accessory-price') < strpos($acc, 'btn-wa-acc'),                                           'accesoriu: pretul sta inaintea butonului');
ok(str_contains(renderAccessoryCards($sample), '<span class="accessory-price">Pret la cerere</span>'),     'accesoriu: fara pret => Pret la cerere');

// ── stockLabel(): eticheta de stoc pe toate cardurile (T3) ──
ok(stockLabel(['stock_status' => 'in_stoc']) === '<span class="stock stock--in_stoc">In stoc</span>',            'stoc: in_stoc');
ok(stockLabel(['stock_status' => 'la_comanda']) === '<span class="stock stock--la_comanda">La comanda</span>',  'stoc: la_comanda');
ok(stockLabel(['stock_status' => 'indisponibil']) === '<span class="stock stock--indisponibil">Indisponibil</span>', 'stoc: indisponibil');
ok(stockLabel([]) === '',                                       'stoc: lipsa => nimic');
ok(stockLabel(['stock_status' => '<script>']) === '',          'stoc: valoare necunoscuta => nimic (nu ajunge in HTML)');

$laComanda = $sample;
$laComanda[0]['stock_status'] = 'la_comanda';
foreach (['material' => renderMaterialCards($laComanda), 'catalog' => renderProdCards($laComanda), 'accesoriu' => renderAccessoryCards($laComanda)] as $tip => $html) {
    ok(substr_count($html, 'class="stock stock--la_comanda"') === 1, "stoc: cardul $tip afiseaza eticheta o singura data");
}
ok(!str_contains(renderMaterialCards($sample), 'class="stock'), 'stoc: card fara stock_status => fara eticheta');

// ── canAddToCart() / cartButton(): doar pret fix si disponibil (T4) ──
$fix = ['id' => 7, 'name' => 'Burlan "90" <mm>', 'price' => '75.00', 'price_unit' => 'buc', 'price_is_from' => 0, 'stock_status' => 'in_stoc'];
ok(canAddToCart($fix) === true,                                                    'cos: pret fix + in stoc => da');
ok(canAddToCart(['stock_status' => 'la_comanda'] + $fix) === true,                'cos: la comanda => da');
ok(canAddToCart(['stock_status' => 'indisponibil'] + $fix) === false,             'cos: indisponibil => nu');
ok(canAddToCart(['price_is_from' => 1] + $fix) === false,                         'cos: pret „de la" => nu');
ok(canAddToCart(['price_is_from' => '1'] + $fix) === false,                       'cos: pret „de la" venit ca text din PDO => nu');
ok(canAddToCart(['price' => null] + $fix) === false,                              'cos: fara pret => nu');
ok(canAddToCart(['price' => '0.00'] + $fix) === false,                            'cos: pret 0 => nu');
ok(canAddToCart(['price' => '75']) === true,                                       'cos: fara stoc/„de la" in date => implicit da');

$btn = cartButton($fix);
ok(str_contains($btn, 'data-cart-id="7"'),                                        'buton: data-cart-id');
ok(str_contains($btn, 'data-cart-name="Burlan &quot;90&quot; &lt;mm&gt;"'),       'buton: numele e escapat in atribut');
ok(str_contains($btn, 'type="button"'),                                            'buton: type="button" (nu trimite formulare)');
ok(cartButton(['price_is_from' => 1] + $fix) === '',                              'buton: lipseste la „de la"');
ok(cartButton(['id' => null] + $fix) === '',                                       'buton: lipseste fara id');

$cardFix = $sample;
$cardFix[0] = $fix + $cardFix[0];
foreach (['material' => renderMaterialCards($cardFix), 'catalog' => renderProdCards($cardFix), 'accesoriu' => renderAccessoryCards($cardFix)] as $tip => $html) {
    ok(substr_count($html, 'class="btn-cart"') === 1,                               "buton: prezent o data pe cardul $tip");
}
ok(!str_contains(renderMaterialCards($sample), 'btn-cart'),                       'buton: cardul fara pret nu are buton');

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
