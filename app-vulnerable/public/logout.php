<?php
// app-vulnerable/public/logout.php
require_once __DIR__ . '/../src/session_handler.php';

// VULN : on vide $_SESSION mais on NE détruit ni le cookie ni l'entrée DB.
// Le SID reste donc utilisable par l'attaquant qui le connaît.
$_SESSION = [];

header('Location: /login.php?msg=Vous+avez+%C3%A9t%C3%A9+d%C3%A9connect%C3%A9.');
exit;
