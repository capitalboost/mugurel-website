# Codebase Concerns

**Analysis Date:** 2026-10-13

Scope: whole repo at `Website/`. Each item was checked against the code. Items marked **(unverified)** need a live check (browser, curl, cPanel) because they depend on server or Cloudflare behaviour that cannot be read from the repo.

## Priority Summary

| # | Concern | Severity | Area |
|---|---------|----------|------|
| 1 | Hardcoded plaintext admin password in committed seed script | High | Security |
| 2 | Deploy log `__deploy_m7x2verif.log` publicly readable and re-created on every deploy | High | Security / Ops |
| 3 | Analytics (GA, Clarity) load before consent, despite the GDPR banner | High | Privacy / Legal |
| 4 | Production deploys from `feat/catalog-db`, not `main` | Medium | Ops |
| 5 | Login rate limit keyed on `REMOTE_ADDR`, which is the Cloudflare edge IP behind the proxy | Medium | Security |
| 6 | CSP `script-src` allows `'unsafe-inline'`; `Sanitize::html()` is a blacklist and bypassable | Medium | Security |
| 7 | 32 product photos missing (31 journal + 1 not in journal) and `og-image.jpg` missing on 18 pages | Medium | Bugs |
| 8 | Static HTML pages reference `css/style.css` and `js/main.js` without a version, cached 4h at Cloudflare | Medium | Performance / Ops |
| 9 | Product import pending (3000+); deploy guard is fragile when the products table is emptied | Medium | Data |
| 10 | Duplicated per-page HTML/meta/tracking across 13 category PHP files | Low | Tech debt |

---

## Security

### Hardcoded admin password in a committed script
- Issue: `db/seed_admin.php` contains a plaintext password literal on line 18, and it is tracked in git. It hashes the password and inserts an `admin` user. The literal is not reproduced here.
- Files: `db/seed_admin.php` (line 18, plus the `DB_PASS` placeholder on line 8)
- Impact: anyone with repo access, or any future clone or leak of the repo, knows a candidate admin password. If that password was ever used on `mugurel-bricolaj.ro/admin`, the admin panel is compromised.
- Current mitigation: none in the repo. The password is not in `.gitignore`. `db/` is not copied to `public_html` by `.cpanel.yml`, so it is not web-reachable.
- Fix approach: rotate the admin password on the live site now. Remove the literal from `db/seed_admin.php`, read it from an environment variable or a CLI prompt, and purge it from git history if the repo is shared (`git filter-repo`). Confirm no other file contains a password literal (`grep -rn "password" db/ admin/`).

### Deploy log publicly readable
- Issue: `.cpanel.yml` writes all deploy output to `$DEPLOYPATH/__deploy_m7x2verif.log`, which is `public_html/__deploy_m7x2verif.log`. The root `.htaccess` has no rule that blocks `*.log`. Only `admin/.htaccess` (`FilesMatch "\.(sql|log|env|ini|json|md)$"`) denies these types, and only inside `/admin`. The journal confirms the file is readable in a browser.
- Files: `.cpanel.yml` (lines with `LOG=$DEPLOYPATH/__deploy_m7x2verif.log`), `.htaccess` (no `FilesMatch`), `admin/.htaccess` (the only block rule)
- Impact: deploy output leaks server paths (`/opt/cpanel/ea-php81/...`, `/home/mugurel/public_html`), PHP versions, and DB-import counts. It is also re-created on every deploy, so deleting it once is not enough.
- Fix approach: (a) add the same `FilesMatch` deny block to the root `.htaccess`, (b) write the log outside the web root (for example `/home/mugurel/logs/deploy.log`), and (c) delete the existing file from `public_html`. The journal's "Sterge" step only works if the log path also changes.

### Admin login rate limit uses the proxy IP
- Issue: `admin/controllers/AuthController.php` counts failures by `$_SERVER['REMOTE_ADDR']`. The site is behind Cloudflare (purge steps in the journal), and the origin does not read `CF-Connecting-IP`. So every visitor probably shares one bucket keyed on a Cloudflare edge IP. (unverified: depends on whether Cloudflare proxies `/admin`.)
- Files: `admin/controllers/AuthController.php` (lines 22-27, login_attempts check), `admin/helpers/Auth.php` (`$_SESSION['ip']` binding, same source)
- Impact: five wrong passwords from anywhere lock out `/admin` for 15 minutes for all users, which is a denial-of-service on the editor. The same IP binding in `Auth::isLoggedIn()` can log out a user whose IP changes.
- Current mitigation: `password_verify` with bcrypt cost 12 and a dummy hash for unknown usernames (timing-safe).
- Fix approach: trust `CF-Connecting-IP` only when `REMOTE_ADDR` is a Cloudflare range, or key the lockout on `username` plus IP. Consider a per-account lock rather than a global IP lock.

