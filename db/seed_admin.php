<?php
// Creează contul de admin. Rulează DOAR din CLI, o singură dată:
//   ADMIN_USER=... ADMIN_EMAIL=... ADMIN_PASSWORD=... php db/seed_admin.php
// Dacă ADMIN_PASSWORD lipsește, se generează o parolă aleatoare, afișată o singură dată.
// Credentialele DB vin din admin/config.php -> admin/config.local.php (negituit).
// Nu pune parole sau credentiale în acest fișier.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Doar din CLI.\n");
}

require_once __DIR__ . '/../admin/config.php';

$username = getenv('ADMIN_USER') ?: 'Mugurel-Bricolaj';
$email    = getenv('ADMIN_EMAIL') ?: '';
$password = getenv('ADMIN_PASSWORD') ?: '';
$generated = false;

if ($email === '') {
    fwrite(STDERR, "Setează ADMIN_EMAIL.\n");
    exit(1);
}
if ($password === '') {
    $password  = bin2hex(random_bytes(12));
    $generated = true;
}
if (strlen($password) < 12) {
    fwrite(STDERR, "Parola trebuie să aibă minim 12 caractere.\n");
    exit(1);
}

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$stmt = $pdo->prepare(
    "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'admin')"
);
$stmt->execute([$username, $email, $hash]);

echo "Admin creat (ID " . $pdo->lastInsertId() . ").\n";
if ($generated) {
    echo "Parola generată (salveaz-o acum, nu mai este afișată): $password\n";
}
