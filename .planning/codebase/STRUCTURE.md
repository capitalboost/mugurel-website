# Codebase Structure

**Analysis Date:** 2026-10-13

## Directory Layout

```
Website/                          # repo root (git repo, branch feat/catalog-db deployed by cPanel)
├── *.php                         # 14 public PHP pages: 13 category pages + catalog.php
├── *.html                        # static pages (index, contact, despre-noi, politici, termeni)
├── router.php                    # DB-driven fallback for slugs without a .php file
├── .htaccess                     # Apache rewrites, cPanel PHP handler, security headers
├── .cpanel.yml                   # cPanel deploy tasks (copy + DB prep)
├── .gitignore                    # ignores admin/config.local.php and uploads/products/*
├── robots.txt, sitemap.xml, llms.txt, favicon.svg, favicon.png
├── includes/                     # shared PHP for public pages (render + repo)
├── templates/                    # header/footer + DB-driven category and page layouts
├── admin/                        # CMS: front controller, controllers, models, helpers, views, tests
├── db/                           # schema, migrations, import/seed/deploy scripts
├── css/style.css                 # single public stylesheet
├── js/main.js                    # single public script
├── img/                          # static images (hero.avif, proiect-digitalizare/ PDF)
├── uploads/products/             # admin-uploaded product photos (contents gitignored)
├── finantari-europene/index.html # static subsection page with its own folder
├── docs/                         # JURNAL-LUCRU.md (project log), superpowers specs and plans
├── .superpowers/sdd/             # review diffs and task reports (generated)
├── .planning/codebase/           # these mapping documents
└── .claude/worktrees/            # local git worktrees (tooling)
```

## Directory Purposes

**`includes/`:**
- Purpose: Code shared by public category pages.
- Contains: `page_bootstrap.php` (`section()`), `products_repo.php` (SQL only), `product_grid.php` (HTML only), `product_icons.php` (icon map and render helpers), `db.php` (one-line include of `admin/models/Database.php`).
- Key files: `includes/products_repo.php`, `includes/product_grid.php`, `includes/page_bootstrap.php`.

**`templates/`:**
- Purpose: Page chrome and DB-driven layouts used only by `router.php`.
- Contains: `header.php` (head, nav, analytics), `footer.php`, `category.php` (flat product grid), `page.php` (static HTML content from `pages.content`).
- Key files: `templates/header.php`, `templates/category.php`.

**`admin/`:**
- Purpose: Password-protected CMS for products, pages and users.
- Contains: `index.php` (front controller), `config.php` (constants, reads `config.local.php`), `config.local.php` (gitignored, server-only credentials), `.htaccess`.
- Subdirectories:
  - `controllers/`: `AuthController.php`, `DashboardController.php`, `ProductsController.php`, `PagesController.php`, `UsersController.php`.
  - `models/`: `Database.php`, `Product.php`, `ProductProperty.php`, `Page.php`, `User.php`.
  - `helpers/`: `Auth.php`, `Csrf.php`, `Flash.php`, `Sanitize.php`, `Upload.php`.
  - `views/`: `layout.php` (shell), `login.php`, `dashboard.php`, and `products/`, `pages/`, `users/` subfolders with `list.php` and `edit.php`.
  - `assets/admin.css`: admin styles.
  - `tests/`: five standalone PHP test scripts (`test_*.php`).

**`db/`:**
- Purpose: Database lifecycle. Not run by Apache; run by hand or by `.cpanel.yml`.
- Contains:
  - `schema.sql`: base tables and the 13 seeded categories.
  - `migration_002_hierarchy.sql` to `migration_006_wa_text.sql`: incremental schema changes (hierarchy, properties, images/badges, accessories, WhatsApp text).
  - `migrate_products.php`, `migrate_catalog_products.php`, `migrate_pages.php`: importers from static HTML/PHP content into DB.
  - `deploy_db.php`: idempotent prep run at deploy (`.cpanel.yml`).
  - `seed_admin.php`: creates the first admin user.
  - `extract_icons.php`: builds icon mapping data.
  - `productie_date.sql`: production data snapshot.