### Content Security Policy weakened by `'unsafe-inline'`
- Issue: the root CSP in `.htaccess` has `script-src 'self' 'unsafe-inline' ...`. `'unsafe-inline'` lets any injected inline script run. Inline scripts exist for gtag and Clarity in each category page (`electrice.php` and 21 other files, both `<head>` blocks).
- Files: `.htaccess` (Security Headers block), inline blocks in `electrice.php` and the 21 other pages that load `https://www.googletagmanager.com/gtag/js?id=G-R6M7YYLB48`
- Impact: the CSP does not stop XSS. It is only a partial defence against third-party script injection.
- Fix approach: move the gtag/Clarity bootstrap into a file under `js/` (for example `js/analytics.js`) and drop `'unsafe-inline'` from `script-src`. Add a nonce or hash if inline is still needed. Also add `base-uri 'self'; form-action 'self'; object-src 'none'`.

### Admin CSP may block TinyMCE assets (unverified)
- Issue: `admin/.htaccess` allows only `script-src 'self' 'unsafe-inline' https://cdn.tiny.cloud`. It sets no `font-src`, `connect-src`, or `style-src` for `cdn.tiny.cloud`, so `default-src 'self'` applies. TinyMCE 6 loads skin CSS and icons from its CDN.
- Files: `admin/.htaccess`, `admin/views/pages/edit.php` (line 43, `tinymce.min.js` from `cdn.tiny.cloud/1/no-api-key/...`)
- Impact: the rich-text editor for legal and content pages may render unstyled or fail to load. (unverified: check the browser console on `/admin/?page=pages&action=edit`.)
- Fix approach: either self-host TinyMCE under `admin/assets/`, or add the needed CDN hosts to the admin CSP. The `no-api-key` cloud build is also a dependency risk (see below).

### `Sanitize::html()` is a blacklist and can be bypassed
- Issue: stored HTML for legal pages is cleaned with regexes in `admin/helpers/Sanitize.php`. The `on*` filter only matches double- or single-quoted values (`on\w+\s*=\s*"..."`), so unquoted handlers (`<p onclick=alert(1)>`) pass. The `href` check only inspects `href="..."` with double quotes, so `<a href='javascript:...'>` is not neutralised. The output is printed raw in `templates/page.php` line 14.
- Files: `admin/helpers/Sanitize.php` (`html()`), `templates/page.php` (line 14, `<?= $pageData['content'] ?>`), `admin/controllers/PagesController.php` (line 30, `save`)
- Impact: stored XSS on public pages, triggered by an editor account. Risk is limited to users who can log in, but it is also reachable through the CSRF-protected form if a session is hijacked. The current test file `admin/tests/test_sanitize.php` only covers the double-quoted case.
- Fix approach: use an allowlist HTML purifier (for example `ezyang/htmlpurifier`) or a DOM-based sanitiser. Until then, add tests for single-quoted `href`, unquoted `on*=`, and `<a href="JaVaScRiPt:...">`.

### Logout is a GET request
- Issue: `admin/index.php` routes `page=logout` through GET, and `AuthController` logs out without a token. Any page can force a logout with an `<img src="/admin/?page=logout">`.
- Files: `admin/controllers/AuthController.php` (line 6-10)
- Impact: low (session disruption only). Fix: make logout a POST form with `Csrf::verify()`.

### Other admin hardening
- `Upload::image()` (`admin/helpers/Upload.php`) validates MIME, extension, and `getimagesize`, and renames the file to random hex. It does not re-encode images, so polyglot payloads survive. Execution is blocked by `uploads/.htaccess`. The `realpath` check at the end (`strpos($realTarget, $realBase) !== 0`) is weak because `$realBase` is computed from the parent directory. Fix: re-encode with GD or Imagick, and drop the redundant check.
- `Csrf::verify()` rotates the token after every successful POST. A second form opened in another tab fails with "CSRF token invalid." Fix: rotate only on login or keep a small token set.
- Admin session cookies are set with `secure`, `httponly`, and `SameSite=Strict` in `admin/helpers/Auth.php`. Good. But `session.cookie_secure` is set only for the admin session, and the public site has no HTTPS redirect in the root `.htaccess` (only `admin/.htaccess` has a forced-HTTPS rule). (unverified: Cloudflare may redirect.)
- Public directories copied by `.cpanel.yml` include `includes/`, `templates/`, and `admin/config.php`. Only `admin/` has a deny rule. PHP files in `includes/` and `templates/` execute when requested directly and can emit warnings that expose paths. Fix: add a deny rule for `includes/` and `templates/` in the root `.htaccess`.
- `admin/config.php` contains a `DB_USER`/`DB_PASS` placeholder. The real values live in `admin/config.local.php`, which is gitignored and was not read for this map. Keep it that way.

