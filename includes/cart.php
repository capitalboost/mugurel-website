<?php
/**
 * Cosul pe server: recitirea produselor din baza si calculul totalurilor.
 *
 * Browserul trimite doar {id, qty}. Tot ce conteaza pentru bani (nume, pret, stoc,
 * daca produsul se mai poate comanda) se citeste AICI, din baza. Aceeasi functie
 * priceCart() e folosita de pagina cosului si de inregistrarea comenzii.
 *
 * Sumele se calculeaza in bani (intregi), ca sa nu apara erori de rotunjire.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/product_grid.php';

const CART_MAX_LINES = 100;
const CART_MAX_QTY   = 99999;

/** Unitatile la care se poate comanda cantitate cu zecimale (ex. 12,5 m²). */
const CART_DECIMAL_UNITS = ['m²', 'm2', 'mp', 'ml', 'm', 'kg', 'l', 'm³', 'm3', 'mc', 'to', 't'];

function cartAllowsDecimals(?string $unit): bool
{
    return in_array(mb_strtolower(trim((string) $unit)), CART_DECIMAL_UNITS, true);
}

/**
 * Normalizeaza ce vine din browser: lista de {id, qty} valide, fara dubluri, maximum 100.
 * Accepta atat JSON decodat (array de array-uri) cat si date de formular.
 * @return list<array{id:int, qty:float}>
 */
function cartNormalizeItems(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $it) {
        if (!is_array($it) || count($out) >= CART_MAX_LINES) {
            continue;
        }
        $id = filter_var($it['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $qtyRaw = is_string($it['qty'] ?? null) ? str_replace(',', '.', $it['qty']) : ($it['qty'] ?? null);
        $qty = is_numeric($qtyRaw) ? round((float) $qtyRaw, 2) : null;
        if ($id === false || $qty === null || $qty <= 0 || isset($out[$id])) {
            continue;
        }
        $out[$id] = ['id' => $id, 'qty' => min($qty, (float) CART_MAX_QTY)];
    }
    return array_values($out);
}

/**
 * Produsele din cos, citite din baza. Produsele ascunse nu sunt intoarse (= „nu mai exista").
 * Intoarce NULL la eroare de baza — spre deosebire de products_repo.php, aici o lista goala
 * ar insemna „produsele nu mai exista", ceea ce ar fi fals; apelantul afiseaza o eroare.
 * @param list<int> $ids
 * @return array<int, array>|null indexat dupa id
 */
function cartProducts(array $ids): ?array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($i) => $i > 0)));
    if ($ids === []) {
        return [];
    }
    try {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::get()->prepare(
            "SELECT id, slug, name, price, price_unit, price_is_from, stock_status, image_path, image_alt, page_slug
             FROM products WHERE is_visible = 1 AND id IN ($in)"
        );
        $stmt->execute($ids);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int) $row['id']] = $row;
        }
        return $out;
    } catch (Throwable $e) {
        error_log('cartProducts: ' . $e->getMessage());
        return null;
    }
}

/** Suma in bani => text „1.234,50 lei". */
function formatLei(int $bani): string
{
    return number_format($bani / 100, 2, ',', '.') . ' lei';
}

/**
 * Calculeaza cosul. Fiecare rand e fie comandabil (cu pret si subtotal), fie are un motiv
 * pentru care nu se poate comanda. Totalul include doar randurile comandabile.
 *
 * @param list<array{id:int, qty:float}> $items  (din cartNormalizeItems)
 * @param array<int, array> $products            (din cartProducts)
 * @return array{lines: list<array>, total_bani: int, orderable_count: int, problem_count: int}
 */
function priceCart(array $items, array $products): array
{
    $lines = [];
    $total = 0;
    $orderable = 0;
    foreach ($items as $it) {
        $p = $products[$it['id']] ?? null;
        // Fiecare rand are aceleasi chei, oricare ar fi situatia lui.
        $line = [
            'id' => $it['id'], 'qty' => $it['qty'], 'orderable' => false, 'problem' => null,
            'name' => null, 'unit' => null, 'decimals' => false, 'stock' => null, 'stock_label' => null,
            'image' => null, 'url' => null, 'price_bani' => null, 'line_bani' => null,
        ];

        if ($p === null) {
            $line['problem'] = 'Produsul nu mai este disponibil pe site.';
            $lines[] = $line;
            continue;
        }

        $line = array_merge($line, [
            'name'        => $p['name'],
            'unit'        => $p['price_unit'] ?: null,
            'decimals'    => cartAllowsDecimals($p['price_unit'] ?? null),
            'stock'       => $p['stock_status'] ?? 'in_stoc',
            'stock_label' => STOCK_LABELS[$p['stock_status'] ?? ''] ?? null,
            'image'       => $p['image_path'] ?: null,
            'url'         => !empty($p['slug']) ? '/produs/' . rawurlencode($p['slug']) . '.html' : null, // pagina produsului
        ]);

        if (!canAddToCart($p)) {
            $line['problem'] = ($p['stock_status'] ?? '') === 'indisponibil'
                ? 'Produsul nu mai este in stoc.'
                : 'Pretul acestui produs se stabileste la cerere — te contactam pentru oferta.';
        } elseif (!$line['decimals'] && floor($it['qty']) != $it['qty']) {
            $line['problem'] = 'Cantitatea trebuie sa fie un numar intreg.';
        } else {
            $priceBani = (int) round((float) $p['price'] * 100);
            $line['price_bani'] = $priceBani;
            $line['line_bani'] = (int) round($priceBani * $it['qty']);
            $line['orderable'] = true;
            $total += $line['line_bani'];
            $orderable++;
        }
        $lines[] = $line;
    }

    return [
        'lines'           => $lines,
        'total_bani'      => $total,
        'orderable_count' => $orderable,
        'problem_count'   => count($lines) - $orderable,
    ];
}
