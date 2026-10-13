# Testing Patterns

**Analysis Date:** 2026-10-13

## Test Framework

**Runner:**
- None. Tests are plain PHP scripts run directly by the PHP CLI. There is no PHPUnit, Pest or Composer dependency (no `composer.json` at repo root).
- Config: Not applicable. Each script bootstraps its own dependencies with `require_once __DIR__ . '/...'`.
- PHP runtime: PHP 8.1 is the target; local verification uses the Laragon PHP 8.x binary (see `docs/JURNAL-LUCRU.md`).

**Assertion Library:**
- Hand-rolled. Each script defines the same helper:
```php
$pass = 0; $fail = 0;
function ok(bool $cond, string $msg): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ✓ $msg\n"; }
    else       { $fail++; echo "  ✗ $msg\n"; }
}
```
- The helper is redeclared in each test file (they are never included together), so keep it self-contained.

**Run Commands:**
```bash
php admin/tests/test_sanitize.php        # Pure helper, no DB
php admin/tests/test_helpers.php         # CSRF / Flash, starts a session
php admin/tests/test_product_grid.php    # HTML rendering, no DB
php admin/tests/test_db.php              # Needs the local MySQL database
php admin/tests/test_products_repo.php   # Needs the local MySQL database
# Run all (PowerShell/bash):
for f in admin/tests/test_*.php; do php "$f" || exit 1; done
```
- Watch mode: Not available.
- Coverage: Not available. No coverage tool is configured.

**Exit codes:** Every script ends with
```php
echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
```
Use this exit pattern so a failing run is visible to shell loops and the deploy checks.

## Test File Organization

**Location:**
- All tests live in `admin/tests/`, separate from the code they test (`includes/`, `admin/helpers/`, `admin/models/`). Tests are not co-located.

**Naming:**
- `test_{subject}.php`, where the subject matches the source file or area: `test_sanitize.php` → `admin/helpers/Sanitize.php`; `test_products_repo.php` → `includes/products_repo.php`; `test_product_grid.php` → `includes/product_grid.php`; `test_db.php` → `admin/models/Database.php` and the migrated schema.

**Structure:**
```
admin/tests/
├── test_db.php            # 7 assertions, live DB: schema counts, singleton
├── test_helpers.php       # 10 assertions: Csrf, Flash (session)
├── test_sanitize.php      # 16 assertions: Sanitize::text/html/slug/posInt/price
├── test_product_grid.php  # ~108 assertions: HTML output of grid renderers
└── test_products_repo.php # ~45 assertion calls (~198 checks incl. loops): live DB
```
Assertion counts are the number of `ok(` calls in each file, with loops and `foreach` expanding them at run time. The project journal reports 198 assertions passing across the suite when the database is populated.

## Test Structure

**Suite Organization:**
Scripts are flat, top-to-bottom sequences of `ok()` calls grouped by a comment header. There is no `describe`/`it` nesting.

```php
<?php
require_once __DIR__ . '/../../includes/products_repo.php';
require_once __DIR__ . '/../../includes/page_bootstrap.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass,$fail; if($c){$pass++;echo"  ✓ $m\n";}else{$fail++;echo"  ✗ $m\n";} }

// Electrice are 23 de materiale, distribuite 4/4/9/6 pe patru sectiuni.
$page = productsForPage('electrice', 'material');
ok(count($page) === 23, "productsForPage(electrice, 'material') => 23 (got " . count($page) . ')');
```

**Patterns:**
- **Message includes the actual value**: `"... => 23 (got " . count($page) . ')'`. Every assertion prints what it got on failure. Keep this pattern; it makes DB drift diagnosable from the log alone.
- **Comment before each block** states the expected business fact and, when relevant, the task or migration that created it (`// Task 8C: 39 de produse ...`).
- **Setup pattern:** Superglobals are faked at the top of the file when needed, e.g. `$_SERVER['REQUEST_METHOD'] = 'GET'; session_start();` in `test_helpers.php`.
- **Teardown pattern:** None. Tests are read-only against the database and leave no files behind.
- **Assertion pattern:** Compare exact values (`=== 23`, `=== []`), key presence (`array_key_exists`), or substring presence (`str_contains($html, ...)`). Use `array_filter` with a closure to assert that every row matches a property.

## Mocking

**Framework:** None. No mocking library and no test doubles.

**Patterns:**
- Tests use the real code paths. Database-dependent tests run against the local `mugurel_cms` database (`Database::get()`), with the production schema applied by `db/migrate_*.php`.
- Failure paths are exercised by the code's own guards rather than by mocks. Example: the empty-array-on-error contract of `includes/products_repo.php` is a behaviour that the page code relies on; there is no test forcing a DB failure.