---

## Privacy and Legal

### Analytics load before consent
- Issue: `electrice.php` (and the other category pages, 22 files total with the same `<head>`) load `googletagmanager.com/gtag/js?id=G-R6M7YYLB48` and Microsoft Clarity (`clarity.ms/tag/y5btegvf2j`) unconditionally in `<head>`. The GDPR banner in `js/main.js` (lines 112-143) only stores a `localStorage` flag. It does not gate the scripts, and clicking "Refuz" still lets them run.
- Files: `js/main.js` (lines 112-143), the `<head>` of all 22 category and static pages that contain `googletagmanager.com`
- Impact: GDPR and ePrivacy exposure in Romania and the EU. The banner text says cookies are used "for proper operation" and to "help improve", which does not describe pre-consent analytics. Clarity records sessions, so this is more than page counts.
- Fix approach: load gtag and Clarity only after `localStorage.gdpr_consent === 'accepted'`, and call `gtag('consent', 'default', {analytics_storage: 'denied'})` before the tag loads. Keep "Refuz" effective.

### Banner markup and copy
- The banner is built in JS and has no `role="dialog"` or focus management. It shows on every first visit, with no link to a settings panel to withdraw consent later.

---

## Deployment and Operations

### Production runs from a feature branch
- Issue: the journal says cPanel deploys `feat/catalog-db`, and `main` is not the production branch. `main` does not contain the catalog work.
- Files: `.cpanel.yml`, `docs/JURNAL-LUCRU.md` (the "Ramase de facut" list)
- Impact: a rollback is a dropdown change, not a revert. Any commit on the branch goes live as soon as it is pushed and deployed. Anyone who pulls `main` gets the old site.
- Fix approach: merge `feat/catalog-db` into `main` after the open items are closed, then point the cPanel deployment at `main`. Use tags for rollback points.

### Deploy is non-blocking by design
- `.cpanel.yml` ends each risky step with `true`, and `db/deploy_db.php` always exits 0 (its header says so). A failed migration therefore reports success. The log file (see Security) is the only signal.
- Fix approach: write the exit status into the log, and make a failed migration fail the cPanel task after the files are copied (this is a trade-off, since the site would then be left half-deployed).

### Static HTML pages are cached stale for up to 4 hours
- Issue: `header.php` appends `?v=filemtime` to `css/style.css`, but the static pages do not. `index.html` (line references in `finantari-europene/index.html`: `href="/css/style.css"`, `src="/js/main.js"`) and the other `.html` files reference `/css/style.css` and `/js/main.js` with no version. Cloudflare caches JS and CSS for 4 hours (per journal).
- Files: `index.html`, `contact.html`, `despre-noi.html`, `politica-*.html`, `termeni-conditii.html`, `finantari-europene/index.html`, `templates/header.php` (correct pattern at line 36)
- Impact: after a CSS or JS change, some pages show the old version for hours, and the purge step is easy to forget.
- Fix approach: add `?v=<hash or date>` to every static reference, or generate the static pages from the same template as the PHP ones. Keep the Cloudflare purge step in the deploy checklist.

### CSP must be updated for every new third-party script
- The CSP in `.htaccess` lists each host explicitly (GA, Clarity, Google Fonts). Adding a new script without updating the header breaks it silently. Current list: `script-src` covers `googletagmanager`, `google-analytics`, `clarity.ms`; `connect-src` adds `c.bing.com` (Clarity); `img-src` is `https:` (broad).
- Fix approach: keep a short checklist in `docs/`, and test each new script on the live domain with the browser console open.

