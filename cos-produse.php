<?php
/**
 * POST /cos-produse.php — recalculeaza cosul din browser cu datele din baza.
 *
 * Intrare (JSON): {"items": [{"id": 3470, "qty": 2}, ...]}   — doar id si cantitate.
 * Iesire (JSON):  randurile cu nume/pret/stoc din baza, subtotaluri si total (vezi priceCart()).
 *
 * Nu modifica nimic (doar citeste), dar accepta doar POST: corpul poate avea pana la 100
 * de randuri si raspunsul nu trebuie pus in cache de Cloudflare.
 */
require_once __DIR__ . '/includes/cart.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function cartJson(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    cartJson(405, ['error' => 'Metodă nepermisă.']);
}

$raw = file_get_contents('php://input', false, null, 0, 20001);
if ($raw === false || strlen($raw) > 20000) {
    cartJson(413, ['error' => 'Coșul trimis e prea mare.']);
}
$data = json_decode($raw, true);
if (!is_array($data) || !array_key_exists('items', $data) || !is_array($data['items'])) {
    cartJson(400, ['error' => 'Format invalid.']);
}

$items = cartNormalizeItems($data['items']);
$products = cartProducts(array_column($items, 'id'));
if ($products === null) {
    cartJson(503, ['error' => 'Coșul nu poate fi verificat acum. Încearcă din nou în câteva minute sau sună la 0749 130 565.']);
}

$cart = priceCart($items, $products);
foreach ($cart['lines'] as &$line) {
    $line['price_label'] = $line['price_bani'] !== null ? formatLei($line['price_bani']) : null;
    $line['line_label'] = $line['line_bani'] !== null ? formatLei($line['line_bani']) : null;
}
unset($line);
$cart['total_label'] = formatLei($cart['total_bani']);

cartJson(200, $cart);
