<?php
/**
 * Produsele catalogului nou in admin (pasul N7): lista cu filtre, citire completa, salvare, stergere.
 * Specificatiile, documentele si tag-urile se salveaza impreuna cu produsul, intr-o singura tranzactie.
 */
require_once __DIR__ . '/../../includes/catalog_text.php';
require_once __DIR__ . '/../../includes/catalog_rules.php';
require_once __DIR__ . '/../../includes/product_grid.php'; // formatQty() pentru stocul numarat

class CatalogProduct {
    const PER_PAGE = 50;
    const VERIFY = ['ok' => 'Verificat', 'de_verificat' => 'De verificat', 'negasit' => 'Negasit'];
    const STOCK = ['in_stoc' => 'In stoc', 'la_comanda' => 'La comanda', 'indisponibil' => 'Indisponibil'];

    /**
     * @param array $f ['q', 'cat' (id, include subcategoriile), 'verify', 'stock', 'photo' ('fara'), 'visible' ('0'|'1'), 'page']
     * @return array{rows: list<array>, total: int, pages: int, page: int}
     */
    public static function search(array $f): array {
        $where = ['p.barcode IS NOT NULL'];
        $params = [];
        if (($f['q'] ?? '') !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $where[] = '(p.name LIKE ? OR p.original_name LIKE ? OR p.barcode LIKE ? OR p.sku LIKE ? OR p.manufacturer LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if (!empty($f['cat'])) {
            $ids = self::descendantIds((int) $f['cat']);
            $where[] = 'p.category_id IN (' . implode(',', $ids) . ')';
        }
        if (isset(self::VERIFY[$f['verify'] ?? ''])) { $where[] = 'p.verify_status = ?'; $params[] = $f['verify']; }
        if (isset(self::STOCK[$f['stock'] ?? ''])) { $where[] = 'p.stock_status = ?'; $params[] = $f['stock']; }
        if (($f['photo'] ?? '') === 'fara') { $where[] = "(p.image_path IS NULL OR p.image_path = '')"; }
        if (($f['photo'] ?? '') === 'cu') { $where[] = "(p.image_path IS NOT NULL AND p.image_path <> '')"; }
        if (($f['visible'] ?? '') === '0' || ($f['visible'] ?? '') === '1') { $where[] = 'p.is_visible = ?'; $params[] = (int) $f['visible']; }
        if (($f['price'] ?? '') === 'fara') { $where[] = '(p.price IS NULL OR p.price = 0)'; }

        $db = Database::get();
        $sqlWhere = ' WHERE ' . implode(' AND ', $where);
        $stmt = $db->prepare('SELECT COUNT(*) FROM products p' . $sqlWhere);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($pages, max(1, (int) ($f['page'] ?? 1)));
        $stmt = $db->prepare('SELECT p.id, p.barcode, p.sku, p.slug, p.name, p.manufacturer, p.image_path, p.price, p.price_unit,
                p.stock_status, p.stock_qty, p.verify_status, p.is_visible, c.name AS category_name
            FROM products p JOIN categories c ON c.id = p.category_id' . $sqlWhere
            . ' ORDER BY ' . self::categoryOrderSql() . ', p.sort_order, p.id LIMIT ' . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE));
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(), 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /** Arborele categoriilor ca lista plata, cu indentare (pentru <select>). @return list<array{id:int, label:string, depth:int}> */
    public static function categoryOptions(): array {
        $rows = Database::get()->query('SELECT id, parent_id, name FROM categories ORDER BY sort_order, name')->fetchAll();
        $children = [];
        foreach ($rows as $r) { $children[(int) $r['parent_id']][] = $r; }
        $out = [];
        $walk = function (int $parent, int $depth) use (&$walk, &$out, $children): void {
            foreach ($children[$parent] ?? [] as $r) {
                $out[] = ['id' => (int) $r['id'], 'label' => $r['name'], 'depth' => $depth];
                $walk((int) $r['id'], $depth + 1);
            }
        };
        $walk(0, 0);
        return $out;
    }

    /** Ordinea produselor dupa locul categoriei in arbore (ca pe site), nu dupa numarul din fisierul de import. */
    private static function categoryOrderSql(): string {
        $ids = array_column(self::categoryOptions(), 'id');
        return $ids ? 'FIELD(p.category_id, ' . implode(',', array_map('intval', $ids)) . ')' : 'p.category_id';
    }