### LiteSpeed and `<IfModule>` (per journal, unverified)
- The journal states LiteSpeed does not honour `<IfModule>` the way Apache does. The root `.htaccess` wraps the rewrite rules and the security headers in `<IfModule mod_rewrite.c>` and `<IfModule mod_headers.c>`. If the module test is false on LiteSpeed, these rules are silently skipped.
- Files: `.htaccess` (all three `<IfModule>` blocks), `admin/.htaccess` (`<IfModule mod_rewrite.c>`, `<IfModule mod_headers.c>`), `uploads/.htaccess` (no IfModule, uses Apache 2.2 `Order/Deny` syntax)
- Impact: headers may be missing on production with no error. Verify with `curl -sI https://mugurel-bricolaj.ro/ | grep -i content-security` and `curl -sI https://mugurel-bricolaj.ro/admin/`.
- Fix approach: remove the `<IfModule>` wrappers in the root `.htaccess` after confirming LiteSpeed behaviour, and check the CSP header on every deploy.

### Cloudflare cache and the deploy log
- The purge after each deploy is manual (journal). Fix: add a Cloudflare purge step to `.cpanel.yml` or a deploy checklist file, or use the `?v=` version approach above.

---

## Known Bugs and Data Gaps

### Missing product photos
- Issue: `db/productie_date.sql` sets `image_path` for 75 products, of which 44 files exist. The journal lists 31 missing files. A direct check of `img/` against all `img/` references in the repo finds one more missing product photo: `img/masina-gaurit-acumulator.jpg` (referenced in `db/productie_date.sql`, not in the journal list). The journal's table also needs this name added.
- Files: `db/productie_date.sql`, `includes/product_grid.php` (`onerror` fallback, lines 99 and 144), `img/` (missing files)
- Trigger: any product card whose `image_path` points to a missing file. The `onerror` handler hides the broken image and shows the generated panel, so the bug is silent.
- Workaround: the generated panel (`panouGenerat()` in `includes/product_grid.php`) covers the gap visually.
- Fix approach: upload the 32 files listed in the journal table plus `masina-gaurit-acumulator.jpg`, or clear `image_path` for products without a file.

### `og-image.jpg` is missing
- Issue: 18 pages declare `<meta property="og:image" content="https://mugurel-bricolaj.ro/img/og-image.jpg">`. Only `img/og-image.svg` exists. Social previews (Facebook, WhatsApp) show no image.
- Files: `electrice.php` and the other category pages, `index.html`, `img/og-image.svg` (present)
- Fix approach: export `img/og-image.svg` to a 1200x630 JPG or PNG as `img/og-image.jpg`, or change the meta tags. WhatsApp caches previews, so the fix is slow to show.

### Catalog import is incomplete
- Issue: 3000+ products from the management system are not imported. The repository already supports `limit` and `offset` (`includes/products_repo.php`), but there is no importer for the new source.
- Files: `db/migrate_catalog_products.php`, `db/migrate_products.php` (existing importers, source-specific), `includes/products_repo.php`
- Impact: the catalog counts (534) do not match the real stock, and new imports must not overwrite admin-entered products.
- Fix approach: write an importer that matches on a stable external key (SKU) and never touches rows where `page_slug` is set.

### Deploy import guard is fragile
- Issue: `db/deploy_db.php` re-imports `db/productie_date.sql` (which contains `DROP TABLE` for `categories`, `products`, `product_properties`, `product_categories`) when `products` is empty, or when no row has `page_slug` set (lines 168-196). The guard is safe for products added in the admin, but an empty `products` table (for example after an admin deletes everything, or on a fresh database) triggers the full re-import and discards any new categories or properties.
- Files: `db/deploy_db.php` (lines 161-200), `db/productie_date.sql` (lines 13, 33, 72, 92)
- Impact: data loss on the catalog tables if the guard conditions are met unexpectedly. The journal records a three-scenario test (old data, admin product, repeated run) but not the empty-after-delete case.
- Fix approach: move the one-time import behind an explicit marker (a row in a `schema_migrations`-style table, or a flag file outside the web root), and remove `DROP TABLE` from the seed file once production is seeded.

### Public `/admin` surface
- `admin/index.php` exposes `login`, `logout`, and the admin routes to anyone. The login page is reachable from the internet. Keep the rate limit fix above in mind.
- `admin/views/pages/list.php` links to edit pages with `$p['id']` only, and `PagesController` checks `restricted` for legal pages. Editors can still change `title` and `meta` on restricted pages? (unverified: `PagesController::save` checks `restricted` before saving all fields, so this is probably blocked.)

---

## Performance

