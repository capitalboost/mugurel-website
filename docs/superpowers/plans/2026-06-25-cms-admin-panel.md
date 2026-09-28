# CMS Admin Panel Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a PHP + MySQL admin panel at `/admin/` for mugurel-bricolaj.ro that lets admins edit product images/descriptions, static page content, legal texts, and manage sub-accounts with editor role.

**Architecture:** Custom PHP 7.4+ without framework or Composer. MySQL via PDO. Single router entry point at `admin/index.php`. Static HTML pages converted to PHP templates served from DB. Admin panel protected by session auth with two roles: `admin` and `editor`.

**Tech Stack:** PHP 7.4+, MySQL/MariaDB, PDO, TinyMCE 6 (CDN), Apache mod_rewrite, cPanel HostGate hosting.

**All paths relative to:** `Website/` (= `public_html/` on server)

---

## Task 1: MySQL Schema + Initial Admin User

**Files:**
- Create: `Website/db/schema.sql`
- Create: `Website/db/seed_admin.php` (CLI script — run once, then delete)

- [ ] **Step 1: Create schema file**

Create `Website/db/schema.sql`:

```sql
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(60)  NOT NULL UNIQUE,
    email         VARCHAR(180) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin','editor') NOT NULL DEFAULT 'editor',
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at DATETIME     NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id         SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug       VARCHAR(80)  NOT NULL UNIQUE,
    name       VARCHAR(120) NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO categories (slug, name, sort_order) VALUES
    ('acoperis','Acoperiș',1),('izolatie','Izolație',2),
    ('gips-carton','Gips Carton',3),('zidarie-bca','Zidărie BCA',4),
    ('gard-imprejmuiri','Gard & Împrejmuiri',5),('electrice','Electrice',6),
    ('incalzire','Încălzire',7),('sanitare','Sanitare',8),
    ('gradina','Grădină',9),('mobilier','Mobilier',10),
    ('electrocasnice','Electrocasnice',11),('scule-unelte','Scule & Unelte',12),
    ('apa-canal','Apă & Canal',13);

CREATE TABLE IF NOT EXISTS products (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id       SMALLINT UNSIGNED NOT NULL,
    name              VARCHAR(200) NOT NULL,
    short_description TEXT         NULL DEFAULT NULL,
    image_path        VARCHAR(500) NULL DEFAULT NULL,
    image_alt         VARCHAR(200) NULL DEFAULT NULL,
    price             DECIMAL(10,2) UNSIGNED NULL DEFAULT NULL,
    price_unit        VARCHAR(40)  NULL DEFAULT NULL,
    is_visible        TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_by        INT UNSIGNED NOT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_cat_sort (category_id, sort_order, is_visible),
    FOREIGN KEY (category_id) REFERENCES categories(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pages (
    id               SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug             VARCHAR(100) NOT NULL UNIQUE,
    title            VARCHAR(200) NOT NULL,
    content          LONGTEXT     NULL DEFAULT NULL,
    meta_title       VARCHAR(160) NULL DEFAULT NULL,
    meta_description VARCHAR(320) NULL DEFAULT NULL,
    restricted       TINYINT(1)   NOT NULL DEFAULT 0,
    is_published     TINYINT(1)   NOT NULL DEFAULT 1,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by       INT UNSIGNED NULL DEFAULT NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO pages (slug, title, restricted) VALUES
    ('despre-noi','Despre Noi',0),
    ('contact','Contact',0),
    ('politica-confidentialitate','Politica de Confidențialitate',1),
    ('termeni-conditii','Termeni și Condiții',1),
    ('politica-retur','Politica de Retur',1);

CREATE TABLE IF NOT EXISTS audit_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL DEFAULT NULL,
    entity_type VARCHAR(50)  NOT NULL,
    entity_id   INT UNSIGNED NULL DEFAULT NULL,
    action      VARCHAR(40)  NOT NULL,
    changes_json JSON        NULL DEFAULT NULL,
    ip_address  VARCHAR(45)  NULL DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_entity (entity_type, entity_id),
    KEY idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip           VARBINARY(16) NOT NULL,
    attempted_at DATETIME NOT NULL,
    INDEX idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 2: Import schema via phpMyAdmin**

In cPanel → phpMyAdmin:
1. Creează baza de date: `mugurel_cms` (charset: utf8mb4, collation: utf8mb4_unicode_ci)
2. Creează user DB: `mugurel_db` cu parolă puternică, grant ALL pe `mugurel_cms`
3. Selectează `mugurel_cms` → tab Import → alege `Website/db/schema.sql` → Execute

Expected: 6 tabele create, 13 rânduri în `categories`, 5 în `pages`.

- [ ] **Step 3: Create seed script for initial admin**

Create `Website/db/seed_admin.php`:

```php
<?php
// Rulează o singură dată din CLI: php Website/db/seed_admin.php
// SAU uploadează temporar în public_html și accesează prin browser, apoi ȘTERGE

define('DB_HOST', 'localhost');
define('DB_NAME', 'mugurel_cms');
define('DB_USER', 'mugurel_db');
define('DB_PASS', 'SCHIMBA_PAROLA_AICI');

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$username = 'mugurel_admin';
$email    = 'admin@mugurel-bricolaj.ro';
$password = 'SETEAZA_PAROLA_ADMIN';
$hash     = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$stmt = $pdo->prepare(
    "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'admin')"
);
$stmt->execute([$username, $email, $hash]);
echo "Admin creat cu ID: " . $pdo->lastInsertId() . "\n";
echo "STERGE ACEST FISIER ACUM.\n";
```

- [ ] **Step 4: Run seed script, verify, delete it**

Uploadează `Website/db/seed_admin.php` temporar în `public_html/` → accesează `https://mugurel-bricolaj.ro/seed_admin.php` → verifică output → șterge fișierul din server și local.

Verificare în phpMyAdmin: `SELECT id, username, role FROM users;` → 1 rând cu role='admin'.

- [ ] **Step 5: Commit**

```
git add Website/db/schema.sql
git commit -m "feat: add MySQL schema for CMS admin panel"
```

(Nu commit `Website/db/seed_admin.php` — conține parole.)

---

## Task 2: Config + Database PDO Singleton

**Files:**
- Create: `admin/config.php`
- Create: `admin/models/Database.php`
- Create: `admin/tests/test_db.php`

- [ ] **Step 1: Create config file**

Create `admin/config.php`:

```php
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
```

- [ ] **Step 2: Create Database.php**

Create `admin/models/Database.php`:

```php
<?php
class Database {
    private static ?PDO $instance = null;

    public static function get(): PDO {
        if (self::$instance === null) {
            require_once __DIR__ . '/../config.php';
            self::$instance = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        }
        return self::$instance;
    }

    private function __construct() {}
    private function __clone() {}
}
```

- [ ] **Step 3: Create CLI test**

Create `admin/tests/test_db.php`:

```php
<?php
require_once __DIR__ . '/../models/Database.php';

$pass = 0; $fail = 0;

function ok(bool $cond, string $msg): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ✓ $msg\n"; }
    else        { $fail++; echo "  ✗ $msg\n"; }
}

// Test: conexiune reușită
try {
    $db = Database::get();
    ok($db instanceof PDO, 'PDO instance returnat');
} catch (Exception $e) {
    ok(false, 'Conexiune DB: ' . $e->getMessage());
}

// Test: query simplu pe categories
$stmt = Database::get()->query('SELECT COUNT(*) FROM categories');
$count = (int)$stmt->fetchColumn();
ok($count === 13, "13 categorii în DB (got $count)");

// Test: singleton — aceeași instanță
ok(Database::get() === Database::get(), 'Singleton returnează aceeași instanță');

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
```

- [ ] **Step 4: Run test**

```
php admin/tests/test_db.php
```

Expected:
```
  ✓ PDO instance returnat
  ✓ 13 categorii în DB (got 13)
  ✓ Singleton returnează aceeași instanță

Rezultat: 3 passed, 0 failed
```

- [ ] **Step 5: Commit**

```
git add admin/config.php admin/models/Database.php admin/tests/test_db.php
git commit -m "feat: add Database PDO singleton and config"
```

---

## Task 3: Auth, CSRF, Flash Helpers

**Files:**
- Create: `admin/helpers/Auth.php`
- Create: `admin/helpers/Csrf.php`
- Create: `admin/helpers/Flash.php`
- Create: `admin/tests/test_helpers.php`

- [ ] **Step 1: Create Auth.php**

Create `admin/helpers/Auth.php`:

```php
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
```

- [ ] **Step 2: Create Csrf.php**

Create `admin/helpers/Csrf.php`:

```php
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
```

- [ ] **Step 3: Create Flash.php**

Create `admin/helpers/Flash.php`:

```php
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
```

- [ ] **Step 4: Write and run tests**

Create `admin/tests/test_helpers.php`:

```php
<?php
// Simulează sesiune fără browser
$_SERVER['REMOTE_ADDR']  = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
session_start();

require_once __DIR__ . '/../helpers/Csrf.php';
require_once __DIR__ . '/../helpers/Flash.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass,$fail; if($c){$pass++;echo"  ✓ $m\n";}else{$fail++;echo"  ✗ $m\n";} }

// CSRF: token generat corect
$t1 = Csrf::token();
ok(strlen($t1) === 64, 'CSRF token are 64 caractere');
ok(Csrf::token() === $t1, 'Același token returnat fără rotire');

// CSRF: input HTML
$html = Csrf::input();
ok(strpos($html, 'name="csrf_token"') !== false, 'CSRF input conține name="csrf_token"');
ok(strpos($html, $t1) !== false, 'CSRF input conține valoarea tokenului');

// Flash: set/get
Flash::success('Test reușit');
$f = Flash::get();
ok($f['type'] === 'success', 'Flash type = success');
ok($f['msg'] === 'Test reușit', 'Flash msg corect');
ok(Flash::get() === null, 'Flash consumat după get()');

// Flash: HTML output
Flash::error('Eroare test');
$h = Flash::html();
ok(strpos($h, 'flash--error') !== false, 'Flash html conține clasa flash--error');
ok(Flash::html() === '', 'Flash html gol după ce a fost consumat');

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
```

Run: `php admin/tests/test_helpers.php`

Expected: `8 passed, 0 failed`

- [ ] **Step 5: Commit**

```
git add admin/helpers/Auth.php admin/helpers/Csrf.php admin/helpers/Flash.php admin/tests/test_helpers.php
git commit -m "feat: add Auth, CSRF, Flash helpers"
```

---

## Task 4: Sanitize + Upload Helpers

**Files:**
- Create: `admin/helpers/Sanitize.php`
- Create: `admin/helpers/Upload.php`
- Create: `admin/tests/test_sanitize.php`

- [ ] **Step 1: Create Sanitize.php**

Create `admin/helpers/Sanitize.php`:

```php
<?php
class Sanitize {
    // Câmpuri text simplu (titlu, nume produs etc.)
    public static function text(string $input, int $max = 500): string {
        $clean = strip_tags(trim($input));
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $clean);
        return mb_substr($clean, 0, $max, 'UTF-8');
    }

    // Conținut HTML pentru pagini (whitelist strict)
    public static function html(string $input): string {
        $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><a><blockquote>';
        $clean = strip_tags($input, $allowed);
        // Elimină atribute on* (onclick etc.)
        $clean = preg_replace('/\s+on\w+\s*=\s*"[^"]*"/i', '', $clean);
        $clean = preg_replace("/\s+on\w+\s*=\s*'[^']*'/i", '', $clean);
        // Sanitizează href pe <a> — permite doar http/https
        $clean = preg_replace_callback('/<a([^>]*)>/i', function($m) {
            $attrs = $m[1];
            if (preg_match('/href\s*=\s*"([^"]*)"/i', $attrs, $hm)) {
                if (!preg_match('/^https?:\/\//i', $hm[1])) {
                    $attrs = str_replace($hm[0], 'href="#"', $attrs);
                }
            }
            // Permite doar href și target
            $attrs = preg_replace('/\s+(?!href|target)\w[\w-]*\s*=\s*"[^"]*"/i', '', $attrs);
            return '<a' . $attrs . '>';
        }, $clean);
        // Elimină null bytes
        return preg_replace('/\x00/', '', $clean);
    }

    // Slug URL-safe
    public static function slug(string $input): string {
        return preg_replace('/[^a-z0-9-]/', '', strtolower(trim($input)));
    }

    // Integer pozitiv
    public static function posInt(mixed $input): int {
        return max(0, (int)$input);
    }

    // Preț
    public static function price(mixed $input): ?float {
        $v = (float)$input;
        return $v > 0 ? round($v, 2) : null;
    }
}
```

- [ ] **Step 2: Create Upload.php**

Create `admin/helpers/Upload.php`:

```php
<?php
class Upload {
    private const ALLOWED = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
    ];

    /**
     * Validează și salvează imaginea uploadată.
     * @param array $file  Elementul din $_FILES
     * @return string      Calea relativă web (ex: /uploads/products/abc123.jpg)
     * @throws RuntimeException
     */
    public static function image(array $file): string {
        require_once __DIR__ . '/../config.php';

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Eroare upload PHP: ' . $file['error']);
        }
        if ($file['size'] > UPLOAD_MAX_BYTES) {
            throw new RuntimeException('Fișierul depășește 2MB.');
        }
        if ($file['size'] < 100) {
            throw new RuntimeException('Fișier suspect (prea mic).');
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
            throw new RuntimeException('Extensia nu corespunde cu tipul fișierului.');
        }

        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            throw new RuntimeException('Fișierul nu este o imagine validă.');
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
            throw new RuntimeException('Nu s-a putut salva fișierul.');
        }

        return UPLOAD_WEB . $safeName;
    }

    /**
     * Șterge imaginea de pe disc dacă există și aparține directorului de upload.
     */
    public static function delete(string $webPath): void {
        require_once __DIR__ . '/../config.php';
        if (!$webPath || strpos($webPath, UPLOAD_WEB) !== 0) return;
        $rel  = substr($webPath, strlen(UPLOAD_WEB));
        $full = rtrim(UPLOAD_DIR, '/') . '/' . basename($rel);
        if (is_file($full)) unlink($full);
    }
}
```

- [ ] **Step 3: Write and run Sanitize tests**

Create `admin/tests/test_sanitize.php`:

```php
<?php
require_once __DIR__ . '/../helpers/Sanitize.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass,$fail; if($c){$pass++;echo"  ✓ $m\n";}else{$fail++;echo"  ✗ $m\n";} }

// text()
ok(Sanitize::text('  hello  ') === 'hello', 'text() trimează spații');
ok(Sanitize::text('<b>bold</b>') === 'bold', 'text() elimină taguri');
ok(strlen(Sanitize::text(str_repeat('a', 1000), 10)) === 10, 'text() respectă max length');

// html()
$h = Sanitize::html('<p>Test <b>bold</b> <script>alert(1)</script></p>');
ok(strpos($h, '<script>') === false, 'html() elimină script');
ok(strpos($h, '<p>') !== false, 'html() păstrează <p>');
ok(strpos($h, '<b>') !== false, 'html() păstrează <b>');

$link = Sanitize::html('<a href="javascript:alert(1)">click</a>');
ok(strpos($link, 'javascript:') === false, 'html() elimină javascript: href');

$onclick = Sanitize::html('<p onclick="alert(1)">test</p>');
ok(strpos($onclick, 'onclick') === false, 'html() elimină onclick');

// slug()
ok(Sanitize::slug('Acoperis BCA') === 'acoperisbca', 'slug() lowercase fără spații');
ok(Sanitize::slug('gips-carton') === 'gips-carton', 'slug() păstrează cratimă');
ok(Sanitize::slug('../etc/passwd') === 'etcpasswd', 'slug() elimină path traversal');

// posInt()
ok(Sanitize::posInt('42') === 42, 'posInt() parsează string');
ok(Sanitize::posInt('-5') === 0, 'posInt() returnează 0 pentru negativ');

// price()
ok(Sanitize::price('25.50') === 25.50, 'price() parsează float');
ok(Sanitize::price('0') === null, 'price() returnează null pentru 0');

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
```

Run: `php admin/tests/test_sanitize.php`
Expected: `13 passed, 0 failed`

- [ ] **Step 4: Commit**

```
git add admin/helpers/Sanitize.php admin/helpers/Upload.php admin/tests/test_sanitize.php
git commit -m "feat: add Sanitize and Upload helpers with tests"
```

---

## Task 5: Uploads Directory + Admin .htaccess

**Files:**
- Create: `uploads/.htaccess`
- Create: `uploads/products/.gitkeep`
- Create: `admin/.htaccess`

- [ ] **Step 1: Secure uploads directory**

Create `uploads/.htaccess`:

```apache
# Blochează execuția PHP în directorul de upload
<FilesMatch "\.php[0-9]?$">
    Order Deny,Allow
    Deny from all
</FilesMatch>
Options -ExecCGI -Indexes
AddType text/plain .php .php3 .php4 .php5 .phtml
```

Create empty `uploads/products/.gitkeep` (creează directorul în git).

- [ ] **Step 2: Secure admin directory**

Create `admin/.htaccess`:

```apache
# Blochează accesul direct la subdirectoare interne
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(models|helpers|views|controllers|tests)/.*$ - [F,L]
</IfModule>
Options -Indexes

# Blochează fișiere sensibile
<FilesMatch "\.(sql|log|env|ini|json|md)$">
    Order Deny,Allow
    Deny from all
</FilesMatch>

# Forțează HTTPS
<IfModule mod_rewrite.c>
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
</IfModule>

# Headers securitate pentru admin
<IfModule mod_headers.c>
    Header always set X-Frame-Options "DENY"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tiny.cloud; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; frame-ancestors 'none'"
</IfModule>
```

- [ ] **Step 3: Commit**

```
git add uploads/.htaccess uploads/products/.gitkeep admin/.htaccess
git commit -m "feat: secure uploads dir and admin .htaccess"
```

---

## Task 6: Admin Router + Login

**Files:**
- Create: `admin/index.php`
- Create: `admin/controllers/AuthController.php`
- Create: `admin/views/layout.php`
- Create: `admin/views/login.php`
- Create: `admin/assets/admin.css`

- [ ] **Step 1: Create admin router**

Create `admin/index.php`:

```php
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
```

- [ ] **Step 2: Create AuthController.php**

Create `admin/controllers/AuthController.php`:

```php
<?php
// Inclus din index.php — $page, $action, $db disponibile

$db = Database::get();

if ($page === 'logout') {
    Auth::logout();
    header('Location: /admin/?page=login');
    exit;
}

// page = login
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();

    $username = Sanitize::text($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Brute-force check
    $ip = inet_pton($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $db->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL " . LOGIN_WINDOW_MINUTES . " MINUTE)")->execute();
    $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL " . LOGIN_WINDOW_MINUTES . " MINUTE)");
    $stmt->execute([$ip]);
    if ((int)$stmt->fetchColumn() >= LOGIN_MAX_ATTEMPTS) {
        $error = 'Prea multe încercări. Așteaptă ' . LOGIN_WINDOW_MINUTES . ' minute.';
    } else {
        $stmt = $db->prepare("SELECT id, username, role, password_hash FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        $dummyHash = '$2y$12$invaliddummyhashfortimingXXXXXXXXXXXXXXXXXXXXXXXX';
        $hashToCheck = $user ? $user['password_hash'] : $dummyHash;
        $valid = password_verify($password, $hashToCheck);

        if (!$user || !$valid) {
            $db->prepare("INSERT INTO login_attempts (ip, attempted_at) VALUES (?, NOW())")->execute([$ip]);
            $error = 'Username sau parolă incorecte.';
        } else {
            $db->prepare("DELETE FROM login_attempts WHERE ip = ?")->execute([$ip]);
            if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
                $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newHash, $user['id']]);
            }
            $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);
            $db->prepare("INSERT INTO audit_log (user_id, entity_type, action, ip_address) VALUES (?, 'auth', 'login', ?)")
               ->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? '']);
            Auth::login($user);
            header('Location: /admin/');
            exit;
        }
    }
}

require __DIR__ . '/../views/login.php';
```

- [ ] **Step 3: Create admin layout**

Create `admin/views/layout.php`:

```php
<?php
// Variabilă așteptată: $pageTitle (string), $bodyContent (capturată cu ob)
$flash = Flash::html();
?>
<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Admin', ENT_QUOTES, 'UTF-8') ?> — Mugurel Admin</title>
<link rel="stylesheet" href="/admin/assets/admin.css">
<meta name="robots" content="noindex,nofollow">
</head>
<body>
<nav class="adm-nav">
  <a class="adm-nav__brand" href="/admin/">⚙ Admin Mugurel</a>
  <div class="adm-nav__links">
    <a href="/admin/?page=products">Produse</a>
    <a href="/admin/?page=pages">Pagini</a>
    <?php if (Auth::hasRole('admin')): ?>
    <a href="/admin/?page=users">Utilizatori</a>
    <?php endif; ?>
    <span class="adm-nav__user"><?= htmlspecialchars(Auth::username(), ENT_QUOTES, 'UTF-8') ?></span>
    <a href="/admin/?page=logout" class="adm-nav__logout">Ieșire</a>
  </div>
</nav>
<main class="adm-main">
  <?= $flash ?>
  <?= $bodyContent ?? '' ?>
</main>
</body>
</html>
```

- [ ] **Step 4: Create login view**

Create `admin/views/login.php`:

```php
<?php
// $error disponibil din AuthController
?>
<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — Mugurel Admin</title>
<link rel="stylesheet" href="/admin/assets/admin.css">
<meta name="robots" content="noindex,nofollow">
</head>
<body class="adm-login-page">
<div class="adm-login-box">
  <h1>Admin Mugurel</h1>
  <?php if ($error): ?>
  <div class="flash flash--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <form method="POST" action="/admin/?page=login">
    <?= Csrf::input() ?>
    <label>Username
      <input type="text" name="username" autocomplete="username" required autofocus>
    </label>
    <label>Parolă
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button type="submit">Intră în cont</button>
  </form>
</div>
</body>
</html>
```

- [ ] **Step 5: Create admin CSS**

Create `admin/assets/admin.css`:

```css
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --verde: #06363A;
  --verde-mid: #0A5258;
  --verde-light: #e8f4f5;
  --accent: #25D366;
  --danger: #c0392b;
  --warn: #e67e22;
  --text: #222;
  --border: #d0dfe0;
  --radius: 6px;
}
body { font-family: 'Open Sans', sans-serif; font-size: 14px; color: var(--text); background: #f4f7f7; }

/* Nav */
.adm-nav { background: var(--verde); color: #fff; display: flex; align-items: center; justify-content: space-between; padding: 0 1.5rem; height: 52px; position: sticky; top: 0; z-index: 100; }
.adm-nav__brand { color: #fff; text-decoration: none; font-weight: 700; font-size: 1rem; }
.adm-nav__links { display: flex; align-items: center; gap: 1.2rem; }
.adm-nav__links a { color: #c8e6e8; text-decoration: none; font-size: .875rem; }
.adm-nav__links a:hover { color: #fff; }
.adm-nav__user { color: #7fb8bb; font-size: .8rem; }
.adm-nav__logout { background: rgba(255,255,255,.15); padding: .3rem .8rem; border-radius: 4px; }

/* Main */
.adm-main { max-width: 1100px; margin: 2rem auto; padding: 0 1.5rem; }

/* Flash */
.flash { padding: .8rem 1.2rem; border-radius: var(--radius); margin-bottom: 1rem; font-size: .9rem; }
.flash--success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.flash--error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

/* Login */
.adm-login-page { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: var(--verde); }
.adm-login-box { background: #fff; padding: 2.5rem; border-radius: 10px; width: 360px; box-shadow: 0 8px 32px rgba(0,0,0,.2); }
.adm-login-box h1 { color: var(--verde); margin-bottom: 1.5rem; font-size: 1.4rem; text-align: center; }
.adm-login-box label { display: block; margin-bottom: 1rem; font-size: .85rem; font-weight: 600; color: #555; }
.adm-login-box input { display: block; width: 100%; margin-top: .3rem; padding: .6rem .8rem; border: 1px solid var(--border); border-radius: var(--radius); font-size: .95rem; }
.adm-login-box button { width: 100%; padding: .75rem; background: var(--verde); color: #fff; border: none; border-radius: var(--radius); font-size: 1rem; cursor: pointer; margin-top: .5rem; }
.adm-login-box button:hover { background: var(--verde-mid); }

/* Cards / panels */
.adm-card { background: #fff; border-radius: var(--radius); border: 1px solid var(--border); padding: 1.5rem; margin-bottom: 1.5rem; }
.adm-card h2 { font-size: 1.1rem; color: var(--verde); margin-bottom: 1.2rem; }

/* Tables */
.adm-table { width: 100%; border-collapse: collapse; }
.adm-table th, .adm-table td { padding: .7rem 1rem; border-bottom: 1px solid var(--border); text-align: left; }
.adm-table th { background: var(--verde-light); color: var(--verde); font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; }
.adm-table tr:hover td { background: #f9fbfb; }
.adm-table img { width: 48px; height: 48px; object-fit: cover; border-radius: 4px; border: 1px solid var(--border); }

/* Buttons */
.btn { display: inline-block; padding: .5rem 1.2rem; border-radius: var(--radius); border: none; cursor: pointer; font-size: .875rem; text-decoration: none; font-weight: 600; }
.btn--primary { background: var(--verde); color: #fff; }
.btn--primary:hover { background: var(--verde-mid); }
.btn--danger  { background: var(--danger); color: #fff; }
.btn--sm      { padding: .3rem .7rem; font-size: .8rem; }

/* Forms */
.adm-form label { display: block; margin-bottom: 1.2rem; font-size: .85rem; font-weight: 600; color: #444; }
.adm-form input[type=text], .adm-form input[type=email], .adm-form input[type=password],
.adm-form input[type=number], .adm-form select, .adm-form textarea {
  display: block; width: 100%; margin-top: .3rem; padding: .55rem .8rem;
  border: 1px solid var(--border); border-radius: var(--radius); font-size: .9rem; }
.adm-form .adm-img-preview { max-width: 200px; max-height: 150px; object-fit: contain; border: 1px solid var(--border); border-radius: 4px; margin-top: .5rem; }
.adm-form .form-row { display: flex; gap: 1rem; }
.adm-form .form-row label { flex: 1; }

/* Dashboard stats */
.adm-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
.adm-stat  { background: #fff; border: 1px solid var(--border); border-radius: var(--radius); padding: 1.2rem; text-align: center; }
.adm-stat__num { font-size: 2rem; font-weight: 700; color: var(--verde); }
.adm-stat__label { font-size: .8rem; color: #777; margin-top: .2rem; }
```

- [ ] **Step 6: Test login in browser**

Deploy modificările pe server (git push + cPanel pull sau upload manual).

Verificare:
1. Accesează `https://mugurel-bricolaj.ro/admin/` → redirect la `?page=login`
2. Completează credentials greșite de 5 ori → apare mesaj "Prea multe încercări"
3. Completează credentials corecte → redirect la dashboard (blank ok pentru moment)
4. Verifică în browser: `httpOnly` cookie, `Secure`, `SameSite=Strict`

- [ ] **Step 7: Commit**

```
git add admin/index.php admin/controllers/AuthController.php admin/views/layout.php admin/views/login.php admin/assets/admin.css
git commit -m "feat: admin router, login/logout, auth controller, layout CSS"
```

---

## Task 7: Products Model + CRUD

**Files:**
- Create: `admin/models/Product.php`
- Create: `admin/controllers/ProductsController.php`
- Create: `admin/views/products/list.php`
- Create: `admin/views/products/edit.php`

