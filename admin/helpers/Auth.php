<?php
class Auth {
    public static function startSecureSession(): void {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure',   '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        session_start();
    }

    public static function login(array $user): void {
        session_regenerate_id(true);
        $_SESSION['user_id']    = (int)$user['id'];
        $_SESSION['role']       = $user['role'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['ip']         = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['ua']         = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['created_at'] = time();
        $_SESSION['last_active'] = time();
    }

    public static function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function isLoggedIn(): bool {
        if (empty($_SESSION['user_id'])) return false;
        require_once __DIR__ . '/../config.php';
        $now = time();
        if ($now - ($_SESSION['last_active'] ?? 0) > SESSION_TIMEOUT_IDLE) {
            self::logout(); return false;
        }
        if ($now - ($_SESSION['created_at'] ?? 0) > SESSION_TIMEOUT_ABSOLUTE) {
            self::logout(); return false;
        }
        if (($_SESSION['ip'] ?? '') !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
            self::logout(); return false;
        }
        $_SESSION['last_active'] = $now;
        return true;
    }

    public static function requireLogin(): void {
        if (!self::isLoggedIn()) {
            header('Location: /admin/?page=login');
            exit;
        }
    }

    public static function requireRole(string $role): void {
        self::requireLogin();
        if ($_SESSION['role'] !== $role) {
            http_response_code(403);
            die('Acces interzis.');
        }
    }

    public static function hasRole(string $role): bool {
        return ($_SESSION['role'] ?? '') === $role;
    }

    public static function id(): int   { return (int)($_SESSION['user_id'] ?? 0); }
    public static function role(): string { return $_SESSION['role'] ?? ''; }
    public static function username(): string { return $_SESSION['username'] ?? ''; }
}
