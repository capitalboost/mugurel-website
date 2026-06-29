<?php
class Upload {
    private const ALLOWED = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
    ];

    public static function image(array $file): string {
        require_once __DIR__ . '/../config.php';

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Eroare upload PHP: ' . $file['error']);
        }
        if ($file['size'] > UPLOAD_MAX_BYTES) {
            throw new RuntimeException('Fişierul depăşeşte 2MB.');
        }
        if ($file['size'] < 100) {
            throw new RuntimeException('Fişier suspect (prea mic).');
        }

        $ext = strtolower(pathinfo(basename($file['name']), PATHINFO_EXTENSION));
        if (!array_key_exists($ext, self::ALLOWED)) {
            throw new RuntimeException('Extensie nepermisă: ' . htmlspecialchars($ext));
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!in_array($mime, array_values(self::ALLOWED), true)) {
            throw new RuntimeException('Tip MIME invalid: ' . htmlspecialchars($mime));
        }
        if (self::ALLOWED[$ext] !== $mime) {
            throw new RuntimeException('Extensia nu corespunde cu tipul fişierului.');
        }

        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            throw new RuntimeException('Fişierul nu este o imagine validă.');
        }
        [$w, $h] = $info;
        if ($w < 10 || $h < 10 || $w > 6000 || $h > 6000) {
            throw new RuntimeException("Dimensiuni invalide: {$w}x{$h}px.");
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Upload invalid.');
        }

        $safeName  = bin2hex(random_bytes(16)) . '.' . $ext;
        $targetDir = rtrim(UPLOAD_DIR, '/') . '/';

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $realBase   = realpath(dirname(rtrim(UPLOAD_DIR, '/')));
        $realTarget = realpath($targetDir);
        if ($realTarget === false || strpos($realTarget, $realBase) !== 0) {
            throw new RuntimeException('Director upload invalid.');
        }

        if (!move_uploaded_file($file['tmp_name'], $targetDir . $safeName)) {
            throw new RuntimeException('Nu s-a putut salva fişierul.');
        }

        return UPLOAD_WEB . $safeName;
    }

    public static function delete(string $webPath): void {
        require_once __DIR__ . '/../config.php';
        if (!$webPath || strpos($webPath, UPLOAD_WEB) !== 0) return;
        $rel  = substr($webPath, strlen(UPLOAD_WEB));
        $full = rtrim(UPLOAD_DIR, '/') . '/' . basename($rel);
        if (is_file($full)) unlink($full);
    }
}