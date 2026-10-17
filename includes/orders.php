<?php
/**
 * Inregistrarea comenzilor (T11).
 *
 * orderCreate() scrie comanda si produsele ei intr-o singura tranzactie: ori tot, ori nimic.
 * Comenzile duble sunt prevenite pe doua niveluri:
 *   1. form_token (UNIQUE) — acelasi formular trimis de doua ori (dublu-click, retrimitere) => aceeasi comanda;
 *   2. content_hash in ultimele 10 minute — aceleasi date si acelasi cos trimise dintr-un formular nou
 *      (ex. „Inapoi" in browser + „Trimite" din nou) => aceeasi comanda.
 * In ambele cazuri se intoarce comanda existenta, marcata 'duplicate' => true.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/product_grid.php'; // formatQty() pentru mesajul de stoc

const ORDER_DUPLICATE_WINDOW_SEC = 600;

/** Token aleator pentru formular (campul ascuns „idem"). */
function orderNewFormToken(): string
{
    return bin2hex(random_bytes(16));
}

function orderFormTokenValid(mixed $t): bool
{
    return is_string($t) && preg_match('/^[0-9a-f]{32}$/', $t) === 1;
}

/** Amprenta continutului: clientul + livrarea + produsele si cantitatile (nu ordinea lor). */
function orderContentHash(array $data, array $lines): string
{
    $items = array_map(fn ($l) => [(int) $l['id'], (string) $l['qty']], $lines);
    sort($items);
    $who = [$data['tip'], $data['nume'], $data['telefon'], $data['email'], $data['plata'], $data['judet'],
            $data['localitate'], $data['adresa'], $data['firma'], $data['cui']];
    return hash('sha256', json_encode([$who, $items], JSON_UNESCAPED_UNICODE));
}

/** Numarul comenzii, din id: MUG-2026-000042. */
function orderNumber(int $id, ?int $year = null): string
{
    return sprintf('MUG-%d-%06d', $year ?? (int) date('Y'), $id);
}