### Duplicate correlated subquery in the product SELECT
- Issue: `productsBaseSql()` in `includes/products_repo.php` builds `cat_slugs` with a correlated subquery over `product_categories`, executed for every product row. The catalog page loads up to 60 products, and each category page loads all products for the category.
- Files: `includes/products_repo.php` (lines 14-37)
- Cause: `GROUP_CONCAT` in a subquery per row.
- Improvement path: precompute `cat_slugs` in a join with `GROUP BY p.id`, or cache per request (the static `pageProductsIndexed()` cache already exists for page-level reads). Check with `EXPLAIN` once the table is larger than a few thousand rows.

### Per-request N+1 for properties is avoided, but the catalog has no server cache
- `attachProperties()` already batches property reads. The catalog page (`catalog.php`) and category pages run their queries on every request, with no page cache. Cloudflare caches HTML only if its rules allow it. (unverified)

### Large hand-written pages
- The 13 category pages (`electrice.php`, `izolatie.php`, and others, 30-35 KB each) contain the same head, tracking, and layout markup. Product data comes from the DB, but the static shell is copied per file.
- Files: `acoperis.php`, `apa-canal.php`, `catalog.php`, `electrice.php`, `electrocasnice.php`, `gard-imprejmuiri.php`, `gips-carton.php`, `gradina.php`, `incalzire.php`, `izolatie.php`, `mobilier.php`, `sanitare.php`, `scule-unelte.php`, `zidarie-bca.php`

---

## Tech Debt

### Duplicated page shell and SEO metadata
- Issue: each category page repeats the `<head>` (meta, OG, JSON-LD, GA, Clarity). The `header.php` and `footer.php` templates exist, but category pages do not use them consistently. The gtag and Clarity code is copied in 22 files, so a consent fix must be applied 22 times.
- Files: `electrice.php` and the other category pages, `templates/header.php`, `templates/footer.php`, `includes/page_bootstrap.php`
- Impact: the consent fix, CSP changes, and OG image fix all need edits in 22 places. Divergence is already visible (`og:image` differs from `img/og-image.svg`).
- Fix approach: move the shared head into `templates/header.php` and drive page-specific meta from variables. The category PHP files then shrink to data and a `section()` call.

### Two parallel product importers
- `db/migrate_products.php` and `db/migrate_catalog_products.php` both populate `products`. Only one is current. Which one is current should be recorded in the journal, and the other removed or moved to `db/legacy/`.
- Files: `db/migrate_products.php`, `db/migrate_catalog_products.php`, `db/migrate_pages.php`, `db/extract_icons.php`

### Icon mapping is per section, not per product
- The journal records that icons were not per product, so identical icons repeat within a section (for example 18 identical `apa-canal` cards). The generated panel is the fallback. Real images are the fix (see missing photos).
- Files: `includes/product_icons.php`, `db/extract_icons.php`

### Mixed-language code and comments
- Code comments and UI strings are in Romanian, identifiers mix Romanian and English (`productsForSection`, `renderEmptyNotice`, `section_key`, `pageSlug`). This is consistent in most files and is a convention, not a bug. New code should follow the same pattern.

### Tests exist but have no runner
- Files: `admin/tests/test_db.php`, `admin/tests/test_helpers.php`, `admin/tests/test_product_grid.php`, `admin/tests/test_products_repo.php`, `admin/tests/test_sanitize.php`
- The journal records 198 assertions passing, run as plain PHP scripts (`php admin/tests/test_sanitize.php`). There is no CI, no `composer.json`, and no single runner, so the suite runs only when someone remembers it. The tests are blocked from the web by `admin/.htaccess`.
- Fix approach: add a `tests/run.php` that runs every file and exits non-zero on failure, and call it from the deploy step before the copy.

### Committed development artifacts
- `.superpowers/sdd/` (review diffs and reports) and `docs/superpowers/` (plans and specs) are tracked in git. `.superpowers/sdd/.gitignore` ignores its own contents, but the files are still tracked. Decide whether these belong in the repo; if not, remove them from the index with `git rm --cached`.

### No application-level dependency manifest
- There is no `composer.json` and no lockfile for PHP. The admin depends on PHP extensions (`pdo_mysql`, `fileinfo`, `gd` for `getimagesize`) with no declared version. TinyMCE is loaded from a CDN without a pinned file hash.
- Files: `admin/views/pages/edit.php` (line 43)

### Dependency risk: TinyMCE `no-api-key` cloud build
- The `no-api-key` build is intended for trial and development use, and TinyMCE shows a notice or restricts features outside those domains. If the editor is used in production, a Tiny API key or a self-hosted build is required.
- Files: `admin/views/pages/edit.php` (line 43)

---

## Fragile Areas