- [ ] **Step 1: Create Product model**

Create `admin/models/Product.php`:

```php
<?php
class Product {
    public static function all(int $categoryId = 0, string $search = ''): array {
        $db = Database::get();
        $sql = 'SELECT p.*, c.name AS category_name
                FROM products p
                JOIN categories c ON c.id = p.category_id
                WHERE 1=1';
        $params = [];
        if ($categoryId > 0) {
            $sql .= ' AND p.category_id = ?';
            $params[] = $categoryId;
        }
        if ($search !== '') {
            $sql .= ' AND (p.name LIKE ? OR p.short_description LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        $sql .= ' ORDER BY c.sort_order, p.sort_order, p.name';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function byId(int $id): ?array {
        $stmt = Database::get()->prepare(
            'SELECT p.*, c.slug AS category_slug FROM products p
             JOIN categories c ON c.id = p.category_id WHERE p.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int {
        $db = Database::get();
        $stmt = $db->prepare(
            'INSERT INTO products
             (category_id, name, short_description, image_path, image_alt, price, price_unit, is_visible, sort_order, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $data['category_id'], $data['name'],  $data['short_description'],
            $data['image_path'],  $data['image_alt'], $data['price'],
            $data['price_unit'],  $data['is_visible'], $data['sort_order'],
            $data['created_by'],
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void {
        Database::get()->prepare(
            'UPDATE products SET
             category_id=?, name=?, short_description=?, image_path=?,
             image_alt=?, price=?, price_unit=?, is_visible=?, sort_order=?
             WHERE id=?'
        )->execute([
            $data['category_id'], $data['name'], $data['short_description'],
            $data['image_path'],  $data['image_alt'], $data['price'],
            $data['price_unit'],  $data['is_visible'], $data['sort_order'], $id,
        ]);
    }

    public static function delete(int $id): ?string {
        $product = self::byId($id);
        if (!$product) return null;
        Database::get()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
        return $product['image_path'] ?? null;
    }

    public static function categories(): array {
        $stmt = Database::get()->query('SELECT id, slug, name FROM categories WHERE is_active=1 ORDER BY sort_order');
        return $stmt->fetchAll();
    }
}
```

- [ ] **Step 2: Create ProductsController.php**

Create `admin/controllers/ProductsController.php`:

```php
<?php
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../helpers/Upload.php';

$db = Database::get();

switch ($action) {
    case 'new':
    case 'edit':
        $id = Sanitize::posInt($_GET['id'] ?? 0);
        $product = $id ? Product::byId($id) : null;
        if ($id && !$product) { Flash::error('Produsul nu există.'); header('Location: /admin/?page=products'); exit; }
        $categories = Product::categories();
        ob_start();
        require __DIR__ . '/../views/products/edit.php';
        $bodyContent = ob_get_clean();
        $pageTitle = $id ? 'Editare produs' : 'Produs nou';
        require __DIR__ . '/../views/layout.php';
        break;

    case 'save':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /admin/?page=products'); exit; }
        Csrf::verify();
        $id = Sanitize::posInt($_POST['id'] ?? 0);

        $imagePath = null;
        if ($id) {
            $existing = Product::byId($id);
            $imagePath = $existing['image_path'] ?? null;
        }

        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $newPath = Upload::image($_FILES['image']);
                if ($imagePath) Upload::delete($imagePath);
                $imagePath = $newPath;
            } catch (RuntimeException $e) {
                Flash::error('Upload imagine: ' . $e->getMessage());
                header('Location: /admin/?page=products&action=' . ($id ? 'edit&id=' . $id : 'new'));
                exit;
            }
        }

        $data = [
            'category_id'       => Sanitize::posInt($_POST['category_id'] ?? 0),
            'name'              => Sanitize::text($_POST['name'] ?? '', 200),
            'short_description' => Sanitize::text($_POST['short_description'] ?? '', 1000),
            'image_path'        => $imagePath,
            'image_alt'         => Sanitize::text($_POST['image_alt'] ?? '', 200),
            'price'             => Sanitize::price($_POST['price'] ?? ''),
            'price_unit'        => Sanitize::text($_POST['price_unit'] ?? '', 40),
            'is_visible'        => isset($_POST['is_visible']) ? 1 : 0,
            'sort_order'        => Sanitize::posInt($_POST['sort_order'] ?? 0),
            'created_by'        => Auth::id(),
        ];

        if (empty($data['name']) || $data['category_id'] === 0) {
            Flash::error('Numele și categoria sunt obligatorii.');
            header('Location: /admin/?page=products&action=' . ($id ? 'edit&id=' . $id : 'new'));
            exit;
        }

        if ($id) {
            Product::update($id, $data);
            $db->prepare("INSERT INTO audit_log (user_id,entity_type,entity_id,action,ip_address) VALUES (?,?,?,?,?)")
               ->execute([Auth::id(), 'product', $id, 'update', $_SERVER['REMOTE_ADDR'] ?? '']);
            Flash::success('Produsul a fost actualizat.');
        } else {
            $newId = Product::create($data);
            $db->prepare("INSERT INTO audit_log (user_id,entity_type,entity_id,action,ip_address) VALUES (?,?,?,?,?)")
               ->execute([Auth::id(), 'product', $newId, 'create', $_SERVER['REMOTE_ADDR'] ?? '']);
            Flash::success('Produs creat cu succes.');
        }
        header('Location: /admin/?page=products');
        exit;

    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /admin/?page=products'); exit; }
        Csrf::verify();
        $id = Sanitize::posInt($_POST['id'] ?? 0);
        $imgPath = Product::delete($id);
        if ($imgPath) Upload::delete($imgPath);
        $db->prepare("INSERT INTO audit_log (user_id,entity_type,entity_id,action,ip_address) VALUES (?,?,?,?,?)")
           ->execute([Auth::id(), 'product', $id, 'delete', $_SERVER['REMOTE_ADDR'] ?? '']);
        Flash::success('Produsul a fost șters.');
        header('Location: /admin/?page=products');
        exit;

    default: // index
        $search   = Sanitize::text($_GET['q'] ?? '', 100);
        $catId    = Sanitize::posInt($_GET['cat'] ?? 0);
        $products   = Product::all($catId, $search);
        $categories = Product::categories();
        ob_start();
        require __DIR__ . '/../views/products/list.php';
        $bodyContent = ob_get_clean();
        $pageTitle = 'Produse';
        require __DIR__ . '/../views/layout.php';
}
```

- [ ] **Step 3: Create products list view**

Create `admin/views/products/list.php`:

```php
<div class="adm-card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
    <h2>Produse (<?= count($products) ?>)</h2>
    <a href="/admin/?page=products&action=new" class="btn btn--primary">+ Produs nou</a>
  </div>

  <form method="GET" action="/admin/" style="display:flex;gap:.5rem;margin-bottom:1.2rem">
    <input type="hidden" name="page" value="products">
    <input type="text" name="q" value="<?= htmlspecialchars($search ?? '', ENT_QUOTES,'UTF-8') ?>" placeholder="Caută produse...">
    <select name="cat">
      <option value="0">Toate categoriile</option>
      <?php foreach ($categories as $cat): ?>
      <option value="<?= $cat['id'] ?>" <?= ($catId??0)==$cat['id']?'selected':'' ?>>
        <?= htmlspecialchars($cat['name'], ENT_QUOTES,'UTF-8') ?>
      </option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn--primary">Filtrează</button>
  </form>

  <table class="adm-table">
    <thead>
      <tr><th>Imagine</th><th>Nume</th><th>Categorie</th><th>Preț</th><th>Vizibil</th><th>Acțiuni</th></tr>
    </thead>
    <tbody>
    <?php foreach ($products as $p): ?>
    <tr>
      <td>
        <?php if ($p['image_path']): ?>
        <img src="<?= htmlspecialchars($p['image_path'],ENT_QUOTES,'UTF-8') ?>" alt="">
        <?php else: ?>
        <span style="color:#aaa;font-size:.8rem">Fără imagine</span>
        <?php endif; ?>
      </td>
      <td><?= htmlspecialchars($p['name'],ENT_QUOTES,'UTF-8') ?></td>
      <td><?= htmlspecialchars($p['category_name'],ENT_QUOTES,'UTF-8') ?></td>
      <td><?= $p['price'] ? number_format($p['price'],2).' '.$p['price_unit'] : '—' ?></td>
      <td><?= $p['is_visible'] ? '✅' : '❌' ?></td>
      <td>
        <a href="/admin/?page=products&action=edit&id=<?= $p['id'] ?>" class="btn btn--sm btn--primary">Editează</a>
        <form method="POST" action="/admin/?page=products&action=delete" style="display:inline"
              onsubmit="return confirm('Ștergi produsul?')">
          <?= Csrf::input() ?>
          <input type="hidden" name="id" value="<?= $p['id'] ?>">
          <button type="submit" class="btn btn--sm btn--danger">Șterge</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($products)): ?>
    <tr><td colspan="6" style="text-align:center;color:#aaa;padding:2rem">Niciun produs găsit.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
```

- [ ] **Step 4: Create products edit view**

Create `admin/views/products/edit.php`:

```php
<?php $isEdit = !empty($product); ?>
<div class="adm-card">
  <h2><?= $isEdit ? 'Editare: '.htmlspecialchars($product['name'],ENT_QUOTES,'UTF-8') : 'Produs nou' ?></h2>
  <form method="POST" action="/admin/?page=products&action=save" enctype="multipart/form-data" class="adm-form">
    <?= Csrf::input() ?>
    <?php if ($isEdit): ?>
    <input type="hidden" name="id" value="<?= $product['id'] ?>">
    <?php endif; ?>

    <label>Categorie *
      <select name="category_id" required>
        <option value="">— alege —</option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?= $cat['id'] ?>"
          <?= ($isEdit && $product['category_id']==$cat['id']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($cat['name'],ENT_QUOTES,'UTF-8') ?>
        </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>Nume produs *
      <input type="text" name="name" maxlength="200" required
             value="<?= htmlspecialchars($product['name']??'',ENT_QUOTES,'UTF-8') ?>">
    </label>

    <label>Descriere scurtă
      <textarea name="short_description" rows="4"><?= htmlspecialchars($product['short_description']??'',ENT_QUOTES,'UTF-8') ?></textarea>
    </label>

    <label>Imagine produs
      <?php if ($isEdit && $product['image_path']): ?>
      <img src="<?= htmlspecialchars($product['image_path'],ENT_QUOTES,'UTF-8') ?>" class="adm-img-preview" id="imgPreview">
      <?php else: ?>
      <img src="" class="adm-img-preview" id="imgPreview" style="display:none">
      <?php endif; ?>
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"
             onchange="previewImg(this)">
      <small>JPG, PNG, WebP, GIF — max 2MB</small>
    </label>

    <label>Text alternativ imagine (alt)
      <input type="text" name="image_alt" maxlength="200"
             value="<?= htmlspecialchars($product['image_alt']??'',ENT_QUOTES,'UTF-8') ?>">
    </label>

    <div class="form-row">
      <label>Preț (RON)
        <input type="number" name="price" step="0.01" min="0"
               value="<?= $product['price']??'' ?>">
      </label>
      <label>Unitate
        <input type="text" name="price_unit" maxlength="40" placeholder="mp, ml, buc..."
               value="<?= htmlspecialchars($product['price_unit']??'',ENT_QUOTES,'UTF-8') ?>">
      </label>
      <label>Ordine afișare
        <input type="number" name="sort_order" min="0"
               value="<?= $product['sort_order']??0 ?>">
      </label>
    </div>

    <label style="flex-direction:row;align-items:center;gap:.5rem">
      <input type="checkbox" name="is_visible" value="1"
             <?= (!$isEdit || $product['is_visible']) ? 'checked' : '' ?>>
      Vizibil pe site
    </label>

    <div style="margin-top:1.5rem;display:flex;gap:1rem">
      <button type="submit" class="btn btn--primary">Salvează</button>
      <a href="/admin/?page=products" class="btn" style="background:#eee">Anulează</a>
    </div>
  </form>
</div>
<script>
function previewImg(input) {
  const preview = document.getElementById('imgPreview');
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
```

- [ ] **Step 5: Test în browser**

1. Login în admin
2. `/admin/?page=products` → tabel gol
3. Click "Produs nou" → completează: categorie "Acoperiș", nume "Tablă cutată Z275", descriere, upload imagine JPG
4. Salvează → redirect la listă, produsul apare cu imagine
5. Editează → schimbă imaginea → salvează → imaginea veche ștearsă, nouă afișată
6. Șterge produsul → confirmă → dispare din listă

- [ ] **Step 6: Commit**

```
git add admin/models/Product.php admin/controllers/ProductsController.php admin/views/products/
git commit -m "feat: products CRUD with image upload"
```

---

## Task 8: Pages Model + CRUD (cu TinyMCE)

**Files:**
- Create: `admin/models/Page.php`
- Create: `admin/controllers/PagesController.php`
- Create: `admin/views/pages/list.php`
- Create: `admin/views/pages/edit.php`

- [ ] **Step 1: Obține TinyMCE API key**

Înainte de a continua: înregistrează-te gratuit la https://www.tiny.cloud → copiază API key-ul. Înlocuiește `YOUR_API_KEY` în pasul 4.

- [ ] **Step 2: Create Page model**

Create `admin/models/Page.php`:

```php
<?php
class Page {
    public static function all(): array {
        $stmt = Database::get()->query(
            'SELECT id, slug, title, restricted, is_published, updated_at FROM pages ORDER BY restricted, title'
        );
        return $stmt->fetchAll();
    }

    public static function byId(int $id): ?array {
        $stmt = Database::get()->prepare('SELECT * FROM pages WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function bySlug(string $slug): ?array {
        $stmt = Database::get()->prepare('SELECT * FROM pages WHERE slug = ? AND is_published = 1');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public static function update(int $id, array $data, int $userId): void {
        Database::get()->prepare(
            'UPDATE pages SET title=?, content=?, meta_title=?, meta_description=?, is_published=?, updated_by=? WHERE id=?'
        )->execute([
            $data['title'], $data['content'], $data['meta_title'],
            $data['meta_description'], $data['is_published'], $userId, $id,
        ]);
    }
}
```

- [ ] **Step 3: Create PagesController.php**

Create `admin/controllers/PagesController.php`:

```php
<?php
require_once __DIR__ . '/../models/Page.php';

switch ($action) {
    case 'edit':
        $id         = Sanitize::posInt($_GET['id'] ?? 0);
        $pageRecord = Page::byId($id); // $pageRecord evită conflict cu $page din router
        if (!$pageRecord) { Flash::error('Pagina nu există.'); header('Location: /admin/?page=pages'); exit; }
        // Check permisiune pagini restricționate
        if ($pageRecord['restricted'] && !Auth::hasRole('admin')) {
            http_response_code(403); die('Acces interzis — doar admin poate edita texte legale.');
        }
        ob_start();
        require __DIR__ . '/../views/pages/edit.php';
        $bodyContent = ob_get_clean();
        $pageTitle = 'Editare: ' . $pageRecord['title'];
        require __DIR__ . '/../views/layout.php';
        break;

    case 'save':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /admin/?page=pages'); exit; }
        Csrf::verify();
        $id         = Sanitize::posInt($_POST['id'] ?? 0);
        $pageRecord = Page::byId($id);
        if (!$pageRecord) { Flash::error('Pagina nu există.'); header('Location: /admin/?page=pages'); exit; }
        if ($pageRecord['restricted'] && !Auth::hasRole('admin')) {
            http_response_code(403); die('Acces interzis.');
        }
        $data = [
            'title'            => Sanitize::text($_POST['title'] ?? '', 200),
            'content'          => Sanitize::html($_POST['content'] ?? ''),
            'meta_title'       => Sanitize::text($_POST['meta_title'] ?? '', 160),
            'meta_description' => Sanitize::text($_POST['meta_description'] ?? '', 320),
            'is_published'     => isset($_POST['is_published']) ? 1 : 0,
        ];
        Page::update($id, $data, Auth::id());
        $db = Database::get();
        $db->prepare("INSERT INTO audit_log (user_id,entity_type,entity_id,action,ip_address) VALUES (?,?,?,?,?)")
           ->execute([Auth::id(), 'page', $id, 'update', $_SERVER['REMOTE_ADDR'] ?? '']);
        Flash::success('Pagina a fost salvată.');
        header('Location: /admin/?page=pages');
        exit;

    default:
        $pages = Page::all();
        ob_start();
        require __DIR__ . '/../views/pages/list.php';
        $bodyContent = ob_get_clean();
        $pageTitle = 'Pagini';
        require __DIR__ . '/../views/layout.php';
}
```

- [ ] **Step 4: Create pages list view**

Create `admin/views/pages/list.php`:

```php
<div class="adm-card">
  <h2>Pagini editabile</h2>
  <table class="adm-table">
    <thead><tr><th>Titlu</th><th>Slug</th><th>Tip</th><th>Status</th><th>Ultima modificare</th><th>Acțiuni</th></tr></thead>
    <tbody>
    <?php foreach ($pages as $p): ?>
    <tr>
      <td><?= htmlspecialchars($p['title'],ENT_QUOTES,'UTF-8') ?></td>
      <td><code>/<?= htmlspecialchars($p['slug'],ENT_QUOTES,'UTF-8') ?>.html</code></td>
      <td><?= $p['restricted'] ? '<span style="color:var(--danger)">🔒 Legal</span>' : 'General' ?></td>
      <td><?= $p['is_published'] ? '✅ Publicat' : '❌ Ascuns' ?></td>
      <td><?= $p['updated_at'] ?></td>
      <td>
        <?php if (!$p['restricted'] || Auth::hasRole('admin')): ?>
        <a href="/admin/?page=pages&action=edit&id=<?= $p['id'] ?>" class="btn btn--sm btn--primary">Editează</a>
        <?php else: ?>
        <span style="color:#aaa;font-size:.8rem">Doar admin</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
```

- [ ] **Step 5: Create pages edit view**

Create `admin/views/pages/edit.php`:

```php
<div class="adm-card">
  <h2>Editare: <?= htmlspecialchars($pageRecord['title'],ENT_QUOTES,'UTF-8') ?></h2>
  <?php if ($pageRecord['restricted']): ?>
  <div class="flash flash--error" style="margin-bottom:1rem">⚠ Pagină legală — modificările sunt vizibile public.</div>
  <?php endif; ?>

  <form method="POST" action="/admin/?page=pages&action=save" class="adm-form">
    <?= Csrf::input() ?>
    <input type="hidden" name="id" value="<?= $pageRecord['id'] ?>">

    <label>Titlu pagină *
      <input type="text" name="title" required maxlength="200"
             value="<?= htmlspecialchars($pageRecord['title'],ENT_QUOTES,'UTF-8') ?>">
    </label>

    <label>Conținut
      <textarea id="pageContent" name="content"><?= htmlspecialchars($pageRecord['content']??'',ENT_QUOTES,'UTF-8') ?></textarea>
    </label>

    <div class="form-row">
      <label>Meta title (SEO)
        <input type="text" name="meta_title" maxlength="160"
               value="<?= htmlspecialchars($pageRecord['meta_title']??'',ENT_QUOTES,'UTF-8') ?>">
      </label>
      <label>Meta description (SEO)
        <input type="text" name="meta_description" maxlength="320"
               value="<?= htmlspecialchars($pageRecord['meta_description']??'',ENT_QUOTES,'UTF-8') ?>">
      </label>
    </div>

    <label style="flex-direction:row;align-items:center;gap:.5rem">
      <input type="checkbox" name="is_published" value="1" <?= $pageRecord['is_published']?'checked':'' ?>>
      Publicat
    </label>

    <div style="margin-top:1.5rem;display:flex;gap:1rem">
      <button type="submit" class="btn btn--primary">Salvează</button>
      <a href="/admin/?page=pages" class="btn" style="background:#eee">Anulează</a>
    </div>
  </form>
</div>

<script src="https://cdn.tiny.cloud/1/YOUR_API_KEY/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
  selector: '#pageContent',
  plugins: 'lists link',
  toolbar: 'undo redo | bold italic underline | h2 h3 | bullist numlist | link | removeformat',
  menubar: false,
  branding: false,
  height: 450,
  valid_elements: 'p,br,strong/b,em/i,u,ul,ol,li,h2,h3,h4,a[href|target],blockquote',
  content_style: 'body { font-family: Open Sans, sans-serif; font-size: 15px; }'
});
</script>
```

- [ ] **Step 6: Test în browser**

1. Login ca admin → `/admin/?page=pages`
2. Toate 5 paginile apar; legale marcate cu 🔒
3. Click "Editează" pe "Despre Noi" → TinyMCE se încarcă → editează text → salvează → success flash
4. Login ca editor → paginile legale afișează "Doar admin"; click edit pe legal → 403
5. Verifică că `Sanitize::html()` funcționează: încearcă salvare cu `<script>alert(1)</script>` → nu apare în DB

- [ ] **Step 7: Commit**

```
git add admin/models/Page.php admin/controllers/PagesController.php admin/views/pages/
git commit -m "feat: pages CRUD with TinyMCE editor and role-based access"
```

---

## Task 9: Users Model + Management

**Files:**
- Create: `admin/models/User.php`
- Create: `admin/controllers/UsersController.php`
- Create: `admin/views/users/list.php`
- Create: `admin/views/users/edit.php`

- [ ] **Step 1: Create User model**

Create `admin/models/User.php`:

```php
<?php
class User {
    public static function all(): array {
        $stmt = Database::get()->query(
            'SELECT id, username, email, role, is_active, created_at, last_login_at FROM users ORDER BY role, username'
        );
        return $stmt->fetchAll();
    }

    public static function byId(int $id): ?array {
        $stmt = Database::get()->prepare('SELECT id, username, email, role, is_active FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int {
        $db = Database::get();
        $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $db->prepare('INSERT INTO users (username, email, password_hash, role, is_active) VALUES (?,?,?,?,?)');
        $stmt->execute([$data['username'], $data['email'], $hash, $data['role'], $data['is_active']]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void {
        $db = Database::get();
        if (!empty($data['password'])) {
            $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare('UPDATE users SET username=?, email=?, password_hash=?, role=?, is_active=? WHERE id=?')
               ->execute([$data['username'], $data['email'], $hash, $data['role'], $data['is_active'], $id]);
        } else {
            $db->prepare('UPDATE users SET username=?, email=?, role=?, is_active=? WHERE id=?')
               ->execute([$data['username'], $data['email'], $data['role'], $data['is_active'], $id]);
        }
    }

    public static function usernameExists(string $username, int $excludeId = 0): bool {
        $stmt = Database::get()->prepare('SELECT COUNT(*) FROM users WHERE username = ? AND id != ?');
        $stmt->execute([$username, $excludeId]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
```

- [ ] **Step 2: Create UsersController.php**

Create `admin/controllers/UsersController.php`:

```php
<?php
Auth::requireRole('admin'); // Numai admin
require_once __DIR__ . '/../models/User.php';

switch ($action) {
    case 'new':
    case 'edit':
        $id   = Sanitize::posInt($_GET['id'] ?? 0);
        $user = $id ? User::byId($id) : null;
        if ($id && !$user) { Flash::error('Utilizatorul nu există.'); header('Location: /admin/?page=users'); exit; }
        ob_start();
        require __DIR__ . '/../views/users/edit.php';
        $bodyContent = ob_get_clean();
        $pageTitle = $id ? 'Editare utilizator' : 'Utilizator nou';
        require __DIR__ . '/../views/layout.php';
        break;

    case 'save':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /admin/?page=users'); exit; }
        Csrf::verify();
        $id       = Sanitize::posInt($_POST['id'] ?? 0);
        $username = Sanitize::text($_POST['username'] ?? '', 60);
        $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $role     = in_array($_POST['role']??'', ['admin','editor'], true) ? $_POST['role'] : 'editor';
        $password = $_POST['password'] ?? '';
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (!$username || !$email) {
            Flash::error('Username și email sunt obligatorii.');
            header('Location: /admin/?page=users&action=' . ($id ? 'edit&id='.$id : 'new')); exit;
        }
        if (!$id && strlen($password) < 8) {
            Flash::error('Parola trebuie să aibă minim 8 caractere.');
            header('Location: /admin/?page=users&action=new'); exit;
        }
        if (User::usernameExists($username, $id)) {
            Flash::error('Username-ul există deja.');
            header('Location: /admin/?page=users&action=' . ($id ? 'edit&id='.$id : 'new')); exit;
        }
        $data = ['username'=>$username,'email'=>$email,'role'=>$role,'is_active'=>$isActive,'password'=>$password];
        if ($id) {
            User::update($id, $data);
            Flash::success('Utilizatorul a fost actualizat.');
        } else {
            User::create($data);
            Flash::success('Utilizator creat cu succes.');
        }
        header('Location: /admin/?page=users'); exit;

    case 'toggle':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /admin/?page=users'); exit; }
        Csrf::verify();
        $id = Sanitize::posInt($_POST['id'] ?? 0);
        if ($id === Auth::id()) { Flash::error('Nu poți dezactiva propriul cont.'); header('Location: /admin/?page=users'); exit; }
        $u = User::byId($id);
        if ($u) {
            Database::get()->prepare('UPDATE users SET is_active = ? WHERE id = ?')
                ->execute([$u['is_active'] ? 0 : 1, $id]);
            Flash::success('Status actualizat.');
        }
        header('Location: /admin/?page=users'); exit;

    default:
        $users = User::all();
        ob_start();
        require __DIR__ . '/../views/users/list.php';
        $bodyContent = ob_get_clean();
        $pageTitle = 'Utilizatori';
        require __DIR__ . '/../views/layout.php';
}
```

- [ ] **Step 3: Create users list view**

Create `admin/views/users/list.php`:

```php
<div class="adm-card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
    <h2>Utilizatori (<?= count($users) ?>)</h2>
    <a href="/admin/?page=users&action=new" class="btn btn--primary">+ Utilizator nou</a>
  </div>
  <table class="adm-table">
    <thead><tr><th>Username</th><th>Email</th><th>Rol</th><th>Status</th><th>Ultima autentificare</th><th>Acțiuni</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><?= htmlspecialchars($u['username'],ENT_QUOTES,'UTF-8') ?></td>
      <td><?= htmlspecialchars($u['email'],ENT_QUOTES,'UTF-8') ?></td>
      <td><?= $u['role'] === 'admin' ? '<strong>Admin</strong>' : 'Editor' ?></td>
      <td><?= $u['is_active'] ? '✅ Activ' : '❌ Inactiv' ?></td>
      <td><?= $u['last_login_at'] ?? 'Niciodată' ?></td>
      <td>
        <a href="/admin/?page=users&action=edit&id=<?= $u['id'] ?>" class="btn btn--sm btn--primary">Editează</a>
        <?php if ($u['id'] !== Auth::id()): ?>
        <form method="POST" action="/admin/?page=users&action=toggle" style="display:inline">
          <?= Csrf::input() ?>
          <input type="hidden" name="id" value="<?= $u['id'] ?>">
          <button type="submit" class="btn btn--sm" style="background:#eee">
            <?= $u['is_active'] ? 'Dezactivează' : 'Activează' ?>
          </button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
```

- [ ] **Step 4: Create users edit view**

Create `admin/views/users/edit.php`:

```php
<?php $isEdit = !empty($user); ?>
<div class="adm-card">
  <h2><?= $isEdit ? 'Editare: '.htmlspecialchars($user['username'],ENT_QUOTES,'UTF-8') : 'Utilizator nou' ?></h2>
  <form method="POST" action="/admin/?page=users&action=save" class="adm-form">
    <?= Csrf::input() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $user['id'] ?>"><?php endif; ?>

    <label>Username *
      <input type="text" name="username" required maxlength="60"
             value="<?= htmlspecialchars($user['username']??'',ENT_QUOTES,'UTF-8') ?>">
    </label>
    <label>Email *
      <input type="email" name="email" required maxlength="180"
             value="<?= htmlspecialchars($user['email']??'',ENT_QUOTES,'UTF-8') ?>">
    </label>
    <label>Parolă <?= $isEdit ? '(lasă gol pentru a păstra parola actuală)' : '* minim 8 caractere' ?>
      <input type="password" name="password" <?= $isEdit?'':'required' ?> minlength="8" autocomplete="new-password">
    </label>
    <label>Rol
      <select name="role">
        <option value="editor" <?= (!$isEdit||$user['role']==='editor')?'selected':'' ?>>Editor</option>
        <option value="admin"  <?= ($isEdit&&$user['role']==='admin')?'selected':'' ?>>Admin</option>
      </select>
    </label>
    <label style="flex-direction:row;align-items:center;gap:.5rem">
      <input type="checkbox" name="is_active" value="1" <?= (!$isEdit||$user['is_active'])?'checked':'' ?>>
      Cont activ
    </label>
    <div style="margin-top:1.5rem;display:flex;gap:1rem">
      <button type="submit" class="btn btn--primary">Salvează</button>
      <a href="/admin/?page=users" class="btn" style="background:#eee">Anulează</a>
    </div>
  </form>
</div>
```

- [ ] **Step 5: Test în browser**

1. Login ca admin → `/admin/?page=users`
2. Crează editor nou: username `editor_test`, email, parolă
3. Login cu editor → verifică că `/admin/?page=users` returnează 403
4. Revino ca admin → dezactivează editor → login cu editor → eșuează

- [ ] **Step 6: Commit**

```
git add admin/models/User.php admin/controllers/UsersController.php admin/views/users/
git commit -m "feat: user management (admin only) with role switching"
```

---

## Task 10: Dashboard

**Files:**
- Create: `admin/controllers/DashboardController.php`
- Create: `admin/views/dashboard.php`

- [ ] **Step 1: Create DashboardController.php**

Create `admin/controllers/DashboardController.php`:

```php
<?php
$db = Database::get();

$stats = $db->query("
    SELECT
        (SELECT COUNT(*) FROM products WHERE is_visible=1) AS produse_active,
        (SELECT COUNT(*) FROM products WHERE is_visible=0) AS produse_ascunse,
        (SELECT COUNT(*) FROM products WHERE image_path IS NULL OR image_path='') AS produse_fara_imagine,
        (SELECT COUNT(*) FROM users   WHERE is_active=1)  AS utilizatori_activi,
        (SELECT COUNT(*) FROM audit_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS modificari_7_zile
")->fetch();

$recentLog = $db->query("
    SELECT al.created_at, al.action, al.entity_type, al.entity_id, u.username
    FROM audit_log al
    LEFT JOIN users u ON u.id = al.user_id
    ORDER BY al.created_at DESC
    LIMIT 10
")->fetchAll();

ob_start();
require __DIR__ . '/../views/dashboard.php';
$bodyContent = ob_get_clean();
$pageTitle = 'Dashboard';
require __DIR__ . '/../views/layout.php';
```

- [ ] **Step 2: Create dashboard view**

Create `admin/views/dashboard.php`:

```php
<div class="adm-stats">
  <div class="adm-stat">
    <div class="adm-stat__num"><?= $stats['produse_active'] ?></div>
    <div class="adm-stat__label">Produse active</div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat__num" style="color:var(--warn)"><?= $stats['produse_fara_imagine'] ?></div>
    <div class="adm-stat__label">Fără imagine</div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat__num"><?= $stats['utilizatori_activi'] ?></div>
    <div class="adm-stat__label">Utilizatori activi</div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat__num"><?= $stats['modificari_7_zile'] ?></div>
    <div class="adm-stat__label">Modificări (7 zile)</div>
  </div>
</div>

<?php if ($stats['produse_fara_imagine'] > 0): ?>
<div class="flash flash--error">
  ⚠ <?= $stats['produse_fara_imagine'] ?> produse fără imagine.
  <a href="/admin/?page=products&q=&cat=0" style="color:inherit;font-weight:700">Completează →</a>
</div>
<?php endif; ?>

<div class="adm-card">
  <h2>Activitate recentă</h2>
  <table class="adm-table">
    <thead><tr><th>Data</th><th>Utilizator</th><th>Acțiune</th><th>Entitate</th></tr></thead>
    <tbody>
    <?php foreach ($recentLog as $log): ?>
    <tr>
      <td><?= $log['created_at'] ?></td>
      <td><?= htmlspecialchars($log['username']??'—',ENT_QUOTES,'UTF-8') ?></td>
      <td><?= htmlspecialchars($log['action'],ENT_QUOTES,'UTF-8') ?></td>
      <td><?= htmlspecialchars($log['entity_type'],ENT_QUOTES,'UTF-8') ?>
          <?= $log['entity_id'] ? '#'.$log['entity_id'] : '' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
```

- [ ] **Step 3: Commit**

```
git add admin/controllers/DashboardController.php admin/views/dashboard.php
git commit -m "feat: admin dashboard with stats and activity log"
```

---

## Task 11: Frontend Conversion — HTML → PHP

**Files:**
- Modify: `Website/.htaccess`
- Create: `router.php`
- Create: `templates/header.php`
- Create: `templates/footer.php`
- Create: `templates/category.php`
- Create: `templates/page.php`
- Create: `includes/db.php`

- [ ] **Step 1: Create includes/db.php**

Create `includes/db.php`:

```php
<?php
require_once __DIR__ . '/../admin/models/Database.php';
```

- [ ] **Step 2: Extract shared header template**

Creează `templates/header.php` copiind header-ul comun din orice `.html` existent (de la `<!DOCTYPE html>` până la și inclusiv `<nav>`), cu aceste modificări:

- Înlocuiește `<?php echo htmlspecialchars($pageTitle ?? '', ENT_QUOTES, 'UTF-8') ?>` pentru `<title>`
- Înlocuiește `<?php echo htmlspecialchars($metaDescription ?? '', ENT_QUOTES, 'UTF-8') ?>` pentru `<meta name="description">`
- Înlocuiește `<?php echo htmlspecialchars($canonicalUrl ?? SITE_URL.'/', ENT_QUOTES, 'UTF-8') ?>` pentru `<link rel="canonical">`
- Adaugă GA4 tag (deja existent în HTML-urile actuale)
- Link-urile din nav rămân neschimbate (`.html` — rewrite-ul le preia)

```php
<!DOCTYPE html>
<html lang="ro">
<head>
  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-R6M7YYLB48"></script>
  <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','G-R6M7YYLB48');</script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Mugurel — Materiale Constructii Dăbuleni', ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="description" content="<?= htmlspecialchars($metaDescription ?? '', ENT_QUOTES, 'UTF-8') ?>">
  <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl ?? 'https://mugurel-bricolaj.ro/', ENT_QUOTES, 'UTF-8') ?>">
  <!-- OG / Twitter tags rămân statice sau pot fi variabile PHP similar -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/css/style.css">
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
</head>
<body>
<!-- PROMO STRIP — copiat din HTML existent, neschimbat -->
<?php /* Inserează promo strip HTML de la linia 52 din acoperis.html */ ?>

<!-- HEADER — copiat din HTML existent, neschimbat -->
<?php /* Inserează header HTML de la linia 67 din acoperis.html */ ?>

<!-- NAV — copiat din HTML existent, neschimbat -->
<?php /* Inserează nav HTML de la linia 86 din acoperis.html */ ?>
```

**Important:** Copiează blocurile HTML exacte din `acoperis.html` liniile 52-99 în acest template.

- [ ] **Step 3: Extract footer template**

Creează `templates/footer.php` cu tot ce vine după conținut (footer, scripts) — copiat din HTML existent.

- [ ] **Step 4: Create category.php template**

Create `templates/category.php`:

```php
<?php
// Variabile așteptate: $categoryName, $categorySlug, $products, $pageTitle, $metaDescription, $canonicalUrl
require __DIR__ . '/header.php';
?>
<!-- PAGE HERO — adaptat dinamic -->
<div class="page-hero">
  <div class="page-hero-inner">
    <div class="breadcrumb">
      <a href="/index.html">Acasa</a><span>›</span>
      <span><?= htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <h1><?= htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') ?> în Dăbuleni</h1>
  </div>
</div>

<!-- PRODUCTS GRID -->
<div class="body-wrap">
  <div class="content-area">
    <?php if (empty($products)): ?>
    <p style="color:#aaa;padding:2rem">Niciun produs disponibil momentan.</p>
    <?php else: ?>
    <div class="materials-grid">
      <?php foreach ($products as $p): ?>
      <div class="material-card">
        <div class="material-img-wrap">
          <?php if ($p['image_path']): ?>
          <img src="<?= htmlspecialchars($p['image_path'], ENT_QUOTES, 'UTF-8') ?>"
               alt="<?= htmlspecialchars($p['image_alt'] ?: $p['name'], ENT_QUOTES, 'UTF-8') ?>"
               loading="lazy"
               onerror="this.closest('.material-img-wrap').style.display='none'">
          <?php endif; ?>
        </div>
        <div class="material-info">
          <h3><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></h3>
          <?php if ($p['short_description']): ?>
          <p><?= htmlspecialchars($p['short_description'], ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
          <?php if ($p['price']): ?>
          <div class="material-price"><?= number_format($p['price'],2) ?> RON/<?= htmlspecialchars($p['price_unit'],ENT_QUOTES,'UTF-8') ?></div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
```

