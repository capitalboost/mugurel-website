# CMS Admin Panel — Design Spec
**Proiect:** mugurel-bricolaj.ro  
**Data:** 2026-06-25  
**Status:** Aprobat de user, în așteptare implementare

---

## 1. Scopul sistemului

Panou de administrare custom PHP + MySQL care permite:
- Editarea imaginilor și descrierilor produselor (per produs individual)
- Editarea textelor paginilor statice (despre noi, contact)
- Editarea textelor legale (politica confidențialitate, termeni, politica retur) — doar admin
- Gestionarea conturilor de utilizator (admin poate crea sub-conturi editor)

Designul vizual al site-ului rămâne **identic**. Paginile HTML devin PHP dar sunt servite cu același layout/CSS.

---

## 2. Arhitectura generală

### Stack
- **Backend:** PHP 7.4+ (fără framework, fără Composer)
- **Baza de date:** MySQL / MariaDB (disponibil pe HostGate cPanel)
- **Editor WYSIWYG:** TinyMCE gratuit, inclus via CDN (fără instalare)
- **Hosting:** HostGate cPanel, Apache

### Fluxul paginilor site-ului
```
Vizitator → /acoperis.html
  → .htaccess rewrite → page.php?slug=acoperis
  → page.php citește DB → randează layout cu conținut din DB
  → HTML identic vizual cu site-ul actual
```

URL-urile existente funcționează nemodificat (`.html` redirecționat transparent).

---

## 3. Structura fișierelor

```
/public_html/
├── .htaccess                   # Rewrite rules site + redirect .html → page.php
├── index.php                   # Homepage (înlocuiește index.html)
├── page.php                    # Servește orice pagină din DB via slug
│
├── /admin/
│   ├── .htaccess               # Blochează accesul la subdirectoare interne
│   ├── index.php               # Router unic al adminului
│   ├── /controllers/
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── ProductsController.php
│   │   ├── PagesController.php
│   │   └── UsersController.php
│   ├── /models/
│   │   ├── Database.php        # Singleton PDO
│   │   ├── Product.php
│   │   ├── Page.php
│   │   └── User.php
│   ├── /views/
│   │   ├── layout.php          # Header + nav + footer admin
│   │   ├── login.php
│   │   ├── dashboard.php
│   │   ├── products/
│   │   │   ├── list.php
│   │   │   └── edit.php
│   │   ├── pages/
│   │   │   ├── list.php
│   │   │   └── edit.php
│   │   └── users/
│   │       ├── list.php
│   │       └── edit.php
│   ├── /helpers/
│   │   ├── Auth.php            # isLoggedIn, requireLogin, requireRole
│   │   ├── Csrf.php            # Generare + validare token
│   │   ├── Flash.php           # Mesaje success/error prin session
│   │   ├── Upload.php          # Validare + salvare imagini
│   │   └── Sanitize.php        # strip_tags, htmlspecialchars, html_purify
│   └── /assets/
│       ├── admin.css
│       └── admin.js
│
├── /uploads/
│   ├── .htaccess               # Deny PHP execution în uploads
│   └── /products/              # Imagini produse (uuid_random.ext)
│
└── /includes/
    └── db.php                  # Include Database.php (partajat cu page.php)
```

---

## 4. Routing admin

Un singur entry point: `/admin/index.php`. Navigare prin query string.

| URL | Acces | Acțiune |
|-----|-------|---------|
| `/admin/` | editor + admin | Dashboard |
| `/admin/?page=login` | public | Formular login |
| `/admin/?page=products` | editor + admin | Lista produse |
| `/admin/?page=products&action=edit&id=42` | editor + admin | Editare produs |
| `/admin/?page=products&action=new` | editor + admin | Produs nou |
| `/admin/?page=pages` | editor + admin | Lista pagini |
| `/admin/?page=pages&action=edit&id=5` | editor + admin | Editare pagină |
| `/admin/?page=users` | **admin only** | Gestiune utilizatori |
| `/admin/?page=users&action=edit&id=2` | **admin only** | Editare user |

---

## 5. Schema bazei de date

### Tabela `users`
```sql
CREATE TABLE users (
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
```

