# External Integrations

**Analysis Date:** 2026-10-13

## APIs & External Services

**Messaging / Contact:**
- WhatsApp (click-to-chat, `wa.me`) - primary order channel. Every product and category CTA builds a link `https://wa.me/40749130565?text=...` (`includes/product_grid.php`, `templates/footer.php`, `js/main.js`). No API or SDK; purely URL-based. Pre-filled text is per product (`wa_text` column, editable in admin).
- Facebook page link - `https://www.facebook.com/byMUGUREL` in `templates/footer.php`, `index.html` (footer and JSON-LD `sameAs`). Link only, no SDK.

**Editorial / Content:**
- TinyMCE 6 (Tiny Cloud CDN, `no-api-key` mode) - rich-text editor for static pages in `admin/views/pages/edit.php`. Loaded from `https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js`. No API key is configured; the admin CSP in `admin/.htaccess` allows `https://cdn.tiny.cloud`.

**Public Funding Reference (links only):**
- Ministry of European Funds (MFE) - `https://www.mfe.gov.ro/` linked from `templates/header.php` and `index.html` (EU co-financing notice). Also referenced in `finantari-europene/index.html`.
- `https://www.adroltenia.ro` - referenced once in site content (outbound link only).

**Developer credit:**
- `https://capitalboost.ro/` - "Built by CapitalBoost.ro" in `templates/footer.php` and `index.html`.

**Structured data / SEO references (not network calls):**
- schema.org JSON-LD in `index.html` and PHP templates; `http://www.sitemaps.org` namespace in `sitemap.xml`.
- `robots.txt`, `sitemap.xml`, `llms.txt` at repo root.

## Analytics & Tracking

**Google Analytics 4:**
- Measurement ID `G-R6M7YYLB48`.
- Loaded via `https://www.googletagmanager.com/gtag/js?id=G-R6M7YYLB48` with inline `gtag('config', ...)`.
- Present in: `index.html`, `contact.html`, `despre-noi.html`, `politica-confidentialitate.html`, `politica-retur.html`, `templates/header.php` (used by all dynamic pages served through `router.php`, `catalog.php` and product pages).
- Connect-src/script-src allowed in `.htaccess` CSP: `www.googletagmanager.com`, `www.google-analytics.com`.

**Microsoft Clarity:**
- Project ID `y5btegvf2j`, inline loader script in `<head>` of `index.html` and `templates/header.php` (and the other static pages per `docs/JURNAL-LUCRU.md`).
- Requires `https://www.clarity.ms` and `https://*.clarity.ms` in CSP `script-src`/`connect-src` (`.htaccess`). Missing these previously blocked data (see the 20 August 2026 entry in `docs/JURNAL-LUCRU.md`).
- The `/admin` area is intentionally not tracked (per journal).

**Bing / Microsoft:**
- `https://c.bing.com` allowed in `connect-src` of the CSP in `.htaccess` (UET/Clarity-related). No Bing tag was found in page source.

**Fonts:**
- Google Fonts CSS (`https://fonts.googleapis.com`) and font files (`https://fonts.gstatic.com`) - Roboto Condensed and Open Sans, loaded in `templates/header.php` and `index.html` with `preconnect`.

**Error tracking:** Not detected. No Sentry or similar SDK.

## Data Storage

