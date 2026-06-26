<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'mugurel_cms');
define('DB_USER', 'mugurel_db');
define('DB_PASS', 'SCHIMBA_PAROLA_AICI');

define('UPLOAD_MAX_BYTES', 2 * 1024 * 1024); // 2MB
define('UPLOAD_DIR',  __DIR__ . '/../uploads/products/');
define('UPLOAD_WEB',  '/uploads/products/');

define('ADMIN_TITLE', 'Admin — Mugurel');
define('SITE_URL',    'https://mugurel-bricolaj.ro');

define('SESSION_TIMEOUT_IDLE',     7200);  // 2h inactivitate
define('SESSION_TIMEOUT_ABSOLUTE', 28800); // 8h absolute
define('LOGIN_MAX_ATTEMPTS',       5);
define('LOGIN_WINDOW_MINUTES',     15);
