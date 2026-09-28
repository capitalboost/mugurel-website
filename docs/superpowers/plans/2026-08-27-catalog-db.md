# Catalog unificat pe baza de date — plan de implementare

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Baza de date devine sursa unica de adevar pentru cele 231 de produse, fara ca vreo pagina sa isi schimbe aspectul sau URL-ul, si fara sa se piarda continut SEO.

**Architecture:** Paginile de categorie devin `.php` si isi pastreaza integral invelisul editorial (JSON-LD, H1, headere de sectiune, FAQ); se inlocuieste doar continutul fiecarui `<div class="materials-grid">` cu un apel catre un repository. Un rewrite in `.htaccess` pastreaza URL-urile publice `.html`. Taxonomia (`category_id`, `product_categories`) e separata de plasarea editoriala (`page_slug`, `section_key`).

**Tech Stack:** PHP 8.1 (handler cPanel), MySQL 8 / MariaDB via PDO, Apache mod_rewrite, deploy prin cPanel Git Version Control (`.cpanel.yml`). Fara framework, fara Composer. Teste: scripturi PHP simple in stilul existent din `Website/admin/tests/`.

**Spec:** `Website/docs/superpowers/specs/2026-08-27-catalog-db-design.md`

## Global Constraints

- **Fara `Co-Authored-By: Claude`** sau orice mentiune Claude/Anthropic in commit-uri sau PR-uri.
- **Fara `git push` fara acord explicit**, de fiecare data. Commit local e permis.
- **Textele afisate pe site sunt fara diacritice** — conventie existenta in tot frontendul.
- Aspectul paginilor nu se schimba. Orice diferenta vizuala fata de azi e un bug, nu o imbunatatire.
- URL-urile publice raman `.html`. Zero redirect-uri.
- Daca baza de date nu raspunde, pagina se randeaza oricum cu tot continutul editorial. Niciodata 500.
- Descrierile de produs: ~85 de caractere, pret "la cerere".
- Cele doua carduri (`.material-card` si `.prod-card`) raman vizual distincte. Nu se unifica.
- Stilul testelor: `ok(bool $cond, string $msg)`, iesire `✓`/`✗`, `exit($fail > 0 ? 1 : 0)`.

## File Structure

| Fisier | Responsabilitate |
|---|---|
| `Website/db/migration_002_hierarchy.sql` | **Creat.** Ierarhie categorii, coloane noi pe `products`, tabel `product_categories`, seed subcategorii. |
| `Website/db/extract_icons.php` | **Creat.** Script one-shot: scoate cele 66 de SVG-uri din HTML si genereaza biblioteca. Ruleaza local, nu se deployeaza. |
| `Website/includes/product_icons.php` | **Generat de scriptul de mai sus.** Doar array `key => svg` + `renderIcon()`. |
| `Website/includes/products_repo.php` | **Creat.** Doar SQL. Fara HTML, fara `echo`. |
| `Website/includes/product_grid.php` | **Creat.** Doar randare. Fara SQL. |
| `Website/includes/page_bootstrap.php` | **Creat in Task 6.** Prologul comun al celor 13 pagini; defineste `section()` o singura data. |
| `Website/db/migrate_products.php` | **Rescris.** Import idempotent din HTML in DB, cu `page_slug`, `section_key`, `icon_key`, `icon_label`, subcategorie. |
| `Website/electrice.php` | **Creat** din `electrice.html` (pilot). Cele 4 grile devin apeluri. |
| `Website/.htaccess` | **Modificat.** Regula `.html` → `.php` inaintea passthrough-ului. |
| `Website/.cpanel.yml` | **Modificat.** Curata `.html`-urile ramase fara pereche. |
| `Website/admin/tests/test_products_repo.php` | **Creat.** Teste pe repo (necesita DB). |
| `Website/admin/tests/test_product_grid.php` | **Creat.** Teste pe randare (fara DB). |
| `Website/admin/tests/test_db.php` | **Modificat.** Asertiunea `=== 13` categorii devine `=== 31`. |

---

### Task 1: Migrarea schemei

**Files:**
- Create: `Website/db/migration_002_hierarchy.sql`
- Modify: `Website/admin/tests/test_db.php:24-26`

**Interfaces:**
- Consumes: schema existenta din `Website/db/schema.sql` (tabelele `categories`, `products`, `users`).
- Produces: `categories.parent_id`; `products.page_slug`, `products.section_key`, `products.icon_key`, `products.icon_label`; tabelul `product_categories(product_id, category_id)`; cheia unica `uq_page_section_name` pe `products(page_slug, section_key, name)` de care depinde idempotenta din Task 5.

Dupa migrare exista **31 de categorii**: 13 originale + `constructii` + 17 subcategorii.

- [ ] **Step 1: Scrie migrarea**

Creeaza `Website/db/migration_002_hierarchy.sql`:

```sql
-- Migrare 002 — ierarhie de categorii + plasare editoriala pentru produse.
-- Idempotenta: se poate rula de mai multe ori fara efecte secundare.

-- ── 1. Ierarhie de categorii ──────────────────────────────────────────
ALTER TABLE categories
    ADD COLUMN parent_id SMALLINT UNSIGNED NULL DEFAULT NULL AFTER slug,
    ADD KEY idx_parent (parent_id),
    ADD CONSTRAINT fk_cat_parent FOREIGN KEY (parent_id)
        REFERENCES categories(id) ON UPDATE CASCADE ON DELETE RESTRICT;

-- ── 2. Nume fara diacritice (conventia site-ului) ─────────────────────
UPDATE categories SET name='Acoperis'           WHERE slug='acoperis';
UPDATE categories SET name='Izolatie'           WHERE slug='izolatie';
UPDATE categories SET name='Gips Carton'        WHERE slug='gips-carton';
UPDATE categories SET name='Zidarie BCA'        WHERE slug='zidarie-bca';
UPDATE categories SET name='Gard & Imprejmuiri' WHERE slug='gard-imprejmuiri';
UPDATE categories SET name='Incalzire'          WHERE slug='incalzire';
UPDATE categories SET name='Gradina'            WHERE slug='gradina';
UPDATE categories SET name='Apa & Canal'        WHERE slug='apa-canal';

-- ── 3. Constructii ca parinte al celor 5 pagini de materiale ──────────
INSERT IGNORE INTO categories (slug, name, sort_order) VALUES ('constructii','Constructii',0);

UPDATE categories SET parent_id = (SELECT id FROM (SELECT id FROM categories WHERE slug='constructii') AS c)
WHERE slug IN ('acoperis','izolatie','gips-carton','zidarie-bca','gard-imprejmuiri');

-- ── 4. Cele 17 subcategorii din sidebar ───────────────────────────────
INSERT IGNORE INTO categories (slug, name, parent_id, sort_order)
SELECT v.slug, v.name, p.id, v.so FROM (
    SELECT 'cabluri'         AS slug, 'Cabluri & Conductori' AS name, 'electrice' AS parent, 1 AS so UNION ALL
    SELECT 'becuri',         'Becuri LED',           'electrice', 2 UNION ALL
    SELECT 'aparataj',       'Aparataj Electric',    'electrice', 3 UNION ALL
    SELECT 'iluminat',       'Corpuri Iluminat',     'electrice', 4 UNION ALL
    SELECT 'centrale',       'Centrale termice',     'incalzire', 1 UNION ALL
    SELECT 'radiatoare',     'Radiatoare',           'incalzire', 2 UNION ALL
    SELECT 'aer-conditionat','Aer conditionat',      'incalzire', 3 UNION ALL
    SELECT 'sobe',           'Sobe & Seminee',       'incalzire', 4 UNION ALL
    SELECT 'baterii',        'Baterii de apa',       'sanitare',  1 UNION ALL
    SELECT 'mobilier-baie',  'Mobilier baie',        'sanitare',  2 UNION ALL
    SELECT 'accesorii-baie', 'Accesorii baie',       'sanitare',  3 UNION ALL
    SELECT 'tevi',           'Tevi & Fitting',       'apa-canal', 1 UNION ALL
    SELECT 'canalizare',     'Canalizare PVC',       'apa-canal', 2 UNION ALL
    SELECT 'pompe',          'Pompe de apa',         'apa-canal', 3 UNION ALL
    SELECT 'living',         'Living',               'mobilier',  1 UNION ALL
    SELECT 'dormitor',       'Dormitor',             'mobilier',  2 UNION ALL
    SELECT 'bucatarie',      'Bucatarie',            'mobilier',  3
) AS v
JOIN (SELECT id, slug FROM categories) AS p ON p.slug = v.parent;

-- ── 5. Plasare editoriala + iconita pe produse ────────────────────────
ALTER TABLE products
    ADD COLUMN page_slug   VARCHAR(80)  NOT NULL DEFAULT '' AFTER category_id,
    ADD COLUMN section_key VARCHAR(60)  NULL DEFAULT NULL   AFTER page_slug,
    ADD COLUMN icon_key    VARCHAR(40)  NULL DEFAULT NULL   AFTER image_alt,
    ADD COLUMN icon_label  VARCHAR(120) NULL DEFAULT NULL   AFTER icon_key,
    ADD KEY idx_page_section (page_slug, section_key, sort_order),
    ADD UNIQUE KEY uq_page_section_name (page_slug, section_key, name);

-- ── 6. Cross-listing (produse in mai multe categorii) ─────────────────
CREATE TABLE IF NOT EXISTS product_categories (
    product_id  INT UNSIGNED     NOT NULL,
    category_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (product_id, category_id),
    KEY idx_cat (category_id),
    FOREIGN KEY (product_id)  REFERENCES products(id)   ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 2: Aplica migrarea si verifica numaratoarea**

Ruleaza (local sau prin phpMyAdmin pe server):

```bash
mysql -u <user> -p mugurel_cms < Website/db/migration_002_hierarchy.sql
mysql -u <user> -p mugurel_cms -e "
  SELECT COUNT(*) AS total FROM categories;
  SELECT COUNT(*) AS l1 FROM categories WHERE parent_id IS NULL;
  SELECT COUNT(*) AS l2 FROM categories WHERE parent_id IS NOT NULL;
  SELECT COUNT(*) AS orfane FROM categories c
    WHERE c.parent_id IS NOT NULL
      AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM categories) p WHERE p.id = c.parent_id);"
```

Expected: `total=31`, `l1=9`, `l2=22`, `orfane=0`.

(Cele 9 de nivel 1: constructii, electrice, incalzire, sanitare, apa-canal, gradina, mobilier, electrocasnice, scule-unelte — exact chipsurile din catalog. Cele 22 de nivel 2: 5 pagini de constructii + 17 subcategorii.)

- [ ] **Step 3: Actualizeaza testul care astepta 13 categorii**

In `Website/admin/tests/test_db.php`, inlocuieste blocul:

```php
// Test: query simplu pe categories
$stmt = Database::get()->query('SELECT COUNT(*) FROM categories');
$count = (int)$stmt->fetchColumn();
ok($count === 13, "13 categorii în DB (got $count)");
```

cu:

```php
// Test: ierarhia de categorii dupa migrarea 002
$count = (int)Database::get()->query('SELECT COUNT(*) FROM categories')->fetchColumn();
ok($count === 31, "31 categorii în DB (got $count)");

$l1 = (int)Database::get()->query('SELECT COUNT(*) FROM categories WHERE parent_id IS NULL')->fetchColumn();
ok($l1 === 9, "9 categorii de nivel 1 (got $l1)");

$orfane = (int)Database::get()->query(
    'SELECT COUNT(*) FROM categories c WHERE c.parent_id IS NOT NULL
       AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM categories) p WHERE p.id = c.parent_id)'
)->fetchColumn();
ok($orfane === 0, "nicio subcategorie orfana (got $orfane)");
```

- [ ] **Step 4: Ruleaza testul**

Run: `php Website/admin/tests/test_db.php`
Expected: `Rezultat: 5 passed, 0 failed`

- [ ] **Step 5: Commit**

```bash
git add Website/db/migration_002_hierarchy.sql Website/admin/tests/test_db.php
git commit -m "feat: ierarhie categorii si plasare editoriala pentru produse"
```

---

### Task 2: Biblioteca de iconite

**Files:**
- Create: `Website/db/extract_icons.php` (script one-shot, ruleaza local, NU se deployeaza)
- Create (generat): `Website/includes/product_icons.php`

**Interfaces:**
- Consumes: cele 13 fisiere `Website/*.html`.
- Produces: `PRODUCT_ICONS` (array `string $key => string $svg`) si `renderIcon(?string $key, ?string $label): string`. Task 4 apeleaza `renderIcon()`; Task 5 foloseste `iconKeyForSvg()` din acelasi script ca sa scrie `icon_key`.

Cheia unei iconite e `icon-` plus primele 8 caractere din SHA-1 al SVG-ului normalizat. Deterministă, deci scriptul de extragere si cel de migrare ajung independent la aceeasi cheie pentru acelasi desen.

- [ ] **Step 1: Scrie generatorul**

Creeaza `Website/db/extract_icons.php`:

```php
<?php
/**
 * Genereaza Website/includes/product_icons.php din SVG-urile aflate azi in
 * paginile de categorie. Ruleaza local, o singura data (si din nou daca se
 * adauga iconite noi in HTML). NU se deployeaza pe server.
 *
 * Usage: php Website/db/extract_icons.php
 */

const PAGES = ['acoperis','izolatie','gips-carton','zidarie-bca','gard-imprejmuiri',
               'electrice','incalzire','sanitare','apa-canal','gradina','mobilier',
               'electrocasnice','scule-unelte'];

/** Normalizeaza spatiile ca sa nu produca chei diferite pentru acelasi desen. */
function normalizeSvg(string $svg): string {
    return trim(preg_replace('/\s+/', ' ', $svg));
}

/** Cheia determinista a unei iconite. Folosita si de Website/db/migrate_products.php. */
function iconKeyForSvg(string $svg): string {
    return 'icon-' . substr(sha1(normalizeSvg($svg)), 0, 8);
}

if (PHP_SAPI !== 'cli') { die("Doar din linia de comanda.\n"); }

$root  = __DIR__ . '/../Website/';
$icons = [];

foreach (PAGES as $page) {
    $file = $root . $page . '.html';
    if (!is_file($file)) { fwrite(STDERR, "SKIP (lipsa): $page.html\n"); continue; }
    $html = file_get_contents($file);
    if (preg_match_all('#<div class="material-img-ph".*?(<svg.*?</svg>)#s', $html, $m)) {
        foreach ($m[1] as $svg) {
            $icons[iconKeyForSvg($svg)] = normalizeSvg($svg);
        }
    }
}

ksort($icons);

