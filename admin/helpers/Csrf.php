<?php
class Csrf {
    public static function token(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function input(): string {
        return '<input type="hidden" name="csrf_token" value="'
             . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function verify(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        $submitted = $_POST['csrf_token'] ?? '';
        $stored    = $_SESSION['csrf_token'] ?? '';
        if (!$stored || !hash_equals($stored, $submitted)) {
            http_response_code(403);
            die('CSRF token invalid.');
        }
        // Rotire token după POST reușit
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}
