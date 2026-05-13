<?php
// app-vulnerable/src/session_handler.php
// Gestionnaire de session persistant en BDD — VOLONTAIREMENT VULNÉRABLE
// La table `sessions` permet à la démo de prouver visuellement le partage du SID.

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

        // user_id extrait du payload sérialisé (best-effort, sans validation)
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

// === VULNÉRABILITÉ FONDAMENTALE ===
// session.use_strict_mode = 0  → on accepte n'importe quel SID fourni par le client
// session.use_trans_sid = 1    → on accepte le SID via URL (?PHPSESSID=...)
// Aucun session_regenerate_id() après authentification (voir login.php)
session_start();

// PHP ne set pas automatiquement le cookie quand le SID arrive via URL (trans-sid).
// On le force ici pour que les redirections (Location: /dashboard.php) conservent la session.
// VULNÉRABILITÉ : on accepte sans vérification le SID imposé par le client.
if (!isset($_COOKIE[session_name()])) {
    setcookie(session_name(), session_id(), ['path' => '/']);
}
