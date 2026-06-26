<?php
class Flash {
    public static function set(string $type, string $msg): void {
        $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
    }

    public static function success(string $msg): void { self::set('success', $msg); }
    public static function error(string $msg): void   { self::set('error',   $msg); }

    public static function get(): ?array {
        if (empty($_SESSION['flash'])) return null;
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }

    public static function html(): string {
        $flash = self::get();
        if (!$flash) return '';
        $type = htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8');
        $msg  = htmlspecialchars($flash['msg'],  ENT_QUOTES, 'UTF-8');
        return "<div class=\"flash flash--{$type}\">{$msg}</div>";
    }
}