$out  = "<?php\n";
$out .= "/**\n";
$out .= " * GENERAT AUTOMAT de Website/db/extract_icons.php — nu edita manual.\n";
$out .= " * Iconitele placeholder ale cardurilor de produs, extrase din paginile statice.\n";
$out .= " */\n\n";
$out .= "const PRODUCT_ICONS = [\n";
foreach ($icons as $key => $svg) {
    $out .= "    '" . $key . "' => " . var_export($svg, true) . ",\n";
}
$out .= "];\n\n";
$out .= <<<'PHP'
/** Iconita generica pentru produsele fara icon_key (produse noi din admin). */
const PRODUCT_ICON_FALLBACK =
    '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5">'
    . '<rect x="15" y="12" width="50" height="36" rx="3"/><line x1="15" y1="24" x2="65" y2="24"/></svg>';

/**
 * Randeaza placeholder-ul unui card. Cheile necunoscute cad pe iconita generica,
 * ca un produs adaugat din admin sa nu randeze o gaura in grila.
 */
function renderIcon(?string $key, ?string $label): string {
    $svg = ($key !== null && isset(PRODUCT_ICONS[$key])) ? PRODUCT_ICONS[$key] : PRODUCT_ICON_FALLBACK;
    return '<div class="material-img-ph" style="display:flex">' . $svg
         . '<span>' . htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8') . '</span></div>';
}
PHP;
$out .= "\n";

file_put_contents($root . 'includes/product_icons.php', $out);
echo "Scris Website/includes/product_icons.php — " . count($icons) . " iconite.\n";
```

- [ ] **Step 2: Ruleaza generatorul**

Run: `php Website/db/extract_icons.php`
Expected: `Scris Website/includes/product_icons.php — 66 iconite.`

Daca numarul difera de 66, **opreste-te**: HTML-ul s-a schimbat fata de analiza si Task 5 va produce potriviri gresite. Investigheaza inainte de a continua.

- [ ] **Step 3: Verifica fisierul generat**

Run: `php -l Website/includes/product_icons.php && php -r "require 'Website/includes/product_icons.php'; echo count(PRODUCT_ICONS), PHP_EOL; echo renderIcon(array_key_first(PRODUCT_ICONS), 'Test'), PHP_EOL;"`

Expected: `No syntax errors`, apoi `66`, apoi un `<div class="material-img-ph" style="display:flex"><svg ...</svg><span>Test</span></div>`.

- [ ] **Step 4: Commit**

```bash
git add Website/db/extract_icons.php Website/includes/product_icons.php
git commit -m "feat: biblioteca de iconite pentru cardurile de produs"
```

---

### Task 3: Repository-ul de produse

**Files:**
- Create: `Website/includes/products_repo.php`
- Test: `Website/admin/tests/test_products_repo.php`

**Interfaces:**
- Consumes: `Database::get()` din `Website/admin/models/Database.php`; coloanele adaugate in Task 1.
- Produces:
  - `productsForSection(string $pageSlug, string $sectionKey, ?int $limit = null, int $offset = 0): array`
  - `productsForPage(string $pageSlug, ?int $limit = null, int $offset = 0): array`
  - `allProducts(?int $limit = null, int $offset = 0): array`

  Fiecare element are cheile: `id, name, short_description, icon_key, icon_label, price, price_unit, cat_slugs` (string, slug-uri L1 separate prin spatiu, pentru `data-cat`), `subcat_slug` (string sau null, pentru `data-subcat`), `cat_label` (eticheta pentru badge). Task 4 randeaza exact aceste chei.
- **La orice eroare de DB toate cele trei functii returneaza `[]`, nu arunca.** Fara asta, un MySQL cazut ar scoate paginile din index.

- [ ] **Step 1: Scrie testul care esueaza**

Creeaza `Website/admin/tests/test_products_repo.php`:

```php
<?php
require_once __DIR__ . '/../../includes/products_repo.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass,$fail; if($c){$pass++;echo"  ✓ $m\n";}else{$fail++;echo"  ✗ $m\n";} }

// Electrice are 23 de produse, distribuite 4/4/9/6 pe patru sectiuni.
$page = productsForPage('electrice');
ok(count($page) === 23, 'productsForPage(electrice) => 23 (got ' . count($page) . ')');

$sec = productsForSection('electrice', 'cabluri-conductori');
ok(count($sec) === 4, 'sectiunea cabluri-conductori => 4 (got ' . count($sec) . ')');

$all = allProducts();
ok(count($all) === 231, 'allProducts() => 231 (got ' . count($all) . ')');

// Forma randului — Task 4 depinde de exact aceste chei.
if ($page) {
    $r = $page[0];
    foreach (['id','name','short_description','icon_key','icon_label','price','price_unit',
              'cat_slugs','subcat_slug','cat_label','properties'] as $k) {
        ok(array_key_exists($k, $r), "randul contine cheia '$k'");
    }
}

// Proprietatile sunt atasate, in ordine, si listele au mai multe linii.
$cuProp = array_filter($all, fn($p) => count($p['properties']) > 1);
ok(count($cuProp) === 187, '187 produse cu mai multe blocuri (got ' . count($cuProp) . ')');
$liste = array_filter($all, fn($p) => array_filter($p['properties'], fn($x) => (int)$x['is_list'] === 1));
ok(count($liste) === 187, '187 produse au un bloc de tip lista (got ' . count($liste) . ')');

// limit/offset — nefolosite azi, dar trebuie sa functioneze pentru cele 3000.
ok(count(allProducts(10)) === 10, 'allProducts(10) => 10');
ok(allProducts(1, 1)[0]['id'] !== allProducts(1, 0)[0]['id'], 'offset schimba randul returnat');

// Produsele cross-listate au mai multe slug-uri in cat_slugs.
$multi = array_filter($all, fn($p) => str_contains(trim($p['cat_slugs']), ' '));
ok(count($multi) >= 2, 'cel putin 2 produse cross-listate (got ' . count($multi) . ')');

// Pagina inexistenta => array gol, nu eroare.
ok(productsForPage('nu-exista') === [], 'pagina inexistenta => []');

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
```

- [ ] **Step 2: Ruleaza testul ca sa confirmi ca esueaza**

Run: `php Website/admin/tests/test_products_repo.php`
Expected: FAIL cu `Failed to open stream ... products_repo.php` — fisierul nu exista inca.

- [ ] **Step 3: Scrie repository-ul**

Creeaza `Website/includes/products_repo.php`:

```php
<?php
/**
 * Acces la produse. Doar SQL — fara HTML, fara echo.
 * Randarea sta in includes/product_grid.php.
 *
 * Toate functiile returneaza [] daca baza de date nu raspunde. Pagina trebuie
 * sa se randeze cu tot continutul editorial chiar si cu MySQL cazut.
 */
require_once __DIR__ . '/../admin/models/Database.php';

/**
 * SELECT-ul comun. Deriva pentru fiecare produs:
 *  - cat_slugs    slug-urile de nivel 1 (proprie + cross-listate), separate prin spatiu.
 *                 Si categoriile cross-listate urca prin parent_id: un produs legat de o
 *                 subcategorie trebuie sa apara sub categoria ei de nivel 1, altfel filtrul
 *                 de nivel 1 din catalog nu l-ar mai gasi.
 *  - subcat_slug  slug-ul de nivel 2, daca produsul e intr-o subcategorie
 *  - cat_label    eticheta pentru badge (numele categoriei de nivel 1)
 */
function productsBaseSql(): string {
    return "
        SELECT p.id, p.name, p.short_description, p.icon_key, p.icon_label,
               p.price, p.price_unit,
               TRIM(CONCAT(
                   COALESCE(l1.slug, c.slug),
                   COALESCE((SELECT CONCAT(' ', GROUP_CONCAT(COALESCE(xl1.slug, x.slug) SEPARATOR ' '))
                             FROM product_categories pc
                             JOIN categories x ON x.id = pc.category_id
                             LEFT JOIN categories xl1 ON xl1.id = x.parent_id
                             WHERE pc.product_id = p.id), '')
               )) AS cat_slugs,
               CASE WHEN c.parent_id IS NULL THEN NULL ELSE c.slug END AS subcat_slug,
               COALESCE(l1.name, c.name) AS cat_label
        FROM products p
        JOIN categories c  ON c.id = p.category_id
        LEFT JOIN categories l1 ON l1.id = c.parent_id
        WHERE p.is_visible = 1
    ";
}

/** Adauga LIMIT/OFFSET. Valorile sunt fortate la int, deci nu sunt injectabile. */
function withLimit(string $sql, ?int $limit, int $offset): string {
    if ($limit === null) { return $sql; }
    return $sql . ' LIMIT ' . max(0, $limit) . ' OFFSET ' . max(0, $offset);
}

/**
 * Ataseaza fiecarui produs blocurile lui de proprietati, sub cheia 'properties'.
 * O singura interogare pentru tot setul, nu una pe produs.
 * Fiecare proprietate: ['label' => string, 'body' => string, 'is_list' => int].
 * Pentru is_list = 1, body contine cate un element pe linie.
 */
function attachProperties(array $rows): array {
    if (!$rows) { return $rows; }
    try {
        $ids  = array_column($rows, 'id');
        $in   = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::get()->prepare(
            "SELECT product_id, label, body, is_list FROM product_properties
             WHERE product_id IN ($in) ORDER BY product_id, sort_order"
        );
        $stmt->execute($ids);
        $byProduct = [];
        foreach ($stmt->fetchAll() as $pr) {
            $byProduct[$pr['product_id']][] = $pr;
        }
        foreach ($rows as &$r) { $r['properties'] = $byProduct[$r['id']] ?? []; }
        unset($r);
        return $rows;
    } catch (Throwable $e) {
        error_log('attachProperties: ' . $e->getMessage());
        foreach ($rows as &$r) { $r['properties'] = []; }
        unset($r);
        return $rows;
    }
}

function productsForSection(string $pageSlug, string $sectionKey, ?int $limit = null, int $offset = 0): array {
    try {
        $sql = productsBaseSql() . ' AND p.page_slug = ? AND p.section_key = ?
                ORDER BY p.sort_order, p.id';
        $stmt = Database::get()->prepare(withLimit($sql, $limit, $offset));
        $stmt->execute([$pageSlug, $sectionKey]);
        return attachProperties($stmt->fetchAll());
    } catch (Throwable $e) {
        error_log('productsForSection: ' . $e->getMessage());
        return [];
    }
}

function productsForPage(string $pageSlug, ?int $limit = null, int $offset = 0): array {
    try {
        $sql = productsBaseSql() . ' AND p.page_slug = ?
                ORDER BY p.section_key, p.sort_order, p.id';
        $stmt = Database::get()->prepare(withLimit($sql, $limit, $offset));
        $stmt->execute([$pageSlug]);
        return attachProperties($stmt->fetchAll());
    } catch (Throwable $e) {
        error_log('productsForPage: ' . $e->getMessage());
        return [];
    }
}

function allProducts(?int $limit = null, int $offset = 0): array {
    try {
        $sql = productsBaseSql() . ' ORDER BY p.page_slug, p.sort_order, p.id';
        return attachProperties(Database::get()->query(withLimit($sql, $limit, $offset))->fetchAll());
    } catch (Throwable $e) {
        error_log('allProducts: ' . $e->getMessage());
        return [];
    }
}
```

- [ ] **Step 4: Ruleaza testul**

Run: `php Website/admin/tests/test_products_repo.php`
Expected: `Rezultat: 20 passed, 0 failed`

Testul presupune ca datele sunt deja importate. **Daca Task 5 nu a rulat inca**, asertiunile de numaratoare vor esua cu 0 — asta e corect si asteptat; reia acest pas dupa Task 5. Asertiunile de forma a randului si cea de pagina inexistenta trebuie sa treaca oricum.

- [ ] **Step 5: Verifica degradarea eleganta cu DB cazut**

Run: `php -r "define('DB_HOST','127.0.0.1'); require 'Website/includes/products_repo.php'; var_dump(allProducts());"` cu MySQL oprit, sau cu o parola gresita in `admin/config.local.php`.
Expected: `array(0) {}` si o intrare in error log. **Nicio exceptie neprinsa, niciun fatal error.**

- [ ] **Step 6: Commit**

```bash
git add Website/includes/products_repo.php Website/admin/tests/test_products_repo.php
git commit -m "feat: repository produse cu degradare eleganta la caderea DB"
```

---

### Task 4: Randarea grilelor

**Files:**
- Create: `Website/includes/product_grid.php`
- Test: `Website/admin/tests/test_product_grid.php`

**Interfaces:**
- Consumes: `renderIcon()` din Task 2; randurile produse de Task 3, inclusiv cheia `properties`.
- Produces:
  - `renderMaterialCards(array $products): string` — varianta `.material-card`, pentru paginile de categorie.
  - `renderProdCards(array $products): string` — varianta `.prod-card`, pentru catalog (Task 8).
  - `renderEmptyNotice(): string` — mesajul afisat cand lista e goala.

Testele nu ating baza de date: functiile primesc array-uri construite in test.

- [ ] **Step 1: Scrie testul care esueaza**

Creeaza `Website/admin/tests/test_product_grid.php`:

```php
<?php
require_once __DIR__ . '/../../includes/product_grid.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $m): void { global $pass,$fail; if($c){$pass++;echo"  ✓ $m\n";}else{$fail++;echo"  ✗ $m\n";} }

