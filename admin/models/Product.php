<?php
class Product {
    public static function all(int $categoryId = 0, string $search = ''): array {
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
             (category_id, name, short_description, image_path, image_alt, price, price_unit, is_visible, sort_order, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $data['category_id'], $data['name'],  $data['short_description'],
            $data['image_path'],  $data['image_alt'], $data['price'],
            $data['price_unit'],  $data['is_visible'], $data['sort_order'],
            $data['created_by'],
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void {
        Database::get()->prepare(
            'UPDATE products SET
             category_id=?, name=?, short_description=?, image_path=?,
             image_alt=?, price=?, price_unit=?, is_visible=?, sort_order=?
             WHERE id=?'
        )->execute([
            $data['category_id'], $data['name'], $data['short_description'],
            $data['image_path'],  $data['image_alt'], $data['price'],
            $data['price_unit'],  $data['is_visible'], $data['sort_order'], $id,
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
