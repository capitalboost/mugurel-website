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
ok(str_contains($h, 'style="display:none"'),                 'placeholder-ul e ascuns cand exista fotografie');
ok(str_contains($h, 'material-badge material-badge--popular'), 'badge cu modificator');
ok(str_contains($h, 'Cel mai vandut'),                       'textul badge-ului');
ok(str_contains($h, 'onerror='),                             'pastreaza fallback-ul onerror');

$fara = $sample;
$fara[0]['image_path'] = null; $fara[0]['badge_label'] = null;
$h2 = renderMaterialCards($fara);
ok(!str_contains($h2, '<img'),                    'fara fotografie => niciun <img>');
ok(str_contains($h2, 'style="display:flex"'),     'placeholder vizibil cand nu exista fotografie');
ok(!str_contains($h2, 'material-badge'),          'fara badge => niciun element de badge');

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
ok(str_contains($hPoza, "onerror=\"this.closest('.accessory-img-wrap').style.display='none'\""),
    'accessory: fallback onerror pe fotografie');
ok(!str_contains($hPoza, 'accessory-img-ph'),          'accessory: fara placeholder cand n-are iconita');

// fara alt explicit => cade pe nume
$faraAlt = $acc;
$faraAlt[0]['image_path'] = 'img/x.jpg';
$faraAlt[0]['image_alt']  = null;
$hFaraAlt = renderAccessoryCards($faraAlt);
ok(str_contains($hFaraAlt, 'alt="Tablou electric metalic/plastic 4-8 module"'), 'accessory: alt cade pe nume cand image_alt e null');

// varianta 3/4: doar iconita, fara fotografie (cazul a 16 din 264) — placeholder vizibil
$doarIcon = $acc;
$doarIcon[0]['icon_key'] = 'icon-09304de8';
$hIcon = renderAccessoryCards($doarIcon);
ok(str_contains($hIcon, 'class="accessory-img-wrap"'),   'accessory: img-wrap prezent cand exista iconita');
ok(str_contains($hIcon, 'class="accessory-img-ph" style="display:flex"'),
    'accessory: placeholder vizibil cand n-are fotografie');
ok(!str_contains($hIcon, '<img'),                          'accessory: fara <img> cand n-are fotografie');
ok(!str_contains($hIcon, '<span>'),                         'accessory: fara icon_label => placeholder fara eticheta (12 din 20 cazuri reale)');

// unele accesorii AU eticheta pe iconita (8 din 20 cazuri reale, ex. "Cot 45°") — icon_label
// se randeaza conditionat, nu se inventeaza din nume (Task 5D, al doilea fix)
$doarIconCuEticheta = $acc;
$doarIconCuEticheta[0]['icon_key']   = 'icon-09304de8';
$doarIconCuEticheta[0]['icon_label'] = 'Cot 45°';
$hIconEticheta = renderAccessoryCards($doarIconCuEticheta);
ok(str_contains($hIconEticheta, '<span>Cot 45°</span>'), 'accessory: eticheta randata cand icon_label exista');

// varianta 4/4: si fotografie si iconita (cazul a 4 din 264) — placeholder ascuns, ramane in DOM
$ambele = $acc;
$ambele[0]['image_path'] = 'img/brida-burlan.jpg';
$ambele[0]['image_alt']  = 'Brida burlan';
$ambele[0]['icon_key']   = 'icon-09304de8';
$ambele[0]['icon_label'] = 'Brida burlan';
$hAmbele = renderAccessoryCards($ambele);
ok(str_contains($hAmbele, 'class="accessory-img"'),        'accessory: fotografia e prezenta cand exista ambele');
ok(str_contains($hAmbele, 'class="accessory-img-ph" style="display:none"'),
    'accessory: placeholder ascuns (dar in DOM) cand exista si fotografie si iconita');
ok(str_contains($hAmbele, '<span>Brida burlan</span>'),
    'accessory: eticheta randata si cand placeholder-ul e ascuns');

// escaping pe eticheta iconitei
$xssIcon = $acc;
$xssIcon[0]['icon_key']   = 'icon-09304de8';
$xssIcon[0]['icon_label'] = '<script>alert(3)</script>';
ok(!str_contains(renderAccessoryCards($xssIcon), '<script>alert(3)</script>'),
    'accessory: escapeaza eticheta iconitei');

// iconita necunoscuta => cade pe iconita generica (acelasi tipar ca la materiale)
$iconNecunoscuta = $acc;
$iconNecunoscuta[0]['icon_key'] = 'icon-inexistenta';
$hNecunoscuta = renderAccessoryCards($iconNecunoscuta);
ok(str_contains($hNecunoscuta, PRODUCT_ICON_FALLBACK), 'accessory: iconita necunoscuta cade pe fallback');

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

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
