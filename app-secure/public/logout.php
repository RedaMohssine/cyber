<?php
require_once __DIR__ . '/../src/session_handler.php';

$uid = $_SESSION['user_id'] ?? null;
$sid = session_id();

// Vider, régénérer, détruire — destruction complète + suppression cookie
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
audit('logout', 'info', $uid, $sid, []);

header('Location: /login.php?msg=Vous+avez+%C3%A9t%C3%A9+d%C3%A9connect%C3%A9.');
exit;
