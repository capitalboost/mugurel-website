<?php
// Credentiale reale in config.local.php (negituit, editat manual pe server)
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
} else {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'mugurel_cms');
    define('DB_USER', 'SCHIMBA_USER');
    define('DB_PASS', 'SCHIMBA_PAROLA');
}

define('UPLOAD_MAX_BYTES', 2 * 1024 * 1024); // 2MB
define('UPLOAD_DIR',  __DIR__ . '/../uploads/products/');
define('UPLOAD_WEB',  '/uploads/products/');

define('ADMIN_TITLE', 'Admin — Mugurel');
define('SITE_URL',    'https://mugurel-bricolaj.ro');

define('SESSION_TIMEOUT_IDLE',     7200);  // 2h inactivitate
define('SESSION_TIMEOUT_ABSOLUTE', 28800); // 8h absolute
define('LOGIN_MAX_ATTEMPTS',       5);
define('LOGIN_WINDOW_MINUTES',     15);

// Comenzi online (T10). Valorile pot fi suprascrise in config.local.php (ex. local, pentru teste).
if (!defined('ORDER_RATE_MAX'))        { define('ORDER_RATE_MAX', 10); }        // trimiteri de comanda...
if (!defined('ORDER_RATE_WINDOW_SEC')) { define('ORDER_RATE_WINDOW_SEC', 600); } // ...per IP, in 10 minute
if (!defined('ORDER_MIN_FILL_SEC'))    { define('ORDER_MIN_FILL_SEC', 3); }      // formular trimis mai repede => robot

// Emailuri (T12/T13). IMPLICIT = mediu de test: SMTP local (Mailpit) si nicio adresa @mugurel-bricolaj.ro.
// Pe server, config.local.php trebuie sa defineasca APP_ENV = 'production', SMTP-ul real si ORDER_NOTIFY_TO.
if (!defined('APP_ENV'))         { define('APP_ENV', 'local'); }
if (!defined('ORDER_NOTIFY_TO')) { define('ORDER_NOTIFY_TO', 'lucian@capitalboost.ro'); } // cine primeste comenzile
if (!defined('MAIL_FROM'))       { define('MAIL_FROM', 'comenzi@mugurel-bricolaj.ro'); }
if (!defined('MAIL_FROM_NAME'))  { define('MAIL_FROM_NAME', 'Mugurel Materiale Constructii'); }
if (!defined('SMTP_HOST'))       { define('SMTP_HOST', '127.0.0.1'); }
if (!defined('SMTP_PORT'))       { define('SMTP_PORT', 1025); }
if (!defined('SMTP_USER'))       { define('SMTP_USER', ''); }
if (!defined('SMTP_PASS'))       { define('SMTP_PASS', ''); }
if (!defined('SMTP_SECURE'))     { define('SMTP_SECURE', ''); } // '', 'tls' sau 'ssl'
