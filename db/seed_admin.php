<?php
// Rulează o singură dată: uploadează temporar în public_html, accesează prin browser, ȘTERGE după
// SAU rulează din CLI: php db/seed_admin.php

define('DB_HOST', 'localhost');
define('DB_NAME', 'mugurel_cms');
define('DB_USER', 'mugurel_db');
define('DB_PASS', 'SCHIMBA_PAROLA_AICI');

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$username = 'Mugurel-Bricolaj';
$email    = 'admin@mugurel-bricolaj.ro';
$password = 'bricolaj1234!';
$hash     = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$stmt = $pdo->prepare(
    "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'admin')"
);
$stmt->execute([$username, $email, $hash]);
echo "Admin creat cu ID: " . $pdo->lastInsertId() . "\n";
echo "STERGE ACEST FISIER ACUM.\n";