**What to Mock:**
- Not applicable today. If a future test needs a DB failure, prefer a second PDO connection to a non-existent database over a mock, because the production functions call `Database::get()` directly.

**What NOT to Mock:**
- Do not stub `Sanitize`, `Csrf` or the HTML renderers. They are pure and fast enough to run for real.
- Do not fake the product dataset. Count assertions (e.g. `allProducts()` => 534) are intentional regression guards against the migrated data.

## Fixtures and Factories

**Test Data:**
- No fixture files. Expected values are the real counts of the migrated catalog, written as literals in the test:
```php
$all = allProducts();
ok(count($all) === 534, 'allProducts() => 534 (got ' . count($all) . ')');
$allMaterial = array_filter($all, fn($p) => $p['card_kind'] === 'material');
ok(count($allMaterial) === 270, 'allProducts() contine 270 materiale (got ' . count($allMaterial) . ')');
```
- Pure-function tests use small inline arrays. `test_product_grid.php` builds one `$sample` row with every key the renderer reads, so the row shape is documented in the test:
```php
$sample = [[
    'id' => 1,
    'name' => 'Cablu CYY-F 3x2.5mm² 100m',
    'short_description' => 'Cablu electric pentru instalatii fixe. ...',
    'icon_key' => 'inexistenta', 'icon_label' => 'Cablu CYY-F 3x2.5mm²',
    'price' => null, 'price_unit' => null,
    // ...
]];
```

**Location:**
- Inline in each test file. No shared fixtures directory.
- Product and schema data originates in `db/productie_date.sql` and `db/migrate_*.php` (not committed to git; the local database must be rebuilt from the scripts after a fresh checkout).

## Coverage

**Requirements:** None enforced. No coverage tool, no threshold.

**View Coverage:** Not available.

**Known gaps** (from the `docs/JURNAL-LUCRU.md` notes and the test files):
- `test_db.php` checks counts (31 categories, 9 level-1 categories, no orphans) but not that every subcategory has products.
- `admin/controllers/*` and `admin/views/*` have no tests. Admin flows were verified manually on the live site.
- `db/deploy_db.php` (import guard, idempotency) was verified by hand on three scenarios, not by an automated test.
- Storefront page output is verified by fetching the live pages with `curl` and counting `class="material-card"` and `class="accessory-card` occurrences with `grep -o | wc -l` (not `grep -c`, which undercounts when several cards share a line).

## Test Types

**Unit Tests:**
- Pure helpers: `test_sanitize.php`, `test_product_grid.php`, parts of `test_helpers.php`. No database needed.

**Integration Tests:**
- Database-backed: `test_db.php`, `test_products_repo.php`. Require the local MySQL/MariaDB instance and a migrated schema. Expect the counts in the scripts to match the migrated data exactly.

**E2E Tests:**
- Not used. Verification of the live site is manual via `curl` checks listed in `docs/JURNAL-LUCRU.md`.

## Adding a New Test

1. Create `admin/tests/test_<subject>.php` with the standard header (`$pass`, `$fail`, `ok()`), and `require_once` the file under test with `__DIR__`.
2. Group assertions under `//` comment headers that state the expected fact.
3. Use `ok(<bool>, "<description> (got " . $value . ')')` so failures print the actual value.
4. End with the summary line and `exit($fail > 0 ? 1 : 0);`.
5. Run it with `php admin/tests/test_<subject>.php` and confirm `0 failed` before committing.
6. If the test depends on the DB, say so in its header comment, and do not assert on values that change when products are added from `/admin` (the admin is live on production, so counts drift; prefer relations and shapes over totals where possible).

## Common Patterns

**Async Testing:** Not applicable. PHP is synchronous here.

**Error Testing:**
```php
// Unknown section returns the unavailable notice, not an exception
ok(str_contains(section('nu-exista', 'nu-exista'), 'wa.me/40749130565'),
    'section(): pagina inexistenta => mesajul de indisponibilitate');

// Empty result for an unknown filter, not an error
ok($secWrongKind === [], 'sectiune de materiale ceruta cu tip accessory => []');
```
Error contracts are asserted as return values (`[]`, a fallback string, `null`), matching the production rule that storefront functions never throw.

**Security / Sanitizer Testing:**
- Assertions in `test_sanitize.php` check that tags are stripped, `on*` handlers removed and lengths capped. Add a case here for any new sanitizer rule.

**Output Testing:**
- HTML renderers are checked with `str_contains` and `preg_match` on the returned string (see `test_product_grid.php`). Prefer checking for the specific class or attribute that matters, not the full markup.

---

*Testing analysis: 2026-10-13*
