<?php
// Teste pentru includes/product_grid.php: pret, stoc, cos, WhatsApp, panoul generat si cardurile catalogului nou.
// (Testele cardurilor vechi — .material-card / .accessory-card — au disparut odata cu catalogul vechi, 16 oct.)
require_once __DIR__ . '/../../includes/product_grid.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass,$fail; if($c){$pass++;echo"  ✓ $m\n";}else{$fail++;echo"  ✗ $m\n";} }

// ── priceLabel(): pret fix, pret minim („de la"), fara pret (T2) ──
$base = ['price' => null, 'price_unit' => null, 'price_is_from' => 0];
ok(priceLabel($base) === 'Pret la cerere',                                  'pret: null => Pret la cerere');
ok(priceLabel(['price' => '0.00'] + $base) === 'Pret la cerere',           'pret: 0 => Pret la cerere');
ok(priceLabel(['price' => '55.00', 'price_unit' => 'm²'] + $base) === '55,00 lei / m²', 'pret fix: 55,00 lei / m²');
ok(priceLabel(['price' => '1234.5', 'price_unit' => 'buc'] + $base) === '1.234,50 lei / buc', 'pret: separator de mii si zecimale RO');
ok(priceLabel(['price' => '55', 'price_unit' => 'm²', 'price_is_from' => 1] + $base) === 'de la 55,00 lei / m²', 'pret minim: de la 55,00 lei / m²');
ok(priceLabel(['price' => '12', 'price_unit' => ''] + $base) === '12,00 lei',  'pret fara unitate: fara " / "');
ok(priceLabel(['price' => '12', 'price_unit' => '<b>x</b>'] + $base) === '12,00 lei / &lt;b&gt;x&lt;/b&gt;', 'pret: unitatea e escapata');
ok(priceLabel(['price' => '12']) === '12,00 lei',                          'pret: functioneaza fara cheile price_unit/price_is_from');

// ── stockLabel() (T3) ──
ok(stockLabel(['stock_status' => 'in_stoc']) === '<span class="stock stock--in_stoc">In stoc</span>',            'stoc: in_stoc');
ok(stockLabel(['stock_status' => 'la_comanda']) === '<span class="stock stock--la_comanda">La comanda</span>',  'stoc: la_comanda');
ok(stockLabel(['stock_status' => 'indisponibil']) === '<span class="stock stock--indisponibil">Indisponibil</span>', 'stoc: indisponibil');
ok(stockLabel([]) === '',                                       'stoc: lipsa => nimic');
ok(stockLabel(['stock_status' => '<script>']) === '',          'stoc: valoare necunoscuta => nimic (nu ajunge in HTML)');

// ── canAddToCart() / cartButton() (T4) ──
$fix = ['id' => 7, 'name' => 'Burlan "90" <mm>', 'price' => '75.00', 'price_unit' => 'buc', 'price_is_from' => 0, 'stock_status' => 'in_stoc'];
ok(canAddToCart($fix) === true,                                                    'cos: pret fix + in stoc => da');
ok(canAddToCart(['stock_status' => 'la_comanda'] + $fix) === true,                'cos: la comanda => da');
ok(canAddToCart(['stock_status' => 'indisponibil'] + $fix) === false,             'cos: indisponibil => nu');
ok(canAddToCart(['price_is_from' => '1'] + $fix) === false,                       'cos: pret „de la" venit ca text din PDO => nu');
ok(canAddToCart(['price' => null] + $fix) === false,                              'cos: fara pret => nu');
ok(canAddToCart(['price' => '0.00'] + $fix) === false,                            'cos: pret 0 => nu');
$btn = cartButton($fix);
ok(str_contains($btn, 'data-cart-id="7"'),                                        'buton: data-cart-id');
ok(str_contains($btn, 'data-cart-name="Burlan &quot;90&quot; &lt;mm&gt;"'),       'buton: numele e escapat in atribut');
ok(str_contains($btn, 'type="button"'),                                            'buton: type="button" (nu trimite formulare)');
ok(cartButton(['id' => null] + $fix) === '',                                       'buton: lipseste fara id');

