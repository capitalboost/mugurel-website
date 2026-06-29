<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/models/Database.php';
require_once __DIR__ . '/helpers/Auth.php';
require_once __DIR__ . '/helpers/Csrf.php';
require_once __DIR__ . '/helpers/Flash.php';
require_once __DIR__ . '/helpers/Sanitize.php';

Auth::startSecureSession();

$page   = Sanitize::slug($_GET['page']   ?? 'dashboard');
$action = Sanitize::slug($_GET['action'] ?? 'index');

$public = ['login', 'logout'];
if (!in_array($page, $public, true)) {
    Auth::requireLogin();
}

$routes = [
    'login'     => __DIR__ . '/controllers/AuthController.php',
    'logout'    => __DIR__ . '/controllers/AuthController.php',
    'dashboard' => __DIR__ . '/controllers/DashboardController.php',
    'products'  => __DIR__ . '/controllers/ProductsController.php',
    'pages'     => __DIR__ . '/controllers/PagesController.php',
    'users'     => __DIR__ . '/controllers/UsersController.php',
];

if (!isset($routes[$page])) {
    http_response_code(404); die('Pagina nu există.');
}

require $routes[$page];
