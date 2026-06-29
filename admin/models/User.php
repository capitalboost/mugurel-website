<?php
class User {
    public static function all(): array {
        $stmt = Database::get()->query(
            'SELECT id, username, email, role, is_active, created_at, last_login_at FROM users ORDER BY role, username'
        );
        return $stmt->fetchAll();
    }

    public static function byId(int $id): ?array {
        $stmt = Database::get()->prepare('SELECT id, username, email, role, is_active FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int {
        $db = Database::get();
        $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $db->prepare('INSERT INTO users (username, email, password_hash, role, is_active) VALUES (?,?,?,?,?)');
        $stmt->execute([$data['username'], $data['email'], $hash, $data['role'], $data['is_active']]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void {
        $db = Database::get();
        if (!empty($data['password'])) {
            $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare('UPDATE users SET username=?, email=?, password_hash=?, role=?, is_active=? WHERE id=?')
               ->execute([$data['username'], $data['email'], $hash, $data['role'], $data['is_active'], $id]);
        } else {
            $db->prepare('UPDATE users SET username=?, email=?, role=?, is_active=? WHERE id=?')
               ->execute([$data['username'], $data['email'], $data['role'], $data['is_active'], $id]);
        }
    }

    public static function usernameExists(string $username, int $excludeId = 0): bool {
        $stmt = Database::get()->prepare('SELECT COUNT(*) FROM users WHERE username = ? AND id != ?');
        $stmt->execute([$username, $excludeId]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
