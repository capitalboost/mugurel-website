# Coding Conventions

**Analysis Date:** 2026-10-13

## Naming Patterns

**Files:**
- Public storefront pages are kebab-case PHP/HTML at the repo root, named after the category slug: `electrice.php`, `gips-carton.php`, `apa-canal.php`, `scule-unelte.php`. Slug and filename must match (`Product::KNOWN_PAGE_SLUGS` in `admin/models/Product.php`).
- Classes are PascalCase, one class per file, file named after the class: `admin/models/Product.php` (`class Product`), `admin/helpers/Csrf.php` (`class Csrf`), `admin/helpers/Sanitize.php` (`class Sanitize`).
- Admin controllers: `admin/controllers/{Name}Controller.php` (`ProductsController.php`, `PagesController.php`).
- Admin views: lowercase folders per resource, `admin/views/{resource}/{list,edit}.php`.
- Tests: `admin/tests/test_{unit}.php` (`test_products_repo.php`, `test_sanitize.php`). See `TESTING.md`.
- Migration and maintenance scripts: snake_case verbs in `db/` (`migrate_products.php`, `deploy_db.php`, `seed_admin.php`, `extract_icons.php`).

**Functions:**
- Global functions in `includes/` use camelCase with a verb or noun prefix: `productsForPage()`, `productsForSection()`, `allProducts()`, `categoryTree()`, `catalogCount()`, `renderMaterialCards()`, `renderProdIcon()`, `waLink()`, `priceLabel()`.
- Helpers that take an HTML-escaping role are short: `e(?string $s): string` in `includes/product_grid.php`.
- Some helper names are Romanian-flavoured camelCase: `panouGenerat()` in `includes/product_grid.php`. Keep new helpers in English unless extending an existing Romanian name.
- Static methods on classes use camelCase: `Database::get()`, `Csrf::token()`, `Sanitize::posInt()`, `Flash::success()`, `Product::byId()`, `ProductProperty::replaceAll()`.

**Variables:**
- PHP local variables are camelCase: `$productId`, `$knownPageSlugs`, `$categoryTree`, `$bodyContent`.
- Array keys and DB columns are snake_case: `card_kind`, `page_slug`, `section_key`, `short_description`, `price_unit`, `cat_slugs`. Keep this in sync with the SQL in `includes/products_repo.php` (`productsBaseSql()`).
- Constants are SCREAMING_SNAKE: `UPLOAD_MAX_BYTES`, `UPLOAD_DIR`, `DB_HOST` (`admin/config.php`). Class constants: `Product::KNOWN_PAGE_SLUGS`, `Product::KNOWN_BADGE_KINDS`.

**Types and classes:**
- PascalCase classes. Class-level static state uses `private static ?PDO $instance = null;` (`admin/models/Database.php`).
- Domain value strings are fixed lists in class constants, not enums: `card_kind` in {`material`, `accessory`}; `page_slug` in `Product::KNOWN_PAGE_SLUGS`; `badge_kind` in `Product::KNOWN_BADGE_KINDS`.

**Identifiers vs. text:**
- Code identifiers, comments and log messages are Romanian or English depending on the file. New comments in `includes/` and `admin/` are written in Romanian without diacritics (e.g. `Toate functiile returneaza [] daca baza de date nu raspunde.`). Match the surrounding file.
- Visible site text is Romanian **without diacritics** (project convention, see below).

## Code Style

**Formatting:**
- No formatter config is committed (no `.php-cs-fixer.php`, `phpcs.xml`, `.editorconfig` or `composer.json` at repo root). Style is manual and varies by file.
- Indentation is 4 spaces in `admin/` and `includes/` PHP. Some older storefront files and `admin/views/products/edit.php` use 2 spaces or mixed indentation; do not reformat unrelated code.
- Line endings are LF. Several PHP files (e.g. `acoperis.php`, `admin/helpers/Upload.php`, `admin/tests/test_sanitize.php`) begin with a UTF-8 BOM. New files should not add a BOM; leave existing BOMs alone unless the file is rewritten.
- Opening tag `<?php` on line 1, no closing `?>` in class files.
- Braces: opening brace on the same line for functions, classes and control structures. Short one-line function bodies are common in helpers: `public static function year(): string { return ...; }`.
- Strict typing is **not** the default: only `db/deploy_db.php` declares `declare(strict_types=1);`. Type hints and return types are used on all new functions (`: array`, `: void`, `?int`, `: string`), so keep them when editing.

