# Technology Stack

**Analysis Date:** 2026-10-13

## Languages

**Primary:**
- PHP 8.1 (production, cPanel `ea-php81` on LiteSpeed; `declare(strict_types=1)` used in `db/deploy_db.php`) - all server-side code: `router.php`, `catalog.php`, `includes/*.php`, `admin/**/*.php`, `db/*.php`, `templates/*.php`, and the product pages `acoperis.php`, `izolatie.php`, `gips-carton.php`, etc.
- PHP 8.3 locally (Laragon). Per `docs/JURNAL-LUCRU.md`, code must stay compatible with 8.1 (server version).
- SQL (MySQL/MariaDB dialect): `db/schema.sql`, `db/migration_002_hierarchy.sql` through `db/migration_006_wa_text.sql`, `db/productie_date.sql`.

**Secondary:**
- HTML5 - static pages at repo root (`index.html`, `contact.html`, `despre-noi.html`, `politica-*.html`, `termeni-conditii.html`, `finantari-europene/index.html`) and PHP templates in `templates/`.
- CSS - `css/style.css` (public site), `admin/assets/admin.css` (admin panel). No preprocessor.
- Vanilla JavaScript (ES5/ES6 style, no framework, no bundler) - `js/main.js`. Inline JS in `admin/views/pages/edit.php` (TinyMCE init) and `admin/views/products/edit.php` (image preview).
- Inline SVG icons for product cards (`includes/product_icons.php`, `db/extract_icons.php`).

## Runtime

**Environment:**
- Production: PHP 8.1.34 under LiteSpeed (cPanel shared hosting, user `mugurel`, docroot `/home/mugurel/public_html/`). Confirmed in `docs/JURNAL-LUCRU.md` (diagnostic `__diag.php` run, 8.1.34).
- Local development: Laragon (Apache + PHP 8.3 + MariaDB/MySQL) on Windows. Vhost config referenced at `C:\laragon\etc\apache2\sites-enabled\mugurel.conf` (outside the repo). Repo is linked into `C:\laragon\www\mugurel` via a junction (see `docs/JURNAL-LUCRU.md`).
- Database engine: InnoDB, charset `utf8mb4`, collation `utf8mb4_unicode_ci` (`db/schema.sql`). Exact server version in production is not recorded in the repo; the journal requires "MySQL 8.x or MariaDB".

**Package Manager:**
- None. There is no `composer.json`, `package.json`, `requirements.txt`, or lockfile in the repository. PHP has no third-party libraries; all code is first-party.
- Lockfile: Not applicable.

## Frameworks

**Core:**
- No PHP framework. Custom lightweight MVC-style admin in `admin/` (front controller `admin/index.php`, controllers in `admin/controllers/`, models in `admin/models/`, views in `admin/views/`). Public site uses procedural PHP pages plus `router.php` for dynamic slugs.
- Routing: Apache `mod_rewrite` rules in `.htaccess` map `/slug.html` and `/slug` to `router.php?slug=...`, and `/slug.html` to `slug.php` when that file exists.
- PDO (built-in) for database access: `admin/models/Database.php` (singleton, `ERRMODE_EXCEPTION`, `EMULATE_PREPARES = false`).

**Testing:**
- No test framework. Tests are plain PHP scripts with a local `ok()` assertion helper in `admin/tests/test_db.php`, `test_helpers.php`, `test_product_grid.php`, `test_products_repo.php`, `test_sanitize.php`. Run directly with `php`.

**Build/Dev:**
- No build step. Files are deployed as-is.
- Local server: Laragon (Apache + PHP + MariaDB) - `docs/JURNAL-LUCRU.md`.
- Deploy hook: cPanel Git Version Control + `.cpanel.yml` (see Configuration).

## Key Dependencies

**Critical (external, loaded in the browser, not installed via a package manager):**
- Google Analytics 4 (gtag.js), measurement ID `G-R6M7YYLB48` - loaded in `<head>` of `index.html`, `contact.html`, `despre-noi.html`, `politica-confidentialitate.html`, `politica-retur.html`, and `templates/header.php`.
- Microsoft Clarity, project ID `y5btegvf2j` - inline loader in `templates/header.php` and `index.html`.
- Google Fonts (Roboto Condensed, Open Sans) - `<link>` in `templates/header.php` and `index.html`.
- TinyMCE 6 from Tiny Cloud (`https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js`) - rich-text editor only in `admin/views/pages/edit.php`.

