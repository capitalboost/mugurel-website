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