    public static function descendantIds(int $id): array {
        $rows = Database::get()->query('SELECT id, parent_id FROM categories')->fetchAll();
        $children = [];
        foreach ($rows as $r) { $children[(int) $r['parent_id']][] = (int) $r['id']; }
        $ids = [$id];
        for ($i = 0; $i < count($ids); $i++) { foreach ($children[$ids[$i]] ?? [] as $c) { $ids[] = $c; } }
        return $ids;
    }

    public static function byId(int $id): ?array {
        $db = Database::get();
        $stmt = $db->prepare('SELECT p.*, c.slug AS category_slug FROM products p JOIN categories c ON c.id = p.category_id WHERE p.id = ?');
        $stmt->execute([$id]);
        $p = $stmt->fetch();
        if (!$p) { return null; }
        $p['specs'] = $db->query("SELECT spec_key, spec_value FROM product_specs WHERE product_id = $id ORDER BY sort_order")->fetchAll();
        $p['documents'] = $db->query("SELECT url FROM product_documents WHERE product_id = $id ORDER BY sort_order")->fetchAll(PDO::FETCH_COLUMN);
        $p['tags'] = $db->query("SELECT t.name, t.grp FROM product_tags pt JOIN tags t ON t.id = pt.tag_id WHERE pt.product_id = $id ORDER BY t.grp, t.name")->fetchAll();
        return $p;
    }