### Tabela `categories`
```sql
CREATE TABLE categories (
    id         SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug       VARCHAR(80)  NOT NULL UNIQUE,
    name       VARCHAR(120) NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Date inițiale (13 categorii)
INSERT INTO categories (slug, name, sort_order) VALUES
    ('acoperis','Acoperiș',1), ('izolatie','Izolație',2),
    ('gips-carton','Gips Carton',3), ('zidarie-bca','Zidărie BCA',4),
    ('gard-imprejmuiri','Gard & Împrejmuiri',5), ('electrice','Electrice',6),
    ('incalzire','Încălzire',7), ('sanitare','Sanitare',8),
    ('gradina','Grădină',9), ('mobilier','Mobilier',10),
    ('electrocasnice','Electrocasnice',11), ('scule-unelte','Scule & Unelte',12),
    ('apa-canal','Apă & Canal',13);
```

### Tabela `products`
```sql
CREATE TABLE products (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id       SMALLINT UNSIGNED NOT NULL,
    name              VARCHAR(200) NOT NULL,
    short_description TEXT         NULL DEFAULT NULL,
    image_path        VARCHAR(500) NULL DEFAULT NULL,  -- cale relativă: uploads/products/...
    image_alt         VARCHAR(200) NULL DEFAULT NULL,
    price             DECIMAL(10,2) UNSIGNED NULL DEFAULT NULL,
    price_unit        VARCHAR(40)  NULL DEFAULT NULL,  -- 'mp', 'ml', 'buc', 'sac 25kg'
    is_visible        TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_by        INT UNSIGNED NOT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    KEY idx_cat_sort (category_id, sort_order, is_visible)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Tabela `pages`
```sql
CREATE TABLE pages (
    id               SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug             VARCHAR(100) NOT NULL UNIQUE,
    title            VARCHAR(200) NOT NULL,
    content          LONGTEXT     NULL DEFAULT NULL,  -- HTML sanitizat
    meta_title       VARCHAR(160) NULL DEFAULT NULL,
    meta_description VARCHAR(320) NULL DEFAULT NULL,
    restricted       TINYINT(1)   NOT NULL DEFAULT 0, -- 1 = doar admin
    is_published     TINYINT(1)   NOT NULL DEFAULT 1,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by       INT UNSIGNED NULL DEFAULT NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Date inițiale
INSERT INTO pages (slug, title, restricted) VALUES
    ('despre-noi','Despre Noi',0), ('contact','Contact',0),
    ('politica-confidentialitate','Politica de Confidențialitate',1),
    ('termeni-conditii','Termeni și Condiții',1),
    ('politica-retur','Politica de Retur',1);
```

### Tabela `audit_log`
```sql
CREATE TABLE audit_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL DEFAULT NULL,
    entity_type VARCHAR(50)  NOT NULL,  -- 'product' | 'page' | 'user' | 'auth'
    entity_id   INT UNSIGNED NULL DEFAULT NULL,
    action      VARCHAR(40)  NOT NULL,  -- 'create' | 'update' | 'delete' | 'login'
    changes_json JSON        NULL DEFAULT NULL,  -- {"before":{...}, "after":{...}}
    ip_address  VARCHAR(45)  NULL DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_entity (entity_type, entity_id),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Tabela `login_attempts` (brute-force throttle)
```sql
CREATE TABLE login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip           VARBINARY(16) NOT NULL,  -- binar IPv4/IPv6
    attempted_at DATETIME NOT NULL,
    INDEX idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Sistem de permisiuni

Două roluri: `admin` și `editor`.

| Funcționalitate | Editor | Admin |
|----------------|--------|-------|
| Editare produse (imagine + descriere) | ✅ | ✅ |
| Editare pagini nerestricționate (despre noi, contact) | ✅ | ✅ |
| Editare pagini legale (politica, termeni, retur) | ❌ | ✅ |
| Gestiune utilizatori | ❌ | ✅ |
| Vizualizare audit log | ❌ | ✅ |

**Implementare:** Coloana `pages.restricted = 1` + verificare PHP înainte de orice operație:
```php
if ($page['restricted'] && $_SESSION['role'] !== 'admin') {
    http_response_code(403); exit;
}
```

---

## 7. Autentificare & sesiuni

```php
// La login — prevenire session fixation
session_regenerate_id(true);
$_SESSION['user_id']    = $user['id'];
$_SESSION['role']       = $user['role'];
$_SESSION['ip']         = $_SERVER['REMOTE_ADDR'];
$_SESSION['created_at'] = time();
```

- Session cookie: `httponly=true`, `secure=true`, `samesite=Strict`
- Timeout: 2 ore de inactivitate, 8 ore absolute
- IP + User-Agent binding: dacă se schimbă în cursul sesiunii → logout forțat

---

## 8. Securitate

| Vector | Măsură |
|--------|--------|
| Brute force | 5 încercări / 15 min per IP, tabel `login_attempts` |
| Session hijacking | IP+UA binding, `session_regenerate_id` la login |
| CSRF | Token `random_bytes(32)` per sesiune, rotit după fiecare POST |
| Upload malicious | `finfo` MIME real + `getimagesize` + rename UUID + PHP blocat în `/uploads/` |
| XSS | HTMLPurifier (whitelist) pentru textarea produse/pagini |
| SQL injection | PDO prepared statements, `EMULATE_PREPARES=false` |
| Path traversal | `realpath` + `basename`, inputul userului nu apare în calea finală |
| Parole | `password_hash(PASSWORD_BCRYPT, ['cost'=>12])` |

---

## 9. Upload imagini

Fluxul complet de validare (în ordine):
1. `UPLOAD_ERR_OK` verificat
2. Dimensiune max: 2MB
3. Extensie: doar `jpg`, `jpeg`, `png`, `webp`, `gif`
4. MIME real cu `finfo` (nu `$_FILES['type']`)
5. Concordanță extensie ↔ MIME
6. `getimagesize()` — confirmă că e imagine validă
7. Dimensiuni: 10px–6000px per latură
8. Rename aleatoriu: `bin2hex(random_bytes(16)) . '.' . $ext`
9. `move_uploaded_file()` în `/uploads/products/`

---

## 10. Trecerea paginilor HTML la PHP

Există **două tipuri** de pagini cu logici diferite:

| Tip | Exemple | Sursă date | Template |
|-----|---------|-----------|----------|
| Pagini statice | despre-noi, contact, politica, termeni | Tabela `pages.content` | `page.php` |
| Pagini categorie | acoperis, izolatie, gips-carton, ... | Tabela `products` filtrat by `category_id` | `category.php` |

### `.htaccess` principal
```apache
RewriteEngine On
RewriteRule ^admin/ - [L]
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]
RewriteRule ^([a-z0-9-]+)\.html?$ /router.php?slug=$1 [L,QSA]
RewriteRule ^([a-z0-9-]+)/?$      /router.php?slug=$1 [L,QSA]
RewriteRule ^$ /index.php [L]
```

### `router.php` — decide ce template să încarce
```php
$slug = preg_replace('/[^a-z0-9-]/', '', $_GET['slug'] ?? '');