function orderFindExisting(PDO $pdo, string $formToken, string $contentHash): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, order_number, public_token FROM orders
         WHERE form_token = ? OR (content_hash = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND))
         ORDER BY id LIMIT 1'
    );
    $stmt->execute([$formToken, $contentHash, ORDER_DUPLICATE_WINDOW_SEC]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** Cat pastram IP-ul si browserul din comenzi (Politica de confidentialitate, sectiunea „Cat pastram datele"). */
const ORDER_TECH_RETENTION_MONTHS = 6;

/**
 * Sterge IP-ul si browserul din comenzile mai vechi de ORDER_TECH_RETENTION_MONTHS luni. Rulata la fiecare comanda
 * noua (o interogare pe index, fara cron). O eroare aici nu opreste comanda.
 */
function orderPurgeTechnicalData(PDO $pdo): int
{
    try {
        return (int) $pdo->exec("UPDATE orders SET ip = '', user_agent = ''
                                 WHERE created_at < DATE_SUB(NOW(), INTERVAL " . ORDER_TECH_RETENTION_MONTHS . " MONTH)
                                   AND (ip <> '' OR user_agent <> '')");
    } catch (Throwable $e) {
        error_log('orderPurgeTechnicalData: ' . $e->getMessage());
        return 0;
    }
}

/** Stocul numarat nu mai ajunge pentru comanda (alt client a comandat intre timp). Nimic nu s-a salvat. */
class OrderStockException extends RuntimeException {}

/**
 * Scade stocul produselor cu stoc numarat (stock_qty nu e NULL), in tranzactia comenzii. Randurile se blocheaza
 * (FOR UPDATE), ca doua comenzi simultane sa nu vanda aceeasi bucata de doua ori. La 0 => „Indisponibil".
 * @throws OrderStockException daca un produs nu mai are destul stoc
 */
function orderReserveStock(PDO $pdo, array $lines): void
{
    $lock = $pdo->prepare('SELECT stock_qty FROM products WHERE id = ? FOR UPDATE');
    $take = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ?,
                                  stock_status = IF(stock_qty <= 0, 'indisponibil', stock_status) WHERE id = ?");
    usort($lines, fn ($a, $b) => $a['id'] <=> $b['id']); // aceeasi ordine la toate comenzile => fara blocaj intre doua comenzi simultane
    foreach ($lines as $l) {
        $lock->execute([$l['id']]);
        $qty = $lock->fetchColumn();
        if ($qty === false || $qty === null) { continue; } // stocul acestui produs nu se numara
        if ((float) $qty + 0.0001 < (float) $l['qty']) {
            throw new OrderStockException($l['name'] . ': mai sunt doar ' . formatQty((float) $qty) . ' pe stoc.');
        }
        $take->execute([$l['qty'], $l['id']]); // MySQL aplica SET in ordine: IF vede deja cantitatea noua
    }
}

/**
 * Inregistreaza comanda. $data = orderFormValidate()['data'] (fara erori), $cart = priceCart() fara probleme.
 * @return array{id:int, number:string, token:string, duplicate:bool}
 * @throws Throwable la orice eroare de baza (dupa rollback) — apelantul afiseaza un mesaj.
 */
function orderCreate(PDO $pdo, array $data, array $cart, string $formToken, string $ip = '', string $userAgent = ''): array
{
    $lines = array_values(array_filter($cart['lines'], fn ($l) => $l['orderable']));
    if ($lines === [] || $cart['problem_count'] > 0) {
        throw new InvalidArgumentException('Coșul nu poate fi comandat.');
    }
    $hash = orderContentHash($data, $lines);
    orderPurgeTechnicalData($pdo);

    $pdo->beginTransaction();
    try {
        if (($existing = orderFindExisting($pdo, $formToken, $hash)) !== null) {
            $pdo->commit();
            return ['id' => (int) $existing['id'], 'number' => $existing['order_number'],
                    'token' => $existing['public_token'], 'duplicate' => true];
        }

        orderReserveStock($pdo, $lines);
        $token = bin2hex(random_bytes(24));
        $pdo->prepare(
            'INSERT INTO orders (public_token, form_token, content_hash, tip, nume, telefon, email, plata,
                                 judet, localitate, adresa, mentiuni, firma, cui, reg_com, sediu,
                                 total_bani, ip, user_agent)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $token, $formToken, $hash, $data['tip'], $data['nume'], $data['telefon'], $data['email'], $data['plata'],
            $data['judet'], $data['localitate'], $data['adresa'], $data['mentiuni'] !== '' ? $data['mentiuni'] : null,
            $data['firma'], $data['cui'], $data['reg_com'], $data['sediu'],
            $cart['total_bani'], mb_substr($ip, 0, 45), mb_substr($userAgent, 0, 255),
        ]);
        $id = (int) $pdo->lastInsertId();
        $number = orderNumber($id);
        $pdo->prepare('UPDATE orders SET order_number = ? WHERE id = ?')->execute([$number, $id]);

        $item = $pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, name, unit, qty, price_bani, line_bani, stock_status)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        foreach ($lines as $l) {
            $item->execute([$id, $l['id'], $l['name'], $l['unit'], $l['qty'], $l['price_bani'], $l['line_bani'], $l['stock']]);
        }

        $pdo->commit();
        return ['id' => $id, 'number' => $number, 'token' => $token, 'duplicate' => false];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Doua trimiteri simultane cu acelasi formular: a doua loveste UNIQUE(form_token) => intoarcem prima.
        if ($e instanceof PDOException && $e->getCode() === '23000'
            && ($existing = orderFindExisting($pdo, $formToken, $hash)) !== null) {
            return ['id' => (int) $existing['id'], 'number' => $existing['order_number'],
                    'token' => $existing['public_token'], 'duplicate' => true];
        }
        throw $e;
    }
}

/** Comanda cu produsele ei, dupa tokenul public. Null daca nu exista. */
function orderByToken(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[0-9a-f]{48}$/', $token)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE public_token = ?');
    $stmt->execute([$token]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        return null;
    }
    $items = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
    $items->execute([$order['id']]);
    $order['items'] = $items->fetchAll(PDO::FETCH_ASSOC);
    return $order;
}