    /**
     * Valideaza ce vine din formular. @return array{0: array, 1: list<string>} [date curate, erori]
     */
    public static function validate(array $in, ?array $existing): array {
        $err = [];
        $text = fn (string $k, int $max) => mb_substr(trim(strip_tags((string) ($in[$k] ?? ''))), 0, $max);
        $d = [
            'name' => $text('name', 200), 'sku' => $text('sku', 64), 'manufacturer' => $text('manufacturer', 120),
            'product_type' => $text('product_type', 120), 'short_description' => $text('short_description', 1000),
            'price_unit' => $text('price_unit', 40), 'image_alt' => $text('image_alt', 200),
            'internal_notes' => mb_substr(trim((string) ($in['internal_notes'] ?? '')), 0, 5000),
            'description_html' => cleanDescriptionHtml((string) ($in['description_html'] ?? '')),
            'category_id' => max(0, (int) ($in['category_id'] ?? 0)),
            'sort_order' => min(65535, max(0, (int) ($in['sort_order'] ?? 0))),
            'is_visible' => !empty($in['is_visible']) ? 1 : 0,
            'stock_status' => (string) ($in['stock_status'] ?? 'in_stoc'),
            'verify_status' => (string) ($in['verify_status'] ?? ''),
        ];
        if ($d['name'] === '') { $err[] = 'Numele e obligatoriu.'; }
        $cat = Database::get()->prepare('SELECT COUNT(*) FROM categories WHERE id = ?');
        $cat->execute([$d['category_id']]);
        if ((int) $cat->fetchColumn() === 0) { $err[] = 'Alege o categorie.'; }
        if (!isset(self::STOCK[$d['stock_status']]) && $d['stock_status'] !== 'cantitate') { $err[] = 'Starea de stoc nu e valida.'; }
        // „Numar pe stoc": cantitatea se scade la fiecare comanda; starea afisata urmeaza cantitatea (0 => indisponibil)
        $d['stock_qty'] = null;
        // cantitatea din baza cand s-a deschis formularul: la salvare se aplica doar diferenta (vanzarile facute intre timp raman)
        $origQty = str_replace([' ', ','], ['', '.'], (string) ($in['stock_qty_orig'] ?? ''));
        $d['stock_qty_orig'] = is_numeric($origQty) ? round((float) $origQty, 2) : null;
        if ($d['stock_status'] === 'cantitate') {
            $rawQty = str_replace([' ', ','], ['', '.'], (string) ($in['stock_qty'] ?? ''));
            if (is_numeric($rawQty) && (float) $rawQty >= 0 && (float) $rawQty < 100000000) {
                $d['stock_qty'] = round((float) $rawQty, 2);
                $d['stock_status'] = $d['stock_qty'] > 0 ? 'in_stoc' : 'indisponibil';
            } else {
                $err[] = 'Scrie cate unitati sunt pe stoc (0 sau mai mult, ex. 12 sau 7,5).';
                $d['stock_status'] = 'in_stoc';
            }
        }
        if ($d['verify_status'] === '') { $d['verify_status'] = null; }
        elseif (!isset(self::VERIFY[$d['verify_status']])) { $err[] = 'Starea de verificare nu e valida.'; }

        $rawPrice = str_replace([' ', "\u{00A0}"], '', (string) ($in['price'] ?? ''));
        if (str_contains($rawPrice, ',') && str_contains($rawPrice, '.')) { $rawPrice = str_replace('.', '', $rawPrice); }
        $rawPrice = str_replace(',', '.', $rawPrice);
        if ($rawPrice === '') { $d['price'] = null; }
        elseif (is_numeric($rawPrice) && (float) $rawPrice > 0 && (float) $rawPrice < 100000000) { $d['price'] = round((float) $rawPrice, 2); }
        else { $err[] = 'Pretul trebuie sa fie un numar mai mare ca 0 (ex. 25,24), sau gol pentru „Pret la cerere".'; $d['price'] = null; }

        $barcode = trim((string) ($in['barcode'] ?? ''));
        if ($barcode === '' && $existing === null) {
            $barcode = 'INT-' . date('ymd') . random_int(1000, 9999); // produs adaugat de mana, fara cod de bare
        }
        if ($existing !== null && $barcode === '') { $barcode = (string) $existing['barcode']; }
        if (!preg_match('/^[0-9A-Za-z-]{4,32}$/', $barcode)) { $err[] = 'Codul de bare poate avea doar cifre, litere si „-" (4–32 caractere).'; }
        else {
            $dup = Database::get()->prepare('SELECT id FROM products WHERE barcode = ? AND id <> ?');
            $dup->execute([$barcode, (int) ($existing['id'] ?? 0)]);
            if ($dup->fetchColumn()) { $err[] = "Codul de bare $barcode exista deja la alt produs."; }
        }
        $d['barcode'] = $barcode;

        // specificatii: perechi cheie/valoare, randurile goale se ignora
        $d['specs'] = [];
        foreach ((array) ($in['spec_key'] ?? []) as $i => $k) {
            $k = mb_substr(trim(strip_tags((string) $k)), 0, 120);
            $v = mb_substr(trim(strip_tags((string) (($in['spec_value'] ?? [])[$i] ?? ''))), 0, 2000);
            if ($k !== '' && $v !== '') { $d['specs'][] = ['key' => $k, 'value' => $v]; }
        }
        // documente: un link pe rand, doar http(s) — sau o fisa gazduita pe site (/fise-tehnice/...pdf)
        $d['documents'] = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) ($in['documents'] ?? '')) ?: [] as $line) {
            $u = trim($line);
            if ($u === '') { continue; }
            if (!preg_match('#^(https?://[^\s<>"\']+|/fise-tehnice/[A-Za-z0-9._-]+\.pdf)$#i', $u) || strlen($u) > 1000) { $err[] = "Link de document invalid: " . mb_substr($u, 0, 80); continue; }
            $d['documents'][] = $u;
        }
        // tag-uri: separate prin „;" (virgula ramane pentru zecimale: „grosime 1,5 mm")
        $d['tags'] = [];
        foreach (explode(';', (string) ($in['tags'] ?? '')) as $t) {
            $t = mb_substr(trim(strip_tags($t)), 0, 120);
            if ($t !== '' && slugify($t) !== '') { $d['tags'][mb_strtolower($t)] = $t; }
        }
        $d['tags'] = array_values($d['tags']);
        return [$d, $err];
    }

    /** Creeaza sau actualizeaza. @return int id-ul produsului */
    public static function save(?int $id, array $d, ?string $imagePath, int $userId): int {
        $db = Database::get();
        $db->beginTransaction();
        try {
            $cols = ['barcode', 'sku', 'category_id', 'name', 'manufacturer', 'product_type', 'short_description', 'description_html',
                     'image_path', 'image_alt', 'price', 'price_unit', 'stock_status', 'stock_qty', 'verify_status', 'internal_notes', 'is_visible', 'sort_order'];
            $vals = [$d['barcode'], $d['sku'] ?: null, $d['category_id'], $d['name'], $d['manufacturer'] ?: null, $d['product_type'] ?: null,
                     $d['short_description'] ?: (shortFromDescription($d['description_html']) ?: null), $d['description_html'] ?: null,
                     $imagePath, $d['image_alt'] ?: null, $d['price'], $d['price_unit'] ?: null, $d['stock_status'], $d['stock_qty'], $d['verify_status'],
                     $d['internal_notes'] ?: null, $d['is_visible'], $d['sort_order']];
            // Stoc numarat si inainte, si acum: nu rescriem cifra din formular (intre deschidere si salvare pot fi intrat
            // comenzi), ci aplicam doar ce a schimbat adminul: 10 -> 12 in formular = +2 peste stocul de acum.
            $delta = ($id && ($d['stock_qty'] ?? null) !== null && ($d['stock_qty_orig'] ?? null) !== null)
                ? $d['stock_qty'] - $d['stock_qty_orig'] : null;
            if ($delta !== null) {
                $skip = array_keys(array_intersect($cols, ['stock_status', 'stock_qty']));
                foreach ($skip as $k) { unset($cols[$k], $vals[$k]); }
                $cols = array_values($cols); $vals = array_values($vals);
            }
            if ($id) {
                $db->prepare('UPDATE products SET ' . implode(', ', array_map(fn ($c) => "$c = ?", $cols)) . ', price_is_from = 0 WHERE id = ?')
                   ->execute(array_merge($vals, [$id]));
                if ($delta !== null) {
                    // (nu ne bazam pe rowCount: o diferenta 0 nu „modifica" randul, desi stocul e numarat)
                    $cur = $db->prepare('SELECT stock_qty FROM products WHERE id = ? FOR UPDATE');
                    $cur->execute([$id]);
                    if ($cur->fetchColumn() !== null) {
                        $db->prepare("UPDATE products SET stock_qty = GREATEST(0, stock_qty + ?),
                                             stock_status = IF(stock_qty > 0, 'in_stoc', 'indisponibil') WHERE id = ?")
                           ->execute([$delta, $id]);
                    } else { // stocul nu mai era numarat intre timp: scriem cifra din formular
                        $db->prepare('UPDATE products SET stock_qty = ?, stock_status = ? WHERE id = ?')
                           ->execute([$d['stock_qty'], $d['stock_status'], $id]);
                    }
                }
            } else {
                $slug = slugify($d['name']);
                $taken = $db->prepare('SELECT COUNT(*) FROM products WHERE slug = ?');
                $taken->execute([$slug]);
                if ((int) $taken->fetchColumn() > 0) { $slug = slugify($d['name'], 105) . '-' . substr(preg_replace('/\D/', '', $d['barcode']) ?: bin2hex(random_bytes(3)), -6); }
                $db->prepare('INSERT INTO products (' . implode(', ', $cols) . ', slug, page_slug, card_kind, price_is_from, created_by)
                              VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ", ?, '', 'material', 0, ?)")
                   ->execute(array_merge($vals, [$slug, $userId]));
                $id = (int) $db->lastInsertId();
            }

            $db->prepare('DELETE FROM product_specs WHERE product_id = ?')->execute([$id]);
            $ins = $db->prepare('INSERT INTO product_specs (product_id, sort_order, spec_key, spec_value) VALUES (?, ?, ?, ?)');
            foreach ($d['specs'] as $i => $s) { $ins->execute([$id, $i + 1, $s['key'], $s['value']]); }

            $db->prepare('DELETE FROM product_documents WHERE product_id = ?')->execute([$id]);
            $ins = $db->prepare('INSERT INTO product_documents (product_id, sort_order, url, label) VALUES (?, ?, ?, ?)');
            foreach ($d['documents'] as $i => $u) { $ins->execute([$id, $i + 1, $u, 'Fisa tehnica']); }

            $db->prepare('DELETE FROM product_tags WHERE product_id = ?')->execute([$id]);
            $find = $db->prepare('SELECT id FROM tags WHERE slug = ?');
            $newTag = $db->prepare('INSERT INTO tags (slug, name, grp) VALUES (?, ?, ?)');
            $link = $db->prepare('INSERT IGNORE INTO product_tags (product_id, tag_id) VALUES (?, ?)');
            foreach ($d['tags'] as $t) {
                $slug = slugify($t, 120);
                $find->execute([$slug]);
                $tagId = $find->fetchColumn();
                if ($tagId === false) {
                    $grp = classifyTag($t, $d['manufacturer'] ? [mb_strtolower($d['manufacturer'])] : [], $d['product_type'] ? [mb_strtolower($d['product_type'])] : []);
                    if ($grp === 'ignorat') { continue; }
                    $newTag->execute([$slug, $t, $grp]);
                    $tagId = (int) $db->lastInsertId();
                }
                $link->execute([$id, (int) $tagId]);
            }
            $db->commit();
            return $id;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /** Sterge produsul (specificatiile, documentele, tag-urile pleaca in cascada). @return ?string poza de sters de pe disc */
    public static function delete(int $id): ?string {
        $p = self::byId($id);
        if (!$p) { return null; }
        Database::get()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
        return $p['image_path'] ?: null;
    }
}