- Generated: `productie_date.sql` is a data dump (treat as generated). Database itself is not in git.

**`css/`, `js/`, `img/`:**
- Purpose: Static front-end assets, copied verbatim by `.cpanel.yml`.
- Contains: `css/style.css`, `js/main.js`, `img/hero.avif`, `img/proiect-digitalizare/`.

**`uploads/`:**
- Purpose: Target of admin image uploads (`UPLOAD_DIR` in `admin/config.php`).
- Contains: `uploads/.htaccess`, `uploads/products/.gitkeep`. Uploaded files are gitignored (`uploads/products/*`).

**`finantari-europene/`:**
- Purpose: Static EU-funding page in its own directory, copied as a folder by `.cpanel.yml`.
- Contains: `index.html`.

**`docs/`:**
- Purpose: Project knowledge.
- Contains: `JURNAL-LUCRU.md` (session log, read first), `superpowers/specs/` (design docs, e.g. `2026-08-27-catalog-db-design.md`), `superpowers/plans/` (implementation plans).

**`.superpowers/`, `.claude/`, `.planning/`:**
- Purpose: Tooling and workflow metadata, not site code. `.superpowers/sdd/` holds review diffs and task reports. `.claude/worktrees/` holds local worktrees.

## Key File Locations

**Entry Points:**
- `index.html`: home page (static).
- `catalog.php`: product catalog with pagination and filters.
- `acoperis.php` (and the other 12 category files): category pages built from DB sections.
- `router.php`: fallback for DB-only categories and pages.
- `admin/index.php`: admin front controller.

**Configuration:**
- `admin/config.php`: site constants (`SITE_URL`, `UPLOAD_*`, session timeouts, login limits). DB defaults are placeholders.
- `admin/config.local.php`: real DB credentials. Gitignored. Do not read or copy into docs.
- `.htaccess`: URL rewrites and security headers.
- `.cpanel.yml`: deploy file list.

**Core Logic:**
- `includes/products_repo.php`: all public read queries.
- `includes/page_bootstrap.php`: `section()`.
- `includes/product_grid.php`: card HTML.
- `admin/controllers/ProductsController.php`: product CRUD and upload handling.
- `admin/models/Product.php`: product persistence and allow-lists.

**Testing:**
- `admin/tests/test_products_repo.php`: repo functions.
- `admin/tests/test_product_grid.php`: card rendering.
- `admin/tests/test_sanitize.php`, `admin/tests/test_helpers.php`, `admin/tests/test_db.php`: helpers and DB connectivity.

**Documentation:**
- `docs/JURNAL-LUCRU.md`: session history, open items, local setup.
- `docs/superpowers/specs/2026-08-27-catalog-db-design.md`: catalog DB design.
- `docs/superpowers/plans/2026-06-25-cms-admin-panel.md`: admin plan.

## Naming Conventions

**Files:**
- Public category pages: lowercase Romanian slug, matching the URL and the DB `categories.slug`, e.g. `gips-carton.php`, `scule-unelte.php`, `apa-canal.php`. Hyphens, no diacritics.
- Static pages: lowercase slug with `.html`, e.g. `politica-retur.html`.
- Shared PHP in `includes/`: lowercase snake_case, e.g. `products_repo.php`, `product_grid.php`, `page_bootstrap.php`.
- Admin classes: PascalCase files matching the class, e.g. `ProductProperty.php` contains `ProductProperty`. Controllers end in `Controller.php`.
- Migrations: `migration_NNN_<topic>.sql`, three-digit sequence (next is `migration_007_*.sql`).
- Ops scripts: snake_case verbs, e.g. `migrate_products.php`, `deploy_db.php`.
- Tests: `test_<unit>.php` in `admin/tests/`.

**Directories:**
- Lowercase, single word or hyphenated: `includes`, `templates`, `finantari-europene`, `proiect-digitalizare`.