$sample = [[
    'id' => 1,
    'name' => 'Cablu CYY-F 3x2.5mm² 100m',
    'short_description' => 'Cablu electric pentru instalatii fixe. Conductor cupru, izolatie PVC. Rola 100m.',
    'icon_key' => 'inexistenta', 'icon_label' => 'Cablu CYY-F 3x2.5mm²',
    'price' => null, 'price_unit' => null,
    'cat_slugs' => 'electrice', 'subcat_slug' => 'cabluri', 'cat_label' => 'Electrice',
    'properties' => [
        ['label' => 'Aspect',   'body' => 'Cablu flexibil cu 3 conductori de cupru.', 'is_list' => 0],
        ['label' => 'Avantaje', 'body' => "Flexibil, usor de pozat
Rezistenta la UV", 'is_list' => 1],
    ],
]];

// ── varianta .material-card (pagini de categorie) ──
$m = renderMaterialCards($sample);
ok(str_contains($m, 'class="material-card"'),        'material: containerul .material-card');
ok(str_contains($m, 'class="material-name"'),        'material: titlul .material-name');
ok(str_contains($m, 'class="prop-val"'),             'material: textul in .prop-val');
ok(str_contains($m, '<span class="prop-label">Aspect</span>'), 'material: eticheta primului bloc');
ok(str_contains($m, '<ul class="prop-list">'),       'material: blocul de tip lista devine <ul>');
ok(substr_count($m, '<li>') === 2,                   'material: doua elemente in lista');
ok(substr_count($m, 'class="material-prop"') === 2,  'material: ambele blocuri randate');
ok(str_contains($m, 'class="btn-wa-material"'),      'material: butonul e .btn-wa-material');
ok(str_contains($m, 'href="https://wa.me/40749130565'), 'material: link WhatsApp direct');
ok(str_contains($m, 'Pret la cerere'),               'material: pret la cerere cand price e null');
ok(!str_contains($m, 'class="prod-card"'),           'material: NU foloseste clasele de catalog');

// ── varianta .prod-card (catalog) ──
$p = renderProdCards($sample);
ok(str_contains($p, 'class="prod-card"'),            'prod: containerul .prod-card');
ok(str_contains($p, 'data-cat="electrice"'),         'prod: data-cat din cat_slugs');
ok(str_contains($p, 'data-subcat="cabluri"'),        'prod: data-subcat din subcat_slug');
ok(str_contains($p, 'class="prod-desc"'),            'prod: descrierea in .prod-desc');
ok(str_contains($p, 'class="btn-wa-prod"'),          'prod: butonul e .btn-wa-prod');
ok(str_contains($p, 'data-wa-product='),             'prod: atributul data-wa-product');
ok(!str_contains($p, 'class="material-card"'),       'prod: NU foloseste clasele de categorie');

// ── escaping ──
$xss = $sample;
$xss[0]['name'] = 'Test "<script>alert(1)</script>';
foreach (['renderMaterialCards','renderProdCards'] as $fn) {
    $h = $fn($xss);
    ok(!str_contains($h, '<script>'), "$fn escapeaza numele");
}

// ── multi-categorie ──
$multi = $sample;
$multi[0]['cat_slugs'] = 'incalzire apa-canal';
ok(str_contains(renderProdCards($multi), 'data-cat="incalzire apa-canal"'), 'prod: cat_slugs multiple');

// Produs fara proprietati: cade pe short_description, cardul nu ramane gol.
$fara = $sample; $fara[0]['properties'] = [];
ok(str_contains(renderMaterialCards($fara), 'class="prop-val"'), 'fara properties => foloseste short_description');

// ── lista goala ──
ok(renderMaterialCards([]) === '', 'lista goala => string gol');
ok(str_contains(renderEmptyNotice(), 'wa.me/40749130565'), 'mesajul gol trimite pe WhatsApp');

echo "\nRezultat: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
```

- [ ] **Step 2: Ruleaza testul ca sa confirmi ca esueaza**

Run: `php Website/admin/tests/test_product_grid.php`
Expected: FAIL cu `Failed to open stream ... product_grid.php`.

- [ ] **Step 3: Scrie randarea**

Creeaza `Website/includes/product_grid.php`:

```php
<?php
/**
 * Randarea cardurilor de produs. Doar HTML — fara SQL.
 *
 * Site-ul are doua carduri vizual distincte, fara clase comune:
 *   .material-card  in paginile de categorie   -> renderMaterialCards()
 *   .prod-card      in catalog                 -> renderProdCards()
 * Sunt tinute separate intentionat; unificarea lor ar fi o schimbare de design.
 */
require_once __DIR__ . '/product_icons.php';

const WA_PHONE = '40749130565';

/** SVG-ul de WhatsApp, identic cu cel din paginile statice. */
const WA_ICON = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.231 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>';

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Pretul afisat. Conventia site-ului: aproape totul e "la cerere". */
function priceLabel(array $p): string {
    if ($p['price'] === null || (float)$p['price'] <= 0) { return 'Pret la cerere'; }
    return number_format((float)$p['price'], 2, ',', '.') . ' lei'
         . ($p['price_unit'] ? ' / ' . e($p['price_unit']) : '');
}

/** Link-ul WhatsApp precompletat pentru un produs. */
function waLink(string $productName): string {
    return 'https://wa.me/' . WA_PHONE . '?text='
         . rawurlencode('Buna ziua! Sunt interesat de: ' . $productName . '. Puteti confirma disponibilitatea?');
}

/**
 * Blocurile de proprietati ale unui card, in ordinea din DB.
 * Un bloc e fie text, fie o lista — `body` cu is_list = 1 are un element pe linie.
 * Produsele fara proprietati cad pe short_description, ca sa nu ramana cardul gol.
 */
function renderProps(array $p): string {
    $props = $p['properties'] ?? [];
    if (!$props) {
        if (($p['short_description'] ?? '') === '') { return ''; }
        $props = [['label' => 'Descriere', 'body' => $p['short_description'], 'is_list' => 0]];
    }
    $out = '<div class="material-props">';
    foreach ($props as $pr) {
        $out .= '<div class="material-prop"><span class="prop-label">' . e($pr['label']) . '</span>';
        if ((int)$pr['is_list'] === 1) {
            $out .= '<ul class="prop-list">';
            foreach (explode("
", $pr['body']) as $item) {
                if (trim($item) !== '') { $out .= '<li>' . e(trim($item)) . '</li>'; }
            }
            $out .= '</ul>';
        } else {
            $out .= '<span class="prop-val">' . e($pr['body']) . '</span>';
        }
        $out .= '</div>';
    }
    return $out . '</div>';
}

/** Varianta din paginile de categorie. */
function renderMaterialCards(array $products): string {
    $out = '';
    foreach ($products as $p) {
        $out .= '<div class="material-card">'
              . '<div class="material-img-wrap">' . renderIcon($p['icon_key'], $p['icon_label']) . '</div>'
              . '<div class="material-body">'
              . '<h3 class="material-name">' . e($p['name']) . '</h3>'
              . renderProps($p)
              . '<div class="material-footer">'
              . '<span class="material-price">' . priceLabel($p) . '</span>'
              . '<a href="' . e(waLink($p['name'])) . '" class="btn-wa-material" target="_blank" rel="noopener">'
              . WA_ICON . 'Afla disponibilitate</a>'
              . '</div></div></div>';
    }
    return $out;
}

/** Varianta din catalog. Filtrele JS existente citesc data-cat / data-subcat. */
function renderProdCards(array $products): string {
    $out = '';
    foreach ($products as $p) {
        $subcat = $p['subcat_slug'] ? ' data-subcat="' . e($p['subcat_slug']) . '"' : '';
        $out .= '<div class="prod-card" data-cat="' . e($p['cat_slugs']) . '"' . $subcat . '>'
              . '<div class="prod-img-wrap">' . renderIcon($p['icon_key'], $p['icon_label']) . '</div>'
              . '<div class="prod-body">'
              . '<div class="prod-badge">' . e($p['cat_label']) . '</div>'
              . '<h3 class="prod-name">' . e($p['name']) . '</h3>'
              . '<div class="prod-desc">' . e($p['short_description']) . '</div>'
              . '<div class="prod-footer">'
              . '<div class="prod-price">' . priceLabel($p) . '</div>'
              . '<button class="btn-wa-prod" data-wa-product="' . e($p['name']) . '">'
              . WA_ICON . 'Afla disponibilitate</button>'
              . '</div></div></div>';
    }
    return $out;
}

/** Afisat cand o sectiune nu are produse — inclusiv cand DB-ul e cazut. */
function renderEmptyNotice(): string {
    return '<p class="cat-empty-note">Lista de produse nu poate fi afisata momentan. '
         . 'Scrie-ne pe <a href="https://wa.me/' . WA_PHONE . '" target="_blank" rel="noopener">WhatsApp</a> '
         . 'si iti spunem imediat ce avem in stoc.</p>';
}
```

- [ ] **Step 4: Ruleaza testul**

Run: `php Website/admin/tests/test_product_grid.php`
Expected: `Rezultat: 25 passed, 0 failed`

- [ ] **Step 5: Compara randarea cu HTML-ul existent**

Ruleaza un card real prin `renderMaterialCards()` si compara-l vizual cu blocul corespunzator din `electrice.html` (linia ~1085). Ordinea si numele claselor trebuie sa coincida. Diferentele de spatiere albă sunt acceptabile; o clasa lipsa sau in plus, nu.

- [ ] **Step 6: Adauga stilul pentru mesajul gol**

In `Website/css/` (fisierul care contine deja `.cat-fallback-note`), adauga langa el:

```css
.cat-empty-note {
  background: #fffbe6;
  border-left: 3px solid #f0c000;
  padding: 14px 18px;
  margin: 0 0 20px;
  font-size: 14px;
  color: #5a4a00;
}
.cat-empty-note a { color: #1a7f37; font-weight: 600; }
```

- [ ] **Step 7: Commit**

```bash
git add Website/includes/product_grid.php Website/admin/tests/test_product_grid.php Website/css/
git commit -m "feat: randare carduri produs pentru pagini de categorie si catalog"
```

---

### Task 5: Rescrierea importului

**Files:**
- Modify: `Website/db/migrate_products.php` (rescriere completa)

**Interfaces:**
- Consumes: coloanele din Task 1. `iconKeyForSvg()` si `normalizeSvg()` sunt **redefinite** aici, identic cu Task 2 — `extract_icons.php` ruleaza doar local, iar acest script si pe server, deci nu pot partaja un fisier. Cheia fiind un SHA-1 determinist, cele doua ajung la aceeasi valoare. Daca modifici una, modific-o si pe cealalta.
- Produces: 231 de randuri in `products` cu `page_slug`, `section_key`, `icon_key`, `icon_label` si `category_id` pe subcategorie; randuri in `product_categories` pentru produsele cross-listate. Task 3 si Task 6 citesc aceste date.

**Idempotenta** se bazeaza pe `uq_page_section_name` din Task 1: `INSERT ... ON DUPLICATE KEY UPDATE`. Rerularea actualizeaza, nu dubleaza.

- [ ] **Step 1: Rescrie scriptul de import**

Inlocuieste continutul lui `Website/db/migrate_products.php`:

```php
<?php
/**
 * Importa produsele din paginile statice HTML in baza de date.
 * Idempotent: rerularea actualizeaza randurile existente, nu creeaza dubluri.
 *
 * UPLOAD in public_html, acceseaza prin browser, STERGE imediat dupa.
 * Sau local: php Website/db/migrate_products.php
 */

// Auto-detect: pe server config.php e in admin/, local e in ../Website/admin/
if (file_exists(__DIR__ . '/admin/config.php')) {
    require_once __DIR__ . '/admin/config.php';
    require_once __DIR__ . '/admin/models/Database.php';
    $htmlBase = __DIR__ . '/';
} else {
    require_once __DIR__ . '/../Website/admin/config.php';
    require_once __DIR__ . '/../Website/admin/models/Database.php';
    $htmlBase = __DIR__ . '/../Website/';
}

$isCli = PHP_SAPI === 'cli';
if (!$isCli) { header('Content-Type: text/plain; charset=utf-8'); }

const PAGES = ['acoperis','izolatie','gips-carton','zidarie-bca','gard-imprejmuiri',
               'electrice','incalzire','sanitare','apa-canal','gradina','mobilier',
               'electrocasnice','scule-unelte'];

/**
 * Produse care apar in mai multe categorii de nivel 1. Cheia e numele exact din HTML.
 * Valoarea: slug-urile L1 SUPLIMENTARE (cea proprie se deduce din category_id).
 */
const CROSS_LISTED = [
    // Numele sunt cele din PAGINILE DE CATEGORIE (sursa migrarii), nu cele din
    // catalog.html, care difera. Valoarea = categoriile L1 SUPLIMENTARE.
    'Teava PPR PN20 20mm bara 4m'         => ['apa-canal'],  // sta in incalzire.html
    'Chiuveta Bucatarie Inox 1 Cuva 60cm' => ['mobilier'],   // sta in sanitare.html
];

function normalizeSvg(string $svg): string { return trim(preg_replace('/\s+/', ' ', $svg)); }
function iconKeyForSvg(string $svg): string { return 'icon-' . substr(sha1(normalizeSvg($svg)), 0, 8); }
function stripTags2(string $s): string { return trim(preg_replace('/\s+/', ' ', strip_tags($s))); }

/** Slug din titlul unei sectiuni: "Cabluri &amp; Conductori" -> "cabluri-conductori". */
function sectionKeyFromTitle(string $title): string {
    $t = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
    $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t) ?: $t;
    $t = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $t));
    return trim(substr($t, 0, 60), '-');
}

$db = Database::get();

$catBySlug = [];
foreach ($db->query('SELECT id, slug, parent_id FROM categories')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $catBySlug[$r['slug']] = $r;
}

$adminId = (int)$db->query('SELECT id FROM users WHERE role="admin" LIMIT 1')->fetchColumn();
if (!$adminId) { die("Eroare: nu exista user admin in DB. Ruleaza intai Website/db/seed_admin.php\n"); }

$insert = $db->prepare(
    'INSERT INTO products
        (category_id, page_slug, section_key, name, short_description,
         icon_key, icon_label, sort_order, created_by)
     VALUES (:cat, :page, :section, :name, :descr, :ikey, :ilabel, :sort, :by)
     ON DUPLICATE KEY UPDATE
        category_id = VALUES(category_id),
        short_description = VALUES(short_description),
        icon_key = VALUES(icon_key), icon_label = VALUES(icon_label),
        sort_order = VALUES(sort_order), id = LAST_INSERT_ID(id)'
);
$linkCross = $db->prepare(
    'INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (?, ?)'
);

$total = 0; $crossed = 0; $noIcon = 0; $perPage = [];

