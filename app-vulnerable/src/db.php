<?php
// app-vulnerable/src/db.php — connexion PDO partagée

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $host = getenv('DB_HOST') ?: 'mysql';
        $name = getenv('DB_NAME') ?: 'bank_vuln';
        $user = getenv('DB_USER') ?: 'bankuser';
        $pass = getenv('DB_PASS') ?: 'bankpass';
        $dsn  = "mysql:host=$host;dbname=$name;charset=utf8mb4";
        $pdo  = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
