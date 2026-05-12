<?php
// app-secure/src/db.php — PDO partagé

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $host = getenv('DB_HOST') ?: 'mysql';
        $name = getenv('DB_NAME') ?: 'bank_secure';
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

function audit(string $event, string $severity, ?int $userId, ?string $sid, array $detail = []): void {
    $stmt = db()->prepare('
        INSERT INTO audit_log (event, severity, user_id, sid, ip_address, user_agent, detail)
        VALUES (:e, :s, :u, :sid, :ip, :ua, :d)
    ');
    $stmt->execute([
        ':e'   => $event,
        ':s'   => $severity,
        ':u'   => $userId,
        ':sid' => $sid,
        ':ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ua'  => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ':d'   => json_encode($detail, JSON_UNESCAPED_UNICODE),
    ]);
    // Tracer aussi en stderr pour qu'un SIEM puisse récupérer via journald
    error_log("[AUDIT] $severity event=$event user=$userId sid=" . substr((string)$sid, 0, 8) . "... detail=" . json_encode($detail));
}
