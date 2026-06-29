<?php
class Sanitize {
    public static function text(string $input, int $max = 500): string {
        $clean = strip_tags(trim($input));
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $clean);
        return mb_substr($clean, 0, $max, 'UTF-8');
    }

    public static function html(string $input): string {
        $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><a><blockquote>';
        $clean = strip_tags($input, $allowed);
        $clean = preg_replace('/\s+on\w+\s*=\s*"[^"]*"/i', '', $clean);
        $clean = preg_replace("/\s+on\w+\s*=\s*'[^']*'/i", '', $clean);
        $clean = preg_replace_callback('/<a([^>]*)>/i', function($m) {
            $attrs = $m[1];
            if (preg_match('/href\s*=\s*"([^"]*)"/i', $attrs, $hm)) {
                if (!preg_match('/^https?:\/\//i', $hm[1])) {
                    $attrs = str_replace($hm[0], 'href="#"', $attrs);
                }
            }
            $attrs = preg_replace('/\s+(?!href|target)\w[\w-]*\s*=\s*"[^"]*"/i', '', $attrs);
            return '<a' . $attrs . '>';
        }, $clean);
        return preg_replace('/\x00/', '', $clean);
    }

    public static function slug(string $input): string {
        return preg_replace('/[^a-z0-9-]/', '', strtolower(trim($input)));
    }

    public static function posInt(mixed $input): int {
        return max(0, (int)$input);
    }

    public static function price(mixed $input): ?float {
        $v = (float)$input;
        return $v > 0 ? round($v, 2) : null;
    }
}