**Linting:**
- Not detected. No PHPStan, Psalm, PHP_CodeSniffer or PHP-CS-Fixer configuration exists. Verification is `php -l <file>` plus the test scripts (see `TESTING.md`).

**PHP runtime:**
- Target is PHP 8.1 (`/opt/cpanel/ea-php81` on the production host). Use PHP 8.1-compatible syntax: constructor property promotion, union types, `match`, `str_contains`, nullsafe operator are fine; enums, readonly properties and first-class callable syntax should be avoided unless the file already uses them.
- Arrow functions (`fn($p) => ...`) are used in tests and array filters.

## Import Organization

**Order (PHP):**
1. `require_once __DIR__ . '/...';` for dependencies, always relative to `__DIR__`. Never rely on the include path.
2. Global state setup (e.g. `$db = Database::get();`).
3. Logic and output.

Example from `admin/controllers/ProductsController.php`:
```php
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/ProductProperty.php';
require_once __DIR__ . '/../helpers/Upload.php';
require_once __DIR__ . '/../../includes/products_repo.php';
require_once __DIR__ . '/../../includes/product_icons.php';
```

**Path Aliases:**
- Not applicable. All paths are filesystem-relative with `__DIR__`. URLs are root-absolute (`/admin/?page=products`, `/uploads/products/`).

## Error Handling

**Storefront read path (`includes/products_repo.php`):**
- Every public query function wraps its work in `try { ... } catch (Throwable $e) { ... }` and returns `[]` (or `0` for counters) on failure. Exceptions must not escape to the page. This is the "degrade gracefully" rule recorded in `docs/JURNAL-LUCRU.md`: a page with no DB must still render editorial content and a WhatsApp fallback. Never return a 500 from a storefront page.
- Log the failure with `error_log('<function>: ' . $e->getMessage());`, as in `attachProperties()` (`includes/products_repo.php`).
- When a secondary query fails, degrade the dependent field rather than the whole result: `attachProperties()` sets `properties = []` on each row and still returns the rows.

**Admin write path:**
- Use `PDO::ERRMODE_EXCEPTION` (set in `admin/models/Database.php`). Catch narrow exceptions at the controller boundary: `catch (RuntimeException $e)` in `admin/controllers/ProductsController.php` converts upload errors into a user-visible message.
- Multi-statement writes use explicit transactions with rollback: `ProductProperty::replaceAll()` calls `beginTransaction()` and rolls back on `Throwable`.
- User feedback goes through `Flash::success()` / `Flash::error()` (`admin/helpers/Flash.php`), which store a one-shot message in `$_SESSION['flash']`. Redirect after POST with `header('Location: ...'); exit;`.
- Missing records: flash an error and redirect to the list, e.g. `Flash::error('Produsul nu exista.'); header('Location: /admin/?page=products'); exit;`.

**Security-sensitive failures:**
- CSRF failure is terminal: `Csrf::verify()` sets HTTP 403 and calls `die('CSRF token invalid.')`.
- Input validation returns a safe value rather than throwing: `Sanitize::posInt()` clamps to `>= 0`, `Sanitize::price()` returns `null` for non-positive values.

**Deploy scripts:**
- `db/deploy_db.php` uses `declare(strict_types=1)` and writes all output to a log readable from the browser. Do not use `|| true` shell tails that swallow errors (see the deploy history in `docs/JURNAL-LUCRU.md`).

## Logging

**Framework:** `error_log()` only. No logging library.

**Patterns:**
- Log only from `catch` blocks in `includes/` and `admin/models/`, with a short prefix naming the function: `error_log('attachProperties: ' . $e->getMessage());`.
- Do not `echo` debug output into pages. Test scripts use `echo` for their own report only.
- Never log request bodies, passwords, tokens or the contents of `admin/config.local.php`.

## Comments

**When to Comment:**
- Explain *why* a fallback or guard exists, especially the storefront degradation rules and deploy guards. Examples: the `productsForPage()` docblock in `includes/products_repo.php` documents the empty-array contract; `Product::KNOWN_PAGE_SLUGS` explains the 14 valid values.
- Test files reference the task that introduced an assertion, e.g. `// Task 8C: 39 de produse ...` and `// Task 5D`. Keep these references when extending a test block.
- Section headers in tests use `// Test: ...` (`admin/tests/test_db.php`) or `// <function>(): <behaviour>` (`admin/tests/test_sanitize.php`).

**Docblocks:**
- PHPDoc `/** ... */` on public functions and class constants in `includes/` and `admin/models/`. Parameter and return types are given in the signature, so docblocks describe semantics (null meaning, sort order, what is returned on error). Example: `productsForSection()` documents what `$modifier` `null`, `''` and a string each mean.
- Docblocks are in Romanian. Keep them consistent with the file.

