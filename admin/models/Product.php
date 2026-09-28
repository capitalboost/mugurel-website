<?php
class Product {
    /** Cele 13 pagini de categorie plus 'catalog' — singurele valori valide pentru page_slug. */
    const KNOWN_PAGE_SLUGS = [
        'acoperis', 'apa-canal', 'catalog', 'electrice', 'electrocasnice',
        'gard-imprejmuiri', 'gips-carton', 'gradina', 'incalzire', 'izolatie',
        'mobilier', 'sanitare', 'scule-unelte', 'zidarie-bca',
    ];

    /** Singurele valori valide pentru badge_kind, in afara de gol. */
    const KNOWN_BADGE_KINDS = ['popular', 'premium', 'new', 'eco'];

    public static function all(int $categoryId = 0, string $search = '', string $pageSlug = ''): array {
        $db = Database::get();
        $sql = 'SELECT p.*, c.name AS category_name
                FROM products p
                JOIN categories c ON c.id = p.category_id
                WHERE 1=1';
        $params = [];
        if ($categoryId > 0) {
            $sql .= ' AND p.category_id = ?';
            $params[] = $categoryId;
        }
        if ($pageSlug !== '') {
            $sql .= ' AND p.page_slug = ?';
            $params[] = $pageSlug;
        }
        if ($search !== '') {
            $sql .= ' AND (p.name LIKE ? OR p.short_description LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        $sql .= ' ORDER BY c.sort_order, p.sort_order, p.name';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Perechile distincte page_slug/section_key existente, grupate pe pagina, pentru selectul dependent din formular. */
    public static function sectionsByPage(): array {
        $rows = Database::get()->query(
            "SELECT DISTINCT page_slug, section_key FROM products
             WHERE section_key IS NOT NULL AND section_key <> ''
             ORDER BY page_slug, section_key"
        )->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['page_slug']][] = $r['section_key'];
        }
        return $out;
    }

    public static function byId(int $id): ?array {
        $stmt = Database::get()->prepare(
            'SELECT p.*, c.slug AS category_slug FROM products p
             JOIN categories c ON c.id = p.category_id WHERE p.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int {
        $db = Database::get();
        $stmt = $db->prepare(
            'INSERT INTO products
             (category_id, name, short_description, image_path, image_alt, price, price_unit,
              is_visible, sort_order, created_by, page_slug, section_key, card_kind, card_modifier,
              icon_key, icon_label, badge_label, badge_kind, wa_text)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $data['category_id'], $data['name'],  $data['short_description'],
            $data['image_path'],  $data['image_alt'], $data['price'],
            $data['price_unit'],  $data['is_visible'], $data['sort_order'],
            $data['created_by'],
            $data['page_slug'], $data['section_key'], $data['card_kind'], $data['card_modifier'],
            $data['icon_key'], $data['icon_label'], $data['badge_label'], $data['badge_kind'],
            $data['wa_text'],
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void {
        Database::get()->prepare(
            'UPDATE products SET
             category_id=?, name=?, short_description=?, image_path=?,
             image_alt=?, price=?, price_unit=?, is_visible=?, sort_order=?,
             page_slug=?, section_key=?, card_kind=?, card_modifier=?,
             icon_key=?, icon_label=?, badge_label=?, badge_kind=?, wa_text=?
             WHERE id=?'
        )->execute([
            $data['category_id'], $data['name'], $data['short_description'],
            $data['image_path'],  $data['image_alt'], $data['price'],
            $data['price_unit'],  $data['is_visible'], $data['sort_order'],
            $data['page_slug'], $data['section_key'], $data['card_kind'], $data['card_modifier'],
            $data['icon_key'], $data['icon_label'], $data['badge_label'], $data['badge_kind'],
            $data['wa_text'], $id,
        ]);
    }

    public static function delete(int $id): ?string {
        $product = self::byId($id);
        if (!$product) return null;
        Database::get()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
        return $product['image_path'] ?? null;
    }

    public static function categories(): array {
        $stmt = Database::get()->query('SELECT id, slug, name FROM categories WHERE is_active=1 ORDER BY sort_order');
        return $stmt->fetchAll();
    }
}