// Verifică dacă slug-ul e o categorie de produse
$stmt = $db->prepare('SELECT id FROM categories WHERE slug = ? AND is_active = 1');
$stmt->execute([$slug]);
$category = $stmt->fetch();

if ($category) {
    // Pagină categorie — citește produse din DB
    $products = /* SELECT * FROM products WHERE category_id = ? AND is_visible = 1 */;
    include 'templates/category.php';
} else {
    // Pagină statică — citește conținut HTML din pages
    $stmt = $db->prepare('SELECT * FROM pages WHERE slug = ? AND is_published = 1');
    $stmt->execute([$slug]);
    $page = $stmt->fetch();
    if (!$page) { http_response_code(404); include '404.php'; exit; }
    include 'templates/page.php';
}
```

URL-urile existente (`/acoperis.html`, `/despre-noi.html`) continuă să funcționeze fără modificare.

---

## 11. Editor WYSIWYG

TinyMCE gratuit, inclus din CDN în views admin. Necesită înregistrare gratuită pe [tiny.cloud](https://www.tiny.cloud) pentru a obține un API key (fără plată pentru site-uri mici):
```html
<script src="https://cdn.tiny.cloud/1/YOUR_API_KEY/tinymce/6/tinymce.min.js"></script>
```
Alternativă fără cont: TinyMCE self-hosted (download zip, copiat în `/admin/assets/tinymce/`).

Configurare minimă: permite doar `p, br, strong, em, ul, ol, li, h2, h3, a, img`. Output-ul este pasat prin HTMLPurifier înainte de salvare în DB.

---

## 12. Ce NU se schimbă

- Designul vizual (CSS, layout, componente) — identic
- URL-urile publice — identice
- Fișierele `css/`, `img/`, `js/` — neatinse
- Google Analytics, meta tags SEO — rămân în template PHP

---

## 13. Unelte necesare suplimentar

| Unealtă | Status | Note |
|---------|--------|------|
| PHP 7.4+ | ✅ deja pe hosting | |
| MySQL | ✅ deja pe hosting | Creare DB + user din cPanel |
| phpMyAdmin | ✅ deja în cPanel | Import schema SQL |
| PHP GD | ✅ inclus de regulă | Pentru resize imagini opțional |
| TinyMCE | ✅ gratis CDN | Fără instalare |
| HTMLPurifier | Manual (un folder) | Download zip, fără Composer |
| **Nimic plătit** | — | — |
