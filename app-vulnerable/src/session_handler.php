<?php
require_once __DIR__ . '/db.php';

class DbSessionHandler implements SessionHandlerInterface {
    public function open($path, $name): bool { return true; }
    public function close(): bool { return true; }

    public function read($sid): string {
        $stmt = db()->prepare('SELECT payload FROM sessions WHERE sid = :sid');
        $stmt->execute([':sid' => $sid]);
        $row = $stmt->fetch();
        return $row ? (string)$row['payload'] : '';
    }

    public function write($sid, $data): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $userId = null;
        if (preg_match('/user_id\|i:(\d+);/', $data, $m)) {
            $userId = (int)$m[1];
        }
        $stmt = db()->prepare('
            INSERT INTO sessions (sid, user_id, ip_address, user_agent, payload)
            VALUES (:sid, :uid, :ip, :ua, :payload)
            ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent),
                payload = VALUES(payload),
                last_seen = CURRENT_TIMESTAMP
        ');
        return $stmt->execute([
            ':sid' => $sid, ':uid' => $userId,
            ':ip'  => $ip,  ':ua'  => $ua, ':payload' => $data,
        ]);
    }

    public function destroy($sid): bool {
        $stmt = db()->prepare('DELETE FROM sessions WHERE sid = :sid');
        return $stmt->execute([':sid' => $sid]);
    }

    public function gc($max): int {
        $stmt = db()->prepare('DELETE FROM sessions WHERE last_seen < (NOW() - INTERVAL :max SECOND)');
        $stmt->execute([':max' => $max]);
        return $stmt->rowCount();
    }
}

session_set_save_handler(new DbSessionHandler(), true);

// VULN: use_strict_mode=0 + use_trans_sid=1 → accepte tout SID client, y compris via URL
// VULN: aucun session_regenerate_id() après authentification (voir login.php)
session_start();

// PHP ne set pas le cookie quand le SID arrive via URL — on le force pour conserver la session après redirect
if (!isset($_COOKIE[session_name()])) {
    setcookie(session_name(), session_id(), ['path' => '/']);
}
