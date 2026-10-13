<?php
/**
 * Router pentru serverul PHP incorporat — DOAR mediu local.
 *
 *   php -S localhost:8080 -t Website Website/dev/router_local.php
 *
 * Reproduce regulile din .htaccess (radacina si admin/) si trimite aceleasi
 * header-e de securitate, citite din .htaccess la fiecare cerere, ca o problema
 * de CSP sa se vada local, nu abia pe live. Directorul dev/ nu e copiat de
 * .cpanel.yml, deci fisierul nu ajunge niciodata pe server.
 */

$root = dirname(__DIR__);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

if (str_contains($path, '..')) {
    http_response_code(400);
    exit;
}

/** Header-ele `Header always set` dintr-un .htaccess, fara HSTS (local e http). */
function htaccessHeaders(string $file): array
{
    $out = [];
    if (!is_file($file)) {
        return $out;
    }
    preg_match_all('/^\s*Header always set ([\w-]+) "([^"]*)"/m', file_get_contents($file), $m, PREG_SET_ORDER);
    foreach ($m as [, $name, $value]) {
        if (strcasecmp($name, 'Strict-Transport-Security') !== 0) {
            $out[$name] = $value;
        }
    }
    return $out;
}

function sendHeaders(array $headers): void
{
    foreach ($headers as $name => $value) {
        header("$name: $value");
    }
}

/** Ruleaza un script PHP ca si cum ar fi fost cerut direct. */
function runPhp(string $file): void
{
    $_SERVER['SCRIPT_FILENAME'] = $file;
    $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/' . ltrim(str_replace('\\', '/', substr($file, strlen(dirname(__DIR__)))), '/');
    chdir(dirname($file));
    require $file;
}

function serveHtml(string $file): void
{
    header('Content-Type: text/html; charset=UTF-8');
    readfile($file);
}

// ── admin/ ────────────────────────────────────────────────────────────────
if (preg_match('#^/admin(/|$)#', $path)) {
    sendHeaders(htaccessHeaders("$root/admin/.htaccess"));
    if (preg_match('#^/admin/(models|helpers|views|controllers|tests)/#', $path)
        || preg_match('/\.(sql|log|env|ini|json|md)$/i', $path)) {
        http_response_code(403);
        exit('403');
    }
    $file = $root . $path;
    if (is_dir($file)) {
        $file = rtrim($file, '/') . '/index.php';
    }
    if (is_file($file) && str_ends_with($file, '.php')) {
        runPhp($file);
        return true;
    }
    return false;
}

sendHeaders(htaccessHeaders("$root/.htaccess"));

// /slug.html serveste slug.php cand exista
if (preg_match('#^/(.+)\.html$#', $path, $m) && is_file("$root/{$m[1]}.php")) {
    runPhp("$root/{$m[1]}.php");
    return true;
}

// Fisiere si directoare reale
$file = $root . $path;
if (is_dir($file)) {
    foreach (['index.php', 'index.html'] as $index) {
        if (is_file(rtrim($file, '/') . "/$index")) {
            $file = rtrim($file, '/') . "/$index";
            break;
        }
    }
}
if (is_file($file)) {
    if (str_ends_with($file, '.php')) {
        runPhp($file);
        return true;
    }
    if (str_ends_with($file, '.html')) {
        serveHtml($file);
        return true;
    }
    return false; // css, js, img: serverul incorporat le serveste singur
}

// /slug.html, /slug.htm, /slug/ sau /slug → router.php
if (preg_match('#^/([a-z0-9][a-z0-9-]*)(\.html?|/)?$#', $path, $m)) {
    $_GET['slug'] = $m[1];
    runPhp("$root/router.php");
    return true;
}

http_response_code(404);
echo '404';
return true;
