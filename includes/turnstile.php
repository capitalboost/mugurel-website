<?php
/**
 * Cloudflare Turnstile — captcha pe formularul de comanda si pe cel de retur (faza 4.11 din PLAN-REMEDIERE).
 * Al patrulea strat, peste campul-capcana, timpul minim de completare si limita pe IP (order_security.php).
 *
 * Pe live: cheia publica e TURNSTILE_SITE_KEY (admin/config.php), cheia secreta TURNSTILE_SECRET sta DOAR in
 * admin/config.local.php de pe server (niciodata in git). Local: cheile de test Cloudflare, care trec mereu;
 * serverul recunoaste raspunsul lor fara sa iasa pe internet, ca testele sa nu depinda de Cloudflare.
 *
 * Daca Cloudflare nu raspunde sau cheia secreta lipseste pe live, formularul trece (si se noteaza in log):
 * o pana la Cloudflare nu trebuie sa opreasca vanzarile, iar celelalte trei protectii raman.
 */
require_once __DIR__ . '/../admin/config.php';

const TURNSTILE_TEST_SITE_KEY = '1x00000000000000000000AA';            // cheile publice de test Cloudflare: trec mereu
const TURNSTILE_TEST_SECRET   = '1x0000000000000000000000000000000AA';
const TURNSTILE_TEST_TOKEN    = 'XXXX.DUMMY.TOKEN.XXXX';               // raspunsul widgetului cu cheia de test
const TURNSTILE_VERIFY_URL    = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
const TURNSTILE_SCRIPT        = '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';

function turnstileSiteKey(): string
{
    return APP_ENV === 'production' ? TURNSTILE_SITE_KEY : TURNSTILE_TEST_SITE_KEY;
}

function turnstileSecret(): ?string
{
    if (APP_ENV !== 'production') { return TURNSTILE_TEST_SECRET; }
    return defined('TURNSTILE_SECRET') && TURNSTILE_SECRET !== '' ? TURNSTILE_SECRET : null;
}

/** Caseta „nu sunt robot", pusa in formular inainte de buton (completeaza singura campul cf-turnstile-response). */
function turnstileWidget(): string
{
    return '<div class="cf-turnstile form-captcha" data-sitekey="' . htmlspecialchars(turnstileSiteKey(), ENT_QUOTES, 'UTF-8')
        . '" data-language="ro" data-theme="light" data-size="flexible"></div>';
}

/**
 * Verifica raspunsul widgetului la Cloudflare. @param mixed $token $_POST['cf-turnstile-response']
 * @return bool false doar cand Cloudflare spune clar ca nu e un om (sau raspunsul lipseste)
 */
function turnstileVerify($token, string $ip = ''): bool
{
    $secret = turnstileSecret();
    if ($secret === null) {
        error_log('turnstile: TURNSTILE_SECRET lipsește din config.local.php — captcha neverificat');
        return true;
    }
    if (!is_string($token) || $token === '' || strlen($token) > 2048) { return false; }
    if ($secret === TURNSTILE_TEST_SECRET) { return $token === TURNSTILE_TEST_TOKEN; }

    $body = http_build_query(['secret' => $secret, 'response' => $token] + ($ip !== '' ? ['remoteip' => $ip] : []));
    $raw = false;
    if (function_exists('curl_init')) {
        $ch = curl_init(TURNSTILE_VERIFY_URL);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 6]);
        $raw = curl_exec($ch);
        curl_close($ch);
    } else {
        $raw = @file_get_contents(TURNSTILE_VERIFY_URL, false, stream_context_create(['http' => [
            'method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $body, 'timeout' => 6]]));
    }
    $res = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($res) || !array_key_exists('success', $res)) {
        error_log('turnstile: Cloudflare nu a răspuns — formularul trece');
        return true;
    }
    return $res['success'] === true;
}
