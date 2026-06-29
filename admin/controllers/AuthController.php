<?php
// Inclus din index.php — $page, $action disponibile

$db = Database::get();

if ($page === 'logout') {
    Auth::logout();
    header('Location: /admin/?page=login');
    exit;
}

// page = login
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();

    $username = Sanitize::text($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Brute-force check
    $ip = inet_pton($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $db->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL " . LOGIN_WINDOW_MINUTES . " MINUTE)")->execute();
    $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL " . LOGIN_WINDOW_MINUTES . " MINUTE)");
    $stmt->execute([$ip]);
    if ((int)$stmt->fetchColumn() >= LOGIN_MAX_ATTEMPTS) {
        $error = 'Prea multe încercări. Așteaptă ' . LOGIN_WINDOW_MINUTES . ' minute.';
    } else {
        $stmt = $db->prepare("SELECT id, username, role, password_hash FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        $dummyHash = '$2y$12$invaliddummyhashfortimingXXXXXXXXXXXXXXXXXXXXXXXX';
        $hashToCheck = $user ? $user['password_hash'] : $dummyHash;
        $valid = password_verify($password, $hashToCheck);

        if (!$user || !$valid) {
            $db->prepare("INSERT INTO login_attempts (ip, attempted_at) VALUES (?, NOW())")->execute([$ip]);
            $error = 'Username sau parolă incorecte.';
        } else {
            $db->prepare("DELETE FROM login_attempts WHERE ip = ?")->execute([$ip]);
            if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
                $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newHash, $user['id']]);
            }
            $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);
            $db->prepare("INSERT INTO audit_log (user_id, entity_type, action, ip_address) VALUES (?, 'auth', 'login', ?)")
               ->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? '']);
            Auth::login($user);
            header('Location: /admin/');
            exit;
        }
    }
}

require __DIR__ . '/../views/login.php';