// ── waLink() ──
ok(waLink('Produs X', 'Mesaj scris de mana.') === 'https://wa.me/40749130565?text=' . rawurlencode('Mesaj scris de mana.'), 'waLink: mesajul propriu');
ok(waLink('Produs X', null) === 'https://wa.me/40749130565?text=' . rawurlencode('Buna ziua! Sunt interesat de: Produs X. Puteti confirma disponibilitatea?'), 'waLink: sablonul cand nu exista mesaj propriu');
ok(waLink('Produs X', '') === waLink('Produs X', null),                           'waLink: mesaj gol => sablonul');

// ── panouGenerat() ──
ok(panouGenerat('Teava') === panouGenerat('Teava'),                               'panou: acelasi nume => acelasi panou');
ok(panouGenerat('Robinet') !== panouGenerat('Teava PPR 20mm'),                    'panou: nume diferite => panouri diferite');
ok(str_contains(panouGenerat('X'), 'class="v-gen"') && str_contains(panouGenerat('X'), 'aria-hidden="true"'), 'panou: .v-gen, decorativ');

// ── renderCatalogCards() — catalogul nou ──
$p = ['id' => 12, 'slug' => 'adeziv-ceresit-cm-11', 'name' => 'Adeziv <Ceresit> "CM 11"', 'short_description' => 'Descriere & detalii',
      'image_path' => null, 'price' => '56.83', 'price_unit' => 'sac', 'price_is_from' => 0, 'stock_status' => 'in_stoc',
      'category_name' => 'Adezivi gresie si faianta', 'wa_text' => null];
$h = renderCatalogCards([$p]);
ok(str_contains($h, 'class="prod-card cat-card" data-product-id="12"'),           'card: containerul si id-ul produsului');
ok(substr_count($h, 'href="/produs/adeziv-ceresit-cm-11.html"') === 2,             'card: imaginea si numele duc la pagina produsului');
ok(str_contains($h, 'Adeziv &lt;Ceresit&gt; &quot;CM 11&quot;') && !str_contains($h, '<Ceresit>'), 'card: numele e escapat');
ok(str_contains($h, '<div class="prod-cat">Adezivi gresie si faianta</div>'),     'card: categoria');
ok(str_contains($h, '<div class="prod-price">56,83 lei / sac</div>'),             'card: pretul cu unitate');
ok(str_contains($h, 'class="btn-cart"') && !str_contains($h, 'btn-wa-prod'),      'card cu pret: buton de cos, fara WhatsApp');
ok(str_contains($h, 'class="v-gen"') && !str_contains($h, '<img'),                'card fara poza: panoul generat, fara <img>');
ok(str_contains($h, 'tabindex="-1" aria-hidden="true"'),                          'card: link-ul imaginii nu dubleaza link-ul numelui pentru tastatura/cititoare');
$h2 = renderCatalogCards([['price' => null, 'image_path' => 'uploads/products/a.jpg'] + $p]);
ok(str_contains($h2, 'Pret la cerere') && !str_contains($h2, 'btn-cart'),         'card fara pret: „Pret la cerere", fara cos');
ok(str_contains($h2, 'class="btn-wa-prod"') && str_contains($h2, 'wa.me/40749130565'), 'card fara pret: buton „Cere oferta" pe WhatsApp');
ok(str_contains($h2, 'src="/uploads/products/a.jpg"') && str_contains($h2, 'loading="lazy"'), 'card cu poza: <img> cu cale absoluta, lazy');
$h3 = renderCatalogCards([['stock_status' => 'indisponibil'] + $p]);
ok(str_contains($h3, 'stock--indisponibil') && !str_contains($h3, 'btn-cart'),    'card indisponibil: eticheta, fara cos');
ok(renderCatalogCards([]) === '',                                                  'lista goala => string gol');
ok(productUrl(['slug' => 'a b']) === '/produs/a%20b.html',                         'productUrl: slug-ul e codificat');

// ── mesajul pentru lista goala ──
ok(str_contains(renderEmptyNotice(), 'wa.me/40749130565'),                        'mesajul gol trimite pe WhatsApp');

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