### Deploy pipeline (`.cpanel.yml`)
- Files: `.cpanel.yml`
- Why fragile: the deploy copies by wildcard (`cp *.html`, `cp *.php`) from the repo root, and removes `${f%.php}.html` files in the same loop. Any new root file with a `.php` extension lands in production; any stale `.html` with the same base name is deleted. The PHP binary is selected by probing paths in order.
- Safe modification: test a change with the log file (see Security) and the live HTTP check from the journal, and keep the list of copied paths explicit when adding a directory.
- Test coverage: none automated.

### Catalog data load (`db/deploy_db.php`)
- Files: `db/deploy_db.php`, `db/productie_date.sql`, `db/migration_00*.sql`
- Why fragile: migrations are "idempotent" by design (per journal) but they rely on the guard logic described above. Adding a column in a new migration without a matching change in `productie_date.sql` can break the import on the next fresh database.
- Safe modification: run the full sequence on an empty local database (`php db/deploy_db.php` on Laragon) before every deploy.

### `products_repo.php` degradation contract
- Files: `includes/products_repo.php`
- Why fragile: every function catches `Throwable` and returns `[]`. This keeps pages at 200 when the DB is down (by design, per journal), but a real bug in a query also shows as an empty grid with a WhatsApp fallback, and nothing is alerted. Errors go only to `error_log`.
- Safe modification: check the PHP error log after every catalog change. Add a counter or a visible admin warning if the products query fails.

### `.htaccess` routing order
- Files: `.htaccess` (URL Routing block)
- Why fragile: the order is significant. `/admin/` is excluded first, then `.html` maps to `.php` when the file exists, then real files and directories pass through, then slugs go to `router.php`. A new static `.html` file with the same slug as a category will be served instead of the DB category.
- Safe modification: after adding a file, test `/<slug>.html` and `/<slug>` on the live domain.

### Session and IP binding
- Files: `admin/helpers/Auth.php` (`isLoggedIn`, `login`)
- Why fragile: the session is bound to `REMOTE_ADDR` and `User-Agent`. Mobile networks and Cloudflare routing can change the IP mid-session, which logs the admin out with no message. (unverified)

---

## Test Coverage Gaps

**Public catalog rendering:**
- Not tested: end-to-end rendering of the 13 category pages and `catalog.php` against a real database.
- Files: `admin/tests/test_product_grid.php` (unit-level), `templates/category.php`
- Risk: a template change can break a page with no failing test.
- Priority: High

**Upload edge cases:**
- Not tested: GIF polyglots, oversized dimensions near the 6000 px limit, `realpath` edge cases in `Upload::image()`.
- Files: `admin/helpers/Upload.php`, no `admin/tests/test_upload.php`
- Priority: Medium

**Auth flows:**
- Not tested: rate-limit behaviour behind a proxy, session IP-change logout, CSRF token rotation across tabs.
- Files: `admin/controllers/AuthController.php`, `admin/helpers/Auth.php`, `admin/helpers/Csrf.php`
- Priority: High

**Sanitiser bypasses:**
- Not tested: single-quoted `href`, unquoted `on*=`, mixed-case `javascript:`.
- Files: `admin/tests/test_sanitize.php`, `admin/helpers/Sanitize.php`
- Priority: High (stored XSS)

**Deploy guard:**
- Not tested: products table emptied after the admin deletes everything.
- Files: `db/deploy_db.php`
- Priority: High

---

## Journal Items: Verified Status

| Journal item | Status in code |
|---|---|
| Production deploys from `feat/catalog-db`, not `main` | Confirmed (journal; `.cpanel.yml` has no branch check) |
| 31 product photos missing (404) | Confirmed: 31 listed in journal, plus `img/masina-gaurit-acumulator.jpg` (not in the list). 32 missing product files in total. |
| `__deploy_m7x2verif.log` publicly readable | Confirmed by code: no deny rule in root `.htaccess`; file written by `.cpanel.yml` on each deploy |
| 3000+ products import pending | Confirmed: no importer for that source in `db/`; repo already supports `limit`/`offset` |
| Cloudflare caches JS/CSS for 4h | Consistent with code: static `.html` pages reference `css/style.css` and `js/main.js` without a version; `templates/header.php` versions only the PHP pages |
| New third-party scripts need CSP in `.htaccess` | Confirmed: `.htaccess` lists hosts explicitly; admin CSP is separate (`admin/.htaccess`) |
| LiteSpeed does not honour `<IfModule>` like Apache | Not verifiable from the repo. Treated as unverified; see LiteSpeed section |

---

*Concerns audit: 2026-10-13*
