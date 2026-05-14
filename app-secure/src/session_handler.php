<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

class SecureDbSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface {
    public function open($p, $n): bool { return true; }
    public function close(): bool { return true; }

    public function validateId($sid): bool {
        $stmt = db()->prepare('SELECT 1 FROM sessions WHERE sid = :sid');
        $stmt->execute([':sid' => $sid]);
        return (bool)$stmt->fetchColumn();
    }

    public function updateTimestamp($sid, $data): bool { return $this->write($sid, $data); }

    public function read($sid): string {
        $stmt = db()->prepare('SELECT payload, fingerprint, user_id FROM sessions WHERE sid = :sid');
        $stmt->execute([':sid' => $sid]);
        $row = $stmt->fetch();
        if (!$row) return '';

        // Détection de SID partagé : fingerprint divergent sur une session authentifiée → kill + audit
        if (!empty($row['user_id']) && $row['fingerprint']
            && !hash_equals($row['fingerprint'], request_fingerprint())) {
            audit('session_fingerprint_mismatch', 'critical', (int)$row['user_id'], $sid, [
                'stored'  => substr($row['fingerprint'], 0, 12),
                'current' => substr(request_fingerprint(), 0, 12),
            ]);
            $this->destroy($sid);
            return '';
        }
        return (string)$row['payload'];
    }

    public function write($sid, $data): bool {
        $userId = null;
        if (preg_match('/user_id\|i:(\d+);/', $data, $m)) $userId = (int)$m[1];

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $stmt = db()->prepare('
            INSERT INTO sessions (sid, user_id, ip_address, ua_hash, payload, fingerprint)
            VALUES (:sid, :uid, :ip, :uah, :pl, :fp)
            ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                ip_address = VALUES(ip_address),
                ua_hash = VALUES(ua_hash),
                payload = VALUES(payload),
                fingerprint = COALESCE(sessions.fingerprint, VALUES(fingerprint)),
                last_seen = CURRENT_TIMESTAMP
        ');
        return $stmt->execute([
            ':sid' => $sid, ':uid' => $userId,
            ':ip'  => $_SERVER['REMOTE_ADDR'] ?? '',
            ':uah' => hash('sha256', $ua),
            ':pl'  => $data,
            ':fp'  => request_fingerprint(),
        ]);
    }

    public function destroy($sid): bool {
        $stmt = db()->prepare('DELETE FROM sessions WHERE sid = :sid');
        return $stmt->execute([':sid' => $sid]);
    }

    public function gc($max): int {
        $stmt = db()->prepare('DELETE FROM sessions WHERE last_seen < (NOW() - INTERVAL :m SECOND)');
        $stmt->execute([':m' => $max]);
        return $stmt->rowCount();
    }
}

session_set_save_handler(new SecureDbSessionHandler(), true);

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => false, // mettre true derrière TLS
    'httponly' => true,
    'samesite' => 'Strict',
]);

session_start();
security_headers();

// Rotation périodique du SID toutes les 5 min (atténue le risque d'un SID compromis indétecté)
if (!empty($_SESSION['user_id'])) {
    $now = time();
    if (empty($_SESSION['_last_rotate']) || $now - $_SESSION['_last_rotate'] > 300) {
        $oldSid = session_id();
        session_regenerate_id(true);
        $_SESSION['_last_rotate'] = $now;
        audit('session_rotated', 'info', (int)$_SESSION['user_id'], session_id(), [
            'previous_sid' => substr($oldSid, 0, 12) . '...',
        ]);
    }
}
