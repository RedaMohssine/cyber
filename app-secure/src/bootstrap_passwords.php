<?php
// app-secure/src/bootstrap_passwords.php
// Rehash les mots de passe en clair du seed au premier démarrage,
// pour que password_verify fonctionne avec bcrypt.

require_once __DIR__ . '/db.php';

$seed = [
    'alice'          => 'Password123!',
    'bob'            => 'BobPass456!',
    'mallory'        => 'EvilPass1!',
    'admin_honeypot' => 'D0_n0t_use!',
];

try {
    $stmt = db()->prepare('UPDATE users SET password_hash = :h WHERE username = :u');
    foreach ($seed as $username => $plain) {
        $hash = password_hash($plain, PASSWORD_BCRYPT, ['cost' => 10]);
        $stmt->execute([':h' => $hash, ':u' => $username]);
    }
    echo "[bootstrap] passwords rehashed at " . date('c') . "\n";
} catch (PDOException $e) {
    fwrite(STDERR, "[bootstrap] DB not ready: " . $e->getMessage() . "\n");
    exit(1);
}