**Databases:**
- MySQL / MariaDB (InnoDB, `utf8mb4_unicode_ci`).
  - Database name: `mugurel_cms` (default in `admin/config.php`).
  - Connection: PHP constants `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, read from `admin/config.local.php` on the server (not in git). Defaults in `admin/config.php` are placeholders.
  - Client: PDO via singleton `Database::get()` in `admin/models/Database.php`. DSN `mysql:host=...;dbname=...;charset=utf8mb4`.
  - Schema: `db/schema.sql` (tables `users`, `categories`, `products`, `pages`, `audit_log`, `login_attempts`); migrations `db/migration_002_hierarchy.sql` to `db/migration_006_wa_text.sql`; catalog data `db/productie_date.sql`.
  - Public read path: `includes/products_repo.php` and `includes/db.php` (degrades to empty results on DB failure, pages still render).
  - Local DB is not in git and must be rebuilt from `db/` scripts.

**File Storage:**
- Local filesystem only. Product images uploaded via admin go to `uploads/products/` (`UPLOAD_DIR` in `admin/config.php`), served at `/uploads/products/` (`UPLOAD_WEB`). Validation in `admin/helpers/Upload.php` (2 MB max, jpg/png/webp/gif, MIME and `getimagesize` checks). PHP execution is blocked in `uploads/.htaccess`.
- Static assets (`img/`, `css/`, `js/`, `img/proiect-digitalizare/` PDF and PNG) are served directly from the repo tree.

**Caching:**
- Cloudflare (edge cache in front of `mugurel-bricolaj.ro`). Not configured in the repo; purge is manual (Cloudflare dashboard, "Purge Everything"), per `docs/JURNAL-LUCRU.md`.
- No application-level cache (no Redis/Memcached). Session storage is PHP's default file handler.

## Authentication & Identity

**Auth Provider:**
- Custom. No third-party identity provider.
  - Implementation: username/password against the `users` table, bcrypt hashes (cost 12) via `password_verify` in `admin/controllers/AuthController.php`. Dummy hash check when user is not found (timing mitigation). Rehash on login when cost changes.
  - Brute-force protection: `login_attempts` table, `LOGIN_MAX_ATTEMPTS` = 5 within `LOGIN_WINDOW_MINUTES` = 15 (`admin/config.php`).
  - Sessions: `admin/helpers/Auth.php` - `session_regenerate_id(true)` on login, idle timeout 2 h, absolute timeout 8 h, session bound to IP and User-Agent, cookie flags `HttpOnly`, `Secure`, `SameSite=Strict`.
  - CSRF: `admin/helpers/Csrf.php` - 64-hex token in session, required on all admin POST forms (`Csrf::verify()` in controllers).
  - Admin user seeding: `db/seed_admin.php`.
  - Admin access: `/admin/` via `admin/index.php`; all pages except `login`/`logout` require login (`Auth::requireLogin()`).

**Public site:** No user accounts, no customer login, no payment.

## Monitoring & Observability

**Error Tracking:** None detected (no Sentry, Bugsnag, Rollbar).

**Logs:**
- Deploy log: `.cpanel.yml` writes all output of `db/deploy_db.php` to `__deploy_m7x2verif.log` in the docroot (readable via browser at `https://mugurel-bricolaj.ro/__deploy_m7x2verif.log`).
- Admin audit trail: `audit_log` table (`db/schema.sql`).
- Login attempt log: `login_attempts` table.
- Web server logs: LiteSpeed/cPanel (not in repo).

**Behavioral analytics:** GA4 and Microsoft Clarity (see Analytics above).

## CI/CD & Deployment

**Hosting:**
- cPanel shared hosting, LiteSpeed web server, PHP 8.1 (`ea-php81`), home `/home/mugurel/public_html/`.
- Domain `https://mugurel-bricolaj.ro`, CDN/proxy Cloudflare.

**Source control:**
- GitHub: `capitalboost/mugurel-website` (remote for the `Website/` repo). Branch `main` is the intended production branch; `feat/catalog-db` was used for the live catalog rollout (journal, 29 September 2026).

**Deploy pipeline:**
- cPanel Git Version Control → "Deploy HEAD Commit" triggers `.cpanel.yml` (manual click from cPanel; a GitHub push alone does not publish the site). Source: `.cpanel.yml`.
- After deploy: Cloudflare "Purge Everything" for JS/CSS changes, then hard refresh (per `docs/JURNAL-LUCRU.md`).
- CI pipeline: Not detected (no GitHub Actions, no workflow files in the repo).

## Environment Configuration

**Required configuration (server):**
- `admin/config.local.php` on the server with real `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` (values are not recorded here; the file is hand-edited on the server).
- PHP extensions expected: `pdo_mysql`, `fileinfo`, `gd` or equivalent for `getimagesize`, `session`, `mbstring` (standard on cPanel PHP 8.1).

**Required configuration (local, Laragon):**
- Laragon Full with PHP 8.x and MariaDB/MySQL 8.x on PATH.
- Local DB `mugurel_cms` rebuilt from `db/` scripts.
- `admin/config.local.php` optional locally; defaults in `admin/config.php` are placeholders and must be overridden for a working DB connection.

**Secrets location:**
- Database credentials: `admin/config.local.php` (server only, git-ignored). Do not copy them into documents, commits, or chat.
- Admin password hashes: `users` table in the database.
- No API keys are stored in the repository (TinyMCE uses the keyless Tiny Cloud endpoint; GA4/Clarity IDs are public identifiers).

## Webhooks & Callbacks

**Incoming:**
- None. No webhook endpoints. The only server-side HTTP endpoints are the site pages, `router.php`, and admin routes under `admin/index.php`.

**Outgoing:**
- None from PHP. The server makes no outbound HTTP calls (no `curl_*`, `file_get_contents` with URLs, or mail sending were found in the codebase).
- Browser-side outbound: WhatsApp links, Facebook/MFE/CapitalBoost links, GA4/Clarity/Bing telemetry, Google Fonts, TinyMCE CDN (admin only).

**Email:** Not integrated. Contact is by WhatsApp and the contact page form (`contact.html`), with no server-side mailer detected in the repository.

---

*Integration audit: 2026-10-13*
