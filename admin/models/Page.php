<?php
class Page {
    public static function all(): array {
        $stmt = Database::get()->query(
            'SELECT id, slug, title, restricted, is_published, updated_at FROM pages ORDER BY restricted, title'
        );
        return $stmt->fetchAll();
    }

    public static function byId(int $id): ?array {
        $stmt = Database::get()->prepare('SELECT * FROM pages WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function bySlug(string $slug): ?array {
        $stmt = Database::get()->prepare('SELECT * FROM pages WHERE slug = ? AND is_published = 1');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public static function update(int $id, array $data, int $userId): void {
        Database::get()->prepare(
            'UPDATE pages SET title=?, content=?, meta_title=?, meta_description=?, is_published=?, updated_by=? WHERE id=?'
        )->execute([
            $data['title'], $data['content'], $data['meta_title'],
            $data['meta_description'], $data['is_published'], $userId, $id,
        ]);
    }
}