## Function Design

**Size:** Query helpers are short and single-purpose; SQL lives in one place (`productsBaseSql()`) and is extended with `' AND ...'` suffixes. Controllers are long `switch ($action)` blocks, one `case` per action.

**Parameters:**
- Scalar parameters with defaults for optional filters: `productsForPage(string $pageSlug, ?string $kind = null, ?int $limit = null, int $offset = 0)`.
- Nullable means "no filter": `null` for `$kind`, `$limit`, `$modifier`. Preserve that meaning when adding parameters.
- Pass user input through `Sanitize::*` before it reaches a model or query.

**Return Values:**
- Lists return `array` (possibly `[]`), never `null`.
- Single lookups return `?array` (`Product::byId()`), and callers check for `null`.
- Storefront renderers return `string` HTML; they are pure (no `echo`). `includes/product_grid.php` and `includes/page_bootstrap.php` return strings that the page then echoes.

## Module Design

**Exports:**
- No namespaces. All functions are global, guarded only by `require_once`. Avoid name collisions with the existing global set listed in `includes/` (`e`, `section`, `renderIcon`, `waLink`, etc.).
- Classes in `admin/models/` and `admin/helpers/` expose only `public static` methods; constructors of `Database` are private (singleton).

**Layers:**
- `admin/models/*` → SQL and persistence for the admin (PDO via `Database::get()`).
- `includes/products_repo.php` → read-only product queries for the storefront. SQL only, no HTML.
- `includes/product_grid.php`, `includes/product_icons.php` → HTML rendering only. No SQL.
- `includes/page_bootstrap.php` → glue for category pages (`section()`).
- `templates/*.php` → shared header, footer and page shells.
- Keep this separation: SQL stays out of renderers and HTML stays out of repositories.

**Barrel Files:** Not used.

## Security and Data Handling

- Escape all output with `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` in views (see `admin/views/products/list.php`) or `e()` in `includes/product_grid.php`.
- Build SQL with placeholders. Dynamic `IN (...)` lists are built from `array_fill(0, count($ids), '?')` (`includes/products_repo.php`). `LIMIT`/`OFFSET` are cast to `int` in `withLimit()`.
- Rich text in admin goes through `Sanitize::html()`, which allows a small tag set and strips `on*` attributes and non-http links.
- Every admin POST must call `Csrf::verify()` and every form must include `Csrf::input()`.
- Secrets live in `admin/config.local.php` (server-only, not in git). `admin/config.php` only holds placeholder defaults. Do not add credentials to tracked files or documents.

## Project Content Convention

- **Site texts without Romanian diacritics.** Write `apa`, `rezistenta`, `pret`, `Cauta produse...`, not `apă`, `rezistență`, `preț`. This is the existing rule in `catalog.html` and the other pages, and the admin placeholder text follows it (`placeholder="Cauta produse..."`).
- Product descriptions: about 85 characters, two or three short sentences, price shown as "la cerere" when no price is set (see `docs/JURNAL-LUCRU.md`).
- Product names with diacritics in the DB are a known exception only where explicitly accepted; do not introduce new ones on the public site.

## Git Commit Conventions

- Subject lines use Conventional Commit prefixes followed by a Romanian description, all lower-case after the prefix:
  - `feat: task 9B - editorul de proprietati in admin produse`
  - `fix: deploy-ul foloseste binarul PHP 8 explicit si scrie log, nu mai esueaza tacut`
  - `docs: jurnal — deploy live si sistemul de verificare a productiei`
  - `chore: muta docs/ si db/ din repo-ul parinte in Website`
- Prefixes in use: `feat`, `fix`, `docs`, `chore`.
- **Never add a `Co-Authored-By: Claude` trailer** in commits, PRs or anything pushed (project memory rule).
- Work happens on feature branches (current: `feat/catalog-db`); deploys are made from a branch via cPanel, not by force-pushing `main`.

## Documentation Conventions

- Working journal: `docs/JURNAL-LUCRU.md`, newest session first. Each entry lists what was resolved, what is still open and what must be known to continue.
- Design specs and plans: `docs/superpowers/specs/YYYY-MM-DD-<topic>-design.md` and `docs/superpowers/plans/YYYY-MM-DD-<topic>.md`.
- Repo-internal notes that are not user docs live under `.superpowers/` and `.planning/`.

---

*Convention analysis: 2026-10-13*
