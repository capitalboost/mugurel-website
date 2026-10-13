<?php
/**
 * Protectiile formularului de comanda: sesiune proprie, token CSRF, capcana anti-robot,
 * limitarea pe IP (cu IP-ul real din spatele Cloudflare).
 *
 * Sesiunea are alt nume decat cea din /admin, ca cele doua sa nu se amestece.
 */
require_once __DIR__ . '/../admin/config.php';

const ORDER_SESSION_NAME = 'mug_comanda';
const ORDER_HONEYPOT     = 'website'; // camp invizibil; un om nu il completeaza

/**
 * Intervalele de IP ale Cloudflare (https://www.cloudflare.com/ips/, verificate 2026-10-13).
 * Doar cand cererea vine de la una dintre ele avem incredere in antetul CF-Connecting-IP.
 * Lista se schimba rar; de reverificat la lansare (IMPLEMENTATION_PROGRESS.md, T16).
 */
const CLOUDFLARE_RANGES = [
    '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
    '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
    '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
    '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
    '2a06:98c0::/29', '2c0f:f248::/32',
];

function orderIsHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function orderSessionStart(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name(ORDER_SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => orderIsHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

/** Tokenul CSRF al sesiunii (creat la prima cerere). */
function orderCsrfToken(): string
{
    orderSessionStart();
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function orderCsrfValid(mixed $token): bool
{
    orderSessionStart();
    return is_string($token) && is_string($_SESSION['csrf'] ?? null) && $token !== ''
        && hash_equals($_SESSION['csrf'], $token);
}

/** Momentul afisarii formularului — pentru capcana „trimis prea repede". */
function orderMarkFormShown(): void
{
    orderSessionStart();
    $_SESSION['form_shown_at'] = time();
}

/**
 * Pare trimis de un robot? Campul invizibil completat sau formular trimis in mai putin de
 * ORDER_MIN_FILL_SEC secunde de la afisare.
 */
function orderLooksLikeBot(array $post, int $minSec = ORDER_MIN_FILL_SEC): bool
{
    orderSessionStart();
    $honeypot = $post[ORDER_HONEYPOT] ?? '';
    if (!is_string($honeypot) || trim($honeypot) !== '') {
        return true;
    }
    $shown = $_SESSION['form_shown_at'] ?? null;
    return !is_int($shown) || (time() - $shown) < $minSec;
}

/** E adresa IP in intervalul CIDR dat? Functioneaza pentru IPv4 si IPv6. */
function ipInCidr(string $ip, string $cidr): bool
{
    [$net, $bits] = explode('/', $cidr) + [1 => null];
    $ipBin = @inet_pton($ip);
    $netBin = @inet_pton($net);
    if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) {
        return false;
    }
    $bits = $bits === null ? strlen($ipBin) * 8 : (int) $bits;
    $bytes = intdiv($bits, 8);
    if (substr($ipBin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
        return false;
    }
    $rest = $bits % 8;
    if ($rest === 0) {
        return true;
    }
    $mask = chr((0xFF << (8 - $rest)) & 0xFF);
    return ($ipBin[$bytes] & $mask) === ($netBin[$bytes] & $mask);
}

/**
 * IP-ul real al vizitatorului. In spatele Cloudflare, REMOTE_ADDR e serverul Cloudflare;
 * IP-ul clientului vine in CF-Connecting-IP — dar il acceptam DOAR daca cererea chiar vine
 * de la Cloudflare (altfel oricine l-ar putea falsifica).
 */
function orderClientIp(array $server): string
{
    $remote = (string) ($server['REMOTE_ADDR'] ?? '0.0.0.0');
    $cf = (string) ($server['HTTP_CF_CONNECTING_IP'] ?? '');
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) {
        foreach (CLOUDFLARE_RANGES as $range) {
            if (ipInCidr($remote, $range)) {
                return $cf;
            }
        }
    }
    return $remote;
}

/**
 * Inregistreaza o incercare si spune daca IP-ul a depasit limita in fereastra curenta.
 * La eroare de baza nu blocam clientul (comanda e mai importanta decat limitarea).
 */
function orderRateLimited(PDO $pdo, string $ip, int $max = ORDER_RATE_MAX, int $windowSec = ORDER_RATE_WINDOW_SEC): bool
{
    try {
        $pdo->prepare('DELETE FROM order_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)')->execute([$windowSec]);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM order_attempts WHERE ip = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)');
        $stmt->execute([$ip, $windowSec]);
        $count = (int) $stmt->fetchColumn();
        $pdo->prepare('INSERT INTO order_attempts (ip) VALUES (?)')->execute([$ip]);
        return $count >= $max;
    } catch (Throwable $e) {
        error_log('orderRateLimited: ' . $e->getMessage());
        return false;
    }
}
