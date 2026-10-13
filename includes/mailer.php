<?php
/**
 * Trimiterea emailurilor prin SMTP (PHPMailer, inclus in includes/lib/PHPMailer).
 *
 * Garda de mediu: in afara productiei (APP_ENV !== 'production') nu se trimite NIMIC catre
 * adrese @mugurel-bricolaj.ro — comenzile de test nu ajung la magazin. Local, SMTP-ul e Mailpit,
 * care prinde toate mesajele (nimic nu iese din calculator).
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/lib/PHPMailer/Exception.php';
require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

const MAIL_PROTECTED_DOMAIN = 'mugurel-bricolaj.ro';

/** Poate primi email adresa asta in mediul curent? */
function mailRecipientAllowed(string $address, string $env = APP_ENV): bool
{
    if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $domain = strtolower(substr(strrchr($address, '@'), 1));
    $isShop = $domain === MAIL_PROTECTED_DOMAIN || str_ends_with($domain, '.' . MAIL_PROTECTED_DOMAIN);
    return $env === 'production' || !$isShop;
}

/**
 * Trimite un email HTML + text. Intoarce true daca serverul SMTP l-a acceptat.
 * Nu arunca exceptii: orice problema e scrisa in log si intoarce false.
 * $smtp suprascrie setarile (folosit de teste).
 */
function mailSend(string $to, string $subject, string $html, string $text, ?string $replyTo = null, array $smtp = []): bool
{
    if (!mailRecipientAllowed($to)) {
        error_log("mailSend: destinatar blocat in mediul " . APP_ENV . ": $to");
        return false;
    }
    $cfg = $smtp + ['host' => SMTP_HOST, 'port' => SMTP_PORT, 'user' => SMTP_USER, 'pass' => SMTP_PASS,
                    'secure' => SMTP_SECURE, 'timeout' => 10];
    try {
        $m = new PHPMailer(true);
        $m->isSMTP();
        $m->Host = $cfg['host'];
        $m->Port = (int) $cfg['port'];
        $m->Timeout = (int) $cfg['timeout'];
        $m->SMTPAuth = $cfg['user'] !== '';
        $m->Username = $cfg['user'];
        $m->Password = $cfg['pass'];
        $m->SMTPSecure = $cfg['secure'];
        $m->SMTPAutoTLS = $cfg['secure'] !== '';
        $m->CharSet = PHPMailer::CHARSET_UTF8;
        $m->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $m->addAddress($to);
        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $m->addReplyTo($replyTo);
        }
        $m->Subject = $subject;
        $m->isHTML(true);
        $m->Body = $html;
        $m->AltBody = $text;
        return $m->send();
    } catch (Throwable $e) {
        error_log('mailSend: ' . $e->getMessage());
        return false;
    }
}