foreach (PAGES as $page) {
    $file = $htmlBase . $page . '.html';
    if (!is_file($file)) { echo "SKIP (lipsa): $page.html\n"; continue; }
    if (!isset($catBySlug[$page])) { echo "SKIP (categorie lipsa in DB): $page\n"; continue; }

    $html  = file_get_contents($file);
    $count = 0;

    // Sparge pagina in sectiuni; retine titlul <h2> al fiecareia.
    $parts = preg_split('/(<section[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    $sectionTitle = null;

    foreach ($parts as $part) {
        if (str_starts_with($part, '<section')) { $sectionTitle = null; continue; }
        if (!str_contains($part, 'class="material-card"')) { continue; }

        if (preg_match('#<h2[^>]*>(.*?)</h2>#s', $part, $h2)) {
            $sectionTitle = stripTags2($h2[1]);
        }
        $sectionKey = $sectionTitle ? sectionKeyFromTitle($sectionTitle) : 'produse';

        preg_match_all('#<div class="material-card">(.*?)(?=<div class="material-card">|\z)#s', $part, $cards);
        $order = 0;

        foreach ($cards[1] as $card) {
            if (!preg_match('#<h3 class="material-name">(.*?)</h3>#s', $card, $n)) { continue; }
            $name = stripTags2($n[1]);
            if ($name === '') { continue; }

            preg_match('#<span class="prop-val">(.*?)</span>#s', $card, $d);
            $descr = isset($d[1]) ? stripTags2($d[1]) : '';

            preg_match('#<div class="material-img-ph".*?(<svg.*?</svg>)#s', $card, $s);
            $iconKey = isset($s[1]) ? iconKeyForSvg($s[1]) : null;
            if ($iconKey === null) { $noIcon++; }

            preg_match('#<div class="material-img-ph".*?<span>(.*?)</span>#s', $card, $l);
            $iconLabel = isset($l[1]) ? stripTags2($l[1]) : $name;

            // category_id = categoria cea mai specifica pe care o cunoastem: pagina insasi.
            $insert->execute([
                ':cat' => $catBySlug[$page]['id'], ':page' => $page, ':section' => $sectionKey,
                ':name' => $name, ':descr' => $descr,
                ':ikey' => $iconKey, ':ilabel' => $iconLabel,
                ':sort' => ++$order, ':by' => $adminId,
            ]);

            if (isset(CROSS_LISTED[$name])) {
                $pid = (int)$db->lastInsertId();
                foreach (CROSS_LISTED[$name] as $extra) {
                    if (isset($catBySlug[$extra])) {
                        $linkCross->execute([$pid, $catBySlug[$extra]['id']]);
                        $crossed++;
                    }
                }
            }
            $count++; $total++;
        }
    }
    $perPage[$page] = $count;
    echo str_pad($page, 20) . " $count produse\n";
}

echo "\nTOTAL: $total produse, $crossed legaturi cross-listate, $noIcon carduri fara iconita\n";
```

- [ ] **Step 2: Ruleaza importul**

Run: `php Website/db/migrate_products.php`

Expected — exact aceste numere pe pagina:

```
acoperis             22 produse
izolatie             10 produse
gips-carton          14 produse
zidarie-bca          17 produse
gard-imprejmuiri     13 produse
electrice            23 produse
incalzire            20 produse
sanitare             20 produse
apa-canal            22 produse
gradina              18 produse
mobilier             15 produse
electrocasnice       17 produse
scule-unelte         20 produse

TOTAL: 231 produse, 2 legaturi cross-listate, 4 carduri fara iconita
```

Orice abatere de la 231 inseamna ca parsarea a ratat carduri. **Nu continua** — investigheaza intai.

- [ ] **Step 3: Verifica idempotenta**

Run: `php Website/db/migrate_products.php && mysql -u <user> -p mugurel_cms -e "SELECT COUNT(*) FROM products;"`
Expected: tot `231` dupa a doua rulare. Daca apar 454, cheia `uq_page_section_name` din Task 1 lipseste.

- [ ] **Step 4: Verifica distributia pe sectiuni pentru pilot**

Run:
```bash
mysql -u <user> -p mugurel_cms -e "
  SELECT section_key, COUNT(*) FROM products WHERE page_slug='electrice' GROUP BY section_key ORDER BY section_key;"
```
Expected: patru sectiuni insumand 23, cu grupurile de 4, 4, 9 si 6.

- [ ] **Step 5: Ruleaza acum testul repo-ului din Task 3**

Run: `php Website/admin/tests/test_products_repo.php`
Expected: `Rezultat: 20 passed, 0 failed`

- [ ] **Step 6: Commit**

```bash
git add Website/db/migrate_products.php
git commit -m "feat: import idempotent produse cu sectiune editoriala si iconita"
```

---

### Task 5B: Proprietatile produselor

**Files:**
- Create: `Website/db/migration_003_properties.sql`
- Modify: `Website/db/migrate_products.php` (adauga importul proprietatilor)

**Interfaces:**
- Consumes: `products` populat de Task 5.
- Produces: tabelul `product_properties(product_id, sort_order, label, body, is_list)`. Task 3 il citeste prin `attachProperties()`; Task 4 il randeaza.

**De ce exista acest task:** cardurile de produs nu au o singura descriere, ci **N blocuri de proprietati** — 187 din cele 231 de carduri (80%) au mai multe. Etichetele reale, cu frecventa lor: `Avantaje` (187), `Specificatii` (141), `Aspect` (43), `Utilizare` (31), `Unde se foloseste` (18). Blocul `Avantaje` e o lista `<ul class="prop-list">`, restul sunt text in `<span class="prop-val">`. Importul din Task 5 pastreaza doar primul bloc, deci pierde ~80% din continutul editorial indexat de Google.

`products.short_description` ramane — pastreaza primul bloc de tip text si alimenteaza `.prod-desc` din catalog, care afiseaza o descriere scurta, nu tot blocul de proprietati.

- [ ] **Step 1: Scrie migrarea**

Creeaza `Website/db/migration_003_properties.sql`:

```sql
-- Migrare 003 — blocurile de proprietati ale cardurilor de produs.
-- Idempotenta: CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS product_properties (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED     NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    label      VARCHAR(60)      NOT NULL,
    body       TEXT             NOT NULL,
    is_list    TINYINT(1)       NOT NULL DEFAULT 0,
    UNIQUE KEY uq_prod_sort (product_id, sort_order),
    KEY idx_product (product_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Pentru `is_list = 1`, `body` contine cate un element pe linie. Randarea le reface ca `<li>`.

- [ ] **Step 2: Aplica migrarea**

Run: `mysql -u root mugurel_cms < Website/db/migration_003_properties.sql`
Apoi: `mysql -u root mugurel_cms -e "DESCRIBE product_properties;"`
Expected: 6 coloane, in ordinea de mai sus.

- [ ] **Step 3: Extinde importul**

In `Website/db/migrate_products.php`, adauga langa celelalte functii ajutatoare:

```php
/**
 * Extrage blocurile de proprietati ale unui card.
 * Fiecare bloc are o eticheta si fie text (<span class="prop-val">),
 * fie o lista (<ul class="prop-list">).
 */
function parseProperties(string $card): array {
    $out = [];
    if (!preg_match_all('#<div class="material-prop">(.*?)</div>#s', $card, $blocks)) {
        return $out;
    }
    foreach ($blocks[1] as $i => $block) {
        if (!preg_match('#<span class="prop-label">(.*?)</span>#s', $block, $l)) { continue; }
        $label = stripTags2($l[1]);
        if (preg_match('#<ul class="prop-list">(.*?)</ul>#s', $block, $ul)) {
            preg_match_all('#<li>(.*?)</li>#s', $ul[1], $items);
            $body = implode("
", array_map('stripTags2', $items[1]));
            $isList = 1;
        } elseif (preg_match('#<span(?: class="prop-val")?>(.*?)</span>#s',
                             preg_replace('#<span class="prop-label">.*?</span>#s', '', $block), $v)) {
            // Span-ul fara class="prop-val" acopera 4 carduri din apa-canal.html, unde
            // clasa lipseste din HTML-ul sursa. Fara asta raman complet fara continut.
            $body = stripTags2($v[1]);
            $isList = 0;
        } else {
            continue;
        }
        if ($body === '') { continue; }
        $out[] = ['label' => $label, 'body' => $body, 'is_list' => $isList, 'sort' => $i + 1];
    }
    return $out;
}
```

Pregateste instructiunile langa celelalte `prepare()`:

```php
$clearProps = $db->prepare('DELETE FROM product_properties WHERE product_id = ?');
$insertProp = $db->prepare(
    'INSERT INTO product_properties (product_id, sort_order, label, body, is_list)
     VALUES (?, ?, ?, ?, ?)'
);
```

`short_description` devine primul bloc de tip **text**, nu pur si simplu primul bloc — un card care incepe cu `Avantaje` ar pune altfel o lista intreaga in descrierea scurta din catalog. Inlocuieste blocul care calculeaza `$descr`:

```php
$props = parseProperties($card);
$descr = '';
foreach ($props as $pr) {
    if ($pr['is_list'] === 0) { $descr = $pr['body']; break; }
}
```

Dupa `$insert->execute([...])`, unde deja se obtine `$pid` pentru cross-listing, scrie proprietatile. Muta obtinerea lui `$pid` inaintea blocului de cross-listing, ca sa fie folosita de ambele:

```php
$pid = (int)$db->lastInsertId();
$clearProps->execute([$pid]);
foreach ($props as $pr) {
    $insertProp->execute([$pid, $pr['sort'], $pr['label'], $pr['body'], $pr['is_list']]);
}
```

`DELETE` inaintea inserarii pastreaza idempotenta: o rerulare inlocuieste proprietatile, nu le acumuleaza.

Adauga si un contor `$totalProps` incrementat la fiecare proprietate scrisa, raportat la final.

- [ ] **Step 4: Ruleaza importul si verifica numerele**

Run: `php Website/db/migrate_products.php`

Expected: tot `TOTAL: 231 produse`, plus noul contor de proprietati.

Verifica apoi:

```bash
mysql -u root mugurel_cms -e "
  SELECT COUNT(*) AS produse FROM products;
  SELECT COUNT(*) AS proprietati FROM product_properties;
  SELECT COUNT(DISTINCT product_id) AS produse_cu_prop FROM product_properties;
  SELECT label, COUNT(*) FROM product_properties GROUP BY label ORDER BY COUNT(*) DESC;
  SELECT COUNT(*) AS goale FROM products WHERE short_description IS NULL OR short_description = '';"
```

Expected: `produse=231`; `proprietati=464`; `produse_cu_prop=231`; `goale=0`; distributia etichetelor **exact**:

| Eticheta | Randuri |
|---|---|
| Avantaje | 187 |
| Specificatii | 141 |
| Descriere | 44 |
| Aspect | 43 |
| Utilizare | 31 |
| Unde se foloseste | 18 |

**Toate** blocurile intra in tabel, inclusiv `Descriere`. Cele 44 de carduri care au doar
`Descriere` sunt singurele cu un singur bloc; daca le-ai lasa in afara, jumatate din produse
si-ar tine continutul in `short_description` iar cealalta in `product_properties` — doua locuri
pentru acelasi lucru, si un formular de admin care trebuie sa le trateze diferit.

`short_description` ramane, derivat din primul bloc de tip text, fiindca `.prod-desc` din catalog
afiseaza o descriere scurta, nu tot blocul de proprietati.

Distributia etichetelor e verificarea care conteaza — provine dintr-o analiza independenta a HTML-ului. Orice abatere inseamna ca parsarea a ratat blocuri.

- [ ] **Step 5: Verifica idempotenta**

Run: `php Website/db/migrate_products.php` inca o data, apoi reciteste numaratorile.
Expected: identice. Daca numarul de proprietati s-a dublat, `DELETE`-ul nu ruleaza.

- [ ] **Step 6: Verifica fidelitatea unei liste**

```bash
mysql -u root mugurel_cms -e "
  SELECT p.name, pp.label, pp.is_list, pp.body FROM product_properties pp
  JOIN products p ON p.id = pp.product_id
  WHERE pp.is_list = 1 LIMIT 1\G"
```
Compara elementele cu `<li>`-urile cardului corespunzator din HTML. Trebuie sa fie aceleasi, in aceeasi ordine.

- [ ] **Step 7: Commit**

```bash
git add Website/db/migration_003_properties.sql Website/db/migrate_products.php
git commit -m "feat: importa blocurile de proprietati ale produselor"
```


---

### Task 5C: Fotografiile si badge-urile cardurilor

**Files:**
- Create: `Website/db/migration_004_images_badges.sql`
- Modify: `Website/db/migrate_products.php`
- Modify: `Website/includes/product_icons.php` (semnatura lui `renderIcon()`)
- Modify: `Website/includes/product_grid.php` (`renderMaterialCards()`)
- Modify: `Website/admin/tests/test_product_grid.php`

**Interfaces:**
- Consumes: `products` populat de Task 5 si 5B.
- Produces: `products.badge_label`, `products.badge_kind`, plus `image_path`/`image_alt` populate.
  `renderIcon(?string $key, ?string $label, bool $hidden = false): string`.

**De ce exista:** **50 din cele 231 de carduri au o fotografie reala** si **33 au un badge**
(`Cel mai vandut`, `Cel mai popular`, ...). Randarea din baza de date fara ele ar sterge vizibil
continut de pe fiecare pagina de categorie, inclusiv de pe Electrice, pagina pilotului.

Tipurile de badge existente: `material-badge--popular` (14), `material-badge--premium` (14),
fara modificator (3), `material-badge--new` (1), `material-badge--eco` (1).

Structura reala a unui card cu fotografie si badge:

```html
<div class="material-img-wrap">
  <img src="img/cablu-myym.jpg" alt="Cablu MYYM 3x1.5mm" class="material-img"
       onerror="this.closest('.material-img-wrap').style.display='none'">
  <div class="material-img-ph" style="display:none">[SVG]<span>Cablu MYYM</span></div>
  <div class="material-badge material-badge--popular">Cel mai vandut</div>
</div>
```

Cand nu exista fotografie, placeholder-ul are `style="display:flex"` si nu exista `<img>`.
Atributul `onerror` ascunde tot wrap-ul daca fisierul lipseste — pastreaza-l identic.

- [ ] **Step 1: Scrie migrarea**

Creeaza `Website/db/migration_004_images_badges.sql`:

```sql
-- Migrare 004 — badge-urile cardurilor de produs.
-- image_path si image_alt exista deja din schema initiala; aici doar badge-ul.
-- Idempotenta prin acelasi tipar ca migrarea 002 (guard pe information_schema).

DROP PROCEDURE IF EXISTS mig004_add_column;
DELIMITER //
CREATE PROCEDURE mig004_add_column(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col) THEN
        SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
        PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;
END//
DELIMITER ;

CALL mig004_add_column('products', 'badge_label', 'badge_label VARCHAR(60) NULL DEFAULT NULL AFTER icon_label');
CALL mig004_add_column('products', 'badge_kind',  'badge_kind VARCHAR(30) NULL DEFAULT NULL AFTER badge_label');

DROP PROCEDURE IF EXISTS mig004_add_column;
```

`badge_kind` retine modificatorul fara prefix (`popular`, `premium`, `new`, `eco`) sau `NULL`
pentru badge-ul fara modificator. `badge_label` e textul afisat.

- [ ] **Step 2: Aplica migrarea**

Run: `mysql -u root mugurel_cms < Website/db/migration_004_images_badges.sql`
Apoi: `mysql -u root mugurel_cms -e "SHOW COLUMNS FROM products LIKE 'badge%'; SHOW PROCEDURE STATUS WHERE Db='mugurel_cms';"`
Expected: doua coloane, zero proceduri ramase.

- [ ] **Step 3: Extinde importul**

In `Website/db/migrate_products.php`, langa celelalte functii ajutatoare:

```php
/** Fotografia reala a unui card, daca exista. */
function parseImage(string $card): array {
    if (preg_match('#<img[^>]*class="material-img"[^>]*>#s', $card, $m)) {
        $tag = $m[0];
        $src = preg_match('#\ssrc="([^"]*)"#', $tag, $s) ? $s[1] : null;
        $alt = preg_match('#\salt="([^"]*)"#', $tag, $a) ? stripTags2($a[1]) : null;
        if ($src !== null && $src !== '') { return [$src, $alt]; }
    }
    return [null, null];
}

/** Badge-ul unui card: textul si tipul (fara prefixul material-badge--). */
function parseBadge(string $card): array {
    if (!preg_match('#<div class="material-badge([^"]*)"[^>]*>(.*?)</div>#s', $card, $m)) {
        return [null, null];
    }
    $label = stripTags2($m[2]);
    if ($label === '') { return [null, null]; }
    $kind = null;
    if (preg_match('#material-badge--([a-z0-9-]+)#', $m[1], $k)) { $kind = $k[1]; }
    return [$label, $kind];
}
```

Adauga cele patru coloane in `INSERT`-ul existent si in clauza `ON DUPLICATE KEY UPDATE`
(`image_path`, `image_alt`, `badge_label`, `badge_kind`), si calculeaza-le in bucla:

```php
[$imgPath, $imgAlt] = parseImage($card);
[$badgeLabel, $badgeKind] = parseBadge($card);
```

Adauga contoare `$withImg` si `$withBadge`, raportate la final.

- [ ] **Step 4: Ruleaza importul si verifica**

Run: `php Website/db/migrate_products.php`
Expected: `TOTAL: 231 produse`, plus `50 cu fotografie` si `33 cu badge`.

```bash
mysql -u root mugurel_cms -e "
  SELECT COUNT(*) AS cu_poza FROM products WHERE image_path IS NOT NULL AND image_path <> '';
  SELECT COUNT(*) AS cu_badge FROM products WHERE badge_label IS NOT NULL;
  SELECT badge_kind, COUNT(*) FROM products WHERE badge_label IS NOT NULL GROUP BY badge_kind ORDER BY COUNT(*) DESC;
  SELECT COUNT(*) AS props FROM product_properties;"
```

Expected: `cu_poza=50`, `cu_badge=33`, distributia `popular 14, premium 14, NULL 3, new 1, eco 1`,
`props=464` (neschimbat).

- [ ] **Step 5: Randarea — `renderIcon()` capata un parametru**

In `Website/includes/product_icons.php`, schimba semnatura si stilul inline:

```php
function renderIcon(?string $key, ?string $label, bool $hidden = false): string {
    $svg = ($key !== null && isset(PRODUCT_ICONS[$key])) ? PRODUCT_ICONS[$key] : PRODUCT_ICON_FALLBACK;
    $style = $hidden ? 'display:none' : 'display:flex';
    return '<div class="material-img-ph" style="' . $style . '">' . $svg
         . '<span>' . htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8') . '</span></div>';
}
```

`renderIcon()` e generata de `Website/db/extract_icons.php` — modifica **si** generatorul, altfel
urmatoarea rulare a lui rescrie fisierul si pierde schimbarea.

- [ ] **Step 6: Randarea — wrap-ul complet**

In `Website/includes/product_grid.php`, inlocuieste constructia lui `.material-img-wrap` din
`renderMaterialCards()` cu:

```php
/** Continutul lui .material-img-wrap: fotografie (daca exista), placeholder, badge. */
function renderImgWrap(array $p): string {
    $hasImg = !empty($p['image_path']);
    $out = '<div class="material-img-wrap">';
    if ($hasImg) {
        $out .= '<img src="' . e($p['image_path']) . '" alt="' . e($p['image_alt'] ?? $p['name'])
              . '" class="material-img"'
              . ' onerror="this.closest(\'.material-img-wrap\').style.display=\'none\'">';
    }
    $out .= renderIcon($p['icon_key'], $p['icon_label'], $hasImg);
    if (!empty($p['badge_label'])) {
        $cls = 'material-badge' . (!empty($p['badge_kind']) ? ' material-badge--' . e($p['badge_kind']) : '');
        $out .= '<div class="' . $cls . '">' . e($p['badge_label']) . '</div>';
    }
    return $out . '</div>';
}
```

- [ ] **Step 7: Repository-ul trebuie sa returneze coloanele noi**

In `Website/includes/products_repo.php`, adauga in `productsBaseSql()` la lista de coloane:
`p.image_path, p.image_alt, p.badge_label, p.badge_kind`. Fara asta, randarea nu are ce afisa.

- [ ] **Step 8: Teste**

In `Website/admin/tests/test_product_grid.php`, adauga asertiuni pe un produs cu fotografie si badge:

```php
$cuPoza = $sample;
$cuPoza[0]['image_path'] = 'img/cablu-myym.jpg';
$cuPoza[0]['image_alt']  = 'Cablu MYYM';
$cuPoza[0]['badge_label'] = 'Cel mai vandut';
$cuPoza[0]['badge_kind']  = 'popular';
$h = renderMaterialCards($cuPoza);
ok(str_contains($h, 'class="material-img"'),                 'randeaza <img> cand exista fotografie');
ok(str_contains($h, 'style="display:none"'),                 'placeholder-ul e ascuns cand exista fotografie');
ok(str_contains($h, 'material-badge material-badge--popular'), 'badge cu modificator');
ok(str_contains($h, 'Cel mai vandut'),                       'textul badge-ului');
ok(str_contains($h, 'onerror='),                             'pastreaza fallback-ul onerror');

$fara = $sample;
$fara[0]['image_path'] = null; $fara[0]['badge_label'] = null;
$h2 = renderMaterialCards($fara);
ok(!str_contains($h2, '<img'),                    'fara fotografie => niciun <img>');
ok(str_contains($h2, 'style="display:flex"'),     'placeholder vizibil cand nu exista fotografie');
ok(!str_contains($h2, 'material-badge'),          'fara badge => niciun element de badge');
```

Adauga si cazul badge fara modificator (`badge_kind = null`): clasa trebuie sa fie exact
`class="material-badge"`, fara spatiu in coada.

Run: `php Website/admin/tests/test_product_grid.php` si `php Website/admin/tests/test_products_repo.php`
Expected: toate trec. Raporteaza numerele.

- [ ] **Step 9: Compara cu HTML-ul real**

Ia produsul `Cablu MYYM 3x1.5mm² (rola 100m)` din baza, randeaza-l, si compara cu cardul lui din
`Website/electrice.html` (in jurul liniei 217). Structura `.material-img-wrap` trebuie sa coincida:
`<img>`, apoi placeholder ascuns, apoi badge. Ignora spatierea alba.

- [ ] **Step 10: Commit**

```bash
git add Website/db/migration_004_images_badges.sql Website/db/migrate_products.php
git commit -m "feat: importa fotografiile si badge-urile cardurilor"
cd Website && git add includes/ admin/tests/ && \
  git commit -m "feat: randeaza fotografia si badge-ul produsului" && cd ..
git add Website && git commit -m "chore: actualizeaza pointer Website submodul"
```

---

### Task 5D: Accesoriile

**Files:**
- Create: `Website/db/migration_005_accessories.sql`
- Modify: `Website/db/migrate_products.php`
- Modify: `Website/includes/products_repo.php`
- Modify: `Website/includes/product_grid.php`
- Modify: `Website/admin/tests/test_product_grid.php`, `test_products_repo.php`

**Interfaces:**
- Produces: `products.card_kind` (`material` | `accessory`), `products.card_modifier`.
  `renderAccessoryCards(array $products): string`.
  Repository-ul filtreaza implicit pe `card_kind`.

**De ce exista:** pe langa cele 231 de carduri `material-card`, paginile contin **264 de
`accessory-card`** — tot produse, cu nume, descriere si buton WhatsApp, in format mai simplu.
Inventarul real al site-ului e **495 de produse**. Fara acest task, adminul ar gestiona doar
jumatate, iar catalogul ar omite cealalta jumatate.

Toate cele 264 au nume unic. 25 au fotografie, 65 folosesc varianta `accessory-card--sm`.
Grilele sunt `accessories-grid` (42) si `accessories-grid accessories-grid--5` (11) — varianta
grilei e o proprietate a sectiunii, nu a produsului, deci ramane in HTML-ul paginii.

Structura:

```html
<div class="accessory-card">
  <div class="accessory-body">
    <h4>Tablou electric metalic/plastic 4-8 module</h4>
    <p>Tablou de distributie pentru locuinte, cu sina DIN integrata...</p>
    <a href="https://wa.me/40749130565?text=..." class="btn-wa-acc" target="_blank" rel="noopener">Afla disponibilitate</a>
  </div>
</div>
```

- [ ] **Step 1: Migrarea**

Creeaza `Website/db/migration_005_accessories.sql`, cu acelasi tipar de procedura ghidata ca migrarea 004:

```sql
CALL mig005_add_column('products', 'card_kind',
    "card_kind ENUM('material','accessory') NOT NULL DEFAULT 'material' AFTER page_slug");
CALL mig005_add_column('products', 'card_modifier',
    'card_modifier VARCHAR(20) NULL DEFAULT NULL AFTER card_kind');
```

`card_modifier` retine `sm` pentru `accessory-card--sm`, altfel `NULL`.

Cheia unica existenta `uq_page_section_name (page_slug, section_key, name)` ramane valida:
numele accesoriilor sunt unice si nu se ciocnesc cu cele ale materialelor.

- [ ] **Step 2: Extinde importul**

Parseaza si `accessory-card` in aceeasi bucla pe sectiuni, scriind in acelasi tabel `products`
cu `card_kind = 'accessory'`. Numele vine din `<h4>`, descrierea din `<p>`, fotografia din
`<img class="accessory-img">` daca exista. Accesoriile **nu au** blocuri de proprietati, pret,
iconita sau badge — lasa coloanele respective `NULL`, iar `short_description` primeste textul din `<p>`.

`sort_order` se numara separat pentru accesorii in cadrul aceleiasi sectiuni, ca sa nu se amestece
cu ordinea materialelor.

Raporteaza la final `TOTAL: 231 materiale + 264 accesorii = 495 produse`.

- [ ] **Step 3: Verifica importul**

```bash
mysql -u root mugurel_cms -e "
  SELECT card_kind, COUNT(*) FROM products GROUP BY card_kind;
  SELECT COUNT(*) AS sm FROM products WHERE card_modifier = 'sm';
  SELECT COUNT(*) AS acc_cu_poza FROM products WHERE card_kind='accessory' AND image_path IS NOT NULL;
  SELECT COUNT(*) AS props FROM product_properties;"
```

Expected: `material=231`, `accessory=264`, `sm=65`, `acc_cu_poza=25`, `props=464` (neschimbat —
accesoriile nu au proprietati).

Verifica si idempotenta: a doua rulare pastreaza 495.

- [ ] **Step 4: Repository-ul filtreaza pe tip**

Adauga `card_kind` si `card_modifier` la coloanele selectate in `productsBaseSql()`, si un
parametru `?string $kind = null` la `productsForSection()` si `productsForPage()`, care adauga
`AND p.card_kind = ?` cand e dat. Paginile cer explicit `'material'` sau `'accessory'` pentru
fiecare grila; `allProducts()` le intoarce pe toate.

Adauga teste: `productsForSection('electrice', '<sectiune>', 'accessory')` intoarce doar accesorii.

- [ ] **Step 5: Randarea accesoriilor**

In `Website/includes/product_grid.php`:

```php
/** Varianta accesoriilor. Card simplu: titlu, descriere, buton WhatsApp. */
function renderAccessoryCards(array $products): string {
    $out = '';
    foreach ($products as $p) {
        $cls = 'accessory-card' . (!empty($p['card_modifier'])
             ? ' accessory-card--' . e($p['card_modifier']) : '');
        $out .= '<div class="' . $cls . '">';
        if (!empty($p['image_path'])) {
            $out .= '<div class="accessory-img-wrap"><img src="' . e($p['image_path'])
                  . '" alt="' . e($p['image_alt'] ?? $p['name']) . '" class="accessory-img"></div>';
        }
        $out .= '<div class="accessory-body">'
              . '<h4>' . e($p['name']) . '</h4>'
              . '<p>' . e($p['short_description']) . '</p>'
              . '<a href="' . e(waLink($p['name'])) . '" class="btn-wa-acc" target="_blank" rel="noopener">'
              . 'Afla disponibilitate</a>'
              . '</div></div>';
    }
    return $out;
}
```

Compara output-ul cu un `accessory-card` real din `Website/electrice.html` inainte de a merge mai
departe. Verifica si varianta `--sm` si pe cea cu fotografie.

- [ ] **Step 6: Teste si commit**

Adauga teste pentru `renderAccessoryCards()`: varianta simpla, varianta `--sm`, cea cu fotografie,
escaping, si lista goala. Ruleaza ambele suite de teste si raporteaza numerele.

```bash
git add Website/db/migration_005_accessories.sql Website/db/migrate_products.php
git commit -m "feat: importa accesoriile ca produse"
cd Website && git add includes/ admin/tests/ && git commit -m "feat: randare carduri de accesorii" && cd ..
git add Website && git commit -m "chore: actualizeaza pointer Website submodul"
```


---

### Task 5E: Mesajele WhatsApp scrise de mana

**Files:**
- Create: `Website/db/migration_006_wa_text.sql`
- Modify: `Website/db/migrate_products.php`, `Website/includes/products_repo.php`,
  `Website/includes/product_grid.php`, `Website/admin/tests/test_product_grid.php`

**De ce exista:** **310 din cele 495 de produse** au in `href`-ul butonului WhatsApp un mesaj
scris de mana, diferit de sablonul generic — 54 de materiale si 256 din cele 264 de accesorii.
Exemplu: `Buna ziua! Sunt interesat de jgheaburi semicirculare 125mm.` in loc de
`Buna ziua! Sunt interesat de: Jgheab Semicircular 125mm. Puteti confirma disponibilitatea?`.

Nu e o diferenta vizuala, dar schimba mesajul pe care il primeste magazinul. Randarea din baza
cu `waLink()` le-ar inlocui pe toate cu sablonul.

- [ ] **Step 1: Migrarea**

`Website/db/migration_006_wa_text.sql`, cu acelasi tipar de procedura ghidata ca migrarile 004 si 005:

```sql
CALL mig006_add_column('products', 'wa_text',
    'wa_text VARCHAR(400) NULL DEFAULT NULL AFTER short_description');
```

`NULL` inseamna „foloseste sablonul" — asa produsele adaugate din admin capata automat mesajul
generic, fara ca cineva sa trebuiasca sa-l scrie.

- [ ] **Step 2: Importul extrage mesajul**

Din `href`-ul butonului (`btn-wa-material` sau `btn-wa-acc`), decodeaza partea de dupa `?text=`
cu `urldecode()`. Compara cu sablonul generat pentru acel produs; daca e **identic**, scrie `NULL`
(nu stoca redundant). Daca difera, stocheaza textul.

- [ ] **Step 3: Randarea il foloseste cand exista**

In `product_grid.php`, `waLink()` capata un al doilea parametru:

```php
function waLink(string $productName, ?string $custom = null): string {
    $msg = ($custom !== null && $custom !== '')
         ? $custom
         : 'Buna ziua! Sunt interesat de: ' . $productName . '. Puteti confirma disponibilitatea?';
    return 'https://wa.me/' . WA_PHONE . '?text=' . rawurlencode($msg);
}
```

Apelantii paseaza `$p['wa_text']`. Adauga `p.wa_text` la coloanele din `productsBaseSql()`.

Pentru `.prod-card` (catalog), butonul foloseste `data-wa-product` si JS-ul construieste mesajul.
Daca produsul are `wa_text`, adauga un atribut `data-wa-text` cu mesajul, si modifica
`js/main.js` sa-l prefere cand exista.

- [ ] **Step 4: Verificari**

```bash
mysql -u root mugurel_cms -e "
  SELECT COUNT(*) AS cu_text_propriu FROM products WHERE wa_text IS NOT NULL;
  SELECT card_kind, COUNT(*) FROM products WHERE wa_text IS NOT NULL GROUP BY card_kind;"
```
Expected: **310** total, din care `material 54` si `accessory 256`.

Compara `href`-ul randat cu cel din HTML pentru 3-4 produse cu mesaj propriu si pentru unul fara —
trebuie sa fie identice dupa decodare. Numerele existente raman: 495 produse, 464 proprietati,
86 iconite. Ambele suite de teste trec, plus asertiuni noi pentru mesaj propriu si pentru fallback.

- [ ] **Step 5: Commit** — trei commit-uri, ca la 5C si 5D.


---

### Task 6: Pilot pe Electrice

**Files:**
- Create: `Website/includes/page_bootstrap.php`
- Create: `Website/electrice.php` (din `Website/electrice.html`)
- Delete: `Website/electrice.html` (dupa validare, la Step 7)
- Modify: `Website/.htaccess:17` (inaintea regulii de passthrough)
- Modify: `Website/.cpanel.yml`

**Interfaces:**
- Consumes: `productsForSection()` (Task 3), `renderMaterialCards()` / `renderEmptyNotice()` (Task 4).
- Produces: tiparul pe care Task 7 il replica pe restul de 12 pagini.

- [ ] **Step 1: Copiaza pagina si adauga include-urile**

```bash
cp Website/electrice.html Website/electrice.php
```

Creeaza intai `Website/includes/page_bootstrap.php` — prologul comun al tuturor celor 13 pagini.
Fara el, `section()` ar fi copiata identic in 13 fisiere.

```php
<?php
/**
 * Prologul paginilor de categorie. Leaga repository-ul de randare, ca paginile
 * sa contina un singur apel per grila.
 *
 * Sta separat de product_grid.php ca acela sa ramana pur de randare, fara SQL.
 */
require_once __DIR__ . '/products_repo.php';
require_once __DIR__ . '/product_grid.php';

/** Randeaza o sectiune, sau mesajul de indisponibilitate daca lista e goala. */
function section(string $page, string $key): string {
    $rows = productsForSection($page, $key);
    return $rows ? renderMaterialCards($rows) : renderEmptyNotice();
}
```

Apoi adauga la **inceputul absolut** al lui `Website/electrice.php`, inaintea lui `<!DOCTYPE html>`:

```php
<?php require_once __DIR__ . '/includes/page_bootstrap.php'; ?>
```

- [ ] **Step 2: Inlocuieste continutul grilelor**

O pagina de categorie are **doua feluri de grile**, si ambele trebuie inlocuite:
`<div class="materials-grid">` (cardurile de material) si `<div class="accessories-grid">`
(accesoriile, uneori cu varianta `accessories-grid--5`). `electrice.html` are 4 grile de
materiale si 1 de accesorii, cu 23 de materiale si 17 accesorii.

`section()` din `page_bootstrap.php` trebuie sa accepte tipul cardului:

```php
function section(string $page, string $key, string $kind = 'material'): string {
    $rows = productsForSection($page, $key, $kind);
    if (!$rows) { return renderEmptyNotice(); }
    return $kind === 'accessory' ? renderAccessoryCards($rows) : renderMaterialCards($rows);
}
```

Grilele de accesorii se completeaza cu `<?= section('electrice', '<cheie>', 'accessory') ?>`.

Varianta grilei (`accessories-grid--5`) e o proprietate a sectiunii, nu a produselor — ramane
in HTML-ul paginii, neatinsa.

Ruleaza intai ca sa afli cheile exacte de sectiune:

```bash
mysql -u <user> -p mugurel_cms -e "
  SELECT section_key, COUNT(*) AS n FROM products
  WHERE page_slug='electrice' GROUP BY section_key ORDER BY MIN(id);"
```

Pentru fiecare grila din `electrice.php` — de materiale si de accesorii — sterge cardurile dintre tagul de deschidere si `</div>`-ul lui de inchidere si pune in locul lor un singur rand. Exemplu pentru prima sectiune:

```php
      <div class="materials-grid">
<?= section('electrice', 'cabluri-conductori') ?>
      </div>
```

**Nu atinge** `<section>`, `<h2 class="cat-section-title">`, `<p class="cat-section-desc">`, ancorele `id="cabluri"` si nici restul paginii. Se schimba exclusiv continutul dintre cele doua taguri ale grilei.

- [ ] **Step 3: Verifica sintaxa si numarul de carduri**

```bash
php -l Website/electrice.php
php Website/electrice.php | grep -o 'class="material-card"' | wc -l      # 23
php Website/electrice.php | grep -o 'class="accessory-card' | wc -l      # 17
```
Expected: `No syntax errors`, apoi `23` si `17`. Numara aparitiile (`grep -o | wc -l`), nu liniile —
`grep -c` numara linii si da rezultate gresite cand mai multe carduri stau pe acelasi rand.

- [ ] **Step 4: Compara randarea cu pagina originala**

```bash
php Website/electrice.php > /tmp/nou.html
python - <<'EOF'
import re,io
def norm(p):
    h=io.open(p,encoding='utf-8',errors='replace').read()
    h=re.sub(r'<div class="materials-grid">.*?</div>\s*</div>\s*</section>','[GRILA]',h,flags=re.S)
    return re.sub(r'\s+',' ',h)
a,b=norm('Website/electrice.html'),norm('/tmp/nou.html')
print('Invelisul editorial e identic' if a==b else 'DIFERENTA in afara grilelor — investigheaza')
EOF
```

Expected: `Invelisul editorial e identic`. Daca apare diferenta, o parte din invelis a fost atinsa din greseala — repara inainte de a continua.

- [ ] **Step 5: Adauga regula de rewrite**

In `Website/.htaccess`, imediat dupa `RewriteRule ^admin/ - [L]` si **inaintea** blocului `RewriteCond %{REQUEST_FILENAME} -f`:

```apache
    # /slug.html serveste slug.php cand acesta exista — URL-urile publice raman .html
    RewriteCond %{REQUEST_FILENAME} ^(.+)\.html$
    RewriteCond %1.php -f
    RewriteRule ^(.+)\.html$ $1.php [L,QSA]
```

`%1` e captura din conditia precedenta, adica **calea completa pe disc** fara extensie. Varianta
cu `%{DOCUMENT_ROOT}/$1.php` ar functiona doar cand site-ul sta in radacina domeniului; aceasta
merge si cand e servit dintr-un subfolder, cum se intampla pe mediul local de dezvoltare.

Ordinea conteaza: pusa dupa passthrough, regula nu s-ar aplica niciodata, fiindca fisierul `.html` exista.

- [ ] **Step 6: Curata `.html`-urile fara pereche la deploy**

In `Website/.cpanel.yml`, adauga ca **ultima** linie din `tasks`:

```yaml
    - cd $DEPLOYPATH && for f in *.php; do [ -f "${f%.php}.html" ] && rm -f "${f%.php}.html"; done; true
```

- [ ] **Step 7: Sterge originalul si commit**

```bash
git rm Website/electrice.html
git add Website/includes/page_bootstrap.php Website/electrice.php Website/.htaccess Website/.cpanel.yml
git commit -m "feat: pagina Electrice serveste produsele din baza de date"
```

- [ ] **Step 8: Deploy si validare live**

Push-ul si deploy-ul **se cer utilizatorului** — nu le executa singur.

Dupa `git push` aprobat, in cPanel: **Git Version Control → Deploy HEAD Commit**. Apoi Cloudflare → **Purge Everything** (`js/` si `css/` sunt cache-uite 4 ore).

Criterii de acceptanta, toate obligatorii:

```bash
curl -s https://mugurel-bricolaj.ro/electrice.html -o /tmp/live.html -w "%{http_code}\n"
grep -c 'class="material-card"'      /tmp/live.html   # 23
grep -c 'application/ld+json'        /tmp/live.html   # 1
grep -c 'Materiale Electrice in Dabuleni' /tmp/live.html   # >= 1
grep -c 'id="cabluri"'               /tmp/live.html   # 1
grep -c 'Întrebări frecvente'        /tmp/live.html   # 1
curl -s -o /dev/null -w "%{http_code}\n" https://mugurel-bricolaj.ro/electrice.html  # 200, nu 301
```

Si o verificare pe care e usor sa o sari: **opreste temporar accesul la DB** (parola gresita in `admin/config.local.php`), reincarca `/electrice.html` si confirma ca pagina se randeaza in continuare cu H1, JSON-LD si FAQ, cu mesajul WhatsApp in locul grilelor. Repune apoi parola.

**Nu incepe Task 7 pana cand toate criteriile de mai sus nu trec.**

---

### Task 7: Replicare pe restul de 12 pagini

**Files:**
- Create: `Website/{acoperis,izolatie,gips-carton,zidarie-bca,gard-imprejmuiri,incalzire,sanitare,apa-canal,gradina,mobilier,electrocasnice,scule-unelte}.php`
- Delete: fisierele `.html` corespunzatoare

**Interfaces:**
- Consumes: tiparul validat in Task 6. Nimic nou.

Distributia pe sectiuni, ca referinta pentru verificare:

| Pagina | Produse | Sectiuni |
|---|---|---|
| acoperis | 22 materiale + 25 accesorii | 5 |
| izolatie | 10 | 4 |
| gips-carton | 14 | 4 |
| zidarie-bca | 17 | 5 |
| gard-imprejmuiri | 13 | 3 |
| incalzire | 20 | 4 |
| sanitare | 20 | 4 |
| apa-canal | 22 | 5 |
| gradina | 18 | 4 |
| mobilier | 15 | 4 |
| electrocasnice | 17 | 4 |
| scule-unelte | 20 | 4 |

- [ ] **Step 1: Converteste o pagina**

Pentru fiecare pagina din tabel, aplica exact pasii 1-4 din Task 6: `cp` la `.php`, prologul de o linie care include `page_bootstrap.php` (creat deja in Task 6), inlocuirea continutului fiecarei grile, apoi verificarea de sintaxa, numaratoarea de carduri si diff-ul de inveliş.

Cheile de sectiune se citesc din DB, nu se ghicesc:

```bash
mysql -u <user> -p mugurel_cms -e "
  SELECT section_key, COUNT(*) AS n FROM products
  WHERE page_slug='<pagina>' GROUP BY section_key ORDER BY MIN(id);"
```

- [ ] **Step 2: Verifica pagina convertita**

Run: `php -l Website/<pagina>.php && php Website/<pagina>.php | grep -c 'class="material-card"'`
Expected: `No syntax errors` si exact numarul din tabel.

Ruleaza si diff-ul de inveliş din Task 6 Step 4, cu numele paginii curente.

- [ ] **Step 3: Commit pagina**

```bash
git rm Website/<pagina>.html
git add Website/<pagina>.php
git commit -m "feat: pagina <Pagina> serveste produsele din baza de date"
```

Cate un commit per pagina: daca una iese prost, se revine punctual fara sa cada celelalte 11.

- [ ] **Step 4: Verificare finala pe toate paginile**

```bash
total=0
for p in acoperis izolatie gips-carton zidarie-bca gard-imprejmuiri electrice incalzire \
         sanitare apa-canal gradina mobilier electrocasnice scule-unelte; do
  n=$(php Website/$p.php | grep -c 'class="material-card"')
  printf "%-18s %s\n" "$p" "$n"; total=$((total+n))
done
echo "TOTAL: $total"
```
Expected: `TOTAL: 231`, cu fiecare pagina la valoarea din tabel.

---

### Task 8A: Interogarea catalogului si helperii de randare

**Files:**
- Modify: `Website/includes/products_repo.php`
- Modify: `Website/includes/product_icons.php` si `Website/db/extract_icons.php` (generatorul)
- Modify: `Website/includes/product_grid.php`
- Modify: `Website/css/style.css`
- Modify: `Website/admin/tests/test_products_repo.php`, `test_product_grid.php`

**Interfaces:**
- Produces: `catalogProducts(?string $cat, ?string $q, int $limit, int $offset): array`,
  `catalogCount(?string $cat, ?string $q): int`, `renderProdIcon(?string $key, ?string $label): string`,
  si maparea categorie → modificator de badge.

**De ce exista:** catalogul trece de la 84 de produse hardcodate la **495 din baza de date**.
Filtrarea si cautarea sunt azi in JavaScript, peste cardurile randate in pagina. Cu paginare la
60 de produse, ar opera doar pe pagina curenta — cautarea ar returna tacit rezultate gresite.
Deci amandoua trec pe server.

- [ ] **Step 1: Interogarea de catalog**

In `Website/includes/products_repo.php`, adauga o clauza comuna si doua functii. `$cat` accepta
**si** un slug de nivel 1 (caz in care include subcategoriile lui), **si** unul de nivel 2.
`$q` cauta in nume si descriere.

```php
/**
 * Conditia comuna pentru catalog: filtrare pe categorie (nivel 1 sau 2) si cautare text.
 * Returneaza [sqlFragment, params].
 */
function catalogWhere(?string $cat, ?string $q): array {
    $sql = ''; $params = [];
    if ($cat !== null && $cat !== '' && $cat !== 'all') {
        // Produsul se potriveste daca e in categoria ceruta, intr-o subcategorie a ei,
        // sau e cross-listat acolo.
        $sql .= ' AND (c.slug = ? OR l1.slug = ? OR EXISTS (
                    SELECT 1 FROM product_categories pc2
                    JOIN categories xc ON xc.id = pc2.category_id
                    LEFT JOIN categories xl1 ON xl1.id = xc.parent_id
                    WHERE pc2.product_id = p.id AND (xc.slug = ? OR xl1.slug = ?)))';
        $params = array_merge($params, [$cat, $cat, $cat, $cat]);
    }
    if ($q !== null && trim($q) !== '') {
        $like = '%' . trim($q) . '%';
        $sql .= ' AND (p.name LIKE ? OR p.short_description LIKE ?)';
        $params[] = $like; $params[] = $like;
    }
    return [$sql, $params];
}

function catalogProducts(?string $cat, ?string $q, int $limit, int $offset): array {
    try {
        [$where, $params] = catalogWhere($cat, $q);
        $sql = productsBaseSql() . $where . ' ORDER BY p.page_slug, p.sort_order, p.id'
             . ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
        $stmt = Database::get()->prepare($sql);
        $stmt->execute($params);
        return attachProperties($stmt->fetchAll());
    } catch (Throwable $e) {
        error_log('catalogProducts: ' . $e->getMessage());
        return [];
    }
}

function catalogCount(?string $cat, ?string $q): int {
    try {
        [$where, $params] = catalogWhere($cat, $q);
        $sql = 'SELECT COUNT(*) FROM products p
                JOIN categories c ON c.id = p.category_id
                LEFT JOIN categories l1 ON l1.id = c.parent_id
                WHERE p.is_visible = 1' . $where;
        $stmt = Database::get()->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('catalogCount: ' . $e->getMessage());
        return 0;
    }
}
```

`limit` si `offset` sunt fortate la int prin `max()`, deci nu sunt injectabile. Apelantul le
trece prin `(int)` inainte, ca un `?page=abc` sa nu produca `TypeError` si un 500.

- [ ] **Step 2: Teste pentru interogare**

In `test_products_repo.php`:

```php
ok(catalogCount(null, null) === 495, 'catalogCount fara filtru => 495 (got ' . catalogCount(null, null) . ')');
ok(count(catalogProducts(null, null, 60, 0)) === 60, 'prima pagina => 60 de produse');
ok(catalogProducts(null, null, 60, 60)[0]['id'] !== catalogProducts(null, null, 60, 0)[0]['id'], 'offset schimba rezultatul');

// Categorie de nivel 1 include subcategoriile ei.
$el = catalogCount('electrice', null);
ok($el >= 23, "categoria electrice => cel putin 23 (got $el)");
$cab = catalogCount('cabluri', null);
ok($cab > 0 && $cab <= $el, "subcategoria cabluri <= categoria parinte (got $cab din $el)");

// Cautarea gaseste dupa nume si dupa descriere.
ok(catalogCount(null, 'cablu') > 0, 'cautarea dupa "cablu" gaseste rezultate');
ok(catalogCount(null, 'zzzznuexista') === 0, 'cautare fara rezultate => 0');

// Filtru + cautare se combina.
ok(catalogCount('electrice', 'cablu') <= catalogCount(null, 'cablu'), 'filtru + cautare se restrang reciproc');

// Categorie inexistenta.
ok(catalogCount('nu-exista', null) === 0, 'categorie inexistenta => 0');
```

Run: `php Website/admin/tests/test_products_repo.php`. Raporteaza numarul total.

- [ ] **Step 3: Iconita pentru cardul de catalog**

Catalogul foloseste `.prod-img-ph`, nu `.material-img-ph`. CSS-ul existent
(`css/style.css:149-151`) suporta si un `<span>` cu eticheta.

In `Website/includes/product_icons.php` **si in generatorul `Website/db/extract_icons.php`**:

```php
/** Placeholder-ul cardului de catalog. Aceeasi biblioteca de iconite, alt container. */
function renderProdIcon(?string $key, ?string $label): string {
    $svg = ($key !== null && isset(PRODUCT_ICONS[$key])) ? PRODUCT_ICONS[$key] : PRODUCT_ICON_FALLBACK;
    $out = '<div class="prod-img-ph">' . $svg;
    if ($label !== null && $label !== '') {
        $out .= '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    }
    return $out . '</div>';
}
```

Modifica generatorul, apoi regenereaza si confirma cu `git status` ca fisierul generat e in
sincron (altfel modificarea se pierde la urmatoarea rulare).

- [ ] **Step 4: Badge consecvent pe categorie**

Azi modificatorii de culoare sunt aplicati inconsecvent: acelasi „Incalzire" apare rosu pe un
card si verde pe altul. Se uniformizeaza — fiecare categorie de nivel 1 isi primeste culoarea.

In `Website/includes/product_grid.php`:

```php
/** Modificatorul de badge al unei categorii de nivel 1. Null = culoarea implicita. */
const BADGE_KIND = [
    'electrice'      => 'electric',
    'incalzire'      => 'incalzire',
    'sanitare'       => 'sanitare',
    'scule-unelte'   => 'scule',
    'constructii'    => 'constructii',
    'apa-canal'      => 'apa-canal',
    'gradina'        => 'gradina',
    'mobilier'       => 'mobilier',
    'electrocasnice' => 'electrocasnice',
];

/** Prima categorie de nivel 1 a produsului determina culoarea badge-ului. */
function badgeClass(array $p): string {
    $first = explode(' ', trim((string)$p['cat_slugs']))[0] ?? '';
    $kind  = BADGE_KIND[$first] ?? null;
    return 'prod-badge' . ($kind !== null ? ' prod-badge--' . $kind : '');
}
```

`renderProdCards()` foloseste `badgeClass($p)` in locul clasei fixe `prod-badge`.

- [ ] **Step 5: Culorile noi in CSS**

Patru clase exista deja (`css/style.css:399-402`). Adauga langa ele cinci noi, alese sa se
distinga intre ele si de cele existente:

```css
.prod-badge--constructii { background: #6b7280; }
.prod-badge--apa-canal { background: #0f8b8d; }
.prod-badge--gradina { background: #4a7c2f; }
.prod-badge--mobilier { background: #7a5c8a; }
.prod-badge--electrocasnice { background: #455a64; }
```

Respecta stilul fisierului: o regula pe o singura linie, ca cele din jur.

- [ ] **Step 6: Stilul paginarii**

Nu exista inca. Adauga, in acelasi stil compact:

```css
.pagination { display: flex; justify-content: center; align-items: center; gap: 6px; flex-wrap: wrap; margin: 28px 0 8px; }
.pagination a, .pagination span { display: inline-block; min-width: 34px; padding: 7px 10px; text-align: center; font-size: 14px; border: 1px solid #d7e0e2; border-radius: 3px; color: #0a5560; text-decoration: none; }
.pagination a:hover { background: #f0f4f4; }
.pagination .current { background: #0a5560; border-color: #0a5560; color: #fff; font-weight: 700; }
.pagination .gap { border: none; color: #8aa0a4; min-width: auto; padding: 7px 2px; }
```

- [ ] **Step 7: Teste pentru randare**

In `test_product_grid.php`, adauga asertiuni pentru:
- `renderProdIcon()` produce `.prod-img-ph`, include `<span>` doar cand exista eticheta,
  si cade pe iconita generica la cheie necunoscuta
- `badgeClass()` intoarce clasa corecta pentru fiecare dintre cele 9 categorii, si doar
  `prod-badge` pentru un slug necunoscut
- un produs cross-listat (`cat_slugs = "incalzire apa-canal"`) primeste culoarea **primei**
  categorii
- `renderProdCards()` foloseste `.prod-img-ph`, nu `.material-img-ph`

Run ambele suite si raporteaza numerele.

- [ ] **Step 8: Commit** — submodul, apoi pointerul in parinte.

---

### Task 8C: Produsele care exista doar in catalog

**Files:**
- Create: `Website/db/migrate_catalog_products.php`
- Modify: `Website/db/extract_icons.php` (protectie impotriva regenerarii goale)
- Modify: `Website/admin/tests/test_products_repo.php`

**De ce exista:** `catalog.html` contine **84 de produse**, dintre care **40 nu exista pe nicio
pagina de categorie** si deci nu au ajuns niciodata in baza de date. Sunt cele adaugate in august
ca sa umple subcategoriile goale (corpuri de iluminat, aer conditionat, sobe, mobilier de baie,
bucatarie, living, dormitor). Inlocuirea catalogului cu versiunea din baza le-ar sterge de pe site.

Tot ele explica de ce **toate cele 17 subcategorii au azi zero produse** in baza: informatia de
subcategorie traieste doar in atributul `data-subcat` din `catalog.html`, pe 57 de carduri.

Dupa acest task, subcategoriile devin utilizabile si niciun produs nu se pierde.

- [ ] **Step 1: Scriptul de import**

Creeaza `Website/db/migrate_catalog_products.php`, care citeste `catalog.html` din istoricul git
(fisierul nu mai exista pe disc dupa conversia paginilor):

```bash
git -C Website show 41edc4b:catalog.html
```

Pentru fiecare card `.prod-card`, extrage numele (`.prod-name`), descrierea (`.prod-desc`),
`data-cat` (categorii de nivel 1, separate prin spatiu) si `data-subcat` daca exista.

Apoi, pentru fiecare produs:

- **daca numele exista deja in `products`** (44 de cazuri): daca are `data-subcat`, actualizeaza-i
  `category_id` catre subcategoria respectiva. Nu atinge nimic altceva — `page_slug`,
  `section_key`, proprietatile si fotografia raman cum sunt, deci randarea paginilor de categorie
  nu se schimba.
- **daca nu exista** (40 de cazuri): insereaza-l cu `page_slug = 'catalog'`,
  `section_key = 'catalog'`, `card_kind = 'material'`, iar `category_id` = subcategoria daca are
  una, altfel prima categorie din `data-cat`. Aceste produse apar **doar in catalog**, exact ca azi.
- pentru `data-cat` cu mai multe valori, adauga legaturile suplimentare in `product_categories`.

Scriptul trebuie sa fie **idempotent**: rerularea nu creeaza dubluri si nu schimba nimic in plus.

`page_slug = 'catalog'` le tine in afara paginilor de categorie: `productsForPage()` si
`productsForSection()` nu le vor returna niciodata, fiindca nicio pagina nu cere `'catalog'`.

- [ ] **Step 2: Ruleaza si verifica**

```bash
php Website/db/migrate_catalog_products.php
mysql -u root mugurel_cms -e "
  SELECT COUNT(*) AS total FROM products;
  SELECT COUNT(*) AS doar_catalog FROM products WHERE page_slug='catalog';
  SELECT c.slug, COUNT(p.id) AS n FROM categories c
    LEFT JOIN products p ON p.category_id=c.id
    WHERE c.parent_id IS NOT NULL AND c.slug NOT IN ('acoperis','izolatie','gips-carton','zidarie-bca','gard-imprejmuiri')
    GROUP BY c.slug ORDER BY n DESC;"
```

Expected: `total = 535` (495 + 40); `doar_catalog = 40`; iar subcategoriile care erau goale au
acum produse. Noteaza cate dintre cele 17 raman la zero — cele care raman se comporta ca azi,
afisand categoria parinte.

Verifica apoi ca **paginile de categorie nu s-au schimbat**: toate cele 13 trebuie sa randeze
exact aceleasi numere ca inainte (231 materiale si 264 accesorii in total, live prin Apache).
Asta e verificarea care conteaza — un produs mutat gresit s-ar vedea imediat.

- [ ] **Step 3: Protejeaza generatorul de iconite**

`Website/db/extract_icons.php` citeste SVG-uri din `Website/<pagina>.html`. Fisierele nu mai exista —
toate paginile sunt acum `.php`. Rulat asa cum e, suprascrie biblioteca cu **zero iconite**
si sparge tot site-ul.

Adauga o protectie: daca numarul de iconite extrase e mai mic decat cel din fisierul existent,
scriptul **refuza sa scrie** si iese cu un mesaj explicit si cod de eroare. In plus, fa-l sa
citeasca paginile din git cand nu le gaseste pe disc, dupa acelasi tipar ca scriptul de la Step 1.

Verifica: ruleaza `php Website/db/extract_icons.php` si confirma ca produce tot **86 de iconite** si ca
`git status` ramane curat pe fisierul generat.

- [ ] **Step 4: Testul care esua**

`test_products_repo.php` are o asertiune care cere ca subcategoria `cabluri` sa aiba produse.
Esua fiindca nicio subcategorie nu avea. Dupa acest task, verifica daca trece; daca `cabluri`
ramane goala si dupa import, inlocuieste asertiunea cu una care foloseste o subcategorie care
chiar are produse, si adauga o asertiune separata care confirma ca un filtru pe o subcategorie
goala intoarce `0` fara sa arunce.

Ruleaza ambele suite si raporteaza numerele.

- [ ] **Step 5: Commit** — scriptul si generatorul in parinte, testele in submodul, apoi pointerul.


---

### Task 8B: Pagina de catalog

**Files:**
- Create: `Website/catalog.php`
- Delete: `Website/catalog.html`
- Modify: `Website/js/main.js`

**Interfaces:**
- Consumes: `catalogProducts()`, `catalogCount()`, `categoryTree()`, `renderProdCards()`.

**De ce exista:** aici se rezolva problema de la care a plecat toata lucrarea — catalogul arata
**84 de produse** dintr-un total real de **495**.

- [ ] **Step 1: Prologul**

```bash
cp Website/catalog.html Website/catalog.php
```

La inceputul absolut al lui `Website/catalog.php`:

```php
<?php
require_once __DIR__ . '/includes/page_bootstrap.php';

const CATALOG_PER_PAGE = 60;

$cat   = isset($_GET['cat']) ? preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['cat'])) : null;
$q     = isset($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 80) : null;
$page  = max(1, (int)($_GET['page'] ?? 1));

$total    = catalogCount($cat, $q);
$pages    = max(1, (int)ceil($total / CATALOG_PER_PAGE));
$page     = min($page, $pages);
$produse  = catalogProducts($cat, $q, CATALOG_PER_PAGE, ($page - 1) * CATALOG_PER_PAGE);
$tree     = categoryTree();

/** Construieste un URL de catalog pastrand filtrele curente. */
function catUrl(?string $cat, ?string $q, int $page = 1): string {
    $p = [];
    if ($cat !== null && $cat !== '') { $p['cat'] = $cat; }
    if ($q !== null && $q !== '')     { $p['q'] = $q; }
    if ($page > 1)                     { $p['page'] = $page; }
    return 'catalog.html' . ($p ? '?' . http_build_query($p) : '');
}
?>
```

`(int)` pe `page` face ca `?page=abc` sa devina 1 in loc sa produca o eroare. `cat` e curatat de
orice in afara de litere mici, cifre si cratima.

- [ ] **Step 2: Grila si contorul**

Inlocuieste cele 84 de carduri din `<div class="prod-grid" id="prod-grid">` cu:

```php
<?= $produse ? renderProdCards($produse) : '' ?>
```

Contorul (`css`-ul il gaseste la `id="result-count"`) devine:

```php
Afisare: <strong><span id="result-count"><?= count($produse) ?></span></strong> din <?= $total ?> produse
```

Cand `$total === 0`, afiseaza mesajul de „niciun rezultat" existent (`id="no-results"`) in loc
de grila goala.

- [ ] **Step 3: Chipsuri si sidebar generate din baza**

Chipsurile si sidebar-ul sunt azi HTML scris de mana, cu slug-uri care **nu se potrivesc cu baza**:
`scule` vs `scule-unelte`, `gard` vs `gard-imprejmuiri`, `zidarie` vs `zidarie-bca`. Lasate asa,
filtrele astea ar returna zero produse.

Genereaza-le din `categoryTree()`, care intoarce cele 9 categorii de nivel 1 cu copiii lor.
Fiecare chip si fiecare intrare de sidebar devine un link catre `catUrl(<slug>, $q)`, iar cel
activ primeste clasa `active` **server-side**, dupa `$cat`.

Pastreaza exact clasele existente (`chip`, `sidebar-cat`, `sidebar-cat-head`, `sidebar-sub`,
`sidebar-sub-item`), ca stilurile si comportamentul de acordeon sa functioneze nemodificate.

- [ ] **Step 4: Cautarea pe server**

Campul de cautare devine un `<form method="get" action="catalog.html">` cu `name="q"`, si un
`<input type="hidden" name="cat">` care pastreaza filtrul curent. Valoarea curenta se
pre-completeaza cu `htmlspecialchars($q)`.

- [ ] **Step 5: Paginarea**

Sub grila:

```php
<?php if ($pages > 1): ?>
<nav class="pagination" aria-label="Paginare catalog">
  <?php if ($page > 1): ?><a href="<?= e(catUrl($cat, $q, $page - 1)) ?>" rel="prev">&laquo; Inapoi</a><?php endif; ?>
  <?php for ($i = 1; $i <= $pages; $i++):
        if ($i > 2 && $i < $pages - 1 && abs($i - $page) > 1) {
            if ($i === 3) { echo '<span class="gap">&hellip;</span>'; }
            continue;
        } ?>
    <?php if ($i === $page): ?><span class="current"><?= $i ?></span>
    <?php else: ?><a href="<?= e(catUrl($cat, $q, $i)) ?>"><?= $i ?></a><?php endif; ?>
  <?php endfor; ?>
  <?php if ($page < $pages): ?><a href="<?= e(catUrl($cat, $q, $page + 1)) ?>" rel="next">Inainte &raquo;</a><?php endif; ?>
</nav>
<?php endif; ?>
```

- [ ] **Step 6: Curata JavaScript-ul care nu mai are ce filtra**

Filtrarea si cautarea sunt acum pe server. In `Website/js/main.js`, elimina `SUBCAT_PARENT`
(liniile ~133-139), `CAT_LABELS` (~142-152), `filterProducts()`, `showFallbackNote()`,
`updateResultCount()` si `searchCatalog()`, plus handler-ul de click pe chipsuri care apela
`filterProducts`.

**Pastreaza** acordeonul din sidebar, butoanele WhatsApp (`data-wa-product` / `data-wa-text`),
meniul mobil si butonul flotant — nu au legatura cu filtrarea.

Verifica cu `grep` ca nicio pagina nu mai apeleaza functiile eliminate:
```bash
grep -rn 'filterProducts\|SUBCAT_PARENT\|CAT_LABELS\|updateResultCount\|searchCatalog' Website/ --include=*.php --include=*.html --include=*.js
```
Expected: zero rezultate.

Sterge si scriptul inline de la finalul lui `catalog.php` care citea `?cat=` si apela
`filterProducts` — filtrarea se face acum inainte ca pagina sa ajunga la browser.

- [ ] **Step 7: Verificari**

```bash
php -l Website/catalog.php
curl -s -o /dev/null -w "%{http_code}\n" http://localhost/catalog.html                      # 200
curl -s http://localhost/catalog.html | grep -o 'class="prod-card"' | wc -l                 # 60
curl -s "http://localhost/catalog.html?cat=electrice" | grep -o 'class="prod-card"' | wc -l # <= 60
curl -s "http://localhost/catalog.html?cat=cabluri" | grep -o 'class="prod-card"' | wc -l   # produsele subcategoriei
curl -s "http://localhost/catalog.html?q=cablu" | grep -o 'class="prod-card"' | wc -l       # > 0
curl -s "http://localhost/catalog.html?page=9" | grep -o 'class="prod-card"' | wc -l        # ultima pagina, > 0
curl -s "http://localhost/catalog.html?page=999" | grep -o 'class="prod-card"' | wc -l      # se limiteaza la ultima
curl -s "http://localhost/catalog.html?page=abc" -o /dev/null -w "%{http_code}\n"           # 200, nu 500
curl -s "http://localhost/catalog.html?cat=scule-unelte" | grep -o 'class="prod-card"' | wc -l  # > 0
```

Confirma si ca suma produselor pe toate paginile e **495**, si ca filtrarea pe fiecare dintre
cele 9 categorii de nivel 1 intoarce impreuna tot 495 (produsele cross-listate pot aparea de
doua ori — noteaza daca se intampla).

**Cu baza de date oprita**, catalogul trebuie sa raspunda tot 200, cu antetul, meniul si
footer-ul intacte, si zero carduri — nu 500.

- [ ] **Step 8: Commit**

```bash
cd Website && git rm catalog.html && git add catalog.php js/main.js && \
  git commit -m "feat: catalogul afiseaza toate produsele din baza de date, cu filtrare si paginare pe server" && cd ..
git add Website && git commit -m "chore: actualizeaza pointer Website submodul"
```


---

### Task 9A: Campurile de plasare si aspect in admin

**Files:**
- Modify: `Website/admin/views/products/edit.php`, `list.php`
- Modify: `Website/admin/controllers/ProductsController.php`
- Modify: `Website/admin/models/Product.php`

**De ce exista:** formularul de produs are azi 9 campuri, dar tabelul `products` are 21 de coloane.
Lipsesc exact cele care decid **unde apare** un produs si **cum arata**. Fara ele, un produs
adaugat din admin primeste `page_slug = ''`, deci nu apare pe nicio pagina si nici in catalog
sub o categorie utila — adminul ramane decorativ.

Campurile existente (`name`, `category_id`, `short_description`, `price`, `price_unit`,
`image`, `image_alt`, `is_visible`, `sort_order`) raman cum sunt.

**Campuri de adaugat:**

| Camp | Tip in formular | Note |
|---|---|---|
| `page_slug` | select | Cele 13 pagini, plus `catalog` (produs care apare doar in catalog). Obligatoriu. |
| `section_key` | select dependent | Sectiunile distincte ale paginii alese, plus optiunea de a scrie una noua |
| `card_kind` | radio | `material` sau `accessory` |
| `card_modifier` | select | Gol sau `sm`. Relevant doar pentru accesorii. |
| `icon_key` | select cu previzualizare | Cele 127 de iconite, randate langa fiecare optiune. Gol = iconita generica. |
| `icon_label` | text scurt | Eticheta de sub iconita. Optional. |
| `badge_label` | text scurt | Ex. „Cel mai vandut". Gol = fara badge. |
| `badge_kind` | select | Gol, `popular`, `premium`, `new`, `eco` |
| `wa_text` | textarea scurt | Gol = se genereaza sablonul automat. Arata sablonul ca placeholder. |

- [ ] **Step 1: Citeste codul existent**

```bash
ls Website/admin/views/products/
grep -n 'category_id\|short_description' Website/admin/views/products/edit.php Website/admin/controllers/ProductsController.php Website/admin/models/Product.php
```

Urmeaza tiparele de acolo — validare prin `Website/admin/helpers/Sanitize.php`, aceeasi structura
de formular, aceeasi maniera de a trata erorile. Nu introduce un tipar nou.

- [ ] **Step 2: Selectul de categorie devine ierarhic**

Azi listeaza plat 31 de categorii. Randeaza-l ierarhic, cu subcategoriile indentate sub parintii
lor, folosind `categoryTree()` din `Website/includes/products_repo.php`. Un `<optgroup>` per
categorie de nivel 1 e suficient.

- [ ] **Step 3: `section_key` dependent de pagina**

Sectiunile existente ale unei pagini:
```sql
SELECT DISTINCT section_key FROM products WHERE page_slug = ? ORDER BY section_key
```

Randeaza-le ca optiuni, plus un camp text pentru o sectiune noua. Fara JavaScript complicat:
un `<select>` populat la incarcare cu sectiunile **tuturor** paginilor, grupate cu `<optgroup>`
pe pagina, e acceptabil si mai robust decat un fetch dinamic.

**Important:** o sectiune noua nu apare pe site pana cand cineva nu adauga apelul corespunzator
in fisierul `.php` al paginii. Scrie asta explicit in interfata, langa camp — altfel Mugurel va
crea sectiuni care nu se vad nicaieri si nu va intelege de ce.

- [ ] **Step 4: Selectul de iconite cu previzualizare**

`PRODUCT_ICONS` din `Website/includes/product_icons.php` are 127 de intrari. Un `<select>` cu
127 de chei hexazecimale e inutilizabil. Randeaza in schimb o grila de radio-uri, fiecare cu
SVG-ul randat vizibil si cheia ca `value`. Include o optiune „fara iconita".

Grila trebuie sa incapa intr-un container cu `max-height` si scroll, ca sa nu domine formularul.

- [ ] **Step 5: Validare pe server**

In controller, inainte de salvare:

- `page_slug` trebuie sa fie una dintre cele 14 valori cunoscute (13 pagini + `catalog`)
- `section_key` trece prin `Sanitize::slug()`
- `card_kind` trebuie sa fie `material` sau `accessory`
- `card_modifier` trebuie sa fie gol sau `sm`
- `icon_key` trebuie sa existe in `PRODUCT_ICONS` sau sa fie gol
- `badge_kind` trebuie sa fie gol sau una dintre cele 4 valori cunoscute
- `wa_text` limitat la 400 de caractere, `Sanitize::text()`

Foloseste helperele existente din `Sanitize.php`. Nu scrie validare noua de la zero.

- [ ] **Step 6: Lista de produse arata plasarea**

In `list.php`, adauga coloane pentru pagina si sectiune, si un filtru dupa pagina. Cu 534 de
produse, o lista fara filtru dupa pagina e greu de folosit.

- [ ] **Step 7: Testeaza fluxul complet, manual**

Aceasta e verificarea care conteaza — e prima data cand o modificare facuta in admin devine
vizibila pe site.

1. Autentifica-te la `http://localhost/admin/` cu `Mugurel-Bricolaj` / `bricolaj1234!`
2. Adauga un produs de test: `page_slug = electrice`, sectiunea
   `produse-disponibile-in-magazin`, `card_kind = material`, o iconita aleasa, un badge
3. Incarca `http://localhost/electrice.html` si confirma ca apare in acea sectiune, cu iconita
   si badge-ul alese
4. Confirma ca apare si in catalog, cu badge-ul colorat corect pentru Electrice
5. Editeaza-i numele din admin, reincarca, confirma ca s-a schimbat
6. Sterge-l si confirma ca dispare din ambele locuri

Documenteaza fiecare pas cu ce ai observat. Daca vreun pas nu functioneaza, acolo e problema.

- [ ] **Step 8: Verificari de regresie**

- `products` revine la **534** dupa ce stergi produsul de test
- cele 13 pagini raman la **231 materiale + 264 accesorii**
- catalogul ramane la **534**
- ambele suite de teste trec

- [ ] **Step 9: Commit** — submodul, apoi pointerul in parinte.

---

### Task 9B: Editorul de proprietati

**Files:**
- Modify: `Website/admin/views/products/edit.php`
- Modify: `Website/admin/controllers/ProductsController.php`
- Create: `Website/admin/models/ProductProperty.php`

**De ce exista:** cardurile de material afiseaza **N blocuri de proprietati** — `Specificatii`,
`Avantaje` (lista cu bullet-uri), `Aspect`, `Utilizare`, `Unde se foloseste`. Sunt 464 de blocuri
pe 231 de produse. Fara editor, un produs adaugat din admin ar avea o singura descriere si ar
arata vizibil mai sarac decat vecinii lui de pe pagina.

Tabelul exista deja: `product_properties(id, product_id, sort_order, label, body, is_list)`.
Pentru `is_list = 1`, `body` contine cate un element pe linie.

- [ ] **Step 1: Modelul**

`Website/admin/models/ProductProperty.php`, dupa tiparul lui `Product.php`:
`forProduct(int $productId): array` si `replaceAll(int $productId, array $blocks): void`.

`replaceAll()` sterge si reinsereaza in tranzactie — acelasi tipar ca importul, care a fost
verificat ca idempotent. Simplifica mult fata de un diff pe randuri.

- [ ] **Step 2: Interfata de editare**

Sub descrierea scurta, o lista de blocuri. Fiecare bloc are:
- eticheta — `<input type="text">` cu o lista de sugestii (`<datalist>`) continand cele 6
  etichete folosite azi: Avantaje, Specificatii, Descriere, Aspect, Utilizare, Unde se foloseste
- continutul — `<textarea>`
- un checkbox „lista cu bullet-uri" care comuta `is_list`; cand e bifat, un text explicativ
  spune ca fiecare rand devine un element de lista
- butoane pentru stergerea blocului si pentru mutarea lui mai sus / mai jos

Plus un buton „adauga bloc".

Campurile se trimit ca array-uri indexate (`prop_label[]`, `prop_body[]`, `prop_is_list[]`),
iar `sort_order` se deduce din ordinea lor la salvare. E cel mai simplu mod de a suporta
reordonarea fara JavaScript complicat.

- [ ] **Step 3: Salvarea**

In controller, construieste array-ul de blocuri din cele trei array-uri paralele, ignorand
blocurile cu continut gol, si apeleaza `replaceAll()`. Fiecare `label` si `body` trece prin
`Sanitize::text()`.

Verifica lungimile: `label` maxim 60 de caractere (cat e coloana), `body` fara limita stricta
dar curatat de taguri.

- [ ] **Step 4: Testeaza pe un produs real**

1. Deschide in admin produsul `Cablu MYYM 3x1.5mm² (rola 100m)` de pe pagina Electrice,
   care are 3 blocuri (Aspect, Avantaje, Utilizare)
2. Confirma ca formularul le arata pe toate trei, in ordine, cu „Avantaje" marcat ca lista
3. Reordoneaza doua blocuri, salveaza, si confirma pe `http://localhost/electrice.html` ca
   ordinea s-a schimbat pe card
4. Adauga un bloc nou de tip lista cu doua elemente; confirma ca apare ca `<ul><li>` pe pagina
5. Readu produsul la starea initiala si confirma ca arata ca la inceput

- [ ] **Step 5: Verificari de regresie**

- `product_properties` revine la **464** dupa ce refaci produsul de test
- cele 13 pagini raman la 231 materiale + 264 accesorii
- ambele suite de teste trec

- [ ] **Step 6: Commit** — submodul, apoi pointerul in parinte.



## Dupa implementare

- [ ] Actualizeaza `Website/docs/JURNAL-LUCRU.md` cu o intrare noua in capul fisierului: ce s-a schimbat, capcana cu regula `-f` din `.htaccess`, si faptul ca `catalog.html`/paginile `.html` nu mai exista ca fisiere.
- [ ] Cere acordul pentru `git push`, apoi pentru **Deploy HEAD Commit** in cPanel si **Purge Everything** in Cloudflare.
- [ ] Sterge `Website/db/migrate_products.php` de pe server daca a fost urcat in `public_html`.

## Ramase deschise dupa acest plan

- Importul celor 3000+ de produse cand stocul devine disponibil digital. `products_repo.php` accepta deja `limit`/`offset`; interfata are nevoie de paginare si cautare.
- Editarea continutului editorial din admin — exclusa explicit din aceasta lucrare.
- Unificarea vizuala a celor doua carduri — exclusa explicit.