**Infrastructure (server-side, all built into PHP or the host):**
- PDO + `pdo_mysql` extension - database access.
- `fileinfo` extension (`finfo`) and `getimagesize()` - image upload validation in `admin/helpers/Upload.php`.
- `random_bytes()` - CSRF tokens (`admin/helpers/Csrf.php`) and upload filenames.
- `password_hash` / `password_verify` with bcrypt cost 12 - `admin/models/User.php`, `admin/controllers/AuthController.php`.
- PHP sessions (`session_*`) - `admin/helpers/Auth.php`.

## Configuration

**Environment:**
- No `.env` mechanism. Database credentials are PHP constants defined in `admin/config.php`. If `admin/config.local.php` exists it is loaded instead of the placeholder defaults (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`). `admin/config.local.php` is git-ignored (`.gitignore`) and is edited by hand on the server; `db/deploy_db.php` reads it from `<public_html>/admin/config.local.php`.
- Other constants in `admin/config.php`: `UPLOAD_MAX_BYTES` (2 MB), `UPLOAD_DIR`, `UPLOAD_WEB`, `SITE_URL` (`https://mugurel-bricolaj.ro`), session timeouts (`SESSION_TIMEOUT_IDLE` 7200 s, `SESSION_TIMEOUT_ABSOLUTE` 28800 s), `LOGIN_MAX_ATTEMPTS` 5, `LOGIN_WINDOW_MINUTES` 15.
- WhatsApp contact number constant `WA_PHONE` in `includes/product_grid.php` (value is a public business number, not a secret).
- Session cookie hardening is set at runtime in `admin/helpers/Auth.php` (`cookie_httponly`, `cookie_secure`, `cookie_samesite=Strict`, `use_strict_mode`).
- Never commit or quote the contents of `admin/config.local.php`. Only the path is referenced in the repo.

**Build / Server config:**
- `.htaccess` (repo root): cPanel PHP handler line (`AddHandler application/x-httpd-ea-php81`), URL rewrite rules, HTTPS-independent security headers (HSTS, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, Content-Security-Policy). Contains a `<IfModule php_module>` block for local Laragon use.
- `admin/.htaccess`: blocks direct access to `models|helpers|views|controllers|tests`, blocks `.sql/.log/.env/.ini/.json/.md`, forces HTTPS, admin-specific CSP (allows `https://cdn.tiny.cloud`).
- `uploads/.htaccess`: disables PHP execution in uploads (`uploads/products/`).
- `.cpanel.yml`: cPanel deployment task list. Copies `css`, `img`, `js`, `admin`, `includes`, `templates`, `uploads`, `finantari-europene`, `router.php`, `.htaccess`, `*.html`, `*.txt`, `*.xml`, `*.svg`, `*.php` to `/home/mugurel/public_html/`, removes static `.html` files that have a matching `.php`, deletes `__diag.php`, then runs `db/deploy_db.php` with the PHP 8 CLI binary (`/opt/cpanel/ea-php81/root/usr/bin/php` preferred). Output goes to `__deploy_m7x2verif.log` in the docroot.
- `db/deploy_db.php`: applies migrations 002-006 idempotently and imports `db/productie_date.sql` once, only when `products` is empty.
- `.gitignore`: ignores `admin/config.local.php` and uploaded product images (`uploads/products/*`, keeping `.gitkeep`).

**Analytics / front-end config:**
- GA4 and Clarity IDs are hard-coded in HTML/PHP templates (listed above). Changing them requires editing each file, and the CSP in `.htaccess` must allow the origin (`googletagmanager.com`, `google-analytics.com`, `clarity.ms`, `*.clarity.ms`, `c.bing.com`).

## Platform Requirements

**Development:**
- Windows with Laragon Full (Apache, PHP 8.x, MariaDB/MySQL 8.x). PHP and MySQL binaries must be on PATH (`C:\laragon\bin\php\php-<version>-Win32-vs16-x64`, `C:\laragon\bin\mysql\mysql-<version>-winx64\bin`).
- Local database `mugurel_cms` is not in git; rebuild with the scripts in `db/` (see `docs/JURNAL-LUCRU.md`).
- Git repository: `capitalboost/mugurel-website`, branch `main` (production deploys from the branch selected in cPanel; the journal notes `feat/catalog-db` was live at one point).

**Production:**
- Host: cPanel shared hosting with LiteSpeed, PHP 8.1 (`ea-php81`), MySQL/MariaDB.
- Edge: Cloudflare in front of the site (CDN/caching). After deploys, cache for JS/CSS must be purged manually (Cloudflare "Purge Everything"), per `docs/JURNAL-LUCRU.md`.
- Domain: `https://mugurel-bricolaj.ro`.

---

*Stack analysis: 2026-10-13*
