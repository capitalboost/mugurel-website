<!-- refreshed: 2026-10-13 -->
# Architecture

**Analysis Date:** 2026-10-13

## System Overview

```text
┌──────────────────────────────────────────────────────────────────────┐
│                       Public site (Apache + PHP 8.1)                 │
├───────────────────┬──────────────────────┬───────────────────────────┤
│ Static HTML pages │ Category pages       │ Catalog                    │
│ index.html, etc.  │ `acoperis.php` ...   │ `catalog.php`              │
│                   │ (13 files, *.php)    │                            │
└─────────┬─────────┴──────────┬───────────┴─────────────┬─────────────┘
          │                    │ includes                 │
          │                    ▼                          ▼
          │    ┌─────────────────────────────────────────────────────┐
          │    │ `includes/page_bootstrap.php` → `section()`          │
          │    │ `includes/product_grid.php`   (HTML render only)     │
          │    │ `includes/product_icons.php`  (icon render)          │
          │    │ `includes/products_repo.php`  (SQL only, [] on err)  │
          │    └──────────────────────────┬──────────────────────────┘
          │                               │ PDO
          │                               ▼
          │    ┌─────────────────────────────────────────────────────┐
          │    │ `admin/models/Database.php` (singleton PDO, MySQL)   │
          │    └──────────────────────────┬──────────────────────────┘
          │                               ▼
          │                  MySQL: categories, products,
          │                  product_categories, product_properties, pages
          │
          └── Fallback router: `router.php` → `templates/category.php`
              or `templates/page.php` (DB-driven, no .php file needed)

┌──────────────────────────────────────────────────────────────────────┐
│                    Admin panel  `/admin/` (separate front controller) │
│  `admin/index.php` → routes → `admin/controllers/*` → `admin/models/*`│
│                       → `admin/views/*` (layout.php wraps bodies)     │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│ Ops: `db/*.php` migrations/seeds, `db/deploy_db.php` run by `.cpanel.yml` │
└──────────────────────────────────────────────────────────────────────┘
```

## Pattern Overview

**Overall:** Procedural PHP multi-entry site (no framework, no Composer, no autoloader). Each public page is a standalone script that `require_once`s a shared bootstrap. Data is read through a thin repository of functions over a PDO singleton. HTML is partly static (`*.html`) and partly server-rendered from MySQL.

**Key Characteristics:**
- No MVC for the public site. Pages are `*.php` scripts that call `section()` (defined in `includes/page_bootstrap.php`) and echo HTML.
- The admin panel (`admin/`) is the only place that follows a controller/model/view split, with a hand-written front controller in `admin/index.php`.
- Strict separation inside `includes/`: `products_repo.php` is SQL-only and returns `[]` on any DB error; `product_grid.php` is HTML-only and contains no SQL. This is an explicit rule from the file docblocks and must be kept.
- Graceful degradation is a deliberate design goal: every repo function catches `Throwable`, logs via `error_log`, and returns an empty value so pages keep their editorial content when MySQL is down (see `docs/JURNAL-LUCRU.md`, section "Degradare eleganta").
- Per-request memoization: `pageProductsIndexed()` in `includes/products_repo.php` uses a `static $cache` so a whole page costs one product query plus one property query.
- Configuration is a PHP constants file (`admin/config.php`) with an optional, gitignored override (`admin/config.local.php`).

## Layers

**Public presentation (static + PHP pages):**
- Purpose: Serve the storefront pages in Romanian.
- Location: repo root (`index.html`, `contact.html`, `despre-noi.html`, `politica-*.html`, `termeni-conditii.html`, `finantari-europene/index.html`) and root `*.php` category pages.
- Contains: HTML with inline JSON-LD, GTM/Clarity snippets, and `section(...)` calls.
- Depends on: `includes/page_bootstrap.php`, `templates/header.php`, `templates/footer.php`.
- Used by: Browsers, via Apache rewrites defined in `.htaccess`.

**Page bootstrap / render helpers:**
- Purpose: Turn DB rows into card HTML for a named section of a page.
- Location: `includes/page_bootstrap.php`, `includes/product_grid.php`, `includes/product_icons.php`.
- Contains: `section()`, `renderMaterialCards()`, `renderAccessoryCards()`, `renderEmptyNotice()`, `renderIcon()`, `waLink()`, `priceLabel()`, `e()` (htmlspecialchars wrapper).
- Depends on: `includes/products_repo.php`.
- Used by: Category `*.php` pages and `catalog.php` (indirectly).

**Data access (repository):**
- Purpose: All read queries for public pages.
- Location: `includes/products_repo.php`. Connection: `admin/models/Database.php` (also required via `includes/db.php`).
- Contains: `productsBaseSql()` (shared SELECT that derives `cat_slugs`, `subcat_slug`, `cat_label`), `pageProductsIndexed()`, `productsForSection()`, `productsForPage()`, `allProducts()`, `catalogProducts()`, `catalogCount()`, `catalogWhere()`, `categoryTree()`, `attachProperties()`.
- Depends on: PDO via `Database::get()`.
- Used by: `includes/page_bootstrap.php`, `catalog.php`, `router.php` (through `Database::get()` directly), admin product controller (`categoryTree()`, `attachProperties` path).

**Persistence:**
- Purpose: MySQL/MariaDB store (DB not in git).
- Location: `db/schema.sql` (base tables), `db/migration_002..006*.sql` (incremental), `db/*.php` (import/seed scripts).
- Contains: `categories` (two-level tree via `parent_id`), `products`, `product_categories` (cross-listing), `product_properties`, `pages`, `users`, `audit_log`, `login_attempts`.
- Depends on: Nothing in-app.
- Used by: Everything above.

**Admin panel:**
- Purpose: CMS for products, static pages, and users, behind login.
- Location: `admin/` (`index.php`, `controllers/`, `models/`, `helpers/`, `views/`, `assets/`).
- Contains: Front controller, session/auth, CSRF, flash messages, upload, sanitization.
- Depends on: `admin/models/Database.php`, `includes/products_repo.php` and `includes/product_icons.php` (reused by products controller).
- Used by: Mugurel (site owner) through `/admin/`.

**Templates:**
- Purpose: Shared chrome and two DB-driven page layouts.
- Location: `templates/header.php`, `templates/footer.php`, `templates/category.php`, `templates/page.php`.
- Contains: `category.php` renders a simple product grid from `$products`; `page.php` renders `$pageData['content']` (HTML sanitized on save by `Sanitize::html()`).
- Depends on: Variables set by the caller (`router.php`).
- Used by: `router.php` only.

## Data Flow

### Category page with its own PHP file (primary path, e.g. `/acoperis.html`)

1. Apache rewrites `/acoperis.html` to `acoperis.php` when that file exists (`.htaccess`, rule "/slug.html serveste slug.php").
2. `acoperis.php` requires `includes/page_bootstrap.php` (`acoperis.php:1`), which requires `includes/products_repo.php` and `includes/product_grid.php`.
3. Each `section('acoperis', '<section_key>', '<kind>', ...)` call (`acoperis.php:219+`) reads `pageProductsIndexed('acoperis')[$key][$kind]`.
4. The first call runs one SQL query on `products JOIN categories` with `page_slug = ?` (`includes/products_repo.php:134`), then `attachProperties()` runs one query on `product_properties`. Result is cached in a static array for the request.
5. Modifier/limit/offset are applied in PHP on the cached subset (`includes/page_bootstrap.php`).
6. Rows go to `renderMaterialCards()` or `renderAccessoryCards()` in `includes/product_grid.php`, which return HTML strings that the page echoes inline.
7. If the query fails or returns nothing, `renderEmptyNotice()` substitutes a WhatsApp prompt; the rest of the page still renders.

### Catalog page (`/catalog.html`)

1. `catalog.php` requires `includes/page_bootstrap.php` (`catalog.php:2`), reads `cat`, `q`, `page` from `$_GET`.
2. `catalogCount()` and `catalogProducts()` apply the same `catalogWhere()` filter (category at level 1 or 2, cross-listed via `product_categories`, text search on name/short_description). Page size is `CATALOG_PER_PAGE = 60` (`catalog.php:4`).
3. `categoryTree()` supplies the sidebar/chips.

### DB-driven fallback page (`/slug` or `/slug.html` with no matching `.php`)

1. `.htaccess` rule `^([a-z0-9][a-z0-9-]*)\.html?$ → /router.php?slug=$1`.
2. `router.php` sanitizes `slug`, then:
   - If `categories` has an active row with that slug, renders `templates/category.php` with a flat `$products` list (`SELECT ... FROM products WHERE category_id = ? AND is_visible = 1`). This path does not use `products_repo.php`.
   - Else if `pages` has a published row, renders `templates/page.php`.
   - Else responds 404 (uses `404.html` if present, which is not in the repo).

### Admin save (products)

1. Browser POSTs to `/admin/?page=products&action=save`.
2. `admin/index.php` applies `Sanitize::slug()`, enforces `Auth::requireLogin()`, and dispatches to `admin/controllers/ProductsController.php`.
3. Controller calls `Csrf::verify()`, `Sanitize::posInt()`, handles the image via `Upload::image()` (2 MB limit from `admin/config.php`), then writes through `admin/models/Product.php` and `admin/models/ProductProperty.php`.
4. Change is visible on the public site on the next request (no cache layer), because public pages query the DB directly.

### Deployment flow

1. `git push` to the branch cPanel tracks does not publish by itself. Deploy is triggered through cPanel "Deploy" using `.cpanel.yml`.
2. `.cpanel.yml` copies directories (`css`, `img`, `js`, `admin`, `includes`, `templates`, `uploads`, `finantari-europene`) and root files (`router.php`, `.htaccess`, `*.php`, `*.html`, `*.xml`, `*.txt`, `*.svg`) into `/home/mugurel/public_html/`.
3. It then removes `.html` files that have a same-named `.php` sibling, so the `.php` version wins.
4. It runs `db/deploy_db.php` with a PHP 8.x binary, logging to `__deploy_m7x2verif.log` in the deploy path.

**State Management:**
- Server: no session store beyond PHP sessions for admin (`admin/helpers/Auth.php`, `Auth::startSecureSession()`). Public pages are stateless.
- Client: Front-end interactivity is in `js/main.js`; no client framework.

## Key Abstractions

**`section()` (page section renderer):**
- Purpose: One call per HTML grid on a category page.
- Examples: `includes/page_bootstrap.php`; used in `acoperis.php`, `izolatie.php`, `gips-carton.php`, and the other category `*.php` files.
- Pattern: Function that maps `(page_slug, section_key, card_kind, limit, offset, modifier)` to HTML. The `$modifier` argument distinguishes two card styles sharing one `section_key` (`''` = NULL modifier only, `null` = no filter).

**Product row contract:**
- Purpose: The shape every repo function returns.
- Examples: `includes/products_repo.php` (`productsBaseSql()`).
- Pattern: Associative array with `id`, `name`, `short_description`, `wa_text`, `icon_key`, `icon_label`, `image_path`, `image_alt`, `badge_label`, `badge_kind`, `card_kind`, `card_modifier`, `section_key`, `price`, `price_unit`, `cat_slugs`, `subcat_slug`, `cat_label`, plus `properties` (list of `label`, `body`, `is_list`).

**`Database` singleton:**
- Purpose: One PDO connection per request.
- Examples: `admin/models/Database.php`.
- Pattern: `Database::get()` lazily builds PDO with `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, real prepares, utf8mb4. Constructor and clone are private.

**Admin models:**
- Purpose: Static-method data access for admin.
- Examples: `admin/models/Product.php`, `admin/models/Page.php`, `admin/models/User.php`, `admin/models/ProductProperty.php`.
- Pattern: Static classes with prepared statements; `Product::KNOWN_PAGE_SLUGS` and `Product::KNOWN_BADGE_KINDS` are the allow-lists for form values.

**Page slug / section key:**
- Purpose: Binds DB products to a page and a grid on that page.
- Examples: `products.page_slug` values listed in `Product::KNOWN_PAGE_SLUGS` (14 values: 13 categories plus `catalog`); section keys such as `tipuri-de-invelitori` in `acoperis.php`.
- Pattern: Stringly-typed keys shared between DB rows and PHP calls. A typo silently yields the empty notice.

## Entry Points

**Root category and catalog pages:**
- Location: `acoperis.php`, `izolatie.php`, `gips-carton.php`, `zidarie-bca.php`, `gard-imprejmuiri.php`, `electrice.php`, `incalzire.php`, `sanitare.php`, `gradina.php`, `mobilier.php`, `electrocasnice.php`, `scule-unelte.php`, `apa-canal.php`, `catalog.php`.
- Triggers: Apache rewrite of `/<slug>.html` when the `.php` file exists.
- Responsibilities: Load bootstrap, call `section()` or `catalogProducts()`, echo full HTML page.

**Router:**
- Location: `router.php`.
- Triggers: `.htaccess` rules for `/<slug>.html`, `/<slug>/`, `/<slug>` without a matching `.php`.
- Responsibilities: Pick category or page template from DB; 404 otherwise.

**Static pages:**
- Location: `index.html`, `contact.html`, `despre-noi.html`, `politica-confidentialitate.html`, `politica-retur.html`, `termeni-conditii.html`, `finantari-europene/index.html`.
- Triggers: Direct request. Served by Apache without PHP.

**Admin front controller:**
- Location: `admin/index.php`.
- Triggers: `/admin/?page=<name>&action=<name>`.
- Responsibilities: Session start, login gate (only `login` and `logout` public), route to controller in `$routes`.

**Deploy hook:**
- Location: `.cpanel.yml` and `db/deploy_db.php`.
- Triggers: cPanel Git deployment.
- Responsibilities: Copy files, delete shadowed `.html`, run DB preparation script.

## Architectural Constraints

- **Threading:** Not applicable. Standard PHP-FPM / mod_php request model; no workers or queues.
- **Global state:** `Database::$instance` (static PDO singleton, `admin/models/Database.php`); `pageProductsIndexed()` static cache keyed by page slug (`includes/products_repo.php`); `CATALOG_PER_PAGE` global constant defined in `catalog.php`.
- **Circular imports:** None observed. `admin/controllers/ProductsController.php` requires `includes/products_repo.php`, which requires `admin/models/Database.php`; no reverse dependency from `includes/` into `admin/` controllers.
- **Include-path coupling:** Files use `__DIR__`-relative requires throughout. Moving a file out of root or `includes/` breaks its relative paths.
- **Deploy surface:** `.cpanel.yml` copies an explicit list of directories and root globs. A new top-level directory that is not added to that list will not reach production.
- **Two render paths for categories:** Category `*.php` files (sectioned grids, `page_slug` keyed) and `templates/category.php` (flat, `category_id` keyed) coexist. Changes to product visibility or ordering affect them differently.
- **Config secrets:** DB credentials are read from `admin/config.local.php` (gitignored, edited on server). The committed fallback in `admin/config.php` contains placeholders only.

## Anti-Patterns

### Hardcoded category pages alongside DB catalog

**What happens:** Each category page is its own `.php` file with hardcoded section keys and hardcoded editorial HTML (for example, `acoperis.php` lines 219–330 list `section()` calls by key).
**Why it's wrong:** Adding a category or renaming a section key needs a code edit and a deploy, and a mismatch fails silently to the empty notice.
**Do this instead:** Keep new section keys consistent with `products.section_key` values in the DB (check `Product::sectionsByPage()` in `admin/models/Product.php`). For a new category page, copy the pattern of `acoperis.php` (`require_once __DIR__ . '/includes/page_bootstrap.php'`, then `section(...)` calls), and add its slug to `Product::KNOWN_PAGE_SLUGS`.

### Duplicate `products_repo.php` SQL in the router

**What happens:** `router.php` runs its own SQL against `categories` and `products` instead of calling `includes/products_repo.php`.
**Why it's wrong:** Filters such as `is_visible`, cross-listing via `product_categories`, and property loading exist in the repo but not in the router path, so the two category render paths can drift.
**Do this instead:** Put new category-list queries in `includes/products_repo.php` and call them from `router.php` and `templates/category.php`.

## Error Handling

**Strategy:** Fail soft on the public site, fail loud in admin.

**Patterns:**
- Public repo functions wrap PDO calls in `try/catch (Throwable $e)` and call `error_log()`, then return `[]` or `0` (`includes/products_repo.php`).
- `attachProperties()` degrades to empty `properties` arrays on error.
- Admin controllers use `Flash::error()` and redirect back to the form (`admin/controllers/ProductsController.php`).
- Router returns 404 for unknown slugs and `die()`s for unknown admin pages (`admin/index.php`).

## Cross-Cutting Concerns

**Logging:** `error_log()` only. No logging framework. Deploy output goes to `__deploy_m7x2verif.log` in the deploy directory (`.cpanel.yml`).
**Validation:** `admin/helpers/Sanitize.php` (`slug`, `posInt`, `html`) for admin input; `Product::KNOWN_*` allow-lists for enums; `Upload::image()` for file checks.
**Authentication:** Session-based login in `admin/helpers/Auth.php` with idle (7200 s) and absolute (28800 s) timeouts, role checks (`Auth::requireRole()`), and login throttling (`LOGIN_MAX_ATTEMPTS` 5 in `LOGIN_WINDOW_MINUTES` 15, enforced via `login_attempts` table). CSRF tokens via `admin/helpers/Csrf.php`.
**Security headers:** Set in `.htaccess` (HSTS, CSP, X-Frame-Options, Permissions-Policy).
**Testing:** Hand-rolled PHP test scripts in `admin/tests/` (`test_products_repo.php`, `test_product_grid.php`, `test_sanitize.php`, `test_db.php`, `test_helpers.php`). No test runner config is present.

---

*Architecture analysis: 2026-10-13*