**Code identifiers:**
- Functions in `includes/`: camelCase (`productsForSection`, `pageProductsIndexed`, `renderMaterialCards`). Exception: `e()` and `section()` are short globals.
- Classes in `admin/`: PascalCase static methods (`Product::all()`, `Sanitize::slug()`).
- DB columns and `page_slug` / `section_key` values: snake_case or hyphenated slug strings.

## Where to Add New Code

**New product category page (public):**
- Primary code: `<slug>.php` at repo root, copying the structure of `acoperis.php` (bootstrap require, `section()` calls, `<head>` from existing pages).
- Register the slug in `Product::KNOWN_PAGE_SLUGS` (`admin/models/Product.php`) and in the `categories` table (new migration under `db/`).
- Add the new file name to the sitemap (`sitemap.xml`). `.cpanel.yml` already copies `*.php`.

**New DB-only category or page (no hand-written HTML):**
- Create the row in `categories` or `pages` through admin or a migration. `router.php` serves it through `templates/category.php` or `templates/page.php`.

**New section or card style on an existing category page:**
- Add a `section('<page>', '<section_key>', '<kind>')` call in the page file. Keep the key in sync with `products.section_key` values (see `Product::sectionsByPage()`).

**New repository query (public read):**
- Implementation: `includes/products_repo.php`. Follow the pattern in `productsForSection()`: `try/catch (Throwable)`, `error_log`, return `[]`. No HTML.

**New card or HTML fragment:**
- Implementation: `includes/product_grid.php`. Use `e()` for escaping. No SQL.

**New admin feature:**
- Controller: `admin/controllers/<Name>Controller.php`, then register the route in the `$routes` array in `admin/index.php`.
- Model: `admin/models/<Name>.php` (static methods, prepared statements).
- View: `admin/views/<area>/<action>.php`; controller buffers it into `$bodyContent` and renders `admin/views/layout.php`.
- Tests: `admin/tests/test_<name>.php`.

**New schema change:**
- SQL: `db/migration_007_<topic>.sql` (next free number). Also update `db/schema.sql` if it defines base tables, and check `db/deploy_db.php` so the change runs on deploy.

**New static page:**
- Place `<slug>.html` at repo root. Add it to `sitemap.xml` and `.cpanel.yml` if it lives in a new folder (root `*.html` is already copied).

**New static assets:**
- Styles: `css/style.css` (single file, cache-busted via `filemtime` in page `<link>` tags).
- Scripts: `js/main.js`.
- Images: `img/`. Product photos go to `uploads/products/` through admin, not into git.

**New top-level directory:**
- Add a matching `/bin/cp -R <dir> $DEPLOYPATH` line to `.cpanel.yml`, otherwise it will not reach production.

**Utilities:**
- Shared PHP helpers used by public pages: `includes/`.
- Shared PHP helpers used only by admin: `admin/helpers/`.

## Special Directories

**`uploads/products/`:**
- Purpose: Product images uploaded from admin.
- Generated: Yes (at runtime).
- Committed: Only `.gitkeep`; contents are gitignored.

**`admin/config.local.php`:**
- Purpose: Real DB credentials on the server and on each developer machine.
- Generated: No (hand-edited).
- Committed: No (gitignored). Never copy its contents into docs or commits.

**`db/productie_date.sql`:**
- Purpose: Production data snapshot.
- Generated: Yes (dump).
- Committed: Yes.

**`.superpowers/`:**
- Purpose: Review diffs and task reports from agent-driven development.
- Generated: Yes.
- Committed: Partly (`.superpowers/sdd/.gitignore` controls what is kept).

**`.planning/`:**
- Purpose: GSD planning and codebase maps (this directory).
- Generated: Yes.
- Committed: Per project policy; the orchestrator handles git.

**`.claude/worktrees/`:**
- Purpose: Local git worktrees.
- Generated: Yes.
- Committed: No.

---

*Structure analysis: 2026-10-13*