- [ ] **Step 5: Create page.php template**

Create `templates/page.php`:

```php
<?php require __DIR__ . '/header.php'; ?>
<div class="page-hero">
  <div class="page-hero-inner">
    <h1><?= htmlspecialchars($pageData['title'], ENT_QUOTES, 'UTF-8') ?></h1>
  </div>
</div>
<div class="body-wrap">
  <div class="content-area" style="max-width:800px;padding:2rem 1.5rem">
    <?= $pageData['content'] /* HTML sanitizat din DB */ ?>
  </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
```

- [ ] **Step 6: Create router.php**

Create `router.php`:

```php
<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/admin/config.php';

$slug = preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['slug'] ?? ''));
if (empty($slug)) { header('Location: /'); exit; }

$db = Database::get();

// Verifică dacă e categorie de produse
$stmt = $db->prepare('SELECT id, name FROM categories WHERE slug = ? AND is_active = 1');
$stmt->execute([$slug]);
$category = $stmt->fetch();

if ($category) {
    $stmt = $db->prepare(
        'SELECT id, name, short_description, image_path, image_alt, price, price_unit
         FROM products WHERE category_id = ? AND is_visible = 1 ORDER BY sort_order, name'
    );
    $stmt->execute([$category['id']]);
    $products       = $stmt->fetchAll();
    $categoryName   = $category['name'];
    $categorySlug   = $slug;
    $pageTitle      = $categoryName . ' în Dăbuleni — Mugurel';
    $metaDescription = 'Materiale ' . $categoryName . ' în Dăbuleni. Stoc disponibil, livrare Dolj. Comandă pe WhatsApp 0749 130 565.';
    $canonicalUrl   = SITE_URL . '/' . $slug . '.html';
    require __DIR__ . '/templates/category.php';
} else {
    // Pagină statică
    $stmt = $db->prepare('SELECT * FROM pages WHERE slug = ? AND is_published = 1');
    $stmt->execute([$slug]);
    $pageData = $stmt->fetch();
    if (!$pageData) { http_response_code(404); include '404.php'; exit; }
    $pageTitle       = $pageData['meta_title'] ?: $pageData['title'] . ' — Mugurel';
    $metaDescription = $pageData['meta_description'] ?? '';
    $canonicalUrl    = SITE_URL . '/' . $slug . '.html';
    require __DIR__ . '/templates/page.php';
}
```

- [ ] **Step 7: Update .htaccess**

Modifică `Website/.htaccess` — adaugă înainte de orice regulă existentă:

```apache
RewriteEngine On

# Admin — nu atinge
RewriteRule ^admin/ - [L]

# Fișiere și directoare reale (css, js, img, uploads)
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# /slug.html sau /slug → router.php
RewriteRule ^([a-z0-9][a-z0-9-]*)\.html?$ /router.php?slug=$1 [L,QSA]
RewriteRule ^([a-z0-9][a-z0-9-]*)/? $      /router.php?slug=$1 [L,QSA]

# Headers securitate (din .htaccess existent — păstrează-le)
```

- [ ] **Step 8: Test frontend**

1. Accesează `https://mugurel-bricolaj.ro/acoperis.html` → trebuie să afișeze pagina din DB (produse din tabela products)
2. Accesează `https://mugurel-bricolaj.ro/despre-noi.html` → pagina statică din DB
3. Adaugă un produs în admin → reîncarcă pagina categorie → produsul apare

- [ ] **Step 9: Commit**

```
git add router.php includes/db.php templates/ .htaccess
git commit -m "feat: frontend router and PHP templates for category and static pages"
```

---

## Task 12: Content Migration — HTML → DB

**Files:**
- Create: `Website/db/migrate_pages.php` (script one-shot)

- [ ] **Step 1: Create migration script**

Create `Website/db/migrate_pages.php`:

```php
<?php
// Rulează o singură dată: php Website/db/migrate_pages.php
// Extrage conținutul HTML din paginile statice și îl inserează în DB

require_once __DIR__ . '/../admin/models/Database.php';

$db = Database::get();

$migrations = [
    'despre-noi' => [
        'file'  => __DIR__ . '/../Website/despre-noi.html',
        'start' => '<!-- CONTENT START -->', // marchează în HTML dacă există
        'title' => 'Despre Noi',
    ],
    'contact' => [
        'file'  => __DIR__ . '/../Website/contact.html',
        'title' => 'Contact',
    ],
    'politica-confidentialitate' => [
        'file'  => __DIR__ . '/../Website/politica-confidentialitate.html',
        'title' => 'Politica de Confidențialitate',
    ],
    'termeni-conditii' => [
        'file'  => __DIR__ . '/../Website/termeni-conditii.html',
        'title' => 'Termeni și Condiții',
    ],
    'politica-retur' => [
        'file'  => __DIR__ . '/../Website/politica-retur.html',
        'title' => 'Politica de Retur',
    ],
];

foreach ($migrations as $slug => $cfg) {
    if (!file_exists($cfg['file'])) {
        echo "  ⚠ Fișier lipsă: {$cfg['file']}\n";
        continue;
    }
    $html = file_get_contents($cfg['file']);

    // Extrage conținutul din <body> — tot ce e între body-wrap sau main-content
    // Adaptează regex la structura HTML reală a fiecărui fișier
    if (preg_match('/<div class="body-wrap">(.*?)<\/body>/s', $html, $m)) {
        $content = trim($m[1]);
    } elseif (preg_match('/<main[^>]*>(.*?)<\/main>/s', $html, $m)) {
        $content = trim($m[1]);
    } else {
        // Fallback: conținut după </nav> și înainte de </body>
        $content = preg_replace('/.*<\/nav>/s', '', $html);
        $content = preg_replace('/<\/body>.*/s', '', $content);
        $content = trim($content);
    }

    $db->prepare('UPDATE pages SET content = ? WHERE slug = ?')->execute([$content, $slug]);
    echo "  ✓ Migrat: $slug\n";
}

echo "\nMigrare completă. Verifică paginile în admin.\n";
```

- [ ] **Step 2: Run migration**

```
php Website/db/migrate_pages.php
```

Verificare: Login admin → Pagini → editează "Despre Noi" → conținut apare în TinyMCE.

- [ ] **Step 3: Manual review + cleanup**

Pentru fiecare pagină:
1. Deschide `/admin/?page=pages` → editează pagina
2. Verifică că TinyMCE arată conținut corect (nu HTML broken)
3. Curăță manual dacă regex-ul a prins elemente nedorite (nav, footer)
4. Salvează

- [ ] **Step 4: Commit**

```
git add Website/db/migrate_pages.php
git commit -m "feat: page content migration script HTML to DB"
```

---

## Task 13: Deploy + Verificare finală

- [ ] **Step 1: Git push + cPanel deploy**

```
git push origin main
```

În cPanel → Git Version Control → pull.

- [ ] **Step 2: Creare DB și user pe server**

În cPanel → MySQL Databases:
1. Crează baza de date `mugurel_cms`
2. Crează user `mugurel_db` cu parolă puternică
3. Adaugă user la DB cu ALL PRIVILEGES
4. Actualizează `admin/config.php` cu credențialele reale (**nu commit parola**)

- [ ] **Step 3: Import schema pe server**

phpMyAdmin → selectează `mugurel_cms` → Import → `Website/db/schema.sql`.

- [ ] **Step 4: Rulează seed + migration pe server**

Uploadează temporar `Website/db/seed_admin.php` cu credențialele reale → accesează URL → șterge.
Uploadează temporar `Website/db/migrate_pages.php` → rulează → șterge.

- [ ] **Step 5: Checklist verificare finală**

- [ ] `https://mugurel-bricolaj.ro/admin/` → redirect la login
- [ ] Login cu admin → dashboard arată stats
- [ ] `/admin/?page=products` → lista goală, "Produs nou" funcționează
- [ ] Upload imagine produs → imagine apare în `/uploads/products/`
- [ ] `/admin/?page=pages` → paginile legale marcate cu 🔒
- [ ] Login cu editor → nu vede users, nu poate edita legal
- [ ] `https://mugurel-bricolaj.ro/acoperis.html` → pagina categorie funcționează
- [ ] `https://mugurel-bricolaj.ro/despre-noi.html` → pagina statică funcționează
- [ ] `https://mugurel-bricolaj.ro/uploads/products/test.php` → 403 (PHP blocat)
- [ ] `https://mugurel-bricolaj.ro/admin/helpers/Auth.php` → 403 (director blocat)

- [ ] **Step 6: Commit config (fără parole!)**

Asigură-te că `admin/config.php` are `DB_PASS = 'SCHIMBA_PAROLA_AICI'` în repo — parola reală **niciodată în git**.

```
git status  # verifică că nu sunt fișiere sensibile staged
git push origin main
```
