<?php
// VULN: vide $_SESSION mais ne détruit pas le cookie ni l'entrée BDD → le SID reste réutilisable
require_once __DIR__ . '/../src/session_handler.php';

$_SESSION = [];

header('Location: /login.php?msg=Vous+avez+%C3%A9t%C3%A9+d%C3%A9connect%C3%A9.');
exit;
